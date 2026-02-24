<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260223213139 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE favorite_offer (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, offer_id INT NOT NULL, INDEX IDX_9F5EAC1AA76ED395 (user_id), INDEX IDX_9F5EAC1A53C674EE (offer_id), UNIQUE INDEX user_offer_unique (user_id, offer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, message LONGTEXT NOT NULL, created_at DATETIME NOT NULL, is_read TINYINT NOT NULL, user_id INT NOT NULL, INDEX IDX_BF5476CAA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE favorite_offer ADD CONSTRAINT FK_9F5EAC1AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favorite_offer ADD CONSTRAINT FK_9F5EAC1A53C674EE FOREIGN KEY (offer_id) REFERENCES offer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE comment_likes DROP FOREIGN KEY `FK_E050D68CA76ED395`');
        $this->addSql('ALTER TABLE comment_likes DROP FOREIGN KEY `FK_E050D68CF8697D13`');
        $this->addSql('ALTER TABLE event_review DROP FOREIGN KEY `FK_4BDAF694A76ED395`');
        $this->addSql('ALTER TABLE event_review DROP FOREIGN KEY `FK_4BDAF694FD02F13`');
        $this->addSql('ALTER TABLE friend_request DROP FOREIGN KEY `FK_F284D94CD53EDB6`');
        $this->addSql('ALTER TABLE friend_request DROP FOREIGN KEY `FK_F284D94F624B39D`');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY `FK_B6BD307FCD53EDB6`');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY `FK_B6BD307FF624B39D`');
        $this->addSql('ALTER TABLE post_likes DROP FOREIGN KEY `FK_DED1C2924B89032C`');
        $this->addSql('ALTER TABLE post_likes DROP FOREIGN KEY `FK_DED1C292A76ED395`');
        $this->addSql('ALTER TABLE riot_stats DROP FOREIGN KEY `FK_9CA76696A76ED395`');
        $this->addSql('ALTER TABLE steam_stats DROP FOREIGN KEY `FK_7F8CC382A76ED395`');
        $this->addSql('ALTER TABLE teamcraft_media_comment DROP FOREIGN KEY `FK_85C2848359A7792`');
        $this->addSql('ALTER TABLE teamcraft_media_comment DROP FOREIGN KEY `FK_85C28483A76ED395`');
        $this->addSql('ALTER TABLE teamcraft_media_like DROP FOREIGN KEY `FK_F30DEB2C59A7792`');
        $this->addSql('ALTER TABLE teamcraft_media_like DROP FOREIGN KEY `FK_F30DEB2CA76ED395`');
        $this->addSql('ALTER TABLE teamcraft_media_submission DROP FOREIGN KEY `FK_8CDBB95FA76ED395`');
        $this->addSql('ALTER TABLE teamcraft_signalement DROP FOREIGN KEY `FK_8B4375DB4B89032C`');
        $this->addSql('ALTER TABLE teamcraft_signalement DROP FOREIGN KEY `FK_8B4375DBE1CFE6F5`');
        $this->addSql('ALTER TABLE team_co_owners DROP FOREIGN KEY `FK_1B04AF38296CD8AE`');
        $this->addSql('ALTER TABLE team_co_owners DROP FOREIGN KEY `FK_1B04AF38A76ED395`');
        $this->addSql('ALTER TABLE team_message DROP FOREIGN KEY `FK_49C44148296CD8AE`');
        $this->addSql('ALTER TABLE team_message DROP FOREIGN KEY `FK_49C44148F624B39D`');
        $this->addSql('DROP TABLE comment_likes');
        $this->addSql('DROP TABLE event_review');
        $this->addSql('DROP TABLE friend_request');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE post_likes');
        $this->addSql('DROP TABLE riot_stats');
        $this->addSql('DROP TABLE steam_stats');
        $this->addSql('DROP TABLE teamcraft_media_comment');
        $this->addSql('DROP TABLE teamcraft_media_like');
        $this->addSql('DROP TABLE teamcraft_media_submission');
        $this->addSql('DROP TABLE teamcraft_signalement');
        $this->addSql('DROP TABLE team_co_owners');
        $this->addSql('DROP TABLE team_message');
        $this->addSql('ALTER TABLE evenement DROP average_rating, DROP review_count, DROP image_evenement');
        $this->addSql('ALTER TABLE offer ADD views INT DEFAULT 0 NOT NULL, ADD status VARCHAR(20) DEFAULT \'DRAFT\' NOT NULL, ADD activated_at DATETIME DEFAULT NULL, ADD offer_type VARCHAR(20) DEFAULT \'FREE\' NOT NULL, ADD visibility_score INT DEFAULT 0 NOT NULL, ADD premium_expires_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE player ADD experience_years INT DEFAULT NULL, ADD winrate DOUBLE PRECISION DEFAULT NULL, ADD kd DOUBLE PRECISION DEFAULT NULL, DROP availability');
        $this->addSql('ALTER TABLE postulation ADD match_score DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE teamcraft_comments DROP FOREIGN KEY `FK_42FEE15F4B89032C`');
        $this->addSql('ALTER TABLE teamcraft_comments DROP FOREIGN KEY `FK_42FEE15F727ACA70`');
        $this->addSql('ALTER TABLE teamcraft_comments DROP image, DROP moderation_status, DROP image_sensitivity, CHANGE contenu contenu VARCHAR(255) NOT NULL, CHANGE date_commentaire date_commentaire DATE NOT NULL');
        $this->addSql('ALTER TABLE teamcraft_comments ADD CONSTRAINT FK_42FEE15F4B89032C FOREIGN KEY (post_id) REFERENCES teamcraft_post (id)');
        $this->addSql('ALTER TABLE teamcraft_comments ADD CONSTRAINT FK_42FEE15F727ACA70 FOREIGN KEY (parent_id) REFERENCES teamcraft_comments (id)');
        $this->addSql('ALTER TABLE teamcraft_post DROP image_sensitivity, CHANGE titre titre VARCHAR(30) NOT NULL, CHANGE contenu contenu VARCHAR(255) DEFAULT NULL, CHANGE date_creation date_creation DATE NOT NULL');
        $this->addSql('ALTER TABLE teamcraft_rubrique DROP ai_summary, DROP image, CHANGE nom_rubrique nom_rubrique VARCHAR(30) NOT NULL, CHANGE description description VARCHAR(255) DEFAULT NULL, CHANGE topic topic VARCHAR(255) DEFAULT NULL');
        $this->addSql('DROP INDEX UNIQ_8D93D649F3FD4ECA ON user');
        $this->addSql('ALTER TABLE user ADD last_activity_at DATETIME DEFAULT NULL, DROP youtube, DROP twitch, DROP kick, DROP twitter, DROP discord, DROP steam_id, DROP is_banned, DROP is_music_enabled');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE comment_likes (comment_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_E050D68CF8697D13 (comment_id), INDEX IDX_E050D68CA76ED395 (user_id), PRIMARY KEY (comment_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE event_review (id INT AUTO_INCREMENT NOT NULL, rating NUMERIC(3, 1) NOT NULL, message LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, evenement_id INT NOT NULL, UNIQUE INDEX UNIQ_USER_EVENT_REVIEW (user_id, evenement_id), INDEX IDX_4BDAF694A76ED395 (user_id), INDEX IDX_4BDAF694FD02F13 (evenement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE friend_request (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, sender_id INT NOT NULL, receiver_id INT NOT NULL, INDEX IDX_SENDER (sender_id), INDEX IDX_RECEIVER (receiver_id), UNIQUE INDEX UNIQ_RELATION (sender_id, receiver_id), INDEX IDX_STATUS (status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, is_read TINYINT NOT NULL, attachment VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, attachment_type VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, sender_id INT NOT NULL, receiver_id INT NOT NULL, INDEX IDX_B6BD307FF624B39D (sender_id), INDEX IDX_B6BD307FCD53EDB6 (receiver_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE post_likes (post_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_DED1C2924B89032C (post_id), INDEX IDX_DED1C292A76ED395 (user_id), PRIMARY KEY (post_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE riot_stats (id INT AUTO_INCREMENT NOT NULL, puuid VARCHAR(78) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, game_name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, tag_line VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, region VARCHAR(10) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, game VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, rank VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, tier VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, division VARCHAR(5) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, league_points INT DEFAULT NULL, wins INT DEFAULT NULL, losses INT DEFAULT NULL, last_updated DATETIME NOT NULL, recent_matches JSON DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_9CA76696CFCB9868 (puuid), INDEX IDX_9CA76696A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE steam_stats (id INT AUTO_INCREMENT NOT NULL, steam_id VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, total_matches INT DEFAULT NULL, total_playtime INT DEFAULT NULL, wins INT DEFAULT NULL, losses INT DEFAULT NULL, current_rank VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, kills INT DEFAULT NULL, deaths INT DEFAULT NULL, assists INT DEFAULT NULL, headshots INT DEFAULT NULL, last_updated DATETIME NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_7F8CC382F3FD4ECA (steam_id), INDEX IDX_7F8CC382A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE teamcraft_media_comment (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, user_id INT NOT NULL, media_submission_id INT NOT NULL, INDEX IDX_85C28483A76ED395 (user_id), INDEX IDX_85C2848359A7792 (media_submission_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE teamcraft_media_like (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, media_submission_id INT NOT NULL, INDEX IDX_F30DEB2C59A7792 (media_submission_id), INDEX IDX_F30DEB2CA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE teamcraft_media_submission (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(10) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, title VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, url VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, thumbnail_url VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, views_count INT NOT NULL, likes_count INT NOT NULL, created_at DATETIME NOT NULL, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, status VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, visibility VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, width INT DEFAULT NULL, height INT DEFAULT NULL, duration INT DEFAULT NULL, size INT DEFAULT NULL, updated_at DATETIME DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_8CDBB95FA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE teamcraft_signalement (id INT AUTO_INCREMENT NOT NULL, motif VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, date_signalement DATETIME NOT NULL, status VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, post_id INT NOT NULL, reporter_id INT NOT NULL, INDEX IDX_8B4375DB4B89032C (post_id), INDEX IDX_8B4375DBE1CFE6F5 (reporter_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE team_co_owners (team_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_1B04AF38296CD8AE (team_id), INDEX IDX_1B04AF38A76ED395 (user_id), PRIMARY KEY (team_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE team_message (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, image VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, sender_id INT NOT NULL, team_id INT NOT NULL, INDEX IDX_49C44148F624B39D (sender_id), INDEX IDX_49C44148296CD8AE (team_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE comment_likes ADD CONSTRAINT `FK_E050D68CA76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment_likes ADD CONSTRAINT `FK_E050D68CF8697D13` FOREIGN KEY (comment_id) REFERENCES teamcraft_comments (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_review ADD CONSTRAINT `FK_4BDAF694A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_review ADD CONSTRAINT `FK_4BDAF694FD02F13` FOREIGN KEY (evenement_id) REFERENCES evenement (id_evenement) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE friend_request ADD CONSTRAINT `FK_F284D94CD53EDB6` FOREIGN KEY (receiver_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE friend_request ADD CONSTRAINT `FK_F284D94F624B39D` FOREIGN KEY (sender_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT `FK_B6BD307FCD53EDB6` FOREIGN KEY (receiver_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT `FK_B6BD307FF624B39D` FOREIGN KEY (sender_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE post_likes ADD CONSTRAINT `FK_DED1C2924B89032C` FOREIGN KEY (post_id) REFERENCES teamcraft_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_likes ADD CONSTRAINT `FK_DED1C292A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE riot_stats ADD CONSTRAINT `FK_9CA76696A76ED395` FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE steam_stats ADD CONSTRAINT `FK_7F8CC382A76ED395` FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE teamcraft_media_comment ADD CONSTRAINT `FK_85C2848359A7792` FOREIGN KEY (media_submission_id) REFERENCES teamcraft_media_submission (id)');
        $this->addSql('ALTER TABLE teamcraft_media_comment ADD CONSTRAINT `FK_85C28483A76ED395` FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE teamcraft_media_like ADD CONSTRAINT `FK_F30DEB2C59A7792` FOREIGN KEY (media_submission_id) REFERENCES teamcraft_media_submission (id)');
        $this->addSql('ALTER TABLE teamcraft_media_like ADD CONSTRAINT `FK_F30DEB2CA76ED395` FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE teamcraft_media_submission ADD CONSTRAINT `FK_8CDBB95FA76ED395` FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE teamcraft_signalement ADD CONSTRAINT `FK_8B4375DB4B89032C` FOREIGN KEY (post_id) REFERENCES teamcraft_post (id)');
        $this->addSql('ALTER TABLE teamcraft_signalement ADD CONSTRAINT `FK_8B4375DBE1CFE6F5` FOREIGN KEY (reporter_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE team_co_owners ADD CONSTRAINT `FK_1B04AF38296CD8AE` FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE team_co_owners ADD CONSTRAINT `FK_1B04AF38A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE team_message ADD CONSTRAINT `FK_49C44148296CD8AE` FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE team_message ADD CONSTRAINT `FK_49C44148F624B39D` FOREIGN KEY (sender_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE favorite_offer DROP FOREIGN KEY FK_9F5EAC1AA76ED395');
        $this->addSql('ALTER TABLE favorite_offer DROP FOREIGN KEY FK_9F5EAC1A53C674EE');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('DROP TABLE favorite_offer');
        $this->addSql('DROP TABLE notification');
        $this->addSql('ALTER TABLE evenement ADD average_rating DOUBLE PRECISION DEFAULT \'0\' NOT NULL, ADD review_count INT DEFAULT 0 NOT NULL, ADD image_evenement VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE offer DROP views, DROP status, DROP activated_at, DROP offer_type, DROP visibility_score, DROP premium_expires_at');
        $this->addSql('ALTER TABLE player ADD availability VARCHAR(100) DEFAULT NULL, DROP experience_years, DROP winrate, DROP kd');
        $this->addSql('ALTER TABLE postulation DROP match_score');
        $this->addSql('ALTER TABLE teamcraft_comments DROP FOREIGN KEY FK_42FEE15F4B89032C');
        $this->addSql('ALTER TABLE teamcraft_comments DROP FOREIGN KEY FK_42FEE15F727ACA70');
        $this->addSql('ALTER TABLE teamcraft_comments ADD image VARCHAR(255) DEFAULT NULL, ADD moderation_status VARCHAR(20) DEFAULT NULL, ADD image_sensitivity VARCHAR(20) DEFAULT NULL, CHANGE contenu contenu VARCHAR(255) DEFAULT NULL, CHANGE date_commentaire date_commentaire DATETIME NOT NULL');
        $this->addSql('ALTER TABLE teamcraft_comments ADD CONSTRAINT `FK_42FEE15F4B89032C` FOREIGN KEY (post_id) REFERENCES teamcraft_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE teamcraft_comments ADD CONSTRAINT `FK_42FEE15F727ACA70` FOREIGN KEY (parent_id) REFERENCES teamcraft_comments (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE teamcraft_post ADD image_sensitivity VARCHAR(20) DEFAULT NULL, CHANGE titre titre VARCHAR(50) NOT NULL, CHANGE contenu contenu LONGTEXT DEFAULT NULL, CHANGE date_creation date_creation DATETIME NOT NULL');
        $this->addSql('ALTER TABLE teamcraft_rubrique ADD ai_summary LONGTEXT DEFAULT NULL, ADD image VARCHAR(255) DEFAULT NULL, CHANGE nom_rubrique nom_rubrique VARCHAR(50) NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE topic topic VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD youtube VARCHAR(255) DEFAULT NULL, ADD twitch VARCHAR(255) DEFAULT NULL, ADD kick VARCHAR(255) DEFAULT NULL, ADD twitter VARCHAR(255) DEFAULT NULL, ADD discord VARCHAR(255) DEFAULT NULL, ADD steam_id VARCHAR(20) DEFAULT NULL, ADD is_banned TINYINT DEFAULT 0 NOT NULL, ADD is_music_enabled TINYINT DEFAULT 0 NOT NULL, DROP last_activity_at');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649F3FD4ECA ON user (steam_id)');
    }
}
