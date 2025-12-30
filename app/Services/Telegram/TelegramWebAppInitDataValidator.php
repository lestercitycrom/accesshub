<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use RuntimeException;

final class TelegramWebAppInitDataValidator
{
	/**
	 * @return array<string, string> validated params (without hash)
	 */
	public function validate(string $initData, string $botToken, int $ttlSeconds): array
	{
		$initData = trim($initData);

		if ($initData === '') {
			throw new RuntimeException('initData is empty');
		}

		$params = $this->parseQueryString($initData);

		$hash = $params['hash'] ?? null;
		if (!is_string($hash) || $hash === '') {
			throw new RuntimeException('hash missing');
		}

		unset($params['hash']);

		$authDate = $params['auth_date'] ?? null;
		if (!is_string($authDate) || $authDate === '' || !ctype_digit($authDate)) {
			throw new RuntimeException('auth_date invalid');
		}

		$authTimestamp = (int) $authDate;
		if ($ttlSeconds > 0) {
			$now = time();
			if ($authTimestamp < ($now - $ttlSeconds)) {
				throw new RuntimeException('initData expired');
			}
		}

		$dataCheckString = $this->buildDataCheckString($params);

		// secret_key = HMAC_SHA256(bot_token, "WebAppData") (binary)
		$secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);

		// signature = HMAC_SHA256(data_check_string, secret_key) (hex)
		$signature = hash_hmac('sha256', $dataCheckString, $secretKey);

		if (!hash_equals($signature, $hash)) {
			throw new RuntimeException('signature mismatch');
		}

		return $params;
	}

	/**
	 * @return array<string, string>
	 */
	private function parseQueryString(string $initData): array
	{
		// initData is a query string: key=value&key2=value2...
		$out = [];
		parse_str($initData, $out);

		$params = [];
		foreach ($out as $k => $v) {
			if (!is_string($k)) {
				continue;
			}

			// WebApp values are strings (user is JSON string)
			if (is_scalar($v)) {
				$params[$k] = (string) $v;
			}
		}

		return $params;
	}

	/**
	 * data_check_string: sorted by key asc, "key=value" joined by "\n"
	 *
	 * @param array<string, string> $params
	 */
	private function buildDataCheckString(array $params): string
	{
		ksort($params);

		$lines = [];
		foreach ($params as $key => $value) {
			$lines[] = $key . '=' . $value;
		}

		return implode("\n", $lines);
	}
}







