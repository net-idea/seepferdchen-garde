<?php
declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\FormContactEntity;
use App\Entity\FormSubmissionMetaEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FormContactRepositoryTest extends KernelTestCase
{
    private ?EntityManagerInterface $em = null;

    protected function setUp(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine')->getManager();
        $this->em = $em;

        // Reset schema for a clean slate
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

    public function testPersistAndQueryOrderByCreatedAt(): void
    {
        $older = new FormContactEntity();
        $older->setName('Alice')->setEmailAddress('alice@example.com')->setMessage('Hi')->setConsent(true)->setCopy(true);
        $older->setMeta((new FormSubmissionMetaEntity())->setIp('1.2.3.4')->setUserAgent('UA1')->setTime(date('c'))->setHost('localhost'));

        // Sleep to ensure different createdAt
        usleep(50000);

        $newer = new FormContactEntity();
        $newer->setName('Bob')->setEmailAddress('bob@example.com')->setMessage('Hello')->setConsent(true)->setCopy(false);
        $newer->setMeta((new FormSubmissionMetaEntity())->setIp('5.6.7.8')->setUserAgent('UA2')->setTime(date('c'))->setHost('localhost'));

        $this->em->persist($older);
        $this->em->persist($newer);
        $this->em->flush();

        $repo = $this->em->getRepository(FormContactEntity::class);
        $rows = $repo->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->getQuery()->getResult();

        self::assertCount(2, $rows);
        self::assertSame('Bob', $rows[0]->getName());
        self::assertSame('Alice', $rows[1]->getName());
    }
}
