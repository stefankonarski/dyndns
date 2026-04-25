<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\HetznerDnsClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:test-hetzner-dns', description: 'Prüft Erreichbarkeit und Token für Hetzner DNS API.')]
class TestHetznerDnsCommand extends Command
{
    public function __construct(
        private readonly HetznerDnsClient $hetznerDnsClient,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->hetznerDnsClient->isConfigured()) {
            $output->writeln('<error>HETZNER_DNS_API_TOKEN ist nicht gesetzt.</error>');

            return Command::FAILURE;
        }

        try {
            $zones = $this->hetznerDnsClient->listZones();
            $output->writeln('<info>Hetzner DNS API erreichbar.</info>');
            $output->writeln('Gefundene Zonen: '.count($zones));
            foreach ($zones as $zone) {
                $output->writeln(sprintf(' - %s (%s)', $zone['name'], $zone['id']));
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>Hetzner DNS API Fehler: '.$e->getMessage().'</error>');

            return Command::FAILURE;
        }
    }
}

