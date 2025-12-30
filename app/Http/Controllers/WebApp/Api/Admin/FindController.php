<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebApp\Api\Admin;

use App\Http\Controllers\WebApp\Api\Admin\Concerns\RequiresAdmin;
use App\Http\Controllers\WebApp\Api\Concerns\JsonResponds;
use App\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class FindController extends Controller
{
	use JsonResponds;
	use RequiresAdmin;

	public function __invoke(Request $request): JsonResponse
	{
		if (!$this->isAdmin($request)) {
			return $this->forbidden('admin only');
		}

		$page = max(1, (int) $request->query('page', 1));
		$perPage = (int) $request->query('per_page', 20);
		$perPage = min(max($perPage, 1), 100);

		$game = $this->q($request->query('game'));
		$platform = $this->q($request->query('platform'));
		$status = $this->q($request->query('status'));

		$now = CarbonImmutable::now();

		$query = Account::query();

		if ($game !== '') {
			$query->where('game', 'like', '%' . $game . '%');
		}

		if ($platform !== '') {
			$query->where('platform', $platform);
		}

		if ($status !== '') {
			if ($status === 'disabled') {
				$query->where('is_active', false);
			} elseif ($status === 'available') {
				$query->where('is_active', true)->where('available_uses', '>', 0);
			} elseif ($status === 'cooldown') {
				$query->where('is_active', true)
					->where('available_uses', '=', 0)
					->whereNotNull('next_release_at')
					->where('next_release_at', '>', $now);
			}
		}

		$total = (clone $query)->count();

		$items = $query
			->orderByDesc('id')
			->forPage($page, $perPage)
			->get(['id', 'platform', 'game', 'game_login', 'available_uses', 'max_uses', 'next_release_at', 'is_active'])
			->map(static function (Account $a) use ($now): array {
				$status = 'available';

				if (!$a->is_active) {
					$status = 'disabled';
				} elseif ((int) $a->available_uses <= 0 && $a->next_release_at !== null && $a->next_release_at->gt($now)) {
					$status = 'cooldown';
				} elseif ((int) $a->available_uses <= 0) {
					$status = 'cooldown';
				}

				return [
					'id' => $a->id,
					'platform' => $a->platform,
					'game' => $a->game,
					'game_login' => $a->game_login,
					'available_uses' => (int) $a->available_uses,
					'max_uses' => (int) $a->max_uses,
					'next_release_at' => $a->next_release_at?->toISOString(),
					'is_active' => (bool) $a->is_active,
					'status' => $status,
				];
			})
			->all();

		return $this->ok([
			'items' => $items,
			'page' => $page,
			'per_page' => $perPage,
			'total' => $total,
		]);
	}

	private function q(mixed $v): string
	{
		return is_string($v) ? trim($v) : '';
	}
}







