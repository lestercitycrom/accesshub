<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$token = config('services.telegram.token');
$secret = config('services.telegram.webhook_secret');

if (empty($token)) {
    echo "ERROR: TELEGRAM_BOT_TOKEN is not set in .env\n";
    exit(1);
}

if (empty($secret)) {
    echo "ERROR: TELEGRAM_WEBHOOK_SECRET is not set in .env\n";
    exit(1);
}

// Получаем URL из аргументов командной строки или используем APP_URL
$url = $argv[1] ?? null;
if (empty($url)) {
    $appUrl = config('app.url');
    if (empty($appUrl) || $appUrl === 'http://localhost') {
        echo "ERROR: Please provide webhook URL as first argument or set APP_URL in .env\n";
        echo "Usage: php set_webhook.php https://your-domain.com/telegram/webhook\n";
        exit(1);
    }
    $url = rtrim($appUrl, '/') . '/telegram/webhook';
}

echo "Setting webhook...\n";
echo "URL: {$url}\n";
echo "Secret token: " . substr($secret, 0, 8) . "...\n\n";

$response = \Illuminate\Support\Facades\Http::timeout(10)->asForm()->post(
    "https://api.telegram.org/bot{$token}/setWebhook",
    [
        'url' => $url,
        'secret_token' => $secret,
    ]
);

if (!$response->successful()) {
    $error = $response->json();
    echo "ERROR: Failed to set webhook: " . ($error['description'] ?? $response->body()) . "\n";
    exit(1);
}

$result = $response->json();
if (isset($result['ok']) && $result['ok'] === true) {
    echo "✓ Webhook set successfully!\n";
    if (isset($result['result']['url'])) {
        echo "Current webhook URL: {$result['result']['url']}\n";
    }
    exit(0);
}

echo "ERROR: Unexpected response: " . json_encode($result) . "\n";
exit(1);

