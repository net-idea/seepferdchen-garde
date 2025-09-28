<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\ContactFormService;
use App\Service\NavigationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    public function __construct(
        private readonly NavigationService $navigation,
        private readonly ContactFormService $contactMail,
    ) {
    }

    #[Route(
        path: '/kontakt',
        name: 'app_contact',
        methods: ['GET', 'POST']
    )]
    public function contact(): Response
    {
        // Always build the form (handler manages a single instance)
        $form = $this->contactMail->getForm();

        // Delegate POST handling to the service
        if ($response = $this->contactMail->handle()) {
            return $response;
        }

        // Render page (GET or invalid POST)
        $navItems = $this->navigation->getItems();

        return $this->render(
            'pages/kontakt.html.twig',
            [
                'slug'     => 'kontakt',
                'navItems' => $navItems,
                'form'     => $form->createView(),
            ]
        );
    }
}
