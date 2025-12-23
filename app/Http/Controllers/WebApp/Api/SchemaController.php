<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebApp\Api;

use App\Http\Controllers\WebApp\Api\Concerns\JsonResponds;
use App\Models\TelegramUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

	final class SchemaController extends Controller
{
	use JsonResponds;

	public function __invoke(Request $request): JsonResponse
	{
		/** @var TelegramUser|null $tgUser */
		$tgUser = $request->attributes->get('telegram_user');

		$role = $tgUser?->role?->value ?? 'operator';

		$schema = [
			'tabs' => [
				[
					'id' => 'issue',
					'title' => __('webapp.tabs.issue.title'),
					'roles' => ['operator', 'admin'],
					'header' => [
						'icon' => '🎮',
						'title' => __('webapp.tabs.issue.header.title'),
						'subtitle' => __('webapp.tabs.issue.header.subtitle'),
					],
					'submit' => [
						'type' => 'api',
						'endpoint' => '/api/webapp/api/issue',
						'method' => 'POST',
						// Fallback only (UI should not use it as main path)
						'fallback' => ['type' => 'bot_action', 'action' => 'issue'],
					],
					'fields' => [
						['name' => 'order_id', 'label' => __('webapp.tabs.issue.fields.order_id'), 'placeholder' => __('webapp.tabs.issue.fields.order_id'), 'type' => 'text', 'required' => true, 'pattern' => '^\\d+$'],
						['name' => 'game', 'label' => __('webapp.tabs.issue.fields.game'), 'placeholder' => __('webapp.tabs.issue.fields.game'), 'type' => 'text', 'required' => true],
						['name' => 'platform', 'label' => __('webapp.tabs.issue.fields.platform'), 'placeholder' => __('webapp.tabs.issue.fields.platform'), 'type' => 'text', 'required' => true],
						['name' => 'qty', 'label' => __('webapp.tabs.issue.fields.qty'), 'placeholder' => __('webapp.tabs.issue.fields.qty'), 'type' => 'number', 'required' => false, 'min' => 1, 'max' => 20, 'default' => 1],
					],
				],
				[
					'id' => 'history',
					'title' => __('webapp.tabs.history.title'),
					'roles' => ['operator', 'admin'],
					'header' => [
						'icon' => '🧾',
						'title' => __('webapp.tabs.history.header.title'),
						'subtitle' => __('webapp.tabs.history.header.subtitle'),
					],
					'submit' => ['type' => 'api', 'endpoint' => '/api/webapp/api/history'],
					'fields' => [
						['name' => 'order_id', 'label' => 'Order ID', 'placeholder' => __('webapp.tabs.history.fields.order_id'), 'type' => 'text', 'required' => false],
					],
				],
				[
					'id' => 'help',
					'title' => __('webapp.tabs.help.title'),
					'roles' => ['operator', 'admin'],
					'type' => 'static',
					'header' => ['icon' => 'ℹ️', 'title' => __('webapp.tabs.help.header.title')],
					'content' => __('webapp.tabs.help.content'),
				],
				[
					'id' => 'admin_find',
					'title' => __('webapp.tabs.admin_find.title'),
					'roles' => ['admin'],
					'header' => [
						'icon' => '🔎',
						'title' => __('webapp.tabs.admin_find.header.title'),
						'subtitle' => __('webapp.tabs.admin_find.header.subtitle'),
					],
					'submit' => ['type' => 'api', 'endpoint' => '/api/webapp/api/admin/find'],
					'fields' => [
						['name' => 'game', 'label' => __('webapp.tabs.issue.fields.game'), 'placeholder' => __('webapp.tabs.admin_find.fields.game'), 'type' => 'text', 'required' => false],
						['name' => 'platform', 'label' => __('webapp.tabs.issue.fields.platform'), 'placeholder' => __('webapp.tabs.admin_find.fields.platform'), 'type' => 'text', 'required' => false],
						['name' => 'status', 'label' => __('webapp.tabs.admin_find.fields.status'), 'placeholder' => __('webapp.tabs.admin_find.fields.status'), 'type' => 'text', 'required' => false],
					],
				],
				[
					'id' => 'admin_logs',
					'title' => __('webapp.tabs.admin_logs.title'),
					'roles' => ['admin'],
					'header' => [
						'icon' => '🧾',
						'title' => __('webapp.tabs.admin_logs.header.title'),
						'subtitle' => __('webapp.tabs.admin_logs.header.subtitle'),
					],
					'submit' => ['type' => 'api', 'endpoint' => '/api/webapp/api/admin/logs'],
					'fields' => [
						['name' => 'account_id', 'label' => __('webapp.tabs.admin_logs.fields.account_id'), 'placeholder' => __('webapp.tabs.admin_logs.fields.account_id'), 'type' => 'text', 'required' => false],
						['name' => 'order_id', 'label' => __('webapp.tabs.admin_logs.fields.order_id'), 'placeholder' => __('webapp.tabs.admin_logs.fields.order_id'), 'type' => 'text', 'required' => false],
						['name' => 'operator_id', 'label' => __('webapp.tabs.admin_logs.fields.operator_id'), 'placeholder' => __('webapp.tabs.admin_logs.fields.operator_id'), 'type' => 'text', 'required' => false],
					],
				],
				[
					'id' => 'admin_stats',
					'title' => __('webapp.tabs.admin_stats.title'),
					'roles' => ['admin'],
					'header' => [
						'icon' => '📊',
						'title' => __('webapp.tabs.admin_stats.header.title'),
						'subtitle' => __('webapp.tabs.admin_stats.header.subtitle'),
					],
					'submit' => ['type' => 'api', 'endpoint' => '/api/webapp/api/admin/stats'],
					'fields' => [],
				],
				[
					'id' => 'admin_import',
					'title' => __('webapp.tabs.admin_import.title'),
					'roles' => ['admin'],
					'header' => [
						'icon' => '📥',
						'title' => __('webapp.tabs.admin_import.header.title'),
						'subtitle' => __('webapp.tabs.admin_import.header.subtitle'),
					],
					'submit' => [
						'type' => 'api',
						'endpoint' => '/api/webapp/api/admin/import/text',
						'method' => 'POST',
					],
					'fields' => [
						['name' => 'text', 'label' => __('webapp.tabs.admin_import.fields.text'), 'placeholder' => __('webapp.tabs.admin_import.fields.text'), 'type' => 'textarea', 'required' => true],
					],
				],
				[
					'id' => 'admin_export',
					'title' => __('webapp.tabs.admin_export.title'),
					'roles' => ['admin'],
					'type' => 'static',
					'content' => __('webapp.tabs.admin_export.content'),
				],
			],
		];

		// Filter tabs by role
		$tabs = array_values(array_filter($schema['tabs'], static function (array $tab) use ($role): bool {
			$roles = $tab['roles'] ?? [];
			return in_array($role, $roles, true);
		}));

		// Include translations for frontend
		$translations = [
			'submit' => __('webapp.ui.submit'),
			'clear' => __('webapp.ui.clear'),
			'result_placeholder' => __('webapp.ui.result_placeholder'),
			'loading' => __('webapp.ui.loading'),
			'load_more' => __('webapp.ui.load_more'),
			'end_of_list' => __('webapp.ui.end_of_list'),
			'total' => __('webapp.ui.total'),
			'no_data' => __('webapp.ui.no_data'),
			'page_of' => __('webapp.ui.page_of'),
			'role' => __('webapp.ui.role'),
			'tg_id' => __('webapp.ui.tg_id'),
			'order' => __('webapp.ui.order'),
			'account_id' => __('webapp.ui.account_id'),
			'download_accounts' => __('webapp.ui.download_accounts'),
			'download_logs' => __('webapp.ui.download_logs'),
			'downloaded' => __('webapp.ui.downloaded'),
			'no_access' => __('webapp.ui.no_access'),
			'auth_error' => __('webapp.ui.auth_error'),
		];

		return $this->ok([
			'role' => $role,
			'tabs' => $tabs,
			'locale' => app()->getLocale(),
			'translations' => $translations,
		]);
	}
}


