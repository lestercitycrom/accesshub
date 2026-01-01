<?php

declare(strict_types=1);

namespace App\Services\AccessHub\Admin;

use App\Models\Account;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

final class BulkImportService
{
	/**
	 * @return array{added: int, skipped: int, errors: array<int, array{row: int, reason: string}>}
	 */
	public function importFromText(string $text): array
	{
		$lines = preg_split('/\R/u', trim($text)) ?: [];

		$added = 0;
		$skipped = 0;
		$errors = [];

		$maxUses = (int) config('accesshub.max_uses_default', 3);

		$rowNumber = 0;
		foreach ($lines as $line) {
			$rowNumber++;
			$line = trim((string) $line);

			if ($line === '' || str_starts_with($line, '#')) {
				continue;
			}

			// Format:
			// Platform | Game | game_login | game_password | email_login | email_password | backup_emails | platform_meta
			$parts = array_map(static fn (string $v): string => trim($v), explode('|', $line));

			if (count($parts) < 4) {
				$errors[] = ['row' => $rowNumber, 'reason' => 'Missing required fields'];
				continue;
			}

			[$platform, $game, $gameLogin, $gamePassword] = $parts;

			// Platform validation: only if config list is not empty
			$allowedPlatforms = (array) config('accesshub.platforms', []);
			if ($allowedPlatforms !== [] && !in_array(trim($platform), $allowedPlatforms, true)) {
				$errors[] = ['row' => $rowNumber, 'reason' => 'Platform not allowed'];
				continue;
			}

			$emailLogin = $parts[4] ?? null;
			$emailPassword = $parts[5] ?? null;
			$backupEmails = $parts[6] ?? null;
			$platformMeta = $parts[7] ?? null;

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

			$meta = null;
			$platformMeta = $this->nullIfDash($platformMeta);
			if ($platformMeta !== null) {
				$decoded = json_decode($platformMeta, true);
				$meta = is_array($decoded) ? $decoded : ['raw' => $platformMeta];
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
					'platform_meta' => $meta,
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
				$errors[] = ['row' => $rowNumber, 'reason' => 'Unexpected error'];
			}
		}

		return [
			'added' => $added,
			'skipped' => $skipped,
			'errors' => $errors,
		];
	}

	/**
	 * Import accounts from file (TXT/CSV/XLSX).
	 *
	 * @return array{added: int, skipped: int, errors: array<int, array{row: int, reason: string}>}
	 */
	public function importFromFile(string $filePath, string $extension): array
	{
		$text = match (strtolower($extension)) {
			'txt' => $this->parseTxtFile($filePath),
			'csv' => $this->parseCsvFile($filePath),
			'xlsx' => $this->parseXlsxFile($filePath),
			default => '',
		};

		if ($text === '') {
			return ['added' => 0, 'skipped' => 0, 'errors' => [['row' => 1, 'reason' => 'File parsing failed']]];
		}

		return $this->importFromText($text);
	}

	private function parseTxtFile(string $filePath): string
	{
		$content = (string) file_get_contents($filePath);
		return $content;
	}

	private function parseCsvFile(string $filePath): string
	{
		$handle = fopen($filePath, 'r');
		if ($handle === false) {
			return '';
		}

		$lines = [];
		$header = null;
		$rowNumber = 0;

		while (($data = fgetcsv($handle)) !== false) {
			$rowNumber++;

			if (count(array_filter($data, static fn ($v) => trim((string) $v) !== '')) === 0) {
				continue;
			}

			if ($header === null) {
				$lower = array_map(static fn ($v) => strtolower(trim((string) $v)), $data);
				$isHeader = in_array('platform', $lower, true) && in_array('game', $lower, true);

				if ($isHeader) {
					$header = $lower;
					continue;
				}

				$header = [
					'platform',
					'game',
					'game_login',
					'game_password',
					'email_login',
					'email_password',
					'codes_receiver_emails',
					'platform_meta',
				];
			}

			$assoc = [];
			foreach ($header as $i => $key) {
				$assoc[$key] = isset($data[$i]) ? trim((string) $data[$i]) : '';
			}

			$metaStr = ($assoc['platform_meta'] ?? '') !== '' ? ($assoc['platform_meta']) : '-';
			$line = sprintf(
				'%s | %s | %s | %s | %s | %s | %s | %s',
				$assoc['platform'] ?? '',
				$assoc['game'] ?? '',
				$assoc['game_login'] ?? '',
				$assoc['game_password'] ?? '',
				($assoc['email_login'] ?? '') !== '' ? ($assoc['email_login']) : '-',
				($assoc['email_password'] ?? '') !== '' ? ($assoc['email_password']) : '-',
				($assoc['codes_receiver_emails'] ?? '') !== '' ? ($assoc['codes_receiver_emails']) : '-',
				$metaStr
			);

			$lines[] = $line;
		}

		fclose($handle);

		return implode("\n", $lines);
	}

	private function parseXlsxFile(string $filePath): string
	{
		// XLSX requires maatwebsite/excel package
		if (!class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
			// Return empty to trigger error in importFromFile
			return '';
		}

		return $this->parseXlsxWithExcel($filePath);
	}

	private function parseXlsxWithExcel(string $filePath): string
	{
		try {
			$sheets = \Maatwebsite\Excel\Facades\Excel::toArray([], $filePath);

			if (!isset($sheets[0]) || !is_array($sheets[0])) {
				return '';
			}

			$rowsRaw = $sheets[0];
			$header = null;
			$lines = [];

			foreach ($rowsRaw as $i => $row) {
				if (!is_array($row)) {
					continue;
				}

				$values = array_map(static fn ($v) => trim((string) $v), $row);
				if (count(array_filter($values, static fn ($v) => $v !== '')) === 0) {
					continue;
				}

				if ($header === null) {
					$lower = array_map(static fn ($v) => strtolower($v), $values);
					$isHeader = in_array('platform', $lower, true) && in_array('game', $lower, true);

					$header = $isHeader ? $lower : [
						'platform',
						'game',
						'game_login',
						'game_password',
						'email_login',
						'email_password',
						'codes_receiver_emails',
						'platform_meta',
					];

					if ($isHeader) {
						continue;
					}
				}

				$assoc = [];
				foreach ($header as $idx => $key) {
					$assoc[$key] = $values[$idx] ?? '';
				}

				$metaStr = ($assoc['platform_meta'] ?? '') !== '' ? ($assoc['platform_meta']) : '-';
				$line = sprintf(
					'%s | %s | %s | %s | %s | %s | %s | %s',
					$assoc['platform'] ?? '',
					$assoc['game'] ?? '',
					$assoc['game_login'] ?? '',
					$assoc['game_password'] ?? '',
					($assoc['email_login'] ?? '') !== '' ? ($assoc['email_login']) : '-',
					($assoc['email_password'] ?? '') !== '' ? ($assoc['email_password']) : '-',
					($assoc['codes_receiver_emails'] ?? '') !== '' ? ($assoc['codes_receiver_emails']) : '-',
					$metaStr
				);

				$lines[] = $line;
			}

			return implode("\n", $lines);
		} catch (\Throwable) {
			return '';
		}
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










