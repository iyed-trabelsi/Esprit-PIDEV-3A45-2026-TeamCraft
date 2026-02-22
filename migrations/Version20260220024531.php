<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220024531 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE teamcraft_media_comment (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, media_submission_id INT NOT NULL, INDEX IDX_85C28483A76ED395 (user_id), INDEX IDX_85C2848359A7792 (media_submission_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE teamcraft_media_like (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, media_submission_id INT NOT NULL, INDEX IDX_F30DEB2CA76ED395 (user_id), INDEX IDX_F30DEB2C59A7792 (media_submission_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE teamcraft_media_comment ADD CONSTRAINT FK_85C28483A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE teamcraft_media_comment ADD CONSTRAINT FK_85C2848359A7792 FOREIGN KEY (media_submission_id) REFERENCES teamcraft_media_submission (id)');
        $this->addSql('ALTER TABLE teamcraft_media_like ADD CONSTRAINT FK_F30DEB2CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE teamcraft_media_like ADD CONSTRAINT FK_F30DEB2C59A7792 FOREIGN KEY (media_submission_id) REFERENCES teamcraft_media_submission (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE teamcraft_media_comment DROP FOREIGN KEY FK_85C28483A76ED395');
        $this->addSql('ALTER TABLE teamcraft_media_comment DROP FOREIGN KEY FK_85C2848359A7792');
        $this->addSql('ALTER TABLE teamcraft_media_like DROP FOREIGN KEY FK_F30DEB2CA76ED395');
        $this->addSql('ALTER TABLE teamcraft_media_like DROP FOREIGN KEY FK_F30DEB2C59A7792');
        $this->addSql('DROP TABLE teamcraft_media_comment');
        $this->addSql('DROP TABLE teamcraft_media_like');
    }
}
