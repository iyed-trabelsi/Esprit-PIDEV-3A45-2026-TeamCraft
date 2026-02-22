<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220021805 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE teamcraft_media_submission (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(10) NOT NULL, title VARCHAR(255) DEFAULT NULL, url VARCHAR(255) NOT NULL, thumbnail_url VARCHAR(255) DEFAULT NULL, views_count INT NOT NULL, likes_count INT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_8CDBB95FA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE teamcraft_media_submission ADD CONSTRAINT FK_8CDBB95FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE teamcraft_media_submission DROP FOREIGN KEY FK_8CDBB95FA76ED395');
        $this->addSql('DROP TABLE teamcraft_media_submission');
    }
}
