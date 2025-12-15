<?php

declare(strict_types=1);

namespace App\Services\Telegram;

final class TelegramUpdateParser
{
	public function parseIssueRequest(string $text): ?ParsedIssueRequest
	{
		$lines = array_values(array_filter(array_map(
			static fn (string $v): string => trim($v),
			preg_split('/\R/u', trim($text)) ?: []
		), static fn (string $v): bool => $v !== ''));

		if (count($lines) < 2) {
			return null;
		}

		$orderId = $lines[0];
		$gamePlatform = $lines[1];
		$qtyLine = $lines[2] ?? '';

		if (!preg_match('/^\d+$/', $orderId)) {
			return null;
		}

		$qty = $this->extractQty($gamePlatform, $qtyLine);
		$gamePlatform = $this->stripQtySuffix($gamePlatform);

		if (!preg_match('/^(.*?)\s*\((.*?)\)\s*$/u', $gamePlatform, $m)) {
			return null;
		}

		$game = trim($m[1]);
		$platform = trim($m[2]);

		if ($game === '' || $platform === '') {
			return null;
		}

		return new ParsedIssueRequest($orderId, $game, $platform, $qty);
	}

	private function extractQty(string $line2, string $line3): int
	{
		// Supports: "Game (Platform) x2" or third line "qty: 2"
		if (preg_match('/\bx(\d{1,2})\b/u', $line2, $m)) {
			return max(1, (int) $m[1]);
		}

		if (preg_match('/\bqty\s*:\s*(\d{1,2})\b/iu', $line3, $m)) {
			return max(1, (int) $m[1]);
		}

		return 1;
	}

	private function stripQtySuffix(string $line2): string
	{
		return (string) preg_replace('/\s+\bx\d{1,2}\b\s*$/u', '', $line2);
	}
}

