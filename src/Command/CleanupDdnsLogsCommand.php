<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\DdnsLogRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:cleanup-ddns-logs', description: 'Löscht alte DynDNS-Logs.')]
class CleanupDdnsLogsCommand extends Command
{
    public function __construct(
        private readonly DdnsLogRepository $ddnsLogRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Logs älter als X Tage löschen', '90');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = max(1, (int) $input->getOption('days'));
        $cutoff = new \DateTimeImmutable(sprintf('-%d days', $days));
        $deleted = $this->ddnsLogRepository->deleteOlderThan($cutoff);

        $output->writeln(sprintf('<info>%d Logeinträge gelöscht (älter als %d Tage).</info>', $deleted, $days));

        return Command::SUCCESS;
    }
}

