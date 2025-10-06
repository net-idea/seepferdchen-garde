<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\NavigationService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class MainController extends AbstractBaseController
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
        return $this->page('start');
    }

    #[Route(
        path: '/{slug}',
        name: 'app_page',
        requirements: ['slug' => '[a-z0-9\-]*'],
        methods: ['GET'],
        priority: -10
    )]
    public function page(string $slug = 'start'): Response
    {
        $projectDir = (string)$this->getParameter('kernel.project_dir');

        // 1) if a Twig page template exists (templates/pages/{slug}.html.twig), render it

        $twigTemplatePath = $projectDir . '/templates/pages/' . ('' !== $slug ? $slug : 'start') . '.html.twig';

        if (is_file($twigTemplatePath)) {
            return $this->render(
                'pages/' . ('' !== $slug ? $slug : 'start') . '.html.twig',
                [
                    'slug'     => $slug,
                    'navItems' => $this->navigation->getItems(),
                    'pageMeta' => $this->loadPageMetadata($slug),
                ]
            );
        }

        // 2) Otherwise, If a Markdown file exists under content/{slug}.md, render it via Parsedown

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
                    'navItems' => $this->navigation->getItems(),
                    'pageMeta' => $this->loadPageMetadata($slug),
                ]
            );
        }

        throw new NotFoundHttpException('Page not found');
    }
}
