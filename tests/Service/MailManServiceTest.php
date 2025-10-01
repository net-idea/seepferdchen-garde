<?php
declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\FormContactEntity;
use App\Service\MailManService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment as Twig;

class MailManServiceTest extends TestCase
{
    public function testDoesNotSendVisitorEmailWhenCopyFalse(): void
    {
        $mailer = $this->createMock(MailerInterface::class);

        // Only owner email => exactly 1 send
        $mailer->expects($this->exactly(1))
            ->method('send');

        $twig = $this->createMock(Twig::class);

        // Only owner templates are rendered (text + HTML) => 2 times
        $twig->expects($this->exactly(2))
            ->method('render')
            ->willReturn('x');

        $service = $this->makeService($mailer, $twig);
        $service->sendContactForm($this->makeContact(false));
    }

    public function testSendsVisitorEmailWhenCopyTrue(): void
    {
        $mailer = $this->createMock(MailerInterface::class);

        // Owner + Visitor => 2 sends
        $mailer->expects($this->exactly(2))
            ->method('send');

        $twig = $this->createMock(Twig::class);

        // Owner (2) + Visitor (2) => 4 renders
        $twig->expects($this->exactly(4))
            ->method('render')
            ->willReturn('x');

        $service = $this->makeService($mailer, $twig);
        $service->sendContactForm($this->makeContact(true));
    }
    private function makeService(
        MailerInterface $mailer,
        Twig $twig
    ): MailManService {
        return new MailManService(
            $mailer,
            $twig,
            'from@example.com',
            'From Name',
            'to@example.com',
            'To Name',
            new NullLogger(),
        );
    }

    private function makeContact(bool $copy): FormContactEntity
    {
        $contactForm = new FormContactEntity();
        $contactForm->setName('Tester');
        $contactForm->setEmailAddress('visitor@example.com');
        $contactForm->setMessage('Hello');
        $contactForm->setConsent(true);
        $contactForm->setCopy($copy);

        return $contactForm;
    }
}
