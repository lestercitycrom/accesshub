Такое **может быть**, и почти всегда причина одна из этих (по порядку вероятности):

1. **Laravel пишет не в `laravel.log`, а в другой файл** (канал `daily`)
2. **Логи уходят не в файл, а в консоль/STDERR** (канал `stderr`)
3. **Уровень логирования слишком высокий** (`LOG_LEVEL=error`, поэтому `info/debug` не пишутся)
4. Ты смотришь **не тот проект/не тот `storage/logs`** (две копии, другой `workdir`)
5. **Нет прав на запись**, и Laravel “тихо” не пишет (реже, но бывает)

Ниже — как за 2 минуты точно понять, куда пишет.

---

## Шаг 1. Проверь, какой канал логов реально активен

Открой `php artisan tinker` и выполни **внутри tinker**:

```php
config('logging.default');
config('logging.channels.' . config('logging.default'));
config('logging.channels.daily.path');
config('logging.channels.single.path');
config('logging.channels.stack.channels');
config('app.env');
config('logging.channels.' . config('logging.default') . '.level');
```

### Что это даст

* увидишь **какой канал** (single/daily/stack/stderr)
* если `daily` — путь будет на `storage/logs/laravel-YYYY-MM-DD.log`
* если `stderr` — в файл **вообще ничего не будет** (будет в консоли процесса)

---

## Шаг 2. Проверь, какие файлы логов реально создаются

В PowerShell в корне проекта:

```powershell
Get-ChildItem .\storage\logs | Select-Object Name,Length,LastWriteTime | Sort-Object LastWriteTime -Descending
```

✅ Если увидишь `laravel-2025-12-15.log` — значит у тебя канал **daily** и ты чистил не тот файл.

---

## Шаг 3. Проверь уровень логирования

Открой `.env` и посмотри:

```env
LOG_CHANNEL=...
LOG_LEVEL=...
```

Если `LOG_LEVEL=error` или `critical` — то **`Log::info()` ты никогда не увидишь**.

Быстрая проверка в tinker:

```php
\Illuminate\Support\Facades\Log::info('log.test.info');
\Illuminate\Support\Facades\Log::error('log.test.error');
```

* Если видишь только `error`, а `info` нет → проблема в `LOG_LEVEL`.

---

## Шаг 4. Убедись, что запись в файл вообще возможна

В PowerShell:

```powershell
Test-Path .\storage\logs
```

И попробуй создать файл:

```powershell
"test" | Out-File .\storage\logs\write-test.txt
```

Если тут ошибка — значит права/доступы.

---

## Шаг 5. Самая частая причина в твоём кейсе

Ты пишешь: “бот работает, меню показывает, статистика есть, но логов нет”.

Это очень похоже на:

* `LOG_CHANNEL=daily` → ты смотришь `laravel.log`, а пишет в `laravel-2025-12-15.log`
  или
* `LOG_CHANNEL=stderr` → пишет в консоль процесса, а не в файл

---

## Мини-фикс “чтобы всегда было в файл” (на локалке)

Поставь в `.env`:

```env
LOG_CHANNEL=single
LOG_LEVEL=debug
```

И выполни:

```powershell
php artisan optimize:clear
```

Перезапусти `php artisan serve`.

---

## Как протестить после фикса (железно)

1. В tinker:

```php
\Illuminate\Support\Facades\Log::debug('debug.ok');
\Illuminate\Support\Facades\Log::error('error.ok');
```

2. Проверь `storage/logs` — должен измениться файл и появиться записи.

---

### Чтобы я сразу сказал точную причину без гаданий

Скинь сюда 2 вещи (текстом):

1. результат `config('logging.default')` из tinker
2. список файлов из `storage/logs` (командой из шага 2)

И я скажу конкретно: **куда именно у тебя пишутся логи и почему ты их не видишь**.
