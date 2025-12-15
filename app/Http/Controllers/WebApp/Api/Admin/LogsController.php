<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebApp\Api\Admin;

use App\Http\Controllers\WebApp\Api\Admin\Concerns\RequiresAdmin;
use App\Http\Controllers\WebApp\Api\Concerns\JsonResponds;
use App\Models\IssuanceLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class LogsController extends Controller
{
	use JsonResponds;
	use RequiresAdmin;

	public function __invoke(Request $request): JsonResponse
	{
		if (!$this->isAdmin($request)) {
			return $this->forbidden('admin only');
		}

		$page = max(1, (int) $request->query('page', 1));
		$perPage = (int) $request->query('per_page', 20);
		$perPage = min(max($perPage, 1), 100);

		$accountId = $this->q($request->query('account_id'));
		$orderId = $this->q($request->query('order_id'));
		$operatorId = $this->q($request->query('operator_id'));

		$query = IssuanceLog::query()->orderByDesc('issued_at');

		if ($accountId !== '' && ctype_digit($accountId)) {
			$query->where('account_id', (int) $accountId);
		}

		if ($orderId !== '') {
			$query->where('order_id', $orderId);
		}

		if ($operatorId !== '') {
			$query->where('operator_telegram_id', $operatorId);
		}

		$total = (clone $query)->count();

		$items = $query->forPage($page, $perPage)->get([
			'id', 'issued_at', 'order_id', 'operator_telegram_id', 'account_id', 'game', 'platform',
		])->map(static function (IssuanceLog $log): array {
			return [
				'id' => $log->id,
				'issued_at' => $log->issued_at?->toISOString(),
				'order_id' => $log->order_id,
				'operator_telegram_id' => $log->operator_telegram_id,
				'account_id' => $log->account_id,
				'game' => $log->game,
				'platform' => $log->platform,
			];
		})->all();

		return $this->ok([
			'items' => $items,
			'page' => $page,
			'per_page' => $perPage,
			'total' => $total,
		]);
	}

	private function q(mixed $v): string
	{
		return is_string($v) ? trim($v) : '';
	}
}
