<?php

declare(strict_types=1);

namespace App\Services\AccessHub\Admin;

use App\Models\IssuanceLog;

final class LogsService
{
	public function accountLog(int $accountId, int $limit = 10): string
	{
		$items = IssuanceLog::query()
			->where('account_id', $accountId)
			->orderByDesc('issued_at')
			->limit($limit)
			->get();

		if ($items->isEmpty()) {
			return 'Логов нет.';
		}

		$lines = [];
		$lines[] = "Логи аккаунта #{$accountId} (последние {$limit}):";
		$lines[] = '';

		foreach ($items as $log) {
			$lines[] = "{$log->issued_at?->format('Y-m-d H:i:s')} | order: {$log->order_id} | operator: {$log->operator_telegram_id}";
		}

		return implode("\n", $lines);
	}
}








