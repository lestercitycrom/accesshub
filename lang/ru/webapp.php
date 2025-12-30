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
		'admin_logs' => [
			'title' => 'Админ: Логи',
			'header' => [
				'title' => 'Логи',
				'subtitle' => 'Журнал выдач',
			],
			'fields' => [
				'account_id' => 'Account ID (опционально)',
				'order_id' => 'Order ID (опционально)',
				'operator_id' => 'TG оператора (опционально)',
			],
		],
		'admin_stats' => [
			'title' => 'Админ: Статистика',
			'header' => [
				'title' => 'Статистика',
				'subtitle' => 'Текущие показатели',
			],
		],
		'admin_import' => [
			'title' => 'Админ: Импорт',
			'header' => [
				'title' => 'Импорт',
				'subtitle' => 'Массовый импорт аккаунтов',
			],
			'fields' => [
				'text' => 'Данные для импорта',
				'file' => 'Файл для импорта',
			],
		],
		'admin_settings' => [
			'title' => 'Админ: Настройки',
			'header' => [
				'title' => 'Настройки',
			],
			'content' => 'Настройки будут добавлены позже.',
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
		'role' => 'Роль: :role',
		'tg_id' => 'TG ID: :id',
		'order' => 'Заказ: :id',
		'account_id' => 'ID аккаунта: :id',
		'download_accounts' => 'Скачать accounts.csv',
		'download_logs' => 'Скачать issuance_logs.csv',
		'downloaded' => ':file скачан',
		'export_filters' => [
			'date_from' => 'Дата от',
			'date_to' => 'Дата до',
			'operator_telegram_id' => 'ID оператора',
			'game' => 'Игра',
			'platform' => 'Платформа',
			'order_id' => 'ID заказа',
		],
		'export_filters_placeholders' => [
			'date_from' => 'ГГГГ-ММ-ДД',
			'date_to' => 'ГГГГ-ММ-ДД',
			'operator_telegram_id' => 'Telegram ID',
			'game' => 'Название игры',
			'platform' => 'Название платформы',
			'order_id' => 'ID заказа',
		],
		'no_access' => 'Нет доступа. Обратитесь к администратору для добавления в систему.',
		'auth_error' => 'Ошибка авторизации. Перезагрузите WebApp.',
	],
	'errors' => [
		'validation_failed' => 'Проверьте поля формы.',
		'required' => 'Поле ":attribute" обязательно.',
		'string' => 'Поле ":attribute" должно быть строкой.',
		'integer' => 'Поле ":attribute" должно быть числом.',
		'min' => 'Поле ":attribute" должно быть не меньше :min.',
		'max' => 'Поле ":attribute" должно быть не больше :max.',
		'regex' => 'Поле ":attribute" заполнено неверно.',
		'file' => 'Поле ":attribute" должно быть файлом.',
		'mimes' => 'Поле ":attribute" должно быть файлом одного из типов: :values.',
		'invalid_file_type' => 'Неподдерживаемый тип файла. Разрешены: TXT, CSV, XLSX.',
		'not_enough_accounts' => 'Недостаточно свободных аккаунтов: доступно :available, нужно :needed.',
		'server_error' => 'Ошибка сервера. Попробуйте позже.',
	],
];







