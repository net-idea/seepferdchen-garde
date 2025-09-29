<?php
declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\\Repository\\BookingRepository')]
#[ORM\Table(name: 'booking')]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $confirmationToken;

    #[ORM\Column(type: 'string', length: 100)]
    private string $coursePeriod = '';

    #[ORM\Column(type: 'string', length: 32)]
    private string $desiredTimeSlot = '';

    // Child data
    #[ORM\Column(type: 'string', length: 160)]
    private string $childName = '';

    #[ORM\Column(type: 'date_immutable')]
    private DateTimeImmutable $childBirthdate;

    #[ORM\Column(type: 'text')]
    private string $childAddress = '';

    // Health
    #[ORM\Column(type: 'boolean')]
    private bool $hasSwimExperience = false;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $swimExperienceDetails = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $healthNotes = null;

    #[ORM\Column(type: 'boolean')]
    private bool $maySwimWithoutAid = false;

    // Parent
    #[ORM\Column(type: 'string', length: 160)]
    private string $parentName = '';

    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private ?string $parentPhone = null;

    #[ORM\Column(type: 'string', length: 200)]
    private string $parentEmail = '';

    // Organization
    #[ORM\Column(type: 'boolean')]
    private bool $isMemberOfClub = false;

    #[ORM\Column(type: 'string', length: 20)]
    private string $paymentMethod = ''; // barzahlung | ueberweisung | paypal

    // Consents
    #[ORM\Column(type: 'boolean')]
    private bool $participationConsent = false;

    #[ORM\Column(type: 'boolean')]
    private bool $liabilityAcknowledged = false;

    #[ORM\Column(type: 'boolean')]
    private bool $photoConsent = false;

    #[ORM\Column(type: 'boolean')]
    private bool $dataConsent = false;

    #[ORM\Column(type: 'boolean')]
    private bool $bookingConfirmation = false;

    // Meta
    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $metaIp = null;

    #[ORM\Column(type: 'string', length: 400, nullable: true)]
    private ?string $metaUa = null;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    private ?string $metaHost = null;

    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    private ?string $metaTime = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->confirmationToken = bin2hex(random_bytes(16));
        // Default birthdate to a reasonable placeholder so the form can render without errors
        $this->childBirthdate = new DateTimeImmutable('2018-01-01');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getConfirmedAt(): ?DateTimeImmutable
    {
        return $this->confirmedAt;
    }
    public function setConfirmedAt(?DateTimeImmutable $dt): self
    {
        $this->confirmedAt = $dt;

        return $this;
    }

    public function getConfirmationToken(): string
    {
        return $this->confirmationToken;
    }
    public function regenerateToken(): self
    {
        $this->confirmationToken = bin2hex(random_bytes(16));

        return $this;
    }

    public function getCoursePeriod(): string
    {
        return $this->coursePeriod;
    }
    public function setCoursePeriod(string $s): self
    {
        $this->coursePeriod = $s;

        return $this;
    }

    public function getDesiredTimeSlot(): string
    {
        return $this->desiredTimeSlot;
    }
    public function setDesiredTimeSlot(string $s): self
    {
        $this->desiredTimeSlot = $s;

        return $this;
    }

    public function getChildName(): string
    {
        return $this->childName;
    }
    public function setChildName(string $s): self
    {
        $this->childName = $s;

        return $this;
    }

    public function getChildBirthdate(): DateTimeImmutable
    {
        return $this->childBirthdate;
    }
    public function setChildBirthdate(DateTimeImmutable $d): self
    {
        $this->childBirthdate = $d;

        return $this;
    }

    public function getChildAddress(): string
    {
        return $this->childAddress;
    }
    public function setChildAddress(string $s): self
    {
        $this->childAddress = $s;

        return $this;
    }

    public function hasSwimExperience(): bool
    {
        return $this->hasSwimExperience;
    }
    public function setHasSwimExperience(bool $b): self
    {
        $this->hasSwimExperience = $b;

        return $this;
    }

    public function getSwimExperienceDetails(): ?string
    {
        return $this->swimExperienceDetails;
    }
    public function setSwimExperienceDetails(?string $s): self
    {
        $this->swimExperienceDetails = $s;

        return $this;
    }

    public function getHealthNotes(): ?string
    {
        return $this->healthNotes;
    }
    public function setHealthNotes(?string $s): self
    {
        $this->healthNotes = $s;

        return $this;
    }

    public function maySwimWithoutAid(): bool
    {
        return $this->maySwimWithoutAid;
    }
    public function setMaySwimWithoutAid(bool $b): self
    {
        $this->maySwimWithoutAid = $b;

        return $this;
    }

    public function getParentName(): string
    {
        return $this->parentName;
    }
    public function setParentName(string $s): self
    {
        $this->parentName = $s;

        return $this;
    }

    public function getParentPhone(): ?string
    {
        return $this->parentPhone;
    }
    public function setParentPhone(?string $s): self
    {
        $this->parentPhone = $s;

        return $this;
    }

    public function getParentEmail(): string
    {
        return $this->parentEmail;
    }
    public function setParentEmail(string $s): self
    {
        $this->parentEmail = $s;

        return $this;
    }

    public function isMemberOfClub(): bool
    {
        return $this->isMemberOfClub;
    }
    public function setIsMemberOfClub(bool $b): self
    {
        $this->isMemberOfClub = $b;

        return $this;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }
    public function setPaymentMethod(string $s): self
    {
        $this->paymentMethod = $s;

        return $this;
    }

    public function hasParticipationConsent(): bool
    {
        return $this->participationConsent;
    }
    public function setParticipationConsent(bool $b): self
    {
        $this->participationConsent = $b;

        return $this;
    }

    public function hasLiabilityAcknowledged(): bool
    {
        return $this->liabilityAcknowledged;
    }
    public function setLiabilityAcknowledged(bool $b): self
    {
        $this->liabilityAcknowledged = $b;

        return $this;
    }

    public function hasPhotoConsent(): bool
    {
        return $this->photoConsent;
    }
    public function setPhotoConsent(bool $b): self
    {
        $this->photoConsent = $b;

        return $this;
    }

    public function hasDataConsent(): bool
    {
        return $this->dataConsent;
    }
    public function setDataConsent(bool $b): self
    {
        $this->dataConsent = $b;

        return $this;
    }

    public function hasBookingConfirmation(): bool
    {
        return $this->bookingConfirmation;
    }
    public function setBookingConfirmation(bool $b): self
    {
        $this->bookingConfirmation = $b;

        return $this;
    }

    public function getMetaIp(): ?string
    {
        return $this->metaIp;
    }
    public function setMetaIp(?string $s): self
    {
        $this->metaIp = $s;

        return $this;
    }

    public function getMetaUa(): ?string
    {
        return $this->metaUa;
    }
    public function setMetaUa(?string $s): self
    {
        $this->metaUa = $s;

        return $this;
    }

    public function getMetaHost(): ?string
    {
        return $this->metaHost;
    }
    public function setMetaHost(?string $s): self
    {
        $this->metaHost = $s;

        return $this;
    }

    public function getMetaTime(): ?string
    {
        return $this->metaTime;
    }
    public function setMetaTime(?string $s): self
    {
        $this->metaTime = $s;

        return $this;
    }

    public function isConfirmed(): bool
    {
        return null !== $this->confirmedAt;
    }
}
