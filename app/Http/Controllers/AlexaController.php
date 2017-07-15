<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Alexa\AlexaRequest;
use App\Models\Alexa\AlexaResponse;
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
		$request = new AlexaRequest($request);

		$result = $this->kodi->playMovie($request->getValue('MovieTitle'));
		$response = new AlexaResponse($result);
		return response()->json($response->get());
	}
}
