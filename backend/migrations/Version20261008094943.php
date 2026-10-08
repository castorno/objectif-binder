<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008094943 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Card identities: what cards of a game are grouped by, linked to cards many-to-many, and the game\'s label for them.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE card_identity_link (card_id UUID NOT NULL, card_identity_id UUID NOT NULL, PRIMARY KEY (card_id, card_identity_id))');
        $this->addSql('CREATE INDEX IDX_C8DB99544ACC9A20 ON card_identity_link (card_id)');
        $this->addSql('CREATE INDEX IDX_C8DB995418EA8B32 ON card_identity_link (card_identity_id)');
        $this->addSql('CREATE TABLE card_identity (name VARCHAR(200) NOT NULL, external_id VARCHAR(100) NOT NULL, sort_order INT DEFAULT NULL, id UUID NOT NULL, game_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX card_identity_game_external_id_unique ON card_identity (game_id, external_id)');
        $this->addSql('CREATE INDEX IDX_D3A42747E48FD905 ON card_identity (game_id)');
        $this->addSql('ALTER TABLE card_identity_link ADD CONSTRAINT FK_C8DB99544ACC9A20 FOREIGN KEY (card_id) REFERENCES card (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE card_identity_link ADD CONSTRAINT FK_C8DB995418EA8B32 FOREIGN KEY (card_identity_id) REFERENCES card_identity (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE card_identity ADD CONSTRAINT FK_D3A42747E48FD905 FOREIGN KEY (game_id) REFERENCES game (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE game ADD identity_label VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card_identity_link DROP CONSTRAINT FK_C8DB99544ACC9A20');
        $this->addSql('ALTER TABLE card_identity_link DROP CONSTRAINT FK_C8DB995418EA8B32');
        $this->addSql('ALTER TABLE card_identity DROP CONSTRAINT FK_D3A42747E48FD905');
        $this->addSql('DROP TABLE card_identity_link');
        $this->addSql('DROP TABLE card_identity');
        $this->addSql('ALTER TABLE game DROP identity_label');
    }
}
