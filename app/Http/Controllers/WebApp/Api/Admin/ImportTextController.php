<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebApp\Api\Admin;

use App\Http\Controllers\WebApp\Api\Admin\Concerns\RequiresAdmin;
use App\Http\Controllers\WebApp\Api\Concerns\JsonResponds;
use App\Services\AccessHub\Admin\BulkImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

final class ImportTextController extends Controller
{
	use JsonResponds;
	use RequiresAdmin;

	public function __construct(
		private readonly BulkImportService $importer
	) {
	}

	public function __invoke(Request $request): JsonResponse
	{
		if (!$this->isAdmin($request)) {
			return $this->forbidden('admin only');
		}

		$validator = Validator::make($request->all(), [
			'text' => ['required', 'string', 'max:2000000'],
		]);

		if ($validator->fails()) {
			return $this->validationError('Validation failed', $validator->errors()->toArray());
		}

		$text = (string) $validator->validated()['text'];

		$stat = $this->importer->importFromText($text);

		return $this->ok([
			'added' => (int) ($stat['added'] ?? 0),
			'skipped' => (int) ($stat['skipped'] ?? 0),
			'errors' => (int) ($stat['errors'] ?? 0),
		], 'Import finished');
	}
}
