<?php

declare(strict_types=1);

namespace App\Services\AccessHub;

use App\Models\TelegramUser;
use App\Services\Telegram\TelegramApiClient;
use App\Services\Telegram\TelegramUpdateParser;
use RuntimeException;
use Throwable;

final class AccessHubBotService
{
	public function __construct(
		private readonly TelegramApiClient $telegram,
		private readonly TelegramUpdateParser $parser,
		private readonly IssueAccountsService $issuer
	) {
	}

	/**
	 * @param array<string, mixed> $update
	 */
	public function handleUpdate(array $update): void
	{
		$message = $update['message'] ?? null;
		if (!is_array($message)) {
			return;
		}

		$chat = $message['chat'] ?? null;
		if (!is_array($chat) || !isset($chat['id'])) {
			return;
		}

		$from = $message['from'] ?? null;
		if (!is_array($from) || !isset($from['id'])) {
			return;
		}

		$chatId = $chat['id'];
		$telegramId = (string) $from['id'];
		$text = (string) ($message['text'] ?? '');

		if ($text === '') {
			return;
		}

		if (str_starts_with($text, '/start') || str_starts_with($text, '/help')) {
			$this->telegram->sendMessage($chatId, $this->helpText());
			return;
		}

		$user = TelegramUser::query()
			->where('telegram_id', $telegramId)
			->where('is_active', true)
			->first();

		$denyByDefault = (bool) config('accesshub.deny_by_default', true);

		if ($denyByDefault && $user === null) {
			$this->telegram->sendMessage($chatId, 'Нет доступа. Обратитесь к администратору.');
			return;
		}

		$parsed = $this->parser->parseIssueRequest($text);
		if ($parsed === null) {
			$this->telegram->sendMessage($chatId, $this->invalidFormatText());
			return;
		}

		try {
			$items = $this->issuer->issue(
				$parsed->orderId,
				$telegramId,
				$parsed->game,
				$parsed->platform,
				$parsed->qty
			);

			$this->telegram->sendMessage($chatId, $this->formatIssued($parsed->orderId, $parsed->game, $parsed->platform, $items));
		} catch (RuntimeException $e) {
			$this->telegram->sendMessage($chatId, $e->getMessage());
		} catch (Throwable) {
			$this->telegram->sendMessage($chatId, 'Ошибка. Попробуйте ещё раз или обратитесь к администратору.');
		}
	}

	private function helpText(): string
	{
		return implode("\n", [
			"AccessHub",
			"",
			"Формат запроса:",
			"1) Номер заказа",
			"2) Игра (Платформа)",
			"",
			"Пример:",
			"2446303",
			"Minecraft (Xbox X)",
			"",
			"Qty (пока тест): можно добавить 'x2' в конец второй строки:",
			"Minecraft (Xbox X) x2",
		]);
	}

	private function invalidFormatText(): string
	{
		return implode("\n", [
			"Неверный формат.",
			"Нужно 2 строки:",
			"1) Номер заказа",
			"2) Игра (Платформа)",
			"",
			"Пример:",
			"2446303",
			"Minecraft (Xbox X)",
		]);
	}

	/**
	 * @param array<int, array{game_login: string, game_password: string}> $items
	 */
	private function formatIssued(string $orderId, string $game, string $platform, array $items): string
	{
		$lines = [];
		$lines[] = "Заказ: {$orderId}";
		$lines[] = "Игра: {$game}";
		$lines[] = "Платформа: {$platform}";
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
}
