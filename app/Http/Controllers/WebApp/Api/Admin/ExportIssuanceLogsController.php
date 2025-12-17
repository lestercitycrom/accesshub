<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebApp\Api\Admin;

use App\Http\Controllers\WebApp\Api\Admin\Concerns\RequiresAdmin;
use App\Models\IssuanceLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportIssuanceLogsController extends Controller
{
	use RequiresAdmin;

	public function __invoke(Request $request): StreamedResponse
	{
		if (!$this->isAdmin($request)) {
			abort(403);
		}

		$filename = 'issuance_logs.csv';

		$filters = $this->extractFilters($request);

		return response()->streamDownload(function () use ($filters): void {
			$out = fopen('php://output', 'wb');

			fwrite($out, "\xEF\xBB\xBF");

			fputcsv($out, ['id', 'issued_at', 'order_id', 'operator_telegram_id', 'account_id', 'game', 'platform']);

			$query = $this->buildQuery($filters);

			$query->orderBy('id')->chunk(500, function ($chunk) use ($out): void {
				foreach ($chunk as $log) {
					fputcsv($out, [
						$log->id,
						$log->issued_at?->toISOString(),
						$log->order_id,
						$log->operator_telegram_id,
						$log->account_id,
						$log->game,
						$log->platform,
					]);
				}
			});

			fclose($out);
		}, $filename, [
			'Content-Type' => 'text/csv; charset=UTF-8',
		]);
	}

	/**
	 * @return array{date_from: string|null, date_to: string|null, operator_telegram_id: string, game: string, platform: string, order_id: string}
	 */
	private function extractFilters(Request $request): array
	{
		$q = static fn (mixed $v): string => is_string($v) ? trim($v) : '';

		return [
			'date_from' => $q($request->query('date_from')),
			'date_to' => $q($request->query('date_to')),
			'operator_telegram_id' => $q($request->query('operator_telegram_id')),
			'game' => $q($request->query('game')),
			'platform' => $q($request->query('platform')),
			'order_id' => $q($request->query('order_id')),
		];
	}

	/**
	 * @param array{date_from: string|null, date_to: string|null, operator_telegram_id: string, game: string, platform: string, order_id: string} $filters
	 */
	private function buildQuery(array $filters): \Illuminate\Database\Eloquent\Builder
	{
		$query = IssuanceLog::query();

		if ($filters['date_from'] !== '') {
			try {
				$dateFrom = CarbonImmutable::parse($filters['date_from'])->startOfDay();
				$query->where('issued_at', '>=', $dateFrom);
			} catch (\Exception) {
				// Invalid date format, skip filter
			}
		}

		if ($filters['date_to'] !== '') {
			try {
				$dateTo = CarbonImmutable::parse($filters['date_to'])->endOfDay();
				$query->where('issued_at', '<=', $dateTo);
			} catch (\Exception) {
				// Invalid date format, skip filter
			}
		}

		if ($filters['operator_telegram_id'] !== '') {
			$query->where('operator_telegram_id', $filters['operator_telegram_id']);
		}

		if ($filters['game'] !== '') {
			$query->where('game', $filters['game']);
		}

		if ($filters['platform'] !== '') {
			$query->where('platform', $filters['platform']);
		}

		if ($filters['order_id'] !== '') {
			$query->where('order_id', $filters['order_id']);
		}

		return $query;
	}
}
