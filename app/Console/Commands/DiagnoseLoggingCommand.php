<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class DiagnoseLoggingCommand extends Command
{
	protected $signature = 'diagnose:logging';
	protected $description = 'Diagnose logging configuration';

	public function handle(): int
	{
		$this->info('=== Logging Configuration Diagnosis ===');
		$this->newLine();

		// Step 1: Check default channel
		$defaultChannel = config('logging.default');
		$this->line("1. Default channel: <comment>{$defaultChannel}</comment>");

		// Step 2: Check channel configuration
		$channelConfig = config("logging.channels.{$defaultChannel}");
		$this->line("2. Channel config:");
		$this->line("   Driver: <comment>" . ($channelConfig['driver'] ?? 'N/A') . "</comment>");

		if (isset($channelConfig['path'])) {
			$this->line("   Path: <comment>{$channelConfig['path']}</comment>");
		}

		if (isset($channelConfig['level'])) {
			$this->line("   Level: <comment>{$channelConfig['level']}</comment>");
		}

		if (isset($channelConfig['channels'])) {
			$this->line("   Stack channels: <comment>" . implode(', ', $channelConfig['channels']) . "</comment>");
		}

		// Step 3: Check daily channel path
		$dailyPath = config('logging.channels.daily.path');
		$this->newLine();
		$this->line("3. Daily channel path: <comment>{$dailyPath}</comment>");

		// Step 4: Check single channel path
		$singlePath = config('logging.channels.single.path');
		$this->line("4. Single channel path: <comment>{$singlePath}</comment>");

		// Step 5: Check environment
		$env = config('app.env');
		$this->newLine();
		$this->line("5. Environment: <comment>{$env}</comment>");

		// Step 6: List log files
		$this->newLine();
		$this->line("6. Log files in storage/logs:");
		$logDir = storage_path('logs');
		if (is_dir($logDir)) {
			$files = glob($logDir . '/*.log');
			if (empty($files)) {
				$this->warn('   No .log files found!');
			} else {
				foreach ($files as $file) {
					$size = filesize($file);
					$mtime = date('Y-m-d H:i:s', filemtime($file));
					$name = basename($file);
					$this->line("   - <info>{$name}</info> ({$size} bytes, modified: {$mtime})");
				}
			}
		} else {
			$this->error("   Directory {$logDir} does not exist!");
		}

		// Step 7: Test logging
		$this->newLine();
		$this->line("7. Testing logging...");
		Log::debug('diagnose.test.debug');
		Log::info('diagnose.test.info');
		Log::warning('diagnose.test.warning');
		Log::error('diagnose.test.error');
		$this->info('   Test log entries written. Check log files above.');

		// Step 8: Check write permissions
		$this->newLine();
		$this->line("8. Write permissions:");
		$testFile = storage_path('logs/write-test.txt');
		if (@file_put_contents($testFile, 'test')) {
			@unlink($testFile);
			$this->info('   ✓ Write permission: OK');
		} else {
			$this->error('   ✗ Write permission: FAILED');
		}

		$this->newLine();
		$this->info('=== Diagnosis Complete ===');
		$this->line('Check the log files listed above for test entries.');

		return 0;
	}
}

