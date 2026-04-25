<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DdnsConfigRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DdnsConfigRepository::class)]
#[ORM\Table(name: 'ddns_config')]
#[ORM\HasLifecycleCallbacks]
class DdnsConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $zoneId = null;

    #[ORM\Column(length: 190, nullable: true)]
    private ?string $domain = null;

    #[ORM\Column(length: 190)]
    private string $subdomain = 'home';

    #[ORM\Column(length: 190, nullable: true)]
    private ?string $fritzboxUsername = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $fritzboxPasswordHash = null;

    #[ORM\Column]
    private int $ttl = 120;

    #[ORM\Column]
    private bool $ipv4Enabled = true;

    #[ORM\Column]
    private bool $ipv6Enabled = false;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $manualIpv6 = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
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

    public function getZoneId(): ?string
    {
        return $this->zoneId;
    }

    public function setZoneId(?string $zoneId): self
    {
        $this->zoneId = $zoneId;

        return $this;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function setDomain(?string $domain): self
    {
        $this->domain = null !== $domain ? mb_strtolower(trim($domain)) : null;

        return $this;
    }

    public function getSubdomain(): string
    {
        return $this->subdomain;
    }

    public function setSubdomain(string $subdomain): self
    {
        $this->subdomain = mb_strtolower(trim($subdomain));

        return $this;
    }

    public function getFritzboxUsername(): ?string
    {
        return $this->fritzboxUsername;
    }

    public function setFritzboxUsername(?string $fritzboxUsername): self
    {
        $this->fritzboxUsername = null !== $fritzboxUsername ? trim($fritzboxUsername) : null;

        return $this;
    }

    public function getFritzboxPasswordHash(): ?string
    {
        return $this->fritzboxPasswordHash;
    }

    public function setFritzboxPasswordHash(?string $fritzboxPasswordHash): self
    {
        $this->fritzboxPasswordHash = $fritzboxPasswordHash;

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

    public function isIpv4Enabled(): bool
    {
        return $this->ipv4Enabled;
    }

    public function setIpv4Enabled(bool $ipv4Enabled): self
    {
        $this->ipv4Enabled = $ipv4Enabled;

        return $this;
    }

    public function isIpv6Enabled(): bool
    {
        return $this->ipv6Enabled;
    }

    public function setIpv6Enabled(bool $ipv6Enabled): self
    {
        $this->ipv6Enabled = $ipv6Enabled;

        return $this;
    }

    public function getManualIpv6(): ?string
    {
        return $this->manualIpv6;
    }

    public function setManualIpv6(?string $manualIpv6): self
    {
        $this->manualIpv6 = null !== $manualIpv6 ? mb_strtolower(trim($manualIpv6)) : null;

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

