<?php
declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\FormBookingEntity;
use App\Repository\FormBookingRepository;
use App\Service\FormBookingService;
use App\Service\MailManService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Validator\Validation;

class FormBookingServiceTest extends TestCase
{
    public function testRestoreFormDataFromSession(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set('bf_data', [
            'coursePeriod'          => '04.11.2025 bis 27.01.2026',
            'desiredTimeSlot'       => 'Di 17:00',
            'childName'             => 'Bob',
            'childBirthdate'        => '2018-02-03',
            'childAddress'          => 'Somewhere 1\n12345 City',
            'hasSwimExperience'     => true,
            'swimExperienceDetails' => '2 Kurse',
            'healthNotes'           => 'all good',
            'maySwimWithoutAid'     => true,
            'parentName'            => 'Parent Bob',
            'parentPhone'           => '555',
            'parentEmail'           => 'parent@example.com',
            'isMemberOfClub'        => false,
            'paymentMethod'         => 'ueberweisung',
            'participationConsent'  => true,
            'liabilityAcknowledged' => true,
            'photoConsent'          => false,
            'dataConsent'           => true,
            'bookingConfirmation'   => true,
        ]);

        $request = new Request();
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $svc = $this->makeService($stack);

        $form = $svc->getForm();
        /** @var FormBookingEntity $data */
        $data = $form->getData();

        $this->assertSame('Bob', $data->getChildName());
        $this->assertSame('Di 17:00', $data->getDesiredTimeSlot());
        $this->assertSame('Parent Bob', $data->getParentName());
        $this->assertSame('parent@example.com', $data->getParentEmail());
        $this->assertTrue($data->hasParticipationConsent());
        $this->assertTrue($data->hasBookingConfirmation());

        // cached form instance
        $this->assertSame($form, $svc->getForm());

        // success summary should prefer restored snapshot
        $summary = $svc->getFormBooking();
        $this->assertInstanceOf(FormBookingEntity::class, $summary);
        $this->assertSame('Bob', $summary->getChildName());
    }

    public function testAssertSessionStartedThrowsWhenSessionCannotStart(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('isStarted')->willReturn(false);
        // start() must return a bool per interface; return false to simulate failure
        $session->method('start')->willReturn(false);

        $request = new Request();
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $svc = $this->makeService($stack);

        $this->expectException(\RuntimeException::class);
        // triggers restore -> assertSessionStarted -> throws
        $svc->getForm();
    }

    public function testFormIsEmptyAfterSentFlagAndSummaryAvailable(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set('bf_data', [
            'coursePeriod'          => '04.11.2025 bis 27.01.2026',
            'desiredTimeSlot'       => '16:00–16:45',
            'childName'             => 'Alice',
            'childBirthdate'        => '2017-05-10',
            'childAddress'          => 'Street 1\nCity',
            'hasSwimExperience'     => true,
            'swimExperienceDetails' => '1 Kurs',
            'healthNotes'           => 'ok',
            'maySwimWithoutAid'     => true,
            'parentName'            => 'Parent A',
            'parentPhone'           => '0123',
            'parentEmail'           => 'a@example.com',
            'isMemberOfClub'        => false,
            'paymentMethod'         => 'ueberweisung',
            'participationConsent'  => true,
            'liabilityAcknowledged' => true,
            'photoConsent'          => false,
            'dataConsent'           => true,
            'bookingConfirmation'   => true,
        ]);

        $request = new Request(['sent' => 1]);
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $svc = $this->makeService($stack);

        $form = $svc->getForm();
        /** @var FormBookingEntity $formData */
        $formData = $form->getData();

        // Form should be empty (new entity) after sent=1
        $this->assertSame('', $formData->getChildName());
        $this->assertSame('', $formData->getDesiredTimeSlot());
        $this->assertSame('', $formData->getParentName());
        $this->assertSame('', $formData->getParentEmail());

        // Summary should still be available from restored snapshot
        $summary = $svc->getFormBooking();
        $this->assertInstanceOf(FormBookingEntity::class, $summary);
        $this->assertSame('Alice', $summary->getChildName());
        $this->assertSame('Parent A', $summary->getParentName());
    }

    private function makeFormFactory(): FormFactoryInterface
    {
        $csrf = new CsrfTokenManager();
        $validator = Validation::createValidator();

        return Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension($csrf))
            ->addExtension(new ValidatorExtension($validator))
            ->getFormFactory();
    }

    private function makeService(RequestStack $stack, ?FormBookingRepository $repo = null): FormBookingService
    {
        $forms = $this->makeFormFactory();
        $mailMan = $this->createMock(MailManService::class);
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $repo ??= $this->createMock(FormBookingRepository::class);

        return new FormBookingService($forms, $stack, $em, $repo, $mailMan, $urls);
    }
}
