<?php

declare(strict_types=1);

namespace App\Http\Controllers\WebApp;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

final class WebAppController extends Controller
{
	public function __invoke(): View
	{
		return view('webapp.index');
	}
}







