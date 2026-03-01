<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260228080002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cvFileName column to postulation table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE postulation ADD cvFileName VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE postulation DROP cvFileName');
    }
}
