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
		'admin_logs' => [
			'title' => 'Адмін: Логи',
			'header' => [
				'title' => 'Логи',
				'subtitle' => 'Журнал видач',
			],
			'fields' => [
				'account_id' => 'Account ID (опціонально)',
				'order_id' => 'Order ID (опціонально)',
				'operator_id' => 'TG оператора (опціонально)',
			],
		],
		'admin_stats' => [
			'title' => 'Адмін: Статистика',
			'header' => [
				'title' => 'Статистика',
				'subtitle' => 'Поточні показники',
			],
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
	'errors' => [
		'validation_failed' => 'Перевірте поля форми.',
		'required' => 'Поле ":attribute" є обовʼязковим.',
		'integer' => 'Поле ":attribute" має бути числом.',
		'min' => 'Поле ":attribute" має бути не менше :min.',
		'max' => 'Поле ":attribute" має бути не більше :max.',
		'regex' => 'Невірний формат поля ":attribute".',
		'not_enough_accounts' => 'Недостатньо вільних акаунтів: доступно :available, потрібно :needed.',
		'server_error' => 'Помилка сервера. Спробуйте пізніше.',
	],
];
