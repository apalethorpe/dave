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

	private function pauseResume($pause)
	{
		$player = $this->getPlayerId();

		$responseText = null; 

		if ($player !== null && ((!$this->isPaused($player) && $pause) || ($this->isPaused($player) && !$pause))) {
			$params = ['method' => 'Player.PlayPause', 'params' => ['playerid' => $player], 'id' => 1];
			$this->curl->get($params);
			$responseText = 'OK';
		} else {
			$responseText = 'How do you expect me to pause when there\'s nothing playing? Dumbass.';
		}

		return $responseText;
	}

	public function stop(AlexaRequest $request)
	{
		$player = $this->getPlayerId();

		$responseText = null;

		if ($player !== null) {
			$params = ['method' => 'Player.Stop', 'params' => ['playerid' => $player]];
			$this->curl->get($params);
			$responseText = 'OK';
		} else {
			$responseText = 'I can\'t stop nothing. Moron.';
		}

		return $responseText;
	}

	public function skip(AlexaRequest $request)
	{
		$player = $this->getPlayerId();

		$responseText = null;

		if ($player !== null) {
			$params = ['method' => 'Player.GoTo', 'params' => ['playerid' => $player, 'to' => 'next']];
			$responseText = 'OK';
		} else {
			$responseText = 'Nothing is currently playing, so how can I skip? Stupid.';
		}

		return $responseText;
	}

	public function seek(AlexaRequest $request)
	{
		$player = $this->getPlayerId();

		if ($player) {
			$playerProperties = $this->getPlayerProperties($player, ['time']);

			$direction = $request->getValue('SeekType');
			$period = $request->getValue('Period');

			$hours = 0;
			$minutes = 0;
			$seconds = 0;

			preg_match('/\d*H/', $period, $hours);
			preg_match('/\d*M/', $period, $minutes);
			preg_match('/\d*S/', $period, $seconds);

			$hours = isset($hours[0]) ? preg_replace('/[^0-9]/', '', $hours[0]) : 0;
			$minutes = isset($minutes[0]) ? preg_replace('/[^0-9]/', '', $minutes[0]) : 0;
			$seconds = isset($seconds[0]) ? preg_replace('/[^0-9]/', '', $seconds[0]) : 0;

			$position = $playerProperties['time'];

			if ($direction == 'fast forward') {
				for ($i = 0; $i < $seconds; $i++) {
					if ($position['seconds'] == 60) {
						$position['minutes']++;
						if ($position['minutes'] == 60) {
							$position['hours']++;
							$position['minutes'] = 0;
						}
						$position['seconds'] = 0;
					}

					$position['seconds']++;
				}

				for ($i = 0; $i < $minutes; $i++) {
					if ($position['minutes'] == 60) {
						$position['hours']++;
						$position['minutes'] = 0;
					}

					$position['minutes']++;
				}

				for ($i = 0; $i < $hours; $i++) {
					$position['hours']++;
				}
			} elseif ($direction == 'rewind') {
				for ($i = 0; $i < $seconds; $i++) {
					if ($position['seconds'] == -1) {
						$position['minutes']--;
						if ($position['minutes'] == -1) {
							$position['hours']--;
							$position['minutes'] = 59;
						}
						$position['seconds'] = 59;
					}

					$position['seconds']--;
				}

				for ($i = 0; $i < $minutes; $i++) {
					if ($position['minutes'] == -1) {
						$position['hours']--;
						$position['minutes'] = 59;
					}

					$position['minutes']--;
				}

				for ($i = 0; $i < $hours; $i++) {
					$position['hours']--;
				}
			}

			$params = ['method' => 'Player.Seek', 'params' => ['playerid' => $player, 'value' => $position]];

			$this->curl->get($params);
		}
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

		if ($requestedShow) {
			$selectedShow = $this->getClosestMatchingTitle($this->getTVShows(), $requestedShow);

			$requestedSeason = $request->getValue('Season');
			$requestedEpisode = $request->getValue('Episode');

			if ($requestedSeason && !$requestedEpisode) {
				$requestedEpisode = 1;
			}

			$responseText = null;

			foreach ($this->getEpisodes($selectedShow['tvshowid']) as $episode) {
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
	}

	public function playAlbum(AlexaRequest $request)
	{
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

	public function playSong(AlexaRequest $request)
	{
		$requestedArtist = $request->getValue('ArtistName');

		if ($requestedArtist) {
			$selectedArtist = $this->getArtist($requestedArtist)['label'];
		}

		$requestedSong = $request->getValue('SongTitle');

		$selectedSong = $this->getClosestMatchingTitle($this->getSongs($selectedArtist), $requestedSong);

		if ($this->play($selectedSong['songid'], 'song')) {
			$responseText = sprintf('Playing %s', $selectedSong['label']);
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

	public function updateLibraries(AlexaRequest $request)
	{
		$this->curl->get(['method' => 'AudioLibrary.Scan']);
		$this->curl->get(['method' => 'VideoLibrary.Scan']);

		return 'Scanning for new content';
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

	private function getArtist($artistName)
	{
		$params = ['method' => 'AudioLibrary.GetArtists'];

		$artists = $this->curl->get($params)['result']['artists'];

		return $this->getClosestMatchingTitle($artists, $artistName);
	}

	private function getAlbums($artistName = null)
	{
		return $this->getMusic('albums', $artistName)['albums'];
	}

	private function getSongs($artistName = null)
	{
		return $this->getMusic('songs', $artistName)['songs'];
	}

	private function getMusic($type, $artistName = null)
	{
		$params = ['method' => ($type == 'albums' ? 'AudioLibrary.GetAlbums' : 'AudioLibrary.GetSongs')];
		if ($artistName) {
			$params['params'] = [
				'filter' => ['artist' => $artistName]
			];
		}

		return $this->curl->get($params)['result'];
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
				if (stripos(preg_replace('/[^A-Za-z0-9\s]/', '', $item['label']), $targetPart) !== false) {
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
