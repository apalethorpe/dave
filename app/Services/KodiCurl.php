<?php

namespace App\Services;

use App\Services\Curl;

class KodiCurl extends Curl
{
	public function __construct()
	{
		parent::__construct(
			sprintf('http://%s:%d/jsonrpc', env('KODI_HOST'), env('KODI_PORT')),
			env('KODI_USERNAME'),
			env('KODI_PASSWORD')
		);
	}
}