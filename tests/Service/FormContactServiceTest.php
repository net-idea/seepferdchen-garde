<?php
declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\FormContactEntity;
use App\Service\AbstractFormService;
use App\Service\FormChallengeService;
use App\Service\FormContactService;
use App\Service\FormSubmissionResult;
use App\Service\FormSubmissionStatus;
use App\Service\MailManService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Rule\InvokedCount;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Validator\Validation;

class FormContactServiceTest extends TestCase
{
    public function testFormDataIsRestoredFromSession(): void
    {
        // Seed session with previously entered data
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set('cf_data', [
            'name'         => 'Alice',
            'emailAddress' => 'alice@example.com',
            'phone'        => '123',
            'message'      => 'Hi there',
            'consent'      => true,
            'copy'         => true,
        ]);

        $request = new Request();
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $svc = $this->makeService($stack);

        $form = $svc->getForm();
        $data = $form->getData();
        $this->assertInstanceOf(FormContactEntity::class, $data);
        $this->assertSame('Alice', $data->getName());
        $this->assertSame('alice@example.com', $data->getEmailAddress());
        $this->assertSame('123', $data->getPhone());
        $this->assertSame('Hi there', $data->getMessage());
        $this->assertTrue($data->getConsent());
        $this->assertTrue($data->getCopy());

        // Ensure data is one-time restored (removed after build)
        $form2 = $svc->getForm();
        $this->assertSame($form, $form2, 'Form instance is cached for the request lifecycle');
    }
    public function testFormCarriesTheIssuedChallenge(): void
    {
        $stack = new RequestStack();
        $svc = $this->makeService($stack);

        $this->assertSame('test-challenge', $svc->getForm()->get('challenge')->getData());
        $this->assertSame('', (string)$svc->getForm()->get('solution')->getData());
    }

    public function testSubmissionThatFailsTheChallengeIsQuarantinedNotMailed(): void
    {
        $stored = null;
        $result = $this->process(FormChallengeService::FAILURE_POW_MISSING, [], $stored);

        $this->assertSame(FormSubmissionStatus::Spam, $result->status);
        $this->assertInstanceOf(FormContactEntity::class, $stored);
        $this->assertTrue($stored->isQuarantined());
        $this->assertSame('pow_missing', $stored->getQuarantineReason());
    }

    public function testFilledHoneypotOfValidSubmissionIsQuarantined(): void
    {
        $stored = null;
        $result = $this->process(null, [AbstractFormService::HONEYPOT_FIELD => 'http://spam.example'], $stored);

        $this->assertSame(FormSubmissionStatus::Spam, $result->status);
        $this->assertInstanceOf(FormContactEntity::class, $stored);
        $this->assertSame('honeypot', $stored->getQuarantineReason());
    }

    public function testAllFailedChecksAreCollected(): void
    {
        $stored = null;
        $this->process(FormChallengeService::FAILURE_TOO_FAST, [AbstractFormService::HONEYPOT_FIELD => 'x'], $stored);

        $this->assertInstanceOf(FormContactEntity::class, $stored);
        $this->assertSame('honeypot,too_fast', $stored->getQuarantineReason());
    }

    public function testHoneypotOfInvalidSubmissionIsDroppedWithoutStoring(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $result = $this->processWith($em, null, ['name' => '', AbstractFormService::HONEYPOT_FIELD => 'x'], $this->never());

        $this->assertSame(FormSubmissionStatus::Spam, $result->status);
    }

    public function testQuarantinedSubmissionIsAnsweredLikeSuccessEvenIfStoringFails(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willThrowException(new \RuntimeException('db down'));

        $result = $this->processWith($em, FormChallengeService::FAILURE_POW_MISSING, [], $this->never());

        $this->assertSame(FormSubmissionStatus::Spam, $result->status);
    }

    public function testSubmissionPassingAllChecksIsMailed(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $result = $this->processWith($em, null, [], $this->once());

        $this->assertSame(FormSubmissionStatus::Ok, $result->status);
        $this->assertInstanceOf(FormContactEntity::class, $result->data);
        $this->assertFalse($result->data->isQuarantined());
        $this->assertNull($result->data->getQuarantineReason());
    }

    /**
     * @param array<string, string> $overrides
     */
    private function process(?string $challengeFailure, array $overrides, ?object &$stored): FormSubmissionResult
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$stored): void {
                $stored = $entity;
            });

        return $this->processWith($em, $challengeFailure, $overrides, $this->never());
    }

    /**
     * @param array<string, string> $overrides
     */
    private function processWith(EntityManagerInterface $em, ?string $challengeFailure, array $overrides, InvokedCount $mails): FormSubmissionResult
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request = new Request(server: ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'PHPUnit']);
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $mailMan = $this->createMock(MailManService::class);
        $mailMan->expects($mails)->method('sendContactForm');

        $challenges = $this->createStub(FormChallengeService::class);
        $challenges->method('verify')->willReturn($challengeFailure);
        $challenges->method('issue')->willReturn('next-challenge');

        $svc = new FormContactService(
            // No CSRF extension: the token is not what these tests are about
            Forms::createFormFactoryBuilder()->addExtension(new ValidatorExtension(Validation::createValidator()))->getFormFactory(),
            $stack,
            $mailMan,
            $this->createStub(UrlGeneratorInterface::class),
            $em,
            new NullLogger(),
            $challenges,
        );

        $form = $svc->getForm();
        $form->submit($overrides + [
            'name'    => 'Real User',
            'email'   => 'real@example.com',
            'message' => 'A genuine request about the next course.',
            'consent' => '1',
        ]);

        return $svc->process($form, $request, $session);
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

    private function makeService(RequestStack $stack): FormContactService
    {
        $forms = $this->makeFormFactory();
        $mailMan = $this->createStub(MailManService::class);
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);

        $challenges = $this->createStub(FormChallengeService::class);
        $challenges->method('issue')->willReturn('test-challenge');

        return new FormContactService($forms, $stack, $mailMan, $urls, $em, $this->createStub(LoggerInterface::class), $challenges);
    }
}
