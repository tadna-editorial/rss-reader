<?php

declare(strict_types=1);

$app = require dirname(__DIR__).'/bootstrap.php';
$options = getopt('', ['language:', 'limit:', 'list']);
$project = $app['project'];
if (isset($options['list'])) {
    echo json_encode($project, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
    exit(0);
}
$language = $options['language'] ?? (isset($project['feeds']['es']) ? 'es' : array_key_first($project['feeds']));
$limit = filter_var($options['limit'] ?? 20, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
if (! is_string($language) || ! isset($project['feeds'][$language]) || $limit === false) {
    fwrite(STDERR, 'Choose a configured language and a limit between 1 and 100. Use --list to inspect the project.'.PHP_EOL);
    exit(2);
}
try {
    $feed = $project['feeds'][$language];
    $items = $app['reader']->read($feed['url'], $limit);
    echo json_encode(['project' => $project['name'], 'language' => $language, 'feed' => $feed['url'], 'fetched_at' => gmdate(DATE_ATOM), 'items' => $items], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
}
