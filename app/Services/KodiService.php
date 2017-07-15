<?php

namespace App\Services;

use App\Services\KodiCurl;

class KodiService
{
	private $curl;
	private $baseParams;

	public function __construct(KodiCurl $curl)
	{
		$this->curl = $curl;
		$this->baseParams = ['jsonrpc' => env('KODI_JSONRPC_VERSION'), 'id' => env('KODI_LIBRARY_ID')];
	}

	private function getMovies($decode = false)
	{
		$params = array_merge(['method' => 'VideoLibrary.GetMovies'], $this->baseParams);
		$movies = $this->curl->get(sprintf('?request=%s', urlencode(json_encode($params))));
		return $decode ? json_decode($movies, true) : $movies;
	}

	public function playMovie($requestedMovie)
	{
		if (!$requestedMovie) {
			throw new \Exception('No movie title provided');
		}

		$movies = $this->getMovies(true);
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

		$params = array_merge(
			['method' => 'Player.Open', 'params' => ['item' => ['movieid' => $selectedMovie['movieid']]]],
			$this->baseParams
		);

		$this->curl->get(sprintf('?request=%s', urlencode(json_encode($params))));

		if ($this->curl->getLastStatusCode() == 200) {
			return sprintf('Playing movie %s', $selectedMovie['label']);
		} else {
			return 'Something went wrong';
		}
	}	
}