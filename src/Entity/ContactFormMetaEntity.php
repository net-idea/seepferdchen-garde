<?php
declare(strict_types=1);

namespace App\Entity;

/**
 * Lightweight value object holding request meta for a contact form submission.
 */
class ContactFormMetaEntity
{
    public function __construct(
        private string $ip = '',
        private string $ua = '',
        private string $time = '',
        private string $host = '',
    ) {
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setIp(string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getUa(): string
    {
        return $this->ua;
    }

    public function setUa(string $ua): self
    {
        $this->ua = $ua;

        return $this;
    }

    public function getTime(): string
    {
        return $this->time;
    }

    public function setTime(string $time): self
    {
        $this->time = $time;

        return $this;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setHost(string $host): self
    {
        $this->host = $host;

        return $this;
    }
}
