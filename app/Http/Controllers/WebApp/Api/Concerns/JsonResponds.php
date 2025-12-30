<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebApp\Api\Concerns;

use Illuminate\Http\JsonResponse;

trait JsonResponds
{
	/**
	 * @param array<string, mixed> $data
	 */
	protected function ok(array $data = [], ?string $message = null): JsonResponse
	{
		$payload = [
			'ok' => true,
			'data' => $data,
		];

		if ($message !== null) {
			$payload['message'] = $message;
		}

		return response()->json($payload);
	}

	/**
	 * @param array<string, mixed> $fields
	 */
	protected function validationError(string $message, array $fields, int $status = 422): JsonResponse
	{
		return response()->json([
			'ok' => false,
			'error' => [
				'code' => 'VALIDATION_ERROR',
				'message' => $message,
				'fields' => $fields,
			],
		], $status);
	}

	protected function forbidden(string $message = 'forbidden'): JsonResponse
	{
		return response()->json([
			'ok' => false,
			'error' => [
				'code' => 'FORBIDDEN',
				'message' => $message,
			],
		], 403);
	}
}







