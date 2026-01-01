<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebApp\Api\Admin;

use App\Http\Controllers\WebApp\Api\Admin\Concerns\RequiresAdmin;
use App\Http\Controllers\WebApp\Api\Concerns\JsonResponds;
use App\Http\Requests\WebApp\Admin\ImportFileRequest;
use App\Services\AccessHub\Admin\BulkImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class ImportFileController extends Controller
{
	use JsonResponds;
	use RequiresAdmin;

	public function __construct(
		private readonly BulkImportService $importer
	) {
	}

	public function __invoke(ImportFileRequest $request): JsonResponse
	{
		if (!$this->isAdmin($request)) {
			return $this->forbidden('admin only');
		}

		$file = $request->file('file');
		if ($file === null) {
			return $this->validationError(__('webapp.errors.validation_failed'), ['file' => [__('webapp.errors.required')]]);
		}

		$extension = strtolower((string) $file->getClientOriginalExtension());
		$filePath = (string) $file->getRealPath();

		if (!in_array($extension, ['txt', 'csv', 'xlsx'], true)) {
			return response()->json([
				'ok' => false,
				'error' => [
					'code' => 'INVALID_FILE_TYPE',
					'message' => __('webapp.errors.invalid_file_type'),
				],
			], 422);
		}

		$result = $this->importer->importFromFile($filePath, $extension);

		return $this->ok([
			'added' => (int) ($result['added'] ?? 0),
			'skipped' => (int) ($result['skipped'] ?? 0),
			'errors' => is_array($result['errors'] ?? null) ? $result['errors'] : [],
		], 'Import finished');
	}
}








