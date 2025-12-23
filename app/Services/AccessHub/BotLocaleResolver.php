<?php

declare(strict_types=1);

namespace App\Services\AccessHub;

use App\Models\TelegramUser;

final class BotLocaleResolver
{
	private const SUPPORTED_LOCALES = ['ru', 'uk', 'en'];
	private const DEFAULT_LOCALE = 'ru';

	/**
	 * Resolve locale for user based on priority:
	 * 1) telegram_users.locale (if user selected via /lang)
	 * 2) update.message.from.language_code (Telegram)
	 * 3) config('app.locale') fallback
	 *
	 * @param array<string, mixed> $update
	 */
	public function resolve(array $update, ?TelegramUser $user): string
	{
		// Priority 1: User's saved locale
		if ($user !== null && $user->locale !== null && $user->locale !== '') {
			$normalized = $this->normalize($user->locale);
			if (in_array($normalized, self::SUPPORTED_LOCALES, true)) {
				return $normalized;
			}
		}

		// Priority 2: Telegram language_code
		$from = $update['message']['from'] ?? null;
		if (is_array($from) && isset($from['language_code'])) {
			$langCode = (string) $from['language_code'];
			$normalized = $this->normalize($langCode);
			if (in_array($normalized, self::SUPPORTED_LOCALES, true)) {
				return $normalized;
			}
		}

		// Priority 3: Config fallback
		$configLocale = config('app.locale', self::DEFAULT_LOCALE);
		$normalized = $this->normalize($configLocale);
		return in_array($normalized, self::SUPPORTED_LOCALES, true) ? $normalized : self::DEFAULT_LOCALE;
	}

	/**
	 * Normalize language code: ru-RU -> ru, uk-UA -> uk, etc.
	 */
	private function normalize(string $langCode): string
	{
		$langCode = trim($langCode);
		if ($langCode === '') {
			return self::DEFAULT_LOCALE;
		}

		// Direct match
		if (in_array($langCode, self::SUPPORTED_LOCALES, true)) {
			return $langCode;
		}

		// Try first 2 characters (e.g., 'ru-RU' -> 'ru')
		$lang2 = substr($langCode, 0, 2);
		if (in_array($lang2, self::SUPPORTED_LOCALES, true)) {
			return $lang2;
		}

		return self::DEFAULT_LOCALE;
	}
}


