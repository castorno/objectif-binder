<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261013102339 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Picture of an identity itself, served by a third party.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_identity ADD image_url VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_identity DROP image_url');
    }
}
