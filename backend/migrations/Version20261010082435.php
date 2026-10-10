<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261010082435 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'A set can give all its cards one rarity.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card_set ADD forced_rarity_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE card_set ADD CONSTRAINT FK_B6E4A11D257BAA8A FOREIGN KEY (forced_rarity_id) REFERENCES rarity (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_B6E4A11D257BAA8A ON card_set (forced_rarity_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card_set DROP CONSTRAINT FK_B6E4A11D257BAA8A');
        $this->addSql('DROP INDEX IDX_B6E4A11D257BAA8A');
        $this->addSql('ALTER TABLE card_set DROP forced_rarity_id');
    }
}
