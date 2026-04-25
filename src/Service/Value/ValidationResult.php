<?php

declare(strict_types=1);

namespace App\Service\Value;

final class ValidationResult
{
    private function __construct(
        private readonly bool $valid,
        private readonly ?string $message = null,
    ) {
    }

    public static function valid(): self
    {
        return new self(true);
    }

    public static function invalid(string $message): self
    {
        return new self(false, $message);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }
}

