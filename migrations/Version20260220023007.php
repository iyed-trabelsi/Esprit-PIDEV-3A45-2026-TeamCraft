<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220023007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE teamcraft_media_submission ADD description LONGTEXT DEFAULT NULL, ADD status VARCHAR(20) NOT NULL, ADD visibility VARCHAR(20) NOT NULL, ADD width INT DEFAULT NULL, ADD height INT DEFAULT NULL, ADD duration INT DEFAULT NULL, ADD size INT DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE teamcraft_media_submission DROP description, DROP status, DROP visibility, DROP width, DROP height, DROP duration, DROP size, DROP updated_at');
    }
}
