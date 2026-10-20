<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261020150454 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Date de révélation et source des aperçus (spoilers)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card_printing ADD revealed_at DATE DEFAULT NULL, ADD previewed_at DATE DEFAULT NULL, ADD preview_source VARCHAR(100) DEFAULT NULL, ADD preview_source_uri VARCHAR(255) DEFAULT NULL');
        // Cartes déjà en base : révélées au plus tard à leur sortie (ou aujourd'hui pour les éditions à venir)
        $this->addSql('UPDATE card_printing SET revealed_at = LEAST(released_at, CURRENT_DATE)');
        $this->addSql('ALTER TABLE card_printing MODIFY revealed_at DATE NOT NULL');
        $this->addSql('CREATE INDEX idx_printing_revealed_at ON card_printing (revealed_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_printing_revealed_at ON card_printing');
        $this->addSql('ALTER TABLE card_printing DROP revealed_at, DROP previewed_at, DROP preview_source, DROP preview_source_uri');
    }
}
