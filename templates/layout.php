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

// Site defaults
$baseUrl = 'https://seepferdchen-garde.de';
$brandName = 'Seepferdchen‑Garde';
$siteName = 'Seepferdchen‑Garde Schwimmschule';
$defaultDescription = 'Schwimmschule in Herzogenrath: Kurse für Kinder ab 5 Jahren – 10× 45 Minuten, individuelle Förderung, Sicherheit und Spaß. Offizielle Seepferdchen‑Prüfung.';
$robots = $currentMeta['robots'] ?? 'index,follow';

// SEO computed values
$absCanonical = $baseUrl . $canonical;
$pageDesc = $description ?: ($currentMeta['description'] ?? $defaultDescription);
$ogImagePath = $currentMeta['og_image'] ?? '/assets/og/start.jpg';
$absOgImage = $baseUrl . $ogImagePath;
$absLogo = $baseUrl . '/assets/logo.png';
$fullTitle = $pageTitle . ' — ' . $brandName;

?><!DOCTYPE html>
<html lang="de" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($fullTitle) ?></title>
    <?php if (!empty($pageDesc)): ?>
        <meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">
    <?php endif; ?>
    <meta name="robots" content="<?= htmlspecialchars($robots) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($absCanonical) ?>">

    <!-- Open Graph -->
    <meta property="og:site_name" content="<?= htmlspecialchars($siteName) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($fullTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($absCanonical) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($absOgImage) ?>">
    <meta property="og:locale" content="de_DE">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($fullTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($absOgImage) ?>">

    <!-- JSON-LD: LocalBusiness -->
    <script type="application/ld+json">
        <?= json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $siteName,
            'url' => $absCanonical,
            'image' => [$absOgImage],
            'logo' => $absLogo,
            'email' => 'mail@seepferdchen-garde.de',
            'telephone' => '+49 176 83239011',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'Forensberger Str. 90',
                'postalCode' => '52134',
                'addressLocality' => 'Herzogenrath',
                'addressCountry' => 'DE',
            ],
            'areaServed' => 'Herzogenrath',
            'priceRange' => '€€',
            'slogan' => 'Kleine Gruppen, kindgerechtes Lernen und offizielle Seepferdchen‑Prüfung.',
            'makesOffer' => [
                '@type' => 'Offer',
                'name' => 'Seepferdchen‑Schwimmkurs (10×45 Min.)',
                'price' => 200,
                'priceCurrency' => 'EUR',
                'availability' => 'https://schema.org/InStock',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    </script>

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
