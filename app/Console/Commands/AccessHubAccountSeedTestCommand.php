<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;

final class AccessHubAccountSeedTestCommand extends Command
{
	protected $signature = 'accesshub:account:seed-test';
	protected $description = 'Seed test accounts for AccessHub.';

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function getTestAccounts(): array
	{
		return [
			[
				'platform' => 'Xbox X',
				'game' => 'Minecraft',
				'game_login' => 'test_minecraft_xbox_1',
				'game_password' => 'test_pass_123',
				'max_uses' => 3,
				'available_uses' => 3,
				'is_active' => true,
			],
			[
				'platform' => 'Xbox X',
				'game' => 'Minecraft',
				'game_login' => 'test_minecraft_xbox_2',
				'game_password' => 'test_pass_456',
				'max_uses' => 3,
				'available_uses' => 3,
				'is_active' => true,
			],
			[
				'platform' => 'PS5',
				'game' => 'Call of Duty',
				'game_login' => 'test_cod_ps5_1',
				'game_password' => 'cod_pass_789',
				'max_uses' => 3,
				'available_uses' => 2,
				'is_active' => true,
			],
			[
				'platform' => 'Steam',
				'game' => 'Counter-Strike 2',
				'game_login' => 'test_cs2_steam_1',
				'game_password' => 'cs2_pass_abc',
				'max_uses' => 3,
				'available_uses' => 1,
				'is_active' => true,
			],
			[
				'platform' => 'Epic',
				'game' => 'Fortnite',
				'game_login' => 'test_fortnite_epic_1',
				'game_password' => 'fortnite_pass_xyz',
				'max_uses' => 5,
				'available_uses' => 5,
				'is_active' => true,
			],
			[
				'platform' => 'Nintendo',
				'game' => 'Zelda: Tears of the Kingdom',
				'game_login' => 'test_zelda_switch_1',
				'game_password' => 'zelda_pass_def',
				'max_uses' => 3,
				'available_uses' => 0,
				'is_active' => true,
			],
		];
	}

	public function handle(): int
	{
		$accounts = $this->getTestAccounts();
		$created = 0;
		$skipped = 0;

		foreach ($accounts as $accountData) {
			try {
				$account = Account::create($accountData);
				$created++;
				$this->info("✓ Created: {$accountData['platform']} / {$accountData['game']} / {$accountData['game_login']}");
			} catch (\Exception $e) {
				$skipped++;
				$this->warn("✗ Skipped: {$accountData['platform']} / {$accountData['game']} / {$accountData['game_login']} (already exists)");
			}
		}

		$this->newLine();
		$this->info("Summary: {$created} created, {$skipped} skipped");

		return self::SUCCESS;
	}
}








