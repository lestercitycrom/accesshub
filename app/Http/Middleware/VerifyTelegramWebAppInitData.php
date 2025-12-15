<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\TelegramUser;
use App\Services\Telegram\TelegramWebAppInitDataValidator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyTelegramWebAppInitData
{
	public function __construct(
		private readonly TelegramWebAppInitDataValidator $validator
	) {
	}

	/**
	 * @param Closure(Request): Response $next
	 */
	public function handle(Request $request, Closure $next): Response
	{
		$initData = $this->extractInitData($request);
		if ($initData === null) {
			return $this->jsonError('UNAUTHORIZED', 'initData missing', 401);
		}

		$botToken = (string) config('services.telegram.token');
		if ($botToken === '') {
			return $this->jsonError('SERVER_ERROR', 'bot token not configured', 500);
		}

		$ttl = (int) config('accesshub.webapp_init_data_ttl', 86400);

		try {
			$params = $this->validator->validate($initData, $botToken, $ttl);
		} catch (\Throwable $e) {
			return $this->jsonError('UNAUTHORIZED', $e->getMessage(), 401);
		}

		$userJson = $params['user'] ?? null;
		if (!is_string($userJson) || $userJson === '') {
			return $this->jsonError('UNAUTHORIZED', 'user missing', 401);
		}

		$user = json_decode($userJson, true);
		if (!is_array($user) || !isset($user['id'])) {
			return $this->jsonError('UNAUTHORIZED', 'user invalid', 401);
		}

		$telegramId = (string) $user['id'];

		$request->attributes->set('telegram_id', $telegramId);

		$denyByDefault = (bool) config('accesshub.deny_by_default', true);

		$dbUser = TelegramUser::query()
			->where('telegram_id', $telegramId)
			->where('is_active', true)
			->first();

		if ($denyByDefault && $dbUser === null) {
			return $this->jsonError('FORBIDDEN', 'no access', 403);
		}

		$request->attributes->set('telegram_user', $dbUser);

		return $next($request);
	}

	private function extractInitData(Request $request): ?string
	{
		$header = $request->header('X-TG-INIT-DATA');
		if (is_string($header) && trim($header) !== '') {
			return $header;
		}

		$body = $request->input('initData');
		if (is_string($body) && trim($body) !== '') {
			return $body;
		}

		return null;
	}

	private function jsonError(string $code, string $message, int $status): Response
	{
		return response()->json([
			'ok' => false,
			'error' => [
				'code' => $code,
				'message' => $message,
			],
		], $status);
	}
}
