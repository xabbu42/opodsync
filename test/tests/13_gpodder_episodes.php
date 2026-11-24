<?php

use KD2\HTTP;
use KD2\Test;

$data = json_decode(file_get_contents(__DIR__ . '/../episodes.json'), true);

$r = $http->POST('/api/2/episodes/demo.json', $data, HTTP::JSON);
Test::equals(200, $r->status, $r);

$r = $http->GET('/api/2/episodes/demo.json');
Test::equals(200, $r->status, $r);

$r = json_decode($r);
Test::assert(is_object($r));
Test::assert(isset($r->actions));
Test::assert(count($r->actions) === 2);

// A later play of the first episode, for the filters below
$r = $http->POST('/api/2/episodes/demo.json', [[
	'action' => 'play',
	'device' => 'test-device',
	'episode' => 'http://example.com/files/s01e20.mp3',
	'podcast' => 'http://example.com/feed.rss',
	'timestamp' => '2009-12-13T09:00:00',
	'started' => 0,
	'position' => 10,
	'total' => 500,
]], HTTP::JSON);
Test::equals(200, $r->status, $r);

$episodes = function (array $query) use ($http): array {
	$r = $http->GET('/api/2/episodes/demo.json?' . http_build_query($query));
	Test::equals(200, $r->status, $r);
	return json_decode($r)->actions;
};

Test::equals(3, count($episodes([])));

// since compares with the action's timestamp: the 2009-12-12 download is left out
$actions = $episodes(['since' => strtotime('2009-12-12T12:00:00Z')]);
Test::equals(2, count($actions));
Test::assert(!in_array('download', array_column($actions, 'action')));

$actions = $episodes(['podcast' => 'http://example.com/feed.rss']);
Test::equals(2, count($actions));
Test::equals(['http://example.com/feed.rss'], array_values(array_unique(array_column($actions, 'podcast'))));

Test::equals(0, count($episodes(['podcast' => 'http://example.com/unknown.rss'])));

// aggregated keeps only the latest action of each episode
Test::equals(2, count($episodes(['aggregated' => 'true'])));

$actions = $episodes(['podcast' => 'http://example.com/feed.rss', 'aggregated' => 'true']);
Test::equals(1, count($actions));
Test::equals('play', $actions[0]->action);
Test::equals(10, $actions[0]->position);
Test::equals('2009-12-13T09:00:00Z', $actions[0]->timestamp);

Test::equals(3, count($episodes(['aggregated' => 'false'])));

// episode: the actions of one episode, with aggregated only its latest
Test::equals(2, count($episodes(['episode' => 'http://example.com/files/s01e20.mp3'])));
Test::equals(0, count($episodes(['episode' => 'http://example.com/files/unknown.mp3'])));

$actions = $episodes(['episode' => 'http://example.com/files/s01e20.mp3', 'aggregated' => 'true']);
Test::equals(1, count($actions));
Test::equals('play', $actions[0]->action);
Test::equals(10, $actions[0]->position);

$actions = $episodes(['episode' => 'http://example.com/files/s01e20.mp3', 'since' => strtotime('2009-12-12T12:00:00Z')]);
Test::equals(1, count($actions));
