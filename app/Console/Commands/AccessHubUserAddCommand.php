<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TelegramUserRole;
use App\Models\TelegramUser;
use Illuminate\Console\Command;

final class AccessHubUserAddCommand extends Command
{
	protected $signature = 'accesshub:user:add {telegram_id} {role=operator}';
	protected $description = 'Add or update telegram user for AccessHub (admin/operator).';

	public function handle(): int
	{
		$telegramId = (string) $this->argument('telegram_id');
		$role = (string) $this->argument('role');

		$roleEnum = TelegramUserRole::tryFrom($role);
		if ($roleEnum === null) {
			$this->error('Role must be: admin or operator.');
			return self::FAILURE;
		}

		TelegramUser::query()->updateOrCreate(
			['telegram_id' => $telegramId],
			['role' => $roleEnum, 'is_active' => true]
		);

		$this->info("OK: {$telegramId} -> {$roleEnum->value}");

		return self::SUCCESS;
	}
}








