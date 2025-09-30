<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class AbstractFormService
{
    /**
     * Child services must provide a form instance.
     */
    abstract public function getForm(): FormInterface;

    /**
     * Persist a sanitized snapshot of the current form data for redirect restoration.
     * Implement this in each concrete service.
     *
     */
    abstract protected function storeFormDataForRedirect(mixed $data): void;

    /**
     * Ensure the Symfony session is started; attempt to start it and throw if it remains inactive.
     */
    protected function assertSessionStarted(SessionInterface $session): void
    {
        if (!$session->isStarted()) {
            $session->start();
        }

        if (!$session->isStarted()) {
            throw new \RuntimeException('Session could not be started.');
        }
    }

    /**
     * Common bootstrap for form handling: fetch request, bind to form, ensure submission and start session.
     *
     * Returns an array [Request $request, FormInterface $form, SessionInterface $session] or null if not ready.
     *
     * @return array{0: Request, 1: FormInterface, 2: SessionInterface}|null
     */
    protected function bootstrapFormHandling(RequestStack $requests): ?array
    {
        $request = $requests->getCurrentRequest();

        if (!$request) {
            return null;
        }

        $form = $this->getForm();
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return null;
        }

        $session = $request->getSession();

        $this->assertSessionStarted($session);

        return [$request, $form, $session];
    }

    /**
     * Create a RedirectResponse to a route with optional hash suffix.
     */
    protected function makeRedirect(UrlGeneratorInterface $urls, string $route, array $params = [], string $hash = ''): RedirectResponse
    {
        return new RedirectResponse($urls->generate($route, $params) . $hash);
    }

    /**
     * Convenience helper: store current form data, then create a redirect.
     */
    protected function makeErrorRedirectWithFormData(UrlGeneratorInterface $urls, FormInterface $form, string $route, array $params = [], string $hash = ''): RedirectResponse
    {
        $this->storeFormDataForRedirect($form->getData());

        return $this->makeRedirect($urls, $route, $params, $hash);
    }

    /**
     * Read a honeypot field value from a form if present. Returns empty string when missing.
     */
    protected function getHoneypotValue(FormInterface $form, string $field = 'website'): string
    {
        if ($form->has($field)) {
            return (string)$form->get($field)->getData();
        }

        return '';
    }

    /**
     * Rate limit helper: returns whether the action is currently blocked and the filtered timestamps list.
     *
     * @return array{blocked: bool, times: array<int,int>, now: int}
     */
    protected function rateLimitCheck(
        SessionInterface $session,
        string $key,
        int $minIntervalSeconds,
        int $maxPerWindow,
        int $windowSeconds = 3600
    ): array {
        $now = time();
        $stored = (array)$session->get($key, []);
        $times = array_values(array_filter($stored, static fn ($t) => ($now - (int)$t) < $windowSeconds));

        $lastTs = !empty($times) ? (int)end($times) : null;
        $blocked = (null !== $lastTs && ($now - $lastTs) < $minIntervalSeconds) || count($times) >= $maxPerWindow;

        return [
            'blocked' => $blocked,
            'times'   => $times,
            'now'     => $now,
        ];
    }

    /**
     * Append the current timestamp to the rate-limit list and persist it back to the session.
     * Returns the new list of timestamps.
     *
     * @param array<int,int> $times
     * @return array<int,int>
     */
    protected function rateLimitTick(SessionInterface $session, string $key, array $times, int $now): array
    {
        $times[] = $now;
        $session->set($key, $times);

        return $times;
    }
}
