<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261116102525 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Decks préconstruits (catalogue MTGJSON) et lien « basé sur » des decks';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE precon_deck (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(150) NOT NULL, name VARCHAR(150) NOT NULL, set_code VARCHAR(10) NOT NULL, released_at DATE NOT NULL, commander_names VARCHAR(255) NOT NULL, cover_uri VARCHAR(255) DEFAULT NULL, unresolved_count INT NOT NULL, set_id BINARY(16) DEFAULT NULL, UNIQUE INDEX UNIQ_B422361E77153098 (code), INDEX idx_precon_released_at (released_at), INDEX IDX_B422361E10FB0D18 (set_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE precon_deck_card (id INT AUTO_INCREMENT NOT NULL, zone VARCHAR(10) NOT NULL, quantity INT NOT NULL, deck_id INT NOT NULL, oracle_card_id BINARY(16) NOT NULL, printing_id BINARY(16) DEFAULT NULL, INDEX IDX_FAC19F64111948DC (deck_id), INDEX IDX_FAC19F642D12DFA8 (oracle_card_id), INDEX IDX_FAC19F647C9783D2 (printing_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE precon_deck ADD CONSTRAINT FK_B422361E10FB0D18 FOREIGN KEY (set_id) REFERENCES mtg_set (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE precon_deck_card ADD CONSTRAINT FK_FAC19F64111948DC FOREIGN KEY (deck_id) REFERENCES precon_deck (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE precon_deck_card ADD CONSTRAINT FK_FAC19F642D12DFA8 FOREIGN KEY (oracle_card_id) REFERENCES oracle_card (id)');
        $this->addSql('ALTER TABLE precon_deck_card ADD CONSTRAINT FK_FAC19F647C9783D2 FOREIGN KEY (printing_id) REFERENCES card_printing (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE deck ADD based_on_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE deck ADD CONSTRAINT FK_4FAC3637D7850ECE FOREIGN KEY (based_on_id) REFERENCES precon_deck (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_4FAC3637D7850ECE ON deck (based_on_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE precon_deck DROP FOREIGN KEY FK_B422361E10FB0D18');
        $this->addSql('ALTER TABLE precon_deck_card DROP FOREIGN KEY FK_FAC19F64111948DC');
        $this->addSql('ALTER TABLE precon_deck_card DROP FOREIGN KEY FK_FAC19F642D12DFA8');
        $this->addSql('ALTER TABLE precon_deck_card DROP FOREIGN KEY FK_FAC19F647C9783D2');
        $this->addSql('DROP TABLE precon_deck');
        $this->addSql('DROP TABLE precon_deck_card');
        $this->addSql('ALTER TABLE deck DROP FOREIGN KEY FK_4FAC3637D7850ECE');
        $this->addSql('DROP INDEX IDX_4FAC3637D7850ECE ON deck');
        $this->addSql('ALTER TABLE deck DROP based_on_id');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
