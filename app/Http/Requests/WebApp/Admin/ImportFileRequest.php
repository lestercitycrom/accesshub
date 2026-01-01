<?php

declare(strict_types=1);

namespace App\Http\Requests\WebApp\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class ImportFileRequest extends FormRequest
{
	public function rules(): array
	{
		return [
			'file' => [
				'required',
				'file',
				'max:10240', // 10MB
				'mimes:txt,csv,xlsx',
			],
		];
	}

	public function messages(): array
	{
		return [
			'required' => __('webapp.errors.required'),
			'file' => __('webapp.errors.file'),
			'max' => __('webapp.errors.max'),
			'mimes' => __('webapp.errors.mimes'),
		];
	}

	public function attributes(): array
	{
		return [
			'file' => __('webapp.tabs.admin_import.fields.file'),
		];
	}
}








