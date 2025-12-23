<?php

return [
	'menu' => [
		'menu' => '📋 Меню',
		'issue' => '🎮 Отримати акаунт',
		'history' => '🧾 Моя історія',
		'help' => 'ℹ️ Допомога',
		'add' => '➕ Додати',
		'import' => '📥 Імпорт',
		'stats' => '📊 Статистика',
		'logs' => '🧾 Логи',
		'find' => '🔎 Пошук',
		'export' => '📤 Експорт',
		'users' => '👥 Користувачі',
		'refresh' => '🔄 Оновити',
		'lang' => '🌐 Мова',
	],
	'common' => [
		'back' => '◀️ Назад',
		'cancel' => '❌ Скасувати',
		'ok' => '✅ ОК',
	],
	'replies' => [
		'welcome' => "Welcome to AccessHub!\n\nUse menu buttons for navigation.",
		'access_denied' => 'Немає доступу. Зверніться до адміністратора.',
		'error_generic' => 'Сталася помилка. Спробуйте пізніше.',
		'no_permission' => 'Немає прав.',
		'command_cancelled' => 'Ок, майстер /add скасовано.',
		'file_read_error' => 'Не вдалося прочитати файл.',
		'file_path_error' => 'Не вдалося отримати шлях файлу.',
		'file_empty_error' => 'Файл порожній або не вдалося завантажити.',
		'import_complete' => 'Імпорт з файлу завершено.',
	],
	'issue' => [
		'success' => "✅ Акаунт видано!\n\nOrder: :order\nГра: :game\nПлатформа: :platform\nAccount ID: :account_id",
		'not_found' => '❌ Акаунт не знайдено для :game (:platform)',
		'help' => "Формат видачі:\n— Order ID\n— Гра (Платформа)\n\nПриклад:\n2446303\nMinecraft (Xbox X)",
		'invalid_format' => "Невірний формат.\nПотрібно 2 рядки:\n1) Номер замовлення\n2) Гра (Платформа)\n\nПриклад:\n2446303\nMinecraft (Xbox X)",
	],
	'history' => [
		'empty' => 'Історія порожня.',
		'items' => '{0} Немає записів|{1} :count запис|[2,4] :count записи|[5,*] :count записів',
		'last' => "Останні :count видач:\n\n:items",
	],
	'admin' => [
		'import_help' => "Імпорт:\n1) надішліть /import + рядки\nабо\n2) надішліть TXT-файл документом.\n\nФормат рядка:\nPlatform | Game | login | pass | email | emailpass | backupEmails",
		'logs_help' => "Логи:\nКоманда: /log <account_id>\nПриклад: /log 12",
		'log_format' => "Формат: /log <account_id>\nПриклад: /log 12",
		'import_result' => "Імпорт:\nДодано: :added\nПропущено: :skipped\nПомилок: :errors",
	],
	'lang' => [
		'select' => 'Оберіть мову / Choose language / Выберите язык:',
		'set' => 'Мову встановлено: :lang',
		'ru' => '🇷🇺 Русский',
		'uk' => '🇺🇦 Українська',
		'en' => '🇬🇧 English',
	],
];


