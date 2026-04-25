<?php

declare(strict_types=1);

namespace App\Service\Value;

use App\Enum\DdnsResult;

final class SyncOutcome
{
    public function __construct(
        private readonly DdnsResult $result,
        private readonly string $message,
        private readonly bool $hetznerCalled = false,
        private readonly ?string $recordType = null,
        private readonly ?string $recordName = null,
    ) {
    }

    public function getResult(): DdnsResult
    {
        return $this->result;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function isHetznerCalled(): bool
    {
        return $this->hetznerCalled;
    }

    public function getRecordType(): ?string
    {
        return $this->recordType;
    }

    public function getRecordName(): ?string
    {
        return $this->recordName;
    }
}

