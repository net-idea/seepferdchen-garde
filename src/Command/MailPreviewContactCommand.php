<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\FormContactEntity;
use App\Entity\FormSubmissionMetaEntity;
use App\Service\MailManService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:mail:preview-contact',
    description: 'Send preview emails (owner + visitor) to verify email templates'
)]
class MailPreviewContactCommand extends Command
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
            ->addArgument('toMail', InputArgument::OPTIONAL, 'Override recipient email address')
            ->addArgument('toName', InputArgument::OPTIONAL, 'Override recipient name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Read configured parameters, fallback to getenv for safety
        $toAddress = (string) ($input->getArgument('toMail') ?: ($this->params->get('mail.to_address') ?? getenv('MAIL_TO_ADDRESS') ?: 'noreply@example.com'));
        $toName = (string) ($input->getArgument('toName') ?: ($this->params->get('mail.to_name') ?? getenv('MAIL_TO_NAME') ?: 'Test Sender'));

        $formContact = new FormContactEntity();
        $formContact->setEmailAddress($toAddress);
        $formContact->setName($toName);
        $formContact->setPhone('+49 170 1234567');
        $formContact->setMessage("Hallo, ich interessiere mich für einen Schwimmkurs. Können Sie mir bitte weitere Informationen zusenden?\nVielen Dank!");
        $formContact->setConsent(true);
        $formContact->setCopy(true); // ensures visitor mail is also sent

        $formSubmissionMeta = (new FormSubmissionMetaEntity())
            ->setIp('127.0.0.1')
            ->setUserAgent('Preview/1.0')
            ->setTime((new \DateTimeImmutable())->format('Y-m-d H:i:s'))
            ->setHost(gethostname() ?: 'localhost');
        $formContact->setMeta($formSubmissionMeta);

        $this->mailMan->sendContactForm($formContact);

        $output->writeln('<info>Preview emails queued/sent. Check your inbox (' . $toAddress . ').</info>');

        return Command::SUCCESS;
    }
}
