<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AccessHub\AccessHubBotService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

final class TelegramWebhookController extends Controller
{
	public function __invoke(Request $request, AccessHubBotService $bot): Response
	{
		$secret = (string) config('services.telegram.webhook_secret');
		$header = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

		if ($secret === '' || !hash_equals($secret, $header)) {
			Log::warning('telegram.webhook_forbidden');
			return response('Forbidden', 403);
		}

		/** @var array<string, mixed> $update */
		$update = (array) $request->all();

		$updateId = $update['update_id'] ?? 'unknown';
		Log::info('telegram.webhook_received', ['update_id' => $updateId]);

		$bot->handleUpdate($update);

		return response('OK', 200);
	}
}








