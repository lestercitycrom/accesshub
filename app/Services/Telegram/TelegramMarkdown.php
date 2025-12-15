<?php

declare(strict_types=1);

namespace App\Services\Telegram;

final class TelegramMarkdown
{
	public function codeBlock(string $text): string
	{
		return "```\n" . $this->escapeCode($text) . "\n```";
	}

	/**
	 * In code blocks Telegram is less strict, but backticks may break block.
	 */
	private function escapeCode(string $text): string
	{
		$text = str_replace(["\r\n", "\r"], "\n", $text);

		// Prevent closing the block (zero-width space after backticks)
		$zeroWidthSpace = json_decode('"\u200B"');
		return str_replace('```', '``' . $zeroWidthSpace . '`', $text);
	}
}
