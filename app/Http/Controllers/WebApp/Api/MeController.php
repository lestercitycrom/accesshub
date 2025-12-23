<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebApp\Api;

use App\Http\Controllers\WebApp\Api\Concerns\JsonResponds;
use App\Models\TelegramUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class MeController extends Controller
{
	use JsonResponds;

	public function __invoke(Request $request): JsonResponse
	{
		$telegramId = (string) $request->attributes->get('telegram_id');

		/** @var TelegramUser|null $tgUser */
		$tgUser = $request->attributes->get('telegram_user');

		$role = $tgUser?->role?->value ?? 'operator';

		return $this->ok([
			'telegram_id' => $telegramId,
			'role' => $role,
		]);
	}
}


