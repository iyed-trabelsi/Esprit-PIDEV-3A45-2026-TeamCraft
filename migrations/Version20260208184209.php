<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208184209 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE teamcraft_comments (id INT AUTO_INCREMENT NOT NULL, contenu VARCHAR(255) NOT NULL, date_commentaire DATE NOT NULL, nb_likes INT NOT NULL, auteur_id INT NOT NULL, post_id INT NOT NULL, parent_id INT DEFAULT NULL, INDEX IDX_42FEE15F60BB6FE6 (auteur_id), INDEX IDX_42FEE15F4B89032C (post_id), INDEX IDX_42FEE15F727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE teamcraft_post (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(30) NOT NULL, contenu VARCHAR(255) DEFAULT NULL, type_post VARCHAR(30) NOT NULL, date_creation DATE NOT NULL, nb_vues INT NOT NULL, statut VARCHAR(20) NOT NULL, nb_likes INT NOT NULL, image VARCHAR(255) DEFAULT NULL, auteur_id INT NOT NULL, rubrique_id INT NOT NULL, INDEX IDX_588FFE0A60BB6FE6 (auteur_id), INDEX IDX_588FFE0A3BD38833 (rubrique_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE teamcraft_rubrique (id INT AUTO_INCREMENT NOT NULL, nom_rubrique VARCHAR(30) NOT NULL, description VARCHAR(255) DEFAULT NULL, topic VARCHAR(255) DEFAULT NULL, date_creation DATE NOT NULL, etat VARCHAR(20) NOT NULL, nb_posts INT NOT NULL, auteur_id INT NOT NULL, INDEX IDX_92C47E0960BB6FE6 (auteur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
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
        $this->addSql('ALTER TABLE teamcraft_comments DROP FOREIGN KEY FK_42FEE15F60BB6FE6');
        $this->addSql('ALTER TABLE teamcraft_comments DROP FOREIGN KEY FK_42FEE15F4B89032C');
        $this->addSql('ALTER TABLE teamcraft_comments DROP FOREIGN KEY FK_42FEE15F727ACA70');
        $this->addSql('ALTER TABLE teamcraft_post DROP FOREIGN KEY FK_588FFE0A60BB6FE6');
        $this->addSql('ALTER TABLE teamcraft_post DROP FOREIGN KEY FK_588FFE0A3BD38833');
        $this->addSql('ALTER TABLE teamcraft_rubrique DROP FOREIGN KEY FK_92C47E0960BB6FE6');
        $this->addSql('DROP TABLE teamcraft_comments');
        $this->addSql('DROP TABLE teamcraft_post');
        $this->addSql('DROP TABLE teamcraft_rubrique');
    }
}
