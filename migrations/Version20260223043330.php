<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260223043330 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event_review (id INT AUTO_INCREMENT NOT NULL, rating NUMERIC(3, 1) NOT NULL, message LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, evenement_id INT NOT NULL, INDEX IDX_4BDAF694A76ED395 (user_id), INDEX IDX_4BDAF694FD02F13 (evenement_id), UNIQUE INDEX UNIQ_USER_EVENT_REVIEW (user_id, evenement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE event_review ADD CONSTRAINT FK_4BDAF694A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_review ADD CONSTRAINT FK_4BDAF694FD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id_evenement) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE evenement ADD average_rating DOUBLE PRECISION DEFAULT 0 NOT NULL, ADD review_count INT DEFAULT 0 NOT NULL, ADD image_evenement VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE player ADD availability VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD youtube VARCHAR(255) DEFAULT NULL, ADD twitch VARCHAR(255) DEFAULT NULL, ADD kick VARCHAR(255) DEFAULT NULL, ADD twitter VARCHAR(255) DEFAULT NULL, ADD discord VARCHAR(255) DEFAULT NULL, ADD steam_id VARCHAR(20) DEFAULT NULL, ADD is_music_enabled TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649F3FD4ECA ON user (steam_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event_review DROP FOREIGN KEY FK_4BDAF694A76ED395');
        $this->addSql('ALTER TABLE event_review DROP FOREIGN KEY FK_4BDAF694FD02F13');
        $this->addSql('DROP TABLE event_review');
        $this->addSql('ALTER TABLE evenement DROP average_rating, DROP review_count, DROP image_evenement');
        $this->addSql('ALTER TABLE player DROP availability');
        $this->addSql('DROP INDEX UNIQ_8D93D649F3FD4ECA ON user');
        $this->addSql('ALTER TABLE user DROP youtube, DROP twitch, DROP kick, DROP twitter, DROP discord, DROP steam_id, DROP is_music_enabled');
    }
}
