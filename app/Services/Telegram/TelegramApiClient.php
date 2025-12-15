<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TelegramApiClient
{
	public function sendMessage(int|string $chatId, string $text): void
	{
		$token = (string) config('services.telegram.token');

		if ($token === '') {
			Log::warning('telegram.token_missing');
			return;
		}

		$url = "https://api.telegram.org/bot{$token}/sendMessage";

		try {
			Http::timeout(10)->post($url, [
				'chat_id' => $chatId,
				'text' => $text,
				'disable_web_page_preview' => true,
			]);
		} catch (Throwable $e) {
			Log::error('telegram.sendMessage_failed', [
				'error' => $e->getMessage(),
			]);
		}
	}
}
