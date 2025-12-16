# AccessHub — ТЗ v13: Локализация бота (reply menu + текстовые ответы)

Дата: **2025-12-16**  
Проект: **accesshub**  
Стек: **Laravel 12 + PHP 8.3 + Telegram Bot**  
Область: **текстовый бот** (reply keyboard и текстовые ответы). WebApp не трогаем.

---

## 1) Цель

Сделать многоязычный текстовый интерфейс бота:

- Reply-меню (ReplyKeyboardMarkup) — локализовано.
- Все текстовые ответы бота — локализованы.
- Язык выбирается автоматически по Telegram, но пользователь может переопределить его через `/lang`.
- Реализация через **стандартную локализацию Laravel** (`App::setLocale`, `lang/*`, `__()`, `trans_choice()`).

---

## 2) Источник языка (приоритет)

1) `telegram_users.locale` (если пользователь выбирал язык через `/lang`).  
2) `update.message.from.language_code` (Telegram).  
3) Fallback: `config('app.locale')` (обычно `en`).

Нормализация:
- `ru-RU` → `ru`
- `uk-UA` → `uk`
- неизвестное → `en`

Whitelist: `['en','ru','uk']`.

---

## 3) Хранение locale

### 3.1 Миграция
Добавить колонку:
- `telegram_users.locale` (nullable string длиной 5)

---

## 4) Установка locale на каждый Update

В начале обработки каждого апдейта:

- определить `telegram_id`
- найти/создать `telegram_users`
- вычислить `locale` по приоритетам из п.2
- выполнить `App::setLocale($locale)`

---

## 5) Файлы переводов (Laravel)

Создать файлы:
- `lang/en/bot.php`
- `lang/ru/bot.php`
- `lang/uk/bot.php`

Пример ключей:

- `bot.menu.issue`
- `bot.menu.history`
- `bot.menu.help`
- `bot.menu.admin`
- `bot.menu.lang`
- `bot.common.back`
- `bot.common.cancel`
- `bot.replies.welcome`
- `bot.replies.access_denied`
- `bot.replies.error_generic`
- `bot.issue.success`
- `bot.issue.not_found`
- `bot.history.empty`
- `bot.history.items` (для `trans_choice`)

Все ответы в коде: **только** через `__()` / `trans_choice()`.

---

## 6) Reply-меню: генерация и обработка

### 6.1 Генерация меню через фабрику
Создать класс `BotKeyboardFactory`:

- `operatorMenu(): array`
- `adminMenu(): array`
- `languageMenu(): array`

Кнопки брать из `__()`.

### 6.2 Обработка нажатий reply-кнопок
ReplyKeyboard не поддерживает `callback_data`, приходит только текст.

Решение: mapping “action → localized label” и обратный поиск:

- `ISSUE => __('bot.menu.issue')`
- `HISTORY => __('bot.menu.history')`
- `HELP => __('bot.menu.help')`

При входящем тексте — ищем action через `array_search()`.

---

## 7) Текстовые ответы: шаблоны + параметры

Все сообщения с данными отдавать так:

- шаблон в `lang/*/bot.php`
- параметры: `__('...', ['order' => $orderId, 'game' => $game])`

Для количества:
- `trans_choice('bot.history.items', $count, ['count' => $count])`

---

## 8) Команда выбора языка `/lang` (обязательно)

- `/lang` показывает меню RU/EN/UK.
- Выбор → сохранить в `telegram_users.locale`.
- Подтверждение на выбранном языке.
- Дальше используем сохранённый locale независимо от Telegram language.

---

## 9) Рефакторинг (минимально)

Добавить 2 сервиса:

1) `BotLocaleResolver`
- `resolve(Update $update, ?TelegramUser $user): string`

2) `BotKeyboardFactory`
- методы меню
- `actionMap(): array` (action → label)

---

## 10) Тестирование

- Без locale: язык берётся из Telegram language_code.
- С /lang: сохранённый locale приоритетнее Telegram.
- Reply-кнопки работают на RU/EN/UK.
- Неизвестный язык → EN.

---

## 11) Acceptance criteria

- Reply меню и ответы локализованы на RU/EN/UK.
- Язык корректно определяется и сохраняется через `/lang`.
- Нет хардкода текстов в хендлерах.
- Reply-кнопки корректно распознаются на любом языке (mapping).
