<?php

return [
	'menu' => [
		'menu' => '📋 Menu',
		'issue' => '🎮 Get account',
		'history' => '🧾 My history',
		'help' => 'ℹ️ Help',
		'add' => '➕ Add',
		'import' => '📥 Import',
		'stats' => '📊 Statistics',
		'logs' => '🧾 Logs',
		'find' => '🔎 Search',
		'export' => '📤 Export',
		'users' => '👥 Users',
		'refresh' => '🔄 Refresh',
		'lang' => '🌐 Language',
	],
	'common' => [
		'back' => '◀️ Back',
		'cancel' => '❌ Cancel',
		'ok' => '✅ OK',
	],
	'replies' => [
		'welcome' => "Welcome to AccessHub!\n\nUse menu buttons for navigation.",
		'access_denied' => 'No access. Contact administrator.',
		'error_generic' => 'An error occurred. Please try again later.',
		'no_permission' => 'No permission.',
		'command_cancelled' => 'OK, /add wizard cancelled.',
		'file_read_error' => 'Failed to read file.',
		'file_path_error' => 'Failed to get file path.',
		'file_empty_error' => 'File is empty or failed to download.',
		'import_complete' => 'File import completed.',
	],
	'issue' => [
		'success' => "✅ Account issued!\n\nOrder: :order\nGame: :game\nPlatform: :platform\nAccount ID: :account_id",
		'not_found' => '❌ Account not found for :game (:platform)',
		'help' => "Issue format:\n— Order ID\n— Game (Platform)\n\nExample:\n2446303\nMinecraft (Xbox X)",
		'invalid_format' => "Invalid format.\nNeed 2 lines:\n1) Order number\n2) Game (Platform)\n\nExample:\n2446303\nMinecraft (Xbox X)",
	],
	'history' => [
		'empty' => 'History is empty.',
		'items' => '{0} No records|{1} :count record|[2,*] :count records',
		'last' => "Last :count issuances:\n\n:items",
	],
	'admin' => [
		'import_help' => "Import:\n1) send /import + lines\nor\n2) send TXT file as document.\n\nLine format:\nPlatform | Game | login | pass | email | emailpass | backupEmails",
		'logs_help' => "Logs:\nCommand: /log <account_id>\nExample: /log 12",
		'log_format' => "Format: /log <account_id>\nExample: /log 12",
		'import_result' => "Import:\nAdded: :added\nSkipped: :skipped\nErrors: :errors",
		'adduser_help' => "Add user:\nCommand: /adduser <telegram_id> [role]\nExample: /adduser 123456789 operator\nExample: /adduser 123456789 admin",
		'adduser_invalid_role' => "Invalid role. Use: admin or operator",
		'adduser_success' => "✅ User added!\nTelegram ID: :telegram_id\nRole: :role",
		'adduser_help_short' => 'Use /adduser command',
		'adduser_callback_help' => "To add a user, use the command:\n/adduser TELEGRAM_ID [role]\n\nExample:\n/adduser 123456789 operator\n/adduser 123456789 admin",
		'role_admin' => 'Administrator',
		'role_operator' => 'Operator',
		'users_list_title' => '👥 User Management',
		'add_user' => '➕ Add',
		'delete_user' => '❌ Delete :id',
		'refresh' => '🔄 Refresh',
		'back_to_users' => '◀️ Back to list',
		'user_not_found' => 'User not found',
		'cannot_delete_self' => 'Cannot delete yourself',
		'user_deleted' => 'User deleted',
		'users_refreshed' => 'List refreshed',
	],
	'lang' => [
		'select' => 'Choose language / Оберіть мову / Выберите язык:',
		'set' => 'Language set: :lang',
		'ru' => '🇷🇺 Русский',
		'uk' => '🇺🇦 Українська',
		'en' => '🇬🇧 English',
	],
];











