<?php

namespace App\Services;

use App\Services\Curl;

class KodiCurl extends Curl
{
	private $params;

	public function __construct()
	{
		parent::__construct(
			sprintf('http://%s:%d/jsonrpc?request=', env('KODI_HOST'), env('KODI_PORT')),
			env('KODI_USERNAME'),
			env('KODI_PASSWORD')
		);
	}

	public function get($url)
	{
		return json_decode(parent::get(urlencode(json_encode(array_merge($this->params, $url)))), true);
	}

	public function setParams($params = [])
	{
		$this->params = $params;
	}
}
