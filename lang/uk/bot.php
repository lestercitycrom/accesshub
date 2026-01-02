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
		'adduser_help' => "Додати користувача:\nКоманда: /adduser <telegram_id> [role]\nПриклад: /adduser 123456789 operator\nПриклад: /adduser 123456789 admin",
		'adduser_invalid_role' => "Невірна роль. Використовуйте: admin або operator",
		'adduser_success' => "✅ Користувача додано!\nTelegram ID: :telegram_id\nРоль: :role",
		'adduser_help_short' => 'Використовуйте команду /adduser',
		'adduser_callback_help' => "Для додавання користувача використовуйте команду:\n/adduser TELEGRAM_ID [role]\n\nПриклад:\n/adduser 123456789 operator\n/adduser 123456789 admin",
		'role_admin' => 'Адміністратор',
		'role_operator' => 'Оператор',
		'users_list_title' => '👥 Управління користувачами',
		'add_user' => '➕ Додати',
		'delete_user' => '❌ Видалити :id',
		'refresh' => '🔄 Оновити',
		'back_to_users' => '◀️ Назад до списку',
		'user_not_found' => 'Користувача не знайдено',
		'cannot_delete_self' => 'Не можна видалити себе',
		'user_deleted' => 'Користувача видалено',
		'users_refreshed' => 'Список оновлено',
	],
	'lang' => [
		'select' => 'Оберіть мову / Choose language / Выберите язык:',
		'set' => 'Мову встановлено: :lang',
		'ru' => '🇷🇺 Русский',
		'uk' => '🇺🇦 Українська',
		'en' => '🇬🇧 English',
	],
];











