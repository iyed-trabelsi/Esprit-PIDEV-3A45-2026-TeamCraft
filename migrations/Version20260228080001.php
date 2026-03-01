<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260228080001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove team_id from evenement table';
    }

    public function up(Schema $schema): void
    {
        // Drop foreign key constraint if it exists
        $this->addSql('ALTER TABLE evenement DROP FOREIGN KEY IF EXISTS FK_B26681E296CD8AE');
        
        // Drop the team_id column if it exists
        $this->addSql('ALTER TABLE evenement DROP COLUMN IF EXISTS team_id');
        
        // Drop the index if it exists
        $this->addSql('DROP INDEX IF EXISTS IDX_B26681E296CD8AE ON evenement');
    }

    public function down(Schema $schema): void
    {
        // Re-add the team_id column
        $this->addSql('ALTER TABLE evenement ADD COLUMN team_id VARCHAR(50) DEFAULT NULL');
        
        // Re-add the index if it doesn't exist
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_B26681E296CD8AE ON evenement (team_id)');
        
        // Re-add the foreign key constraint
        $this->addSql('ALTER TABLE evenement ADD CONSTRAINT FK_B26681E296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
    }
}
