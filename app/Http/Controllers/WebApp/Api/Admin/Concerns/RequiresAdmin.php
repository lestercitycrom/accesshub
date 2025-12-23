<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebApp\Api\Admin\Concerns;

use App\Models\TelegramUser;
use Illuminate\Http\Request;

trait RequiresAdmin
{
	protected function isAdmin(Request $request): bool
	{
		/** @var TelegramUser|null $tgUser */
		$tgUser = $request->attributes->get('telegram_user');

		$role = $tgUser?->role?->value ?? null;

		return $role === 'admin';
	}
}


