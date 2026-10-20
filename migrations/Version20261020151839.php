<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261020151839 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Illustration de couverture des éditions';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mtg_set ADD cover_art_uri VARCHAR(255) DEFAULT NULL, ADD cover_card_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mtg_set DROP cover_art_uri, DROP cover_card_name');
    }
}
