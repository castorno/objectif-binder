<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261009183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'A set can come in the boosters of another.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card_set ADD parent_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE card_set ADD CONSTRAINT FK_B6E4A11D727ACA70 FOREIGN KEY (parent_id) REFERENCES card_set (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_B6E4A11D727ACA70 ON card_set (parent_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card_set DROP CONSTRAINT FK_B6E4A11D727ACA70');
        $this->addSql('DROP INDEX IDX_B6E4A11D727ACA70');
        $this->addSql('ALTER TABLE card_set DROP parent_id');
    }
}
