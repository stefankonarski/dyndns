<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\IpHistorySource;
use App\Enum\RecordType;
use App\Repository\IpHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IpHistoryRepository::class)]
#[ORM\Table(name: 'ip_history')]
#[ORM\Index(name: 'idx_ip_history_record_type_valid_from', columns: ['record_type', 'valid_from'])]
#[ORM\Index(name: 'idx_ip_history_record_type_valid_to', columns: ['record_type', 'valid_to'])]
#[ORM\Index(name: 'idx_ip_history_ip', columns: ['ip'])]
class IpHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: RecordType::class)]
    private RecordType $recordType;

    #[ORM\Column(length: 64)]
    private string $ip = '';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $validFrom;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validTo = null;

    #[ORM\Column(enumType: IpHistorySource::class)]
    private IpHistorySource $source;

    #[ORM\ManyToOne(targetEntity: DdnsLog::class)]
    #[ORM\JoinColumn(name: 'log_entry_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?DdnsLog $logEntry = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->validFrom = $now;
        $this->createdAt = $now;
        $this->recordType = RecordType::A;
        $this->source = IpHistorySource::Sync;
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

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setIp(string $ip): self
    {
        $this->ip = trim($ip);

        return $this;
    }

    public function getValidFrom(): \DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function setValidFrom(\DateTimeImmutable $validFrom): self
    {
        $this->validFrom = $validFrom;

        return $this;
    }

    public function getValidTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }

    public function setValidTo(?\DateTimeImmutable $validTo): self
    {
        $this->validTo = $validTo;

        return $this;
    }

    public function getSource(): IpHistorySource
    {
        return $this->source;
    }

    public function setSource(IpHistorySource $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getLogEntry(): ?DdnsLog
    {
        return $this->logEntry;
    }

    public function setLogEntry(?DdnsLog $logEntry): self
    {
        $this->logEntry = $logEntry;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

