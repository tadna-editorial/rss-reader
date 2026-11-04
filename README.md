# Tadna · RSS Reader

Tecnología de consumo: noticias, lanzamientos, análisis, tutoriales y guías de compra.

Official website: [Tadna](https://www.tadna.com/).

A standalone PHP reader for this project's public feeds, with a responsive web view and JSON output from the command line. It displays titles, publication dates and short summaries; full articles remain on the original website. RSS contains the items made available by each feed and is not a complete historical archive.

## Project and languages

| Language | Published feed |
| --- | --- |
| Español (`es`) | [RSS](https://tadna.com/es/rss.xml) |
| Inglés (`en`) | [RSS](https://tadna.com/en/rss.xml) |
| Francés (`fr`) | [RSS](https://tadna.com/fr/rss.xml) |
| Italiano (`it`) | [RSS](https://tadna.com/it/rss.xml) |
| Alemán (`de`) | [RSS](https://tadna.com/de/rss.xml) |
| Portugués (`pt`) | [RSS](https://tadna.com/pt/rss.xml) |

The public project information and feed URLs are kept in `project.json`. Feed languages are selected explicitly; this reader does not translate articles.

## Requirements

PHP 8.4 or newer within the 8.x series, with cURL, SimpleXML and mbstring. There are no third-party runtime packages and no database or API keys.

## Web view

```sh
php -S 127.0.0.1:8080 -t public
```

Open `http://127.0.0.1:8080` and select a feed language. The PHP built-in server is intended for local use. For hosting, set the web server document root to `public/`.

## Command line

```sh
php bin/rss.php --list
php bin/rss.php --language=es --limit=10
php tests/run.php
```

The reader writes JSON to standard output, errors to standard error and returns a nonzero exit code on failure. Dates without a valid value remain `null`. The maximum item limit is 100.

## Feed handling

RSS 2.0, RSS 1.0 and Atom are supported. HTML is removed from summaries and output is escaped in the web view. HTTPS certificate validation remains enabled. Redirects are limited to the configured feed/edition hosts; downloads are bounded by size and time. DTDs and XML entity declarations are rejected.

This repository contains only the reader and public project metadata. It does not contain the website source, article archive, account credentials or subscriber information.
