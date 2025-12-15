<?php

declare(strict_types=1);

namespace App\Services\AccessHub;

use App\Enums\TelegramUserRole;
use App\Models\TelegramUser;
use App\Services\AccessHub\Admin\AddAccountWizard;
use App\Services\AccessHub\Admin\AdminStatsService;
use App\Services\AccessHub\Admin\BulkImportService;
use App\Services\AccessHub\Admin\LogsService;
use App\Services\AccessHub\Operator\OperatorHistoryService;
use App\Services\Telegram\TelegramApiClient;
use App\Services\Telegram\TelegramKeyboardFactory;
use App\Services\Telegram\TelegramMarkdown;
use App\Services\Telegram\TelegramUpdateParser;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class AccessHubBotService
{
	private const BTN_MENU = '📋 Меню';
	private const BTN_ISSUE = '🎮 Получить аккаунт';
	private const BTN_HISTORY = '🧾 Моя история';
	private const BTN_HELP = 'ℹ️ Помощь';

	private const BTN_ADD = '➕ Добавить';
	private const BTN_IMPORT = '📥 Импорт';
	private const BTN_STATS = '📊 Статистика';
	private const BTN_LOGS = '🧾 Логи';
	private const BTN_FIND = '🔎 Поиск';
	private const BTN_EXPORT = '📤 Экспорт';
	private const BTN_USERS = '👥 Пользователи';

	private const BTN_REFRESH = '🔄 Обновить';

	public function __construct(
		private readonly TelegramApiClient $telegram,
		private readonly TelegramUpdateParser $parser,
		private readonly IssueAccountsService $issuer,
		private readonly AddAccountWizard $addWizard,
		private readonly BulkImportService $bulkImport,
		private readonly LogsService $logs,
		private readonly OperatorHistoryService $operatorHistory,
		private readonly AdminStatsService $adminStats,
		private readonly TelegramKeyboardFactory $kb,
		private readonly TelegramMarkdown $md
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

		// WebApp: tg.sendData()
		$webAppData = $message['web_app_data']['data'] ?? null;
		if (is_string($webAppData) && trim($webAppData) !== '') {
			$this->handleWebAppData($chatId, $telegramId, $webAppData);
			return;
		}

		if ($text === '' && !isset($message['document'])) {
			return;
		}

		if (str_starts_with($text, '/start') || str_starts_with($text, '/help') || $text === self::BTN_HELP) {
			$user = $this->loadUser($telegramId);
			$denyByDefault = (bool) config('accesshub.deny_by_default', true);
			
			// If user has access to WebApp, don't show ReplyKeyboard (hide menu)
			if (!$denyByDefault || $user !== null) {
				// User has access - send message without ReplyKeyboard to hide menu
				$this->telegram->sendMessage($chatId, $this->helpText(), null);
			} else {
				// User doesn't have access - show menu as usual
				$this->telegram->sendMessage($chatId, $this->helpText(), $this->mainReplyKeyboard($telegramId));
			}
			return;
		}

		if (str_starts_with($text, '/menu') || $text === self::BTN_MENU || $text === self::BTN_REFRESH) {
			$this->telegram->sendMessage($chatId, $this->menuText($telegramId), $this->mainReplyKeyboard($telegramId));
			return;
		}

		$user = $this->loadUser($telegramId);

		$denyByDefault = (bool) config('accesshub.deny_by_default', true);
		if ($denyByDefault && $user === null) {
			$this->telegram->sendMessage($chatId, 'Нет доступа. Обратитесь к администратору.');
			return;
		}

		// Wizard input (admin only)
		if ($this->addWizard->isActive($telegramId)) {
			if ($text === '/abort') {
				$this->addWizard->cancel($telegramId);
				$this->telegram->sendMessage($chatId, 'Ок, мастер /add отменён.', $this->mainReplyKeyboard($telegramId));
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

		// Fast buttons mapping (text UI)
		if ($text === self::BTN_ISSUE) {
			$this->telegram->sendMessage($chatId, $this->operatorIssueHelp(), $this->mainReplyKeyboard($telegramId));
			return;
		}

		if (str_starts_with($text, '/history') || $text === self::BTN_HISTORY) {
			$this->telegram->sendMessage($chatId, $this->operatorHistory->last($telegramId, 10), $this->mainReplyKeyboard($telegramId));
			return;
		}

		// Admin buttons + commands
		if ($text === self::BTN_ADD || str_starts_with($text, '/add')) {
			if ($user?->role !== TelegramUserRole::Admin) {
				$this->telegram->sendMessage($chatId, 'Нет прав.');
				return;
			}

			$started = $this->addWizard->start($telegramId);
			$this->telegram->sendMessage($chatId, $started['prompt']);
			return;
		}

		if ($text === self::BTN_IMPORT || str_starts_with($text, '/import')) {
			if ($user?->role !== TelegramUserRole::Admin) {
				$this->telegram->sendMessage($chatId, 'Нет прав.');
				return;
			}

			$payload = trim((string) preg_replace('/^\/import\s*/u', '', $text));
			if ($payload !== '' && str_starts_with($text, '/import')) {
				$stat = $this->bulkImport->importFromText($payload);
				$this->telegram->sendMessage($chatId, $this->formatImportResult($stat), $this->mainReplyKeyboard($telegramId));
				return;
			}

			$this->telegram->sendMessage(
				$chatId,
				"Импорт:\n1) отправь /import + строки\nили\n2) пришли TXT-файл документом.\n\nФормат строки:\nPlatform | Game | login | pass | email | emailpass | backupEmails",
				$this->mainReplyKeyboard($telegramId)
			);
			return;
		}

		if (isset($message['document'])) {
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
			$this->telegram->sendMessage($chatId, "Импорт из файла завершён.\n" . $this->formatImportResult($stat), $this->mainReplyKeyboard($telegramId));
			return;
		}

		if ($text === self::BTN_STATS || str_starts_with($text, '/stats')) {
			if ($user?->role !== TelegramUserRole::Admin) {
				$this->telegram->sendMessage($chatId, 'Нет прав.');
				return;
			}

			$this->telegram->sendMessage($chatId, $this->adminStats->summary(), $this->mainReplyKeyboard($telegramId));
			return;
		}

		if ($text === self::BTN_LOGS) {
			if ($user?->role !== TelegramUserRole::Admin) {
				$this->telegram->sendMessage($chatId, 'Нет прав.');
				return;
			}

			$this->telegram->sendMessage($chatId, "Логи:\nКоманда: /log <account_id>\nПример: /log 12", $this->mainReplyKeyboard($telegramId));
			return;
		}

		if (str_starts_with($text, '/log')) {
			if ($user?->role !== TelegramUserRole::Admin) {
				$this->telegram->sendMessage($chatId, 'Нет прав.');
				return;
			}

			if (!preg_match('/^\/log\s+(\d+)\s*$/u', $text, $m)) {
				$this->telegram->sendMessage($chatId, "Формат: /log <account_id>\nПример: /log 12", $this->mainReplyKeyboard($telegramId));
				return;
			}

			$accountId = (int) $m[1];
			$this->telegram->sendMessage($chatId, $this->logs->accountLog($accountId, 10), $this->mainReplyKeyboard($telegramId));
			return;
		}

		// Future stubs (buttons only)
		if (in_array($text, [self::BTN_FIND, self::BTN_EXPORT, self::BTN_USERS], true)) {
			if ($user?->role !== TelegramUserRole::Admin) {
				$this->telegram->sendMessage($chatId, 'Нет прав.');
				return;
			}

			$this->telegram->sendMessage($chatId, 'Скоро. Пока это заглушка.', $this->mainReplyKeyboard($telegramId));
			return;
		}

		// Operator issue request (text flow)
		$parsed = $this->parser->parseIssueRequest($text);
		if ($parsed === null) {
			$this->telegram->sendMessage($chatId, $this->invalidFormatText(), $this->mainReplyKeyboard($telegramId));
			return;
		}

		try {
			Log::info('issue.request', [
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

			Log::info('issue.completed', [
				'items_count' => count($items),
			]);

			$messageText = $this->formatIssued($parsed->orderId, $parsed->game, $parsed->platform, $items);
			$replyMarkup = $this->mainReplyKeyboard($telegramId);

			Log::info('issue.sending', [
				'text_length' => mb_strlen($messageText),
				'text_preview' => mb_substr($messageText, 0, 300),
				'has_reply_markup' => !empty($replyMarkup),
				'reply_markup' => $replyMarkup,
			]);

			$this->telegram->sendMessage($chatId, $messageText, $replyMarkup);
		} catch (RuntimeException $e) {
			Log::error('issue.exception.runtime', [
				'message' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			]);
			$this->telegram->sendMessage($chatId, $e->getMessage(), $this->mainReplyKeyboard($telegramId));
		} catch (Throwable $e) {
			Log::error('issue.exception.throwable', [
				'message' => $e->getMessage(),
				'class' => get_class($e),
				'trace' => $e->getTraceAsString(),
			]);
			$this->telegram->sendMessage($chatId, 'Ошибка. Попробуйте ещё раз или обратитесь к администратору.', $this->mainReplyKeyboard($telegramId));
		}
	}

	private function menuText(string $telegramId): string
	{
		$user = $this->loadUser($telegramId);

		if ($user?->role === TelegramUserRole::Admin) {
			return implode("\n", [
				"AccessHub — меню (Админ)",
				"Выбери действие кнопками ниже.",
			]);
		}

		return implode("\n", [
			"AccessHub — меню (Оператор)",
			"Выбери действие кнопками ниже.",
		]);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function mainReplyKeyboard(string $telegramId): array
	{
		$user = $this->loadUser($telegramId);

		$rows = [
			[self::BTN_MENU, self::BTN_HISTORY],
			[self::BTN_ISSUE, self::BTN_HELP],
		];

		if ($user?->role === TelegramUserRole::Admin) {
			$rows[] = [self::BTN_ADD, self::BTN_IMPORT];
			$rows[] = [self::BTN_STATS, self::BTN_LOGS];
			$rows[] = [self::BTN_FIND, self::BTN_EXPORT];
			$rows[] = [self::BTN_USERS, self::BTN_REFRESH];
		} else {
			$rows[] = [self::BTN_REFRESH];
		}

		return $this->kb->reply($rows, true, false);
	}

	private function operatorIssueHelp(): string
	{
		return implode("\n", [
			"Выдача аккаунта:",
			"отправь 2 строки:",
			"1) номер заказа",
			"2) Игра (Платформа)",
			"",
			"Пример:",
			"2446303",
			"Minecraft (Xbox X)",
			"",
			"Qty (тест): добавь x2 в конце второй строки:",
			"Minecraft (Xbox X) x2",
		]);
	}

	/**
	 * @param array{added: int, skipped: int, errors: int} $stat
	 */
	private function formatImportResult(array $stat): string
	{
		return "Импорт завершён.\nДобавлено: {$stat['added']}\nПропущено: {$stat['skipped']}\nОшибок: {$stat['errors']}";
	}

	private function helpText(): string
	{
		return implode("\n", [
			"AccessHub — команды (работают параллельно с кнопками):",
			"/menu, /history, /add, /abort, /import, /log <id>, /stats",
		]);
	}

	private function invalidFormatText(): string
	{
		return implode("\n", [
			"❌ Неверный формат.",
			"",
			"Нужно 2 строки:",
			"1️⃣ Номер заказа (только цифры)",
			"2️⃣ Игра (Платформа) — обязательно со скобками!",
			"",
			"✅ Правильный пример:",
			"2446303",
			"Minecraft (Xbox X)",
			"",
			"❌ Неправильно:",
			"1212",
			"4334ававава",
			"",
			"⚠️ Обратите внимание: во второй строке должны быть скобки (Платформа)!",
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

	/**
	 * Handle Telegram WebApp payload (message.web_app_data.data).
	 */
	private function handleWebAppData(int|string $chatId, string $telegramId, string $raw): void
	{
		// Hide ReplyKeyboard when WebApp sends data
		$this->telegram->removeReplyKeyboard($chatId);

		$data = json_decode($raw, true);

		if (!is_array($data)) {
			$this->telegram->sendMessage($chatId, 'WebApp: неверный формат данных.');
			return;
		}

		$action = $data['action'] ?? null;
		$payload = $data['payload'] ?? null;

		if (!is_string($action) || !is_array($payload)) {
			$this->telegram->sendMessage($chatId, 'WebApp: отсутствуют обязательные поля.');
			return;
		}

		if ($action !== 'issue') {
			$this->telegram->sendMessage($chatId, 'WebApp: неизвестное действие.');
			return;
		}

		$orderId = isset($payload['order_id']) ? trim((string) $payload['order_id']) : '';
		$game = isset($payload['game']) ? trim((string) $payload['game']) : '';
		$platform = isset($payload['platform']) ? trim((string) $payload['platform']) : '';
		$qty = (int) ($payload['qty'] ?? 1);

		if ($orderId === '' || $game === '' || $platform === '') {
			$this->telegram->sendMessage($chatId, 'WebApp: заполни order_id, game, platform.');
			return;
		}

		if ($qty < 1) {
			$qty = 1;
		}

		if ($qty > 20) {
			$qty = 20;
		}

		try {
			$items = $this->issuer->issue($orderId, $telegramId, $game, $platform, $qty);

			// Reply as "black form" (code-block)
			$text = $this->formatIssued($orderId, $game, $platform, $items);

			$this->telegram->sendMessage(
				$chatId,
				$this->md->codeBlock($text),
				null,
				'MarkdownV2'
			);
		} catch (RuntimeException $e) {
			$this->telegram->sendMessage($chatId, $e->getMessage());
		} catch (Throwable) {
			$this->telegram->sendMessage($chatId, 'Ошибка. Попробуй ещё раз.');
		}
	}

	private function loadUser(string $telegramId): ?TelegramUser
	{
		return TelegramUser::query()
			->where('telegram_id', $telegramId)
			->where('is_active', true)
			->first();
	}
}

