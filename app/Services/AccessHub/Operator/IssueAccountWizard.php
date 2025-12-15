<?php

declare(strict_types=1);

namespace App\Services\AccessHub\Operator;

use App\Services\AccessHub\IssueAccountsService;
use App\Services\Telegram\TelegramMarkdown;
use App\Services\Telegram\TelegramUpdateParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class IssueAccountWizard
{
	private const KEY_PREFIX = 'accesshub:issue_wizard:';

	public function __construct(
		private readonly IssueAccountsService $issuer,
		private readonly TelegramUpdateParser $parser,
		private readonly TelegramMarkdown $md
	) {
	}

	/**
	 * @return array{state: string, prompt: string}
	 */
	public function start(string $telegramId): array
	{
		$this->put($telegramId, [
			'step' => 'order_id',
			'data' => [],
		]);

		return [
			'state' => 'started',
			'prompt' => $this->prompt('order_id'),
		];
	}

	public function cancel(string $telegramId): void
	{
		Cache::forget($this->key($telegramId));
	}

	public function isActive(string $telegramId): bool
	{
		return Cache::has($this->key($telegramId));
	}

	/**
	 * @return array{done: bool, message: string, items?: array}
	 */
	public function handleInput(string $telegramId, string $text): array
	{
		$ctx = $this->get($telegramId);
		if ($ctx === null) {
			return [
				'done' => true,
				'message' => 'Мастер не запущен. Используйте кнопку "🎮 Получить аккаунт".',
			];
		}

		$text = trim($text);
		if ($text === '') {
			return [
				'done' => false,
				'message' => 'Пустое значение. ' . $this->prompt((string) $ctx['step']),
			];
		}

		$step = (string) $ctx['step'];
		$data = (array) $ctx['data'];

		if ($step === 'order_id') {
			if (!preg_match('/^\d+$/', $text)) {
				return [
					'done' => false,
					'message' => 'Номер заказа должен содержать только цифры. ' . $this->prompt('order_id'),
				];
			}

			$data['order_id'] = $text;
			$ctx['data'] = $data;
			$ctx['step'] = 'game_platform';
			$this->put($telegramId, $ctx);

			return [
				'done' => false,
				'message' => $this->prompt('game_platform'),
			];
		}

		if ($step === 'game_platform') {
			$parsed = $this->parser->parseIssueRequest($data['order_id'] . "\n" . $text);
			if ($parsed === null) {
				return [
					'done' => false,
					'message' => "Неверный формат. Нужно: Игра (Платформа)\nПример: Minecraft (Xbox X)\n\n" . $this->prompt('game_platform'),
				];
			}

			// Issue accounts
			try {
				Log::info('issue.wizard.request', [
					'order_id' => $parsed->orderId,
					'game' => $parsed->game,
					'platform' => $parsed->platform,
					'qty' => $parsed->qty,
					'operator' => $telegramId,
				]);

				$items = $this->issuer->issue(
					$parsed->orderId,
					$telegramId,
					$parsed->game,
					$parsed->platform,
					$parsed->qty
				);

				Log::info('issue.wizard.completed', [
					'items_count' => count($items),
				]);

				$this->cancel($telegramId);

				$messageText = $this->md->codeBlock($this->formatIssuedPlain($parsed->orderId, $parsed->game, $parsed->platform, $items));

				return [
					'done' => true,
					'message' => $messageText,
					'items' => $items,
					'parse_mode' => 'MarkdownV2',
				];
			} catch (RuntimeException $e) {
				Log::error('issue.wizard.exception.runtime', [
					'message' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				]);
				$this->cancel($telegramId);

				return [
					'done' => true,
					'message' => $e->getMessage(),
				];
			} catch (Throwable $e) {
				Log::error('issue.wizard.exception.throwable', [
					'message' => $e->getMessage(),
					'class' => get_class($e),
					'trace' => $e->getTraceAsString(),
				]);
				$this->cancel($telegramId);

				return [
					'done' => true,
					'message' => 'Ошибка. Попробуйте ещё раз или обратитесь к администратору.',
				];
			}
		}

		return [
			'done' => true,
			'message' => 'Неизвестный шаг. Мастер отменён.',
		];
	}

	/**
	 * @param array<int, array{game_login: string, game_password: string}> $items
	 */
	private function formatIssuedPlain(string $orderId, string $game, string $platform, array $items): string
	{
		$lines = [];
		$lines[] = "Order: {$orderId}";
		$lines[] = "Game: {$game}";
		$lines[] = "Platform: {$platform}";
		$lines[] = "";

		foreach ($items as $i => $item) {
			$n = $i + 1;
			$lines[] = "#{$n}";
			$lines[] = "Login: {$item['game_login']}";
			$lines[] = "Password: {$item['game_password']}";
			$lines[] = "";
		}

		return trim(implode("\n", $lines));
	}

	private function prompt(string $step): string
	{
		return match ($step) {
			'order_id' => "Введите номер заказа (только цифры).\nПример: 2446303\n\n/abort — отмена",
			'game_platform' => "Введите игру и платформу в формате:\nИгра (Платформа)\n\nПример:\nMinecraft (Xbox X)\n\n/abort — отмена",
			default => "Ожидаю данные.\n\n/abort — отмена",
		};
	}

	private function key(string $telegramId): string
	{
		return self::KEY_PREFIX . $telegramId;
	}

	private function get(string $telegramId): ?array
	{
		/** @var array|null $ctx */
		$ctx = Cache::get($this->key($telegramId));

		return is_array($ctx) ? $ctx : null;
	}

	private function put(string $telegramId, array $ctx): void
	{
		Cache::put($this->key($telegramId), $ctx, now()->addMinutes(30));
	}
}
