<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261026155011 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Collections de cartes (card_collection, collection_card)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE card_collection (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, owner_id INT NOT NULL, INDEX IDX_903FF83C7E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE collection_card (id INT AUTO_INCREMENT NOT NULL, added_at DATETIME NOT NULL, quantity INT NOT NULL, foil TINYINT DEFAULT 0 NOT NULL, collection_id INT NOT NULL, printing_id BINARY(16) NOT NULL, UNIQUE INDEX uniq_collection_printing_foil (collection_id, printing_id, foil), INDEX IDX_5C63B43D514956FD (collection_id), INDEX IDX_5C63B43D7C9783D2 (printing_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE card_collection ADD CONSTRAINT FK_903FF83C7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE collection_card ADD CONSTRAINT FK_5C63B43D514956FD FOREIGN KEY (collection_id) REFERENCES card_collection (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE collection_card ADD CONSTRAINT FK_5C63B43D7C9783D2 FOREIGN KEY (printing_id) REFERENCES card_printing (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_collection DROP FOREIGN KEY FK_903FF83C7E3C61F9');
        $this->addSql('ALTER TABLE collection_card DROP FOREIGN KEY FK_5C63B43D514956FD');
        $this->addSql('ALTER TABLE collection_card DROP FOREIGN KEY FK_5C63B43D7C9783D2');
        $this->addSql('DROP TABLE card_collection');
        $this->addSql('DROP TABLE collection_card');
    }
}
