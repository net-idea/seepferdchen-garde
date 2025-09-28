<?php
declare(strict_types=1);

namespace App\Entity;

use Symfony\Component\Mime\Address;

class ContactFormEntity
{
    public function __construct(
        protected string $name = '',
        protected string $emailAddress = '',
        protected ?Address $email = null,
        protected string $phone = '',
        protected bool $consent = false,
        protected string $message = '',
        protected bool $copy = true,
        protected string $emailrep = '',
        private ?ContactFormMetaEntity $meta = null,
    ) {
    }

    public function setName($name): self
    {
        $this->name = (string) $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setEmailAddress(string $emailAddress): self
    {
        $this->emailAddress = $emailAddress;

        return $this;
    }

    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }

    public function setEmail(Address $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getEmail(): Address
    {
        if (!$this->email) {
            $this->email = new Address(
                $this->emailAddress,
                $this->name
            );
        }

        return $this->email;
    }

    public function setPhone($phone): self
    {
        $this->phone = (string) $phone;

        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setConsent(bool $consent): self
    {
        $this->consent = $consent;

        return $this;
    }

    public function getConsent(): bool
    {
        return $this->consent;
    }

    public function setMessage($message): self
    {
        $this->message = (string) $message;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setCopy(bool $copy): self
    {
        $this->copy = $copy;

        return $this;
    }

    public function getCopy(): bool
    {
        return $this->copy;
    }

    public function setEmailrep($emailrep): self
    {
        $this->emailrep = (string) $emailrep;

        return $this;
    }

    public function getEmailrep(): string
    {
        return $this->emailrep;
    }

    /**
     * Returns the message formatted as safe HTML with line breaks.
     */
    public function getMessageHtml(): string
    {
        return nl2br(htmlentities($this->message, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'));
    }

    /**
     * Set meta info object.
     */
    public function setMeta(ContactFormMetaEntity $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    /**
     * Get meta info object (never null; returns empty object if not set).
     */
    public function getMeta(): ContactFormMetaEntity
    {
        if (null === $this->meta) {
            $this->meta = new ContactFormMetaEntity();
        }

        return $this->meta;
    }
}
