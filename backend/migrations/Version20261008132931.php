<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008132931 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sort card numbers the way a person reads them: 2 before 10.';
    }

    public function up(Schema $schema): void
    {
        // Added by hand: Doctrine generates the column change below, but
        // cannot create the collation it refers to. "kn" makes the digits
        // inside a text compare as numbers. Needs PostgreSQL built with ICU,
        // which the official images are.
        $this->addSql("CREATE COLLATION natural_sort (provider = icu, locale = 'und-u-kn')");
        $this->addSql('ALTER TABLE card ALTER number_in_set TYPE VARCHAR(20) COLLATE "natural_sort"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card ALTER number_in_set TYPE VARCHAR(20) COLLATE "default"');
        $this->addSql('DROP COLLATION natural_sort');
    }
}
