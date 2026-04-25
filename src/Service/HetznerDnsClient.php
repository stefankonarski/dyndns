<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\Exception\HetznerDnsException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HetznerDnsClient
{
    private const LEGACY_BASE_URL = 'https://dns.hetzner.com/api/v1';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $apiToken,
    ) {
    }

    public function isConfigured(): bool
    {
        return null !== $this->apiToken && '' !== trim($this->apiToken);
    }

    public function testToken(): bool
    {
        try {
            $this->listZones();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function listZones(): array
    {
        $data = $this->requestJson('GET', '/zones');
        $zones = $data['zones'] ?? [];
        if (!is_array($zones)) {
            throw new HetznerDnsException('Unerwartete Antwort beim Laden der Zonen.');
        }

        $result = [];
        foreach ($zones as $zone) {
            if (!is_array($zone)) {
                continue;
            }
            $id = (string) ($zone['id'] ?? '');
            $name = mb_strtolower(trim((string) ($zone['name'] ?? '')));
            if ('' === $id || '' === $name) {
                continue;
            }
            $result[] = ['id' => $id, 'name' => $name];
        }

        usort($result, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $result;
    }

    /**
     * @return list<array{id: string, type: string, name: string, value: string, ttl: int}>
     */
    public function listRecords(string $zoneId, ?string $fullName = null): array
    {
        $query = ['zone_id' => $zoneId];
        if (null !== $fullName && '' !== trim($fullName)) {
            $query['name'] = mb_strtolower(trim($fullName));
        }

        $data = $this->requestJson('GET', '/records', ['query' => $query]);
        $records = $data['records'] ?? [];
        if (!is_array($records)) {
            throw new HetznerDnsException('Unerwartete Antwort beim Laden der Records.');
        }

        $result = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $result[] = [
                'id' => (string) ($record['id'] ?? ''),
                'type' => strtoupper((string) ($record['type'] ?? '')),
                'name' => mb_strtolower((string) ($record['name'] ?? '')),
                'value' => trim((string) ($record['value'] ?? '')),
                'ttl' => (int) ($record['ttl'] ?? 120),
            ];
        }

        return $result;
    }

    /**
     * @return array{id: string, type: string, name: string, value: string, ttl: int}
     */
    public function createRecord(string $zoneId, string $type, string $name, string $value, int $ttl): array
    {
        $data = $this->requestJson('POST', '/records', [
            'json' => [
                'zone_id' => $zoneId,
                'type' => strtoupper($type),
                'name' => mb_strtolower($name),
                'value' => $value,
                'ttl' => $ttl,
            ],
        ]);

        return $this->normalizeRecordFromResponse($data);
    }

    /**
     * @return array{id: string, type: string, name: string, value: string, ttl: int}
     */
    public function updateRecord(string $recordId, string $zoneId, string $type, string $name, string $value, int $ttl): array
    {
        $data = $this->requestJson('PUT', '/records/'.$recordId, [
            'json' => [
                'id' => $recordId,
                'zone_id' => $zoneId,
                'type' => strtoupper($type),
                'name' => mb_strtolower($name),
                'value' => $value,
                'ttl' => $ttl,
            ],
        ]);

        return $this->normalizeRecordFromResponse($data);
    }

    public function deleteRecord(string $recordId): void
    {
        $this->requestJson('DELETE', '/records/'.$recordId);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function requestJson(string $method, string $path, array $options = []): array
    {
        if (!$this->isConfigured()) {
            throw new HetznerDnsException('Hetzner DNS Token fehlt.');
        }

        $options['headers'] = array_merge($options['headers'] ?? [], [
            'Auth-API-Token' => (string) $this->apiToken,
            'Accept' => 'application/json',
        ]);

        try {
            $response = $this->httpClient->request($method, self::LEGACY_BASE_URL.$path, $options);
            $statusCode = $response->getStatusCode();
            $payload = $response->toArray(false);
        } catch (TransportExceptionInterface $e) {
            throw new HetznerDnsException('Hetzner DNS API ist nicht erreichbar.', previous: $e);
        } catch (\Throwable $e) {
            throw new HetznerDnsException('Hetzner DNS Anfrage ist fehlgeschlagen.', previous: $e);
        }

        if ($statusCode >= 400) {
            $message = 'Hetzner DNS API Fehler (HTTP '.$statusCode.').';
            if (is_array($payload) && isset($payload['error']['message']) && is_string($payload['error']['message'])) {
                $message = 'Hetzner DNS API Fehler: '.$payload['error']['message'];
            }
            throw new HetznerDnsException($message);
        }

        if (!is_array($payload)) {
            throw new HetznerDnsException('Ungültige Antwort von Hetzner DNS API.');
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{id: string, type: string, name: string, value: string, ttl: int}
     */
    private function normalizeRecordFromResponse(array $data): array
    {
        $record = $data['record'] ?? $data;
        if (!is_array($record)) {
            throw new HetznerDnsException('Hetzner API Antwort enthält keinen gültigen Record.');
        }

        return [
            'id' => (string) ($record['id'] ?? ''),
            'type' => strtoupper((string) ($record['type'] ?? '')),
            'name' => mb_strtolower((string) ($record['name'] ?? '')),
            'value' => trim((string) ($record['value'] ?? '')),
            'ttl' => (int) ($record['ttl'] ?? 120),
        ];
    }
}

