<?php

declare(strict_types=1);

use App\Http\Controllers\WebApp\WebAppController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/webapp', WebAppController::class)->name('webapp');
