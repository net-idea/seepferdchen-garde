<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\FormContactEntity;
use App\Entity\FormSubmissionMetaEntity;
use App\Form\FormContactType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FormContactService extends AbstractFormService
{
    private const ROUTE_CONTACT = 'app_contact';

    private const SESSION_DATA_KEY = 'cf_data';
    private const SESSION_RATE_KEY = 'cf_times';

    private ?FormInterface $form = null;

    public function __construct(
        private readonly FormFactoryInterface $forms,
        private readonly RequestStack $requests,
        private readonly MailManService $mailMan,
        private readonly UrlGeneratorInterface $urls,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly FormChallengeService $challenges,
    ) {
    }

    public function getForm(): FormInterface
    {
        if (null === $this->form) {
            $data = $this->restoreFormData();
            $this->form = $this->forms->create(
                FormContactType::class,
                $data ?? new FormContactEntity(),
                ['challenge' => $this->challenges->issue()]
            );
        }

        return $this->form;
    }

    /**
     * Classic (non-JS) flow. Returns a RedirectResponse, or null to re-render the form with errors.
     */
    public function handle(): ?RedirectResponse
    {
        $boot = $this->handleFormRequest($this->requests);

        if (null === $boot) {
            return null;
        }

        [$request, $form, $session] = $boot;

        $result = $this->process($form, $request, $session);

        switch ($result->status) {
            case FormSubmissionStatus::RateLimited:
                return $this->makeErrorRedirectWithFormData($this->urls, $form, self::ROUTE_CONTACT, ['error' => 'rate'], '#contact-error');

            case FormSubmissionStatus::Invalid:
                return null;

            case FormSubmissionStatus::DbError:
                return $this->makeErrorRedirectWithFormData($this->urls, $form, self::ROUTE_CONTACT, ['error' => 'db'], '#contact-error');

            case FormSubmissionStatus::MailError:
                return $this->makeErrorRedirectWithFormData($this->urls, $form, self::ROUTE_CONTACT, ['error' => 'mail'], '#contact-error');

            case FormSubmissionStatus::Spam:
            case FormSubmissionStatus::Ok:
            default:
                $session->remove(self::SESSION_DATA_KEY);

                return $this->makeRedirect($this->urls, self::ROUTE_CONTACT, ['submit' => 1], '#contact-success');
        }
    }

    /**
     * A fresh spam challenge for the next submission (every challenge is valid for one submission only).
     */
    public function issueChallenge(): string
    {
        return $this->challenges->issue();
    }

    public function process(FormInterface $form, Request $request, SessionInterface $session): FormSubmissionResult
    {
        $rl = $this->rateLimitCheck(
            $session,
            self::SESSION_RATE_KEY,
            self::RATE_MIN_INTERVAL_SECONDS,
            self::RATE_MAX_PER_WINDOW,
            self::RATE_WINDOW_SECONDS
        );

        if ($rl['blocked']) {
            return FormSubmissionResult::rateLimited();
        }

        /** @var FormContactEntity $contactForm */
        $contactForm = $form->getData();

        $honeypotFilled = $this->isHoneypotFilled($form);

        if ($honeypotFilled && !$form->isValid()) {
            $this->logger->warning('Contact request rejected: honeypot field was filled', [
                'ip'    => (string)$request->server->get('REMOTE_ADDR', ''),
                'email' => $contactForm->getEmailAddress(),
            ]);
            $this->rateLimitTickNow($session, self::SESSION_RATE_KEY);

            // Nothing storable; pretend success
            return FormSubmissionResult::spam();
        }

        if (!$form->isValid()) {
            return FormSubmissionResult::invalid($this->collectFormErrors($form));
        }

        // Spam checks: suspicious submissions are quarantined (stored, not mailed) instead of rejected
        $spamReasons = $honeypotFilled ? ['honeypot'] : [];
        $challengeFailure = $this->challenges->verify(
            (string)$form->get('challenge')->getData(),
            (string)$form->get('solution')->getData(),
        );

        if (null !== $challengeFailure) {
            $spamReasons[] = $challengeFailure;
        }

        if ([] !== $spamReasons) {
            $contactForm->quarantine($spamReasons);
        }

        $meta = (new FormSubmissionMetaEntity())
            ->setIp((string)$request->server->get('REMOTE_ADDR', ''))
            ->setUserAgent((string)$request->server->get('HTTP_USER_AGENT', ''))
            ->setTime(date('c'))
            ->setHost($request->getHost());
        $contactForm->setMeta($meta);

        $storedInDatabase = true;

        try {
            $this->em->persist($contactForm);
            $this->em->flush();
        } catch (\Throwable $e) {
            $storedInDatabase = false;
            $this->logger->error('Database error while saving contact request', ['exception' => $e->getMessage()]);

            if (!$contactForm->isQuarantined()) {
                return FormSubmissionResult::dbError($contactForm);
            }
        }

        if ($contactForm->isQuarantined()) {
            $context = ['reasons' => $spamReasons, 'email' => $contactForm->getEmailAddress(), 'ip' => $meta->getIp()];

            if ($storedInDatabase) {
                $this->logger->notice('Contact form submission quarantined', $context);
            } else {
                $this->logger->error('Contact form submission quarantined but not stored', $context);
            }

            $this->rateLimitTickNow($session, self::SESSION_RATE_KEY);

            // Pretend success: senders must not be able to tell that they were held back
            return FormSubmissionResult::spam();
        }

        try {
            $this->mailMan->sendContactForm($contactForm);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send contact e-mail', ['exception' => $e->getMessage(), 'contactId' => $contactForm->getId()]);

            return FormSubmissionResult::mailError($contactForm);
        }

        $this->rateLimitTickNow($session, self::SESSION_RATE_KEY);

        return FormSubmissionResult::ok($contactForm);
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
