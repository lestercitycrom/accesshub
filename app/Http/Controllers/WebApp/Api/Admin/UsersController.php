<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebApp\Api\Admin;

use App\Http\Controllers\WebApp\Api\Admin\Concerns\RequiresAdmin;
use App\Http\Controllers\WebApp\Api\Concerns\JsonResponds;
use App\Models\TelegramUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

final class UsersController extends Controller
{
	use JsonResponds;
	use RequiresAdmin;

	public function index(Request $request): JsonResponse
	{
		if (!$this->isAdmin($request)) {
			return $this->forbidden('admin only');
		}

		$items = TelegramUser::query()
			->orderByDesc('id')
			->get(['id', 'telegram_id', 'role', 'is_active', 'created_at'])
			->map(static function (TelegramUser $u): array {
				$role = $u->role?->value ?? (string) $u->role;

				return [
					'id' => $u->id,
					'telegram_id' => $u->telegram_id,
					'role' => $role,
					'is_active' => (bool) $u->is_active,
					'created_at' => $u->created_at?->toISOString(),
				];
			})
			->all();

		return $this->ok(['items' => $items]);
	}

	public function upsert(Request $request): JsonResponse
	{
		if (!$this->isAdmin($request)) {
			return $this->forbidden('admin only');
		}

		$validator = Validator::make($request->all(), [
			'telegram_id' => ['required', 'string', 'max:32'],
			'role' => ['required', 'in:admin,operator'],
			'is_active' => ['required', 'boolean'],
		]);

		if ($validator->fails()) {
			return $this->validationError('Validation failed', $validator->errors()->toArray());
		}

		$data = $validator->validated();

		$user = TelegramUser::query()
			->where('telegram_id', $data['telegram_id'])
			->first();

		if ($user === null) {
			$user = new TelegramUser();
			$user->telegram_id = $data['telegram_id'];
		}

		$user->role = $data['role'];
		$user->is_active = (bool) $data['is_active'];
		$user->save();

		return $this->ok([
			'id' => $user->id,
		], 'Saved');
	}
}









