<?php

namespace App\Services;

use App\Models\Alexa\AlexaRequest;
use App\Services\HueCurl;

class HueService
{
	private $curl;

	public function __construct(HueCurl $curl)
	{
		$this->curl = $curl;
	}

	public function pause(AlexaRequest $request)
	{
		$this->lightsOnIfDark();
	}

	public function resume(AlexaRequest $request)
	{
		$this->adjustLights(['on' => false]);
	}

	public function stop(AlexaRequest $request)
	{
		$this->lightsOnIfDark();
	}

	public function playMovie(AlexaRequest $request)
	{
		return $this->adjustLights(['on' => false]);
	}

	private function lightsOnIfDark()
	{
		if ($this->isNightTime()) {
			$this->adjustLights(['on' => true]);
		}
	}

	private function adjustLights($params)
	{
		$groups = $this->getGroups();

		$selectedGroups = explode('|', strtolower(env('HUE_GROUPS')));

		foreach ($groups as $id => $group) {
			if (in_array(strtolower($group['name']), $selectedGroups)) {
				$this->curl->put(sprintf('groups/%d/action', $id), $params);
			}
		}
	}

	private function getGroups()
	{
		return $this->curl->get('groups');
	}

	private function isNightTime()
	{
		$sensors = $this->curl->get('sensors');

		foreach ($sensors as $sensor) {
			if ($sensor['type'] == 'Daylight') {
				return $sensor['state']['daylight'] == false;
			}
		}
	}
}
