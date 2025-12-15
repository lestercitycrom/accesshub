Ок, идём дальше: **админская часть** — `/add` (пошаговый мастер), `/import` (массовая загрузка текстом или файлом), и `/log` (просмотр истории). Плюс — **шифрование чувствительных полей** через встроенные encrypted casts Laravel.

Ниже — шаги + код + как тестить после каждого шага.

---

# Шаг 1. Шифрование чувствительных полей (Laravel encrypted casts)

## 1.1 Обнови модель `Account`

`app/Models/Account.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Account extends Model
{
	protected $fillable = [
		'platform',
		'game',
		'game_login',
		'game_password',
		'email_login',
		'email_password',
		'codes_receiver_emails',
		'platform_meta',
		'max_uses',
		'available_uses',
		'next_release_at',
		'is_active',
	];

	protected $casts = [
		// Security: encrypt sensitive fields at rest
		'game_password' => 'encrypted',
		'email_login' => 'encrypted',
		'email_password' => 'encrypted',

		'codes_receiver_emails' => 'array',
		'platform_meta' => 'array',
		'next_release_at' => 'datetime',
		'is_active' => 'bool',
	];
}
```

### Как протестить (Шаг 1)

1. Открой tinker:

```bash
php artisan tinker
```

2. Создай запись:

```php
$a = \App\Models\Account::create([
	'platform' => 'Xbox X',
	'game' => 'Minecraft',
	'game_login' => 'enc_test_login',
	'game_password' => 'enc_test_pass',
	'email_login' => 'enc@test.com',
	'email_password' => 'mail_pass',
	'max_uses' => 3,
	'available_uses' => 3,
	'is_active' => true,
]);

$a->fresh()->game_password;
```

3. В MySQL проверь, что в `accounts.game_password/email_login/email_password` лежит **не читаемый текст** (шифротекст), а из Eloquent читается нормально.

---

# Шаг 2. Пошаговый мастер `/add` для админа

Сделаем мастер через Cache (без доп. таблиц). Админ пишет `/add`, бот задаёт вопросы и сохраняет введённое.

## 2.1 Wizard-сервис

`app/Services/AccessHub/Admin/AddAccountWizard.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\AccessHub\Admin;

use App\Models\Account;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class AddAccountWizard
{
	private const KEY_PREFIX = 'accesshub:add_wizard:';

	/**
	 * @return array{state: string, prompt: string}
	 */
	public function start(string $telegramId): array
	{
		$this->put($telegramId, [
			'step' => 'platform',
			'data' => [],
		]);

		return [
			'state' => 'started',
			'prompt' => $this->prompt('platform'),
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
	 * @return array{done: bool, message: string}
	 */
	public function handleInput(string $telegramId, string $text): array
	{
		$ctx = $this->get($telegramId);
		if ($ctx === null) {
			return [
				'done' => true,
				'message' => 'Мастер не запущен. Используйте /add.',
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

		// Special keywords
		if (Str::lower($text) === 'skip' && in_array($step, ['email_login', 'email_password', 'codes_receiver_emails', 'platform_meta'], true)) {
			$ctx['step'] = $this->nextStep($step);
			$this->put($telegramId, $ctx);

			return [
				'done' => false,
				'message' => $this->prompt((string) $ctx['step']),
			];
		}

		// Assign input
		$data[$step] = $this->normalize($step, $text);

		$next = $this->nextStep($step);

		// Finish?
		if ($next === 'finish') {
			$account = $this->createAccount($data);
			$this->cancel($telegramId);

			return [
				'done' => true,
				'message' => "Готово. Аккаунт добавлен.\nID: {$account->id}\n{$account->game} ({$account->platform})\nLogin: {$account->game_login}",
			];
		}

		$ctx['data'] = $data;
		$ctx['step'] = $next;
		$this->put($telegramId, $ctx);

		return [
			'done' => false,
			'message' => $this->prompt($next),
		];
	}

	private function createAccount(array $data): Account
	{
		$maxUses = (int) config('accesshub.max_uses_default', 3);

		$emailLogin = $data['email_login'] ?? null;
		$emailPassword = $data['email_password'] ?? null;

		return Account::create([
			'platform' => (string) $data['platform'],
			'game' => (string) $data['game'],
			'game_login' => (string) $data['game_login'],
			'game_password' => (string) $data['game_password'],

			'email_login' => $emailLogin !== null ? (string) $emailLogin : null,
			'email_password' => $emailPassword !== null ? (string) $emailPassword : null,

			'codes_receiver_emails' => $data['codes_receiver_emails'] ?? null,
			'platform_meta' => $data['platform_meta'] ?? null,

			'max_uses' => $maxUses,
			'available_uses' => $maxUses,
			'next_release_at' => null,
			'is_active' => true,
		]);
	}

	private function normalize(string $step, string $text): mixed
	{
		if ($step === 'codes_receiver_emails') {
			// Input: mail1@mail.com, mail2@mail.com OR "-"
			if ($text === '-' || Str::lower($text) === 'none') {
				return null;
			}

			$items = array_values(array_filter(array_map(
				static fn (string $v): string => trim($v),
				explode(',', $text)
			), static fn (string $v): bool => $v !== ''));

			return $items === [] ? null : $items;
		}

		if ($step === 'platform_meta') {
			// Simple: store raw text for now
			if ($text === '-' || Str::lower($text) === 'none') {
				return null;
			}

			return ['raw' => $text];
		}

		return $text;
	}

	private function nextStep(string $step): string
	{
		return match ($step) {
			'platform' => 'game',
			'game' => 'game_login',
			'game_login' => 'game_password',
			'game_password' => 'email_login',
			'email_login' => 'email_password',
			'email_password' => 'codes_receiver_emails',
			'codes_receiver_emails' => 'platform_meta',
			'platform_meta' => 'finish',
			default => 'finish',
		};
	}

	private function prompt(string $step): string
	{
		return match ($step) {
			'platform' => "Введите платформу (пока тестово, позже будет строгий список).\nПример: Xbox X\n\n/abort — отмена",
			'game' => "Введите название игры.\nПример: Minecraft\n\n/abort — отмена",
			'game_login' => "Введите логин игрового аккаунта.\n\n/abort — отмена",
			'game_password' => "Введите пароль игрового аккаунта.\n\n/abort — отмена",
			'email_login' => "Введите логин почты (или 'skip').\n\n/abort — отмена",
			'email_password' => "Введите пароль почты (или 'skip').\n\n/abort — отмена",
			'codes_receiver_emails' => "Введите почты для кодов через запятую (или '-' / 'skip').\nПример: backup@mail.com, backup2@mail.com\n\n/abort — отмена",
			'platform_meta' => "Введите доп. данные платформы (или '-' / 'skip').\nПример: Steam Guard: ...\n\n/abort — отмена",
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
```

### Как протестить (Шаг 2)

1. Убедись, что ты добавлен как admin:

```bash
php artisan accesshub:user:add <твой_telegram_id> admin
```

2. В Telegram напиши:

* `/add`
* дальше отвечай по шагам (platform → game → login → pass → email …)

3. Проверь в MySQL таблицу `accounts` — появилась запись, пароль хранится шифрованно.

---

# Шаг 3. Массовый импорт `/import` (текстом или файлом)

## 3.1 Расширяем Telegram API клиент (скачивание файла)

`app/Services/Telegram/TelegramApiClient.php` — ДОБАВЬ методы:

```php
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
```

## 3.2 Сервис импорта

`app/Services/AccessHub/Admin/BulkImportService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\AccessHub\Admin;

use App\Models\Account;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

final class BulkImportService
{
	/**
	 * @return array{added: int, skipped: int, errors: int}
	 */
	public function importFromText(string $text): array
	{
		$lines = preg_split('/\R/u', trim($text)) ?: [];

		$added = 0;
		$skipped = 0;
		$errors = 0;

		$maxUses = (int) config('accesshub.max_uses_default', 3);

		foreach ($lines as $line) {
			$line = trim((string) $line);

			if ($line === '' || str_starts_with($line, '#')) {
				continue;
			}

			// Format:
			// Platform | Game | game_login | game_password | email_login | email_password | backup_emails
			$parts = array_map(static fn (string $v): string => trim($v), explode('|', $line));

			if (count($parts) < 4) {
				$errors++;
				continue;
			}

			[$platform, $game, $gameLogin, $gamePassword] = $parts;

			$emailLogin = $parts[4] ?? null;
			$emailPassword = $parts[5] ?? null;
			$backupEmails = $parts[6] ?? null;

			$emailLogin = $this->nullIfDash($emailLogin);
			$emailPassword = $this->nullIfDash($emailPassword);

			$codes = null;
			$backupEmails = $this->nullIfDash($backupEmails);
			if ($backupEmails !== null) {
				$codes = array_values(array_filter(array_map(
					static fn (string $v): string => trim($v),
					preg_split('/[,;\s]+/u', $backupEmails) ?: []
				), static fn (string $v): bool => $v !== ''));
			}

			try {
				Account::create([
					'platform' => $platform,
					'game' => $game,
					'game_login' => $gameLogin,
					'game_password' => $gamePassword,
					'email_login' => $emailLogin,
					'email_password' => $emailPassword,
					'codes_receiver_emails' => $codes,
					'platform_meta' => null,
					'max_uses' => $maxUses,
					'available_uses' => $maxUses,
					'next_release_at' => null,
					'is_active' => true,
				]);

				$added++;
			} catch (QueryException) {
				// Duplicate by unique(platform, game, game_login) or other constraint
				$skipped++;
			} catch (\Throwable) {
				$errors++;
			}
		}

		return [
			'added' => $added,
			'skipped' => $skipped,
			'errors' => $errors,
		];
	}

	private function nullIfDash(?string $value): ?string
	{
		if ($value === null) {
			return null;
		}

		$value = trim($value);

		if ($value === '' || $value === '-' || Str::lower($value) === 'none') {
			return null;
		}

		return $value;
	}
}
```

### Как протестить (Шаг 3)

1. В Telegram (админом) отправь:

```
/import
Xbox X | Minecraft | bulk_login_1 | bulk_pass_1 | mail1@mail.com | mailpass1 | backup1@mail.com
Xbox X | Minecraft | bulk_login_2 | bulk_pass_2 | - | - | -
```

2. Бот должен ответить: сколько добавил/пропустил/ошибок.
3. Проверь `accounts` в MySQL.

---

# Шаг 4. Команда `/log` (просмотр истории по аккаунту)

`/log 123` → показать последние 10 выдач по account_id.

## `app/Services/AccessHub/Admin/LogsService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\AccessHub\Admin;

use App\Models\IssuanceLog;

final class LogsService
{
	public function accountLog(int $accountId, int $limit = 10): string
	{
		$items = IssuanceLog::query()
			->where('account_id', $accountId)
			->orderByDesc('issued_at')
			->limit($limit)
			->get();

		if ($items->isEmpty()) {
			return 'Логов нет.';
		}

		$lines = [];
		$lines[] = "Логи аккаунта #{$accountId} (последние {$limit}):";
		$lines[] = '';

		foreach ($items as $log) {
			$lines[] = "{$log->issued_at?->format('Y-m-d H:i:s')} | order: {$log->order_id} | operator: {$log->operator_telegram_id}";
		}

		return implode("\n", $lines);
	}
}
```

### Как протестить (Шаг 4)

1. Сделай выдачу аккаунта оператором.
2. Админом напиши:

```
/log 1
```

3. Убедись, что выводит записи.

---

# Шаг 5. Обновляем `AccessHubBotService` — маршрутизация команд `/add /abort /import /log`

Заменяй файл целиком:

`app/Services/AccessHub/AccessHubBotService.php`:

```php
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
```

### Как протестить (Шаг 5)

1. В Telegram `/help` — должен показать список команд.
2. `/add` — должен запустить мастер.
3. `/abort` — отменяет мастер.
4. `/import` — подсказывает формат.
5. Отправь TXT-файл документом с 2–3 строками импорта — должен добавить.
6. Сделай выдачу оператором → `/log <id>` должен показывать записи.

