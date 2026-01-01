<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TelegramSetWebhookCommand extends Command
{
	protected $signature = 'telegram:set-webhook {--url= : Webhook URL (defaults to APP_URL/api/telegram/webhook)}';
	protected $description = 'Set Telegram bot webhook URL with secret token.';

	public function handle(): int
	{
		$token = (string) config('services.telegram.token');
		if ($token === '') {
			$this->error('TELEGRAM_BOT_TOKEN is not set in .env');
			return self::FAILURE;
		}

		$secret = (string) config('services.telegram.webhook_secret');
		if ($secret === '') {
			$this->error('TELEGRAM_WEBHOOK_SECRET is not set in .env');
			return self::FAILURE;
		}

		$url = $this->option('url');
		if ($url === null) {
			$appUrl = (string) config('app.url');
			if ($appUrl === '' || $appUrl === 'http://localhost') {
				$this->error('APP_URL is not set or is localhost. Please provide --url option or set APP_URL in .env');
				return self::FAILURE;
			}
			$url = rtrim($appUrl, '/') . '/api/telegram/webhook';
		}

		$this->info("Setting webhook...");
		$this->line("URL: {$url}");
		$this->line("Secret token: " . substr($secret, 0, 8) . '...');

		try {
			$response = Http::timeout(10)->asForm()->post(
				"https://api.telegram.org/bot{$token}/setWebhook",
				[
					'url' => $url,
					'secret_token' => $secret,
				]
			);

			if (!$response->successful()) {
				$error = $response->json();
				$this->error('Failed to set webhook: ' . ($error['description'] ?? $response->body()));
				Log::error('telegram.set_webhook_failed', [
					'error' => $error ?? $response->body(),
					'status' => $response->status(),
				]);
				return self::FAILURE;
			}

			$result = $response->json();
			if (isset($result['ok']) && $result['ok'] === true) {
				$this->info('✓ Webhook set successfully!');
				if (isset($result['result']['url'])) {
					$this->line("Current webhook URL: {$result['result']['url']}");
				}
				Log::info('telegram.webhook_set', ['url' => $url]);
				return self::SUCCESS;
			}

			$this->error('Unexpected response: ' . json_encode($result));
			return self::FAILURE;
		} catch (\Throwable $e) {
			$this->error('Exception: ' . $e->getMessage());
			Log::error('telegram.set_webhook_exception', ['error' => $e->getMessage()]);
			return self::FAILURE;
		}
	}
}

