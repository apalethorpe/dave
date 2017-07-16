<?php

namespace App\Models\Alexa;

use Illuminate\Http\Request;

class AlexaRequest
{
	private $intent;

	public function __construct(Request $request)
	{
		$this->intent = $request->get('request')['intent'];
	}

	public function getIntent()
	{
		return lcfirst($this->intent['name']);
	}

	public function getValue($slotName)
	{
		foreach ($this->intent['slots'] as $slot) {
			if (isset($slot['name']) && isset($slot['value']) && strtolower($slot['name']) == strtolower($slotName)) {
				return $slot['value'];
			}
		}
	}
}
