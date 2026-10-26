<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261026161337 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Impressions « extra » (variantes, traitements, promos) masquables dans les éditions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_printing ADD extra TINYINT DEFAULT 0 NOT NULL');
        // Première estimation avec les colonnes déjà en base ; le prochain import Scryfall complète
        // (variantes, cadres showcase / extended art, traitements foil, qui ne sont pas stockés)
        $this->addSql("UPDATE card_printing p JOIN oracle_card o ON o.id = p.oracle_card_id SET p.extra = 1 WHERE p.promo = 1 OR p.border_color = 'borderless' OR (p.full_art = 1 AND o.type_line NOT LIKE '%Basic Land%')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_printing DROP extra');
    }
}
