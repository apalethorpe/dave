<?php

namespace App\Services;

use App\Models\Alexa\AlexaRequest;
use App\Services\KodiCurl;

class KodiService
{
	private $curl;

	public function __construct(KodiCurl $curl)
	{
		$this->curl = $curl;
		$this->curl->setParams(['jsonrpc' => env('KODI_JSONRPC_VERSION'), 'id' => env('KODI_LIBRARY_ID')]);
	}

	public function pause(AlexaRequest $request)
	{
		return $this->pauseResume(true);
	}

	public function resume(AlexaRequest $request)
	{
		return $this->pauseResume(false);
	}

	public function stop(AlexaRequest $request)
	{
		$player = $this->getPlayer();

		$responseText = null;

		if ($player !== null) {
			$params = ['method' => 'Player.Stop', 'params' => ['playerid' => $player]];
			$this->curl->get($params);
			$responseText = 'OK';
		}

		return $responseText;
	}

	public function playMovie(AlexaRequest $request)
	{
		$requestedMovie = $request->getValue('MovieTitle');
		$selectedMovie = $this->getClosestMatchingTitle($this->getMovies(), $requestedMovie);

		if ($this->play($selectedMovie['movieid'])) {
			$responseText = sprintf('Playing movie %s', $selectedMovie['label']);
		} else {
			$responseText = 'Something went wrong';
		}

		return $responseText;
	}

	public function playTVShow(AlexaRequest $request)
	{
		$requestedShow = $request->getValue('TVShowTitle');
		$selectedShow = $this->getClosestMatchingTitle($this->getTVShows(), $requestedShow);

		$requestedSeason = $request->getValue('Season');
		$requestedEpisode = $request->getValue('Episode');

		$episodes = $this->getEpisodes($selectedShow['tvshowid']);

		$responseText = null;

		foreach ($episodes as $episode) {
			$isCorrectSeason = (!$requestedSeason || ($requestedSeason && $episode['season'] == $requestedSeason));
			$isCorrectEpisode = $requestedEpisode == $episode['episode'];

			if ($isCorrectSeason && $isCorrectEpisode) {
				if ($this->play($episode['episodeid'], 'episode')) {
					$responseText = sprintf('Playing %s', $requestedShow);
				} else {
					$responseText = 'Something went wrong';
				}
			}
		}

		if (!$responseText) {
			$responseText = sprintf(
				'I could\'nt find %s %s episode %d',
				$requestedShow,
				$requestedSeason ? sprintf('season %d', $requestedSeason) : '',
				$requestedEpisode
			);
		}

		return $responseText;
	}

	private function play($itemId, $type = 'movie')
	{
		$params = ['method' => 'Player.Open', 'params' => ['item' => [$type . 'id' => $itemId]]];
		
		$this->curl->get($params);

		return $this->curl->getLastStatusCode() == 200;
	}

	private function getPlayer()
	{
		$params = ['method' => 'Player.GetActivePlayers'];
		$response = $this->curl->get($params);
		return isset($response['result'][0]) ? $response['result'][0]['playerid'] : null;
	}

	private function getPlayerProperties($player, $properties = [])
	{
		$params = [
			'method' => 'Player.GetProperties',
			'params' => ['playerid' => $player, 'properties' => $properties]
		];

		return $this->curl->get($params)['result'];
	}

	private function isPaused($player)
	{
		return $this->getPlayerProperties($player, ['speed'])['speed'] === 0;
	}

	private function pauseResume($pause)
	{
		$player = $this->getPlayer();
		if ($player !== null && ((!$this->isPaused($player) && $pause) || ($this->isPaused($player) && !$pause))) {
			$params = ['method' => 'Player.PlayPause', 'params' => ['playerid' => $player], 'id' => 1];
			$this->curl->get($params);
			return 'OK';
		}
	}

	private function getMovies()
	{
		$params = ['method' => 'VideoLibrary.GetMovies'];
		return $this->curl->get($params)['result']['movies'];
	}

	private function getTVShows()
	{
		$params = ['method' => 'VideoLibrary.GetTVShows'];
		return $this->curl->get($params)['result']['tvshows'];
	}

	private function getEpisodes($tvShowId)
	{
		$params = [
			'method' => 'VideoLibrary.GetEpisodes',
			'params' => [
				'tvshowid' => $tvShowId,
				'properties' => ['season', 'episode']
			]
		];
		
		return $this->curl->get($params)['result']['episodes'];
	}

	private function getClosestMatchingTitle($items, $target)
	{
		$selected = null;

		// Try to find an exact match or a title containing every word in the request
		$targetParts = explode(' ', $target);

		foreach ($items as $item) {
			if (strtolower($item['label']) == strtolower($target)) {
				$selected = $item;
				break;
			}

			$score = 0;

			foreach ($targetParts as $targetPart) {
				if (stripos(preg_replace('/[A-Za-z0-9\s]/', '', $item['label']), $targetPart) !== false) {
					$score++;
				}
			}

			if ($score == count($targetParts)) {
				$selected = $item;
			}
		}

		// If nothing is found, try to find the closest title
		if (!$selected) {
			$bestScore = null;

			foreach ($items as $item) {
				$score = levenshtein($target, $item['label']);
				if ($bestScore === null || $bestScore > $score) {
					$bestScore = $score;
					$selected = $item;
				}
			}
		}

		return $selected;
	}
}
