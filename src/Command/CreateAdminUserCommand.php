<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\AdminUser;
use App\Repository\AdminUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin-user', description: 'Erstellt einen Admin-User für das Dashboard.')]
class CreateAdminUserCommand extends Command
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
            ->addArgument('password', InputArgument::REQUIRED, 'Admin-Passwort (mindestens 12 Zeichen)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $credentials = AdminCredentialInput::parseAndValidate($input, $output);
        if (null === $credentials) {
            return Command::FAILURE;
        }

        $email = $credentials['email'];
        $password = $credentials['password'];

        $existing = $this->adminUserRepository->findOneBy(['email' => $email]);
        if (null !== $existing) {
            $output->writeln('<error>Admin-User mit dieser E-Mail existiert bereits.</error>');

            return Command::FAILURE;
        }

        $user = new AdminUser()
            ->setEmail($email)
            ->setRoles(['ROLE_ADMIN']);
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $output->writeln('<info>Admin-User wurde erstellt.</info>');

        return Command::SUCCESS;
    }
}
