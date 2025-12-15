<?php

declare(strict_types=1);

namespace App\Services\AccessHub\Operator;

use App\Models\IssuanceLog;

final class OperatorHistoryService
{
	public function last(string $operatorTelegramId, int $limit = 10): string
	{
		$items = IssuanceLog::query()
			->where('operator_telegram_id', $operatorTelegramId)
			->orderByDesc('issued_at')
			->limit($limit)
			->get();

		if ($items->isEmpty()) {
			return 'История пустая.';
		}

		$lines = [];
		$lines[] = "Последние выдачи ({$limit}):";
		$lines[] = '';

		foreach ($items as $log) {
			$dt = $log->issued_at?->format('Y-m-d H:i:s') ?? '-';
			$lines[] = "{$dt} | order: {$log->order_id} | {$log->game} ({$log->platform}) | account: {$log->account_id}";
		}

		return implode("\n", $lines);
	}
}

