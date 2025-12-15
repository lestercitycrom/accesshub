# ТЗ (v4): AccessHub — Telegram Bot + WebApp (Mini App) интерфейс

Дата: **2025-12-15**  
Проект: **accesshub** (Laravel 12 + PHP 8.3 + MySQL + Bootstrap)

## 1. Цель

Сделать основной интерфейс через **Telegram WebApp (Mini App)**, открывающийся как «модалка» внутри Telegram по кнопке **Open / Menu Button** (как BotFather).  
Текущий текстовый интерфейс бота **оставить как резервный** (работает параллельно).

---

## 2. Роли и доступы

### 2.1. Оператор
- Имеет доступ к WebApp вкладкам: **Выдача**, **История**, **Помощь**
- В чате получает **только игровые** данные: `game_login` / `game_password`
- Не видит почту/почтовые пароли/скрытые поля

### 2.2. Админ
- Имеет доступ к WebApp вкладкам: **Выдача**, **История**, **Админ: Аккаунты**, **Админ: Импорт**, **Админ: Поиск**, **Админ: Логи**, **Админ: Пользователи**, **Админ: Экспорт**, **Настройки (заглушка)**
- Может добавлять/импортировать/менять статусы/сбрасывать лимиты/выгружать CSV

### 2.3. Источник прав
Таблица `telegram_users`:
- `telegram_id` (unique)
- `role` = `admin|operator`
- `is_active` bool

По умолчанию доступ закрыт, если `deny_by_default=true`.

---

## 3. Telegram WebApp: как открывается

### 3.1. BotFather настройка
- Указать **WebApp domain** (HTTPS домен проекта)
- Настроить **Menu Button** на URL: `https://<domain>/webapp`

> Для разработки через ngrok: домен меняется. На прод нужен стабильный домен.

### 3.2. URL WebApp
- `GET /webapp` — единая страница WebApp (Bootstrap)

---

## 4. WebApp: универсальный (schema-driven) интерфейс

### 4.1. Требование
WebApp должен отрисовывать вкладки/формы **по JSON-схеме**, чтобы не «рисовать каждую форму вручную».

### 4.2. Schema endpoint
- `GET /webapp/api/schema`  
Возвращает JSON с вкладками, формами и правами (`roles`).

### 4.3. Рендеринг
На фронте:
- Bootstrap Tabs
- Универсальный FormRenderer, который строит поля по схеме:
  - `text`, `number`, `textarea`, `select`
  - валидация required/min/max/pattern

### 4.4. Submit типы
- `submit.type = "bot"` → `Telegram.WebApp.sendData(JSON.stringify(payload))` и закрыть WebApp
- `submit.type = "api"` → `fetch('/webapp/api/...')` и показать результат внутри WebApp

---

## 5. Обмен WebApp → Bot (обязательный контракт)

### 5.1. Payload (строго)
`tg.sendData()` отправляет JSON-строку:
```json
{
  "action": "issue",
  "request_id": "uuid",
  "payload": {
    "order_id": "2446303",
    "game": "Minecraft",
    "platform": "Xbox X",
    "qty": 2
  }
}
```

### 5.2. Как бот получает
В webhook update:
- `message.web_app_data.data` — строка JSON
- `message.from.id` — реальный Telegram ID отправителя

### 5.3. Что делает бот при `action=issue`
1) Проверить `telegram_users` (is_active, роль)
2) Выполнить выдачу через существующий `IssueAccountsService`
3) Ответить **в чат** (НЕ в WebApp) в формате «чёрного блока» (code-block):
```
Order: 2446303
Game: Minecraft
Platform: Xbox X

#1
Login: ...
Password: ...
```

---

## 6. WebApp API (backend) — обязательные эндпоинты

### 6.1. Авторизация запросов WebApp API
Каждый запрос WebApp API обязан передавать `initData` (Telegram WebApp):
- заголовок: `X-TG-INIT-DATA: <initData>`
или
- body: `initData: "..."`

Backend обязан **проверять подпись initData** (HMAC по Bot Token) и извлекать `telegram_id`.

После проверки:
- сопоставить с `telegram_users`
- применить роль (operator/admin)

### 6.2. Operator API
- `GET /webapp/api/me` → {telegram_id, role}
- `GET /webapp/api/history?limit=50&order_id=...` → список выдач текущего пользователя

### 6.3. Admin API (доступ только admin)
- `POST /webapp/api/admin/accounts` — добавить аккаунт
- `POST /webapp/api/admin/import/text` — импорт строками
- `POST /webapp/api/admin/import/file` — импорт файлом (txt/csv) *(по необходимости)*
- `GET /webapp/api/admin/find?game=...&platform=...&status=...` — поиск аккаунтов
- `POST /webapp/api/admin/accounts/{id}/enable` — включить
- `POST /webapp/api/admin/accounts/{id}/disable` — выключить
- `POST /webapp/api/admin/accounts/{id}/reset` — сброс: available_uses=max_uses, next_release_at=null
- `GET /webapp/api/admin/logs?account_id=...&order_id=...&operator_id=...` — логи
- `GET /webapp/api/admin/export/accounts.csv` — экспорт CSV
- `GET /webapp/api/admin/export/issuance_logs.csv` — экспорт CSV
- `GET /webapp/api/admin/users` — список пользователей
- `POST /webapp/api/admin/users` — добавить/обновить (telegram_id, role, is_active)

---

## 7. Набор вкладок WebApp (все роли покрыты)

### 7.1. Оператор (operator/admin)
1) **Выдача** (submit=bot, action=issue): order_id, game, platform, qty  
2) **История** (submit=api): таблица выдач, фильтр order_id (опц.)  
3) **Помощь** (статично)

### 7.2. Админ (admin)
4) **Аккаунты: Добавить** (api)  
5) **Импорт** (api)  
6) **Поиск (/find)** (api) + actions enable/disable/reset + логи  
7) **Логи** (api)  
8) **Пользователи** (api)  
9) **Экспорт** (api)  
10) **Настройки** (заглушка)

---

## 8. Что оставить от текущего бота

- Webhook уже работает — не ломать
- Текстовый ввод выдачи (2 строки + x2) оставить
- Команды админа (/add,/import,/log,/stats) оставить
- Inline-меню не развивать (опционально)

---

## 9. План работ (что делать программисту)

### 9.1. Laravel: WebApp UI
- `GET /webapp` — Blade + Bootstrap + `telegram-web-app.js`
- JS:
  - `tg.ready(); tg.expand();`
  - `GET /webapp/api/schema` → отрисовать вкладки/формы
  - submit bot: `tg.sendData(JSON.stringify(payload)); tg.close();`
  - submit api: `fetch()` + вывод результата

### 9.2. Laravel: WebApp API
- Middleware проверки initData → telegram_id → роль
- Endpoints из раздела 6

### 9.3. Bot webhook: обработка web_app_data
- Если `message.web_app_data.data`:
  - JSON decode → `action`
  - `issue` → вызвать `IssueAccountsService`, ответить в чат code-block

---

## 10. Примеры

### 10.1. Пример schema (минимум)
```json
{
  "tabs": [
    {
      "id": "issue",
      "title": "Выдача",
      "roles": ["operator","admin"],
      "submit": { "type": "bot", "action": "issue" },
      "fields": [
        { "name": "order_id", "label": "Order ID", "type": "text", "required": true, "pattern": "^\\d+$" },
        { "name": "game", "label": "Игра", "type": "text", "required": true },
        { "name": "platform", "label": "Платформа", "type": "text", "required": true },
        { "name": "qty", "label": "Количество", "type": "number", "required": false, "min": 1, "max": 20, "default": 1 }
      ]
    }
  ]
}
```

### 10.2. Пример импорта
```
Xbox X | Minecraft | login1 | pass1 | mail@mail.com | mailpass | backup@mail.com
Xbox X | Minecraft | login2 | pass2 | - | - | -
```

---

## 11. Приёмка (тест-кейсы)

1) WebApp открывается по Menu Button в Telegram  
2) Operator: «Выдача» → бот выдаёт креды в чат (code-block), «История» работает  
3) Admin: add/import/find/actions/export/users работают  
4) Безопасность: нет доступа без telegram_users, operator не видит admin вкладки
