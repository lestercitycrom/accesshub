# Шаг 2: Тестирование AccessHub в Telegram

После настройки ядра системы (миграции, модели, сервисы) и добавления тестовых данных, можно приступать к тестированию бота в Telegram.

---

## Подготовка перед тестированием

### 1. Запуск миграций
```bash
php artisan migrate
```

### 2. Добавление пользователя
```bash
php artisan accesshub:user:add <your_telegram_id> admin
```

Чтобы узнать свой Telegram ID, можно использовать бота [@userinfobot](https://t.me/userinfobot) или посмотреть в логи при первом обращении к боту.

### 3. Создание тестовых аккаунтов
```bash
php artisan accesshub:account:seed-test
```

Список созданных тестовых аккаунтов см. в `docs/test_accounts.md`.

### 4. Проверка webhook
Убедитесь, что webhook настроен и работает:
```bash
curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"
```

---

## Формат запроса

### Базовый формат (2 строки)

```
<order_id>
<Game> (<Platform>)
```

**Пример:**
```
2446303
Minecraft (Xbox X)
```

### Множественная выдача (qty)

Для выдачи нескольких аккаунтов добавьте `xN` в конец второй строки:

```
2446303
Minecraft (Xbox X) x2
```

---

## Примеры для тестирования

### 1. Команды помощи

Отправьте боту:
```
/start
```
или
```
/help
```

**Ожидаемый результат:** Бот покажет инструкцию по формату запроса.

---

### 2. Простая выдача (1 аккаунт)

```
1001
Minecraft (Xbox X)
```

**Ожидаемый результат:**
```
Заказ: 1001
Игра: Minecraft
Платформа: Xbox X

#1
Login: test_minecraft_xbox_1
Password: test_pass_123
```

---

### 3. Множественная выдача (2 аккаунта)

```
1002
Minecraft (Xbox X) x2
```

**Ожидаемый результат:**
```
Заказ: 1002
Игра: Minecraft
Платформа: Xbox X

#1
Login: test_minecraft_xbox_1
Password: test_pass_123

#2
Login: test_minecraft_xbox_2
Password: test_pass_456
```

**Важно:** Выдаются 2 **разных** аккаунта в рамках одного `order_id`.

---

### 4. Другие игры и платформы

#### Call of Duty (PS5)
```
1003
Call of Duty (PS5)
```

**Ожидаемый результат:** Выдаст аккаунт `test_cod_ps5_1`.

#### Counter-Strike 2 (Steam)
```
1004
Counter-Strike 2 (Steam)
```

**Ожидаемый результат:** Выдаст аккаунт `test_cs2_steam_1`.

#### Fortnite (Epic)
```
1005
Fortnite (Epic)
```

**Ожидаемый результат:** Выдаст аккаунт `test_fortnite_epic_1`.

---

### 5. Недостаточно аккаунтов

```
1006
Minecraft (Xbox X) x5
```

**Ожидаемый результат:**
```
Недостаточно свободных аккаунтов: доступно 2, нужно 5.
```

---

### 6. Аккаунт с нулевыми выдачами

```
1007
Zelda: Tears of the Kingdom (Nintendo)
```

**Ожидаемый результат:**
```
Недостаточно свободных аккаунтов: доступно 0, нужно 1.
```

**Примечание:** Аккаунт Zelda создан с `available_uses = 0` для тестирования восстановления доступности.

---

### 7. Неверный формат запроса

Отправьте боту любой текст, не соответствующий формату:
```
просто текст
```
или
```
123
```
или
```
Minecraft
```

**Ожидаемый результат:**
```
Неверный формат.
Нужно 2 строки:
1) Номер заказа
2) Игра (Платформа)

Пример:
2446303
Minecraft (Xbox X)
```

---

### 8. Доступ запрещён

Если пользователь не добавлен в систему (`telegram_users`) и включен `deny_by_default = true`:

**Ожидаемый результат:**
```
Нет доступа. Обратитесь к администратору.
```

---

## Что проверять при тестировании

### ✅ 1. Выдача работает
- Бот возвращает логин и пароль игрового аккаунта
- Формат ответа корректный
- Аккаунты соответствуют запрошенной игре и платформе

### ✅ 2. Журнал выдачи
После каждой выдачи проверьте таблицу `issuance_logs`:

```sql
SELECT * FROM issuance_logs ORDER BY id DESC LIMIT 5;
```

Должны быть записи с:
- Правильным `order_id`
- Правильным `operator_telegram_id`
- Правильными `account_id`, `game`, `platform`
- Временем выдачи `issued_at`

### ✅ 3. Лимиты выдач
Проверьте, что после выдачи уменьшается `available_uses`:

```sql
SELECT id, game, platform, available_uses, max_uses, next_release_at 
FROM accounts 
WHERE platform = 'Xbox X' AND game = 'Minecraft';
```

**Ожидаемое поведение:**
- После каждой выдачи `available_uses` уменьшается на 1
- Когда `available_uses` достигает 0, устанавливается `next_release_at` (через 14 дней)

### ✅ 4. Множественная выдача
При запросе `x2` должны выдаваться 2 **разных** аккаунта:
- Разные `game_login`
- Разные записи в `issuance_logs`
- Оба с одним и тем же `order_id`

### ✅ 5. Уникальность
Проверьте, что один и тот же аккаунт не выдаётся повторно на тот же `order_id`:

```sql
SELECT order_id, account_id, COUNT(*) as count
FROM issuance_logs
WHERE order_id = '1002'
GROUP BY order_id, account_id
HAVING count > 1;
```

**Ожидаемый результат:** Пустой результат (каждая комбинация `order_id + account_id` уникальна).

### ✅ 6. Восстановление доступности
Команда `accesshub:restore-availability` запускается каждый час и восстанавливает доступность аккаунтов, у которых истёк срок `next_release_at`.

Для тестирования вручную:
```bash
php artisan accesshub:restore-availability
```

---

## Проверка через базу данных

### Посмотреть все выдачи для заказа
```sql
SELECT 
    il.order_id,
    il.operator_telegram_id,
    a.game,
    a.platform,
    a.game_login,
    il.issued_at
FROM issuance_logs il
JOIN accounts a ON il.account_id = a.id
WHERE il.order_id = '1001'
ORDER BY il.issued_at;
```

### Посмотреть состояние аккаунтов
```sql
SELECT 
    id,
    platform,
    game,
    game_login,
    available_uses,
    max_uses,
    next_release_at,
    is_active,
    created_at,
    updated_at
FROM accounts
ORDER BY platform, game, id;
```

### Статистика выдач по оператору
```sql
SELECT 
    operator_telegram_id,
    COUNT(*) as total_issues,
    COUNT(DISTINCT order_id) as unique_orders,
    COUNT(DISTINCT account_id) as unique_accounts
FROM issuance_logs
GROUP BY operator_telegram_id;
```

---

## Частые проблемы и решения

### Бот не отвечает

1. **Проверьте webhook:**
   ```bash
   curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"
   ```

2. **Проверьте логи Laravel:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. **Проверьте, что пользователь добавлен:**
   ```sql
   SELECT * FROM telegram_users WHERE telegram_id = '<your_telegram_id>';
   ```

### Ошибка "Нет доступа"

1. **Добавьте пользователя:**
   ```bash
   php artisan accesshub:user:add <telegram_id> admin
   ```

2. **Проверьте, что пользователь активен:**
   ```sql
   UPDATE telegram_users SET is_active = 1 WHERE telegram_id = '<telegram_id>';
   ```

### Ошибка "Недостаточно свободных аккаунтов"

1. **Проверьте наличие аккаунтов:**
   ```sql
   SELECT COUNT(*) FROM accounts 
   WHERE platform = '<Platform>' 
     AND game = '<Game>' 
     AND is_active = 1 
     AND available_uses > 0;
   ```

2. **Создайте больше тестовых аккаунтов:**
   ```bash
   php artisan accesshub:account:add --platform="Xbox X" --game="Minecraft" --game_login="test_3" --game_password="pass_3"
   ```

### Неверный формат при правильном запросе

1. **Проверьте формат:** Должно быть ровно 2 строки
2. **Проверьте скобки:** Формат должен быть `Game (Platform)`
3. **Проверьте пробелы:** Лишние пробелы могут нарушить парсинг

---

## Следующие шаги

После успешного тестирования основной функциональности:

1. ✅ Добавление админ-команд (см. `docs/buffer.md`)
2. ✅ Массовая загрузка аккаунтов
3. ✅ Шифрование чувствительных данных (email_login, email_password)
4. ✅ Выгрузка журналов
5. ✅ Управление статусами аккаунтов

---

## Ссылки

- Список тестовых аккаунтов: `docs/test_accounts.md`
- Техническое задание: `docs/accesshub_TZ_v3.md`
- Буфер разработки: `docs/buffer.md`
