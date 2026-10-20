<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261020145123 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Decks et impression par défaut des cartes';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE deck (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, format VARCHAR(20) NOT NULL, description LONGTEXT DEFAULT NULL, public TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, owner_id INT NOT NULL, INDEX idx_deck_public_updated (public, updated_at), INDEX IDX_4FAC36377E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE deck_card (id INT AUTO_INCREMENT NOT NULL, zone VARCHAR(10) NOT NULL, quantity INT NOT NULL, deck_id INT NOT NULL, oracle_card_id BINARY(16) NOT NULL, printing_id BINARY(16) DEFAULT NULL, UNIQUE INDEX uniq_deck_card_zone (deck_id, oracle_card_id, zone), INDEX IDX_2AF3DCED111948DC (deck_id), INDEX IDX_2AF3DCED2D12DFA8 (oracle_card_id), INDEX IDX_2AF3DCED7C9783D2 (printing_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE deck ADD CONSTRAINT FK_4FAC36377E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE deck_card ADD CONSTRAINT FK_2AF3DCED111948DC FOREIGN KEY (deck_id) REFERENCES deck (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE deck_card ADD CONSTRAINT FK_2AF3DCED2D12DFA8 FOREIGN KEY (oracle_card_id) REFERENCES oracle_card (id)');
        $this->addSql('ALTER TABLE deck_card ADD CONSTRAINT FK_2AF3DCED7C9783D2 FOREIGN KEY (printing_id) REFERENCES card_printing (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE oracle_card ADD default_printing_id BINARY(16) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE deck DROP FOREIGN KEY FK_4FAC36377E3C61F9');
        $this->addSql('ALTER TABLE deck_card DROP FOREIGN KEY FK_2AF3DCED111948DC');
        $this->addSql('ALTER TABLE deck_card DROP FOREIGN KEY FK_2AF3DCED2D12DFA8');
        $this->addSql('ALTER TABLE deck_card DROP FOREIGN KEY FK_2AF3DCED7C9783D2');
        $this->addSql('DROP TABLE deck');
        $this->addSql('DROP TABLE deck_card');
        $this->addSql('ALTER TABLE oracle_card DROP default_printing_id');
    }
}
