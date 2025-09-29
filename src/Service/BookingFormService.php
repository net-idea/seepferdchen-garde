<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Form\BookingFormType;
use App\Repository\BookingRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class BookingFormService
{
    private ?FormInterface $form = null;

    public function __construct(
        private readonly FormFactoryInterface $forms,
        private readonly RequestStack $requests,
        private readonly EntityManagerInterface $em,
        private readonly BookingRepository $bookings,
        private readonly MailManService $mailMan,
        private readonly UrlGeneratorInterface $urls,
        private readonly string $coursePeriod = '04.11.2025 bis 27.01.2026',
    ) {
    }

    public function getForm(): FormInterface
    {
        if (null === $this->form) {
            $data = $this->restoreFormData();
            $entity = $data ?? new Booking();
            if (!$entity->getCoursePeriod()) {
                $entity->setCoursePeriod($this->coursePeriod);
            }
            $this->form = $this->forms->create(BookingFormType::class, $entity);
        }

        return $this->form;
    }

    public function handle(): ?RedirectResponse
    {
        $request = $this->requests->getCurrentRequest();
        if (!$request) {
            return null;
        }

        $form = $this->getForm();
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return null;
        }

        $session = $request->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        $redirect = function (array $params, string $hash = ''): RedirectResponse {
            return new RedirectResponse($this->urls->generate('app_anmeldung', $params) . $hash);
        };

        // rate limit: 1 per 30s, max 5 per hour
        $now = time();
        $times = array_values(array_filter((array)$session->get('bf_times', []), static fn ($t) => ($now - (int)$t) < 3600));
        if (!empty($times)) {
            $last = (int)end($times);
            if (($now - $last) < 30 || count($times) >= 5) {
                $this->storeFormData($form->getData());

                return $redirect(['error' => 'rate'], '#booking-error');
            }
        }

        // spam trap
        $honey = (string)($form->has('website') ? (string)$form->get('website')->getData() : '');
        /** @var Booking $booking */
        $booking = $form->getData();

        if ('' !== $honey) {
            $times[] = $now;
            $session->set('bf_times', $times);

            return $redirect(['sent' => 1], '#booking-success');
        }

        if (!$form->isValid()) {
            return null; // controller re-renders with errors
        }

        // meta
        $booking
            ->setMetaIp((string)$request->server->get('REMOTE_ADDR', ''))
            ->setMetaUa((string)$request->server->get('HTTP_USER_AGENT', ''))
            ->setMetaTime(date('c'))
            ->setMetaHost($request->getHost());

        // persist booking
        $this->em->persist($booking);
        $this->em->flush();

        // remember last booking id for summary
        $session->set('bf_last_id', $booking->getId());

        // email confirm link
        $confirmUrl = $this->urls->generate('app_anmeldung_confirm', [
            'token' => $booking->getConfirmationToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $this->mailMan->sendBookingVisitorConfirmationRequest($booking, $confirmUrl);
        } catch (TransportExceptionInterface $e) {
            $this->storeFormData($booking);

            return $redirect(['error' => 'mail'], '#booking-error');
        }

        $times[] = $now;
        $session->set('bf_times', $times);
        $this->storeFormData(null);

        return $redirect(['sent' => 1], '#booking-success');
    }

    public function confirmByToken(string $token): string
    {
        $booking = $this->bookings->findOneByToken($token);
        if (!$booking) {
            return 'notfound';
        }
        if ($booking->isConfirmed()) {
            return 'already';
        }
        $booking->setConfirmedAt(new DateTimeImmutable());
        $this->em->flush();

        try {
            $this->mailMan->sendBookingOwnerNotification($booking);
        } catch (TransportExceptionInterface $e) {
            // ignore
        }

        return 'ok';
    }

    public function getLastSubmittedBooking(): ?Booking
    {
        $request = $this->requests->getCurrentRequest();
        if (!$request) {
            return null;
        }
        $session = $request->getSession();
        $id = $session->get('bf_last_id');
        if (!$id) {
            return null;
        }

        return $this->bookings->find((int)$id);
    }

    private function storeFormData(?Booking $data): void
    {
        $request = $this->requests->getCurrentRequest();
        if (!$request) {
            return;
        }
        $session = $request->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        if (!$data) {
            $session->remove('bf_data');

            return;
        }

        $session->set('bf_data', [
            'coursePeriod'          => $data->getCoursePeriod(),
            'desiredTimeSlot'       => $data->getDesiredTimeSlot(),
            'childName'             => $data->getChildName(),
            'childBirthdate'        => $data->getChildBirthdate()->format('Y-m-d'),
            'childAddress'          => $data->getChildAddress(),
            'hasSwimExperience'     => $data->hasSwimExperience(),
            'swimExperienceDetails' => $data->getSwimExperienceDetails(),
            'healthNotes'           => $data->getHealthNotes(),
            'maySwimWithoutAid'     => $data->maySwimWithoutAid(),
            'parentName'            => $data->getParentName(),
            'parentPhone'           => $data->getParentPhone(),
            'parentEmail'           => $data->getParentEmail(),
            'isMemberOfClub'        => $data->isMemberOfClub(),
            'paymentMethod'         => $data->getPaymentMethod(),
            'participationConsent'  => $data->hasParticipationConsent(),
            'liabilityAcknowledged' => $data->hasLiabilityAcknowledged(),
            'photoConsent'          => $data->hasPhotoConsent(),
            'dataConsent'           => $data->hasDataConsent(),
            'bookingConfirmation'   => $data->hasBookingConfirmation(),
        ]);
    }

    private function restoreFormData(): ?Booking
    {
        $request = $this->requests->getCurrentRequest();
        if (!$request) {
            return null;
        }
        $session = $request->getSession();
        if (!$session->has('bf_data')) {
            return null;
        }

        $data = (array)$session->get('bf_data', []);
        $session->remove('bf_data');

        $e = new Booking();
        $e->setCoursePeriod($data['coursePeriod'] ?? $this->coursePeriod);
        $e->setDesiredTimeSlot($data['desiredTimeSlot'] ?? '');
        $e->setChildName($data['childName'] ?? '');
        if (!empty($data['childBirthdate'])) {
            $e->setChildBirthdate(new DateTimeImmutable($data['childBirthdate']));
        }
        $e->setChildAddress($data['childAddress'] ?? '');
        $e->setHasSwimExperience((bool)($data['hasSwimExperience'] ?? false));
        $e->setSwimExperienceDetails($data['swimExperienceDetails'] ?? null);
        $e->setHealthNotes($data['healthNotes'] ?? null);
        $e->setMaySwimWithoutAid((bool)($data['maySwimWithoutAid'] ?? false));
        $e->setParentName($data['parentName'] ?? '');
        $e->setParentPhone($data['parentPhone'] ?? null);
        $e->setParentEmail($data['parentEmail'] ?? '');
        $e->setIsMemberOfClub((bool)($data['isMemberOfClub'] ?? false));
        $e->setPaymentMethod($data['paymentMethod'] ?? '');
        $e->setParticipationConsent((bool)($data['participationConsent'] ?? false));
        $e->setLiabilityAcknowledged((bool)($data['liabilityAcknowledged'] ?? false));
        $e->setPhotoConsent((bool)($data['photoConsent'] ?? false));
        $e->setDataConsent((bool)($data['dataConsent'] ?? false));
        $e->setBookingConfirmation((bool)($data['bookingConfirmation'] ?? false));

        return $e;
    }
}
