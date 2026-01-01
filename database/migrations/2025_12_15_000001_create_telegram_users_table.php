<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration
{
	public function up(): void
	{
		Schema::create('telegram_users', function (Blueprint $table): void {
			$table->id();
			$table->string('telegram_id')->unique();
			$table->string('role')->default('operator'); // admin|operator
			$table->boolean('is_active')->default(true);
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('telegram_users');
	}
};










