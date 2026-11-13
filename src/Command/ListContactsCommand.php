<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\FormContactEntity;
use App\Repository\FormContactRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:list:contacts',
    description: 'List stored contact form submissions (delivered ones, or the spam quarantine with --quarantined)'
)]
class ListContactsCommand extends Command
{
    private const int EXCERPT_LENGTH = 60;

    public function __construct(private readonly FormContactRepository $contacts)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::OPTIONAL, 'ID of a submission to show in full')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit number of rows', '100')
            ->addOption('csv', null, InputOption::VALUE_NONE, 'Output CSV instead of a table')
            ->addOption('quarantined', 'Q', InputOption::VALUE_NONE, 'List submissions held back as suspected spam instead of the delivered ones');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int)($input->getOption('limit') ?? 100);
        $csv = (bool)$input->getOption('csv');
        $quarantined = (bool)$input->getOption('quarantined');

        $id = $input->getArgument('id');

        if (null !== $id) {
            return $this->showSubmission($io, (int)$id, $quarantined);
        }

        $rows = $this->contacts->createQueryBuilder('c')
            ->where('c.quarantined = :quarantined')
            ->setParameter('quarantined', $quarantined)
            ->orderBy('c.createdAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();

        $headers = ['ID', 'Created At', 'Name', 'Email', 'Phone', 'Consent', 'Copy'];

        if ($quarantined) {
            $headers = [...$headers, 'Reasons', 'IP'];
        }

        $data = [];
        /** @var FormContactEntity $r */
        foreach ($rows as $r) {
            $row = [
                $r->getId(),
                $r->getCreatedAt()->format('Y-m-d H:i:s'),
                $this->clean($r->getName()),
                $this->clean($r->getEmailAddress()),
                $this->clean($r->getPhone()),
                $r->getConsent() ? 'yes' : 'no',
                $r->getCopy() ? 'yes' : 'no',
            ];

            if ($quarantined) {
                $row[] = $this->clean((string)$r->getQuarantineReason());
                $row[] = $this->clean((string)$r->getMeta()->getIp());
            }

            $data[] = $row;
        }

        if ($csv) {
            $out = fopen('php://temp', 'r+');
            fputcsv($out, $headers, escape: '');
            foreach ($data as $row) {
                fputcsv($out, $row, escape: '');
            }
            rewind($out);
            // Raw: stored values must never be parsed as console formatting tags
            $output->write((string)stream_get_contents($out), false, OutputInterface::OUTPUT_RAW);
            fclose($out);
        } else {
            $io->title($quarantined ? 'Quarantined Contact Submissions' : 'Contact Submissions');
            $io->table($headers, array_map(
                fn (array $row): array => array_map(
                    fn (mixed $cell): string => OutputFormatter::escape((string)$cell),
                    $row
                ),
                $data
            ));
            $io->success(sprintf('Total rows displayed: %d. Pass an ID to read one in full.', count($data)));
        }

        return Command::SUCCESS;
    }

    private function showSubmission(SymfonyStyle $io, int $id, bool $quarantined): int
    {
        $contact = $this->contacts->findOneBy(['id' => $id, 'quarantined' => $quarantined]);

        if (null === $contact) {
            $io->error(sprintf(
                'There is no %s with ID %d.',
                $quarantined ? 'quarantined submission' : 'contact request',
                $id
            ));

            return Command::FAILURE;
        }

        $meta = $contact->getMeta();
        $details = [
            ['ID' => (string)$contact->getId()],
            ['Created At' => $contact->getCreatedAt()->format('Y-m-d H:i:s')],
        ];

        if ($quarantined) {
            $details[] = ['Reasons' => $this->escape((string)$contact->getQuarantineReason())];
        }

        $io->definitionList(
            ...$details,
            ...[
                ['Name' => $this->escape($contact->getName())],
                ['Email' => $this->escape($contact->getEmailAddress())],
                ['Phone' => $this->escape($contact->getPhone())],
                ['Copy requested' => $contact->getCopy() ? 'yes' : 'no'],
                ['IP' => $this->escape((string)$meta->getIp())],
                ['User agent' => $this->escape((string)$meta->getUserAgent())],
            ],
        );
        $io->section('Message');
        $io->writeln($this->escape($contact->getMessage(), true));

        return Command::SUCCESS;
    }

    /**
     * Submissions are untrusted input: keep control characters out of the terminal.
     */
    private function clean(string $text, bool $keepLineBreaks = false): string
    {
        $pattern = $keepLineBreaks ? '/[^\P{C}\n]/u' : '/\p{C}/u';

        return (string)preg_replace($pattern, '', $text);
    }

    /**
     * Clean for console output: additionally neutralise formatting tags such as "<error>".
     */
    private function escape(string $text, bool $keepLineBreaks = false): string
    {
        return OutputFormatter::escape($this->clean($text, $keepLineBreaks));
    }
}
