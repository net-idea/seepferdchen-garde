<?php
declare(strict_types=1);

use Website\PageRepository;

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    $loader = require __DIR__ . '/vendor/autoload.php';
} else {
    die('The main autoloader not found! Did you forget to run "composer install"?');
}

require __DIR__ . '/src/config.php';

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$path = trim($path, '/');
// Strip on first '&' or '?' just in case they appear in the path
$path = preg_replace('~[&?].*$~', '', $path) ?? '';
// Collapse multiple slashes
$path = preg_replace('~/+~', '/', $path) ?? '';
// Keep only safe characters for slugs
$path = preg_replace('~[^a-z0-9/_\-]~i', '', $path) ?? '';
// Use the first segment as the slug
$segments = array_values(array_filter(explode('/', $path)));
$slug = $segments[0] ?? '';

// Fallback to legacy "?p=" if no path slug present
if ($slug === '' && isset($_GET['p'])) {
    $slug = trim((string) $_GET['p'], '/');
}

if ($slug === '' || $slug === 'index.php') {
    $slug = 'start';
}

$repo  = new PageRepository(CMS_CONTENT_DIR);
$container = true;

if (!$repo->exists($slug)) {
    http_response_code(404);
    $pageTitle   = 'Seite nicht gefunden';
    $description = 'Die gewünschte Seite wurde nicht gefunden.';
    $htmlContent = '<h1>Fehler 404</h1><p>Die gewünschte Seite wurde nicht gefunden.</p>';
} else {
    $pageTitle   = $repo->titleFor($slug);
    $description = $repo->descriptionFor($slug);
    $markdown    = $repo->get($slug);

    if ($markdown !== '') {
        $parsedown   = new Parsedown();
        $htmlContent = $parsedown->text($markdown);
    } else {
        $container = false;
        // Static or non CMS-managed page...
        $htmlContent = file_get_contents(__DIR__ . "/content/{$slug}.html");
    }
}

$navItems = $repo->navItems();
$currentMeta = $repo->pageMeta($slug);

require __DIR__ . '/templates/layout.php';
