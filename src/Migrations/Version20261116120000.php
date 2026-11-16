<?php

declare(strict_types=1);

namespace Odiseo\SuluSiteAssistantBundle\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The assistant's tables: conversations and their messages, visitor memory, model spend, the
 * versions of the rules and knowledge edited in the admin, the cases handed to the team and each
 * webspace's settings.
 */
final class Version20261116120000 extends AbstractMigration
{
    private const TABLES = [
        'odiseo_site_assistant_message',
        'odiseo_site_assistant_handoff',
        'odiseo_site_assistant_conversation',
        'odiseo_site_assistant_memory_fact',
        'odiseo_site_assistant_spend_entry',
        'odiseo_site_assistant_content_version',
        'odiseo_site_assistant_settings',
    ];

    public function getDescription(): string
    {
        return 'Site assistant: conversation, message, memory, spend, content version, handoff and settings tables';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('CREATE TABLE odiseo_site_assistant_handoff (webspace_key VARCHAR(64) DEFAULT NULL, locale VARCHAR(16) DEFAULT NULL, reference VARCHAR(32) NOT NULL, topic VARCHAR(64) NOT NULL, category VARCHAR(32) NOT NULL, summary LONGTEXT NOT NULL, contact_name VARCHAR(255) DEFAULT NULL, contact_email VARCHAR(255) DEFAULT NULL, contact_phone VARCHAR(32) DEFAULT NULL, path VARCHAR(64) DEFAULT NULL, page VARCHAR(255) DEFAULT NULL, state VARCHAR(16) NOT NULL, notified_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, conversation_id INT DEFAULT NULL, INDEX idx_odiseo_site_assistant_handoff_state (state), INDEX idx_odiseo_site_assistant_handoff_topic (topic), INDEX idx_odiseo_site_assistant_handoff_created (created_at), UNIQUE INDEX uniq_odiseo_site_assistant_handoff_reference (reference), INDEX IDX_FF11B9419AC0396 (conversation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('CREATE TABLE odiseo_site_assistant_memory_fact (subject VARCHAR(255) NOT NULL, fact_key VARCHAR(255) NOT NULL, fact_value LONGTEXT NOT NULL, category VARCHAR(32) NOT NULL, source_session VARCHAR(64) DEFAULT NULL, updated_at DATETIME NOT NULL, id INT AUTO_INCREMENT NOT NULL, INDEX idx_agent_memory_fact_updated (subject, updated_at), UNIQUE INDEX uniq_agent_memory_fact_subject_key (subject, fact_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('CREATE TABLE odiseo_site_assistant_spend_entry (session_id VARCHAR(64) NOT NULL, client_key VARCHAR(64) DEFAULT NULL, spent_on DATE NOT NULL, usd NUMERIC(12, 6) NOT NULL, model VARCHAR(64) DEFAULT NULL, scope VARCHAR(64) DEFAULT NULL, input_tokens INT NOT NULL, output_tokens INT NOT NULL, cache_creation_tokens INT NOT NULL, cache_read_tokens INT NOT NULL, created_at DATETIME NOT NULL, id INT AUTO_INCREMENT NOT NULL, INDEX idx_agent_spend_session (session_id), INDEX idx_agent_spend_day (spent_on), INDEX idx_agent_spend_client (client_key, spent_on), INDEX idx_agent_spend_scope (scope, spent_on), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('CREATE TABLE odiseo_site_assistant_message (position INT NOT NULL, role VARCHAR(16) NOT NULL, text LONGTEXT DEFAULT NULL, payload JSON NOT NULL, created_at DATETIME NOT NULL, id INT AUTO_INCREMENT NOT NULL, conversation_id INT NOT NULL, UNIQUE INDEX uniq_odiseo_site_assistant_message_position (conversation_id, position), INDEX IDX_E98AA5149AC0396 (conversation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('CREATE TABLE odiseo_site_assistant_conversation (session_id VARCHAR(64) NOT NULL, principal_id VARCHAR(255) NOT NULL, version INT DEFAULT 1 NOT NULL, state JSON NOT NULL, pending_app_events JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(16) DEFAULT NULL, webspace_key VARCHAR(64) DEFAULT NULL, first_message VARCHAR(255) DEFAULT NULL, last_page VARCHAR(255) DEFAULT NULL, turn_count INT DEFAULT 0 NOT NULL, contact_opened_at DATETIME DEFAULT NULL, INDEX idx_odiseo_site_assistant_conversation_created (created_at), INDEX idx_odiseo_site_assistant_conversation_webspace (webspace_key, created_at), INDEX idx_agent_conversation_principal (principal_id, expires_at, updated_at), INDEX idx_agent_conversation_expires (expires_at), UNIQUE INDEX uniq_agent_conversation_session (session_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('CREATE TABLE odiseo_site_assistant_settings (webspace_key VARCHAR(64) NOT NULL, enabled TINYINT NOT NULL, turn_model VARCHAR(64) DEFAULT NULL, turn_effort VARCHAR(16) DEFAULT NULL, memory_model VARCHAR(64) DEFAULT NULL, anthropic_api_key LONGTEXT DEFAULT NULL, openai_api_key LONGTEXT DEFAULT NULL, session_budget_usd DOUBLE PRECISION DEFAULT NULL, daily_budget_usd DOUBLE PRECISION DEFAULT NULL, monthly_budget_usd DOUBLE PRECISION DEFAULT NULL, brand_name VARCHAR(255) DEFAULT NULL, assistant_name VARCHAR(255) DEFAULT NULL, brand_voice LONGTEXT DEFAULT NULL, memory_enabled TINYINT DEFAULT NULL, memory_retention_days INT DEFAULT NULL, texts JSON NOT NULL, updated_by VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, UNIQUE INDEX uniq_odiseo_site_assistant_settings_webspace (webspace_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('CREATE TABLE odiseo_site_assistant_content_version (kind VARCHAR(32) NOT NULL, item_key VARCHAR(128) NOT NULL, version_number INT NOT NULL, data JSON DEFAULT NULL, author VARCHAR(255) DEFAULT NULL, note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, id INT AUTO_INCREMENT NOT NULL, INDEX idx_agent_content_version_kind (kind, version_number), UNIQUE INDEX uniq_agent_content_version (kind, item_key, version_number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('ALTER TABLE odiseo_site_assistant_handoff ADD CONSTRAINT FK_FF11B9419AC0396 FOREIGN KEY (conversation_id) REFERENCES odiseo_site_assistant_conversation (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE odiseo_site_assistant_message ADD CONSTRAINT FK_E98AA5149AC0396 FOREIGN KEY (conversation_id) REFERENCES odiseo_site_assistant_conversation (id) ON DELETE CASCADE');

            return;
        }

        $this->abortIf(!$platform instanceof PostgreSQLPlatform, 'The site assistant ships migrations for MySQL, MariaDB and PostgreSQL only.');

        $this->addSql('CREATE TABLE odiseo_site_assistant_handoff (webspace_key VARCHAR(64) DEFAULT NULL, locale VARCHAR(16) DEFAULT NULL, reference VARCHAR(32) NOT NULL, topic VARCHAR(64) NOT NULL, category VARCHAR(32) NOT NULL, summary TEXT NOT NULL, contact_name VARCHAR(255) DEFAULT NULL, contact_email VARCHAR(255) DEFAULT NULL, contact_phone VARCHAR(32) DEFAULT NULL, path VARCHAR(64) DEFAULT NULL, page VARCHAR(255) DEFAULT NULL, state VARCHAR(16) NOT NULL, notified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, conversation_id INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_odiseo_site_assistant_handoff_state ON odiseo_site_assistant_handoff (state)');
        $this->addSql('CREATE INDEX idx_odiseo_site_assistant_handoff_topic ON odiseo_site_assistant_handoff (topic)');
        $this->addSql('CREATE INDEX idx_odiseo_site_assistant_handoff_created ON odiseo_site_assistant_handoff (created_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_odiseo_site_assistant_handoff_reference ON odiseo_site_assistant_handoff (reference)');
        $this->addSql('CREATE INDEX IDX_FF11B9419AC0396 ON odiseo_site_assistant_handoff (conversation_id)');
        $this->addSql('CREATE TABLE odiseo_site_assistant_memory_fact (subject VARCHAR(255) NOT NULL, fact_key VARCHAR(255) NOT NULL, fact_value TEXT NOT NULL, category VARCHAR(32) NOT NULL, source_session VARCHAR(64) DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_agent_memory_fact_updated ON odiseo_site_assistant_memory_fact (subject, updated_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_agent_memory_fact_subject_key ON odiseo_site_assistant_memory_fact (subject, fact_key)');
        $this->addSql('CREATE TABLE odiseo_site_assistant_spend_entry (session_id VARCHAR(64) NOT NULL, client_key VARCHAR(64) DEFAULT NULL, spent_on DATE NOT NULL, usd NUMERIC(12, 6) NOT NULL, model VARCHAR(64) DEFAULT NULL, scope VARCHAR(64) DEFAULT NULL, input_tokens INT NOT NULL, output_tokens INT NOT NULL, cache_creation_tokens INT NOT NULL, cache_read_tokens INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_agent_spend_session ON odiseo_site_assistant_spend_entry (session_id)');
        $this->addSql('CREATE INDEX idx_agent_spend_day ON odiseo_site_assistant_spend_entry (spent_on)');
        $this->addSql('CREATE INDEX idx_agent_spend_client ON odiseo_site_assistant_spend_entry (client_key, spent_on)');
        $this->addSql('CREATE INDEX idx_agent_spend_scope ON odiseo_site_assistant_spend_entry (scope, spent_on)');
        $this->addSql('CREATE TABLE odiseo_site_assistant_message (position INT NOT NULL, role VARCHAR(16) NOT NULL, text TEXT DEFAULT NULL, payload JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, conversation_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_odiseo_site_assistant_message_position ON odiseo_site_assistant_message (conversation_id, position)');
        $this->addSql('CREATE INDEX IDX_E98AA5149AC0396 ON odiseo_site_assistant_message (conversation_id)');
        $this->addSql('CREATE TABLE odiseo_site_assistant_conversation (session_id VARCHAR(64) NOT NULL, principal_id VARCHAR(255) NOT NULL, version INT DEFAULT 1 NOT NULL, state JSON NOT NULL, pending_app_events JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, locale VARCHAR(16) DEFAULT NULL, webspace_key VARCHAR(64) DEFAULT NULL, first_message VARCHAR(255) DEFAULT NULL, last_page VARCHAR(255) DEFAULT NULL, turn_count INT DEFAULT 0 NOT NULL, contact_opened_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_odiseo_site_assistant_conversation_created ON odiseo_site_assistant_conversation (created_at)');
        $this->addSql('CREATE INDEX idx_odiseo_site_assistant_conversation_webspace ON odiseo_site_assistant_conversation (webspace_key, created_at)');
        $this->addSql('CREATE INDEX idx_agent_conversation_principal ON odiseo_site_assistant_conversation (principal_id, expires_at, updated_at)');
        $this->addSql('CREATE INDEX idx_agent_conversation_expires ON odiseo_site_assistant_conversation (expires_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_agent_conversation_session ON odiseo_site_assistant_conversation (session_id)');
        $this->addSql('CREATE TABLE odiseo_site_assistant_settings (webspace_key VARCHAR(64) NOT NULL, enabled BOOLEAN NOT NULL, turn_model VARCHAR(64) DEFAULT NULL, turn_effort VARCHAR(16) DEFAULT NULL, memory_model VARCHAR(64) DEFAULT NULL, anthropic_api_key TEXT DEFAULT NULL, openai_api_key TEXT DEFAULT NULL, session_budget_usd DOUBLE PRECISION DEFAULT NULL, daily_budget_usd DOUBLE PRECISION DEFAULT NULL, monthly_budget_usd DOUBLE PRECISION DEFAULT NULL, brand_name VARCHAR(255) DEFAULT NULL, assistant_name VARCHAR(255) DEFAULT NULL, brand_voice TEXT DEFAULT NULL, memory_enabled BOOLEAN DEFAULT NULL, memory_retention_days INT DEFAULT NULL, texts JSON NOT NULL, updated_by VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_odiseo_site_assistant_settings_webspace ON odiseo_site_assistant_settings (webspace_key)');
        $this->addSql('CREATE TABLE odiseo_site_assistant_content_version (kind VARCHAR(32) NOT NULL, item_key VARCHAR(128) NOT NULL, version_number INT NOT NULL, data JSON DEFAULT NULL, author VARCHAR(255) DEFAULT NULL, note TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_agent_content_version_kind ON odiseo_site_assistant_content_version (kind, version_number)');
        $this->addSql('CREATE UNIQUE INDEX uniq_agent_content_version ON odiseo_site_assistant_content_version (kind, item_key, version_number)');
        $this->addSql('ALTER TABLE odiseo_site_assistant_handoff ADD CONSTRAINT FK_FF11B9419AC0396 FOREIGN KEY (conversation_id) REFERENCES odiseo_site_assistant_conversation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE odiseo_site_assistant_message ADD CONSTRAINT FK_E98AA5149AC0396 FOREIGN KEY (conversation_id) REFERENCES odiseo_site_assistant_conversation (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            $this->addSql(\sprintf('DROP TABLE %s', $table));
        }
    }
}
