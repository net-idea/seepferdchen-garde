<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\Booking;
use App\Service\MailManService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(name: 'app:mail:booking-preview', description: 'Send a preview booking confirmation (visitor) email to verify delivery')]
class BookingMailPreviewCommand extends Command
{
    public function __construct(
        private readonly MailManService $mailMan,
        private readonly ParameterBagInterface $params,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('toMail', InputArgument::OPTIONAL, 'Recipient email address (defaults to MAIL_TO_ADDRESS or mail.to_address)')
            ->addArgument('toName', InputArgument::OPTIONAL, 'Recipient name (defaults to MAIL_TO_NAME or mail.to_name)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $toAddress = (string) ($input->getArgument('toMail') ?: ($this->params->get('mail.to_address') ?? getenv('MAIL_TO_ADDRESS') ?: 'noreply@example.com'));
        $toName = (string) ($input->getArgument('toName') ?: ($this->params->get('mail.to_name') ?? getenv('MAIL_TO_NAME') ?: 'Test Empfänger'));

        // Minimal booking for preview
        $booking = new Booking();
        $booking
            ->setCoursePeriod('04.11.2025 bis 27.01.2026')
            ->setDesiredTimeSlot('16:00–16:45')
            ->setChildName('Max Mustermann')
            ->setChildBirthdate(new \DateTimeImmutable('2018-05-15'))
            ->setChildAddress("Musterstraße 1\n52062 Aachen")
            ->setHasSwimExperience(true)
            ->setSwimExperienceDetails('Kurze Erfahrung im Nichtschwimmerbecken')
            ->setHealthNotes('Keine')
            ->setMaySwimWithoutAid(false)
            ->setParentName($toName)
            ->setParentPhone('+49 170 1234567')
            ->setParentEmail($toAddress)
            ->setIsMemberOfClub(false)
            ->setPaymentMethod('ueberweisung')
            ->setParticipationConsent(true)
            ->setLiabilityAcknowledged(true)
            ->setPhotoConsent(false)
            ->setDataConsent(true)
            ->setBookingConfirmation(true);

        $confirmUrl = 'https://example.com/anmeldung/bestaetigen/' . $booking->getConfirmationToken();

        try {
            $this->mailMan->sendBookingVisitorConfirmationRequest($booking, $confirmUrl);
            $output->writeln('<info>Preview booking confirmation email sent to ' . $toAddress . '</info>');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>Failed to send preview booking confirmation email: ' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}
