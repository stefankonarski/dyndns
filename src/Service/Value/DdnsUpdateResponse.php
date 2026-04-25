<?php

declare(strict_types=1);

namespace App\Service\Value;

use App\Enum\DdnsResult;

final class DdnsUpdateResponse
{
    public function __construct(
        private readonly int $httpStatus,
        private readonly DdnsResult $result,
        private readonly string $message,
    ) {
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getResult(): DdnsResult
    {
        return $this->result;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}

