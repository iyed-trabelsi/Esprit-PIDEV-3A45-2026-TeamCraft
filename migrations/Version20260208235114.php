<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208235114 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE player ADD selected_games JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD bio LONGTEXT DEFAULT NULL, ADD country VARCHAR(100) DEFAULT NULL, ADD profile_picture VARCHAR(255) DEFAULT NULL, CHANGE pseudo pseudo VARCHAR(100) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE player DROP selected_games');
        $this->addSql('ALTER TABLE user DROP bio, DROP country, DROP profile_picture, CHANGE pseudo pseudo VARCHAR(100) DEFAULT NULL');
    }
}
