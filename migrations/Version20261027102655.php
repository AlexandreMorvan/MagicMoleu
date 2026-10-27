<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027102655 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Exemplaires de collection : langue, état et prix d\'achat (import CSV ManaBox)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_collection_printing_foil ON collection_card');
        $this->addSql('ALTER TABLE collection_card ADD lang VARCHAR(5) DEFAULT \'en\' NOT NULL, ADD card_condition VARCHAR(15) DEFAULT \'near_mint\' NOT NULL, ADD purchase_price NUMERIC(10, 2) DEFAULT NULL, ADD purchase_currency VARCHAR(3) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_collection_card_copy ON collection_card (collection_id, printing_id, foil, lang, card_condition)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_collection_card_copy ON collection_card');
        $this->addSql('ALTER TABLE collection_card DROP lang, DROP card_condition, DROP purchase_price, DROP purchase_currency');
        $this->addSql('CREATE UNIQUE INDEX uniq_collection_printing_foil ON collection_card (collection_id, printing_id, foil)');
    }
}
