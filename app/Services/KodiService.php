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
		$player = $this->getPlayerId();

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
					$responseText = sprintf('Playing %s', $selectedShow['label']);
				} else {
					$responseText = 'Something went wrong';
				}
				break;
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

	public function playAlbum(AlexaRequest $request) {
		$requestedArtist = $request->getValue('ArtistName');

		$selectedArtist = null;

		if ($requestedArtist) {
			$selectedArtist = $this->getArtist($requestedArtist)['label'];
		}

		$requestedAlbum = $request->getValue('AlbumTitle');
		
		$selectedAlbum = $this->getClosestMatchingTitle($this->getAlbums($selectedArtist), $requestedAlbum);
		if ($this->play($selectedAlbum['albumid'], 'album')) {
			$responseText = sprintf('Playing album %s', $selectedAlbum['label']);
			if ($selectedArtist) {
				$responseText .= sprintf(' by %s', $selectedArtist);
			}
		} else {
			$responseText = 'Something went wrong';
		}

		return $responseText;
	}

	public function whatsPlaying(AlexaRequest $request)
	{
		$player = $this->getPlayer();

		$responseText = null;

		if ($player) {
			$params = [
				'method' => 'Player.GetItem',
				'params' => [
					'playerid' => $player['playerid'],
					'properties' => ['title', 'album', 'artist', 'season', 'episode', 'showtitle']
				]
			];

			$item = $this->curl->get($params)['result']['item'];

			if ($item['type'] == 'song') {
				$responseText = sprintf('You\'re listening to %s, by %s', $item['title'], $item['artist'][0]);
			} elseif ($item['type'] == 'episode') {
				$responseText = sprintf(
					'You\'re watching "%s", season %d episode %d of %s',
					$item['title'],
					$item['season'],
					$item['episode'],
					$item['showtitle']
				);
			} elseif ($item['type'] == 'movie') {
				$responseText = sprintf('You\'re watching %s', $item['title']);
			} else {
				$responseText = 'I don\'t know what\'s playing right now';
			}
		} else {
			$responseText = 'There\'s nothing playing right now. Idiot.';
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

		return isset($response['result'][0]) ? $response['result'][0] : null;
	}

	private function getPlayerId()
	{
		$player = $this->getPlayer();
		return isset($player['playerid']) ? $player['playerid'] : null;
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
		$player = $this->getPlayerId();
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

	private function getArtist($artistName) {
		$params = ['method' => 'AudioLibrary.GetArtists'];

		$artists = $this->curl->get($params)['result']['artists'];

		return $this->getClosestMatchingTitle($artists, $artistName);
	}

	private function getAlbums($artistName = null) {
		$params = ['method' => 'AudioLibrary.GetAlbums'];
		if ($artistName) {
			$params['params'] = [
				'filter' => ['artist' => $artistName]
			];
		}

		return $this->curl->get($params)['result']['albums'];
	}

	private function getClosestMatchingTitle($items, $target)
	{
		$selected = null;

		// Try to find an exact match or a title containing every word in the request
		$targetParts = explode(' ', $target);

		$bestScore = 0;

		foreach ($items as $item) {
			if (strtolower($item['label']) == strtolower($target)) {
				$selected = $item;
				break;
			}

			$score = 0;

			foreach ($targetParts as $targetPart) {
				if (stripos(preg_replace('/[^A-Za-z0-9\s]/', '', $item['label']), $targetPart) !== false) {
					$score++;
				}
			}

			if ($score == count($targetParts) && $score > $bestScore) {
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
