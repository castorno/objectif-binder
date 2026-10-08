<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008082437 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Deleting a user now deletes their owned cards and favorites (ON DELETE CASCADE).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE favorite DROP CONSTRAINT fk_68c58ed9a76ed395');
        $this->addSql('ALTER TABLE favorite ADD CONSTRAINT FK_68C58ED9A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE owned_card DROP CONSTRAINT fk_553d1ac5a76ed395');
        $this->addSql('ALTER TABLE owned_card ADD CONSTRAINT FK_553D1AC5A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE favorite DROP CONSTRAINT FK_68C58ED9A76ED395');
        $this->addSql('ALTER TABLE favorite ADD CONSTRAINT fk_68c58ed9a76ed395 FOREIGN KEY (user_id) REFERENCES app_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE owned_card DROP CONSTRAINT FK_553D1AC5A76ED395');
        $this->addSql('ALTER TABLE owned_card ADD CONSTRAINT fk_553d1ac5a76ed395 FOREIGN KEY (user_id) REFERENCES app_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
