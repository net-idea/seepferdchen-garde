<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\FormBookingEntity;
use App\Entity\FormSubmissionMetaEntity;
use App\Form\FormBookingType;
use App\Repository\FormBookingRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FormBookingService extends AbstractFormService
{
    /**
     * Route names, session keys and rate-limiting configuration.
     */
    private const ROUTE_BOOKING = 'app_anmeldung';
    private const ROUTE_BOOKING_CONFIRM = 'app_anmeldung_confirm';

    private const SESSION_DATA_KEY = 'bf_data';
    private const SESSION_RATE_KEY = 'bf_times';
    private const SESSION_LAST_ID_KEY = 'bf_last_id';

    private const RATE_WINDOW_SECONDS = 3600; // 1 hour sliding window
    private const RATE_MIN_INTERVAL_SECONDS = 30; // at most 1 submission every 30 seconds
    private const RATE_MAX_PER_WINDOW = 5; // max 5 submissions per window

    private const CONFIRM_STATUS_OK = 'ok';
    private const CONFIRM_STATUS_ALREADY = 'already';
    private const CONFIRM_STATUS_NOTFOUND = 'notfound';

    private ?FormInterface $form = null;
    private ?FormBookingEntity $restoredBooking = null; // cached snapshot for this request

    public function __construct(
        private readonly FormFactoryInterface $forms,
        private readonly RequestStack $requests,
        private readonly EntityManagerInterface $em,
        private readonly FormBookingRepository $bookings,
        private readonly MailManService $mailMan,
        private readonly UrlGeneratorInterface $urls,
        private readonly string $coursePeriod = '04.11.2025 bis 27.01.2026',
    ) {
    }

    public function getForm(): FormInterface
    {
        if (null === $this->form) {
            // Cache restored snapshot once to avoid consuming session twice
            $this->restoredBooking = $this->restoreFormData();
            $entity = $this->restoredBooking ?? new FormBookingEntity();

            if (!$entity->getCoursePeriod()) {
                $entity->setCoursePeriod($this->coursePeriod);
            }

            $this->form = $this->forms->create(FormBookingType::class, $entity);
        }

        return $this->form;
    }

    public function handle(): ?RedirectResponse
    {
        $bootstrap = $this->bootstrapFormHandling($this->requests);
        if (null === $bootstrap) {
            return null;
        }

        [$request, $form, $session] = $bootstrap;

        // Apply rate limit: 1 per 30s, max 5 per hour (sliding 1h window)
        $rl = $this->rateLimitCheck($session, self::SESSION_RATE_KEY, self::RATE_MIN_INTERVAL_SECONDS, self::RATE_MAX_PER_WINDOW, self::RATE_WINDOW_SECONDS);

        if ($rl['blocked']) {
            return $this->makeErrorRedirectWithFormData($this->urls, $form, self::ROUTE_BOOKING, ['error' => 'rate'], '#booking-error');
        }

        // Honeypot check
        $honey = $this->getHoneypotValue($form, 'website');

        /** @var FormBookingEntity $formBooking */
        $formBooking = $form->getData();

        // If honeypot filled, pretend success but do not persist
        if ('' !== $honey) {
            $this->rateLimitTick($session, self::SESSION_RATE_KEY, $rl['times'], $rl['now']);

            return $this->makeRedirect($this->urls, self::ROUTE_BOOKING, ['sent' => 1], '#booking-success');
        }

        if (!$form->isValid()) {
            return null;
        }

        // Store snapshot before attempting to send mail (in case of failure)
        $this->storeFormDataForRedirect($formBooking);

        // Attach submission meta
        $meta = (new FormSubmissionMetaEntity())
            ->setIp((string)$request->server->get('REMOTE_ADDR', ''))
            ->setUserAgent((string)$request->server->get('HTTP_USER_AGENT', ''))
            ->setTime(date('c'))
            ->setHost($request->getHost());
        $formBooking->setMeta($meta);

        // Persist booking form data
        $this->em->persist($formBooking);
        $this->em->flush();

        // Remember last booking id for summary
        $session->set(self::SESSION_LAST_ID_KEY, $formBooking->getId());

        // Build email confirmation link
        $confirmUrl = $this->urls->generate(
            self::ROUTE_BOOKING_CONFIRM,
            [
                'token' => $formBooking->getConfirmationToken(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        try {
            $this->mailMan->sendBookingVisitorConfirmationRequest($formBooking, $confirmUrl);
        } catch (TransportExceptionInterface) {
            return $this->makeErrorRedirectWithFormData($this->urls, $form, self::ROUTE_BOOKING, ['error' => 'mail'], '#booking-error');
        }

        // Success: tick rate limiter; keep snapshot so the next GET can render a summary via restoreFormData().
        $this->rateLimitTick($session, self::SESSION_RATE_KEY, $rl['times'], $rl['now']);

        // Do not clear SESSION_DATA_KEY here. restoreFormData() consumes it on the next request.

        // Include booking id as a robust fallback for the success summary (session-less environments)
        return $this->makeRedirect($this->urls, self::ROUTE_BOOKING, ['sent' => 1, 'bid' => $formBooking->getId()], '#booking-success');
    }

    public function confirmByToken(string $token): string
    {
        $booking = $this->bookings->findOneByToken($token);

        if (!$booking) {
            return self::CONFIRM_STATUS_NOTFOUND;
        }

        if ($booking->isConfirmed()) {
            return self::CONFIRM_STATUS_ALREADY;
        }

        $booking->setConfirmedAt(new DateTimeImmutable());
        $this->em->flush();

        try {
            $this->mailMan->sendBookingOwnerNotification($booking);
        } catch (TransportExceptionInterface) {
            // ignore mail transport failures for owner notification
        }

        return self::CONFIRM_STATUS_OK;
    }

    public function getFormBooking(): ?FormBookingEntity
    {
        if ($this->restoredBooking instanceof FormBookingEntity) {
            return $this->restoredBooking;
        }

        $request = $this->requests->getCurrentRequest();

        if (!$request) {
            return null;
        }

        // Try query parameter first (robust fallback included in redirect)
        $bid = (int)($request->query->get('bid', 0));

        if ($bid > 0) {
            $booking = $this->bookings->find($bid);

            if ($booking instanceof FormBookingEntity) {
                return $booking;
            }
        }

        // Fallback: session last id (may be unavailable in stateless environments)
        $session = $request->getSession();
        $this->assertSessionStarted($session);
        $lastId = (int)($session->get(self::SESSION_LAST_ID_KEY, 0));

        if ($lastId > 0) {
            $booking = $this->bookings->find($lastId);

            if ($booking instanceof FormBookingEntity) {
                return $booking;
            }
        }

        return null;
    }

    public function restoreFormData(): ?FormBookingEntity
    {
        $request = $this->requests->getCurrentRequest();

        if (!$request) {
            return null;
        }

        $session = $request->getSession();
        $this->assertSessionStarted($session);

        if (!$session->has(self::SESSION_DATA_KEY)) {
            return null;
        }

        /** @var array<string,mixed> $data */
        $data = (array)$session->get(self::SESSION_DATA_KEY, []);
        $session->remove(self::SESSION_DATA_KEY); // one-time restore

        $booking = new FormBookingEntity();
        $booking->setCoursePeriod($data['coursePeriod'] ?? $this->coursePeriod);
        $booking->setDesiredTimeSlot($data['desiredTimeSlot'] ?? '');
        $booking->setChildName($data['childName'] ?? '');

        if (!empty($data['childBirthdate'])) {
            $booking->setChildBirthdate(new DateTimeImmutable((string)$data['childBirthdate']));
        }

        $booking->setChildAddress($data['childAddress'] ?? '');
        $booking->setHasSwimExperience((bool)($data['hasSwimExperience'] ?? false));
        $booking->setSwimExperienceDetails($data['swimExperienceDetails'] ?? null);
        $booking->setHealthNotes($data['healthNotes'] ?? null);
        $booking->setMaySwimWithoutAid((bool)($data['maySwimWithoutAid'] ?? false));
        $booking->setParentName($data['parentName'] ?? '');
        $booking->setParentPhone($data['parentPhone'] ?? null);
        $booking->setParentEmail($data['parentEmail'] ?? '');
        $booking->setIsMemberOfClub((bool)($data['isMemberOfClub'] ?? false));
        $booking->setPaymentMethod($data['paymentMethod'] ?? '');
        $booking->setParticipationConsent((bool)($data['participationConsent'] ?? false));
        $booking->setLiabilityAcknowledged((bool)($data['liabilityAcknowledged'] ?? false));
        $booking->setPhotoConsent((bool)($data['photoConsent'] ?? false));
        $booking->setDataConsent((bool)($data['dataConsent'] ?? false));
        $booking->setBookingConfirmation((bool)($data['bookingConfirmation'] ?? false));

        return $booking;
    }

    protected function storeFormDataForRedirect(mixed $data): void
    {
        if ($data instanceof FormBookingEntity) {
            $this->storeFormData($data);
        }
    }

    private function storeFormData(?FormBookingEntity $data): void
    {
        $request = $this->requests->getCurrentRequest();
        if (!$request) {
            return;
        }

        $session = $request->getSession();
        $this->assertSessionStarted($session);

        if (!$data) {
            $session->remove(self::SESSION_DATA_KEY);

            return;
        }

        $session->set(self::SESSION_DATA_KEY, [
            'coursePeriod'          => $data->getCoursePeriod(),
            'desiredTimeSlot'       => $data->getDesiredTimeSlot(),
            'childName'             => $data->getChildName(),
            'childBirthdate'        => $data->getChildBirthdate()?->format('Y-m-d'),
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
}
