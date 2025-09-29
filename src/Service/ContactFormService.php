<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\ContactFormEntity;
use App\Entity\ContactFormMetaEntity;
use App\Form\ContactFormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ContactFormService
{
    private ?FormInterface $form = null;

    public function __construct(
        private readonly FormFactoryInterface $forms,
        private readonly RequestStack $requests,
        private readonly MailManService $mailMan,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function getForm(): FormInterface
    {
        if (null === $this->form) {
            $data = $this->restoreFormData();
            $this->form = $this->forms->create(
                ContactFormType::class,
                $data ?? new ContactFormEntity()
            );
        }

        return $this->form;
    }

    /**
     * Handles the contact form submit. Returns a RedirectResponse on success or when
     * spam/rate-limit/mail errors occur, or null to re-render the form with errors.
     */
    public function handle(): ?RedirectResponse
    {
        $request = $this->requests->getCurrentRequest();

        if (!$request) {
            return null;
        }

        $form = $this->getForm();
        $form->handleRequest($request);

        // Only act on submissions
        if (!$form->isSubmitted()) {
            return null;
        }

        // rate limiting: max 1 per 20s, 5 per hour
        $session = $request->getSession();

        if (!$session->isStarted()) {
            $session->start();
        }

        $redirectWith = function (array $params, string $hash) use ($form): RedirectResponse {
            // Preserve current user input across redirects
            $this->storeFormData($form->getData());

            $url = $this->urls->generate('app_contact', $params) . $hash;

            return new RedirectResponse($url);
        };

        $now = time();
        $times = array_values(array_filter((array)$session->get('cf_times', []), static fn ($t) => ($now - (int)$t) < 3600));

        if (!empty($times)) {
            $last = (int)end($times);

            if (($now - $last) < 20 || count($times) >= 5) {
                return $redirectWith(['error' => 'rate'], '#contact-error');
            }
        }

        // Honeypot: hidden website field (unmapped) or emailrep must be empty => if filled, pretend success
        $honey = (string)($form->has('website') ? (string)$form->get('website')->getData() : '');

        /** @var ContactFormEntity $contactForm */
        $contactForm = $form->getData();

        if ('' !== $honey || '' !== trim((string)$contactForm->getEmailrep())) {
            $times[] = $now;
            $session->set('cf_times', $times);

            return new RedirectResponse($this->urls->generate('app_contact', ['sent' => 1]) . '#contact-success');
        }

        if (!$form->isValid()) {
            // Let the controller re-render with validation errors
            return null;
        }

        // Prepare meta-data for email and set inside the entity
        $meta = new ContactFormMetaEntity();
        $meta
            ->setIp((string)($request->server->get('REMOTE_ADDR', '')))
            ->setUa((string)($request->server->get('HTTP_USER_AGENT', '')))
            ->setTime(date('c'))
            ->setHost($request->getHost());

        $contactForm->setMeta($meta);

        try {
            $this->mailMan->sendContactForm($contactForm);
            $times[] = $now;
            $session->set('cf_times', $times);

            return new RedirectResponse($this->urls->generate('app_contact', ['sent' => 1]) . '#contact-success');
        } catch (TransportExceptionInterface $e) {
            return $redirectWith(['error' => 'mail'], '#contact-error');
        }
    }

    /**
     * Persist a sanitized snapshot of the form data in the session so it can be restored after a redirect.
     */
    private function storeFormData(?ContactFormEntity $data): void
    {
        if (!$data) {
            return;
        }

        $request = $this->requests->getCurrentRequest();

        if (!$request) {
            return;
        }

        $session = $request->getSession();

        if (!$session->isStarted()) {
            $session->start();
        }

        $session->set(
            'cf_data',
            [
                'name'         => $data->getName(),
                'emailAddress' => $data->getEmailAddress(),
                'phone'        => $data->getPhone(),
                'message'      => $data->getMessage(),
                'consent'      => $data->getConsent(),
                'copy'         => $data->getCopy(),
                // Note: do not persist honeypots or meta
            ]
        );
    }

    /**
     * Restore form data from the session if present and clear it so it is used only once.
     */
    private function restoreFormData(): ?ContactFormEntity
    {
        $request = $this->requests->getCurrentRequest();

        if (!$request) {
            return null;
        }

        $session = $request->getSession();

        if (!$session->has('cf_data')) {
            return null;
        }

        $data = (array)$session->get('cf_data', []);
        $session->remove('cf_data');

        $entity = new ContactFormEntity();

        if (isset($data['name'])) {
            $entity->setName($data['name']);
        }

        if (isset($data['emailAddress'])) {
            $entity->setEmailAddress($data['emailAddress']);
        }

        if (isset($data['phone'])) {
            $entity->setPhone($data['phone']);
        }

        if (isset($data['message'])) {
            $entity->setMessage($data['message']);
        }

        if (isset($data['consent'])) {
            $entity->setConsent((bool)$data['consent']);
        }

        if (isset($data['copy'])) {
            $entity->setCopy((bool)$data['copy']);
        }

        return $entity;
    }
}
