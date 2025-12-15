<?php

declare(strict_types=1);

use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\WebApp\Api\Admin\AccountsController;
use App\Http\Controllers\WebApp\Api\Admin\ExportAccountsController;
use App\Http\Controllers\WebApp\Api\Admin\ExportIssuanceLogsController;
use App\Http\Controllers\WebApp\Api\Admin\FindController;
use App\Http\Controllers\WebApp\Api\Admin\ImportTextController;
use App\Http\Controllers\WebApp\Api\Admin\LogsController;
use App\Http\Controllers\WebApp\Api\Admin\UsersController;
use App\Http\Controllers\WebApp\Api\HistoryController;
use App\Http\Controllers\WebApp\Api\MeController;
use App\Http\Controllers\WebApp\Api\SchemaController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook', TelegramWebhookController::class);

Route::prefix('webapp/api')->middleware(['tg.webapp'])->group(function (): void {
	Route::get('/schema', SchemaController::class);
	Route::get('/me', MeController::class);
	Route::get('/history', HistoryController::class);

	Route::prefix('admin')->group(function (): void {
		Route::post('/accounts', [AccountsController::class, 'store']);
		Route::post('/import/text', ImportTextController::class);
		Route::get('/find', FindController::class);
		Route::get('/logs', LogsController::class);

		Route::get('/users', [UsersController::class, 'index']);
		Route::post('/users', [UsersController::class, 'upsert']);

		Route::get('/export/accounts.csv', ExportAccountsController::class);
		Route::get('/export/issuance_logs.csv', ExportIssuanceLogsController::class);
	});
});

