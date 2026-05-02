<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260502113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove obsolete manual_ipv6 column from ddns_config';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ddns_config DROP COLUMN manual_ipv6');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ddns_config ADD manual_ipv6 VARCHAR(64) DEFAULT NULL');
    }
}

