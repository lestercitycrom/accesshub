<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebApp\Api\Admin;

use App\Http\Controllers\WebApp\Api\Admin\Concerns\RequiresAdmin;
use App\Http\Controllers\WebApp\Api\Concerns\JsonResponds;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

final class AccountsController extends Controller
{
	use JsonResponds;
	use RequiresAdmin;

	public function store(Request $request): JsonResponse
	{
		if (!$this->isAdmin($request)) {
			return $this->forbidden('admin only');
		}

		$validator = Validator::make($request->all(), [
			'platform' => ['required', 'string', 'max:50'],
			'game' => ['required', 'string', 'max:255'],
			'game_login' => ['required', 'string', 'max:255'],
			'game_password' => ['required', 'string', 'max:255'],
			'max_uses' => ['nullable', 'integer', 'min:1', 'max:100'],
		]);

		if ($validator->fails()) {
			return $this->validationError('Validation failed', $validator->errors()->toArray());
		}

		$data = $validator->validated();

		$account = new Account();
		$account->platform = $data['platform'];
		$account->game = $data['game'];
		$account->game_login = $data['game_login'];
		$account->game_password = $data['game_password'];

		$account->max_uses = (int) ($data['max_uses'] ?? 3);
		$account->available_uses = $account->max_uses;
		$account->is_active = true;
		$account->next_release_at = null;

		$account->save();

		return $this->ok([
			'id' => $account->id,
		], 'Account created');
	}
}
