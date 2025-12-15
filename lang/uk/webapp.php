<?php

return [
	'tabs' => [
		'issue' => [
			'title' => 'Видача',
			'header' => [
				'title' => 'Видача',
				'subtitle' => 'Отримати акаунт',
			],
			'fields' => [
				'order_id' => 'Order ID',
				'game' => 'Гра',
				'platform' => 'Платформа',
				'qty' => 'Кількість',
			],
		],
		'history' => [
			'title' => 'Історія',
			'header' => [
				'title' => 'Історія',
				'subtitle' => 'Перегляд видач',
			],
			'fields' => [
				'order_id' => 'Order ID (опціонально)',
			],
		],
		'help' => [
			'title' => 'Допомога',
			'header' => [
				'title' => 'Допомога',
			],
			'content' => "Формат видачі:\n— Order ID\n— Гра (Платформа)\n\nWebApp відправляє дані боту, бот відповідає в чат.",
		],
		'admin_find' => [
			'title' => 'Адмін: Пошук',
			'header' => [
				'title' => 'Пошук',
				'subtitle' => 'Знайти акаунти',
			],
			'fields' => [
				'game' => 'Гра (опціонально)',
				'platform' => 'Платформа (опціонально)',
				'status' => 'Статус: available/cooldown/disabled',
			],
		],
		'admin_export' => [
			'title' => 'Адмін: Експорт',
			'content' => "Посилання:\n— /webapp/api/admin/export/accounts.csv\n— /webapp/api/admin/export/issuance_logs.csv",
		],
	],
	'ui' => [
		'submit' => 'Відправити',
		'clear' => 'Очистити',
		'result_placeholder' => 'Результат з\'явиться тут.',
		'loading' => 'Завантаження...',
		'load_more' => 'Завантажити ще',
		'end_of_list' => 'Кінець списку',
		'total' => 'Всього: :count',
		'no_data' => 'Немає даних',
		'page_of' => 'Стор. :page з :total',
		'role' => 'Role: :role',
		'tg_id' => 'TG: :id',
		'order' => 'Order: :id',
		'account_id' => 'Account ID: :id',
		'download_accounts' => 'Завантажити accounts.csv',
		'download_logs' => 'Завантажити issuance_logs.csv',
		'downloaded' => ':file завантажено',
		'no_access' => 'Немає доступу. Зверніться до адміністратора для додавання в систему.',
		'auth_error' => 'Помилка авторизації. Перезавантажте WebApp.',
	],
];
