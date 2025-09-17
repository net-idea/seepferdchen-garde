<?php
declare(strict_types=1);

namespace Website\Model;

final class PageModel
{
    public string $slug;
    public string $title;
    public string $description;
    public bool $cms;
    public bool $nav;
    public ?string $navLabel;
    public ?int $navOrder;
    public ?string $canonical;

    public function __construct(
        string $slug,
        string $title,
        string $description,
        ?bool $cms,
        ?bool $nav,
        ?string $navLabel = null,
        ?int $navOrder = null,
        ?string $canonical = null
    ) {
        $this->slug = $slug;
        $this->title = $title;
        $this->description = $description;
        $this->cms = $cms ?? true;
        $this->nav = $nav ?? false;
        $this->navLabel = $navLabel ?? $title;
        $this->navOrder = $navOrder ?? 100;
        $this->canonical = $canonical ?? $slug;
    }

    public static function fromArray(string $slug, array $data): self
    {
        return new self(
            $slug,
            (string)($data['title'] ?? ''),
            (string)($data['description'] ?? ''),
            (bool)($data['cms'] ?? false),
            (bool)($data['nav'] ?? false),
            isset($data['nav_label']) ? (string)$data['nav_label'] : (string)($data['title'] ?? ''),
            isset($data['nav_order']) ? (int)$data['nav_order'] : 100,
            isset($data['canonical']) ? (string)$data['canonical'] : $slug
        );
    }
}
