<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TelegramApiClient
{
	public function sendMessage(int|string $chatId, string $text): void
	{
		$token = (string) config('services.telegram.token');

		if ($token === '') {
			Log::warning('telegram.token_missing');
			return;
		}

		$url = "https://api.telegram.org/bot{$token}/sendMessage";

		try {
			Http::timeout(10)->post($url, [
				'chat_id' => $chatId,
				'text' => $text,
				'disable_web_page_preview' => true,
			]);
		} catch (Throwable $e) {
			Log::error('telegram.sendMessage_failed', [
				'error' => $e->getMessage(),
			]);
		}
	}

	public function getFilePath(string $fileId): ?string
	{
		$token = (string) config('services.telegram.token');
		if ($token === '') {
			return null;
		}

		$url = "https://api.telegram.org/bot{$token}/getFile";

		try {
			$response = Http::timeout(10)->get($url, [
				'file_id' => $fileId,
			]);

			$data = $response->json();

			$filePath = $data['result']['file_path'] ?? null;

			return is_string($filePath) ? $filePath : null;
		} catch (Throwable $e) {
			Log::error('telegram.getFile_failed', ['error' => $e->getMessage()]);
			return null;
		}
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
}
