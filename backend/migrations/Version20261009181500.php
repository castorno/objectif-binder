<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009181500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'A pull rate becomes "so many cards for so many boosters".';
    }

    public function up(Schema $schema): void
    {
        // Written by hand: "1 chance in N" is one card for N boosters, so the
        // column is renamed and keeps its values. A generated migration would
        // drop it and add another, losing them.
        $this->addSql('ALTER TABLE pull_rate RENAME COLUMN odds_one_in TO booster_count');
        $this->addSql('ALTER TABLE pull_rate ADD card_count INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE pull_rate ALTER card_count DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // Rates of several cards per booster cannot be written as "1 chance in N".
        $this->addSql('DELETE FROM pull_rate WHERE card_count <> 1');
        $this->addSql('ALTER TABLE pull_rate DROP card_count');
        $this->addSql('ALTER TABLE pull_rate RENAME COLUMN booster_count TO odds_one_in');
    }
}
