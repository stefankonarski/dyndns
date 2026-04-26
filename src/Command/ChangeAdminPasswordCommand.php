<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\AdminUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:change-admin-password', description: 'Ändert das Passwort eines bestehenden Admin-Users.')]
class ChangeAdminPasswordCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly AdminUserRepository $adminUserRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Admin-E-Mail')
            ->addArgument('password', InputArgument::REQUIRED, 'Neues Admin-Passwort (mindestens 12 Zeichen)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = mb_strtolower(trim((string) $input->getArgument('email')));
        $password = (string) $input->getArgument('password');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $output->writeln('<error>Ungültige E-Mail-Adresse.</error>');

            return Command::FAILURE;
        }
        if (mb_strlen($password) < 12) {
            $output->writeln('<error>Passwort muss mindestens 12 Zeichen lang sein.</error>');

            return Command::FAILURE;
        }

        $user = $this->adminUserRepository->findOneBy(['email' => $email]);
        if (null === $user) {
            $output->writeln('<error>Admin-User mit dieser E-Mail wurde nicht gefunden.</error>');

            return Command::FAILURE;
        }

        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));
        $this->entityManager->flush();

        $output->writeln('<info>Admin-Passwort wurde aktualisiert.</info>');

        return Command::SUCCESS;
    }
}

