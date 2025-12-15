Отлично. Тогда следующий шаг — **поднять “ядро” AccessHub**: БД + модели + сервис выдачи + восстановление доступности + обработка сообщений (order_id + Game(Platform) + qty).

Ниже даю **полный, компилируемый набор файлов** под **Laravel 12 + PHP 8.3 + MySQL**, с PSR-12 и табами.

---

# Шаг 1. Конфиг проекта AccessHub

## `config/accesshub.php`

```php
<?php

declare(strict_types=1);

return [
	/*
	|--------------------------------------------------------------------------
	| Platforms
	|--------------------------------------------------------------------------
	| Пока можно работать без списка платформ.
	| Позже включим enforce_platform_list и дадим строгий список.
	*/
	'platforms' => [
		'PS',
		'Xbox',
		'Steam',
		'Epic',
		'Nintendo',
	],

	'enforce_platform_list' => false,

	/*
	|--------------------------------------------------------------------------
	| Issue rules
	|--------------------------------------------------------------------------
	*/
	'max_uses_default' => 3,
	'release_days' => 14,

	/*
	|--------------------------------------------------------------------------
	| Security / Access
	|--------------------------------------------------------------------------
	| По умолчанию доступ только у тех, кто есть в telegram_users и активен.
	*/
	'deny_by_default' => true,
];
```

---

# Шаг 2. Миграции (MySQL)

## 2.1 `database/migrations/2025_12_15_000001_create_telegram_users_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration
{
	public function up(): void
	{
		Schema::create('telegram_users', function (Blueprint $table): void {
			$table->id();
			$table->string('telegram_id')->unique();
			$table->string('role')->default('operator'); // admin|operator
			$table->boolean('is_active')->default(true);
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('telegram_users');
	}
};
```

## 2.2 `database/migrations/2025_12_15_000002_create_accounts_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration
{
	public function up(): void
	{
		Schema::create('accounts', function (Blueprint $table): void {
			$table->id();

			$table->string('platform');
			$table->string('game');

			$table->string('game_login');
			$table->text('game_password');

			// Sensitive (store encrypted in app layer)
			$table->text('email_login')->nullable();
			$table->text('email_password')->nullable();

			$table->json('codes_receiver_emails')->nullable();
			$table->json('platform_meta')->nullable();

			$table->unsignedTinyInteger('max_uses')->default(3);
			$table->unsignedTinyInteger('available_uses')->default(3);

			// cooldown = hold (единая пауза)
			$table->timestamp('next_release_at')->nullable();

			$table->boolean('is_active')->default(true);

			$table->timestamps();

			$table->index(['platform', 'game']);
			$table->unique(['platform', 'game', 'game_login'], 'accounts_platform_game_login_unique');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('accounts');
	}
};
```

## 2.3 `database/migrations/2025_12_15_000003_create_issuance_logs_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration
{
	public function up(): void
	{
		Schema::create('issuance_logs', function (Blueprint $table): void {
			$table->id();

			$table->string('order_id');
			$table->string('operator_telegram_id');

			$table->foreignId('account_id')->constrained('accounts');

			$table->string('game');
			$table->string('platform');

			$table->timestamp('issued_at');
			$table->string('note')->nullable();

			$table->timestamps();

			$table->index(['order_id', 'operator_telegram_id']);
			$table->unique(['order_id', 'account_id'], 'issuance_logs_order_account_unique');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('issuance_logs');
	}
};
```

Запускаем:

```bash
php artisan migrate
```

---

# Шаг 3. Enum роли + модели

## 3.1 `app/Enums/TelegramUserRole.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum TelegramUserRole: string
{
	case Admin = 'admin';
	case Operator = 'operator';
}
```

## 3.2 `app/Models/TelegramUser.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TelegramUserRole;
use Illuminate\Database\Eloquent\Model;

final class TelegramUser extends Model
{
	protected $fillable = [
		'telegram_id',
		'role',
		'is_active',
	];

	protected $casts = [
		'is_active' => 'bool',
		'role' => TelegramUserRole::class,
	];
}
```

## 3.3 `app/Models/Account.php`

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
		'codes_receiver_emails' => 'array',
		'platform_meta' => 'array',
		'next_release_at' => 'datetime',
		'is_active' => 'bool',
	];
}
```

## 3.4 `app/Models/IssuanceLog.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IssuanceLog extends Model
{
	protected $fillable = [
		'order_id',
		'operator_telegram_id',
		'account_id',
		'game',
		'platform',
		'issued_at',
		'note',
	];

	protected $casts = [
		'issued_at' => 'datetime',
	];

	public function account(): BelongsTo
	{
		return $this->belongsTo(Account::class);
	}
}
```

---

# Шаг 4. Telegram API клиент

## `app/Services/Telegram/TelegramApiClient.php`

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
}
```

---

# Шаг 5. Парсер сообщений (order_id + Game(Platform) + qty)

## 5.1 DTO `app/Services/Telegram/ParsedIssueRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Telegram;

final class ParsedIssueRequest
{
	public function __construct(
		public readonly string $orderId,
		public readonly string $game,
		public readonly string $platform,
		public readonly int $qty
	) {
	}
}
```

## 5.2 Парсер `app/Services/Telegram/TelegramUpdateParser.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Telegram;

final class TelegramUpdateParser
{
	public function parseIssueRequest(string $text): ?ParsedIssueRequest
	{
		$lines = array_values(array_filter(array_map(
			static fn (string $v): string => trim($v),
			preg_split('/\R/u', trim($text)) ?: []
		), static fn (string $v): bool => $v !== ''));

		if (count($lines) < 2) {
			return null;
		}

		$orderId = $lines[0];
		$gamePlatform = $lines[1];
		$qtyLine = $lines[2] ?? '';

		if (!preg_match('/^\d+$/', $orderId)) {
			return null;
		}

		$qty = $this->extractQty($gamePlatform, $qtyLine);
		$gamePlatform = $this->stripQtySuffix($gamePlatform);

		if (!preg_match('/^(.*?)\s*\((.*?)\)\s*$/u', $gamePlatform, $m)) {
			return null;
		}

		$game = trim($m[1]);
		$platform = trim($m[2]);

		if ($game === '' || $platform === '') {
			return null;
		}

		return new ParsedIssueRequest($orderId, $game, $platform, $qty);
	}

	private function extractQty(string $line2, string $line3): int
	{
		// Supports: "Game (Platform) x2" or third line "qty: 2"
		if (preg_match('/\bx(\d{1,2})\b/u', $line2, $m)) {
			return max(1, (int) $m[1]);
		}

		if (preg_match('/\bqty\s*:\s*(\d{1,2})\b/iu', $line3, $m)) {
			return max(1, (int) $m[1]);
		}

		return 1;
	}

	private function stripQtySuffix(string $line2): string
	{
		return (string) preg_replace('/\s+\bx\d{1,2}\b\s*$/u', '', $line2);
	}
}
```

---

# Шаг 6. Сервис выдачи аккаунтов (ключевая логика)

## `app/Services/AccessHub/IssueAccountsService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\AccessHub;

use App\Models\Account;
use App\Models\IssuanceLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class IssueAccountsService
{
	/**
	 * @return array<int, array{game_login: string, game_password: string}>
	 */
	public function issue(
		string $orderId,
		string $operatorTelegramId,
		string $game,
		string $platform,
		int $qty
	): array {
		$qty = max(1, $qty);
		$now = CarbonImmutable::now();
		$releaseDays = (int) config('accesshub.release_days', 14);

		$this->assertPlatformAllowed($platform);

		return DB::transaction(function () use ($orderId, $operatorTelegramId, $game, $platform, $qty, $now, $releaseDays): array {
			$accounts = Account::query()
				->where('is_active', true)
				->where('game', $game)
				->where('platform', $platform)
				->where('available_uses', '>', 0)
				->orderBy('id')
				->lockForUpdate()
				->limit($qty)
				->get();

			if ($accounts->count() < $qty) {
				throw new RuntimeException("Недостаточно свободных аккаунтов: доступно {$accounts->count()}, нужно {$qty}.");
			}

			$result = [];

			foreach ($accounts as $account) {
				$account->available_uses = (int) $account->available_uses - 1;

				if ((int) $account->available_uses <= 0) {
					$account->available_uses = 0;
					$account->next_release_at = $now->addDays($releaseDays);
				}

				$account->save();

				IssuanceLog::create([
					'order_id' => $orderId,
					'operator_telegram_id' => $operatorTelegramId,
					'account_id' => $account->id,
					'game' => $account->game,
					'platform' => $account->platform,
					'issued_at' => $now,
					'note' => null,
				]);

				$result[] = [
					'game_login' => (string) $account->game_login,
					'game_password' => (string) $account->game_password,
				];
			}

			return $result;
		}, 3);
	}

	private function assertPlatformAllowed(string $platform): void
	{
		$enforce = (bool) config('accesshub.enforce_platform_list', false);

		if (!$enforce) {
			return;
		}

		$allowed = (array) config('accesshub.platforms', []);

		if (!in_array($platform, $allowed, true)) {
			throw new RuntimeException('Неизвестная платформа. Уточните список платформ у администратора.');
		}
	}
}
```

---

# Шаг 7. Восстановление доступности (через 14 дней)

## 7.1 Команда `app/Console/Commands/AccessHubRestoreAvailabilityCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class AccessHubRestoreAvailabilityCommand extends Command
{
	protected $signature = 'accesshub:restore-availability';
	protected $description = 'Restore availability for accounts when next_release_at is reached (cooldown=hold).';

	public function handle(): int
	{
		$now = CarbonImmutable::now();

		$updated = Account::query()
			->where('is_active', true)
			->where('available_uses', '=', 0)
			->whereNotNull('next_release_at')
			->where('next_release_at', '<=', $now)
			->update([
				'available_uses' => 1,
				'next_release_at' => null,
				'updated_at' => $now,
			]);

		$this->info("Restored accounts: {$updated}");

		return self::SUCCESS;
	}
}
```

## 7.2 Регистрация расписания

В зависимости от структуры Laravel 12 у тебя может быть `routes/console.php`.

**Если есть `routes/console.php`**, добавь:

```php
<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('accesshub:restore-availability')->hourly();
```

---

# Шаг 8. Связываем webhook → выдачу

## 8.1 Сервис бота `app/Services/AccessHub/AccessHubBotService.php`

```php
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
```

## 8.2 Обнови `TelegramWebhookController` чтобы дергать сервис

`app/Http/Controllers/TelegramWebhookController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AccessHub\AccessHubBotService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class TelegramWebhookController extends Controller
{
	public function __invoke(Request $request, AccessHubBotService $bot): Response
	{
		$secret = (string) config('services.telegram.webhook_secret');
		$header = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

		if ($secret === '' || !hash_equals($secret, $header)) {
			return response('Forbidden', 403);
		}

		/** @var array<string, mixed> $update */
		$update = (array) $request->all();

		$bot->handleUpdate($update);

		return response('OK', 200);
	}
}
```

---

# Шаг 9. Быстро добавить себя как админа (чтобы не “deny by default”)

Сделаем команду.

## `app/Console/Commands/AccessHubUserAddCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TelegramUserRole;
use App\Models\TelegramUser;
use Illuminate\Console\Command;

final class AccessHubUserAddCommand extends Command
{
	protected $signature = 'accesshub:user:add {telegram_id} {role=operator}';
	protected $description = 'Add or update telegram user for AccessHub (admin/operator).';

	public function handle(): int
	{
		$telegramId = (string) $this->argument('telegram_id');
		$role = (string) $this->argument('role');

		$roleEnum = TelegramUserRole::tryFrom($role);
		if ($roleEnum === null) {
			$this->error('Role must be: admin or operator.');
			return self::FAILURE;
		}

		TelegramUser::query()->updateOrCreate(
			['telegram_id' => $telegramId],
			['role' => $roleEnum, 'is_active' => true]
		);

		$this->info("OK: {$telegramId} -> {$roleEnum->value}");

		return self::SUCCESS;
	}
}
```

Запусти:

```bash
php artisan accesshub:user:add <твой_telegram_id> admin
```

---

# Шаг 10. Тест — добавь 1-2 аккаунта через tinker

```bash
php artisan tinker
```

```php
\App\Models\Account::create([
	'platform' => 'Xbox X',
	'game' => 'Minecraft',
	'game_login' => 'test_login_1',
	'game_password' => 'test_pass_1',
	'max_uses' => 3,
	'available_uses' => 3,
	'is_active' => true,
]);

\App\Models\Account::create([
	'platform' => 'Xbox X',
	'game' => 'Minecraft',
	'game_login' => 'test_login_2',
	'game_password' => 'test_pass_2',
	'max_uses' => 3,
	'available_uses' => 3,
	'is_active' => true,
]);
```

После этого в Telegram напиши:

```
2446303
Minecraft (Xbox X) x2
```

Должно выдать **2 разных** логина/пароля и записать 2 строки в `issuance_logs`.

