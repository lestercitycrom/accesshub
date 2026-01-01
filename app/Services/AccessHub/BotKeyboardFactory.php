<?php

declare(strict_types=1);

namespace App\Services\AccessHub;

use App\Enums\TelegramUserRole;
use App\Services\Telegram\TelegramKeyboardFactory;

final class BotKeyboardFactory
{
	public function __construct(
		private readonly TelegramKeyboardFactory $kb
	) {
	}

	/**
	 * Operator menu (reply keyboard).
	 *
	 * @return array<string, mixed>
	 */
	public function operatorMenu(): array
	{
		$rows = [
			[__('bot.menu.issue'), __('bot.menu.history')],
			[__('bot.menu.help'), __('bot.menu.lang')],
		];

		return $this->kb->reply($rows);
	}

	/**
	 * Admin menu (reply keyboard).
	 *
	 * @return array<string, mixed>
	 */
	public function adminMenu(): array
	{
		$rows = [
			[__('bot.menu.issue'), __('bot.menu.history')],
			[__('bot.menu.add'), __('bot.menu.import')],
			[__('bot.menu.stats'), __('bot.menu.logs')],
			[__('bot.menu.help')],
		];

		return $this->kb->reply($rows);
	}

	/**
	 * Language selection menu (reply keyboard).
	 *
	 * @return array<string, mixed>
	 */
	public function languageMenu(): array
	{
		$rows = [
			[__('bot.lang.ru'), __('bot.lang.uk')],
			[__('bot.lang.en')],
		];

		return $this->kb->reply($rows, true, true);
	}

	/**
	 * Action mapping: action constant -> localized label.
	 * Used for reverse lookup when user clicks reply button.
	 *
	 * @return array<string, string>
	 */
	public function actionMap(): array
	{
		return [
			'ISSUE' => __('bot.menu.issue'),
			'HISTORY' => __('bot.menu.history'),
			'HELP' => __('bot.menu.help'),
			'ADD' => __('bot.menu.add'),
			'IMPORT' => __('bot.menu.import'),
			'STATS' => __('bot.menu.stats'),
			'LOGS' => __('bot.menu.logs'),
			'FIND' => __('bot.menu.find'),
			'EXPORT' => __('bot.menu.export'),
			'USERS' => __('bot.menu.users'),
			'REFRESH' => __('bot.menu.refresh'),
			'MENU' => __('bot.menu.menu'),
			'LANG' => __('bot.menu.lang'),
		];
	}

	/**
	 * Find action by localized label (reverse lookup).
	 */
	public function findActionByLabel(string $text): ?string
	{
		$map = $this->actionMap();
		$action = array_search($text, $map, true);
		return $action !== false ? $action : null;
	}

	/**
	 * Get menu for role.
	 *
	 * @return array<string, mixed>
	 */
	public function menuForRole(?TelegramUserRole $role): array
	{
		if ($role === TelegramUserRole::Admin) {
			return $this->adminMenu();
		}

		return $this->operatorMenu();
	}
}









