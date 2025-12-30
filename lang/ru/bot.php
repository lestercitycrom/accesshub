<?php

return [
	'menu' => [
		'menu' => '📋 Меню',
		'issue' => '🎮 Получить аккаунт',
		'history' => '🧾 Моя история',
		'help' => 'ℹ️ Помощь',
		'add' => '➕ Добавить',
		'import' => '📥 Импорт',
		'stats' => '📊 Статистика',
		'logs' => '🧾 Логи',
		'find' => '🔎 Поиск',
		'export' => '📤 Экспорт',
		'users' => '👥 Пользователи',
		'refresh' => '🔄 Обновить',
		'lang' => '🌐 Язык',
	],
	'common' => [
		'back' => '◀️ Назад',
		'cancel' => '❌ Отмена',
		'ok' => '✅ ОК',
	],
	'replies' => [
		'welcome' => "Добро пожаловать в AccessHub!\n\nИспользуйте кнопки меню для навигации.",
		'access_denied' => 'Нет доступа. Обратитесь к администратору.',
		'error_generic' => 'Произошла ошибка. Попробуйте позже.',
		'no_permission' => 'Нет прав.',
		'command_cancelled' => 'Ок, мастер /add отменён.',
		'file_read_error' => 'Не удалось прочитать файл.',
		'file_path_error' => 'Не удалось получить путь файла.',
		'file_empty_error' => 'Файл пустой или не удалось скачать.',
		'import_complete' => 'Импорт из файла завершён.',
	],
	'issue' => [
		'success' => "✅ Аккаунт выдан!\n\nOrder: :order\nИгра: :game\nПлатформа: :platform\nAccount ID: :account_id",
		'not_found' => '❌ Аккаунт не найден для :game (:platform)',
		'help' => "Формат выдачи:\n— Order ID\n— Игра (Платформа)\n\nПример:\n2446303\nMinecraft (Xbox X)",
		'invalid_format' => "Неверный формат.\nНужно 2 строки:\n1) Номер заказа\n2) Игра (Платформа)\n\nПример:\n2446303\nMinecraft (Xbox X)",
	],
	'history' => [
		'empty' => 'История пуста.',
		'items' => '{0} Нет записей|{1} :count запись|[2,4] :count записи|[5,*] :count записей',
		'last' => "Последние :count выдач:\n\n:items",
	],
	'admin' => [
		'import_help' => "Импорт:\n1) отправь /import + строки\nили\n2) пришли TXT-файл документом.\n\nФормат строки:\nPlatform | Game | login | pass | email | emailpass | backupEmails",
		'logs_help' => "Логи:\nКоманда: /log <account_id>\nПример: /log 12",
		'log_format' => "Формат: /log <account_id>\nПример: /log 12",
		'import_result' => "Импорт:\nДобавлено: :added\nПропущено: :skipped\nОшибок: :errors",
	],
	'lang' => [
		'select' => 'Выберите язык / Choose language / Оберіть мову:',
		'set' => 'Язык установлен: :lang',
		'ru' => '🇷🇺 Русский',
		'uk' => '🇺🇦 Українська',
		'en' => '🇬🇧 English',
	],
];







