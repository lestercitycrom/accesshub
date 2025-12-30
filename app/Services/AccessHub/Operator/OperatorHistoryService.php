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
			return __('bot.history.empty');
		}

		$itemLines = [];
		foreach ($items as $log) {
			$dt = $log->issued_at?->format('Y-m-d H:i:s') ?? '-';
			$itemLines[] = "{$dt} | order: {$log->order_id} | {$log->game} ({$log->platform}) | account: {$log->account_id}";
		}

		return __('bot.history.last', [
			'count' => $limit,
			'items' => implode("\n", $itemLines),
		]);
	}
}








