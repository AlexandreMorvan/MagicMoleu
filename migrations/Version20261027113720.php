<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027113720 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Compte : avatar, présentation, couleurs et format favoris, préférences ; demandes de réinitialisation de mot de passe';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reset_password_request (id INT AUTO_INCREMENT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_7CE748AA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user ADD bio LONGTEXT DEFAULT NULL, ADD favorite_colors SMALLINT DEFAULT 0 NOT NULL, ADD preferred_format VARCHAR(20) DEFAULT NULL, ADD notification_toasts TINYINT DEFAULT 1 NOT NULL, ADD default_deck_view VARCHAR(12) DEFAULT \'liste\' NOT NULL, ADD default_collection_view VARCHAR(10) DEFAULT \'grid\' NOT NULL, ADD avatar_card_id BINARY(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D6499CA295FE FOREIGN KEY (avatar_card_id) REFERENCES oracle_card (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_8D93D6499CA295FE ON user (avatar_card_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY FK_7CE748AA76ED395');
        $this->addSql('DROP TABLE reset_password_request');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D6499CA295FE');
        $this->addSql('DROP INDEX IDX_8D93D6499CA295FE ON user');
        $this->addSql('ALTER TABLE user DROP bio, DROP favorite_colors, DROP preferred_format, DROP notification_toasts, DROP default_deck_view, DROP default_collection_view, DROP avatar_card_id');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
