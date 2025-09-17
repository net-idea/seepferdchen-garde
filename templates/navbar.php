<?php
declare(strict_types=1);

/** @var string $slug */
/** @var string $pageTitle */
/** @var string $htmlContent */
/** @var array  $navItems */
/** @var array  $currentMeta */
/** @var ?string $description */

?>
<a class="navbar-brand" href="/" aria-label="Seepferdchen‑Garde">Seepferdchen‑Garde</a>
<button
    class="navbar-toggler"
    type="button"
    data-bs-toggle="collapse"
    data-bs-target="#navbarNav"
    aria-controls="navbarNav"
    aria-expanded="false"
    aria-label="Navigation umschalten"
>
    <span class="navbar-toggler-icon"></span>
</button>
<!-- Navigation links -->
<div class="collapse navbar-collapse" id="navbarNav">
    <ul class="navbar-nav ms-auto">
        <?php

        foreach ($navItems as $navItemSlug => $navItem):
            $navItemUrl = '/' . ($navItemSlug === 'start' ? '' : $navItemSlug);
            $navItemLabel = $navItem['nav_label'] ?? $navItem['title'];
            $navItemActive = $navItemSlug === $slug ? ' active' : '';

            ?><li class="nav-item">
            <a class="nav-link<?= $navItemActive ?>" href="<?= htmlspecialchars($navItemUrl) ?>"><?= htmlspecialchars($navItemLabel) ?></a>
            </li><?php

        endforeach;

        ?>
    </ul>
</div>
