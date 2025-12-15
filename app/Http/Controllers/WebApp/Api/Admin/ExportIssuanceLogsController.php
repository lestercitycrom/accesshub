<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebApp\Api\Admin;

use App\Http\Controllers\WebApp\Api\Admin\Concerns\RequiresAdmin;
use App\Models\IssuanceLog;
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

		return response()->streamDownload(function (): void {
			$out = fopen('php://output', 'wb');

			fwrite($out, "\xEF\xBB\xBF");

			fputcsv($out, ['id', 'issued_at', 'order_id', 'operator_telegram_id', 'account_id', 'game', 'platform']);

			IssuanceLog::query()
				->orderBy('id')
				->chunk(500, function ($chunk) use ($out): void {
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
}
