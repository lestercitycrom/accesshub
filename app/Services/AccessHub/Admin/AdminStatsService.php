<?php

declare(strict_types=1);

namespace App\Services\AccessHub\Admin;

use App\Models\Account;
use Carbon\CarbonImmutable;

final class AdminStatsService
{
	public function summary(): string
	{
		$now = CarbonImmutable::now();

		$total = Account::query()->count();
		$active = Account::query()->where('is_active', true)->count();
		$availableNow = Account::query()->where('is_active', true)->where('available_uses', '>', 0)->count();

		$cooldown = Account::query()
			->where('is_active', true)
			->where('available_uses', '=', 0)
			->whereNotNull('next_release_at')
			->where('next_release_at', '>', $now)
			->count();

		return implode("\n", [
			"Статистика AccessHub:",
			"Всего аккаунтов: {$total}",
			"Активных: {$active}",
			"Доступны сейчас: {$availableNow}",
			"На паузе (ожидание): {$cooldown}",
		]);
	}
}

