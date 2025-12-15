<?php

declare(strict_types=1);

namespace App\Services\Telegram;

final class ParsedIssueRequest
{
	public function __construct(
		public readonly string $orderId,
		public readonly string $game,
		public readonly string $platform,
		public readonly int $qty
	) {
	}
}
