<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260209231953 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_880E0D76A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE evenement (id_evenement INT AUTO_INCREMENT NOT NULL, nom_evenement VARCHAR(255) NOT NULL, type_evenement VARCHAR(255) NOT NULL, date_debut DATETIME NOT NULL, date_fin DATETIME NOT NULL, status VARCHAR(255) NOT NULL, place_id INT NOT NULL, organisateur_id INT NOT NULL, INDEX IDX_B26681EDA6A219 (place_id), INDEX IDX_B26681ED936B2FA (organisateur_id), PRIMARY KEY (id_evenement)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE manager (id INT AUTO_INCREMENT NOT NULL, organization_name VARCHAR(100) DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_FA2425B9A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE offer (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, date_creation DATE NOT NULL, date_expiration DATE NOT NULL, description LONGTEXT NOT NULL, poster VARCHAR(255) DEFAULT NULL, game VARCHAR(255) NOT NULL, role VARCHAR(255) NOT NULL, rank VARCHAR(255) NOT NULL, nb_player_recruited INT NOT NULL, team_id INT NOT NULL, INDEX IDX_29D6873E296CD8AE (team_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE participation (id_participation INT AUTO_INCREMENT NOT NULL, date_inscription DATETIME DEFAULT NULL, evenement_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_AB55E24FFD02F13 (evenement_id), INDEX IDX_AB55E24FA76ED395 (user_id), PRIMARY KEY (id_participation)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE place (id_place INT AUTO_INCREMENT NOT NULL, nom_place VARCHAR(255) NOT NULL, type_place VARCHAR(255) NOT NULL, adresse VARCHAR(255) NOT NULL, capacite_max INT NOT NULL, PRIMARY KEY (id_place)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE player (id INT AUTO_INCREMENT NOT NULL, game_rank VARCHAR(50) DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_98197A65A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE team (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, logo VARCHAR(255) DEFAULT NULL, games JSON NOT NULL, created_at DATETIME NOT NULL, owner_id INT NOT NULL, INDEX IDX_C4E0A61F7E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE teamcraft_comments (id INT AUTO_INCREMENT NOT NULL, contenu VARCHAR(255) NOT NULL, date_commentaire DATE NOT NULL, nb_likes INT NOT NULL, auteur_id INT NOT NULL, post_id INT NOT NULL, parent_id INT DEFAULT NULL, INDEX IDX_42FEE15F60BB6FE6 (auteur_id), INDEX IDX_42FEE15F4B89032C (post_id), INDEX IDX_42FEE15F727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE teamcraft_post (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(30) NOT NULL, contenu VARCHAR(255) DEFAULT NULL, type_post VARCHAR(30) NOT NULL, date_creation DATE NOT NULL, nb_vues INT NOT NULL, statut VARCHAR(20) NOT NULL, nb_likes INT NOT NULL, image VARCHAR(255) DEFAULT NULL, auteur_id INT NOT NULL, rubrique_id INT NOT NULL, INDEX IDX_588FFE0A60BB6FE6 (auteur_id), INDEX IDX_588FFE0A3BD38833 (rubrique_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE teamcraft_rubrique (id INT AUTO_INCREMENT NOT NULL, nom_rubrique VARCHAR(30) NOT NULL, description VARCHAR(255) DEFAULT NULL, topic VARCHAR(255) DEFAULT NULL, date_creation DATE NOT NULL, etat VARCHAR(20) NOT NULL, nb_posts INT NOT NULL, auteur_id INT NOT NULL, INDEX IDX_92C47E0960BB6FE6 (auteur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, pseudo VARCHAR(100) NOT NULL, username VARCHAR(100) NOT NULL, user_type VARCHAR(50) NOT NULL, name VARCHAR(100) NOT NULL, sexe VARCHAR(10) DEFAULT NULL, is_active TINYINT NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE admin ADD CONSTRAINT FK_880E0D76A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE evenement ADD CONSTRAINT FK_B26681EDA6A219 FOREIGN KEY (place_id) REFERENCES place (id_place)');
        $this->addSql('ALTER TABLE evenement ADD CONSTRAINT FK_B26681ED936B2FA FOREIGN KEY (organisateur_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE manager ADD CONSTRAINT FK_FA2425B9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873E296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24FFD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id_evenement)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE player ADD CONSTRAINT FK_98197A65A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE team ADD CONSTRAINT FK_C4E0A61F7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE teamcraft_comments ADD CONSTRAINT FK_42FEE15F60BB6FE6 FOREIGN KEY (auteur_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE teamcraft_comments ADD CONSTRAINT FK_42FEE15F4B89032C FOREIGN KEY (post_id) REFERENCES teamcraft_post (id)');
        $this->addSql('ALTER TABLE teamcraft_comments ADD CONSTRAINT FK_42FEE15F727ACA70 FOREIGN KEY (parent_id) REFERENCES teamcraft_comments (id)');
        $this->addSql('ALTER TABLE teamcraft_post ADD CONSTRAINT FK_588FFE0A60BB6FE6 FOREIGN KEY (auteur_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE teamcraft_post ADD CONSTRAINT FK_588FFE0A3BD38833 FOREIGN KEY (rubrique_id) REFERENCES teamcraft_rubrique (id)');
        $this->addSql('ALTER TABLE teamcraft_rubrique ADD CONSTRAINT FK_92C47E0960BB6FE6 FOREIGN KEY (auteur_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin DROP FOREIGN KEY FK_880E0D76A76ED395');
        $this->addSql('ALTER TABLE evenement DROP FOREIGN KEY FK_B26681EDA6A219');
        $this->addSql('ALTER TABLE evenement DROP FOREIGN KEY FK_B26681ED936B2FA');
        $this->addSql('ALTER TABLE manager DROP FOREIGN KEY FK_FA2425B9A76ED395');
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873E296CD8AE');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FFD02F13');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FA76ED395');
        $this->addSql('ALTER TABLE player DROP FOREIGN KEY FK_98197A65A76ED395');
        $this->addSql('ALTER TABLE team DROP FOREIGN KEY FK_C4E0A61F7E3C61F9');
        $this->addSql('ALTER TABLE teamcraft_comments DROP FOREIGN KEY FK_42FEE15F60BB6FE6');
        $this->addSql('ALTER TABLE teamcraft_comments DROP FOREIGN KEY FK_42FEE15F4B89032C');
        $this->addSql('ALTER TABLE teamcraft_comments DROP FOREIGN KEY FK_42FEE15F727ACA70');
        $this->addSql('ALTER TABLE teamcraft_post DROP FOREIGN KEY FK_588FFE0A60BB6FE6');
        $this->addSql('ALTER TABLE teamcraft_post DROP FOREIGN KEY FK_588FFE0A3BD38833');
        $this->addSql('ALTER TABLE teamcraft_rubrique DROP FOREIGN KEY FK_92C47E0960BB6FE6');
        $this->addSql('DROP TABLE admin');
        $this->addSql('DROP TABLE evenement');
        $this->addSql('DROP TABLE manager');
        $this->addSql('DROP TABLE offer');
        $this->addSql('DROP TABLE participation');
        $this->addSql('DROP TABLE place');
        $this->addSql('DROP TABLE player');
        $this->addSql('DROP TABLE team');
        $this->addSql('DROP TABLE teamcraft_comments');
        $this->addSql('DROP TABLE teamcraft_post');
        $this->addSql('DROP TABLE teamcraft_rubrique');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
