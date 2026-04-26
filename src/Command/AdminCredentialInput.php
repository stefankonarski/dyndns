<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class AdminCredentialInput
{
    /**
     * @return array{email: string, password: string}|null
     */
    public static function parseAndValidate(InputInterface $input, OutputInterface $output): ?array
    {
        $email = mb_strtolower(trim((string) $input->getArgument('email')));
        $password = (string) $input->getArgument('password');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $output->writeln('<error>Ungültige E-Mail-Adresse.</error>');

            return null;
        }

        if (mb_strlen($password) < 12) {
            $output->writeln('<error>Passwort muss mindestens 12 Zeichen lang sein.</error>');

            return null;
        }

        return [
            'email' => $email,
            'password' => $password,
        ];
    }
}
