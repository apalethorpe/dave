<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Alexa\AlexaRequest;
use App\Models\Alexa\AlexaResponse;
use App\Services\HueService;
use App\Services\KodiService;
use Illuminate\Http\Request;

class AlexaController extends Controller
{
	private $hue;
	private $kodi;

	public function __construct(HueService $hue, KodiService $kodi)
	{
		$this->hue = $hue;
		$this->kodi = $kodi;
	}

	public function handle(Request $request)
	{
		$request = new AlexaRequest($request);

		$intent = $request->getIntent();

		$result = '';

		if (method_exists($this->hue, $intent)) {
			$result .= $this->hue->$intent($request);
		}

		if (method_exists($this->kodi, $intent)) {
			$result .= $this->kodi->$intent($request);
		}

		$response = new AlexaResponse($result);

		return response()->json($response->get());
	}
}
