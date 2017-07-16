<?php

namespace App\Services;

use App\Services\Curl;

class HueCurl extends Curl
{
	public function __construct()
	{
		parent::__construct(sprintf('http://%s/api/%s/', env('HUE_HOST'), env('HUE_USERNAME')));
	}

	public function get($url)
	{
		return json_decode(parent::get($url), true);
	}

	public function put($url, $payload)
	{
		return json_decode(parent::put($url, json_encode($payload)), true);
	}
}
