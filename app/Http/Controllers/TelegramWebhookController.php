<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

final class TelegramWebhookController extends Controller
{
	public function __invoke(Request $request): Response
	{
		$secret = (string) config('services.telegram.webhook_secret');
		$header = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

		if ($secret === '' || !hash_equals($secret, $header)) {
			// Security: reject чужие запросы
			return response('Forbidden', 403);
		}

		$update = $request->all();

		// Debug: пока просто логируем входящие апдейты
		Log::info('telegram.update', $update);

		// Telegram ждёт 200 OK быстро
		return response('OK', 200);
	}
}
