<?php

declare(strict_types=1);

namespace App\Services\AccessHub\Admin;

use App\Models\Account;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class AddAccountWizard
{
	private const KEY_PREFIX = 'accesshub:add_wizard:';

	/**
	 * @return array{state: string, prompt: string}
	 */
	public function start(string $telegramId): array
	{
		$this->put($telegramId, [
			'step' => 'platform',
			'data' => [],
		]);

		return [
			'state' => 'started',
			'prompt' => $this->prompt('platform'),
		];
	}

	public function cancel(string $telegramId): void
	{
		Cache::forget($this->key($telegramId));
	}

	public function isActive(string $telegramId): bool
	{
		return Cache::has($this->key($telegramId));
	}

	/**
	 * @return array{done: bool, message: string}
	 */
	public function handleInput(string $telegramId, string $text): array
	{
		$ctx = $this->get($telegramId);
		if ($ctx === null) {
			return [
				'done' => true,
				'message' => 'Мастер не запущен. Используйте /add.',
			];
		}

		$text = trim($text);
		if ($text === '') {
			return [
				'done' => false,
				'message' => 'Пустое значение. ' . $this->prompt((string) $ctx['step']),
			];
		}

		$step = (string) $ctx['step'];
		$data = (array) $ctx['data'];

		// Special keywords
		if (Str::lower($text) === 'skip' && in_array($step, ['email_login', 'email_password', 'codes_receiver_emails', 'platform_meta'], true)) {
			$ctx['step'] = $this->nextStep($step);
			$this->put($telegramId, $ctx);

			return [
				'done' => false,
				'message' => $this->prompt((string) $ctx['step']),
			];
		}

		// Assign input
		$data[$step] = $this->normalize($step, $text);

		$next = $this->nextStep($step);

		// Finish?
		if ($next === 'finish') {
			$account = $this->createAccount($data);
			$this->cancel($telegramId);

			return [
				'done' => true,
				'message' => "Готово. Аккаунт добавлен.\nID: {$account->id}\n{$account->game} ({$account->platform})\nLogin: {$account->game_login}",
			];
		}

		$ctx['data'] = $data;
		$ctx['step'] = $next;
		$this->put($telegramId, $ctx);

		return [
			'done' => false,
			'message' => $this->prompt($next),
		];
	}

	private function createAccount(array $data): Account
	{
		$maxUses = (int) config('accesshub.max_uses_default', 3);

		$emailLogin = $data['email_login'] ?? null;
		$emailPassword = $data['email_password'] ?? null;

		return Account::create([
			'platform' => (string) $data['platform'],
			'game' => (string) $data['game'],
			'game_login' => (string) $data['game_login'],
			'game_password' => (string) $data['game_password'],

			'email_login' => $emailLogin !== null ? (string) $emailLogin : null,
			'email_password' => $emailPassword !== null ? (string) $emailPassword : null,

			'codes_receiver_emails' => $data['codes_receiver_emails'] ?? null,
			'platform_meta' => $data['platform_meta'] ?? null,

			'max_uses' => $maxUses,
			'available_uses' => $maxUses,
			'next_release_at' => null,
			'is_active' => true,
		]);
	}

	private function normalize(string $step, string $text): mixed
	{
		if ($step === 'codes_receiver_emails') {
			// Input: mail1@mail.com, mail2@mail.com OR "-"
			if ($text === '-' || Str::lower($text) === 'none') {
				return null;
			}

			$items = array_values(array_filter(array_map(
				static fn (string $v): string => trim($v),
				explode(',', $text)
			), static fn (string $v): bool => $v !== ''));

			return $items === [] ? null : $items;
		}

		if ($step === 'platform_meta') {
			// Simple: store raw text for now
			if ($text === '-' || Str::lower($text) === 'none') {
				return null;
			}

			return ['raw' => $text];
		}

		return $text;
	}

	private function nextStep(string $step): string
	{
		return match ($step) {
			'platform' => 'game',
			'game' => 'game_login',
			'game_login' => 'game_password',
			'game_password' => 'email_login',
			'email_login' => 'email_password',
			'email_password' => 'codes_receiver_emails',
			'codes_receiver_emails' => 'platform_meta',
			'platform_meta' => 'finish',
			default => 'finish',
		};
	}

	private function prompt(string $step): string
	{
		return match ($step) {
			'platform' => "Введите платформу (пока тестово, позже будет строгий список).\nПример: Xbox X\n\n/abort — отмена",
			'game' => "Введите название игры.\nПример: Minecraft\n\n/abort — отмена",
			'game_login' => "Введите логин игрового аккаунта.\n\n/abort — отмена",
			'game_password' => "Введите пароль игрового аккаунта.\n\n/abort — отмена",
			'email_login' => "Введите логин почты (или 'skip').\n\n/abort — отмена",
			'email_password' => "Введите пароль почты (или 'skip').\n\n/abort — отмена",
			'codes_receiver_emails' => "Введите почты для кодов через запятую (или '-' / 'skip').\nПример: backup@mail.com, backup2@mail.com\n\n/abort — отмена",
			'platform_meta' => "Введите доп. данные платформы (или '-' / 'skip').\nПример: Steam Guard: ...\n\n/abort — отмена",
			default => "Ожидаю данные.\n\n/abort — отмена",
		};
	}

	private function key(string $telegramId): string
	{
		return self::KEY_PREFIX . $telegramId;
	}

	private function get(string $telegramId): ?array
	{
		/** @var array|null $ctx */
		$ctx = Cache::get($this->key($telegramId));

		return is_array($ctx) ? $ctx : null;
	}

	private function put(string $telegramId, array $ctx): void
	{
		Cache::put($this->key($telegramId), $ctx, now()->addMinutes(30));
	}
}

