<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Account extends Model
{
	protected $fillable = [
		'platform',
		'game',
		'game_login',
		'game_password',
		'email_login',
		'email_password',
		'codes_receiver_emails',
		'platform_meta',
		'max_uses',
		'available_uses',
		'next_release_at',
		'is_active',
	];

	protected $casts = [
		'codes_receiver_emails' => 'array',
		'platform_meta' => 'array',
		'next_release_at' => 'datetime',
		'is_active' => 'bool',
	];
}








