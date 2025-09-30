<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'form_submission_meta')]
class FormSubmissionMetaEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(type: 'string', length: 400, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private ?string $time = null;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    private ?string $host = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }
    public function setIp(?string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }
    public function setUserAgent(?string $ua): self
    {
        $this->userAgent = $ua;

        return $this;
    }

    public function getTime(): ?string
    {
        return $this->time;
    }
    public function setTime(?string $time): self
    {
        $this->time = $time;

        return $this;
    }

    public function getHost(): ?string
    {
        return $this->host;
    }
    public function setHost(?string $host): self
    {
        $this->host = $host;

        return $this;
    }
}
