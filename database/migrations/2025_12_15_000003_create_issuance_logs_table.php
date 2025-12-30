<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration
{
	public function up(): void
	{
		Schema::create('issuance_logs', function (Blueprint $table): void {
			$table->id();

			$table->string('order_id');
			$table->string('operator_telegram_id');

			$table->foreignId('account_id')->constrained('accounts');

			$table->string('game');
			$table->string('platform');

			$table->timestamp('issued_at');
			$table->string('note')->nullable();

			$table->timestamps();

			$table->index(['order_id', 'operator_telegram_id']);
			$table->unique(['order_id', 'account_id'], 'issuance_logs_order_account_unique');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('issuance_logs');
	}
};








