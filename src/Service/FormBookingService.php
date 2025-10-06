<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\FormBookingEntity;
use App\Entity\FormSubmissionMetaEntity;
use App\Form\FormBookingType;
use App\Repository\FormBookingRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FormBookingService extends AbstractFormService
{
    private const ROUTE_BOOKING = 'app_booking';
    private const ROUTE_BOOKING_CONFIRM = 'app_booking_confirm';

    private const SESSION_FORM_DATA_KEY = 'bf_form';
    private const SESSION_SUMMARY_KEY = 'bf_summary';

    private const SESSION_RATE_KEY = 'bf_times';

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
        private readonly LoggerInterface $logger
    ) {
    }

    public function getForm(): FormInterface
    {
        if (null === $this->form) {
            $submitted = $this->isSubmitFlag();

            $this->restoredBooking = $submitted
                ? $this->restoreSummaryData()
                : $this->restoreFormData();

            $entity = $submitted
                ? new FormBookingEntity()
                : ($this->restoredBooking ?? new FormBookingEntity());

            $this->form = $this->forms->create(FormBookingType::class, $entity);
        }

        return $this->form;
    }

    public function handle(): ?RedirectResponse
    {
        $boot = $this->bootstrapFormHandling($this->requests);

        if (null === $boot) {
            return null;
        }

        [$request, $form, $session] = $boot;

        if ($redirect = $this->enforceRateLimitOrRedirect(
            $session,
            self::SESSION_RATE_KEY,
            self::RATE_MIN_INTERVAL_SECONDS,
            self::RATE_MAX_PER_WINDOW,
            self::RATE_WINDOW_SECONDS,
            $form,
            $this->urls,
            self::ROUTE_BOOKING,
            '#booking-error'
        )) {
            return $redirect;
        }

        // Honeypots
        $honey = trim($this->getHoneypotValue($form, 'website'));
        $honeyAlt = trim($this->getHoneypotValue($form, 'emailrep'));

        /** @var FormBookingEntity $formBooking */
        $formBooking = $form->getData();

        if ('' !== $honey || '' !== $honeyAlt) {
            $this->storeSummarySnapshot($formBooking);
            $this->rateLimitTickNow($session, self::SESSION_RATE_KEY);

            return $this->makeRedirect($this->urls, self::ROUTE_BOOKING, ['submit' => 1], '#booking-success');
        }

        if (!$form->isValid()) {
            $this->storeFormSnapshot($formBooking);

            return null;
        }

        $meta = (new FormSubmissionMetaEntity())
            ->setIp((string)$request->server->get('REMOTE_ADDR', ''))
            ->setUserAgent((string)$request->server->get('HTTP_USER_AGENT', ''))
            ->setTime(date('c'))
            ->setHost($request->getHost());
        $formBooking->setMeta($meta);

        try {
            $this->em->persist($formBooking);
            $this->em->flush();
        } catch (\Exception $e) {
            $this->storeFormSnapshot($formBooking);
            $this->logger->error(
                'Database error while saving booking',
                [
                    'exception' => $e->getMessage(),
                    'ip'        => $meta->getIp(),
                    'userAgent' => $meta->getUserAgent(),
                    'host'      => $meta->getHost(),
                ]
            );

            return $this->makeRedirect($this->urls, self::ROUTE_BOOKING, ['error' => 'db'], '#booking-error');
        }

        $confirmUrl = $this->urls->generate(
            self::ROUTE_BOOKING_CONFIRM,
            ['token' => $formBooking->getConfirmationToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        try {
            $this->mailMan->sendBookingVisitorConfirmationRequest($formBooking, $confirmUrl);
            $emailSent = true;
        } catch (\Exception $e) {
            error_log('ERROR: Failed to send booking confirmation email to ' . $formBooking->getParentEmail() . ': ' . $e->getMessage());
            error_log('Booking ID: ' . $formBooking->getId() . ', Token: ' . $formBooking->getConfirmationToken());

            $this->storeFormSnapshot($formBooking);

            return $this->makeRedirect($this->urls, self::ROUTE_BOOKING, ['error' => 'mail'], '#booking-error');
        }

        $this->rateLimitTickNow($session, self::SESSION_RATE_KEY);
        $this->storeSummarySnapshot($formBooking);

        $this->logger->info(
            'Booking saved and confirmation email sent',
            [
                'bookingId' => $formBooking->getId(),
                'to'        => $formBooking->getParentEmail(),
                'token'     => substr($formBooking->getConfirmationToken(), 0, 6) . '…',
                'ip'        => $meta->getIp(),
                'userAgent' => $meta->getUserAgent(),
                'host'      => $meta->getHost(),
            ]
        );

        return $this->makeRedirect($this->urls, self::ROUTE_BOOKING, ['submit' => 1], '#booking-success');
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

        $this->restoredBooking = $this->isSubmitFlag()
            ? $this->restoreSummaryData()
            : $this->restoreFormData();

        return $this->restoredBooking;
    }

    /**
     * Restore one-time form data snapshot for repopulating the form after errors.
     */
    public function restoreFormData(): ?FormBookingEntity
    {
        $request = $this->requests->getCurrentRequest();

        if (!$request) {
            return null;
        }

        $session = $request->getSession();
        $this->assertSessionStarted($session);

        // Backward compatibility: read old key 'bf_data' if present
        $key = $session->has(self::SESSION_FORM_DATA_KEY) ? self::SESSION_FORM_DATA_KEY : 'bf_data';
        if (!$session->has($key)) {
            return null;
        }

        /** @var array<string,mixed> $data */
        $data = (array)$session->get($key, []);
        $session->remove($key); // one-time restore

        return $this->hydrateBookingFromArray($data);
    }

    /**
     * Restore one-time summary snapshot for success page after redirect.
     */
    public function restoreSummaryData(): ?FormBookingEntity
    {
        $request = $this->requests->getCurrentRequest();

        if (!$request) {
            return null;
        }

        $session = $request->getSession();
        $this->assertSessionStarted($session);

        if (!$session->has(self::SESSION_SUMMARY_KEY)) {
            return null;
        }

        /** @var array<string,mixed> $data */
        $data = (array)$session->get(self::SESSION_SUMMARY_KEY, []);
        $session->remove(self::SESSION_SUMMARY_KEY); // one-time restore

        return $this->hydrateBookingFromArray($data);
    }

    protected function storeFormDataForRedirect(mixed $data): void
    {
        // Legacy no-op to avoid accidental use; use storeFormSnapshot/storeSummarySnapshot directly
        if ($data instanceof FormBookingEntity) {
            $this->storeFormSnapshot($data);
        }
    }

    private function hydrateBookingFromArray(array $data): FormBookingEntity
    {
        $booking = new FormBookingEntity();
        $booking->setCoursePeriod($data['coursePeriod'] ?? '');
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

    /**
     * Convert a booking entity into a session-safe array snapshot.
     * @return array<string,mixed>
     */
    private function dehydrateBookingToArray(FormBookingEntity $data): array
    {
        return [
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
        ];
    }

    private function storeFormSnapshot(?FormBookingEntity $data): void
    {
        $request = $this->requests->getCurrentRequest();
        if (!$request) {
            return;
        }

        $session = $request->getSession();
        $this->assertSessionStarted($session);

        if (!$data) {
            $session->remove(self::SESSION_FORM_DATA_KEY);

            return;
        }

        $session->set(self::SESSION_FORM_DATA_KEY, $this->dehydrateBookingToArray($data));
    }

    private function storeSummarySnapshot(?FormBookingEntity $data): void
    {
        $request = $this->requests->getCurrentRequest();
        if (!$request) {
            return;
        }

        $session = $request->getSession();
        $this->assertSessionStarted($session);

        // Clear any stale form snapshot to ensure empty form on success
        $session->remove(self::SESSION_FORM_DATA_KEY);

        if (!$data) {
            $session->remove(self::SESSION_SUMMARY_KEY);

            return;
        }

        $session->set(self::SESSION_SUMMARY_KEY, $this->dehydrateBookingToArray($data));
    }

    private function isSubmitFlag(): bool
    {
        $request = $this->requests->getCurrentRequest();
        if (!$request) {
            return false;
        }

        $submit = $request->query->get('submit');

        return null !== $submit && '0' !== $submit && 0 !== $submit && false !== $submit && '' !== $submit;
    }
}
