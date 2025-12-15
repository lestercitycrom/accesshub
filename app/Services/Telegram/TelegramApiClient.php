<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TelegramApiClient
{
	/**
	 * @param array<string, mixed>|null $replyMarkup
	 */
	public function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null, ?string $parseMode = null): void
	{
		$payload = [
			'chat_id' => $chatId,
			'text' => $this->sanitizeUtf8($text),
			'disable_web_page_preview' => true,
			'reply_markup' => $replyMarkup,
		];

		if ($parseMode !== null) {
			$payload['parse_mode'] = $parseMode;
		}

		$this->post('sendMessage', $payload);
	}

	/**
	 * @param array<string, mixed>|null $replyMarkup
	 */
	public function editMessageText(int|string $chatId, int $messageId, string $text, ?array $replyMarkup = null): void
	{
		$this->post('editMessageText', [
			'chat_id' => $chatId,
			'message_id' => $messageId,
			'text' => $this->sanitizeUtf8($text),
			'disable_web_page_preview' => true,
			'reply_markup' => $replyMarkup,
		]);
	}

	public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): void
	{
		$payload = [
			'callback_query_id' => $callbackQueryId,
		];

		if ($text !== null) {
			$payload['text'] = $this->sanitizeUtf8($text);
			$payload['show_alert'] = false;
		}

		$this->post('answerCallbackQuery', $payload);
	}

	public function removeReplyKeyboard(int|string $chatId, ?string $text = null): void
	{
		$payload = [
			'chat_id' => $chatId,
			'reply_markup' => [
				'remove_keyboard' => true,
			],
		];

		if ($text !== null) {
			$payload['text'] = $this->sanitizeUtf8($text);
		}

		$this->post('sendMessage', $payload);
	}

	public function getFilePath(string $fileId): ?string
	{
		$response = $this->get('getFile', [
			'file_id' => $fileId,
		]);

		if ($response === null) {
			return null;
		}

		$filePath = $response['result']['file_path'] ?? null;

		return is_string($filePath) ? $filePath : null;
	}

	public function downloadFile(string $filePath): ?string
	{
		$token = (string) config('services.telegram.token');
		if ($token === '') {
			return null;
		}

		$url = "https://api.telegram.org/file/bot{$token}/{$filePath}";

		try {
			$response = Http::timeout(20)->get($url);

			if (!$response->successful()) {
				return null;
			}

			return (string) $response->body();
		} catch (Throwable $e) {
			Log::error('telegram.downloadFile_failed', ['error' => $e->getMessage()]);
			return null;
		}
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function post(string $method, array $payload): void
	{
		$token = (string) config('services.telegram.token');

		if ($token === '') {
			Log::warning('telegram.token_missing');
			return;
		}

		$url = "https://api.telegram.org/bot{$token}/{$method}";

		$payload = $this->preparePayload($payload);

		// Log outgoing payload for sendMessage method
		if ($method === 'sendMessage') {
			Log::info('telegram.sendMessage.outgoing', [
				'chat_id' => $payload['chat_id'] ?? null,
				'text_length' => isset($payload['text']) && is_string($payload['text']) ? mb_strlen($payload['text']) : 0,
				'text_preview' => isset($payload['text']) && is_string($payload['text']) ? mb_substr($payload['text'], 0, 500) : null,
				'has_reply_markup' => isset($payload['reply_markup']),
				'reply_markup' => isset($payload['reply_markup']) ? (is_string($payload['reply_markup']) ? mb_substr($payload['reply_markup'], 0, 1000) : $payload['reply_markup']) : null,
				'payload_keys' => array_keys($payload),
			]);
		}

		try {
			$response = Http::timeout(15)->asForm()->post($url, $payload);

			$data = $response->json();
			$isOk = isset($data['ok']) && $data['ok'] === true;

			// Log response for sendMessage method
			if ($method === 'sendMessage') {
				Log::info('telegram.sendMessage.response', [
					'status' => $response->status(),
					'ok' => $data['ok'] ?? null,
					'error_code' => $data['error_code'] ?? null,
					'description' => $data['description'] ?? null,
					'response_body' => mb_substr((string) $response->body(), 0, 1000),
				]);
			}

			if ($response->successful() && $isOk) {
				Log::debug('telegram.api_success', [
					'method' => $method,
					'ok' => $data['ok'] ?? null,
				]);
			} else {
				$errorDescription = $data['description'] ?? $response->body();

				// Log text preview for debugging (first 200 chars, no sensitive data)
				$textPreview = '';
				if (isset($payload['text']) && is_string($payload['text'])) {
					$textPreview = mb_substr($payload['text'], 0, 200);
					if (mb_strlen($payload['text']) > 200) {
						$textPreview .= '...';
					}
				}

				// Always log if ok is false, even if HTTP status is 200
				Log::error('telegram.api_failed', [
					'method' => $method,
					'status' => $response->status(),
					'ok' => $data['ok'] ?? null,
					'error_code' => $data['error_code'] ?? null,
					'error' => $errorDescription,
					'response_body' => mb_substr((string) $response->body(), 0, 500),
					'text_preview' => $textPreview,
					'text_length' => isset($payload['text']) && is_string($payload['text']) ? mb_strlen($payload['text']) : 0,
					'has_reply_markup' => isset($payload['reply_markup']),
					'reply_markup_preview' => isset($payload['reply_markup']) && is_string($payload['reply_markup']) ? mb_substr($payload['reply_markup'], 0, 200) : null,
				]);
			}
		} catch (Throwable $e) {
			Log::error('telegram.api_exception', [
				'method' => $method,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			]);
		}
	}

	/**
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>|null
	 */
	private function get(string $method, array $params): ?array
	{
		$token = (string) config('services.telegram.token');
		if ($token === '') {
			return null;
		}

		$url = "https://api.telegram.org/bot{$token}/{$method}";

		try {
			$response = Http::timeout(15)->get($url, $params);

			if (!$response->successful()) {
				return null;
			}

			$data = $response->json();

			return is_array($data) ? $data : null;
		} catch (Throwable) {
			return null;
		}
	}

	/**
	 * Telegram expects reply_markup as JSON string when sending form-data.
	 *
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function preparePayload(array $payload): array
	{
		if (array_key_exists('reply_markup', $payload)) {
			$rm = $payload['reply_markup'];

			if ($rm === null || $rm === [] || $rm === '') {
				unset($payload['reply_markup']);
			} elseif (is_array($rm)) {
				$rm = $this->sanitizeArrayUtf8($rm);
				$rmJson = json_encode($rm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

				if ($rmJson === false) {
					unset($payload['reply_markup']);
				} else {
					$payload['reply_markup'] = $rmJson;
				}
			}
		}

		// Clean up text fields
		foreach (['text'] as $key) {
			if (isset($payload[$key]) && is_string($payload[$key])) {
				$payload[$key] = $this->sanitizeUtf8($payload[$key]);
			}
		}

		return $payload;
	}

	private function sanitizeUtf8(string $value): string
	{
		// Try iconv first
		$clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
		if ($clean !== false) {
			$value = $clean;
		}

		// Additional cleanup: remove invalid UTF-8 sequences
		$value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');

		// Remove control characters except newlines and tabs
		$value = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/u', '', $value);

		return $value;
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	private function sanitizeArrayUtf8(mixed $value): mixed
	{
		if (is_string($value)) {
			return $this->sanitizeUtf8($value);
		}

		if (is_array($value)) {
			$out = [];

			foreach ($value as $k => $v) {
				$key = is_string($k) ? $this->sanitizeUtf8($k) : $k;
				$out[$key] = $this->sanitizeArrayUtf8($v);
			}

			return $out;
		}

		return $value;
	}
}
