<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DdnsConfig;
use App\Service\Exception\ConfigurationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DdnsConfigService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function getOrCreate(): DdnsConfig
    {
        $repo = $this->entityManager->getRepository(DdnsConfig::class);
        \assert($repo instanceof \App\Repository\DdnsConfigRepository);
        $config = $repo->getSingleton();

        if (null === $config) {
            $config = new DdnsConfig();
            $this->entityManager->persist($config);
            $this->entityManager->flush();
        }

        return $config;
    }

    public function normalizeRecordName(DdnsConfig $config): ?string
    {
        $domain = $config->getDomain();
        if (null === $domain || '' === $domain) {
            return null;
        }

        $subdomain = trim($config->getSubdomain());
        if ('' === $subdomain || '@' === $subdomain) {
            return $domain;
        }

        return $subdomain.'.'.$domain;
    }

    /**
     * @return list<string>
     */
    public function validateConfiguration(DdnsConfig $config): array
    {
        $errors = [];

        if (null === $config->getZoneId() || '' === trim($config->getZoneId())) {
            $errors[] = 'Zone ist nicht konfiguriert.';
        }
        if (null === $config->getDomain() || '' === trim($config->getDomain())) {
            $errors[] = 'Domain ist nicht konfiguriert.';
        }
        if (null === $config->getFritzboxUsername() || '' === trim($config->getFritzboxUsername())) {
            $errors[] = 'DynDNS-Username fehlt.';
        }
        if (null === $config->getFritzboxPasswordHash() || '' === trim($config->getFritzboxPasswordHash())) {
            $errors[] = 'DynDNS-Passwort fehlt.';
        }
        if (!$config->isIpv4Enabled() && !$config->isIpv6Enabled()) {
            $errors[] = 'Mindestens IPv4 oder IPv6 muss aktiviert sein.';
        }
        $subdomainViolations = $this->validator->validate($config->getSubdomain(), [
            new Assert\NotBlank(message: 'Subdomain darf nicht leer sein.'),
            new Assert\Regex(
                pattern: '/^(?:@|[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)$/',
                message: 'Subdomain muss DNS-kompatibel sein (oder @).',
            ),
        ]);
        foreach ($subdomainViolations as $violation) {
            $errors[] = $violation->getMessage();
        }

        $ttlViolations = $this->validator->validate($config->getTtl(), [
            new Assert\Range(min: 60, max: 86400, notInRangeMessage: 'TTL muss zwischen 60 und 86400 liegen.'),
        ]);
        foreach ($ttlViolations as $violation) {
            $errors[] = $violation->getMessage();
        }

        return array_values(array_unique($errors));
    }

    public function assertValidForUpdate(DdnsConfig $config): void
    {
        $errors = $this->validateConfiguration($config);
        if ([] !== $errors) {
            throw new ConfigurationException(implode(' ', $errors));
        }
    }
}
