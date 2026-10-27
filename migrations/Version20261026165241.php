<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261026165241 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Logo des éditions (URL d\'origine et copie locale)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mtg_set ADD logo_source_url VARCHAR(500) DEFAULT NULL, ADD logo_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mtg_set DROP logo_source_url, DROP logo_path');
    }
}
