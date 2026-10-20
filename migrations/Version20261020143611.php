<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261020143611 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial : cartes Scryfall et utilisateurs';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE card_face (id INT AUTO_INCREMENT NOT NULL, position SMALLINT NOT NULL, name VARCHAR(255) NOT NULL, mana_cost VARCHAR(100) DEFAULT NULL, type_line VARCHAR(255) DEFAULT NULL, oracle_text LONGTEXT DEFAULT NULL, power VARCHAR(10) DEFAULT NULL, toughness VARCHAR(10) DEFAULT NULL, loyalty VARCHAR(10) DEFAULT NULL, defense VARCHAR(10) DEFAULT NULL, colors SMALLINT NOT NULL, oracle_card_id BINARY(16) NOT NULL, UNIQUE INDEX uniq_face_position (oracle_card_id, position), INDEX IDX_E92779C52D12DFA8 (oracle_card_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE card_legality (format VARCHAR(20) NOT NULL, status VARCHAR(12) NOT NULL, oracle_card_id BINARY(16) NOT NULL, INDEX idx_legality_format_status (format, status), INDEX IDX_67066B3D2D12DFA8 (oracle_card_id), PRIMARY KEY (oracle_card_id, format)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE card_localization (id INT AUTO_INCREMENT NOT NULL, lang VARCHAR(5) NOT NULL, printed_name VARCHAR(255) NOT NULL, printed_type_line VARCHAR(255) DEFAULT NULL, printed_text LONGTEXT DEFAULT NULL, oracle_card_id BINARY(16) NOT NULL, FULLTEXT INDEX ft_localization_text (printed_name, printed_text), UNIQUE INDEX uniq_localization_card_lang (oracle_card_id, lang), INDEX IDX_88E131422D12DFA8 (oracle_card_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE card_printing (id BINARY(16) NOT NULL, collector_number VARCHAR(20) NOT NULL, lang VARCHAR(5) NOT NULL, rarity VARCHAR(10) NOT NULL, artist VARCHAR(255) DEFAULT NULL, flavor_text LONGTEXT DEFAULT NULL, image_uris JSON NOT NULL, prices JSON NOT NULL, finishes JSON NOT NULL, released_at DATE NOT NULL, promo TINYINT DEFAULT 0 NOT NULL, full_art TINYINT DEFAULT 0 NOT NULL, digital TINYINT DEFAULT 0 NOT NULL, frame VARCHAR(10) NOT NULL, border_color VARCHAR(20) NOT NULL, scryfall_uri VARCHAR(255) NOT NULL, oracle_card_id BINARY(16) NOT NULL, set_id BINARY(16) NOT NULL, INDEX idx_printing_released_at (released_at), INDEX idx_printing_rarity (rarity), UNIQUE INDEX uniq_printing_set_number_lang (set_id, collector_number, lang), INDEX IDX_89BEA3702D12DFA8 (oracle_card_id), INDEX IDX_89BEA37010FB0D18 (set_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mtg_set (id BINARY(16) NOT NULL, code VARCHAR(10) NOT NULL, name VARCHAR(255) NOT NULL, set_type VARCHAR(30) NOT NULL, released_at DATE DEFAULT NULL, card_count INT DEFAULT 0 NOT NULL, icon_svg_uri VARCHAR(255) DEFAULT NULL, parent_set_code VARCHAR(10) DEFAULT NULL, digital TINYINT DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_4697F5EB77153098 (code), INDEX idx_set_released_at (released_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE oracle_card (id BINARY(16) NOT NULL, name VARCHAR(255) NOT NULL, layout VARCHAR(30) NOT NULL, mana_cost VARCHAR(100) DEFAULT NULL, cmc DOUBLE PRECISION NOT NULL, type_line VARCHAR(255) NOT NULL, oracle_text LONGTEXT DEFAULT NULL, power VARCHAR(10) DEFAULT NULL, toughness VARCHAR(10) DEFAULT NULL, loyalty VARCHAR(10) DEFAULT NULL, defense VARCHAR(10) DEFAULT NULL, colors SMALLINT NOT NULL, color_identity SMALLINT NOT NULL, keywords JSON NOT NULL, produced_mana JSON NOT NULL, edhrec_rank INT DEFAULT NULL, reserved TINYINT DEFAULT 0 NOT NULL, game_changer TINYINT DEFAULT 0 NOT NULL, first_printed_at DATE DEFAULT NULL, image_uri VARCHAR(255) DEFAULT NULL, art_crop_uri VARCHAR(255) DEFAULT NULL, image_rank INT DEFAULT NULL, INDEX idx_oracle_name (name), INDEX idx_oracle_cmc (cmc), INDEX idx_oracle_colors (colors), INDEX idx_oracle_color_identity (color_identity), FULLTEXT INDEX ft_oracle_text (name, type_line, oracle_text), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, username VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE card_face ADD CONSTRAINT FK_E92779C52D12DFA8 FOREIGN KEY (oracle_card_id) REFERENCES oracle_card (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE card_legality ADD CONSTRAINT FK_67066B3D2D12DFA8 FOREIGN KEY (oracle_card_id) REFERENCES oracle_card (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE card_localization ADD CONSTRAINT FK_88E131422D12DFA8 FOREIGN KEY (oracle_card_id) REFERENCES oracle_card (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE card_printing ADD CONSTRAINT FK_89BEA3702D12DFA8 FOREIGN KEY (oracle_card_id) REFERENCES oracle_card (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE card_printing ADD CONSTRAINT FK_89BEA37010FB0D18 FOREIGN KEY (set_id) REFERENCES mtg_set (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card_face DROP FOREIGN KEY FK_E92779C52D12DFA8');
        $this->addSql('ALTER TABLE card_legality DROP FOREIGN KEY FK_67066B3D2D12DFA8');
        $this->addSql('ALTER TABLE card_localization DROP FOREIGN KEY FK_88E131422D12DFA8');
        $this->addSql('ALTER TABLE card_printing DROP FOREIGN KEY FK_89BEA3702D12DFA8');
        $this->addSql('ALTER TABLE card_printing DROP FOREIGN KEY FK_89BEA37010FB0D18');
        $this->addSql('DROP TABLE card_face');
        $this->addSql('DROP TABLE card_legality');
        $this->addSql('DROP TABLE card_localization');
        $this->addSql('DROP TABLE card_printing');
        $this->addSql('DROP TABLE mtg_set');
        $this->addSql('DROP TABLE oracle_card');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
