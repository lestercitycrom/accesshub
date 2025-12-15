<?php

return [
	'tabs' => [
		'issue' => [
			'title' => 'Выдача',
			'header' => [
				'title' => 'Выдача',
				'subtitle' => 'Получить аккаунт',
			],
			'fields' => [
				'order_id' => 'Order ID',
				'game' => 'Игра',
				'platform' => 'Платформа',
				'qty' => 'Количество',
			],
		],
		'history' => [
			'title' => 'История',
			'header' => [
				'title' => 'История',
				'subtitle' => 'Просмотр выдач',
			],
			'fields' => [
				'order_id' => 'Order ID (опционально)',
			],
		],
		'help' => [
			'title' => 'Помощь',
			'header' => [
				'title' => 'Помощь',
			],
			'content' => "Формат выдачи:\n— Order ID\n— Игра (Платформа)\n\nWebApp отправляет данные боту, бот отвечает в чат.",
		],
		'admin_find' => [
			'title' => 'Админ: Поиск',
			'header' => [
				'title' => 'Поиск',
				'subtitle' => 'Найти аккаунты',
			],
			'fields' => [
				'game' => 'Игра (опционально)',
				'platform' => 'Платформа (опционально)',
				'status' => 'Статус: available/cooldown/disabled',
			],
		],
		'admin_export' => [
			'title' => 'Админ: Экспорт',
			'content' => "Ссылки:\n— /webapp/api/admin/export/accounts.csv\n— /webapp/api/admin/export/issuance_logs.csv",
		],
	],
	'ui' => [
		'submit' => 'Отправить',
		'clear' => 'Очистить',
		'result_placeholder' => 'Результат появится здесь.',
		'loading' => 'Загрузка...',
		'load_more' => 'Загрузить ещё',
		'end_of_list' => 'Конец списка',
		'total' => 'Всего: :count',
		'no_data' => 'Нет данных',
		'page_of' => 'Стр. :page из :total',
		'role' => 'Role: :role',
		'tg_id' => 'TG: :id',
		'order' => 'Order: :id',
		'account_id' => 'Account ID: :id',
		'download_accounts' => 'Скачать accounts.csv',
		'download_logs' => 'Скачать issuance_logs.csv',
		'downloaded' => ':file скачан',
		'no_access' => 'Нет доступа. Обратитесь к администратору для добавления в систему.',
		'auth_error' => 'Ошибка авторизации. Перезагрузите WebApp.',
	],
];
