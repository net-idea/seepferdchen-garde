<?php
declare(strict_types=1);

/** @var boolean $container */
/** @var string $slug */
/** @var string $pageTitle */
/** @var string $htmlContent */
/** @var array $navItems */
/** @var array $currentMeta */
/** @var ?string $description */

$canonical = $currentMeta['canonical'] ?? ('/' . ($slug === 'start' ? '' : $slug));

?><!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?> — Seepferdchen‑Garde</title>
    <?php if (!empty($description)): ?>
        <meta name="description" content="<?= htmlspecialchars($description) ?>">
    <?php endif; ?>
    <meta name="robots" content="index,follow">
    <link rel="canonical" href="https://seepferdchen-garde.de<?= $canonical ?>">
    <link href="/styles/fonts.css" rel="stylesheet">
    <link href="/styles/bootstrap.min.css" rel="stylesheet">
    <link href="/styles/main.css" rel="stylesheet">
    <link href="/styles/carousel.css" rel="stylesheet">
    <link href="/styles/sections.css" rel="stylesheet">
    <link href="/styles/footer.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark" aria-label="Hauptnavigation">
    <div class="container"><?php include __DIR__ . '/navbar.php'; ?></div>
</nav>

<?php if ($container): ?>
    <main id="content">
        <div class="container py-5">
            <?= $htmlContent ?>
        </div>
    </main>
<?php else: ?>
    <?= $htmlContent ?>
<?php endif; ?>

<!-- Footer -->
<footer class="footer mt-5">
    <div class="container py-4">
        <div class="row text-center text-md-start">
            <div class="col-12 text-center">
                <p class="small">© 2025 Riccardo Nappa</p>
            </div>
        </div>
    </div>
</footer>
<script src="/scripts/bootstrap.bundle.min.js"></script>
<script src="/scripts/contact.js"></script>
</body>
</html>
