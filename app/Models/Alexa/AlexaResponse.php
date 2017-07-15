<?php

namespace App\Models\Alexa;

class AlexaResponse
{
	public function __construct($voice_response)
	{
		$this->voice_response = $voice_response;
	}

	public function get()
	{
		return [
			'version' => '1.0',
			'response' => [
				'outputSpeech' => [
					'type' => 'PlainText',
					'text' => $this->voice_response
				],
				'shouldEndSession' => true,
			],

			'sessionAttributes' => new \StdClass
		];
	}
}
