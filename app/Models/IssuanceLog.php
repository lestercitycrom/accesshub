<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IssuanceLog extends Model
{
	protected $fillable = [
		'order_id',
		'operator_telegram_id',
		'account_id',
		'game',
		'platform',
		'issued_at',
		'note',
	];

	protected $casts = [
		'issued_at' => 'datetime',
	];

	public function account(): BelongsTo
	{
		return $this->belongsTo(Account::class);
	}
}



