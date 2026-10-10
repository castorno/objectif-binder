<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'An owned card has a finish, which is part of what identifies it.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE owned_card ADD finish VARCHAR(20) DEFAULT NULL');
        // Written by hand: the copies already owned get the finish a card has
        // when nothing more is said, the same rule as Card::getBaseFinish():
        // plain when the card exists that way or when its finishes are
        // unknown, otherwise the first one it was printed with.
        $this->addSql(<<<'SQL'
            UPDATE owned_card o
            SET finish = CASE
                WHEN c.finishes IS NULL OR json_array_length(c.finishes) = 0 THEN 'normal'
                WHEN c.finishes::text LIKE '%"normal"%' THEN 'normal'
                ELSE c.finishes ->> 0
            END
            FROM card c
            WHERE c.id = o.card_id
            SQL);
        $this->addSql('ALTER TABLE owned_card ALTER finish SET NOT NULL');
        $this->addSql('DROP INDEX owned_card_user_card_language_unique');
        $this->addSql('CREATE UNIQUE INDEX owned_card_user_card_language_finish_unique ON owned_card (user_id, card_id, language, finish)');
    }

    public function down(Schema $schema): void
    {
        // Copies of one card in one language become a single entry again:
        // only one finish of each can be kept.
        $this->addSql(<<<'SQL'
            DELETE FROM owned_card o
            USING owned_card kept
            WHERE kept.user_id = o.user_id AND kept.card_id = o.card_id AND kept.language = o.language AND kept.id < o.id
            SQL);
        $this->addSql('DROP INDEX owned_card_user_card_language_finish_unique');
        $this->addSql('CREATE UNIQUE INDEX owned_card_user_card_language_unique ON owned_card (user_id, card_id, language)');
        $this->addSql('ALTER TABLE owned_card DROP finish');
    }
}
