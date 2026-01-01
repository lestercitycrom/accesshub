<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class AccessHubEncryptPasswordsCommand extends Command
{
	protected $signature = 'accesshub:encrypt-passwords';
	protected $description = 'Encrypt existing passwords in accounts table';

	public function handle(): int
	{
		$this->info('Encrypting passwords in accounts table...');

		// Get raw data directly from database (bypassing model casts)
		$accounts = DB::table('accounts')->get();
		$total = $accounts->count();
		$encrypted = 0;
		$skipped = 0;

		foreach ($accounts as $account) {
			$updates = [];

			// Check and encrypt game_password
			if (isset($account->game_password) && $account->game_password !== null && $account->game_password !== '') {
				if ($this->isEncrypted($account->game_password)) {
					// Already encrypted
				} else {
					// Not encrypted, encrypt it
					$updates['game_password'] = encrypt($account->game_password);
				}
			}

			// Check and encrypt email_login
			if (isset($account->email_login) && $account->email_login !== null && $account->email_login !== '') {
				if ($this->isEncrypted($account->email_login)) {
					// Already encrypted
				} else {
					// Not encrypted, encrypt it
					$updates['email_login'] = encrypt($account->email_login);
				}
			}

			// Check and encrypt email_password
			if (isset($account->email_password) && $account->email_password !== null && $account->email_password !== '') {
				if ($this->isEncrypted($account->email_password)) {
					// Already encrypted
				} else {
					// Not encrypted, encrypt it
					$updates['email_password'] = encrypt($account->email_password);
				}
			}

			if (!empty($updates)) {
				DB::table('accounts')->where('id', $account->id)->update($updates);
				$encrypted++;
				$this->line("Encrypted account #{$account->id}");
			} else {
				$skipped++;
			}
		}

		$this->info("Total accounts: {$total}");
		$this->info("Encrypted: {$encrypted}");
		$this->info("Skipped (already encrypted or empty): {$skipped}");

		return 0;
	}

	private function isEncrypted(string $value): bool
	{
		try {
			decrypt($value);
			return true;
		} catch (\Exception) {
			return false;
		}
	}
}










