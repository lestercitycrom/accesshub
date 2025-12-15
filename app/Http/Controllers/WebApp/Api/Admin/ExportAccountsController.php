<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebApp\Api\Admin;

use App\Http\Controllers\WebApp\Api\Admin\Concerns\RequiresAdmin;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportAccountsController extends Controller
{
	use RequiresAdmin;

	public function __invoke(Request $request): StreamedResponse
	{
		if (!$this->isAdmin($request)) {
			abort(403);
		}

		$filename = 'accounts.csv';

		return response()->streamDownload(function (): void {
			$out = fopen('php://output', 'wb');

			// UTF-8 BOM for Excel
			fwrite($out, "\xEF\xBB\xBF");

			fputcsv($out, ['id', 'platform', 'game', 'game_login', 'available_uses', 'max_uses', 'next_release_at', 'is_active', 'created_at']);

			Account::query()
				->orderBy('id')
				->chunk(500, function ($chunk) use ($out): void {
					foreach ($chunk as $a) {
						fputcsv($out, [
							$a->id,
							$a->platform,
							$a->game,
							$a->game_login,
							(int) $a->available_uses,
							(int) $a->max_uses,
							$a->next_release_at?->toISOString(),
							(bool) $a->is_active ? 1 : 0,
							$a->created_at?->toISOString(),
						]);
					}
				});

			fclose($out);
		}, $filename, [
			'Content-Type' => 'text/csv; charset=UTF-8',
		]);
	}
}
