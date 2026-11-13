<?php
declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\FormBookingEntity;
use App\Entity\FormContactEntity;
use App\Service\AbstractFormService;
use App\Service\FormChallengeService;
use App\Tests\FormChallengeSolverTrait;
use App\Tests\Service\FormBookingProcessTest;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end check of the JSON endpoints against the real kernel, the SQLite test
 * database and the null mailer (see .env.test).
 */
final class FormApiTest extends WebTestCase
{
    use FormChallengeSolverTrait;

    /**
     * Same-origin request headers: the stateless CSRF manager accepts a request whose
     * Origin matches the host (browsers always send it for POST), so no token round trip is needed.
     */
    private const HEADERS = [
        'HTTP_ACCEPT'  => 'application/json',
        'HTTP_ORIGIN'  => 'http://localhost',
        'HTTP_REFERER' => 'http://localhost/anmeldung',
    ];
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    /** @var list<object> */
    private array $cleanup = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        // The SQLite test database may predate the current mapping (e.g. the quarantine columns)
        (new SchemaTool($this->em))->updateSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $entity) {
            $managed = $this->em->find($entity::class, $entity->getId());
            if ($managed) {
                $this->em->remove($managed);
            }
        }
        $this->em->flush();

        parent::tearDown();
    }

    public function testBookingIsStoredAndSummaryReturned(): void
    {
        $payload = FormBookingProcessTest::validPayload();
        $payload['childName'] = 'API Testkind ' . bin2hex(random_bytes(3));

        $this->client->request('POST', '/api/booking', ['form_booking' => $payload], [], self::HEADERS);

        $this->assertResponseIsSuccessful();
        $data = json_decode((string)$this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('ok', $data['status']);
        $this->assertStringContainsString($payload['childName'], (string)$data['summaryHtml']);
        $this->assertStringContainsString('id="booking-success-summary"', (string)$data['summaryHtml']);

        $booking = $this->em->getRepository(FormBookingEntity::class)->findOneBy(['childName' => $payload['childName']]);
        $this->assertInstanceOf(FormBookingEntity::class, $booking, 'booking must be persisted');
        $this->cleanup[] = $booking;
        $this->assertSame('max@example.com', $booking->getParentEmail());
        $this->assertFalse($booking->isConfirmed());
    }

    public function testInvalidBookingReturns422WithFieldErrors(): void
    {
        $this->client->request('POST', '/api/booking', ['form_booking' => ['childName' => '', '_token' => 'csrf-token']], [], self::HEADERS);

        $this->assertResponseStatusCodeSame(422);
        $data = json_decode((string)$this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('invalid', $data['status']);
        $this->assertArrayHasKey('childName', $data['errors']);
        $this->assertArrayHasKey('parentEmail', $data['errors']);
    }

    public function testHoneypotSubmissionIsNotStored(): void
    {
        $payload = FormBookingProcessTest::validPayload();
        $payload['childName'] = 'Spambot ' . bin2hex(random_bytes(3));
        $payload[AbstractFormService::HONEYPOT_FIELD] = 'filled by a bot';

        $this->client->request('POST', '/api/booking', ['form_booking' => $payload], [], self::HEADERS);

        $this->assertResponseIsSuccessful();
        $data = json_decode((string)$this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('ok', $data['status']);
        $this->assertNull($data['summaryHtml']);

        $this->assertNull($this->em->getRepository(FormBookingEntity::class)->findOneBy(['childName' => $payload['childName']]));
    }

    public function testContactIsStored(): void
    {
        $name = 'API Kontakt ' . bin2hex(random_bytes(3));
        $this->client->request('POST', '/api/contact', ['form_contact' => [
            'name'    => $name,
            'email'   => 'kontakt@example.com',
            'phone'   => '',
            'message' => 'Ich hätte gern Informationen zum nächsten Kurs.',
            'consent' => '1',
            'copy'    => '1',
            '_token'  => 'csrf-token',
        ] + $this->solvedChallenge()], [], self::HEADERS);

        $this->assertResponseIsSuccessful();
        $data = json_decode((string)$this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('ok', $data['status']);
        // The consumed challenge is replaced for the next message
        $this->assertMatchesRegularExpression('/^\d+\.[0-9a-f]{24}\.\d+\.[0-9a-f]{64}$/', $data['challenge']);

        $contact = $this->em->getRepository(FormContactEntity::class)->findOneBy(['name' => $name]);
        $this->assertInstanceOf(FormContactEntity::class, $contact);
        $this->cleanup[] = $contact;
        $this->assertFalse($contact->isQuarantined());
        $this->assertNull($contact->getQuarantineReason());
    }

    public function testContactWithoutChallengeIsQuarantinedButAnsweredLikeSuccess(): void
    {
        $name = 'API Bot ' . bin2hex(random_bytes(3));
        $this->client->request('POST', '/api/contact', ['form_contact' => [
            'name'    => $name,
            'email'   => 'bot@example.com',
            'message' => 'Posted straight to the API without the challenge.',
            'consent' => '1',
            '_token'  => 'csrf-token',
        ]], [], self::HEADERS);

        $this->assertResponseIsSuccessful();
        $data = json_decode((string)$this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('ok', $data['status']);
        $this->assertArrayHasKey('challenge', $data);

        $contact = $this->em->getRepository(FormContactEntity::class)->findOneBy(['name' => $name]);
        $this->assertInstanceOf(FormContactEntity::class, $contact);
        $this->cleanup[] = $contact;
        $this->assertTrue($contact->isQuarantined());
        $this->assertSame('challenge_missing', $contact->getQuarantineReason());
    }

    public function testContactWithFilledHoneypotIsQuarantinedEvenWithSolvedChallenge(): void
    {
        $name = 'API Honeypot ' . bin2hex(random_bytes(3));
        $this->client->request('POST', '/api/contact', ['form_contact' => [
            'name'                              => $name,
            'email'                             => 'trap@example.com',
            'message'                           => 'This bot filled in the trap field as well.',
            'consent'                           => '1',
            AbstractFormService::HONEYPOT_FIELD => 'http://spam.example',
            '_token'                            => 'csrf-token',
        ] + $this->solvedChallenge()], [], self::HEADERS);

        $this->assertResponseIsSuccessful();
        $contact = $this->em->getRepository(FormContactEntity::class)->findOneBy(['name' => $name]);
        $this->assertInstanceOf(FormContactEntity::class, $contact);
        $this->cleanup[] = $contact;
        $this->assertSame('honeypot', $contact->getQuarantineReason());
    }

    public function testInvalidContactKeepsTheChallengeUsable(): void
    {
        $this->client->request('POST', '/api/contact', ['form_contact' => ['name' => '', '_token' => 'csrf-token']], [], self::HEADERS);

        $this->assertResponseStatusCodeSame(422);
        $data = json_decode((string)$this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('invalid', $data['status']);
        $this->assertArrayNotHasKey('challenge', $data);
    }

    public function testContactPageRendersChallengeFields(): void
    {
        $crawler = $this->client->request('GET', '/kontakt');

        $challenge = $crawler->filter('input[type="hidden"][name="form_contact[challenge]"]');
        $this->assertCount(1, $challenge);
        $this->assertMatchesRegularExpression('/^\d+\.[0-9a-f]{24}\.\d+\.[0-9a-f]{64}$/', (string)$challenge->attr('value'));
        $this->assertCount(1, $crawler->filter('input[type="hidden"][name="form_contact[solution]"]'));
    }

    public function testGetIsNotAllowed(): void
    {
        $this->client->request('GET', '/api/booking');
        $this->assertResponseStatusCodeSame(405);
    }

    /**
     * @return array{challenge: string, solution: string}
     */
    private function solvedChallenge(): array
    {
        return self::issueSolvedChallenge(static::getContainer()->get(FormChallengeService::class));
    }
}
