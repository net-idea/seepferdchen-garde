<?php
declare(strict_types=1);

namespace App\Tests;

use App\Service\FormChallengeService;

/**
 * Plays the browser's part of the spam challenge in tests.
 */
trait FormChallengeSolverTrait
{
    /**
     * Brute-forces the proof-of-work counter exactly like assets/scripts/proof-of-work.ts does.
     */
    protected static function solveChallenge(string $challenge): string
    {
        $bits = (int)explode('.', $challenge)[2];

        for ($counter = 0; ; ++$counter) {
            $prefix = unpack('N', hash('sha256', $challenge . '.' . $counter, true))[1];

            if (0 === $prefix >> (32 - $bits)) {
                return (string)$counter;
            }
        }
    }

    /**
     * A challenge that was issued a moment ago (old enough to pass the minimum fill-in time) and solved.
     *
     * @return array{challenge: string, solution: string}
     */
    protected static function issueSolvedChallenge(FormChallengeService $challenges): array
    {
        $challenge = $challenges->issue(time() - 10);

        return ['challenge' => $challenge, 'solution' => self::solveChallenge($challenge)];
    }
}
