<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebApp\Api\Admin;

use App\Http\Controllers\WebApp\Api\Admin\Concerns\RequiresAdmin;
use App\Http\Controllers\WebApp\Api\Concerns\JsonResponds;
use App\Models\Account;
use App\Models\IssuanceLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class StatsController extends Controller
{
	use JsonResponds;
	use RequiresAdmin;

	public function __invoke(Request $request): JsonResponse
	{
		if (!$this->isAdmin($request)) {
			return $this->forbidden('admin only');
		}

		$now = CarbonImmutable::now();
		$todayStart = $now->startOfDay();
		$weekStart = $now->subDays(7);

		$totalAccounts = Account::query()->count();
		$activeAccounts = Account::query()->where('is_active', true)->count();
		$availableNow = Account::query()->where('is_active', true)->where('available_uses', '>', 0)->count();
		$onCooldown = Account::query()
			->where('is_active', true)
			->where('available_uses', '=', 0)
			->whereNotNull('next_release_at')
			->where('next_release_at', '>', $now)
			->count();

		$issuedToday = IssuanceLog::query()
			->where('issued_at', '>=', $todayStart)
			->count();

		$issuedLast7Days = IssuanceLog::query()
			->where('issued_at', '>=', $weekStart)
			->count();

		return $this->ok([
			'total_accounts' => $totalAccounts,
			'active_accounts' => $activeAccounts,
			'available_now' => $availableNow,
			'on_cooldown' => $onCooldown,
			'issued_today' => $issuedToday,
			'issued_last_7_days' => $issuedLast7Days,
		]);
	}
}
