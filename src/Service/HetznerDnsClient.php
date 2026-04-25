<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\Exception\HetznerDnsException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HetznerDnsClient
{
    private const BASE_URL = 'https://api.hetzner.cloud/v1';

    /**
     * @var array<string, array{name: string, ttl: int}>
     */
    private array $zoneCache = [];

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
            $ttl = max(60, (int) ($zone['ttl'] ?? 3600));
            $this->zoneCache[$id] = ['name' => $name, 'ttl' => $ttl];
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
        $query = [];
        if (null !== $fullName && '' !== trim($fullName)) {
            $query['name'] = $this->toRelativeRecordName($zoneId, $fullName);
        }

        $data = $this->requestJson('GET', '/zones/'.rawurlencode($zoneId).'/rrsets', ['query' => $query]);
        $rrsets = $data['rrsets'] ?? [];
        if (!is_array($rrsets)) {
            throw new HetznerDnsException('Unerwartete Antwort beim Laden der RRSets.');
        }

        $zoneTtl = $this->resolveZoneTtl($zoneId);
        $result = [];
        foreach ($rrsets as $rrset) {
            if (!is_array($rrset)) {
                continue;
            }
            $rrName = mb_strtolower(trim((string) ($rrset['name'] ?? '')));
            $rrType = strtoupper(trim((string) ($rrset['type'] ?? '')));
            if ('' === $rrName || '' === $rrType) {
                continue;
            }

            $ttlRaw = $rrset['ttl'] ?? null;
            $ttl = is_int($ttlRaw) ? max(60, $ttlRaw) : $zoneTtl;

            $records = $rrset['records'] ?? [];
            if (!is_array($records)) {
                continue;
            }

            foreach ($records as $record) {
                if (!is_array($record)) {
                    continue;
                }
                $value = trim((string) ($record['value'] ?? ''));
                if ('' === $value) {
                    continue;
                }

                $result[] = [
                    'id' => $this->encodeRecordId($zoneId, $rrName, $rrType, $value),
                    'type' => $rrType,
                    'name' => $this->toFullRecordName($zoneId, $rrName),
                    'value' => $value,
                    'ttl' => $ttl,
                ];
            }
        }

        return $result;
    }

    /**
     * @return array{id: string, type: string, name: string, value: string, ttl: int}
     */
    public function createRecord(string $zoneId, string $type, string $name, string $value, int $ttl): array
    {
        $rrType = strtoupper($type);
        $rrName = $this->toRelativeRecordName($zoneId, $name);
        $this->requestJson('POST', '/zones/'.rawurlencode($zoneId).'/rrsets', [
            'json' => [
                'name' => $rrName,
                'type' => $rrType,
                'ttl' => $ttl,
                'records' => [
                    ['value' => $value],
                ],
            ],
        ]);

        return [
            'id' => $this->encodeRecordId($zoneId, $rrName, $rrType, $value),
            'type' => $rrType,
            'name' => $this->toFullRecordName($zoneId, $rrName),
            'value' => trim($value),
            'ttl' => $ttl,
        ];
    }

    /**
     * @return array{id: string, type: string, name: string, value: string, ttl: int}
     */
    public function updateRecord(string $recordId, string $zoneId, string $type, string $name, string $value, int $ttl): array
    {
        $rrType = strtoupper($type);
        $rrName = $this->toRelativeRecordName($zoneId, $name);

        $basePath = '/zones/'.rawurlencode($zoneId).'/rrsets/'.rawurlencode($rrName).'/'.rawurlencode($rrType).'/actions';
        $this->requestJson('POST', $basePath.'/change_ttl', [
            'json' => [
                'ttl' => $ttl,
            ],
        ]);
        $this->requestJson('POST', $basePath.'/set_records', [
            'json' => [
                'records' => [
                    ['value' => $value],
                ],
            ],
        ]);

        return [
            'id' => $this->encodeRecordId($zoneId, $rrName, $rrType, $value),
            'type' => $rrType,
            'name' => $this->toFullRecordName($zoneId, $rrName),
            'value' => trim($value),
            'ttl' => $ttl,
        ];
    }

    public function deleteRecord(string $recordId): void
    {
        $parts = $this->decodeRecordId($recordId);
        if (null === $parts) {
            throw new HetznerDnsException('Ungültige Record-ID für Löschoperation.');
        }

        $this->requestJson(
            'POST',
            '/zones/'.rawurlencode($parts['zoneId']).'/rrsets/'.rawurlencode($parts['rrName']).'/'.rawurlencode($parts['rrType']).'/actions/remove_records',
            [
                'json' => [
                    'records' => [
                        ['value' => $parts['value']],
                    ],
                ],
            ],
        );
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
            'Authorization' => 'Bearer '.(string) $this->apiToken,
            'Accept' => 'application/json',
        ]);

        try {
            $response = $this->httpClient->request($method, self::BASE_URL.$path, $options);
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
     * @return array{name: string, ttl: int}
     *
     * @throws HetznerDnsException
     */
    private function getZoneMeta(string $zoneId): array
    {
        if (isset($this->zoneCache[$zoneId])) {
            return $this->zoneCache[$zoneId];
        }

        $data = $this->requestJson('GET', '/zones/'.rawurlencode($zoneId));
        $zone = $data['zone'] ?? null;
        if (!is_array($zone)) {
            throw new HetznerDnsException('Unerwartete Antwort beim Laden der Zone.');
        }

        $name = mb_strtolower(trim((string) ($zone['name'] ?? '')));
        if ('' === $name) {
            throw new HetznerDnsException('Zone-Name konnte nicht ermittelt werden.');
        }

        $ttl = max(60, (int) ($zone['ttl'] ?? 3600));
        $meta = ['name' => $name, 'ttl' => $ttl];
        $this->zoneCache[$zoneId] = $meta;

        return $meta;
    }

    private function resolveZoneName(string $zoneId): string
    {
        return $this->getZoneMeta($zoneId)['name'];
    }

    private function resolveZoneTtl(string $zoneId): int
    {
        return $this->getZoneMeta($zoneId)['ttl'];
    }

    private function toRelativeRecordName(string $zoneId, string $recordName): string
    {
        $name = rtrim(mb_strtolower(trim($recordName)), '.');
        if ('' === $name || '@' === $name) {
            return '@';
        }

        $zoneName = $this->resolveZoneName($zoneId);
        if ($name === $zoneName) {
            return '@';
        }

        $suffix = '.'.$zoneName;
        if (str_ends_with($name, $suffix)) {
            $relative = substr($name, 0, -strlen($suffix));

            return '' === $relative ? '@' : $relative;
        }

        return $name;
    }

    private function toFullRecordName(string $zoneId, string $relativeName): string
    {
        $name = rtrim(mb_strtolower(trim($relativeName)), '.');
        $zoneName = $this->resolveZoneName($zoneId);

        if ('' === $name || '@' === $name) {
            return $zoneName;
        }
        if ($name === $zoneName || str_ends_with($name, '.'.$zoneName)) {
            return $name;
        }

        return $name.'.'.$zoneName;
    }

    private function encodeRecordId(string $zoneId, string $rrName, string $rrType, string $value): string
    {
        return rawurlencode($zoneId)
            .'|'.rawurlencode($rrName)
            .'|'.rawurlencode(strtoupper($rrType))
            .'|'.rawurlencode($value);
    }

    /**
     * @return array{zoneId: string, rrName: string, rrType: string, value: string}|null
     */
    private function decodeRecordId(string $recordId): ?array
    {
        $parts = explode('|', $recordId, 4);
        if (4 !== count($parts)) {
            return null;
        }

        [$zoneId, $rrName, $rrType, $value] = $parts;
        $zoneId = rawurldecode($zoneId);
        $rrName = rawurldecode($rrName);
        $rrType = strtoupper(rawurldecode($rrType));
        $value = rawurldecode($value);
        if ('' === $zoneId || '' === $rrName || '' === $rrType || '' === $value) {
            return null;
        }

        return [
            'zoneId' => $zoneId,
            'rrName' => $rrName,
            'rrType' => $rrType,
            'value' => $value,
        ];
    }
}
