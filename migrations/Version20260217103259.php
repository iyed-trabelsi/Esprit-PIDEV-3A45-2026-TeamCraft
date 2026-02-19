<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260217103259 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE teamcraft_comments DROP FOREIGN KEY `FK_42FEE15F4B89032C`');
        $this->addSql('ALTER TABLE teamcraft_comments DROP FOREIGN KEY `FK_42FEE15F727ACA70`');
        $this->addSql('ALTER TABLE teamcraft_comments ADD CONSTRAINT FK_42FEE15F4B89032C FOREIGN KEY (post_id) REFERENCES teamcraft_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE teamcraft_comments ADD CONSTRAINT FK_42FEE15F727ACA70 FOREIGN KEY (parent_id) REFERENCES teamcraft_comments (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE teamcraft_rubrique ADD image VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE teamcraft_comments DROP FOREIGN KEY FK_42FEE15F4B89032C');
        $this->addSql('ALTER TABLE teamcraft_comments DROP FOREIGN KEY FK_42FEE15F727ACA70');
        $this->addSql('ALTER TABLE teamcraft_comments ADD CONSTRAINT `FK_42FEE15F4B89032C` FOREIGN KEY (post_id) REFERENCES teamcraft_post (id)');
        $this->addSql('ALTER TABLE teamcraft_comments ADD CONSTRAINT `FK_42FEE15F727ACA70` FOREIGN KEY (parent_id) REFERENCES teamcraft_comments (id)');
        $this->addSql('ALTER TABLE teamcraft_rubrique DROP image');
    }
}
