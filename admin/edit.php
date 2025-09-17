<?php
declare(strict_types=1);

use Website\PageRepository;

if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    $loader = require __DIR__ . '/../vendor/autoload.php';
} else {
    die('The main autoloader not found! Did you forget to run "composer install"?');
}

require __DIR__ . '/../src/config.php';

cms_require_login();

$repo = new PageRepository(CMS_CONTENT_DIR);

$slug = $_GET['p'] ?? 'start';

if (!$repo->exists($slug)) {
    $slug = 'start';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cms_verify_csrf($_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Bad CSRF');
    }

    $newContent = $_POST['content'] ?? '';
    $repo->save($slug, $newContent);
    header('Location: edit.php?p=' . urlencode($slug) . '&saved=1');
    exit;
}

$current = $repo->get($slug);
$pageTitle = $repo->titleFor($slug) ?: $slug;

?><!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Admin — Edit <?= htmlspecialchars($slug) ?></title>
    <meta name="robots" content="noindex,nofollow">
    <link href="/styles/fonts.css" rel="stylesheet">
    <link href="/styles/bootstrap.min.css" rel="stylesheet">
    <link href="/styles/main.css" rel="stylesheet">
    <style>
        .editor-textarea {
            min-height: 20vh !important;
            height: 60vh !important;
            font-family: var(--bs-font-monospace, ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace), monospace;
            line-height: 1.4;
            resize: vertical;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <span class="navbar-brand mb-0 h1">CMS</span>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-light btn-sm" href="/?p=<?= htmlspecialchars($slug) ?>" target="_blank"
               rel="noopener">View</a>
        </div>
    </div>
</nav>

<main class="container my-4">
    <?php if (!empty($_GET['saved'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Gespeichert.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="mb-3">
        <ul class="nav nav-pills flex-wrap gap-2">
            <?php foreach ($repo->allowedPages() as $allowedPageSlug => $allowedPage): ?>
                <?php

                $isActive = $allowedPageSlug === $slug ? 'active' : '';
                $label = $allowedPage['title'] ?? $allowedPageSlug;

                ?>
                <li class="nav-item">
                    <a class="nav-link <?= $isActive ?>"
                       href="?p=<?= urlencode($allowedPageSlug) ?>">
                        <?= htmlspecialchars($label) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h5 mb-0">Edit: <?= htmlspecialchars($pageTitle) ?></h1>
                <span class="badge text-bg-secondary"><?= htmlspecialchars($slug) ?></span>
            </div>

            <form method="post" class="vstack gap-3">
                <input type="hidden" name="csrf" value="<?= cms_csrf_token() ?>">

                <div class="form-floating">
                        <textarea
                            id="content"
                            name="content"
                            class="form-control editor-textarea"
                            placeholder="Markdown content"><?= htmlspecialchars($current) ?></textarea>
                    <label for="content">Markdown content</label>
                </div>

                <div class="d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Speichern</button>
                    <a class="btn btn-outline-secondary" href="/?p=<?= htmlspecialchars($slug) ?>" target="_blank"
                       rel="noopener">Seite ansehen</a>
                </div>
            </form>
        </div>
    </div>
</main>

<footer class="container py-4">
    <p class="text-center text-body-secondary small mb-0">© 2025</p>
</footer>

<script src="/scripts/bootstrap.bundle.min.js"></script>
</body>
</html>
