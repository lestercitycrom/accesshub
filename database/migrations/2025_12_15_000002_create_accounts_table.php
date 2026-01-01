<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration
{
	public function up(): void
	{
		Schema::create('accounts', function (Blueprint $table): void {
			$table->id();

			$table->string('platform');
			$table->string('game');

			$table->string('game_login');
			$table->text('game_password');

			// Sensitive (store encrypted in app layer)
			$table->text('email_login')->nullable();
			$table->text('email_password')->nullable();

			$table->json('codes_receiver_emails')->nullable();
			$table->json('platform_meta')->nullable();

			$table->unsignedTinyInteger('max_uses')->default(3);
			$table->unsignedTinyInteger('available_uses')->default(3);

			// cooldown = hold (единая пауза)
			$table->timestamp('next_release_at')->nullable();

			$table->boolean('is_active')->default(true);

			$table->timestamps();

			$table->index(['platform', 'game']);
			$table->unique(['platform', 'game', 'game_login'], 'accounts_platform_game_login_unique');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('accounts');
	}
};










