# AccessHub — план работ по закрытию оставшихся пунктов основного ТЗ (импорт файлами TXT/CSV/XLSX)

Дата: **2025-12-23**  
Контекст: **Laravel 12 + PHP 8.3 + MySQL**.  

Текущее состояние:
- Импорт через текст **есть**: `POST /webapp/api/admin/import/text` (формат: многострочный текст, разделитель `|`)
- Дедупликация **есть**: unique `(platform, game, game_login)` → дубликаты в `skipped`
- Логи **есть**: `GET /webapp/api/admin/logs`
- Экспорт CSV логов **есть**: `GET /webapp/api/admin/export/issuance_logs.csv` (с фильтрами)
- Импорт файлом **нет**: endpoint отсутствует

Цель этого плана: **добавить импорт файлами** TXT/CSV/XLSX и закрыть обязательный пункт ТЗ: “массовая загрузка аккаунтов через файл”.

---

## 1) Что осталось по основному ТЗ (однозначно)

### 1.1. Массовая загрузка через файл (CSV/Excel/TXT) — ❌ не сделано
ТЗ требует: “Массово загружать аккаунты через файл (CSV/Excel/TXT)”.

**DoD:**
- существует endpoint `POST /webapp/api/admin/import/file`
- принимает файл `.txt/.csv/.xlsx`
- обрабатывает строки по той же бизнес-логике, что и `/import/text`
- возвращает структуру результата: `added`, `skipped`, `errors`
- не падает на единичных ошибках — продолжает импорт

### 1.2. “Строгий список платформ” — ⚠️ в ТЗ однозначно требуется, но список не предоставлен
В ТЗ указано, что платформы должны быть фиксированным списком (PS/Xbox/Steam/Epic/Nintendo + уточнения).  
Поскольку финальный список заказчик ещё не дал, это **блокер на “идеальную” валидацию**, но не блокер на импорт.

**DoD (минимум, чтобы не нарушать ТЗ):**
- в коде есть конфиг `config/accesshub.php` → `platforms` (пока тестовый список)
- валидация импорта проверяет платформу по этому списку
- когда список будет предоставлен — обновляется конфиг без переписывания логики

### 1.3. Выдача нескольких аккаунтов на 1 заказ (qty) — ⚠️ формат не утверждён
ТЗ фиксирует необходимость выдачи нескольких аккаунтов под один `order_id`, но формат передачи `qty` оператором не утвержден.  
Если логика выдачи у вас уже реализована — это может считаться закрытым частично.  
В рамках этого плана: только **зафиксировать TODO** (без реализации UI/формата).

---

## 2) Технический подход импорта файлом (единая бизнес-логика)

Ключевая идея: **не дублировать** бизнес-логику импорта.

Сделать 2 уровня:
1) **Парсинг источника** (TXT/CSV/XLSX) → массив унифицированных DTO
2) **Единый ImportService** → валидирует, пишет в БД, считает `added/skipped/errors`

### 2.1. Рекомендуемая структура классов

```
app/
  Http/Controllers/Webapp/Admin/ImportFileController.php
  Http/Requests/Webapp/Admin/ImportFileRequest.php
  Services/Import/
    ImportAccountsService.php
    ImportResult.php
    Parsers/
      TextImportParser.php
      CsvImportParser.php
      XlsxImportParser.php
    DTO/
      AccountImportRow.php
config/
  accesshub.php
```

---

## 3) API контракт (обязательный)

### 3.1. Endpoint
`POST /webapp/api/admin/import/file`

### 3.2. Request
`multipart/form-data`:
- `file` — файл: `txt|csv|xlsx`

### 3.3. Response (JSON)
```json
{
  "added": 12,
  "skipped": 3,
  "errors": [
    {"row": 5, "reason": "Missing required fields"},
    {"row": 9, "reason": "Platform not allowed"}
  ]
}
```

**Правило:** наличие `errors` не мешает успешной обработке других строк.

---

## 4) Форматы входных данных

### 4.1. TXT (как в текущем /import/text)
- многострочный текст
- разделитель полей: `|`

Пример строки:
```
PS | FIFA 23 | login1 | pass1 | mail@mail.com | mailpass | backup@mail.com
```

### 4.2. CSV
Поддержать 2 варианта:
- **с заголовком** колонок
- **без заголовка** (позиционный)

**Рекомендуемые колонки:**
- `platform`
- `game`
- `game_login`
- `game_password`
- `email_login` (optional)
- `email_password` (optional)
- `codes_receiver_emails` (optional, строка, разделитель `,`)
- `platform_meta` (optional, JSON строкой)

### 4.3. XLSX
- лист 1
- те же колонки, что для CSV (предпочтительно с заголовком)

---

## 5) Пошаговая реализация (с примерами кода)

### Шаг 1 — Конфиг платформ (минимум)
`config/accesshub.php`:

```php
<?php

declare(strict_types=1);

return [
	// TODO: Replace with final customer-approved list
	'platforms' => [
		'PS',
		'Xbox',
		'Steam',
		'Epic',
		'Nintendo',
	],
];
```

---

### Шаг 2 — FormRequest для импорта файла
`app/Http/Requests/Webapp/Admin/ImportFileRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Webapp\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class ImportFileRequest extends FormRequest
{
	public function rules(): array
	{
		return [
			'file' => [
				'required',
				'file',
				'max:10240', // 10MB
				'mimes:txt,csv,xlsx',
			],
		];
	}
}
```

---

### Шаг 3 — Роут + контроллер
`routes/api.php` (или где у вас webapp api роуты):

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Webapp\Admin\ImportFileController;

Route::post('/webapp/api/admin/import/file', ImportFileController::class);
```

`app/Http/Controllers/Webapp/Admin/ImportFileController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webapp\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Webapp\Admin\ImportFileRequest;
use App\Services\Import\ImportAccountsService;
use App\Services\Import\Parsers\CsvImportParser;
use App\Services\Import\Parsers\TextImportParser;
use App\Services\Import\Parsers\XlsxImportParser;
use Illuminate\Http\JsonResponse;

final class ImportFileController extends Controller
{
	public function __construct(
		private readonly TextImportParser $textParser,
		private readonly CsvImportParser $csvParser,
		private readonly XlsxImportParser $xlsxParser,
		private readonly ImportAccountsService $importer,
	) {
	}

	public function __invoke(ImportFileRequest $request): JsonResponse
	{
		$file = $request->file('file');

		$ext = strtolower((string) $file->getClientOriginalExtension());

		$rows = match ($ext) {
			'txt' => $this->textParser->parseFile((string) $file->getRealPath()),
			'csv' => $this->csvParser->parseFile((string) $file->getRealPath()),
			'xlsx' => $this->xlsxParser->parseFile((string) $file->getRealPath()),
			default => [],
		};

		$result = $this->importer->import($rows);

		return response()->json([
			'added' => $result->added,
			'skipped' => $result->skipped,
			'errors' => $result->errors,
		]);
	}
}
```

---

### Шаг 4 — DTO строки импорта
`app/Services/Import/DTO/AccountImportRow.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Import\DTO;

final class AccountImportRow
{
	public function __construct(
		public readonly int $rowNumber,
		public readonly string $platform,
		public readonly string $game,
		public readonly string $gameLogin,
		public readonly string $gamePassword,
		public readonly ?string $emailLogin = null,
		public readonly ?string $emailPassword = null,
		public readonly array $codesReceiverEmails = [],
		public readonly array $platformMeta = [],
	) {
	}
}
```

---

### Шаг 5 — Парсер TXT (использует текущую логику)
`app/Services/Import/Parsers/TextImportParser.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Import\Parsers;

use App\Services\Import\DTO\AccountImportRow;

final class TextImportParser
{
	/**
	 * Parse TXT file with pipe-separated lines.
	 *
	 * @return array<int, AccountImportRow>
	 */
	public function parseFile(string $path): array
	{
		$text = (string) file_get_contents($path);

		return $this->parseText($text);
	}

	/**
	 * @return array<int, AccountImportRow>
	 */
	public function parseText(string $text): array
	{
		$lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
		$rows = [];

		foreach ($lines as $index => $line) {
			$line = trim((string) $line);

			if ($line === '') {
				continue;
			}

			// Expected: platform | game | login | pass | email | emailpass | backups
			$parts = array_map('trim', explode('|', $line));

			$platform = $parts[0] ?? '';
			$game = $parts[1] ?? '';
			$gameLogin = $parts[2] ?? '';
			$gamePassword = $parts[3] ?? '';
			$emailLogin = $parts[4] ?? null;
			$emailPassword = $parts[5] ?? null;
			$backup = $parts[6] ?? '';

			$codes = [];
			if ($backup !== '' && $backup !== '-') {
				$codes = array_values(array_filter(array_map('trim', explode(',', $backup))));
			}

			$rows[] = new AccountImportRow(
				rowNumber: $index + 1,
				platform: $platform,
				game: $game,
				gameLogin: $gameLogin,
				gamePassword: $gamePassword,
				emailLogin: $emailLogin !== '-' ? $emailLogin : null,
				emailPassword: $emailPassword !== '-' ? $emailPassword : null,
				codesReceiverEmails: $codes,
				platformMeta: [],
			);
		}

		return $rows;
	}
}
```

---

### Шаг 6 — Парсер CSV (без внешних зависимостей)
`app/Services/Import/Parsers/CsvImportParser.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Import\Parsers;

use App\Services\Import\DTO\AccountImportRow;

final class CsvImportParser
{
	/**
	 * @return array<int, AccountImportRow>
	 */
	public function parseFile(string $path): array
	{
		$handle = fopen($path, 'r');

		if ($handle === false) {
			return [];
		}

		$rows = [];
		$header = null;
		$rowNumber = 0;

		while (($data = fgetcsv($handle)) !== false) {
			$rowNumber++;

			if (count(array_filter($data, static fn ($v) => trim((string) $v) !== '')) === 0) {
				continue;
			}

			if ($header === null) {
				$lower = array_map(static fn ($v) => strtolower(trim((string) $v)), $data);
				$isHeader = in_array('platform', $lower, true) && in_array('game', $lower, true);

				if ($isHeader) {
					$header = $lower;
					continue;
				}

				$header = [
					'platform',
					'game',
					'game_login',
					'game_password',
					'email_login',
					'email_password',
					'codes_receiver_emails',
					'platform_meta',
				];
			}

			$assoc = [];
			foreach ($header as $i => $key) {
				$assoc[$key] = isset($data[$i]) ? trim((string) $data[$i]) : '';
			}

			$codes = array_values(array_filter(array_map('trim', explode(',', (string) ($assoc['codes_receiver_emails'] ?? '')))));

			$meta = [];
			$metaRaw = trim((string) ($assoc['platform_meta'] ?? ''));
			if ($metaRaw !== '') {
				$decoded = json_decode($metaRaw, true);
				if (is_array($decoded)) {
					$meta = $decoded;
				}
			}

			$rows[] = new AccountImportRow(
				rowNumber: $rowNumber,
				platform: (string) ($assoc['platform'] ?? ''),
				game: (string) ($assoc['game'] ?? ''),
				gameLogin: (string) ($assoc['game_login'] ?? ''),
				gamePassword: (string) ($assoc['game_password'] ?? ''),
				emailLogin: ($assoc['email_login'] ?? '') !== '' ? (string) $assoc['email_login'] : null,
				emailPassword: ($assoc['email_password'] ?? '') !== '' ? (string) $assoc['email_password'] : null,
				codesReceiverEmails: $codes,
				platformMeta: $meta,
			);
		}

		fclose($handle);

		return $rows;
	}
}
```

---

### Шаг 7 — XLSX парсер (через maatwebsite/excel)

#### 7.1. Установка зависимости
```bash
composer require maatwebsite/excel:^3.1
```

#### 7.2. Парсер XLSX
`app/Services/Import/Parsers/XlsxImportParser.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Import\Parsers;

use App\Services\Import\DTO\AccountImportRow;
use Maatwebsite\Excel\Facades\Excel;

final class XlsxImportParser
{
	/**
	 * @return array<int, AccountImportRow>
	 */
	public function parseFile(string $path): array
	{
		$sheets = Excel::toArray([], $path);

		if (!isset($sheets[0]) || !is_array($sheets[0])) {
			return [];
		}

		$rowsRaw = $sheets[0];
		$header = null;
		$rows = [];

		foreach ($rowsRaw as $i => $row) {
			if (!is_array($row)) {
				continue;
			}

			$rowNumber = $i + 1;

			$values = array_map(static fn ($v) => trim((string) $v), $row);
			if (count(array_filter($values, static fn ($v) => $v !== '')) === 0) {
				continue;
			}

			if ($header === null) {
				$lower = array_map(static fn ($v) => strtolower($v), $values);
				$isHeader = in_array('platform', $lower, true) && in_array('game', $lower, true);

				$header = $isHeader ? $lower : [
					'platform',
					'game',
					'game_login',
					'game_password',
					'email_login',
					'email_password',
					'codes_receiver_emails',
					'platform_meta',
				];

				if ($isHeader) {
					continue;
				}
			}

			$assoc = [];
			foreach ($header as $idx => $key) {
				$assoc[$key] = $values[$idx] ?? '';
			}

			$codes = array_values(array_filter(array_map('trim', explode(',', (string) ($assoc['codes_receiver_emails'] ?? '')))));

			$meta = [];
			$metaRaw = trim((string) ($assoc['platform_meta'] ?? ''));
			if ($metaRaw !== '') {
				$decoded = json_decode($metaRaw, true);
				if (is_array($decoded)) {
					$meta = $decoded;
				}
			}

			$rows[] = new AccountImportRow(
				rowNumber: $rowNumber,
				platform: (string) ($assoc['platform'] ?? ''),
				game: (string) ($assoc['game'] ?? ''),
				gameLogin: (string) ($assoc['game_login'] ?? ''),
				gamePassword: (string) ($assoc['game_password'] ?? ''),
				emailLogin: ($assoc['email_login'] ?? '') !== '' ? (string) $assoc['email_login'] : null,
				emailPassword: ($assoc['email_password'] ?? '') !== '' ? (string) $assoc['email_password'] : null,
				codesReceiverEmails: $codes,
				platformMeta: $meta,
			);
		}

		return $rows;
	}
}
```

---

### Шаг 8 — Единый ImportService (бизнес-логика)
`app/Services/Import/ImportResult.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Import;

final class ImportResult
{
	/**
	 * @param array<int, array{row:int, reason:string}> $errors
	 */
	public function __construct(
		public int $added = 0,
		public int $skipped = 0,
		public array $errors = [],
	) {
	}
}
```

`app/Services/Import/ImportAccountsService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Account;
use App\Services\Import\DTO\AccountImportRow;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;

final class ImportAccountsService
{
	/**
	 * @param array<int, AccountImportRow> $rows
	 */
	public function import(array $rows): ImportResult
	{
		$result = new ImportResult();
		$allowedPlatforms = (array) config('accesshub.platforms', []);

		foreach ($rows as $row) {
			// Basic validation
			if (trim($row->platform) === '' || trim($row->game) === '' || trim($row->gameLogin) === '' || trim($row->gamePassword) === '') {
				$result->errors[] = ['row' => $row->rowNumber, 'reason' => 'Missing required fields'];
				continue;
			}

			// Minimal platform validation
			if ($allowedPlatforms !== [] && !in_array($row->platform, $allowedPlatforms, true)) {
				$result->errors[] = ['row' => $row->rowNumber, 'reason' => 'Platform not allowed'];
				continue;
			}

			try {
				Account::query()->create([
					'platform' => $row->platform,
					'game' => $row->game,
					'game_login' => $row->gameLogin,
					'game_password' => $row->gamePassword,

					// Sensitive fields: encrypt (adjust if you use encrypted casts)
					'email_login' => $row->emailLogin !== null ? Crypt::encryptString($row->emailLogin) : null,
					'email_password' => $row->emailPassword !== null ? Crypt::encryptString($row->emailPassword) : null,

					// JSON fields (adjust casts in model)
					'codes_receiver_emails' => $row->codesReceiverEmails,
					'platform_meta' => $row->platformMeta,

					// Defaults (adjust to your schema)
					'max_uses' => 3,
					'available_uses' => 3,
					'next_release_at' => null,
					'is_active' => true,
				]);

				$result->added++;
			} catch (QueryException $e) {
				// Duplicate by unique constraint (platform, game, game_login)
				$result->skipped++;
			} catch (\Throwable $e) {
				$result->errors[] = ['row' => $row->rowNumber, 'reason' => 'Unexpected error'];
			}
		}

		return $result;
	}
}
```

> IMPORTANT: подстрой названия полей под ваш реальный `accounts` schema.  
> Если у вас уже есть `casts = ['email_login' => 'encrypted', ...]`, тогда `Crypt::encryptString()` убрать.

---

## 6) Middleware / доступы (обязательное)

Endpoint импорта файла должен быть доступен **только админу** — используйте ваш текущий admin middleware (тот же, что на `/import/text`).

---

## 7) Тест-план (минимальный)

1) TXT: 10 строк, 2 дубля, 1 битая строка → `added=7`, `skipped=2`, `errors=1`  
2) CSV: с header и без header → корректный маппинг  
3) XLSX: с header → корректно  
4) Проверка, что почта/пароль почты **не возвращаются** в ответе API (только агрегаты/ошибки)  
5) Размер файла >10MB → 422 (validation error)

---

## 8) Пункты ТЗ, которые нужно держать в TODO (без реализации сейчас)

1) **Финальный список платформ** — заменить тестовый список в `config/accesshub.php` после получения от заказчика.  
2) **Формат qty** — утвердить формат ввода оператором (если qty еще не реализован в боте/выдаче).  
3) **Код верификации** — реализовывать только после утверждения сценария (если заказчик подтвердит, что это нужно).

---
