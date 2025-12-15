<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TelegramUserRole;
use Illuminate\Database\Eloquent\Model;

final class TelegramUser extends Model
{
	protected $fillable = [
		'telegram_id',
		'role',
		'is_active',
	];

	protected $casts = [
		'is_active' => 'bool',
		'role' => TelegramUserRole::class,
	];
}
