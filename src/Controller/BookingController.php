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
        private readonly FormBookingService $formBookingService,
    ) {
    }

    #[Route(
        path: '/anmeldung',
        name: 'app_booking',
        methods: ['GET', 'POST']
    )]
    public function bookingForm(): Response
    {
        $form = $this->formBookingService->getForm();

        if ($response = $this->formBookingService->handle()) {
            return $response;
        }

        return $this->render(
            'pages/anmeldung.html.twig',
            [
                'slug'     => 'anmeldung',
                'navItems' => $this->navigation->getItems(),
                'pageMeta' => $this->loadPageMetadata('anmeldung'),
                'form'     => $form->createView(),
                // Provide a single, consistent booking source (cached restored booking or DB by id)
                'booking' => $this->formBookingService->getFormBooking(),
            ]
        );
    }

    #[Route(
        path: '/anmeldung/bestaetigen/{token}',
        name: 'app_booking_confirm',
        methods: ['GET']
    )]
    public function confirm(string $token): Response
    {
        $navItems = $this->navigation->getItems();
        $status = $this->formBookingService->confirmByToken($token);

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
