<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\FormContactEntity;
use App\Entity\FormSubmissionMetaEntity;
use App\Form\FormContactType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FormContactService extends AbstractFormService
{
    private const ROUTE_CONTACT = 'app_contact';

    private const SESSION_DATA_KEY = 'cf_data';
    private const SESSION_RATE_KEY = 'cf_times';

    private const RATE_WINDOW_SECONDS = 3600; // 1h sliding window
    private const RATE_MIN_INTERVAL_SECONDS = 20; // at most 1 submission every 20s
    private const RATE_MAX_PER_WINDOW = 5; // max 5 submissions per window

    private ?FormInterface $form = null;

    public function __construct(
        private readonly FormFactoryInterface $forms,
        private readonly RequestStack $requests,
        private readonly MailManService $mailMan,
        private readonly UrlGeneratorInterface $urls,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function getForm(): FormInterface
    {
        if (null === $this->form) {
            $data = $this->restoreFormData();
            $this->form = $this->forms->create(
                FormContactType::class,
                $data ?? new FormContactEntity()
            );
        }

        return $this->form;
    }

    /**
     * Handle contact form submission. Returns a RedirectResponse on success or when
     * spam/rate-limit/mail errors occur, or null to re-render the form with errors.
     */
    public function handle(): ?RedirectResponse
    {
        $boot = $this->bootstrapFormHandling($this->requests);

        if (null === $boot) {
            return null;
        }

        [$request, $form, $session] = $boot;

        // Rate limiting: max 1 per 20s, 5 per hour (sliding window)
        $rl = $this->rateLimitCheck(
            $session,
            self::SESSION_RATE_KEY,
            self::RATE_MIN_INTERVAL_SECONDS,
            self::RATE_MAX_PER_WINDOW,
            self::RATE_WINDOW_SECONDS
        );

        if ($rl['blocked']) {
            return $this->makeErrorRedirectWithFormData($this->urls, $form, self::ROUTE_CONTACT, ['error' => 'rate'], '#contact-error');
        }

        // Honeypot: hidden website field (unmapped) or emailrep must be empty => if filled, pretend success
        $honey = $this->getHoneypotValue($form, 'website');

        /** @var FormContactEntity $contactForm */
        $contactForm = $form->getData();

        if ('' !== $honey || '' !== trim((string)$contactForm->getEmailrep())) {
            $this->rateLimitTick($session, self::SESSION_RATE_KEY, $rl['times'], $rl['now']);

            return $this->makeRedirect($this->urls, self::ROUTE_CONTACT, ['sent' => 1], '#contact-success');
        }

        if (!$form->isValid()) {
            return null; // Let the controller re-render with validation errors
        }

        // Store snapshot before attempting to send mail (in case of failure)
        $this->storeFormDataForRedirect($contactForm);

        // Prepare meta-data for email and set inside the entity
        $meta = (new FormSubmissionMetaEntity())
            ->setIp((string)($request->server->get('REMOTE_ADDR', '')))
            ->setUserAgent((string)($request->server->get('HTTP_USER_AGENT', '')))
            ->setTime(date('c'))
            ->setHost($request->getHost());
        $contactForm->setMeta($meta);

        // Persist submission to DB
        $this->em->persist($contactForm);
        $this->em->flush();

        try {
            $this->mailMan->sendContactForm($contactForm);
            $this->rateLimitTick($session, self::SESSION_RATE_KEY, $rl['times'], $rl['now']);

            // Clear snapshot after successful send
            $session->remove(self::SESSION_DATA_KEY);

            return $this->makeRedirect($this->urls, self::ROUTE_CONTACT, ['sent' => 1], '#contact-success');
        } catch (TransportExceptionInterface) {
            return $this->makeErrorRedirectWithFormData($this->urls, $form, self::ROUTE_CONTACT, ['error' => 'mail'], '#contact-error');
        }
    }

    /**
     * Persist a sanitized snapshot of the form data in the session so it can be restored after a redirect.
     */
    protected function storeFormDataForRedirect(mixed $data): void
    {
        if (!$data instanceof FormContactEntity) {
            return;
        }

        $request = $this->requests->getCurrentRequest();

        if (!$request) {
            return;
        }

        $session = $request->getSession();
        $this->assertSessionStarted($session);

        $session->set(self::SESSION_DATA_KEY, [
            'name'         => $data->getName(),
            'emailAddress' => $data->getEmailAddress(),
            'phone'        => $data->getPhone(),
            'message'      => $data->getMessage(),
            'consent'      => $data->getConsent(),
            'copy'         => $data->getCopy(),
            // Note: do not persist honeypots or meta
        ]);
    }

    /**
     * Restore form data from the session if present and clear it so it is used only once.
     */
    private function restoreFormData(): ?FormContactEntity
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

        $data = (array)$session->get(self::SESSION_DATA_KEY, []);
        $session->remove(self::SESSION_DATA_KEY);

        $contact = new FormContactEntity();
        $contact->setName($data['name'] ?? '');
        $contact->setEmailAddress($data['emailAddress'] ?? '');
        $contact->setPhone($data['phone'] ?? '');
        $contact->setMessage($data['message'] ?? '');
        $contact->setConsent(isset($data['consent']) && (bool)$data['consent']);
        $contact->setCopy(isset($data['copy']) && (bool)$data['copy']);

        return $contact;
    }
}
