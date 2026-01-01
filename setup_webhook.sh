#!/bin/bash

# Скрипт для установки Telegram webhook на хостинге
# Использование: ./setup_webhook.sh https://your-domain.com

if [ -z "$1" ]; then
    echo "Usage: ./setup_webhook.sh https://your-domain.com"
    exit 1
fi

WEBHOOK_URL="$1/telegram/webhook"

echo "Setting Telegram webhook..."
echo "URL: $WEBHOOK_URL"
echo ""

php artisan telegram:set-webhook --url="$WEBHOOK_URL"

