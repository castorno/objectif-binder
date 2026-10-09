<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261009172452 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Where a pull rate comes from and when it was entered.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pull_rate ADD source VARCHAR(255) DEFAULT NULL');
        // Written by hand: the rates already there are dated from now, then
        // the default goes away so that the application always gives the date.
        $this->addSql('ALTER TABLE pull_rate ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE pull_rate ALTER updated_at DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pull_rate DROP source');
        $this->addSql('ALTER TABLE pull_rate DROP updated_at');
    }
}
