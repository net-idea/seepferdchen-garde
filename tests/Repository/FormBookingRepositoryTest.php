<?php
declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\FormBookingEntity;
use App\Entity\FormSubmissionMetaEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FormBookingRepositoryTest extends KernelTestCase
{
    private ?EntityManagerInterface $em = null;

    protected function setUp(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine')->getManager();
        $this->em = $em;

        // Reset schema
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->em) {
            $this->em->close();
        }
        $this->em = null;
    }

    public function testFindOneByToken(): void
    {
        $a = $this->makeBooking('a@example.com', 'Child A');
        $b = $this->makeBooking('b@example.com', 'Child B');

        $this->em->persist($a);
        $this->em->persist($b);
        $this->em->flush();

        $token = $b->getConfirmationToken();
        $repo = $this->em->getRepository(FormBookingEntity::class);
        $found = method_exists($repo, 'findOneByToken') ? $repo->findOneByToken($token) : $repo->findOneBy(['confirmationToken' => $token]);

        self::assertInstanceOf(FormBookingEntity::class, $found);
        self::assertSame('b@example.com', $found->getParentEmail());
    }

    public function testOrderByCreatedAtDesc(): void
    {
        $first = $this->makeBooking('first@example.com', 'First');
        usleep(50000);
        $second = $this->makeBooking('second@example.com', 'Second');

        $this->em->persist($first);
        $this->em->persist($second);
        $this->em->flush();

        $repo = $this->em->getRepository(FormBookingEntity::class);
        $rows = $repo->createQueryBuilder('b')
            ->orderBy('b.createdAt', 'DESC')
            ->addOrderBy('b.id', 'DESC')
            ->getQuery()->getResult();

        self::assertCount(2, $rows);
        self::assertSame('second@example.com', $rows[0]->getParentEmail());
        self::assertSame('first@example.com', $rows[1]->getParentEmail());
    }

    private function makeBooking(string $parentEmail, string $childName): FormBookingEntity
    {
        $b = new FormBookingEntity();
        $b->setCoursePeriod('P1');
        $b->setDesiredTimeSlot('Mo 10:00');
        $b->setChildName($childName);
        $b->setChildBirthdate(new \DateTimeImmutable('2018-01-01'));
        $b->setChildAddress('Street 1');
        $b->setHasSwimExperience(false);
        $b->setParentName('Parent');
        $b->setParentEmail($parentEmail);
        $b->setIsMemberOfClub(false);
        $b->setPaymentMethod('barzahlung');
        $b->setParticipationConsent(true);
        $b->setLiabilityAcknowledged(true);
        $b->setPhotoConsent(false);
        $b->setDataConsent(true);
        $b->setBookingConfirmation(true);
        $b->setMeta((new FormSubmissionMetaEntity())->setIp('1.1.1.1')->setUserAgent('UA')->setTime(date('c'))->setHost('localhost'));

        return $b;
    }
}
