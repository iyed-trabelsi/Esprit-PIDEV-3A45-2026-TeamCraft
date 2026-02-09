<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260209064707 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE competitive_rank (id INT AUTO_INCREMENT NOT NULL, game VARCHAR(50) NOT NULL, principal_role VARCHAR(100) NOT NULL, skill_level VARCHAR(100) NOT NULL, experience VARCHAR(100) NOT NULL, availability VARCHAR(100) NOT NULL, region VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, player_id INT NOT NULL, INDEX IDX_1EBB5F2999E6F5DF (player_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE competitive_rank ADD CONSTRAINT FK_1EBB5F2999E6F5DF FOREIGN KEY (player_id) REFERENCES player (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE competitive_rank DROP FOREIGN KEY FK_1EBB5F2999E6F5DF');
        $this->addSql('DROP TABLE competitive_rank');
    }
}
