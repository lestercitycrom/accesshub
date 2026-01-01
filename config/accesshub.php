<?php

declare(strict_types=1);

return [
	/*
	|--------------------------------------------------------------------------
	| Platforms
	|--------------------------------------------------------------------------
	| Пока можно работать без списка платформ.
	| Позже включим enforce_platform_list и дадим строгий список.
	*/
	// TODO: Replace with final customer-approved list
	// If empty array [] → platform validation is disabled (accept any non-empty value)
	// If not empty → strict validation (only values from this list are allowed)
	'platforms' => [
		// Empty by default until final list is provided by customer
		// 'PS',
		// 'Xbox',
		// 'Steam',
		// 'Epic',
		// 'Nintendo',
	],

	'enforce_platform_list' => false,

	/*
	|--------------------------------------------------------------------------
	| Issue rules
	|--------------------------------------------------------------------------
	*/
	'max_uses_default' => 3,
	'release_days' => 14,

	/*
	|--------------------------------------------------------------------------
	| Security / Access
	|--------------------------------------------------------------------------
	| По умолчанию доступ только у тех, кто есть в telegram_users и активен.
	*/
	'deny_by_default' => true,

	/*
	|--------------------------------------------------------------------------
	| WebApp initData TTL (seconds)
	|--------------------------------------------------------------------------
	*/
	'webapp_init_data_ttl' => (int) env('ACCESSHUB_WEBAPP_TTL', 86400),

	/*
	|--------------------------------------------------------------------------
	| WebApp (Mini App) helpers
	|--------------------------------------------------------------------------
	| Debug bypass is for local/dev only.
	*/
	'webapp' => [
		'debug_allow_header' => (bool) env('ACCESSHUB_WEBAPP_DEBUG', false),
		'allowed_langs' => ['ru', 'uk', 'en'],
	],
];











