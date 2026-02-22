<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260221153458 extends AbstractMigration
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
        $this->addSql('ALTER TABLE evenement ADD average_rating DOUBLE PRECISION DEFAULT 0 NOT NULL, ADD review_count INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event_review DROP FOREIGN KEY FK_4BDAF694A76ED395');
        $this->addSql('ALTER TABLE event_review DROP FOREIGN KEY FK_4BDAF694FD02F13');
        $this->addSql('DROP TABLE event_review');
        $this->addSql('ALTER TABLE evenement DROP average_rating, DROP review_count');
    }
}
