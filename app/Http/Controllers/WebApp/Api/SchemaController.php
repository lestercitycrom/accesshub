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
					'title' => 'Выдача',
					'roles' => ['operator', 'admin'],
					'header' => ['icon' => '🎮', 'title' => 'Выдача', 'subtitle' => 'Получить аккаунт'],
					'submit' => ['type' => 'bot', 'action' => 'issue'],
					'fields' => [
						['name' => 'order_id', 'label' => 'Order ID', 'placeholder' => 'Order ID', 'type' => 'text', 'required' => true, 'pattern' => '^\\d+$'],
						['name' => 'game', 'label' => 'Игра', 'placeholder' => 'Игра', 'type' => 'text', 'required' => true],
						['name' => 'platform', 'label' => 'Платформа', 'placeholder' => 'Платформа', 'type' => 'text', 'required' => true],
						['name' => 'qty', 'label' => 'Количество', 'placeholder' => 'Количество', 'type' => 'number', 'required' => false, 'min' => 1, 'max' => 20, 'default' => 1],
					],
				],
				[
					'id' => 'history',
					'title' => 'История',
					'roles' => ['operator', 'admin'],
					'header' => ['icon' => '🧾', 'title' => 'История', 'subtitle' => 'Просмотр выдач'],
					'submit' => ['type' => 'api', 'endpoint' => '/api/webapp/api/history'],
					'fields' => [
						['name' => 'order_id', 'label' => 'Order ID', 'placeholder' => 'Order ID (опционально)', 'type' => 'text', 'required' => false],
					],
				],
				[
					'id' => 'help',
					'title' => 'Помощь',
					'roles' => ['operator', 'admin'],
					'type' => 'static',
					'header' => ['icon' => 'ℹ️', 'title' => 'Помощь'],
					'content' => "Формат выдачи:\n— Order ID\n— Игра (Платформа)\n\nWebApp отправляет данные боту, бот отвечает в чат.",
				],
				[
					'id' => 'admin_find',
					'title' => 'Админ: Поиск',
					'roles' => ['admin'],
					'header' => ['icon' => '🔎', 'title' => 'Поиск', 'subtitle' => 'Найти аккаунты'],
					'submit' => ['type' => 'api', 'endpoint' => '/api/webapp/api/admin/find'],
					'fields' => [
						['name' => 'game', 'label' => 'Игра', 'placeholder' => 'Игра (опционально)', 'type' => 'text', 'required' => false],
						['name' => 'platform', 'label' => 'Платформа', 'placeholder' => 'Платформа (опционально)', 'type' => 'text', 'required' => false],
						['name' => 'status', 'label' => 'Статус', 'placeholder' => 'Статус: available/cooldown/disabled', 'type' => 'text', 'required' => false],
					],
				],
				[
					'id' => 'admin_export',
					'title' => 'Админ: Экспорт',
					'roles' => ['admin'],
					'type' => 'static',
					'content' => "Ссылки:\n— /webapp/api/admin/export/accounts.csv\n— /webapp/api/admin/export/issuance_logs.csv",
				],
			],
		];

		// Filter tabs by role
		$tabs = array_values(array_filter($schema['tabs'], static function (array $tab) use ($role): bool {
			$roles = $tab['roles'] ?? [];
			return in_array($role, $roles, true);
		}));

		return $this->ok([
			'role' => $role,
			'tabs' => $tabs,
		]);
	}
}
