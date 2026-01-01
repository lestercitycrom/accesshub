<?php

declare(strict_types=1);

namespace App\Services\AccessHub;

use App\Models\Account;
use App\Models\IssuanceLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class IssueAccountsService
{
	/**
	 * @return array<int, array{game_login: string, game_password: string}>
	 */
	public function issue(
		string $orderId,
		string $operatorTelegramId,
		string $game,
		string $platform,
		int $qty
	): array {
		$qty = max(1, $qty);
		$now = CarbonImmutable::now();
		$releaseDays = (int) config('accesshub.release_days', 14);

		$this->assertPlatformAllowed($platform);

		return DB::transaction(function () use ($orderId, $operatorTelegramId, $game, $platform, $qty, $now, $releaseDays): array {
			// Get already issued account IDs for this order
			$alreadyIssued = IssuanceLog::query()
				->where('order_id', $orderId)
				->pluck('account_id')
				->toArray();

			$accounts = Account::query()
				->where('is_active', true)
				->where('game', $game)
				->where('platform', $platform)
				->where('available_uses', '>', 0)
				->when(count($alreadyIssued) > 0, function ($query) use ($alreadyIssued) {
					$query->whereNotIn('id', $alreadyIssued);
				})
				->orderBy('id')
				->lockForUpdate()
				->limit($qty)
				->get();

			if ($accounts->count() < $qty) {
				throw new RuntimeException("Недостаточно свободных аккаунтов: доступно {$accounts->count()}, нужно {$qty}.");
			}

			$result = [];

			foreach ($accounts as $account) {
				$account->available_uses = (int) $account->available_uses - 1;

				if ((int) $account->available_uses <= 0) {
					$account->available_uses = 0;
					$account->next_release_at = $now->addDays($releaseDays);
				}

				$account->save();

				IssuanceLog::create([
					'order_id' => $orderId,
					'operator_telegram_id' => $operatorTelegramId,
					'account_id' => $account->id,
					'game' => $account->game,
					'platform' => $account->platform,
					'issued_at' => $now,
					'note' => null,
				]);

				$result[] = [
					'game_login' => (string) $account->game_login,
					'game_password' => (string) $account->game_password,
					'account_id' => $account->id,
				];
			}

			return $result;
		}, 3);
	}

	private function assertPlatformAllowed(string $platform): void
	{
		$enforce = (bool) config('accesshub.enforce_platform_list', false);

		if (!$enforce) {
			return;
		}

		$allowed = (array) config('accesshub.platforms', []);

		if (!in_array($platform, $allowed, true)) {
			throw new RuntimeException('Неизвестная платформа. Уточните список платформ у администратора.');
		}
	}
}










