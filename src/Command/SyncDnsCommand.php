<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\DdnsConfigService;
use App\Service\DnsRecordSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:sync-dns', description: 'Synchronisiert DNS-Records mit der aktuellen Konfiguration.')]
class SyncDnsCommand extends Command
{
    public function __construct(
        private readonly DdnsConfigService $ddnsConfigService,
        private readonly DnsRecordSynchronizer $dnsRecordSynchronizer,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('ipv4', null, InputOption::VALUE_REQUIRED, 'Optional: öffentliche IPv4 für A-Record')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Erzwingt Update auch bei unveränderten Werten');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->ddnsConfigService->getOrCreate();
        $ipv4 = $input->getOption('ipv4');
        \assert(null === $ipv4 || is_string($ipv4));
        $force = (bool) $input->getOption('force');

        try {
            $outcome = $this->dnsRecordSynchronizer->syncFromConfig($config, $ipv4, null, $force);
            $this->entityManager->flush();
            $output->writeln('<info>'.$outcome->getResult()->value.'</info>: '.$outcome->getMessage());

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>Sync fehlgeschlagen: '.$e->getMessage().'</error>');

            return Command::FAILURE;
        }
    }
}

