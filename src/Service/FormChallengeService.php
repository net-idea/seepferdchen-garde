<?php
declare(strict_types=1);

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Invisible spam challenge for public forms: a signed issue timestamp combined with a proof-of-work puzzle.
 *
 * Challenge format: "<issuedAt>.<salt>.<bits>.<signature>". The browser has to find a counter so that
 * sha256("<challenge>.<counter>") starts with <bits> zero bits. Nothing is stored until a challenge is
 * solved; solved challenges are remembered so that each one can be used only once.
 */
class FormChallengeService
{
    public const string FAILURE_MISSING = 'challenge_missing';
    public const string FAILURE_INVALID = 'challenge_invalid';
    public const string FAILURE_EXPIRED = 'challenge_expired';
    public const string FAILURE_REUSED = 'challenge_reused';
    public const string FAILURE_TOO_FAST = 'too_fast';
    public const string FAILURE_POW_MISSING = 'pow_missing';
    public const string FAILURE_POW_INVALID = 'pow_invalid';

    /** Minimum age in seconds; the browser side (contact-form.ts) waits at least this long before sending. */
    public const int MIN_AGE_SECONDS = 3;

    // ~16k hashes on average: well below a second in a browser
    private const int DIFFICULTY_BITS = 14;
    // a tab may stay open for a day before the form is sent
    private const int MAX_AGE_SECONDS = 86400;
    private const int MAX_SOLUTION_LENGTH = 12;
    private const string STORAGE_PREFIX = 'form_challenge_';

    public function __construct(
        #[Autowire(env: 'APP_SECRET')]
        private readonly string $secret,
        private readonly CacheItemPoolInterface $cache,
        // symfony/lock is not installed: a plain flock() keeps the "seen before" check atomic
        #[Autowire('%kernel.project_dir%/var/form_challenge.lock')]
        private readonly string $lockFile,
    ) {
    }

    public function issue(?int $now = null): string
    {
        $payload = sprintf('%d.%s.%d', $now ?? time(), bin2hex(random_bytes(12)), self::DIFFICULTY_BITS);

        return $payload . '.' . $this->sign($payload);
    }

    /**
     * Returns null when the challenge is solved and unused, otherwise one of the FAILURE_* reasons.
     * A successfully verified challenge is consumed and fails with FAILURE_REUSED afterwards.
     */
    public function verify(string $challenge, string $solution, ?int $now = null): ?string
    {
        if ('' === $challenge) {
            return self::FAILURE_MISSING;
        }

        $parts = explode('.', $challenge);

        if (4 !== count($parts)) {
            return self::FAILURE_INVALID;
        }

        [$issuedAt, $salt, $bits, $signature] = $parts;

        if (!hash_equals($this->sign($issuedAt . '.' . $salt . '.' . $bits), $signature)) {
            return self::FAILURE_INVALID;
        }

        $age = ($now ?? time()) - (int)$issuedAt;

        if ($age > self::MAX_AGE_SECONDS) {
            return self::FAILURE_EXPIRED;
        }

        if ($age < self::MIN_AGE_SECONDS) {
            return self::FAILURE_TOO_FAST;
        }

        if ('' === $solution) {
            return self::FAILURE_POW_MISSING;
        }

        if (
            strlen($solution) > self::MAX_SOLUTION_LENGTH
            || !ctype_digit($solution)
            || !$this->hasLeadingZeroBits(hash('sha256', $challenge . '.' . $solution, true), (int)$bits)
        ) {
            return self::FAILURE_POW_INVALID;
        }

        return $this->consume($salt, self::MAX_AGE_SECONDS - $age) ? null : self::FAILURE_REUSED;
    }

    /**
     * Remember a solved challenge until it would expire anyway. Returns false if it was used before.
     */
    private function consume(string $salt, int $remainingSeconds): bool
    {
        $key = self::STORAGE_PREFIX . $salt;
        // Parallel requests with the same challenge must not all pass the "seen before" check
        $handle = @fopen($this->lockFile, 'c');

        if (false === $handle || !flock($handle, LOCK_EX)) {
            return false;
        }

        try {
            $item = $this->cache->getItem($key);

            if ($item->isHit()) {
                return false;
            }

            $item->set(true);
            $item->expiresAfter($remainingSeconds + 60);
            $this->cache->save($item);

            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', 'form-challenge|' . $payload, $this->secret);
    }

    private function hasLeadingZeroBits(string $hash, int $bits): bool
    {
        $fullBytes = intdiv($bits, 8);

        if (substr($hash, 0, $fullBytes) !== str_repeat("\0", $fullBytes)) {
            return false;
        }

        $remainingBits = $bits % 8;

        return 0 === $remainingBits || 0 === (ord($hash[$fullBytes]) >> (8 - $remainingBits));
    }
}
