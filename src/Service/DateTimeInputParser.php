<?php

declare(strict_types=1);

namespace App\Service;

class DateTimeInputParser
{
    public function parse(mixed $input): ?\DateTimeImmutable
    {
        if (!is_string($input) || '' === trim($input)) {
            return null;
        }

        $input = trim($input);
        $formats = ['Y-m-d\TH:i', \DateTimeInterface::ATOM, 'Y-m-d H:i:s'];
        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $input);
            if (false !== $dt) {
                return $dt;
            }
        }

        try {
            return new \DateTimeImmutable($input);
        } catch (\Throwable) {
            return null;
        }
    }
}
