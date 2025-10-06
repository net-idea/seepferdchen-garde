<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

abstract class AbstractBaseController extends AbstractController
{
    public function loadPageMetadata(string $slug): array
    {
        $projectDir = (string)$this->getParameter('kernel.project_dir');

        // Load page metadata from content/_pages.php
        $pagesFile = $projectDir . '/content/_pages.php';
        $pages = is_file($pagesFile) ? (require $pagesFile) : [];
        $metaSlug = ('' === $slug || 'main' === $slug) ? 'start' : $slug;

        /** @var array<string,mixed> $pageMeta */
        $pageMeta = $pages[$metaSlug] ?? [
            'title'       => ucfirst($metaSlug ?: 'Start'),
            'description' => 'Seepferdchen‑Garde Schwimmschule in Herzogenrath Region Aachen: Kurse für Kinder ab 5 Jahren – individuell, sicher und mit Spaß schwimmen lernen.',
            'canonical'   => '/' . ('start' === $metaSlug ? '' : $metaSlug),
            'robots'      => 'index,follow',
            'og_image'    => '/assets/og/start.jpg',
        ];

        return $pageMeta;
    }
}
