<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\NavigationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class MainController extends AbstractController
{
    public function __construct(private readonly NavigationService $navigation)
    {
    }

    #[Route(
        path: '/',
        name: 'app_main',
        methods: ['GET']
    )]
    public function main(): Response
    {
        // Render the default "main" page dynamically
        return $this->page('main');
    }

    #[Route(
        path: '/{slug}',
        name: 'app_page',
        requirements: ['slug' => '[a-z0-9\-]*'],
        methods: ['GET'],
        priority: -10
    )]
    public function page(string $slug = 'main'): Response
    {
        $projectDir = (string)$this->getParameter('kernel.project_dir');
        $navItems = $this->navigation->getItems();

        // Load page metadata
        $pagesFile = $projectDir . '/content/_pages.php';
        $pages = is_file($pagesFile) ? (require $pagesFile) : [];
        $metaSlug = ('' === $slug || 'main' === $slug) ? 'start' : $slug;

        /** @var array<string,mixed> $pageMeta */
        $pageMeta = $pages[$metaSlug] ?? [
            'title'       => ucfirst($metaSlug ?: 'Start'),
            'description' => 'Seepferdchen‑Garde Schwimmschule in Herzogenrath Region Aachen: Kurse für Kinder ab 5 Jahren – individuell, sicher und mit Spaß.',
            'canonical'   => '/' . ('start' === $metaSlug ? '' : $metaSlug),
            'robots'      => 'index,follow',
            'og_image'    => '/assets/og/start.jpg',
        ];

        // 1) If a Markdown file exists under content/{slug}.md, render it via Parsedown
        $contentFile = $projectDir . '/content/' . ('' !== $slug ? $slug : 'start') . '.md';

        if (is_file($contentFile)) {
            $markdown = (string)file_get_contents($contentFile);
            $parsedown = new \Parsedown();
            $html = $parsedown->text($markdown);

            return $this->render(
                'pages/content.html.twig',
                [
                    'content'  => $html,
                    'slug'     => $slug,
                    'navItems' => $navItems,
                    'pageMeta' => $pageMeta,
                ]
            );
        }

        // 2) Otherwise, if a Twig page template exists (templates/pages/{slug}.html.twig), render it
        $twigTemplatePath = $projectDir . '/templates/pages/' . ('' !== $slug ? $slug : 'start') . '.html.twig';

        if (is_file($twigTemplatePath)) {
            return $this->render(
                'pages/' . ('' !== $slug ? $slug : 'start') . '.html.twig',
                [
                    'slug'     => $slug,
                    'navItems' => $navItems,
                    'pageMeta' => $pageMeta,
                ]
            );
        }

        // 3) Not found
        throw new NotFoundHttpException('Page not found');
    }
}
