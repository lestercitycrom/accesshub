# Быстрая шпаргалка по деплою

## Минимальные шаги для деплоя

### 1. На сервере выполните:

```bash
# Клонирование/загрузка проекта
git clone <repository-url> /path/to/accesshub
cd /path/to/accesshub

# Установка зависимостей
composer install --optimize-autoloader --no-dev
npm install
npm run build

# Создание .env файла
cp .env.example .env
# Или создайте .env вручную с настройками ниже

# Генерация ключа
php artisan key:generate

# Настройка прав
chmod -R 775 storage bootstrap/cache

# Миграции
php artisan migrate --force

# Оптимизация
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 2. Настройте .env файл:

```env
APP_NAME="AccessHub"
APP_ENV=production
APP_KEY=base64:... # сгенерируется автоматически
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=accesshub
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_WEBHOOK_SECRET=your_webhook_secret

ACCESSHUB_DENY_BY_DEFAULT=true
ACCESSHUB_WEBAPP_TTL=86400
ACCESSHUB_WEBAPP_DEBUG=false

CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database
```

### 3. Настройте веб-сервер

**Apache:** DocumentRoot должен указывать на `/path/to/accesshub/public`

**Nginx:** root должен быть `/path/to/accesshub/public`

### 4. Настройте Queue Worker (Supervisor)

Создайте `/etc/supervisor/conf.d/accesshub-worker.conf`:

```ini
[program:accesshub-worker]
command=php /path/to/accesshub/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
```

Затем:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start accesshub-worker:*
```

### 5. Настройте Cron

```bash
* * * * * cd /path/to/accesshub && php artisan schedule:run >> /dev/null 2>&1
```

### 6. Настройте Telegram Webhook

```bash
curl -X POST "https://api.telegram.org/botYOUR_BOT_TOKEN/setWebhook" \
  -d "url=https://YOUR_DOMAIN.com/telegram/webhook" \
  -d "secret_token=YOUR_WEBHOOK_SECRET"
```

### 7. Проверьте

- Откройте сайт в браузере
- Проверьте логи: `tail -f storage/logs/laravel.log`

---

**Подробная инструкция:** см. `DEPLOY.md`



