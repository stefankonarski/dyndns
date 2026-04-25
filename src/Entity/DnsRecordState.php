<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RecordType;
use App\Repository\DnsRecordStateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DnsRecordStateRepository::class)]
#[ORM\Table(name: 'dns_record_state')]
#[ORM\UniqueConstraint(name: 'uniq_dns_record_state', columns: ['zone_id', 'name', 'record_type'])]
#[ORM\HasLifecycleCallbacks]
class DnsRecordState
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: RecordType::class)]
    private RecordType $recordType;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $recordId = null;

    #[ORM\Column(length: 64)]
    private string $zoneId = '';

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 255)]
    private string $value = '';

    #[ORM\Column]
    private int $ttl = 120;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lastSyncedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->lastSyncedAt = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->recordType = RecordType::A;
    }

    #[ORM\PrePersist]
    public function onCreate(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecordType(): RecordType
    {
        return $this->recordType;
    }

    public function setRecordType(RecordType $recordType): self
    {
        $this->recordType = $recordType;

        return $this;
    }

    public function getRecordId(): ?string
    {
        return $this->recordId;
    }

    public function setRecordId(?string $recordId): self
    {
        $this->recordId = $recordId;

        return $this;
    }

    public function getZoneId(): string
    {
        return $this->zoneId;
    }

    public function setZoneId(string $zoneId): self
    {
        $this->zoneId = $zoneId;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = mb_strtolower(trim($name));

        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = trim($value);

        return $this;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function setTtl(int $ttl): self
    {
        $this->ttl = $ttl;

        return $this;
    }

    public function getLastSyncedAt(): \DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function setLastSyncedAt(\DateTimeImmutable $lastSyncedAt): self
    {
        $this->lastSyncedAt = $lastSyncedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

