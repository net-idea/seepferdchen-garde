<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\BookingFormService;
use App\Service\NavigationService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BookingController extends AbstractBaseController
{
    public function __construct(
        private readonly NavigationService $navigation,
        private readonly BookingFormService $bookingService,
    ) {
    }

    #[Route(
        '/anmeldung',
        name: 'app_anmeldung',
        methods: ['GET', 'POST']
    )]
    public function bookingForm(): Response
    {
        $projectDir = (string)$this->getParameter('kernel.project_dir');
        $navItems = $this->navigation->getItems();
        $pageMeta = $this->loadPageMetadata($projectDir, 'anmeldung');

        // Build the form
        $form = $this->bookingService->getForm();

        // Delegate submit handling
        if ($response = $this->bookingService->handle()) {
            return $response;
        }

        return $this->render(
            'pages/anmeldung.html.twig',
            [
                'slug'     => 'anmeldung',
                'navItems' => $navItems,
                'pageMeta' => $pageMeta,
                'form'     => $form->createView(),
                'booking'  => $this->bookingService->getLastSubmittedBooking(),
            ]
        );
    }

    #[Route(
        '/anmeldung/bestaetigen/{token}',
        name: 'app_anmeldung_confirm',
        methods: ['GET']
    )]
    public function confirm(string $token): Response
    {
        $navItems = $this->navigation->getItems();
        $status = $this->bookingService->confirmByToken($token);

        return $this->render(
            'pages/anmeldung_confirm.html.twig',
            [
                'slug'     => 'anmeldung',
                'navItems' => $navItems,
                'status'   => $status,
            ]
        );
    }
}
