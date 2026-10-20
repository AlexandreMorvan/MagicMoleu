<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261020220811 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index (rarity, oracle_card_id) pour le filtre de rareté de la recherche';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_printing_rarity ON card_printing');
        $this->addSql('CREATE INDEX idx_printing_rarity_oracle ON card_printing (rarity, oracle_card_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_printing_rarity_oracle ON card_printing');
        $this->addSql('CREATE INDEX idx_printing_rarity ON card_printing (rarity)');
    }
}
