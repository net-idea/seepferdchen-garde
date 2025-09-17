<?php
declare(strict_types=1);

namespace Website;

class PageRepository
{
    private string $dir;
    private array $pages;

    public function __construct(string $dir)
    {
        $this->dir = rtrim($dir, '/');
        $this->pages = require CMS_CONTENT_DIR . '/_pages.php';
    }

    public function allowedPages(): array
    {
        return array_filter($this->pages, fn (array $p) => ($p['cms'] ?? false) === true);
    }

    public function exists(string $slug): bool
    {
        return isset($this->pages[$slug]) && (!(($this->pages[$slug]['cms'] ?? false) === true) || is_file($this->path($slug)));
    }

    public function isCmsManaged(string $slug): bool
    {
        return ($this->pages[$slug]['cms'] ?? false) === true;
    }

    public function get(string $slug): string
    {
        if (!$this->isCmsManaged($slug)) {
            return '';
        }

        return is_file($this->path($slug)) ? file_get_contents($this->path($slug)) : '';
    }

    public function save(string $slug, string $content): void
    {
        if (!$this->isCmsManaged($slug)) {
            throw new RuntimeException('Slug not CMS managed: ' . $slug);
        }

        file_put_contents($this->path($slug), $content);
    }

    public function titleFor(string $slug): string
    {
        return $this->pages[$slug]['title'] ?? ucfirst($slug);
    }

    public function descriptionFor(string $slug): ?string
    {
        return $this->pages[$slug]['description'] ?? null;
    }

    public function pageMeta(string $slug): array
    {
        return $this->pages[$slug] ?? [];
    }

    public function navItems(): array
    {
        $items = array_filter($this->pages, fn ($p) => ($p['nav'] ?? false) === true);

        uasort($items, function ($a, $b) {
            return ($a['nav_order'] ?? 999) <=> ($b['nav_order'] ?? 999);
        });

        return $items;
    }

    private function path(string $slug): string
    {
        return $this->dir . '/' . $slug . '.md';
    }
}
