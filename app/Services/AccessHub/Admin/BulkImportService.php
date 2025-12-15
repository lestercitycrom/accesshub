<?php

declare(strict_types=1);

namespace App\Services\AccessHub\Admin;

use App\Models\Account;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

final class BulkImportService
{
	/**
	 * @return array{added: int, skipped: int, errors: int}
	 */
	public function importFromText(string $text): array
	{
		$lines = preg_split('/\R/u', trim($text)) ?: [];

		$added = 0;
		$skipped = 0;
		$errors = 0;

		$maxUses = (int) config('accesshub.max_uses_default', 3);

		foreach ($lines as $line) {
			$line = trim((string) $line);

			if ($line === '' || str_starts_with($line, '#')) {
				continue;
			}

			// Format:
			// Platform | Game | game_login | game_password | email_login | email_password | backup_emails
			$parts = array_map(static fn (string $v): string => trim($v), explode('|', $line));

			if (count($parts) < 4) {
				$errors++;
				continue;
			}

			[$platform, $game, $gameLogin, $gamePassword] = $parts;

			$emailLogin = $parts[4] ?? null;
			$emailPassword = $parts[5] ?? null;
			$backupEmails = $parts[6] ?? null;

			$emailLogin = $this->nullIfDash($emailLogin);
			$emailPassword = $this->nullIfDash($emailPassword);

			$codes = null;
			$backupEmails = $this->nullIfDash($backupEmails);
			if ($backupEmails !== null) {
				$codes = array_values(array_filter(array_map(
					static fn (string $v): string => trim($v),
					preg_split('/[,;\s]+/u', $backupEmails) ?: []
				), static fn (string $v): bool => $v !== ''));
			}

			try {
				Account::create([
					'platform' => $platform,
					'game' => $game,
					'game_login' => $gameLogin,
					'game_password' => $gamePassword,
					'email_login' => $emailLogin,
					'email_password' => $emailPassword,
					'codes_receiver_emails' => $codes,
					'platform_meta' => null,
					'max_uses' => $maxUses,
					'available_uses' => $maxUses,
					'next_release_at' => null,
					'is_active' => true,
				]);

				$added++;
			} catch (QueryException) {
				// Duplicate by unique(platform, game, game_login) or other constraint
				$skipped++;
			} catch (\Throwable) {
				$errors++;
			}
		}

		return [
			'added' => $added,
			'skipped' => $skipped,
			'errors' => $errors,
		];
	}

	private function nullIfDash(?string $value): ?string
	{
		if ($value === null) {
			return null;
		}

		$value = trim($value);

		if ($value === '' || $value === '-' || Str::lower($value) === 'none') {
			return null;
		}

		return $value;
	}
}

