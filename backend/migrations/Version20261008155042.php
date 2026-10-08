<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008155042 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE card_price (marketplace VARCHAR(50) DEFAULT NULL, currency VARCHAR(3) DEFAULT NULL, trend_cents INT DEFAULT NULL, low_cents INT DEFAULT NULL, average_30_days_cents INT DEFAULT NULL, holo_trend_cents INT DEFAULT NULL, holo_low_cents INT DEFAULT NULL, holo_average_30_days_cents INT DEFAULT NULL, source_updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, fetched_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, card_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F2FCD31F4ACC9A20 ON card_price (card_id)');
        $this->addSql('ALTER TABLE card_price ADD CONSTRAINT FK_F2FCD31F4ACC9A20 FOREIGN KEY (card_id) REFERENCES card (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card_price DROP CONSTRAINT FK_F2FCD31F4ACC9A20');
        $this->addSql('DROP TABLE card_price');
    }
}
