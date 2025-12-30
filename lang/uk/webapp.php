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
		'admin_import' => [
			'title' => 'Адмін: Імпорт',
			'header' => [
				'title' => 'Імпорт',
				'subtitle' => 'Масовий імпорт акаунтів',
			],
			'fields' => [
				'text' => 'Дані для імпорту',
				'file' => 'Файл для імпорту',
			],
		],
		'admin_settings' => [
			'title' => 'Адмін: Налаштування',
			'header' => [
				'title' => 'Налаштування',
			],
			'content' => 'Налаштування будуть додані пізніше.',
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
		'role' => 'Роль: :role',
		'tg_id' => 'TG ID: :id',
		'order' => 'Замовлення: :id',
		'account_id' => 'ID акаунта: :id',
		'download_accounts' => 'Завантажити accounts.csv',
		'download_logs' => 'Завантажити issuance_logs.csv',
		'downloaded' => ':file завантажено',
		'export_filters' => [
			'date_from' => 'Дата від',
			'date_to' => 'Дата до',
			'operator_telegram_id' => 'ID оператора',
			'game' => 'Гра',
			'platform' => 'Платформа',
			'order_id' => 'ID замовлення',
		],
		'export_filters_placeholders' => [
			'date_from' => 'РРРР-ММ-ДД',
			'date_to' => 'РРРР-ММ-ДД',
			'operator_telegram_id' => 'Telegram ID',
			'game' => 'Назва гри',
			'platform' => 'Назва платформи',
			'order_id' => 'ID замовлення',
		],
		'no_access' => 'Немає доступу. Зверніться до адміністратора для додавання в систему.',
		'auth_error' => 'Помилка авторизації. Перезавантажте WebApp.',
	],
	'errors' => [
		'validation_failed' => 'Перевірте поля форми.',
		'required' => 'Поле ":attribute" є обовʼязковим.',
		'string' => 'Поле ":attribute" має бути рядком.',
		'integer' => 'Поле ":attribute" має бути числом.',
		'min' => 'Поле ":attribute" має бути не менше :min.',
		'max' => 'Поле ":attribute" має бути не більше :max.',
		'regex' => 'Невірний формат поля ":attribute".',
		'file' => 'Поле ":attribute" має бути файлом.',
		'mimes' => 'Поле ":attribute" має бути файлом одного з типів: :values.',
		'invalid_file_type' => 'Непідтримуваний тип файлу. Дозволені: TXT, CSV, XLSX.',
		'not_enough_accounts' => 'Недостатньо вільних акаунтів: доступно :available, потрібно :needed.',
		'server_error' => 'Помилка сервера. Спробуйте пізніше.',
	],
];







