<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\FormBookingService;
use App\Service\NavigationService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BookingController extends AbstractBaseController
{
    public function __construct(
        private readonly NavigationService $navigation,
        private readonly FormBookingService $bookingService,
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

        $form = $this->bookingService->getForm();

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
                // Provide a single, consistent booking source (cached restored booking or DB by id)
                'booking' => $this->bookingService->getFormBooking(),
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
