<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\KodiService;
use Illuminate\Http\Request;

class AlexaController extends Controller
{
	private $kodi;

	public function __construct(KodiService $kodi)
	{
		$this->kodi = $kodi;
	}

	public function test(Request $request)
	{
		return json_encode($request, true);
	}
}
