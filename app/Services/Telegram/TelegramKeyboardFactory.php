<?php

declare(strict_types=1);

namespace App\Services\Telegram;

final class TelegramKeyboardFactory
{
	/**
	 * Reply keyboard (нижняя клавиатура).
	 *
	 * @param array<int, array<int, string>> $rows
	 * @return array<string, mixed>
	 */
	public function reply(array $rows, bool $resize = true, bool $oneTime = false): array
	{
		return [
			'keyboard' => array_map(static fn (array $row): array => array_map(
				static fn (string $label): array => ['text' => $label],
				$row
			), $rows),
			'resize_keyboard' => $resize,
			'one_time_keyboard' => $oneTime,
		];
	}

	/**
	 * Inline keyboard (кнопки внутри сообщения).
	 *
	 * @param array<int, array<int, array{text: string, callback_data: string}>> $rows
	 * @return array<string, mixed>
	 */
	public function inline(array $rows): array
	{
		return [
			'inline_keyboard' => $rows,
		];
	}
}

