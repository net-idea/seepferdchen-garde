<?php
declare(strict_types=1);

namespace App\Command;

use App\Repository\FormBookingRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:list:bookings',
    description: 'List stored booking submissions'
)]
class ListBookingsCommand extends Command
{
    public function __construct(private readonly FormBookingRepository $bookings)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit number of rows', '100')
            ->addOption('csv', null, InputOption::VALUE_NONE, 'Output CSV instead of a table');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int)($input->getOption('limit') ?? 100);
        $csv = (bool)$input->getOption('csv');

        $rows = $this->bookings->createQueryBuilder('b')
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();

        $headers = [
            'ID', 'Created At', 'Confirmed', 'Period', 'Time', 'Child', 'Birthdate', 'Parent', 'Email', 'Phone',
            'Member', 'Payment', 'SwimExp', 'SwimDetails', 'WithoutAid', 'HealthNotes',
            'Participation', 'Liability', 'Photo', 'Data', 'BookingConf', 'Address',
        ];
        $data = [];
        foreach ($rows as $b) {
            $data[] = [
                $b->getId(),
                $b->getCreatedAt()->format('Y-m-d H:i:s'),
                $b->isConfirmed() ? 'yes' : 'no',
                $b->getCoursePeriod(),
                $b->getDesiredTimeSlot(),
                $b->getChildName(),
                $b->getChildBirthdate()->format('Y-m-d'),
                $b->getParentName(),
                $b->getParentEmail(),
                $b->getParentPhone() ?? '',
                $b->isMemberOfClub() ? 'yes' : 'no',
                $b->getPaymentMethod(),
                $b->hasSwimExperience() ? 'yes' : 'no',
                $b->getSwimExperienceDetails() ?? '',
                $b->maySwimWithoutAid() ? 'yes' : 'no',
                $b->getHealthNotes() ?? '',
                $b->hasParticipationConsent() ? 'yes' : 'no',
                $b->hasLiabilityAcknowledged() ? 'yes' : 'no',
                $b->hasPhotoConsent() ? 'yes' : 'no',
                $b->hasDataConsent() ? 'yes' : 'no',
                $b->hasBookingConfirmation() ? 'yes' : 'no',
                $b->getChildAddress(),
            ];
        }

        if ($csv) {
            $out = fopen('php://temp', 'r+');
            fputcsv($out, $headers);
            foreach ($data as $row) {
                fputcsv($out, $row);
            }
            rewind($out);
            $output->write(stream_get_contents($out));
            fclose($out);
        } else {
            // For table view, truncate the address and long notes to keep it readable
            $display = array_map(function (array $row) {
                $row[13] = mb_strimwidth((string)$row[13], 0, 40, '…'); // SwimDetails
                $row[14] = $row[14]; // WithoutAid stays
                $row[15] = mb_strimwidth((string)$row[15], 0, 40, '…'); // HealthNotes
                $row[21] = mb_strimwidth((string)$row[21], 0, 40, '…'); // Address

                return $row;
            }, $data);

            $io->title('Bookings');
            $io->table($headers, $display);
            $io->success(sprintf('Total rows displayed: %d', count($data)));
        }

        return Command::SUCCESS;
    }
}
