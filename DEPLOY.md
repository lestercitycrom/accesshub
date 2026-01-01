# Инструкция по деплою AccessHub на хостинг

## Требования к серверу

- **PHP**: 8.2 или выше
- **Composer**: последняя версия
- **Node.js**: 18+ и npm (для сборки фронтенда)
- **База данных**: MySQL/PostgreSQL/SQLite (рекомендуется MySQL для продакшена)
- **Расширения PHP**: 
  - `openssl`
  - `pdo`
  - `mbstring`
  - `tokenizer`
  - `xml`
  - `ctype`
  - `json`
  - `bcmath`
  - `curl`
  - `fileinfo`
  - `gd` (если нужна работа с изображениями)

## Шаг 1: Подготовка проекта

### 1.1. Убедитесь, что все изменения закоммичены

```bash
git add .
git commit -m "Prepare for deployment"
```

### 1.2. Проверьте `.gitignore`

Убедитесь, что в `.gitignore` есть:
- `.env`
- `vendor/`
- `node_modules/`
- `/public/build`
- `/public/hot`
- `/storage/*.key`

## Шаг 2: Загрузка файлов на сервер

### Вариант A: Через Git (рекомендуется)

```bash
# На сервере
cd /path/to/your/project
git clone <your-repository-url> .
```

### Вариант B: Через FTP/SFTP

Загрузите все файлы проекта на сервер, исключая:
- `.env`
- `vendor/`
- `node_modules/`
- `/public/build`

## Шаг 3: Установка зависимостей

### 3.1. Установка PHP зависимостей

```bash
composer install --optimize-autoloader --no-dev
```

### 3.2. Установка Node.js зависимостей и сборка фронтенда

```bash
npm install
npm run build
```

## Шаг 4: Настройка окружения

### 4.1. Создайте файл `.env`

```bash
cp .env.example .env
# Или создайте новый файл .env
```

### 4.2. Настройте переменные окружения в `.env`

**Обязательные переменные:**

```env
APP_NAME="AccessHub"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://your-domain.com

# База данных
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=accesshub
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# Telegram Bot
TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_WEBHOOK_SECRET=your_webhook_secret_here

# AccessHub настройки
ACCESSHUB_DENY_BY_DEFAULT=true
ACCESSHUB_WEBAPP_TTL=86400
ACCESSHUB_WEBAPP_DEBUG=false

# Кэш и сессии (для продакшена рекомендуется redis)
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database

# Почта (если используется)
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### 4.3. Сгенерируйте ключ приложения

```bash
php artisan key:generate
```

## Шаг 5: Настройка базы данных

### 5.1. Создайте базу данных

Создайте базу данных MySQL/PostgreSQL через панель управления хостингом или командой:

```sql
CREATE DATABASE accesshub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5.2. Выполните миграции

```bash
php artisan migrate --force
```

**Внимание:** Флаг `--force` нужен для продакшена, чтобы не запрашивать подтверждение.

## Шаг 6: Настройка прав доступа

```bash
# Права на запись для storage и bootstrap/cache
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Если используется другой пользователь веб-сервера, замените www-data
```

## Шаг 7: Настройка веб-сервера

### 7.1. Apache (.htaccess уже должен быть в public/)

Убедитесь, что в `public/.htaccess` есть:

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

**Настройте VirtualHost:**

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /path/to/accesshub/public

    <Directory /path/to/accesshub/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/accesshub_error.log
    CustomLog ${APACHE_LOG_DIR}/accesshub_access.log combined
</VirtualHost>
```

### 7.2. Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/accesshub/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## Шаг 8: Оптимизация для продакшена

```bash
# Очистка кэша конфигурации
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Кэширование конфигурации (ускоряет работу)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Шаг 9: Настройка очередей (Queue Worker)

Приложение использует очереди, поэтому нужно запустить worker:

### 9.1. Запуск через Supervisor (рекомендуется)

Создайте файл `/etc/supervisor/conf.d/accesshub-worker.conf`:

```ini
[program:accesshub-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/accesshub/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/accesshub/storage/logs/worker.log
stopwaitsecs=3600
```

Затем:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start accesshub-worker:*
```

### 9.2. Альтернатива: через cron (менее надежно)

Добавьте в crontab:

```bash
* * * * * cd /path/to/accesshub && php artisan schedule:run >> /dev/null 2>&1
```

## Шаг 10: Настройка планировщика задач (Scheduler)

Добавьте в crontab (если еще не добавлено):

```bash
* * * * * cd /path/to/accesshub && php artisan schedule:run >> /dev/null 2>&1
```

Это запустит команду `accesshub:restore-availability` каждый час.

## Шаг 11: Настройка Telegram Webhook

После деплоя настройте webhook для Telegram бота.

**Важно:** Убедитесь, что в `.env` указаны:
- `TELEGRAM_BOT_TOKEN` - токен вашего бота (получается от @BotFather)
- `TELEGRAM_WEBHOOK_SECRET` - секретный ключ для защиты webhook
- `APP_URL` - URL вашего домена (например, `https://your-domain.com`)

### Способ 1: Использование команды artisan (рекомендуется)

```bash
# Если APP_URL правильно настроен в .env:
php artisan telegram:set-webhook

# Или укажите URL вручную:
php artisan telegram:set-webhook --url=https://your-domain.com/telegram/webhook
```

### Способ 2: Использование curl напрямую

```bash
# Замените YOUR_BOT_TOKEN, YOUR_DOMAIN и YOUR_WEBHOOK_SECRET
curl -X POST "https://api.telegram.org/botYOUR_BOT_TOKEN/setWebhook" \
  -d "url=https://YOUR_DOMAIN.com/telegram/webhook" \
  -d "secret_token=YOUR_WEBHOOK_SECRET"
```

### Проверка установки webhook

После установки проверьте текущий webhook:

```bash
curl "https://api.telegram.org/botYOUR_BOT_TOKEN/getWebhookInfo"
```

## Шаг 12: Проверка работоспособности

1. Откройте сайт в браузере: `https://your-domain.com`
2. Проверьте API endpoints:
   - `https://your-domain.com/webapp/api/schema`
   - `https://your-domain.com/webapp/api/me`
3. Проверьте логи на ошибки:
   ```bash
   tail -f storage/logs/laravel.log
   ```

## Шаг 13: SSL сертификат (HTTPS)

Для продакшена обязательно используйте HTTPS:

- **Let's Encrypt** (бесплатно): `certbot --nginx` или `certbot --apache`
- Или настройте через панель управления хостингом

После установки SSL обновите `APP_URL` в `.env`:

```env
APP_URL=https://your-domain.com
```

## Дополнительные рекомендации

### Безопасность

1. Убедитесь, что `.env` не доступен извне
2. Проверьте права доступа к файлам
3. Используйте сильные пароли для базы данных
4. Регулярно обновляйте зависимости: `composer update` и `npm update`

### Мониторинг

1. Настройте мониторинг логов
2. Настройте алерты на ошибки
3. Регулярно проверяйте `storage/logs/laravel.log`

### Резервное копирование

1. Настройте автоматическое резервное копирование базы данных
2. Регулярно делайте бэкапы файлов проекта

### Производительность

1. Используйте Redis для кэша и сессий в продакшене:
   ```env
   CACHE_STORE=redis
   SESSION_DRIVER=redis
   ```
2. Включите OPcache в PHP
3. Используйте CDN для статических файлов

## Обновление приложения

При обновлении приложения выполните:

```bash
# 1. Получите последние изменения
git pull origin main

# 2. Установите новые зависимости
composer install --optimize-autoloader --no-dev
npm install
npm run build

# 3. Выполните миграции (если есть)
php artisan migrate --force

# 4. Очистите и пересоздайте кэш
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Перезапустите queue worker
sudo supervisorctl restart accesshub-worker:*
```

## Проблемы и решения

### Ошибка 500

1. Проверьте логи: `storage/logs/laravel.log`
2. Проверьте права доступа к `storage/` и `bootstrap/cache/`
3. Убедитесь, что `APP_KEY` установлен
4. Проверьте настройки базы данных

### Очереди не работают

1. Проверьте, запущен ли queue worker
2. Проверьте логи worker: `storage/logs/worker.log`
3. Убедитесь, что таблица `jobs` существует в БД

### Telegram webhook не работает

1. Проверьте, что webhook установлен правильно
2. Проверьте `TELEGRAM_BOT_TOKEN` и `TELEGRAM_WEBHOOK_SECRET` в `.env`
3. Проверьте логи на ошибки

---

**Готово!** Ваше приложение должно быть доступно на хостинге.



