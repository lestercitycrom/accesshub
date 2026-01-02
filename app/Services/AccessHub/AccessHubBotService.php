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
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
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
		private readonly LogsService $logs,
		private readonly OperatorHistoryService $operatorHistory,
		private readonly AdminStatsService $adminStats,
		private readonly TelegramKeyboardFactory $kb,
		private readonly TelegramMarkdown $md,
		private readonly BotLocaleResolver $localeResolver,
		private readonly BotKeyboardFactory $botKb
	) {
	}

	/**
	 * @param array<string, mixed> $update
	 */
	public function handleUpdate(array $update): void
	{
		// Handle callback_query (inline button clicks)
		$callbackQuery = $update['callback_query'] ?? null;
		if (is_array($callbackQuery)) {
			$this->handleCallbackQuery($callbackQuery);
			return;
		}

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

		// Load user and set locale
		$user = $this->loadUser($telegramId);
		$locale = $this->localeResolver->resolve($update, $user);
		App::setLocale($locale);

		// WebApp: tg.sendData()
		$webAppData = $message['web_app_data']['data'] ?? null;
		if (is_string($webAppData) && trim($webAppData) !== '') {
			$this->handleWebAppData($chatId, $telegramId, $webAppData);
			return;
		}

		if ($text === '' && !isset($message['document'])) {
			return;
		}

		// Handle /lang command
		if (str_starts_with($text, '/lang')) {
			$this->handleLangCommand($chatId, $telegramId, $text, $user);
			return;
		}

		$btnHelp = __('bot.menu.help');
		if (str_starts_with($text, '/start') || str_starts_with($text, '/help') || $text === $btnHelp) {
			$denyByDefault = (bool) config('accesshub.deny_by_default', true);
			
			// If user has access to WebApp, don't show ReplyKeyboard (hide menu)
			if (!$denyByDefault || $user !== null) {
				// User has access - send message without ReplyKeyboard to hide menu
				$this->telegram->sendMessage($chatId, $this->helpText(), null);
			} else {
				// User doesn't have access - show menu as usual
				$this->telegram->sendMessage($chatId, $this->helpText(), $this->mainReplyKeyboard($user));
			}
			return;
		}

		$btnMenu = __('bot.menu.menu');
		$btnRefresh = __('bot.menu.refresh');
		if (str_starts_with($text, '/menu') || $text === $btnMenu || $text === $btnRefresh) {
			$this->telegram->sendMessage($chatId, $this->menuText($user), $this->mainReplyKeyboard($user));
			return;
		}

		$denyByDefault = (bool) config('accesshub.deny_by_default', true);
		if ($denyByDefault && $user === null) {
			$this->telegram->sendMessage($chatId, __('bot.replies.access_denied'));
			return;
		}

		// Fast buttons mapping (text UI) - use reverse lookup
		$action = $this->botKb->findActionByLabel($text);

		// Check for menu buttons and commands BEFORE wizard (to allow canceling wizard)
		$btnUsers = __('bot.menu.users');
		if ($text === $btnUsers || $action === 'USERS' || str_starts_with($text, '/users')) {
			if ($this->addWizard->isActive($telegramId)) {
				$this->addWizard->cancel($telegramId);
			}
			if ($user?->role !== TelegramUserRole::Admin) {
				$this->telegram->sendMessage($chatId, __('bot.replies.no_permission'));
				return;
			}
			$this->showUsersList($chatId, $user);
			return;
		}

		// Wizard input (admin only) - check AFTER menu buttons
		if ($this->addWizard->isActive($telegramId)) {
			if ($text === '/abort') {
				$this->addWizard->cancel($telegramId);
				$this->telegram->sendMessage($chatId, __('bot.replies.command_cancelled'), $this->mainReplyKeyboard($user));
				return;
			}

			if ($user?->role !== TelegramUserRole::Admin) {
				$this->addWizard->cancel($telegramId);
				$this->telegram->sendMessage($chatId, __('bot.replies.no_permission'));
				return;
			}

			$result = $this->addWizard->handleInput($telegramId, $text);
			$this->telegram->sendMessage($chatId, $result['message']);
			return;
		}
		
		if ($action === 'ISSUE' || $text === __('bot.menu.issue')) {
			$this->telegram->sendMessage($chatId, $this->operatorIssueHelp(), $this->mainReplyKeyboard($user));
			return;
		}

		if (str_starts_with($text, '/history') || $action === 'HISTORY' || $text === __('bot.menu.history')) {
			$this->telegram->sendMessage($chatId, $this->operatorHistory->last($telegramId, 10), $this->mainReplyKeyboard($user));
			return;
		}

		// Admin buttons + commands
		if ($action === 'ADD' || $text === __('bot.menu.add') || str_starts_with($text, '/add')) {
			if ($user?->role !== TelegramUserRole::Admin) {
				$this->telegram->sendMessage($chatId, __('bot.replies.no_permission'));
				return;
			}

			$started = $this->addWizard->start($telegramId);
			$this->telegram->sendMessage($chatId, $started['prompt']);
			return;
		}

		if ($action === 'IMPORT' || $text === __('bot.menu.import') || str_starts_with($text, '/import')) {
			if ($user?->role !== TelegramUserRole::Admin) {
				$this->telegram->sendMessage($chatId, __('bot.replies.no_permission'));
				return;
			}

			$payload = trim((string) preg_replace('/^\/import\s*/u', '', $text));
			if ($payload !== '' && str_starts_with($text, '/import')) {
				$stat = $this->bulkImport->importFromText($payload);
				$this->telegram->sendMessage($chatId, $this->formatImportResult($stat), $this->mainReplyKeyboard($user));
				return;
			}

			$this->telegram->sendMessage(
				$chatId,
				__('bot.admin.import_help'),
				$this->mainReplyKeyboard($user)
			);
			return;
		}

		if (isset($message['document'])) {
			if ($user?->role !== TelegramUserRole::Admin) {
				$this->telegram->sendMessage($chatId, __('bot.replies.no_permission'));
				return;
			}

			$document = $message['document'];
			if (!is_array($document)) {
				return;
			}

			$fileId = (string) ($document['file_id'] ?? '');
			if ($fileId === '') {
				$this->telegram->sendMessage($chatId, __('bot.replies.file_read_error'));
				return;
			}

			$filePath = $this->telegram->getFilePath($fileId);
			if ($filePath === null) {
				$this->telegram->sendMessage($chatId, __('bot.replies.file_path_error'));
				return;
			}

			$body = $this->telegram->downloadFile($filePath);
			if ($body === null || trim($body) === '') {
				$this->telegram->sendMessage($chatId, __('bot.replies.file_empty_error'));
				return;
			}

			$stat = $this->bulkImport->importFromText($body);
			$this->telegram->sendMessage($chatId, __('bot.replies.import_complete') . "\n" . $this->formatImportResult($stat), $this->mainReplyKeyboard($user));
			return;
		}

		if ($action === 'STATS' || $text === __('bot.menu.stats') || str_starts_with($text, '/stats')) {
			if ($user?->role !== TelegramUserRole::Admin) {
				$this->telegram->sendMessage($chatId, __('bot.replies.no_permission'));
				return;
			}

			$this->telegram->sendMessage($chatId, $this->adminStats->summary(), $this->mainReplyKeyboard($user));
			return;
		}

		if ($action === 'LOGS' || $text === __('bot.menu.logs')) {
			if ($user?->role !== TelegramUserRole::Admin) {
				$this->telegram->sendMessage($chatId, __('bot.replies.no_permission'));
				return;
			}

			$this->telegram->sendMessage($chatId, __('bot.admin.logs_help'), $this->mainReplyKeyboard($user));
			return;
		}

		if (str_starts_with($text, '/log')) {
			if ($user?->role !== TelegramUserRole::Admin) {
				$this->telegram->sendMessage($chatId, __('bot.replies.no_permission'));
				return;
			}

			if (!preg_match('/^\/log\s+(\d+)\s*$/u', $text, $m)) {
				$this->telegram->sendMessage($chatId, __('bot.admin.log_format'), $this->mainReplyKeyboard($user));
				return;
			}

			$accountId = (int) $m[1];
			$this->telegram->sendMessage($chatId, $this->logs->accountLog($accountId, 10), $this->mainReplyKeyboard($user));
			return;
		}

		// Add user command: /adduser TELEGRAM_ID [role]
		if (str_starts_with($text, '/adduser')) {
			if ($user?->role !== TelegramUserRole::Admin) {
				$this->telegram->sendMessage($chatId, __('bot.replies.no_permission'));
				return;
			}

			$parts = preg_split('/\s+/u', trim($text), 3);
			if (count($parts) < 2) {
				$this->telegram->sendMessage($chatId, __('bot.admin.adduser_help'), $this->mainReplyKeyboard($user));
				return;
			}

			$targetTelegramId = (string) $parts[1];
			$role = isset($parts[2]) ? strtolower(trim($parts[2])) : 'operator';

			$roleEnum = TelegramUserRole::tryFrom($role);
			if ($roleEnum === null) {
				$this->telegram->sendMessage($chatId, __('bot.admin.adduser_invalid_role'), $this->mainReplyKeyboard($user));
				return;
			}

			try {
				TelegramUser::query()->updateOrCreate(
					['telegram_id' => $targetTelegramId],
					['role' => $roleEnum, 'is_active' => true]
				);

				$roleLabel = $roleEnum === TelegramUserRole::Admin ? __('bot.admin.role_admin') : __('bot.admin.role_operator');
				$this->telegram->sendMessage(
					$chatId,
					__('bot.admin.adduser_success', ['telegram_id' => $targetTelegramId, 'role' => $roleLabel]),
					$this->mainReplyKeyboard($user)
				);
			} catch (Throwable $e) {
				Log::error('bot.adduser_failed', ['error' => $e->getMessage(), 'telegram_id' => $targetTelegramId]);
				$this->telegram->sendMessage($chatId, __('bot.replies.error_generic'), $this->mainReplyKeyboard($user));
			}
			return;
		}


		// Future stubs (buttons only)
		$btnFind = __('bot.menu.find');
		$btnExport = __('bot.menu.export');
		if (in_array($text, [$btnFind, $btnExport], true) || in_array($action, ['FIND', 'EXPORT'], true)) {
			if ($user?->role !== TelegramUserRole::Admin) {
				$this->telegram->sendMessage($chatId, __('bot.replies.no_permission'));
				return;
			}

			$this->telegram->sendMessage($chatId, __('bot.replies.error_generic'), $this->mainReplyKeyboard($user));
			return;
		}

		// Operator issue request (text flow)
		$parsed = $this->parser->parseIssueRequest($text);
		if ($parsed === null) {
			$this->telegram->sendMessage($chatId, $this->invalidFormatText(), $this->mainReplyKeyboard($user));
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
			$replyMarkup = $this->mainReplyKeyboard($user);

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
			$this->telegram->sendMessage($chatId, $e->getMessage(), $this->mainReplyKeyboard($user));
		} catch (Throwable $e) {
			Log::error('issue.exception.throwable', [
				'message' => $e->getMessage(),
				'class' => get_class($e),
				'trace' => $e->getTraceAsString(),
			]);
			$this->telegram->sendMessage($chatId, __('bot.replies.error_generic'), $this->mainReplyKeyboard($user));
		}
	}

	private function menuText(?TelegramUser $user): string
	{
		if ($user?->role === TelegramUserRole::Admin) {
			return __('bot.replies.welcome');
		}

		return __('bot.replies.welcome');
	}

	/**
	 * @return array<string, mixed>
	 */
	private function mainReplyKeyboard(?TelegramUser $user): array
	{
		return $this->botKb->menuForRole($user?->role);
	}

	private function operatorIssueHelp(): string
	{
		return __('bot.issue.help');
	}

	/**
	 * @param array{added: int, skipped: int, errors: array<int, array{row: int, reason: string}>|int} $stat
	 */
	private function formatImportResult(array $stat): string
	{
		$errors = $stat['errors'] ?? [];
		$errorsCount = is_array($errors) ? count($errors) : (int) $errors;

		return __('bot.admin.import_result', [
			'added' => $stat['added'] ?? 0,
			'skipped' => $stat['skipped'] ?? 0,
			'errors' => $errorsCount,
		]);
	}

	private function helpText(): string
	{
		return __('bot.replies.welcome');
	}

	private function invalidFormatText(): string
	{
		return __('bot.issue.invalid_format');
	}

	/**
	 * @param array<int, array{game_login: string, game_password: string, account_id: int}> $items
	 */
	private function formatIssued(string $orderId, string $game, string $platform, array $items): string
	{
		if (count($items) === 0) {
			return __('bot.issue.not_found', ['game' => $game, 'platform' => $platform]);
		}

		$lines = [];
		foreach ($items as $i => $item) {
			$n = $i + 1;
			$accountId = $item['account_id'] ?? '-';
			if (count($items) > 1) {
				$lines[] = "#{$n}";
			}
			$lines[] = __('bot.issue.success', [
				'order' => $orderId,
				'game' => $game,
				'platform' => $platform,
				'account_id' => $accountId,
			]);
			$lines[] = "Login: {$item['game_login']}";
			$lines[] = "Password: {$item['game_password']}";
			$lines[] = "";
		}

		return trim(implode("\n", $lines));
	}

	/**
	 * Handle /lang command for language selection.
	 */
	private function handleLangCommand(int|string $chatId, string $telegramId, string $text, ?TelegramUser $user): void
	{
		$parts = explode(' ', trim($text), 2);
		$langArg = $parts[1] ?? null;

		if ($langArg === null) {
			// Show language selection menu
			$this->telegram->sendMessage($chatId, __('bot.lang.select'), $this->botKb->languageMenu());
			return;
		}

		// Map language selection
		$langMap = [
			'ru' => 'ru',
			'uk' => 'uk',
			'en' => 'en',
			__('bot.lang.ru') => 'ru',
			__('bot.lang.uk') => 'uk',
			__('bot.lang.en') => 'en',
		];

		$selectedLang = $langMap[trim($langArg)] ?? null;
		if ($selectedLang === null) {
			$this->telegram->sendMessage($chatId, __('bot.lang.select'), $this->botKb->languageMenu());
			return;
		}

		// Save locale to user
		if ($user === null) {
			$user = TelegramUser::query()->firstOrCreate(
				['telegram_id' => $telegramId],
				['role' => TelegramUserRole::Operator, 'is_active' => true]
			);
		}

		$user->locale = $selectedLang;
		$user->save();

		// Set locale and send confirmation
		App::setLocale($selectedLang);
		$langLabel = __('bot.lang.' . $selectedLang);
		$this->telegram->sendMessage($chatId, __('bot.lang.set', ['lang' => $langLabel]), $this->mainReplyKeyboard($user));
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

	/**
	 * Handle callback_query from inline buttons.
	 *
	 * @param array<string, mixed> $callbackQuery
	 */
	private function handleCallbackQuery(array $callbackQuery): void
	{
		$from = $callbackQuery['from'] ?? null;
		$message = $callbackQuery['message'] ?? null;
		$data = $callbackQuery['data'] ?? null;
		$callbackQueryId = $callbackQuery['id'] ?? null;

		if (!is_array($from) || !isset($from['id']) || !is_string($data) || !is_string($callbackQueryId)) {
			return;
		}

		$telegramId = (string) $from['id'];
		$user = $this->loadUser($telegramId);
		$locale = $this->localeResolver->resolve(['message' => ['from' => $from]], $user);
		App::setLocale($locale);

		if ($user?->role !== TelegramUserRole::Admin) {
			$this->telegram->answerCallbackQuery($callbackQueryId, __('bot.replies.no_permission'));
			return;
		}

		if (!is_array($message) || !isset($message['chat']['id']) || !isset($message['message_id'])) {
			return;
		}

		$chatId = $message['chat']['id'];
		$messageId = (int) $message['message_id'];

		// Parse callback data: user_add, user_delete_{telegram_id}, user_refresh
		if ($data === 'user_add') {
			$this->handleUserAddCallback($chatId, $messageId, $callbackQueryId, $user);
		} elseif (str_starts_with($data, 'user_delete_')) {
			$targetTelegramId = substr($data, 13); // Remove "user_delete_" prefix
			$this->handleUserDeleteCallback($chatId, $messageId, $callbackQueryId, $targetTelegramId, $user);
		} elseif ($data === 'user_refresh') {
			$this->showUsersList($chatId, $user, $messageId);
			$this->telegram->answerCallbackQuery($callbackQueryId, __('bot.admin.users_refreshed'));
		} else {
			$this->telegram->answerCallbackQuery($callbackQueryId, __('bot.replies.error_generic'));
		}
	}

	/**
	 * Show users list with inline buttons for management.
	 */
	private function showUsersList(int|string $chatId, ?TelegramUser $user, ?int $messageId = null): void
	{
		$users = TelegramUser::query()
			->orderByDesc('id')
			->get(['id', 'telegram_id', 'role', 'is_active']);

		$lines = [__('bot.admin.users_list_title') . "\n"];
		$buttons = [];

		foreach ($users as $u) {
			$role = $u->role?->value ?? (string) $u->role;
			$roleLabel = $role === 'admin' ? __('bot.admin.role_admin') : __('bot.admin.role_operator');
			$status = $u->is_active ? '✅' : '❌';
			$lines[] = "{$status} ID: {$u->telegram_id} ({$roleLabel})";

			// Add delete button for each user (except self)
			if ($u->telegram_id !== $user?->telegram_id) {
				$buttons[] = [
					[
						'text' => __('bot.admin.delete_user', ['id' => $u->telegram_id]),
						'callback_data' => 'user_delete_' . $u->telegram_id,
					],
				];
			}
		}

		// Add action buttons
		$buttons[] = [
			[
				'text' => __('bot.admin.add_user'),
				'callback_data' => 'user_add',
			],
			[
				'text' => __('bot.admin.refresh'),
				'callback_data' => 'user_refresh',
			],
		];

		$text = implode("\n", $lines);
		$inlineKeyboard = $this->kb->inline($buttons);

		if ($messageId !== null) {
			$this->telegram->editMessageText($chatId, $messageId, $text, $inlineKeyboard);
		} else {
			$this->telegram->sendMessage($chatId, $text, $inlineKeyboard);
		}
	}

	/**
	 * Handle user add callback - ask for Telegram ID.
	 */
	private function handleUserAddCallback(int|string $chatId, int $messageId, string $callbackQueryId, ?TelegramUser $user): void
	{
		$this->telegram->answerCallbackQuery($callbackQueryId, __('bot.admin.adduser_help_short'));
		$this->telegram->editMessageText(
			$chatId,
			$messageId,
			__('bot.admin.adduser_callback_help'),
			$this->kb->inline([
				[
					[
						'text' => __('bot.admin.back_to_users'),
						'callback_data' => 'user_refresh',
					],
				],
			])
		);
	}

	/**
	 * Handle user delete callback.
	 */
	private function handleUserDeleteCallback(int|string $chatId, int $messageId, string $callbackQueryId, string $targetTelegramId, ?TelegramUser $user): void
	{
		try {
			$targetUser = TelegramUser::query()->where('telegram_id', $targetTelegramId)->first();

			if ($targetUser === null) {
				$this->telegram->answerCallbackQuery($callbackQueryId, __('bot.admin.user_not_found'));
				$this->showUsersList($chatId, $user, $messageId);
				return;
			}

			// Prevent self-deletion
			if ($targetUser->telegram_id === $user?->telegram_id) {
				$this->telegram->answerCallbackQuery($callbackQueryId, __('bot.admin.cannot_delete_self'));
				return;
			}

			$targetUser->delete();
			$this->telegram->answerCallbackQuery($callbackQueryId, __('bot.admin.user_deleted'));
			$this->showUsersList($chatId, $user, $messageId);
		} catch (Throwable $e) {
			Log::error('bot.deleteuser_failed', ['error' => $e->getMessage(), 'telegram_id' => $targetTelegramId]);
			$this->telegram->answerCallbackQuery($callbackQueryId, __('bot.replies.error_generic'));
		}
	}
}












