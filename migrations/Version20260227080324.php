<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260227080324 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_880E0D76A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE application (id INT AUTO_INCREMENT NOT NULL, message LONGTEXT DEFAULT NULL, status VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, offer_id INT NOT NULL, INDEX IDX_A45BDDC1A76ED395 (user_id), INDEX IDX_A45BDDC153C674EE (offer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE competitive_rank (id INT AUTO_INCREMENT NOT NULL, game VARCHAR(50) NOT NULL, principal_role VARCHAR(100) NOT NULL, skill_level VARCHAR(100) NOT NULL, experience VARCHAR(100) NOT NULL, availability VARCHAR(100) NOT NULL, region VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, player_id INT NOT NULL, INDEX IDX_1EBB5F2999E6F5DF (player_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE evenement (id_evenement INT AUTO_INCREMENT NOT NULL, nom_evenement VARCHAR(255) NOT NULL, type_evenement VARCHAR(255) NOT NULL, date_debut DATETIME NOT NULL, date_fin DATETIME NOT NULL, status VARCHAR(255) NOT NULL, average_rating DOUBLE PRECISION DEFAULT 0 NOT NULL, review_count INT DEFAULT 0 NOT NULL, image_evenement VARCHAR(255) DEFAULT NULL, place_id INT NOT NULL, organisateur_id INT NOT NULL, INDEX IDX_B26681EDA6A219 (place_id), INDEX IDX_B26681ED936B2FA (organisateur_id), PRIMARY KEY (id_evenement)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE event_review (id INT AUTO_INCREMENT NOT NULL, rating NUMERIC(3, 1) NOT NULL, message LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, evenement_id INT NOT NULL, INDEX IDX_4BDAF694A76ED395 (user_id), INDEX IDX_4BDAF694FD02F13 (evenement_id), UNIQUE INDEX UNIQ_USER_EVENT_REVIEW (user_id, evenement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE favorite_offer (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, offer_id INT NOT NULL, INDEX IDX_9F5EAC1AA76ED395 (user_id), INDEX IDX_9F5EAC1A53C674EE (offer_id), UNIQUE INDEX user_offer_unique (user_id, offer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE friend_request (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, sender_id INT NOT NULL, receiver_id INT NOT NULL, INDEX IDX_SENDER (sender_id), INDEX IDX_RECEIVER (receiver_id), INDEX IDX_STATUS (status), UNIQUE INDEX UNIQ_RELATION (sender_id, receiver_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE login_history (id INT AUTO_INCREMENT NOT NULL, ip_adress VARCHAR(45) NOT NULL, user_agent LONGTEXT NOT NULL, city VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, risk_score INT DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_37976E36A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE manager (id INT AUTO_INCREMENT NOT NULL, organization_name VARCHAR(100) DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_FA2425B9A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, is_read TINYINT NOT NULL, attachment VARCHAR(255) DEFAULT NULL, attachment_type VARCHAR(50) DEFAULT NULL, sender_id INT NOT NULL, receiver_id INT NOT NULL, INDEX IDX_B6BD307FF624B39D (sender_id), INDEX IDX_B6BD307FCD53EDB6 (receiver_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, message LONGTEXT NOT NULL, created_at DATETIME NOT NULL, is_read TINYINT NOT NULL, user_id INT NOT NULL, INDEX IDX_BF5476CAA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE offer (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, date_creation DATE NOT NULL, date_expiration DATE NOT NULL, description LONGTEXT DEFAULT NULL, poster VARCHAR(255) DEFAULT NULL, game VARCHAR(255) NOT NULL, role VARCHAR(255) NOT NULL, rank VARCHAR(255) NOT NULL, nb_player_recruited INT NOT NULL, views INT DEFAULT 0 NOT NULL, status VARCHAR(20) DEFAULT \'DRAFT\' NOT NULL, activated_at DATETIME DEFAULT NULL, offer_type VARCHAR(20) DEFAULT \'FREE\' NOT NULL, visibility_score INT DEFAULT 0 NOT NULL, premium_expires_at DATETIME DEFAULT NULL, team_id INT NOT NULL, INDEX IDX_29D6873E296CD8AE (team_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE participation (id_participation INT AUTO_INCREMENT NOT NULL, date_inscription DATETIME DEFAULT NULL, evenement_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_AB55E24FFD02F13 (evenement_id), INDEX IDX_AB55E24FA76ED395 (user_id), PRIMARY KEY (id_participation)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE place (id_place INT AUTO_INCREMENT NOT NULL, nom_place VARCHAR(255) NOT NULL, type_place VARCHAR(255) NOT NULL, adresse VARCHAR(255) NOT NULL, capacite_max INT NOT NULL, PRIMARY KEY (id_place)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE player (id INT AUTO_INCREMENT NOT NULL, game VARCHAR(100) DEFAULT NULL, game_rank VARCHAR(100) DEFAULT NULL, role VARCHAR(100) DEFAULT NULL, region VARCHAR(100) DEFAULT NULL, status VARCHAR(50) DEFAULT NULL, selected_games JSON DEFAULT NULL, availability VARCHAR(100) DEFAULT NULL, experience_years INT DEFAULT NULL, winrate DOUBLE PRECISION DEFAULT NULL, kd DOUBLE PRECISION DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_98197A65A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE postulation (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(50) NOT NULL, message LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, match_score DOUBLE PRECISION DEFAULT NULL, user_id INT NOT NULL, offer_id INT NOT NULL, INDEX IDX_DA7D4E9BA76ED395 (user_id), INDEX IDX_DA7D4E9B53C674EE (offer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE reset_password_request (id INT AUTO_INCREMENT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_7CE748AA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE riot_stats (id INT AUTO_INCREMENT NOT NULL, puuid VARCHAR(78) NOT NULL, game_name VARCHAR(255) NOT NULL, tag_line VARCHAR(255) NOT NULL, region VARCHAR(10) NOT NULL, game VARCHAR(20) NOT NULL, rank VARCHAR(50) DEFAULT NULL, tier VARCHAR(20) DEFAULT NULL, division VARCHAR(5) DEFAULT NULL, league_points INT DEFAULT NULL, wins INT DEFAULT NULL, losses INT DEFAULT NULL, last_updated DATETIME NOT NULL, recent_matches JSON DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_9CA76696CFCB9868 (puuid), INDEX IDX_9CA76696A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE steam_stats (id INT AUTO_INCREMENT NOT NULL, steam_id VARCHAR(255) NOT NULL, total_matches INT DEFAULT NULL, total_playtime INT DEFAULT NULL, wins INT DEFAULT NULL, losses INT DEFAULT NULL, current_rank VARCHAR(100) DEFAULT NULL, kills INT DEFAULT NULL, deaths INT DEFAULT NULL, assists INT DEFAULT NULL, headshots INT DEFAULT NULL, last_updated DATETIME NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_7F8CC382F3FD4ECA (steam_id), INDEX IDX_7F8CC382A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE team (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, logo VARCHAR(255) DEFAULT NULL, games JSON NOT NULL, created_at DATETIME NOT NULL, owner_id INT NOT NULL, INDEX IDX_C4E0A61F7E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE team_members (team_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_BAD9A3C8296CD8AE (team_id), INDEX IDX_BAD9A3C8A76ED395 (user_id), PRIMARY KEY (team_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE team_co_owners (team_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_1B04AF38296CD8AE (team_id), INDEX IDX_1B04AF38A76ED395 (user_id), PRIMARY KEY (team_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE team_message (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, image VARCHAR(255) DEFAULT NULL, sender_id INT NOT NULL, team_id INT NOT NULL, INDEX IDX_49C44148F624B39D (sender_id), INDEX IDX_49C44148296CD8AE (team_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE teamcraft_comments (id INT AUTO_INCREMENT NOT NULL, contenu VARCHAR(255) DEFAULT NULL, date_commentaire DATETIME NOT NULL, nb_likes INT NOT NULL, image VARCHAR(255) DEFAULT NULL, moderation_status VARCHAR(20) DEFAULT NULL, image_sensitivity VARCHAR(20) DEFAULT NULL, auteur_id INT NOT NULL, post_id INT NOT NULL, parent_id INT DEFAULT NULL, INDEX IDX_42FEE15F60BB6FE6 (auteur_id), INDEX IDX_42FEE15F4B89032C (post_id), INDEX IDX_42FEE15F727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE comment_likes (comment_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_E050D68CF8697D13 (comment_id), INDEX IDX_E050D68CA76ED395 (user_id), PRIMARY KEY (comment_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE teamcraft_media_comment (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, media_submission_id INT NOT NULL, INDEX IDX_85C28483A76ED395 (user_id), INDEX IDX_85C2848359A7792 (media_submission_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE teamcraft_media_like (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, media_submission_id INT NOT NULL, INDEX IDX_F30DEB2CA76ED395 (user_id), INDEX IDX_F30DEB2C59A7792 (media_submission_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE teamcraft_media_submission (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(10) NOT NULL, title VARCHAR(255) DEFAULT NULL, url VARCHAR(255) NOT NULL, thumbnail_url VARCHAR(255) DEFAULT NULL, views_count INT NOT NULL, likes_count INT NOT NULL, created_at DATETIME NOT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, visibility VARCHAR(20) NOT NULL, width INT DEFAULT NULL, height INT DEFAULT NULL, duration INT DEFAULT NULL, size INT DEFAULT NULL, updated_at DATETIME DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_8CDBB95FA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE teamcraft_music_track (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, artist VARCHAR(255) DEFAULT NULL, filename VARCHAR(255) NOT NULL, original_filename VARCHAR(255) NOT NULL, mime_type VARCHAR(50) NOT NULL, file_size INT NOT NULL, duration INT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, status VARCHAR(20) NOT NULL, user_id INT NOT NULL, INDEX IDX_CE9CA7A1A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE teamcraft_post (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(50) NOT NULL, contenu LONGTEXT DEFAULT NULL, type_post VARCHAR(30) NOT NULL, date_creation DATETIME NOT NULL, nb_vues INT NOT NULL, statut VARCHAR(20) NOT NULL, nb_likes INT NOT NULL, image VARCHAR(255) DEFAULT NULL, image_sensitivity VARCHAR(20) DEFAULT NULL, auteur_id INT NOT NULL, rubrique_id INT NOT NULL, INDEX IDX_588FFE0A60BB6FE6 (auteur_id), INDEX IDX_588FFE0A3BD38833 (rubrique_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE post_likes (post_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_DED1C2924B89032C (post_id), INDEX IDX_DED1C292A76ED395 (user_id), PRIMARY KEY (post_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE teamcraft_rubrique (id INT AUTO_INCREMENT NOT NULL, nom_rubrique VARCHAR(50) NOT NULL, description LONGTEXT DEFAULT NULL, topic VARCHAR(50) DEFAULT NULL, date_creation DATE NOT NULL, etat VARCHAR(20) NOT NULL, nb_posts INT NOT NULL, ai_summary LONGTEXT DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, auteur_id INT NOT NULL, INDEX IDX_92C47E0960BB6FE6 (auteur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE teamcraft_signalement (id INT AUTO_INCREMENT NOT NULL, motif VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, date_signalement DATETIME NOT NULL, status VARCHAR(50) NOT NULL, post_id INT NOT NULL, reporter_id INT NOT NULL, INDEX IDX_8B4375DB4B89032C (post_id), INDEX IDX_8B4375DBE1CFE6F5 (reporter_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, pseudo VARCHAR(100) DEFAULT NULL, username VARCHAR(100) NOT NULL, user_type VARCHAR(50) NOT NULL, name VARCHAR(100) NOT NULL, sexe VARCHAR(10) DEFAULT NULL, is_active TINYINT NOT NULL, bio LONGTEXT DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, profile_picture VARCHAR(255) DEFAULT NULL, youtube VARCHAR(255) DEFAULT NULL, twitch VARCHAR(255) DEFAULT NULL, kick VARCHAR(255) DEFAULT NULL, twitter VARCHAR(255) DEFAULT NULL, discord VARCHAR(255) DEFAULT NULL, steam_id VARCHAR(20) DEFAULT NULL, security_code VARCHAR(255) DEFAULT NULL, is_banned TINYINT DEFAULT 0 NOT NULL, is_music_enabled TINYINT DEFAULT 0 NOT NULL, last_activity_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649F3FD4ECA (steam_id), UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE admin ADD CONSTRAINT FK_880E0D76A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE application ADD CONSTRAINT FK_A45BDDC1A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE application ADD CONSTRAINT FK_A45BDDC153C674EE FOREIGN KEY (offer_id) REFERENCES offer (id)');
        $this->addSql('ALTER TABLE competitive_rank ADD CONSTRAINT FK_1EBB5F2999E6F5DF FOREIGN KEY (player_id) REFERENCES player (id)');
        $this->addSql('ALTER TABLE evenement ADD CONSTRAINT FK_B26681EDA6A219 FOREIGN KEY (place_id) REFERENCES place (id_place)');
        $this->addSql('ALTER TABLE evenement ADD CONSTRAINT FK_B26681ED936B2FA FOREIGN KEY (organisateur_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE event_review ADD CONSTRAINT FK_4BDAF694A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_review ADD CONSTRAINT FK_4BDAF694FD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id_evenement) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favorite_offer ADD CONSTRAINT FK_9F5EAC1AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favorite_offer ADD CONSTRAINT FK_9F5EAC1A53C674EE FOREIGN KEY (offer_id) REFERENCES offer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE friend_request ADD CONSTRAINT FK_F284D94F624B39D FOREIGN KEY (sender_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE friend_request ADD CONSTRAINT FK_F284D94CD53EDB6 FOREIGN KEY (receiver_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE login_history ADD CONSTRAINT FK_37976E36A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE manager ADD CONSTRAINT FK_FA2425B9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF624B39D FOREIGN KEY (sender_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FCD53EDB6 FOREIGN KEY (receiver_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE offer ADD CONSTRAINT FK_29D6873E296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24FFD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id_evenement)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE player ADD CONSTRAINT FK_98197A65A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE postulation ADD CONSTRAINT FK_DA7D4E9BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE postulation ADD CONSTRAINT FK_DA7D4E9B53C674EE FOREIGN KEY (offer_id) REFERENCES offer (id)');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE riot_stats ADD CONSTRAINT FK_9CA76696A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE steam_stats ADD CONSTRAINT FK_7F8CC382A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE team ADD CONSTRAINT FK_C4E0A61F7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE team_members ADD CONSTRAINT FK_BAD9A3C8296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE team_members ADD CONSTRAINT FK_BAD9A3C8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE team_co_owners ADD CONSTRAINT FK_1B04AF38296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE team_co_owners ADD CONSTRAINT FK_1B04AF38A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE team_message ADD CONSTRAINT FK_49C44148F624B39D FOREIGN KEY (sender_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE team_message ADD CONSTRAINT FK_49C44148296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE teamcraft_comments ADD CONSTRAINT FK_42FEE15F60BB6FE6 FOREIGN KEY (auteur_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE teamcraft_comments ADD CONSTRAINT FK_42FEE15F4B89032C FOREIGN KEY (post_id) REFERENCES teamcraft_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE teamcraft_comments ADD CONSTRAINT FK_42FEE15F727ACA70 FOREIGN KEY (parent_id) REFERENCES teamcraft_comments (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment_likes ADD CONSTRAINT FK_E050D68CF8697D13 FOREIGN KEY (comment_id) REFERENCES teamcraft_comments (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment_likes ADD CONSTRAINT FK_E050D68CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE teamcraft_media_comment ADD CONSTRAINT FK_85C28483A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE teamcraft_media_comment ADD CONSTRAINT FK_85C2848359A7792 FOREIGN KEY (media_submission_id) REFERENCES teamcraft_media_submission (id)');
        $this->addSql('ALTER TABLE teamcraft_media_like ADD CONSTRAINT FK_F30DEB2CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE teamcraft_media_like ADD CONSTRAINT FK_F30DEB2C59A7792 FOREIGN KEY (media_submission_id) REFERENCES teamcraft_media_submission (id)');
        $this->addSql('ALTER TABLE teamcraft_media_submission ADD CONSTRAINT FK_8CDBB95FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE teamcraft_music_track ADD CONSTRAINT FK_CE9CA7A1A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE teamcraft_post ADD CONSTRAINT FK_588FFE0A60BB6FE6 FOREIGN KEY (auteur_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE teamcraft_post ADD CONSTRAINT FK_588FFE0A3BD38833 FOREIGN KEY (rubrique_id) REFERENCES teamcraft_rubrique (id)');
        $this->addSql('ALTER TABLE post_likes ADD CONSTRAINT FK_DED1C2924B89032C FOREIGN KEY (post_id) REFERENCES teamcraft_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_likes ADD CONSTRAINT FK_DED1C292A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE teamcraft_rubrique ADD CONSTRAINT FK_92C47E0960BB6FE6 FOREIGN KEY (auteur_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE teamcraft_signalement ADD CONSTRAINT FK_8B4375DB4B89032C FOREIGN KEY (post_id) REFERENCES teamcraft_post (id)');
        $this->addSql('ALTER TABLE teamcraft_signalement ADD CONSTRAINT FK_8B4375DBE1CFE6F5 FOREIGN KEY (reporter_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin DROP FOREIGN KEY FK_880E0D76A76ED395');
        $this->addSql('ALTER TABLE application DROP FOREIGN KEY FK_A45BDDC1A76ED395');
        $this->addSql('ALTER TABLE application DROP FOREIGN KEY FK_A45BDDC153C674EE');
        $this->addSql('ALTER TABLE competitive_rank DROP FOREIGN KEY FK_1EBB5F2999E6F5DF');
        $this->addSql('ALTER TABLE evenement DROP FOREIGN KEY FK_B26681EDA6A219');
        $this->addSql('ALTER TABLE evenement DROP FOREIGN KEY FK_B26681ED936B2FA');
        $this->addSql('ALTER TABLE event_review DROP FOREIGN KEY FK_4BDAF694A76ED395');
        $this->addSql('ALTER TABLE event_review DROP FOREIGN KEY FK_4BDAF694FD02F13');
        $this->addSql('ALTER TABLE favorite_offer DROP FOREIGN KEY FK_9F5EAC1AA76ED395');
        $this->addSql('ALTER TABLE favorite_offer DROP FOREIGN KEY FK_9F5EAC1A53C674EE');
        $this->addSql('ALTER TABLE friend_request DROP FOREIGN KEY FK_F284D94F624B39D');
        $this->addSql('ALTER TABLE friend_request DROP FOREIGN KEY FK_F284D94CD53EDB6');
        $this->addSql('ALTER TABLE login_history DROP FOREIGN KEY FK_37976E36A76ED395');
        $this->addSql('ALTER TABLE manager DROP FOREIGN KEY FK_FA2425B9A76ED395');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FF624B39D');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FCD53EDB6');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE offer DROP FOREIGN KEY FK_29D6873E296CD8AE');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FFD02F13');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FA76ED395');
        $this->addSql('ALTER TABLE player DROP FOREIGN KEY FK_98197A65A76ED395');
        $this->addSql('ALTER TABLE postulation DROP FOREIGN KEY FK_DA7D4E9BA76ED395');
        $this->addSql('ALTER TABLE postulation DROP FOREIGN KEY FK_DA7D4E9B53C674EE');
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY FK_7CE748AA76ED395');
        $this->addSql('ALTER TABLE riot_stats DROP FOREIGN KEY FK_9CA76696A76ED395');
        $this->addSql('ALTER TABLE steam_stats DROP FOREIGN KEY FK_7F8CC382A76ED395');
        $this->addSql('ALTER TABLE team DROP FOREIGN KEY FK_C4E0A61F7E3C61F9');
        $this->addSql('ALTER TABLE team_members DROP FOREIGN KEY FK_BAD9A3C8296CD8AE');
        $this->addSql('ALTER TABLE team_members DROP FOREIGN KEY FK_BAD9A3C8A76ED395');
        $this->addSql('ALTER TABLE team_co_owners DROP FOREIGN KEY FK_1B04AF38296CD8AE');
        $this->addSql('ALTER TABLE team_co_owners DROP FOREIGN KEY FK_1B04AF38A76ED395');
        $this->addSql('ALTER TABLE team_message DROP FOREIGN KEY FK_49C44148F624B39D');
        $this->addSql('ALTER TABLE team_message DROP FOREIGN KEY FK_49C44148296CD8AE');
        $this->addSql('ALTER TABLE teamcraft_comments DROP FOREIGN KEY FK_42FEE15F60BB6FE6');
        $this->addSql('ALTER TABLE teamcraft_comments DROP FOREIGN KEY FK_42FEE15F4B89032C');
        $this->addSql('ALTER TABLE teamcraft_comments DROP FOREIGN KEY FK_42FEE15F727ACA70');
        $this->addSql('ALTER TABLE comment_likes DROP FOREIGN KEY FK_E050D68CF8697D13');
        $this->addSql('ALTER TABLE comment_likes DROP FOREIGN KEY FK_E050D68CA76ED395');
        $this->addSql('ALTER TABLE teamcraft_media_comment DROP FOREIGN KEY FK_85C28483A76ED395');
        $this->addSql('ALTER TABLE teamcraft_media_comment DROP FOREIGN KEY FK_85C2848359A7792');
        $this->addSql('ALTER TABLE teamcraft_media_like DROP FOREIGN KEY FK_F30DEB2CA76ED395');
        $this->addSql('ALTER TABLE teamcraft_media_like DROP FOREIGN KEY FK_F30DEB2C59A7792');
        $this->addSql('ALTER TABLE teamcraft_media_submission DROP FOREIGN KEY FK_8CDBB95FA76ED395');
        $this->addSql('ALTER TABLE teamcraft_music_track DROP FOREIGN KEY FK_CE9CA7A1A76ED395');
        $this->addSql('ALTER TABLE teamcraft_post DROP FOREIGN KEY FK_588FFE0A60BB6FE6');
        $this->addSql('ALTER TABLE teamcraft_post DROP FOREIGN KEY FK_588FFE0A3BD38833');
        $this->addSql('ALTER TABLE post_likes DROP FOREIGN KEY FK_DED1C2924B89032C');
        $this->addSql('ALTER TABLE post_likes DROP FOREIGN KEY FK_DED1C292A76ED395');
        $this->addSql('ALTER TABLE teamcraft_rubrique DROP FOREIGN KEY FK_92C47E0960BB6FE6');
        $this->addSql('ALTER TABLE teamcraft_signalement DROP FOREIGN KEY FK_8B4375DB4B89032C');
        $this->addSql('ALTER TABLE teamcraft_signalement DROP FOREIGN KEY FK_8B4375DBE1CFE6F5');
        $this->addSql('DROP TABLE admin');
        $this->addSql('DROP TABLE application');
        $this->addSql('DROP TABLE competitive_rank');
        $this->addSql('DROP TABLE evenement');
        $this->addSql('DROP TABLE event_review');
        $this->addSql('DROP TABLE favorite_offer');
        $this->addSql('DROP TABLE friend_request');
        $this->addSql('DROP TABLE login_history');
        $this->addSql('DROP TABLE manager');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE offer');
        $this->addSql('DROP TABLE participation');
        $this->addSql('DROP TABLE place');
        $this->addSql('DROP TABLE player');
        $this->addSql('DROP TABLE postulation');
        $this->addSql('DROP TABLE reset_password_request');
        $this->addSql('DROP TABLE riot_stats');
        $this->addSql('DROP TABLE steam_stats');
        $this->addSql('DROP TABLE team');
        $this->addSql('DROP TABLE team_members');
        $this->addSql('DROP TABLE team_co_owners');
        $this->addSql('DROP TABLE team_message');
        $this->addSql('DROP TABLE teamcraft_comments');
        $this->addSql('DROP TABLE comment_likes');
        $this->addSql('DROP TABLE teamcraft_media_comment');
        $this->addSql('DROP TABLE teamcraft_media_like');
        $this->addSql('DROP TABLE teamcraft_media_submission');
        $this->addSql('DROP TABLE teamcraft_music_track');
        $this->addSql('DROP TABLE teamcraft_post');
        $this->addSql('DROP TABLE post_likes');
        $this->addSql('DROP TABLE teamcraft_rubrique');
        $this->addSql('DROP TABLE teamcraft_signalement');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
