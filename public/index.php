<?php

declare(strict_types=1);

$app = require dirname(__DIR__).'/bootstrap.php';
$project = $app['project'];
$language = $_GET['language'] ?? (isset($project['feeds']['es']) ? 'es' : array_key_first($project['feeds']));
if (! is_string($language) || ! isset($project['feeds'][$language])) {
    http_response_code(400);
    exit('Choose a configured feed language.');
}
$feed = $project['feeds'][$language];
$items = [];
$error = null;
try {
    $items = $app['reader']->read($feed['url']);
} catch (RuntimeException $exception) {
    http_response_code(502);
    $error = $exception->getMessage();
}
function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
header('Content-Type: text/html; charset=UTF-8');
header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($project['name']) ?> · RSS Reader</title>
    <style>
        :root{color-scheme:light dark;--bg:#f3f6fb;--card:#fff;--text:#142237;--muted:#52647b;--line:#dce5f0;--accent:#2459af}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:16px/1.6 system-ui,sans-serif}main{max-width:1040px;margin:auto;padding:44px 24px}a{color:var(--accent)}header{margin-bottom:28px}.eyebrow{font-size:.8rem;text-transform:uppercase;letter-spacing:.12em;color:var(--muted)}h1{font-size:clamp(2rem,6vw,3.4rem);letter-spacing:-.04em;line-height:1.12;margin:12px 0}h2{font-size:1.2rem;line-height:1.4;margin:0 0 10px}p{margin:10px 0}.description{max-width:740px;color:var(--muted)}.links{display:flex;gap:18px;flex-wrap:wrap}.toolbar{display:flex;gap:16px;align-items:end;justify-content:space-between;margin:28px 0}.field{display:grid;gap:6px}select,button{font:inherit;padding:10px 14px;border-radius:9px;border:1px solid var(--line);background:var(--card);color:var(--text)}button{background:var(--accent);color:#fff;cursor:pointer}form{display:flex;gap:10px;align-items:end}.items{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}article,.notice{background:var(--card);padding:24px;border:1px solid var(--line);border-radius:14px}article a{text-decoration:none}article a:hover{text-decoration:underline}.date,footer{color:var(--muted);font-size:.88rem}.date{display:block;margin-bottom:10px}details{margin-top:30px;border-top:1px solid var(--line);padding-top:18px}details li{margin:8px 0;overflow-wrap:anywhere}footer{margin-top:30px}@media(max-width:650px){main{padding:28px 18px}.items{grid-template-columns:1fr}.toolbar{align-items:start;flex-direction:column}form{width:100%}.field{flex:1}select{width:100%}}@media(prefers-color-scheme:dark){:root{--bg:#101923;--card:#182535;--text:#edf3ff;--muted:#a8b9cf;--line:#30435b;--accent:#8bbaff}button{color:#10213a}}
    </style>
</head>
<body>
<main>
    <header>
        <span class="eyebrow">Official feeds · PHP reader</span>
        <h1><?= h($project['name']) ?></h1>
        <p class="description"><?= h($project['description']) ?></p>
        <p class="links"><a href="<?= h($project['website']) ?>" target="_blank" rel="noopener noreferrer">Visit website ↗</a><a href="<?= h($feed['url']) ?>" target="_blank" rel="noopener noreferrer">Open RSS ↗</a></p>
    </header>
    <section class="toolbar" aria-label="Feed selection">
        <div><strong>Latest publications</strong><br><span class="date"><?= count($items) ?> items from the selected feed</span></div>
        <form method="get">
            <div class="field"><label for="language">Feed language</label><select id="language" name="language"><?php foreach ($project['feeds'] as $code => $available) { ?><option value="<?= h($code) ?>" <?= $code === $language ? 'selected' : '' ?>><?= h($available['label']) ?></option><?php } ?></select></div>
            <button type="submit">Read feed</button>
        </form>
    </section>
    <?php if ($error !== null) { ?><p class="notice" role="alert"><?= h($error) ?> <a href="<?= h($feed['url']) ?>">Open the source feed</a>.</p><?php } ?>
    <section class="items" aria-label="Publications">
        <?php foreach ($items as $item) { ?>
            <article>
                <?php if ($item['published_at'] !== null) { ?><time class="date" datetime="<?= h($item['published_at']) ?>"><?= h((new DateTimeImmutable($item['published_at']))->format('Y-m-d H:i T')) ?></time><?php } ?>
                <h2><a href="<?= h($item['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($item['title']) ?></a></h2>
                <?php if ($item['summary'] !== '') { ?><p><?= h($item['summary']) ?></p><?php } ?>
                <a href="<?= h($item['url']) ?>" target="_blank" rel="noopener noreferrer">Read on <?= h($project['name']) ?> ↗</a>
            </article>
        <?php } ?>
    </section>
    <?php if ($error === null && $items === []) { ?><p class="notice">This feed currently contains no publications.</p><?php } ?>
    <details><summary>Project information and available feeds</summary><p><?= h($project['description']) ?></p><ul><?php foreach ($project['feeds'] as $available) { ?><li><?= h($available['label']) ?>: <a href="<?= h($available['url']) ?>"><?= h($available['url']) ?></a></li><?php } ?></ul></details>
    <footer>This reader displays titles and short summaries from public feeds. Full articles remain on the original website.</footer>
</main>
</body>
</html>
