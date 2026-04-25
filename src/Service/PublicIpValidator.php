<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\Value\ValidationResult;

class PublicIpValidator
{
    public function validatePublicIpv4(?string $ip): ValidationResult
    {
        $ip = null !== $ip ? trim($ip) : null;
        if (null === $ip || '' === $ip) {
            return ValidationResult::invalid('IPv4 fehlt.');
        }

        $isValid = false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        if (!$isValid) {
            return ValidationResult::invalid('IPv4 ist ungültig oder nicht öffentlich.');
        }

        return ValidationResult::valid();
    }

    public function validatePublicIpv6(?string $ip): ValidationResult
    {
        $ip = null !== $ip ? mb_strtolower(trim($ip)) : null;
        if (null === $ip || '' === $ip) {
            return ValidationResult::invalid('IPv6 fehlt.');
        }

        if (false === filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return ValidationResult::invalid('IPv6 ist ungültig.');
        }

        if ('::1' === $ip || '::' === $ip) {
            return ValidationResult::invalid('IPv6 ist nicht öffentlich.');
        }

        if ($this->isInIpv6Cidr($ip, 'fc00::', 7)
            || $this->isInIpv6Cidr($ip, 'fe80::', 10)
            || $this->isInIpv6Cidr($ip, 'ff00::', 8)
            || $this->isInIpv6Cidr($ip, '2001:db8::', 32)) {
            return ValidationResult::invalid('IPv6 ist reserviert oder nicht öffentlich.');
        }

        return ValidationResult::valid();
    }

    private function isInIpv6Cidr(string $ip, string $network, int $maskBits): bool
    {
        $ipBytes = inet_pton($ip);
        $networkBytes = inet_pton($network);
        if (false === $ipBytes || false === $networkBytes) {
            return false;
        }

        $fullBytes = intdiv($maskBits, 8);
        $remainingBits = $maskBits % 8;

        if ($fullBytes > 0 && substr($ipBytes, 0, $fullBytes) !== substr($networkBytes, 0, $fullBytes)) {
            return false;
        }

        if (0 === $remainingBits) {
            return true;
        }

        $mask = 0xFF << (8 - $remainingBits);
        $ipByte = ord($ipBytes[$fullBytes]);
        $networkByte = ord($networkBytes[$fullBytes]);

        return ($ipByte & $mask) === ($networkByte & $mask);
    }
}

