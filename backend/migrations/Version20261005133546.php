<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261005133546 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial card catalog and collection schema: game, card_set, rarity, card, pull_rate, app_user, owned_card, favorite.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_user (email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9E7927C74 ON app_user (email)');
        $this->addSql('CREATE TABLE card (name VARCHAR(200) NOT NULL, number_in_set VARCHAR(20) NOT NULL, external_id VARCHAR(100) DEFAULT NULL, attributes JSON NOT NULL, id UUID NOT NULL, card_set_id UUID NOT NULL, rarity_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX card_set_number_unique ON card (card_set_id, number_in_set)');
        $this->addSql('CREATE INDEX IDX_161498D362C45E6C ON card (card_set_id)');
        $this->addSql('CREATE INDEX IDX_161498D3F3747573 ON card (rarity_id)');
        $this->addSql('CREATE TABLE card_set (name VARCHAR(150) NOT NULL, code VARCHAR(50) NOT NULL, release_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, game_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX card_set_game_code_unique ON card_set (game_id, code)');
        $this->addSql('CREATE INDEX IDX_B6E4A11DE48FD905 ON card_set (game_id)');
        $this->addSql('CREATE TABLE favorite (created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, card_id UUID NOT NULL, PRIMARY KEY (user_id, card_id))');
        $this->addSql('CREATE INDEX IDX_68C58ED9A76ED395 ON favorite (user_id)');
        $this->addSql('CREATE INDEX IDX_68C58ED94ACC9A20 ON favorite (card_id)');
        $this->addSql('CREATE TABLE game (name VARCHAR(100) NOT NULL, slug VARCHAR(100) NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_232B318C5E237E06 ON game (name)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_232B318C989D9B62 ON game (slug)');
        $this->addSql('CREATE TABLE owned_card (language VARCHAR(2) NOT NULL, quantity INT NOT NULL, condition VARCHAR(50) DEFAULT NULL, acquired_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, user_id UUID NOT NULL, card_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX owned_card_user_card_language_unique ON owned_card (user_id, card_id, language)');
        $this->addSql('CREATE INDEX IDX_553D1AC5A76ED395 ON owned_card (user_id)');
        $this->addSql('CREATE INDEX IDX_553D1AC54ACC9A20 ON owned_card (card_id)');
        $this->addSql('CREATE TABLE pull_rate (odds_one_in INT NOT NULL, id UUID NOT NULL, card_set_id UUID NOT NULL, rarity_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX pull_rate_set_rarity_unique ON pull_rate (card_set_id, rarity_id)');
        $this->addSql('CREATE INDEX IDX_85D1190062C45E6C ON pull_rate (card_set_id)');
        $this->addSql('CREATE INDEX IDX_85D11900F3747573 ON pull_rate (rarity_id)');
        $this->addSql('CREATE TABLE rarity (name VARCHAR(100) NOT NULL, sort_order INT NOT NULL, id UUID NOT NULL, game_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX rarity_game_name_unique ON rarity (game_id, name)');
        $this->addSql('CREATE INDEX IDX_B7C0BE46E48FD905 ON rarity (game_id)');
        $this->addSql('ALTER TABLE card ADD CONSTRAINT FK_161498D362C45E6C FOREIGN KEY (card_set_id) REFERENCES card_set (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE card ADD CONSTRAINT FK_161498D3F3747573 FOREIGN KEY (rarity_id) REFERENCES rarity (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE card_set ADD CONSTRAINT FK_B6E4A11DE48FD905 FOREIGN KEY (game_id) REFERENCES game (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE favorite ADD CONSTRAINT FK_68C58ED9A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE favorite ADD CONSTRAINT FK_68C58ED94ACC9A20 FOREIGN KEY (card_id) REFERENCES card (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE owned_card ADD CONSTRAINT FK_553D1AC5A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE owned_card ADD CONSTRAINT FK_553D1AC54ACC9A20 FOREIGN KEY (card_id) REFERENCES card (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE pull_rate ADD CONSTRAINT FK_85D1190062C45E6C FOREIGN KEY (card_set_id) REFERENCES card_set (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE pull_rate ADD CONSTRAINT FK_85D11900F3747573 FOREIGN KEY (rarity_id) REFERENCES rarity (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rarity ADD CONSTRAINT FK_B7C0BE46E48FD905 FOREIGN KEY (game_id) REFERENCES game (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card DROP CONSTRAINT FK_161498D362C45E6C');
        $this->addSql('ALTER TABLE card DROP CONSTRAINT FK_161498D3F3747573');
        $this->addSql('ALTER TABLE card_set DROP CONSTRAINT FK_B6E4A11DE48FD905');
        $this->addSql('ALTER TABLE favorite DROP CONSTRAINT FK_68C58ED9A76ED395');
        $this->addSql('ALTER TABLE favorite DROP CONSTRAINT FK_68C58ED94ACC9A20');
        $this->addSql('ALTER TABLE owned_card DROP CONSTRAINT FK_553D1AC5A76ED395');
        $this->addSql('ALTER TABLE owned_card DROP CONSTRAINT FK_553D1AC54ACC9A20');
        $this->addSql('ALTER TABLE pull_rate DROP CONSTRAINT FK_85D1190062C45E6C');
        $this->addSql('ALTER TABLE pull_rate DROP CONSTRAINT FK_85D11900F3747573');
        $this->addSql('ALTER TABLE rarity DROP CONSTRAINT FK_B7C0BE46E48FD905');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE card');
        $this->addSql('DROP TABLE card_set');
        $this->addSql('DROP TABLE favorite');
        $this->addSql('DROP TABLE game');
        $this->addSql('DROP TABLE owned_card');
        $this->addSql('DROP TABLE pull_rate');
        $this->addSql('DROP TABLE rarity');
    }
}
