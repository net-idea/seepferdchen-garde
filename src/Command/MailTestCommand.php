<?php
declare(strict_types=1);

namespace App\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:mail:test',
    description: 'Send a test email using the configured Symfony Mailer DSN'
)]
class MailTestCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ParameterBagInterface $params,
        private readonly ?LoggerInterface $logger = null,
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
        $fromAddress = (string) ($this->params->get('mail.from_address') ?? getenv('MAIL_FROM_ADDRESS') ?: 'noreply@example.com');
        $fromName = (string) ($this->params->get('mail.from_name') ?? getenv('MAIL_FROM_NAME') ?: 'Test Sender');
        $toAddress = (string) ($input->getArgument('toMail') ?: ($this->params->get('mail.to_address') ?? getenv('MAIL_TO_ADDRESS') ?: $fromAddress));
        $toName = (string) ($input->getArgument('toName') ?: ($this->params->get('mail.to_name') ?? getenv('MAIL_TO_NAME') ?: $fromName));

        $email = (new Email())
            ->from(new Address($fromAddress, $fromName))
            ->to(new Address($toAddress, $toName))
            ->subject('Symfony Mailer Test')
            ->text('This is a test email sent by app:mail:test')
            ->html('<p>This is a <strong>test email</strong> sent by <code>app:mail:test</code>.</p>');

        try {
            $this->mailer->send($email);
            $output->writeln('<info>Test email sent successfully to ' . $toAddress . '</info>');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $msg = 'Failed to send test email: ' . $e->getMessage();

            $this->logger?->error($msg, ['exception' => $e]);
            $output->writeln('<error>' . $msg . '</error>');

            return Command::FAILURE;
        }
    }
}
