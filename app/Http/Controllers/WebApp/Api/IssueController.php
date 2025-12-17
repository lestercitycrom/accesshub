<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebApp\Api;

use App\Http\Controllers\WebApp\Api\Concerns\JsonResponds;
use App\Models\TelegramUser;
use App\Services\AccessHub\IssueAccountsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

final class IssueController extends Controller
{
	use JsonResponds;

	public function __construct(
		private readonly IssueAccountsService $issuer
	) {
	}

	public function __invoke(Request $request): JsonResponse
	{
		/** @var TelegramUser|null $tgUser */
		$tgUser = $request->attributes->get('telegram_user');
		$role = $tgUser?->role?->value ?? 'operator';
		if (!in_array($role, ['operator', 'admin'], true)) {
			return $this->forbidden('no access');
		}

		$validator = Validator::make(
			$request->all(),
			[
			'order_id' => ['required', 'string', 'regex:/^\d+$/'],
			'game' => ['required', 'string', 'min:1', 'max:255'],
			'platform' => ['required', 'string', 'min:1', 'max:255'],
			'qty' => ['nullable', 'integer', 'min:1', 'max:20'],
			],
			[
				'required' => __('webapp.errors.required'),
				'integer' => __('webapp.errors.integer'),
				'min' => __('webapp.errors.min'),
				'max' => __('webapp.errors.max'),
				'regex' => __('webapp.errors.regex'),
			],
			[
				'order_id' => __('webapp.tabs.issue.fields.order_id'),
				'game' => __('webapp.tabs.issue.fields.game'),
				'platform' => __('webapp.tabs.issue.fields.platform'),
				'qty' => __('webapp.tabs.issue.fields.qty'),
			]
		);

		if ($validator->fails()) {
			return $this->validationError(__('webapp.errors.validation_failed'), $validator->errors()->toArray());
		}

		$data = $validator->validated();

		$orderId = (string) $data['order_id'];
		$game = (string) $data['game'];
		$platform = (string) $data['platform'];
		$qty = isset($data['qty']) ? (int) $data['qty'] : 1;

		$telegramId = (string) $request->attributes->get('telegram_id');

		try {
			$items = $this->issuer->issue($orderId, $telegramId, $game, $platform, $qty);

			return $this->ok([
				'receipt' => [
					'order_id' => $orderId,
					'game' => $game,
					'platform' => $platform,
					'qty' => count($items),
				],
				'items' => $items,
			]);
		} catch (RuntimeException $e) {
			$translated = $this->translateRuntimeIssueError($e->getMessage());

			return response()->json([
				'ok' => false,
				'error' => [
					'code' => 'CONFLICT',
					'message' => $translated,
				],
			], 409);
		} catch (Throwable) {
			return response()->json([
				'ok' => false,
				'error' => [
					'code' => 'SERVER_ERROR',
					'message' => __('webapp.errors.server_error'),
				],
			], 500);
		}
	}

	private function translateRuntimeIssueError(string $message): string
	{
		$message = trim($message);

		if (preg_match('/доступно\s+(\d+)\s*,\s*нужно\s+(\d+)/iu', $message, $m)) {
			return __('webapp.errors.not_enough_accounts', [
				'available' => (int) $m[1],
				'needed' => (int) $m[2],
			]);
		}

		return $message;
	}
}
