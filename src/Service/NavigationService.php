<?php
declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Builds navigation items from content/_pages.php
 */
readonly class NavigationService
{
    public function __construct(private KernelInterface $kernel)
    {
    }

    /**
     * @return array<int, array{slug:string,label:string,url:string,order:int}>
     */
    public function getItems(): array
    {
        $projectDir = $this->kernel->getProjectDir();
        $pagesFile = $projectDir . '/content/_pages.php';

        $items = [];
        if (is_file($pagesFile)) {
            /** @var array<string, array> $pages */
            $pages = require $pagesFile;
            foreach ($pages as $slug => $meta) {
                $showInNav = (bool)($meta['nav'] ?? false);
                if (!$showInNav) {
                    continue;
                }
                $label = (string)($meta['nav_label'] ?? $meta['title'] ?? $slug);
                $order = (int)($meta['nav_order'] ?? 0);
                $url = '/' . ('start' === $slug ? '' : $slug);
                $items[] = [
                    'slug'  => (string)$slug,
                    'label' => $label,
                    'url'   => $url,
                    'order' => $order,
                ];
            }
        }

        usort($items, static fn ($a, $b) => $a['order'] <=> $b['order']);

        return $items;
    }
}
