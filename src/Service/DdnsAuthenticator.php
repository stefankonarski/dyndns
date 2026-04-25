<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DdnsConfig;

class DdnsAuthenticator
{
    public function hashPassword(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_ARGON2ID);
    }

    public function verify(DdnsConfig $config, ?string $username, ?string $password): bool
    {
        if (null === $username || null === $password) {
            return false;
        }

        $expectedUsername = $config->getFritzboxUsername();
        $expectedPasswordHash = $config->getFritzboxPasswordHash();
        if (null === $expectedUsername || null === $expectedPasswordHash) {
            return false;
        }

        return hash_equals($expectedUsername, trim($username))
            && password_verify($password, $expectedPasswordHash);
    }
}

