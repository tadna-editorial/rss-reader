<?php

declare(strict_types=1);
use EditorialRss\FeedReader;

require_once __DIR__.'/src/FeedReader.php';

$project = json_decode(file_get_contents(__DIR__.'/project.json'), true, 512, JSON_THROW_ON_ERROR);
$hosts = [];
foreach ($project['feeds'] as $feed) {
    foreach ([$feed['url'], $feed['website']] as $url) {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $hosts[] = $host;
        $hosts[] = str_starts_with($host, 'www.') ? substr($host, 4) : 'www.'.$host;
    }
}

return ['project' => $project, 'reader' => new FeedReader(array_values(array_unique($hosts)))];
