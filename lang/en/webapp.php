<?php

return [
	'tabs' => [
		'issue' => [
			'title' => 'Issue',
			'header' => [
				'title' => 'Issue',
				'subtitle' => 'Get account',
			],
			'fields' => [
				'order_id' => 'Order ID',
				'game' => 'Game',
				'platform' => 'Platform',
				'qty' => 'Quantity',
			],
		],
		'history' => [
			'title' => 'History',
			'header' => [
				'title' => 'History',
				'subtitle' => 'View issuances',
			],
			'fields' => [
				'order_id' => 'Order ID (optional)',
			],
		],
		'help' => [
			'title' => 'Help',
			'header' => [
				'title' => 'Help',
			],
			'content' => "Issue format:\n— Order ID\n— Game (Platform)\n\nWebApp sends data to bot, bot replies in chat.",
		],
		'admin_find' => [
			'title' => 'Admin: Search',
			'header' => [
				'title' => 'Search',
				'subtitle' => 'Find accounts',
			],
			'fields' => [
				'game' => 'Game (optional)',
				'platform' => 'Platform (optional)',
				'status' => 'Status: available/cooldown/disabled',
			],
		],
		'admin_export' => [
			'title' => 'Admin: Export',
			'content' => "Links:\n— /webapp/api/admin/export/accounts.csv\n— /webapp/api/admin/export/issuance_logs.csv",
		],
		'admin_logs' => [
			'title' => 'Admin: Logs',
			'header' => [
				'title' => 'Logs',
				'subtitle' => 'Issuance log search',
			],
			'fields' => [
				'account_id' => 'Account ID (optional)',
				'order_id' => 'Order ID (optional)',
				'operator_id' => 'Operator TG ID (optional)',
			],
		],
		'admin_stats' => [
			'title' => 'Admin: Stats',
			'header' => [
				'title' => 'Stats',
				'subtitle' => 'Current totals',
			],
		],
	],
	'ui' => [
		'submit' => 'Submit',
		'clear' => 'Clear',
		'result_placeholder' => 'Result will appear here.',
		'loading' => 'Loading...',
		'load_more' => 'Load more',
		'end_of_list' => 'End of list',
		'total' => 'Total: :count',
		'no_data' => 'No data',
		'page_of' => 'Page :page of :total',
		'role' => 'Role: :role',
		'tg_id' => 'TG: :id',
		'order' => 'Order: :id',
		'account_id' => 'Account ID: :id',
		'download_accounts' => 'Download accounts.csv',
		'download_logs' => 'Download issuance_logs.csv',
		'downloaded' => ':file downloaded',
		'no_access' => 'No access. Contact administrator to be added to the system.',
		'auth_error' => 'Authorization error. Reload WebApp.',
	],
	'errors' => [
		'validation_failed' => 'Please check the form fields.',
		'required' => 'The :attribute field is required.',
		'integer' => 'The :attribute must be an integer.',
		'min' => 'The :attribute must be at least :min.',
		'max' => 'The :attribute must be at most :max.',
		'regex' => 'The :attribute format is invalid.',
		'not_enough_accounts' => 'Not enough available accounts: available :available, needed :needed.',
		'server_error' => 'Server error. Please try again later.',
	],
];
