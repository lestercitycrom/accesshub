<?php

declare(strict_types=1);

use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\WebApp\Api\Admin\AccountsController;
use App\Http\Controllers\WebApp\Api\Admin\ExportAccountsController;
use App\Http\Controllers\WebApp\Api\Admin\ExportIssuanceLogsController;
use App\Http\Controllers\WebApp\Api\Admin\FindController;
use App\Http\Controllers\WebApp\Api\Admin\ImportFileController;
use App\Http\Controllers\WebApp\Api\Admin\ImportTextController;
use App\Http\Controllers\WebApp\Api\Admin\LogsController;
use App\Http\Controllers\WebApp\Api\Admin\StatsController;
use App\Http\Controllers\WebApp\Api\Admin\UsersController;
use App\Http\Controllers\WebApp\Api\HistoryController;
use App\Http\Controllers\WebApp\Api\IssueController;
use App\Http\Controllers\WebApp\Api\MeController;
use App\Http\Controllers\WebApp\Api\SchemaController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook', TelegramWebhookController::class);

Route::prefix('webapp/api')->middleware(['tg.webapp'])->group(function (): void {
	Route::get('/schema', SchemaController::class);
	Route::get('/me', MeController::class);
	Route::get('/history', HistoryController::class);
	Route::post('/issue', IssueController::class);

	Route::prefix('admin')->group(function (): void {
		Route::post('/accounts', [AccountsController::class, 'store']);
		Route::post('/accounts/{accountId}/enable', [AccountsController::class, 'enable'])->whereNumber('accountId');
		Route::post('/accounts/{accountId}/disable', [AccountsController::class, 'disable'])->whereNumber('accountId');
		Route::post('/accounts/{accountId}/reset', [AccountsController::class, 'resetAvailability'])->whereNumber('accountId');
		Route::post('/accounts/{accountId}/cooldown', [AccountsController::class, 'forceCooldown'])->whereNumber('accountId');
		Route::post('/import/text', ImportTextController::class);
		Route::post('/import/file', ImportFileController::class);
		Route::get('/find', FindController::class);
		Route::get('/logs', LogsController::class);
		Route::get('/stats', StatsController::class);

		Route::get('/users', [UsersController::class, 'index']);
		Route::post('/users', [UsersController::class, 'upsert']);

		Route::get('/export/accounts.csv', ExportAccountsController::class);
		Route::get('/export/issuance_logs.csv', ExportIssuanceLogsController::class);
	});
});





