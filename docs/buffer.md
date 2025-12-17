# AccessHub — что осталось закрыть по ТЗ и что делаем дальше

Дата: **2025-12-17**

> Контекст: Laravel 12 + PHP 8.3 + MySQL + Telegram Bot + Telegram WebApp (Mini App).  
> WebApp auth (initData + lang override + dev-bypass) — уже внедрены.  
> Базовая выдача/журналирование/импорт/мастер /add — уже есть (по твоим сообщениям).

---

## 1) Что по ТЗ уже закрыто (по текущему статусу)

- Webhook бота работает.
- База (accounts, issuance_logs, telegram_users) есть.
- Выдача по `order_id + Game (Platform) + qty` работает.
- Лимиты и “пауза 14 дней” (cooldown=hold) реализованы через `available_uses + next_release_at`.
- Шифрование чувствительных полей через encrypted casts.
- Админ: `/add` (wizard), `/import` (текст/файл), `/log <id>` (логи).
- Планировщик восстановления доступности (команда restore availability) — есть.
- WebApp middleware: initData (с алиасами заголовков), i18n override `X-Tg-Lang`, debug bypass (dev only) — сделано.

---

## 2) Что ещё осталось по ТЗ (и UI) — backlog

### A) WebApp API: довести заглушки до рабочего состояния
UI уже “натянут” и частично заглушки — значит нужно закрыть:
- `schema` (если не готов полностью) → чтобы UI рендерился без хардкода текста/форм.
- `issue` → выдача из UI.
- `history` → история для infinite scroll на page/per_page/total.
- `import` → импорт из UI.
- `logs` → просмотр логов.
- `stats` → статистика (в UI есть раздел).

### B) Админ-функции управления аккаунтами
По ТЗ это обычно нужно:
- Список аккаунтов с фильтрами (game/platform/status/is_active).
- Ручные действия:
  - disable/enable (is_active)
  - reset: вернуть `available_uses=max_uses`, `next_release_at=null`
  - принудительно поставить паузу (установить next_release_at и available_uses=0)
- Поиск “find” (по game_login / game / platform).

### C) Экспорт (для админа)
Нужно закрыть выгрузки:
- Экспорт журнала выдач в CSV (фильтры: период, оператор, игра, платформа, order_id).
- Экспорт аккаунтов в CSV (без чувствительных полей, либо только для админа).

### D) Локализация (Bot + WebApp)
WebApp уже умеет `X-Tg-Lang` override, но остаётся:
- Bot replies / меню (если вы это используете) — выбрать источник языка (telegram_users.locale → telegram language_code → fallback).
- Вынести строки в `lang/*`.

### E) Открытый пункт, который лучше отдельно согласовать
**“Бот сам получает письмо/код при необходимости”** и “выдача кода верификации” — отдельная задача.  
Если это про доступ к чужим email/2FA — такое автоматизировать нельзя.  
Безопасная альтернатива: код вводится вручную админом/оператором, либо интеграция с официальным провайдером через API.

---

## 3) Рекомендуемый следующий этап (по шагам) + как тестировать

### Шаг 1 — Закрыть WebApp schema
**Что делаем:**
- Убедиться, что `GET /api/webapp/api/schema` отдаёт актуальную schema под текущий UI renderer (без смены формата).
- Строки локализованы (`__()`), язык выбирается из initData (primary) и `X-Tg-Lang` (override).

**Тест:**
1) Открыть WebApp → вкладки и формы должны появиться без ошибок.
2) Сменить язык Telegram → schema должна вернуться с другим языком (либо через `X-Tg-Lang: en` в запросе).

---

### Шаг 2 — Выдача из UI (issue endpoint)
**Что делаем:**
- `POST /api/webapp/api/issue` принимает `order_id, game, platform, qty`.
- Роль operator/admin проверяется.
- Транзакция: при нехватке qty ничего не списывать.

**Тест:**
1) В БД создать 2 доступных аккаунта для одной игры/платформы.
2) В UI: order_id=123, qty=2 → получить 2 результата.
3) Проверить `issuance_logs`: 2 записи, `order_id=123`.
4) Повторить выдачу qty=3 при доступных 0–2 → должна быть ошибка и без частичных списаний.

---

### Шаг 3 — История (history endpoint) под infinite scroll
**Что делаем:**
- Остаёмся на `page/per_page/total` (как уже у вас).
- Добавить стабильную сортировку (issued_at desc, id desc).

**Тест:**
1) Накопить 30–50 выдач.
2) В UI вкладка “Історія” → прокрутка:
   - подгружает страницы
   - не дублирует элементы
   - прекращает загрузку, когда достигнут total.

---

### Шаг 4 — Импорт из UI
**Что делаем:**
- `POST /api/webapp/api/admin/import/text` (payload строками)
- (опционально) upload TXT/CSV в `import/file`
- Дубли пропускать (unique constraint + корректный report)

**Тест:**
1) Импорт 5 строк, среди них 2 дубля → ответ: added=3, skipped=2.
2) В БД убедиться, что дублей нет.

---

### Шаг 5 — Логи из UI
**Что делаем:**
- `GET /api/webapp/api/admin/logs/account/<built-in function id>` или текущий ваш аналог.
- Показ последних N записей.

**Тест:**
1) Выдать аккаунт пару раз.
2) В UI открыть “Логи” по account_id → увидеть записи с order_id/оператором/датой.

---

### Шаг 6 — Статистика (в UI есть вкладка)
**Что делаем:**
- Endpoint `GET /api/webapp/api/admin/stats`:
  - total accounts
  - active accounts
  - available now (available_uses>0)
  - on cooldown (available_uses=0 and next_release_at not null)
  - issued today / last 7 days

**Тест:**
1) Сравнить цифры с прямыми запросами в MySQL (или через tinker).
2) Проверить, что после выдачи статистика обновляется ожидаемо.

---

### Шаг 7 — Экспорт CSV (админ)
**Что делаем:**
- `GET /api/webapp/api/admin/export/issuance.csv`
- `GET /api/webapp/api/admin/export/accounts.csv`
- Stream response, корректные заголовки, UTF-8.

**Тест:**
1) Скачать CSV из UI (или curl).
2) Открыть в Excel/Google Sheets — колонки должны отображаться корректно.
3) Проверить, что оператор не может вызвать экспорт (403).

---

### Шаг 8 — Админ-управление аккаунтом (enable/disable/reset)
**Что делаем:**
- Endpoint(ы) действий по account_id:
  - disable/enable
  - reset availability
  - force cooldown

**Тест:**
1) Disable аккаунт → он не выдаётся.
2) Reset → снова выдаётся.
3) Force cooldown → не выдаётся до даты.

---

