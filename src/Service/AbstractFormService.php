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
    // 1 hour sliding window
    protected const RATE_WINDOW_SECONDS = 3600;
    // at most 1 submission every 30 seconds
    protected const RATE_MIN_INTERVAL_SECONDS = 30;
    // max 10 submissions per window
    protected const RATE_MAX_PER_WINDOW = 10;

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

    /**
     * Centralized rate-limit check. If blocked, stores form data and returns a RedirectResponse to the given route with error=rate.
     * On success, returns null and the caller may proceed.
     */
    protected function enforceRateLimitOrRedirect(
        SessionInterface $session,
        string $rateKey,
        int $minIntervalSeconds,
        int $maxPerWindow,
        int $windowSeconds,
        FormInterface $form,
        UrlGeneratorInterface $urls,
        string $route,
        string $hash
    ): ?RedirectResponse {
        $rl = $this->rateLimitCheck($session, $rateKey, $minIntervalSeconds, $maxPerWindow, $windowSeconds);
        if ($rl['blocked']) {
            return $this->makeErrorRedirectWithFormData($urls, $form, $route, ['error' => 'rate'], $hash);
        }

        return null;
    }

    /**
     * Centralized rate-limit tick using current time.
     *
     * @param string $rateKey The session key to store rate-limit timestamps under (must be provided by child service).
     */
    protected function rateLimitTickNow(SessionInterface $session, string $rateKey, int $windowSeconds = self::RATE_WINDOW_SECONDS): void
    {
        $rl = $this->rateLimitCheck($session, $rateKey, 0, PHP_INT_MAX, $windowSeconds);
        $this->rateLimitTick($session, $rateKey, $rl['times'], time());
    }
}
