<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class AccessHubRestoreAvailabilityCommand extends Command
{
	protected $signature = 'accesshub:restore-availability';
	protected $description = 'Restore availability for accounts when next_release_at is reached (cooldown=hold).';

	public function handle(): int
	{
		$now = CarbonImmutable::now();

		$updated = Account::query()
			->where('is_active', true)
			->where('available_uses', '=', 0)
			->whereNotNull('next_release_at')
			->where('next_release_at', '<=', $now)
			->update([
				'available_uses' => 1,
				'next_release_at' => null,
				'updated_at' => $now,
			]);

		$this->info("Restored accounts: {$updated}");

		return self::SUCCESS;
	}
}










