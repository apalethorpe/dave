<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\KodiService;
use Illuminate\Http\Request;

class KodiController extends Controller
{
	private $kodi;

	public function __construct(KodiService $kodi)
	{
		$this->kodi = $kodi;
	}

	public function playMovie(Request $request)
	{
		$this->kodi->playMovie($request->get('title'));
	}
}
