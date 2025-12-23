<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

final class AccessHubAccountAddCommand extends Command
{
	protected $signature = 'accesshub:account:add
							{--platform= : Platform name (e.g., "Xbox X", "PS5")}
							{--game= : Game name (e.g., "Minecraft")}
							{--game_login= : Game login}
							{--game_password= : Game password}
							{--email_login= : Email login (optional)}
							{--email_password= : Email password (optional)}
							{--max_uses=3 : Maximum uses before cooldown}
							{--available_uses= : Available uses (defaults to max_uses)}
							{--active=1 : Is active (1 or 0)}';

	protected $description = 'Add a new account to AccessHub.';

	public function handle(): int
	{
		try {
			$data = $this->collectData();

			$account = Account::create($data);

			$this->info("Account created successfully!");
			$this->line("ID: {$account->id}");
			$this->line("Platform: {$account->platform}");
			$this->line("Game: {$account->game}");
			$this->line("Login: {$account->game_login}");
			$this->line("Available uses: {$account->available_uses}/{$account->max_uses}");

			return self::SUCCESS;
		} catch (ValidationException $e) {
			foreach ($e->errors() as $field => $messages) {
				foreach ($messages as $message) {
					$this->error("{$field}: {$message}");
				}
			}
			return self::FAILURE;
		} catch (\Illuminate\Database\QueryException $e) {
			if ($e->getCode() === '23000') {
				$this->error('Account with this platform, game and login already exists.');
			} else {
				$this->error("Database error: {$e->getMessage()}");
			}
			return self::FAILURE;
		} catch (\Exception $e) {
			$this->error("Error: {$e->getMessage()}");
			return self::FAILURE;
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function collectData(): array
	{
		$platform = $this->option('platform') ?: $this->ask('Platform');
		$game = $this->option('game') ?: $this->ask('Game');
		$gameLogin = $this->option('game_login') ?: $this->ask('Game login');
		$gamePassword = $this->option('game_password') ?: $this->secret('Game password');

		if (!$platform || !$game || !$gameLogin || !$gamePassword) {
			throw ValidationException::withMessages([
				'fields' => ['Platform, Game, Game login and Game password are required.'],
			]);
		}

		$maxUses = (int) ($this->option('max_uses') ?: 3);
		$availableUses = $this->option('available_uses')
			? (int) $this->option('available_uses')
			: $maxUses;

		$data = [
			'platform' => $platform,
			'game' => $game,
			'game_login' => $gameLogin,
			'game_password' => $gamePassword,
			'max_uses' => max(1, $maxUses),
			'available_uses' => max(0, min($availableUses, $maxUses)),
			'is_active' => (bool) (int) ($this->option('active') ?: 1),
		];

		if ($this->option('email_login')) {
			$data['email_login'] = $this->option('email_login');
		}

		if ($this->option('email_password')) {
			$data['email_password'] = $this->option('email_password');
		}

		return $data;
	}
}



