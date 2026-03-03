<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260303155830 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE evenement CHANGE average_rating average_rating DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE postulation ADD cv_file_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user DROP team_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE evenement CHANGE average_rating average_rating DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE postulation DROP cv_file_name');
        $this->addSql('ALTER TABLE user ADD team_id VARCHAR(50) DEFAULT NULL');
    }
}
