<?php
declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\FormChallengeService;
use App\Tests\FormChallengeSolverTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class FormChallengeServiceTest extends TestCase
{
    use FormChallengeSolverTrait;

    private const int ISSUED_AT = 1_800_000_000;

    public function testIssuedChallengeCarriesTimestampSaltDifficultyAndSignature(): void
    {
        $challenge = $this->makeService()->issue(self::ISSUED_AT);

        $this->assertMatchesRegularExpression('/^1800000000\.[0-9a-f]{24}\.14\.[0-9a-f]{64}$/', $challenge);
    }

    public function testEveryChallengeIsUnique(): void
    {
        $service = $this->makeService();

        $this->assertNotSame($service->issue(self::ISSUED_AT), $service->issue(self::ISSUED_AT));
    }

    public function testSolvedChallengeIsAccepted(): void
    {
        $service = $this->makeService();
        $challenge = $service->issue(self::ISSUED_AT);

        $this->assertNull($service->verify($challenge, self::solveChallenge($challenge), self::ISSUED_AT + 30));
    }

    public function testChallengeCanBeUsedOnlyOnce(): void
    {
        $service = $this->makeService();
        $challenge = $service->issue(self::ISSUED_AT);
        $solution = self::solveChallenge($challenge);

        $this->assertNull($service->verify($challenge, $solution, self::ISSUED_AT + 30));
        $this->assertSame(
            FormChallengeService::FAILURE_REUSED,
            $service->verify($challenge, $solution, self::ISSUED_AT + 60),
        );
    }

    public function testFailedAttemptDoesNotConsumeTheChallenge(): void
    {
        $service = $this->makeService();
        $challenge = $service->issue(self::ISSUED_AT);

        $this->assertSame(
            FormChallengeService::FAILURE_POW_MISSING,
            $service->verify($challenge, '', self::ISSUED_AT + 30),
        );
        $this->assertNull($service->verify($challenge, self::solveChallenge($challenge), self::ISSUED_AT + 30));
    }

    public function testMissingChallengeIsRejected(): void
    {
        $this->assertSame(FormChallengeService::FAILURE_MISSING, $this->makeService()->verify('', '0'));
    }

    public function testMalformedChallengeIsRejected(): void
    {
        $this->assertSame(
            FormChallengeService::FAILURE_INVALID,
            $this->makeService()->verify('not-a-challenge', '0', self::ISSUED_AT),
        );
    }

    public function testChallengeWithManipulatedTimestampIsRejected(): void
    {
        $service = $this->makeService();
        [, $salt, $bits, $signature] = explode('.', $service->issue(self::ISSUED_AT));
        $backdated = implode('.', [self::ISSUED_AT - 3600, $salt, $bits, $signature]);

        $this->assertSame(
            FormChallengeService::FAILURE_INVALID,
            $service->verify($backdated, self::solveChallenge($backdated), self::ISSUED_AT + 30),
        );
    }

    public function testChallengeWithLoweredDifficultyIsRejected(): void
    {
        $service = $this->makeService();
        [$issuedAt, $salt, , $signature] = explode('.', $service->issue(self::ISSUED_AT));
        $easy = implode('.', [$issuedAt, $salt, '0', $signature]);

        $this->assertSame(
            FormChallengeService::FAILURE_INVALID,
            $service->verify($easy, '0', self::ISSUED_AT + 30),
        );
    }

    public function testChallengeSignedWithAnotherSecretIsRejected(): void
    {
        $challenge = $this->makeService('another-secret')->issue(self::ISSUED_AT);

        $this->assertSame(
            FormChallengeService::FAILURE_INVALID,
            $this->makeService()->verify($challenge, self::solveChallenge($challenge), self::ISSUED_AT + 30),
        );
    }

    public function testSubmissionFasterThanAHumanIsRejected(): void
    {
        $service = $this->makeService();
        $challenge = $service->issue(self::ISSUED_AT);

        $this->assertSame(
            FormChallengeService::FAILURE_TOO_FAST,
            $service->verify($challenge, self::solveChallenge($challenge), self::ISSUED_AT + 2),
        );
    }

    public function testChallengeIsAcceptedAtTheMinimumAge(): void
    {
        $service = $this->makeService();
        $challenge = $service->issue(self::ISSUED_AT);

        $this->assertNull($service->verify($challenge, self::solveChallenge($challenge), self::ISSUED_AT + 3));
    }

    public function testExpiredChallengeIsRejected(): void
    {
        $service = $this->makeService();
        $challenge = $service->issue(self::ISSUED_AT);

        $this->assertSame(
            FormChallengeService::FAILURE_EXPIRED,
            $service->verify($challenge, self::solveChallenge($challenge), self::ISSUED_AT + 86401),
        );
    }

    public function testMissingSolutionIsRejected(): void
    {
        $service = $this->makeService();

        $this->assertSame(
            FormChallengeService::FAILURE_POW_MISSING,
            $service->verify($service->issue(self::ISSUED_AT), '', self::ISSUED_AT + 30),
        );
    }

    public function testWrongSolutionIsRejected(): void
    {
        $service = $this->makeService();
        $challenge = $service->issue(self::ISSUED_AT);
        // The counter right before the first valid one is guaranteed to be wrong
        $wrongSolution = (string)((int)self::solveChallenge($challenge) - 1);

        $this->assertSame(
            FormChallengeService::FAILURE_POW_INVALID,
            $service->verify($challenge, $wrongSolution, self::ISSUED_AT + 30),
        );
    }

    public function testNonNumericSolutionIsRejected(): void
    {
        $service = $this->makeService();

        $this->assertSame(
            FormChallengeService::FAILURE_POW_INVALID,
            $service->verify($service->issue(self::ISSUED_AT), 'abc', self::ISSUED_AT + 30),
        );
    }

    private function makeService(string $secret = 'test-secret'): FormChallengeService
    {
        return new FormChallengeService($secret, new ArrayAdapter(), sys_get_temp_dir() . '/form_challenge_test.lock');
    }
}
