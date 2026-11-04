<?php

declare(strict_types=1);

namespace EditorialRss;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use SimpleXMLElement;

final class FeedReader
{
    private const MAX_BYTES = 2_097_152;

    /** @param list<string> $allowedHosts */
    public function __construct(private readonly array $allowedHosts) {}

    /** @return list<array{title: string, url: string, summary: string, published_at: ?string}> */
    public function read(string $url, int $limit = 20): array
    {
        return $this->parse($this->download($url), $limit);
    }

    public function validateFeedUrl(string $url): void
    {
        $parts = parse_url($url);
        if (! is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || ! in_array(strtolower($parts['host'] ?? ''), $this->allowedHosts, true)
            || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && $parts['port'] !== 443)) {
            throw new RuntimeException('The feed must use HTTPS on a configured project host.');
        }
    }

    /** @return list<array{title: string, url: string, summary: string, published_at: ?string}> */
    public function parse(string $xml, int $limit = 20): array
    {
        if ($limit < 1 || $limit > 100) {
            throw new RuntimeException('The item limit must be between 1 and 100.');
        }
        if (strlen($xml) > self::MAX_BYTES || preg_match('/<!\s*(DOCTYPE|ENTITY)\b/i', $xml)) {
            throw new RuntimeException('The feed is too large or contains a document type declaration.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $feed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if ($feed === false || ! in_array($feed->getName(), ['rss', 'RDF', 'feed'], true)) {
            throw new RuntimeException('The response is not a supported RSS or Atom feed.');
        }
        $nodes = $feed->xpath('/*[local-name()="rss"]/*[local-name()="channel"]/*[local-name()="item"] | /*[local-name()="RDF"]/*[local-name()="item"] | /*[local-name()="feed"]/*[local-name()="entry"]') ?: [];
        $items = [];
        $seen = [];
        foreach ($nodes as $node) {
            $title = $this->text($this->value($node, 'title'), 250);
            $link = $this->value($node, 'link');
            if ($feed->getName() === 'feed') {
                $links = $node->xpath('./*[local-name()="link" and (not(@rel) or @rel="alternate")]') ?: [];
                $link = isset($links[0]) ? (string) $links[0]['href'] : '';
            }
            if ($title === '' || ! $this->isArticleUrl($link) || isset($seen[$link])) {
                continue;
            }
            $seen[$link] = true;
            $items[] = [
                'title' => $title,
                'url' => $link,
                'summary' => $this->text($this->value($node, 'description') ?: $this->value($node, 'summary'), 400),
                'published_at' => $this->date($this->value($node, 'pubDate') ?: $this->value($node, 'published') ?: $this->value($node, 'updated') ?: $this->value($node, 'date')),
            ];
            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    private function download(string $url): string
    {
        for ($redirect = 0; $redirect <= 3; $redirect++) {
            $this->validateFeedUrl($url);
            $body = '';
            $location = null;
            $request = curl_init($url);
            if ($request === false) {
                throw new RuntimeException('Unable to initialize the HTTP client.');
            }
            curl_setopt_array($request, [
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_ENCODING => '',
                CURLOPT_USERAGENT => 'EditorialRSSReader/1.0',
                CURLOPT_HTTPHEADER => ['Accept: application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9'],
                CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$location): int {
                    if (stripos($line, 'Location:') === 0) {
                        $location = trim(substr($line, 9));
                    }

                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                    if (strlen($body) + strlen($chunk) > self::MAX_BYTES) {
                        return 0;
                    }
                    $body .= $chunk;

                    return strlen($chunk);
                },
            ]);
            $ok = curl_exec($request);
            $status = curl_getinfo($request, CURLINFO_RESPONSE_CODE);
            curl_close($request);
            if ($ok === false) {
                throw new RuntimeException('The feed download failed or exceeded its size/time limit.');
            }
            if (in_array($status, [301, 302, 303, 307, 308], true) && $location !== null) {
                $url = $this->redirectUrl($url, $location);

                continue;
            }
            if ($status !== 200) {
                throw new RuntimeException('The feed returned HTTP '.$status.'.');
            }

            return $body;
        }

        throw new RuntimeException('The feed exceeded its redirect limit.');
    }

    private function redirectUrl(string $base, string $location): string
    {
        if (str_starts_with($location, '//')) {
            return 'https:'.$location;
        }
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }
        $parts = parse_url($base);
        $origin = 'https://'.$parts['host'];

        return str_starts_with($location, '/') ? $origin.$location : $origin.rtrim(dirname($parts['path'] ?? '/'), '/').'/'.$location;
    }

    private function value(SimpleXMLElement $node, string $name): string
    {
        $values = $node->xpath('./*[local-name()="'.$name.'"]') ?: [];

        return isset($values[0]) ? trim((string) $values[0]) : '';
    }

    private function text(string $value, int $length): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');

        return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)).'…' : $text;
    }

    private function isArticleUrl(string $url): bool
    {
        $parts = parse_url($url);

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array($parts['scheme'] ?? '', ['https', 'http'], true)
            && ! isset($parts['user']) && ! isset($parts['pass']);
    }

    private function date(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format(DATE_ATOM);
        } catch (\Exception) {
            return null;
        }
    }
}
