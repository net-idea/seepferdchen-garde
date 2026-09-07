<?php
declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'regenerate-app-secret',
    description: 'Generate a new APP_SECRET and write it to the .env file',
)]
class RegenerateAppSecretCommand extends Command
{
    public function __construct(private readonly ParameterBagInterface $params)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only print the new secret, do not touch .env');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $secret = bin2hex(random_bytes(16));

        if ($input->getOption('dry-run')) {
            $io->success('New APP_SECRET (not written): ' . $secret);

            return Command::SUCCESS;
        }

        $envFile = (string)$this->params->get('kernel.project_dir') . '/.env';

        if (!is_file($envFile) || !is_writable($envFile)) {
            $io->error(sprintf('%s does not exist or is not writable. New secret: %s', $envFile, $secret));

            return Command::FAILURE;
        }

        $contents = (string)file_get_contents($envFile);
        $updated = preg_replace('/^APP_SECRET=.*$/m', 'APP_SECRET=' . $secret, $contents, 1, $count);

        if (null === $updated || 0 === $count) {
            $updated = rtrim($contents, "\n") . "\nAPP_SECRET=" . $secret . "\n";
        }

        file_put_contents($envFile, $updated);

        $io->success('New APP_SECRET was written to .env: ' . $secret);

        return Command::SUCCESS;
    }
}
