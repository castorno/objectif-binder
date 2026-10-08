<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008123842 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE import_run (source VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_count INT NOT NULL, updated_count INT NOT NULL, unchanged_count INT NOT NULL, rejected_count INT NOT NULL, errors JSON NOT NULL, failure TEXT DEFAULT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX import_run_started_at_idx ON import_run (started_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE import_run');
    }
}
