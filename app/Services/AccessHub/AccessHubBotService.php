<?php

declare(strict_types=1);

namespace App\Services\AccessHub;

use App\Enums\TelegramUserRole;
use App\Models\TelegramUser;
use App\Services\AccessHub\Admin\AddAccountWizard;
use App\Services\AccessHub\Admin\BulkImportService;
use App\Services\AccessHub\Admin\LogsService;
use App\Services\Telegram\TelegramApiClient;
use App\Services\Telegram\TelegramUpdateParser;
use RuntimeException;
use Throwable;

final class AccessHubBotService
{
	public function __construct(
		private readonly TelegramApiClient $telegram,
		private readonly TelegramUpdateParser $parser,
		private readonly IssueAccountsService $issuer,
		private readonly AddAccountWizard $addWizard,
		private readonly BulkImportService $bulkImport,
		private readonly LogsService $logs
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

		if ($text === '' && !isset($message['document'])) {
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

		// Wizard input takes priority (admin only)
		if ($this->addWizard->isActive($telegramId)) {
			if ($text === '/abort') {
				$this->addWizard->cancel($telegramId);
				$this->telegram->sendMessage($chatId, 'Ок, мастер /add отменён.');
				return;
			}

			if ($user?->role !== TelegramUserRole::Admin) {
				$this->addWizard->cancel($telegramId);
				$this->telegram->sendMessage($chatId, 'Нет прав.');
				return;
			}

			$result = $this->addWizard->handleInput($telegramId, $text);
			$this->telegram->sendMessage($chatId, $result['message']);
			return;
		}

		// Admin commands
		if (str_starts_with($text, '/add')) {
			if ($user?->role !== TelegramUserRole::Admin) {
				$this->telegram->sendMessage($chatId, 'Нет прав.');
				return;
			}

			$started = $this->addWizard->start($telegramId);
			$this->telegram->sendMessage($chatId, $started['prompt']);
			return;
		}

		if (str_starts_with($text, '/import')) {
			if ($user?->role !== TelegramUserRole::Admin) {
				$this->telegram->sendMessage($chatId, 'Нет прав.');
				return;
			}

			// Option A: /import + multiline text in same message
			$payload = trim((string) preg_replace('/^\/import\s*/u', '', $text));
			if ($payload !== '') {
				$stat = $this->bulkImport->importFromText($payload);
				$this->telegram->sendMessage($chatId, "Импорт завершён.\nДобавлено: {$stat['added']}\nПропущено: {$stat['skipped']}\nОшибок: {$stat['errors']}");
				return;
			}

			// Option B: /import without text => ask for next message OR handle document if already present
			$this->telegram->sendMessage($chatId, "Пришлите текст строками после /import или отправьте TXT-файл (документ) с содержимым.\nФормат строки:\nPlatform | Game | login | pass | email | emailpass | backupEmails");
			return;
		}

		if (isset($message['document'])) {
			// Allow import by sending a file. To keep flow simple:
			// if admin sends a document, we try to parse it as import file.
			if ($user?->role !== TelegramUserRole::Admin) {
				$this->telegram->sendMessage($chatId, 'Нет прав.');
				return;
			}

			$document = $message['document'];
			if (!is_array($document)) {
				return;
			}

			$fileId = (string) ($document['file_id'] ?? '');
			if ($fileId === '') {
				$this->telegram->sendMessage($chatId, 'Не удалось прочитать файл.');
				return;
			}

			$filePath = $this->telegram->getFilePath($fileId);
			if ($filePath === null) {
				$this->telegram->sendMessage($chatId, 'Не удалось получить путь файла.');
				return;
			}

			$body = $this->telegram->downloadFile($filePath);
			if ($body === null || trim($body) === '') {
				$this->telegram->sendMessage($chatId, 'Файл пустой или не удалось скачать.');
				return;
			}

			$stat = $this->bulkImport->importFromText($body);
			$this->telegram->sendMessage($chatId, "Импорт из файла завершён.\nДобавлено: {$stat['added']}\nПропущено: {$stat['skipped']}\nОшибок: {$stat['errors']}");
			return;
		}

		if (str_starts_with($text, '/log')) {
			if ($user?->role !== TelegramUserRole::Admin) {
				$this->telegram->sendMessage($chatId, 'Нет прав.');
				return;
			}

			if (!preg_match('/^\/log\s+(\d+)\s*$/u', $text, $m)) {
				$this->telegram->sendMessage($chatId, "Формат: /log <account_id>\nПример: /log 12");
				return;
			}

			$accountId = (int) $m[1];
			$this->telegram->sendMessage($chatId, $this->logs->accountLog($accountId, 10));
			return;
		}

		// Operator issue request
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
			"Оператор (выдача):",
			"1) Номер заказа",
			"2) Игра (Платформа)",
			"",
			"Пример:",
			"2446303",
			"Minecraft (Xbox X)",
			"",
			"Qty (тест): суффикс x2 во 2-й строке:",
			"Minecraft (Xbox X) x2",
			"",
			"Админ:",
			"/add — добавить аккаунт (мастер)",
			"/abort — отменить мастер",
			"/import — массовый импорт текстом или TXT файлом",
			"/log <account_id> — логи выдач по аккаунту",
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
