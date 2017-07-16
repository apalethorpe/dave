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

		if (!$requestedMovie) {
			throw new \Exception('No movie title provided');
		}

		$movies = $this->getMovies();
		$selectedMovie = null;

		// Try to find an exact match or a title containing every word in the request
		$requestedMovieParts = explode(' ', $requestedMovie);

		foreach ($movies['result']['movies'] as $movie) {
			if (strtolower($movie['label']) == strtolower($requestedMovie)) {
				$selectedMovie = $movie;
				break;
			}

			$score = 0;

			foreach ($requestedMovieParts as $requestedMoviePart) {
				if (stripos(preg_replace('/[A-Za-z0-9\s]/', '', $movie['label']), $requestedMoviePart) !== false) {
					$score++;
				}
			}

			if ($score == count($requestedMovieParts)) {
				$selectedMovie = $movie;
			}
		}

		// If nothing is found, try to find the closest title
		if (!$selectedMovie) {
			$bestScore = null;

			foreach ($movies['result']['movies'] as $movie) {
				$score = levenshtein($requestedMovie, $movie['label']);
				if ($bestScore === null || $bestScore > $score) {
					$bestScore = $score;
					$selectedMovie = $movie;
				}
			}
		}

		$params = ['method' => 'Player.Open', 'params' => ['item' => ['movieid' => $selectedMovie['movieid']]]];

		$this->curl->get($params);

		if ($this->curl->getLastStatusCode() == 200) {
			$responseText = sprintf('Playing movie %s', $selectedMovie['label']);
		} else {
			$responseText = 'Something went wrong';
		}

		return $responseText;
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
		return $this->curl->get($params);
	}
}
