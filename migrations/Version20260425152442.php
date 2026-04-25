<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260425152442 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin_user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(190) NOT NULL, roles CLOB NOT NULL, password_hash VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_admin_user_email ON admin_user (email)');
        $this->addSql('CREATE TABLE ddns_config (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, zone_id VARCHAR(64) DEFAULT NULL, domain VARCHAR(190) DEFAULT NULL, subdomain VARCHAR(190) NOT NULL, fritzbox_username VARCHAR(190) DEFAULT NULL, fritzbox_password_hash CLOB DEFAULT NULL, ttl INTEGER NOT NULL, ipv4_enabled BOOLEAN NOT NULL, ipv6_enabled BOOLEAN NOT NULL, manual_ipv6 VARCHAR(64) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE TABLE ddns_log (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, request_path VARCHAR(255) NOT NULL, http_method VARCHAR(16) NOT NULL, username VARCHAR(190) DEFAULT NULL, requested_domain VARCHAR(190) DEFAULT NULL, ipaddr VARCHAR(64) DEFAULT NULL, configured_ipv6 VARCHAR(64) DEFAULT NULL, client_ip VARCHAR(64) DEFAULT NULL, user_agent VARCHAR(512) DEFAULT NULL, auth_success BOOLEAN NOT NULL, result VARCHAR(255) NOT NULL, message CLOB DEFAULT NULL, duration_ms INTEGER NOT NULL, hetzner_called BOOLEAN NOT NULL, normalized_record_name VARCHAR(255) DEFAULT NULL, record_type VARCHAR(8) DEFAULT NULL)');
        $this->addSql('CREATE INDEX idx_ddns_log_created_at ON ddns_log (created_at)');
        $this->addSql('CREATE INDEX idx_ddns_log_result ON ddns_log (result)');
        $this->addSql('CREATE INDEX idx_ddns_log_requested_domain ON ddns_log (requested_domain)');
        $this->addSql('CREATE INDEX idx_ddns_log_ipaddr ON ddns_log (ipaddr)');
        $this->addSql('CREATE INDEX idx_ddns_log_auth_success ON ddns_log (auth_success)');
        $this->addSql('CREATE INDEX idx_ddns_log_record_type ON ddns_log (record_type)');
        $this->addSql('CREATE TABLE dns_record_state (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, record_type VARCHAR(255) NOT NULL, record_id VARCHAR(64) DEFAULT NULL, zone_id VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, value VARCHAR(255) NOT NULL, ttl INTEGER NOT NULL, last_synced_at DATETIME NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_dns_record_state ON dns_record_state (zone_id, name, record_type)');
        $this->addSql('CREATE TABLE ip_history (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, record_type VARCHAR(255) NOT NULL, ip VARCHAR(64) NOT NULL, valid_from DATETIME NOT NULL, valid_to DATETIME DEFAULT NULL, source VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, log_entry_id INTEGER DEFAULT NULL, CONSTRAINT FK_80BCA46D465829D FOREIGN KEY (log_entry_id) REFERENCES ddns_log (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_80BCA46D465829D ON ip_history (log_entry_id)');
        $this->addSql('CREATE INDEX idx_ip_history_record_type_valid_from ON ip_history (record_type, valid_from)');
        $this->addSql('CREATE INDEX idx_ip_history_record_type_valid_to ON ip_history (record_type, valid_to)');
        $this->addSql('CREATE INDEX idx_ip_history_ip ON ip_history (ip)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE admin_user');
        $this->addSql('DROP TABLE ddns_config');
        $this->addSql('DROP TABLE ddns_log');
        $this->addSql('DROP TABLE dns_record_state');
        $this->addSql('DROP TABLE ip_history');
    }
}
