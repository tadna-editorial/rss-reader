<?php

declare(strict_types=1);

require dirname(__DIR__).'/src/FeedReader.php';

use EditorialRss\FeedReader;

$reader = new FeedReader(['example.org', 'www.example.org']);
$count = 0;
function check(bool $condition, string $message): void
{
    global $count;
    $count++;
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
function rejects(Closure $action, string $message): void
{
    try {
        $action();
    } catch (RuntimeException) {
        check(true, $message);

        return;
    }
    check(false, $message);
}
$rss = '<rss version="2.0"><channel><item><title>New &amp; verified</title><link>https://example.org/one</link><description><![CDATA[<b>A short summary</b>]]></description><pubDate>Tue, 03 Nov 2026 10:00:00 +0000</pubDate></item><item><title>Duplicate</title><link>https://example.org/one</link></item><item><title>Unsafe</title><link>javascript:alert(1)</link></item><item><title>Second</title><link>https://example.org/two</link><pubDate>not a date</pubDate></item></channel></rss>';
$items = $reader->parse($rss);
check(count($items) === 2, 'Duplicate and unsafe links must be excluded.');
check($items[0]['title'] === 'New & verified', 'Titles must decode XML entities.');
check($items[0]['summary'] === 'A short summary', 'Summaries must contain plain text.');
check($items[0]['published_at'] === '2026-11-03T10:00:00+00:00', 'Publication dates must be normalized.');
check($items[1]['published_at'] === null, 'Invalid dates must remain unknown.');
check(count($reader->parse($rss, 1)) === 1, 'The limit must be respected.');
$atom = '<feed xmlns="http://www.w3.org/2005/Atom"><entry><title>Atom item</title><link rel="self" href="https://example.org/api"/><link rel="alternate" href="https://example.org/article"/><summary>Short summary</summary><updated>2026-11-03T10:00:00Z</updated></entry></feed>';
$items = $reader->parse($atom);
check(count($items) === 1 && $items[0]['url'] === 'https://example.org/article', 'Atom must use the alternate article link.');
check($items[0]['summary'] === 'Short summary', 'Atom summaries must be supported.');
check($reader->parse('<rss><channel/></rss>') === [], 'Empty valid feeds must be supported.');
rejects(fn () => $reader->parse('<rss>'), 'Malformed XML must be rejected.');
rejects(fn () => $reader->parse('<html><body>Not a feed</body></html>'), 'HTML responses must be rejected.');
rejects(fn () => $reader->parse('<!DOCTYPE rss [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><rss><channel/></rss>'), 'External entities must be rejected.');
rejects(fn () => $reader->parse(str_repeat('a', 2_097_153)), 'Oversized XML must be rejected.');
rejects(fn () => $reader->parse($rss, 0), 'Invalid limits must be rejected.');
foreach (['http://example.org/rss.xml', 'https://attacker.test/rss', 'https://user:password@example.org/rss', 'https://example.org:8080/rss', 'file:///etc/passwd', 'https://example.org.attacker.test/rss'] as $url) {
    rejects(fn () => $reader->validateFeedUrl($url), 'Unconfigured or unsafe feed URL must be rejected.');
}
$reader->validateFeedUrl('https://example.org/rss.xml');
check(true, 'A configured HTTPS feed must be accepted.');
echo 'Passed '.$count.' assertions.'.PHP_EOL;
