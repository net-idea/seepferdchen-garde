<?php
declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\FormContactEntity;
use App\Entity\FormSubmissionMetaEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ListContactsCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        $this->em->close();
        parent::tearDown();
    }

    public function testListsDeliveredRequestsOnly(): void
    {
        $this->persistSubmission('Erste Kundin', 'erste@example.com', 'Anfrage zum Kurs.');
        $this->persistSubmission('Bot', 'bot@example.com', 'Buy cheap links now', ['honeypot']);

        $display = $this->listContacts()->getDisplay();

        $this->assertStringContainsString('erste@example.com', $display);
        $this->assertStringNotContainsString('bot@example.com', $display);
        $this->assertStringNotContainsString('Reasons', $display);
    }

    public function testQuarantineOptionListsQuarantinedSubmissionsWithReasons(): void
    {
        $this->persistSubmission('Kundin', 'kundin@example.com', 'Eine ganz normale Anfrage.');
        $this->persistSubmission('Bot One', 'bot1@example.com', 'Buy cheap links now', ['honeypot', 'pow_missing']);
        $this->persistSubmission('Bot Two', 'bot2@example.com', 'More cheap links', ['too_fast']);

        $display = $this->listContacts(['--quarantined' => true])->getDisplay();

        $this->assertStringContainsString('bot1@example.com', $display);
        $this->assertStringContainsString('honeypot,pow_missing', $display);
        $this->assertStringContainsString('203.0.113.7', $display);
        $this->assertStringContainsString('bot2@example.com', $display);
        $this->assertStringContainsString('too_fast', $display);
        $this->assertStringNotContainsString('kundin@example.com', $display);
    }

    public function testCsvContainsQuarantineColumnsOnlyForTheQuarantine(): void
    {
        $this->persistSubmission('Bot', 'bot@example.com', 'Buy cheap links now', ['honeypot']);

        $this->assertStringContainsString('Reasons,IP', $this->listContacts(['--quarantined' => true, '--csv' => true])->getDisplay());
        $this->assertStringNotContainsString('bot@example.com', $this->listContacts(['--csv' => true])->getDisplay());
    }

    public function testShowsSingleSubmissionInFull(): void
    {
        $message = str_repeat('spam ', 40) . "THE-END\nsecond line";
        $id = $this->persistSubmission('Bot', 'bot@example.com', $message, ['honeypot']);

        $tester = $this->listContacts(['id' => (string)$id, '--quarantined' => true]);
        $display = $tester->getDisplay();

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('honeypot', $display);
        $this->assertStringContainsString('TestAgent/1.0', $display);
        $this->assertStringContainsString("THE-END\nsecond line", $display);
    }

    public function testSubmissionOfTheOtherKindIsNotShownById(): void
    {
        $regular = $this->persistSubmission('Kundin', 'kundin@example.com', 'Eine ganz normale Anfrage.');
        $spam = $this->persistSubmission('Bot', 'bot@example.com', 'Buy cheap links now', ['honeypot']);

        $quarantineLookup = $this->listContacts(['id' => (string)$regular, '--quarantined' => true]);
        $deliveredLookup = $this->listContacts(['id' => (string)$spam]);

        $this->assertSame(Command::FAILURE, $quarantineLookup->getStatusCode());
        $this->assertStringContainsString(sprintf('no quarantined submission with ID %d', $regular), $quarantineLookup->getDisplay());
        $this->assertSame(Command::FAILURE, $deliveredLookup->getStatusCode());
        $this->assertStringNotContainsString('bot@example.com', $deliveredLookup->getDisplay());
    }

    public function testControlCharactersAndFormattingTagsFromSpamAreNeutralised(): void
    {
        $id = $this->persistSubmission('Bot', 'bot@example.com', "evil \e[2J<error>tag</error> text", ['honeypot']);

        $display = $this->listContacts(['id' => (string)$id, '--quarantined' => true])->getDisplay();

        $this->assertStringNotContainsString("\e", $display);
        $this->assertStringContainsString('evil [2J<error>tag</error> text', $display);
    }

    /**
     * @param array<string, string|bool> $input
     */
    private function listContacts(array $input = []): CommandTester
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('app:list:contacts'));
        $tester->execute($input);

        return $tester;
    }

    /**
     * @param list<string> $quarantineReasons
     */
    private function persistSubmission(string $name, string $email, string $message, array $quarantineReasons = []): int
    {
        $contact = (new FormContactEntity())
            ->setName($name)
            ->setEmailAddress($email)
            ->setPhone('+49 30 1234567')
            ->setMessage($message)
            ->setConsent(true)
            ->setCopy(false)
            ->setMeta((new FormSubmissionMetaEntity())->setIp('203.0.113.7')->setUserAgent('TestAgent/1.0'));

        if ([] !== $quarantineReasons) {
            $contact->quarantine($quarantineReasons);
        }

        $this->em->persist($contact);
        $this->em->flush();

        return (int)$contact->getId();
    }
}
