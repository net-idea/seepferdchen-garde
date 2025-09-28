<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\ContactFormEntity;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment as Twig;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

readonly class MailManService
{
    public function __construct(
        private MailerInterface $mailer,
        private Twig $twig,
        private string $fromAddress,
        private string $fromName,
        private string $toAddress,
        private string $toName,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws RuntimeError
     * @throws LoaderError
     * @throws SyntaxError
     */
    public function sendContactForm(ContactFormEntity $contact): void
    {
        $from = new Address($this->fromAddress, $this->fromName);
        $to = new Address($this->toAddress, $this->toName);

        $context = ['contact' => $contact];

        try {
            $ownerSubject = 'Seepferdchen Garde — Neue Kontaktanfrage';
            $ownerText = $this->twig->render('email/contact_owner.txt.twig', $context);
            $ownerHtml = $this->twig->render('email/contact_owner.html.twig', $context);

            $emailOwner = (new Email())
                ->from($from)
                ->to($to)
                ->replyTo(new Address($contact->getEmailAddress(), $contact->getName()))
                ->subject($ownerSubject)
                ->text($ownerText)
                ->html($ownerHtml);

            $this->mailer->send($emailOwner);

            if ($contact->getCopy()) {
                $visitorSubject = 'Seepferdchen Garde — Ihre Kontaktanfrage';
                $visitorText = $this->twig->render('email/contact_visitor.txt.twig', $context);
                $visitorHtml = $this->twig->render('email/contact_visitor.html.twig', $context);

                $emailVisitor = (new Email())
                    ->from($from)
                    ->to(new Address($contact->getEmailAddress(), $contact->getName()))
                    ->subject($visitorSubject)
                    ->text($visitorText)
                    ->html($visitorHtml);

                $this->mailer->send($emailVisitor);
            }
        } catch (TransportExceptionInterface $e) {
            // Logs transport failures (bad DSN, auth, SSL, DNS, etc.)
            $this->logger->error('Mailer send failed: ' . $e->getMessage(), ['exception' => $e]);

            throw $e;
        }
    }
}
