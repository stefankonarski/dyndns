<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DdnsResult;
use App\Repository\DdnsLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DdnsLogRepository::class)]
#[ORM\Table(name: 'ddns_log')]
#[ORM\Index(name: 'idx_ddns_log_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_ddns_log_result', columns: ['result'])]
#[ORM\Index(name: 'idx_ddns_log_requested_domain', columns: ['requested_domain'])]
#[ORM\Index(name: 'idx_ddns_log_ipaddr', columns: ['ipaddr'])]
#[ORM\Index(name: 'idx_ddns_log_auth_success', columns: ['auth_success'])]
#[ORM\Index(name: 'idx_ddns_log_record_type', columns: ['record_type'])]
class DdnsLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 255)]
    private string $requestPath = '/update';

    #[ORM\Column(length: 16)]
    private string $httpMethod = 'GET';

    #[ORM\Column(length: 190, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(length: 190, nullable: true)]
    private ?string $requestedDomain = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $ipaddr = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $configuredIpv6 = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $clientIp = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column]
    private bool $authSuccess = false;

    #[ORM\Column(enumType: DdnsResult::class)]
    private DdnsResult $result = DdnsResult::InternalError;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column]
    private int $durationMs = 0;

    #[ORM\Column]
    private bool $hetznerCalled = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $normalizedRecordName = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $recordType = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getRequestPath(): string
    {
        return $this->requestPath;
    }

    public function setRequestPath(string $requestPath): self
    {
        $this->requestPath = $requestPath;

        return $this;
    }

    public function getHttpMethod(): string
    {
        return $this->httpMethod;
    }

    public function setHttpMethod(string $httpMethod): self
    {
        $this->httpMethod = $httpMethod;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getRequestedDomain(): ?string
    {
        return $this->requestedDomain;
    }

    public function setRequestedDomain(?string $requestedDomain): self
    {
        $this->requestedDomain = null !== $requestedDomain ? mb_strtolower(trim($requestedDomain)) : null;

        return $this;
    }

    public function getIpaddr(): ?string
    {
        return $this->ipaddr;
    }

    public function setIpaddr(?string $ipaddr): self
    {
        $this->ipaddr = $ipaddr;

        return $this;
    }

    public function getConfiguredIpv6(): ?string
    {
        return $this->configuredIpv6;
    }

    public function setConfiguredIpv6(?string $configuredIpv6): self
    {
        $this->configuredIpv6 = $configuredIpv6;

        return $this;
    }

    public function getClientIp(): ?string
    {
        return $this->clientIp;
    }

    public function setClientIp(?string $clientIp): self
    {
        $this->clientIp = $clientIp;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function isAuthSuccess(): bool
    {
        return $this->authSuccess;
    }

    public function setAuthSuccess(bool $authSuccess): self
    {
        $this->authSuccess = $authSuccess;

        return $this;
    }

    public function getResult(): DdnsResult
    {
        return $this->result;
    }

    public function setResult(DdnsResult $result): self
    {
        $this->result = $result;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    public function setDurationMs(int $durationMs): self
    {
        $this->durationMs = $durationMs;

        return $this;
    }

    public function isHetznerCalled(): bool
    {
        return $this->hetznerCalled;
    }

    public function setHetznerCalled(bool $hetznerCalled): self
    {
        $this->hetznerCalled = $hetznerCalled;

        return $this;
    }

    public function getNormalizedRecordName(): ?string
    {
        return $this->normalizedRecordName;
    }

    public function setNormalizedRecordName(?string $normalizedRecordName): self
    {
        $this->normalizedRecordName = $normalizedRecordName;

        return $this;
    }

    public function getRecordType(): ?string
    {
        return $this->recordType;
    }

    public function setRecordType(?string $recordType): self
    {
        $this->recordType = $recordType;

        return $this;
    }
}

