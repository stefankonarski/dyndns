<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class HetznerZoneService
{
    public function __construct(
        private readonly HetznerDnsClient $hetznerDnsClient,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function listZones(): array
    {
        return $this->cache->get('hetzner_zones', function (ItemInterface $item): array {
            $item->expiresAfter(60);

            return $this->hetznerDnsClient->listZones();
        });
    }

    public function findZoneIdByDomain(string $domain): ?string
    {
        $domain = mb_strtolower(trim($domain));
        foreach ($this->listZones() as $zone) {
            if ($zone['name'] === $domain) {
                return $zone['id'];
            }
        }

        return null;
    }
}
