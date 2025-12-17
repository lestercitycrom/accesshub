<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\TelegramUser;
use App\Services\Telegram\TelegramWebAppInitDataValidator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
		$debugTelegramId = $this->extractDebugTelegramId($request);
		if ($debugTelegramId !== null) {
			Log::warning('webapp.debug_bypass_used', [
				'telegram_id' => $debugTelegramId,
			]);

			$this->applyLocale($request, '', true);
			$authError = $this->attachUserAndAuthorize($request, $debugTelegramId);
			if ($authError !== null) {
				return $authError;
			}

			return $next($request);
		}

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

		$languageCode = (string) ($user['language_code'] ?? '');
		$this->applyLocale($request, $languageCode, false);

		$authError = $this->attachUserAndAuthorize($request, $telegramId);
		if ($authError !== null) {
			return $authError;
		}

		return $next($request);
	}

	/**
	 * @return string|null Telegram ID for debug bypass
	 */
	private function extractDebugTelegramId(Request $request): ?string
	{
		$debugAllowed = (bool) config('accesshub.webapp.debug_allow_header', false);
		if (!$debugAllowed) {
			return null;
		}

		$isSafeEnv = app()->environment('local') || (bool) config('app.debug', false);
		if (!$isSafeEnv) {
			return null;
		}

		$debugUserId = $request->header('X-Debug-Tg-UserId');
		if (!is_string($debugUserId)) {
			return null;
		}

		$debugUserId = trim($debugUserId);
		if ($debugUserId === '' || !preg_match('/^\d+$/', $debugUserId)) {
			return null;
		}

		return $debugUserId;
	}

	private function extractInitData(Request $request): ?string
	{
		// Canonical header (new): X-Tg-Init-Data
		$header = $request->header('X-Tg-Init-Data');
		if (is_string($header) && trim($header) !== '') {
			return trim($header);
		}

		// Legacy header (current frontend): X-TG-INIT-DATA
		$legacyHeader = $request->header('X-TG-INIT-DATA');
		if (is_string($legacyHeader) && trim($legacyHeader) !== '') {
			return trim($legacyHeader);
		}

		$body = $request->input('initData');
		if (is_string($body) && trim($body) !== '') {
			return trim($body);
		}

		return null;
	}

	private function applyLocale(Request $request, string $languageCode, bool $allowEmptyPrimary): void
	{
		$primary = $this->mapLanguageCodeToLocale($languageCode, $allowEmptyPrimary);
		$override = $this->extractLocaleOverride($request);

		$locale = $override ?? $primary;

		app()->setLocale($locale);
		$request->attributes->set('locale', $locale);
	}

	private function extractLocaleOverride(Request $request): ?string
	{
		$header = $request->header('X-Tg-Lang');
		if (!is_string($header)) {
			return null;
		}

		$header = strtolower(trim($header));
		if ($header === '') {
			return null;
		}

		$allowed = (array) config('accesshub.webapp.allowed_langs', ['ru', 'uk', 'en']);
		if (in_array($header, $allowed, true)) {
			return $header;
		}

		return null;
	}

	private function mapLanguageCodeToLocale(string $languageCode, bool $allowEmpty): string
	{
		$supportedLocales = (array) config('accesshub.webapp.allowed_langs', ['ru', 'uk', 'en']);
		$defaultLocale = (string) config('app.locale', 'ru');

		if ($languageCode === '') {
			if ($allowEmpty) {
				return $defaultLocale;
			}

			return $defaultLocale;
		}

		// Direct match
		if (in_array($languageCode, $supportedLocales, true)) {
			return $languageCode;
		}

		// Try first 2 characters (e.g., 'ru-RU' -> 'ru')
		$lang2 = substr($languageCode, 0, 2);
		if (in_array($lang2, $supportedLocales, true)) {
			return $lang2;
		}

		return $defaultLocale;
	}

	private function attachUserAndAuthorize(Request $request, string $telegramId): ?Response
	{
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

