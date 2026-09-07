<?php
declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\FormBookingEntity;
use App\Repository\FormBookingRepository;
use App\Service\AbstractFormService;
use App\Service\FormBookingService;
use App\Service\FormSubmissionStatus;
use App\Service\MailManService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validation;

/**
 * Covers the submission pipeline that used to swallow real requests:
 * spam detection, validation, persistence and mailing must produce distinct results.
 */
final class FormBookingProcessTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private MailManService&MockObject $mailMan;

    public function testValidSubmissionIsPersistedAndMailed(): void
    {
        [$svc, $request, $session] = $this->makeService();

        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(FormBookingEntity::class));
        $this->em->expects($this->once())->method('flush');
        $this->mailMan->expects($this->once())->method('sendBookingVisitorConfirmationRequest');

        $form = $svc->getForm();
        $form->submit(self::validPayload());

        $result = $svc->process($form, $request, $session);

        $this->assertSame(FormSubmissionStatus::Ok, $result->status);
        $this->assertInstanceOf(FormBookingEntity::class, $result->data);
        $this->assertSame('Mia Muster', $result->data->getChildName());
        $this->assertSame('15:00–15:45', $result->data->getDesiredTimeSlot());
    }

    public function testFilledHoneypotIsRejectedAsSpamWithoutSideEffects(): void
    {
        [$svc, $request, $session] = $this->makeService();

        $this->em->expects($this->never())->method('persist');
        $this->mailMan->expects($this->never())->method('sendBookingVisitorConfirmationRequest');

        $form = $svc->getForm();
        $form->submit(self::validPayload() + [AbstractFormService::HONEYPOT_FIELD => 'http://spam.example']);

        $result = $svc->process($form, $request, $session);

        $this->assertSame(FormSubmissionStatus::Spam, $result->status);
    }

    public function testEmptySubmissionIsInvalidWithFieldErrors(): void
    {
        [$svc, $request, $session] = $this->makeService();

        $this->em->expects($this->never())->method('persist');

        $form = $svc->getForm();
        $form->submit([]);

        $result = $svc->process($form, $request, $session);

        $this->assertSame(FormSubmissionStatus::Invalid, $result->status);
        $this->assertArrayHasKey('childName', $result->errors);
        $this->assertArrayHasKey('desiredTimeSlot', $result->errors);
        $this->assertArrayHasKey('dataConsent', $result->errors);
    }

    public function testFullyBookedTimeSlotIsRejected(): void
    {
        [$svc, $request, $session] = $this->makeService();

        $form = $svc->getForm();
        $form->submit(['desiredTimeSlot' => '16:00–16:45'] + self::validPayload());

        $result = $svc->process($form, $request, $session);

        $this->assertSame(FormSubmissionStatus::Invalid, $result->status);
        $this->assertArrayHasKey('desiredTimeSlot', $result->errors);
    }

    public function testMailFailureStillKeepsTheBookingAndReportsMailError(): void
    {
        [$svc, $request, $session] = $this->makeService();

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $this->mailMan->method('sendBookingVisitorConfirmationRequest')->willThrowException(new \RuntimeException('SMTP down'));

        $form = $svc->getForm();
        $form->submit(self::validPayload());

        $result = $svc->process($form, $request, $session);

        $this->assertSame(FormSubmissionStatus::MailError, $result->status);
        $this->assertInstanceOf(FormBookingEntity::class, $result->data);
    }

    public function testDatabaseFailureReportsDbError(): void
    {
        [$svc, $request, $session] = $this->makeService();

        $this->em->method('flush')->willThrowException(new \RuntimeException('DB down'));
        $this->mailMan->expects($this->never())->method('sendBookingVisitorConfirmationRequest');

        $form = $svc->getForm();
        $form->submit(self::validPayload());

        $result = $svc->process($form, $request, $session);

        $this->assertSame(FormSubmissionStatus::DbError, $result->status);
    }

    /**
     * @return array<string, mixed>
     */
    public static function validPayload(): array
    {
        return [
            'coursePeriod'          => 'Kursplatz-Anfrage',
            'desiredTimeSlot'       => '15:00–15:45',
            'childName'             => 'Mia Muster',
            'childBirthdate'        => '2019-03-04',
            'childAddress'          => "Musterstraße 1\n52134 Herzogenrath",
            'hasSwimExperience'     => '1',
            'swimExperienceDetails' => '',
            'healthNotes'           => '',
            'maySwimWithoutAid'     => '0',
            'parentName'            => 'Max Muster',
            'parentPhone'           => '0176 1234567',
            'parentEmail'           => 'max@example.com',
            'isMemberOfClub'        => '0',
            'paymentMethod'         => 'barzahlung',
            'participationConsent'  => '1',
            'liabilityAcknowledged' => '1',
            'photoConsent'          => '0',
            'dataConsent'           => '1',
            'bookingConfirmation'   => '1',
            '_token'                => 'csrf-token',
        ];
    }

    /**
     * @return array{0: FormBookingService, 1: Request, 2: Session}
     */
    private function makeService(): array
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();

        $request = new Request();
        $request->setSession($session);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $stack = new RequestStack();
        $stack->push($request);

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->mailMan = $this->createMock(MailManService::class);

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://example.test/anmeldung/bestaetigen/abc');

        $svc = new FormBookingService(
            $this->makeFormFactory(),
            $stack,
            $this->em,
            $this->createStub(FormBookingRepository::class),
            $this->mailMan,
            $urls,
            $this->createStub(LoggerInterface::class),
        );

        return [$svc, $request, $session];
    }

    private function makeFormFactory(): FormFactoryInterface
    {
        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(true);
        $csrf->method('getToken')->willReturn(new CsrfToken('submit', 'test-token'));

        return Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension($csrf))
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();
    }
}
