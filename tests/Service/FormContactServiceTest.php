<?php
declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\FormContactEntity;
use App\Service\FormContactService;
use App\Service\MailManService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Validator\Validation;

class FormContactServiceTest extends TestCase
{
    public function testFormDataIsRestoredFromSession(): void
    {
        // Seed session with previously entered data
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set('cf_data', [
            'name'         => 'Alice',
            'emailAddress' => 'alice@example.com',
            'phone'        => '123',
            'message'      => 'Hi there',
            'consent'      => true,
            'copy'         => true,
        ]);

        $request = new Request();
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $svc = $this->makeService($stack);

        $form = $svc->getForm();
        $data = $form->getData();
        $this->assertInstanceOf(FormContactEntity::class, $data);
        $this->assertSame('Alice', $data->getName());
        $this->assertSame('alice@example.com', $data->getEmailAddress());
        $this->assertSame('123', $data->getPhone());
        $this->assertSame('Hi there', $data->getMessage());
        $this->assertTrue($data->getConsent());
        $this->assertTrue($data->getCopy());

        // Ensure data is one-time restored (removed after build)
        $form2 = $svc->getForm();
        $this->assertSame($form, $form2, 'Form instance is cached for the request lifecycle');
    }
    private function makeFormFactory(): FormFactoryInterface
    {
        $csrf = new CsrfTokenManager();
        $validator = Validation::createValidator();

        return Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension($csrf))
            ->addExtension(new ValidatorExtension($validator))
            ->getFormFactory();
    }

    private function makeService(RequestStack $stack): FormContactService
    {
        $forms = $this->makeFormFactory();
        $mailMan = $this->createMock(MailManService::class);
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);

        return new FormContactService($forms, $stack, $mailMan, $urls, $em);
    }
}
