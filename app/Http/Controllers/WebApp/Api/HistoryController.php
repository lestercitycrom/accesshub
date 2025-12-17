<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebApp\Api;

use App\Http\Controllers\WebApp\Api\Concerns\JsonResponds;
use App\Models\IssuanceLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class HistoryController extends Controller
{
	use JsonResponds;

	public function __invoke(Request $request): JsonResponse
	{
		$telegramId = (string) $request->attributes->get('telegram_id');

		$page = max(1, (int) $request->query('page', 1));
		$perPage = (int) $request->query('per_page', 20);
		$perPage = min(max($perPage, 1), 100);

		$orderId = $request->query('order_id');
		$orderId = is_string($orderId) ? trim($orderId) : '';

		$query = IssuanceLog::query()
			->where('operator_telegram_id', $telegramId)
			->orderByDesc('issued_at')
			->orderByDesc('id');

		if ($orderId !== '') {
			$query->where('order_id', $orderId);
		}

		$total = (clone $query)->count();

		$items = $query
			->forPage($page, $perPage)
			->get(['id', 'issued_at', 'order_id', 'game', 'platform', 'account_id'])
			->map(static function (IssuanceLog $log): array {
				return [
					'id' => $log->id,
					'issued_at' => $log->issued_at?->toISOString(),
					'order_id' => $log->order_id,
					'game' => $log->game,
					'platform' => $log->platform,
					'account_id' => $log->account_id,
				];
			})
			->all();

		return $this->ok([
			'items' => $items,
			'page' => $page,
			'per_page' => $perPage,
			'total' => $total,
		]);
	}
}
