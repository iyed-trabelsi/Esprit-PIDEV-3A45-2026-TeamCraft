<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208150245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE evenement (id_evenement INT AUTO_INCREMENT NOT NULL, nom_evenement VARCHAR(255) NOT NULL, type_evenement VARCHAR(255) NOT NULL, date_debut DATETIME NOT NULL, date_fin DATETIME NOT NULL, status VARCHAR(255) NOT NULL, place_id INT NOT NULL, INDEX IDX_B26681EDA6A219 (place_id), PRIMARY KEY (id_evenement)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE participation (id_participation INT AUTO_INCREMENT NOT NULL, date_inscription DATETIME NOT NULL, evenement_id INT NOT NULL, INDEX IDX_AB55E24FFD02F13 (evenement_id), PRIMARY KEY (id_participation)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE place (id_place INT AUTO_INCREMENT NOT NULL, nom_place VARCHAR(255) NOT NULL, type_place VARCHAR(255) NOT NULL, adresse VARCHAR(255) DEFAULT NULL, capacite_max INT NOT NULL, PRIMARY KEY (id_place)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE evenement ADD CONSTRAINT FK_B26681EDA6A219 FOREIGN KEY (place_id) REFERENCES place (id_place)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24FFD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id_evenement)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE evenement DROP FOREIGN KEY FK_B26681EDA6A219');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FFD02F13');
        $this->addSql('DROP TABLE evenement');
        $this->addSql('DROP TABLE participation');
        $this->addSql('DROP TABLE place');
    }
}
