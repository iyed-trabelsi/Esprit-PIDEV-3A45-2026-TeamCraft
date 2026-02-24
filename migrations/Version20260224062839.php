<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260224062839 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE friend_request (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, sender_id INT NOT NULL, receiver_id INT NOT NULL, INDEX IDX_SENDER (sender_id), INDEX IDX_RECEIVER (receiver_id), INDEX IDX_STATUS (status), UNIQUE INDEX UNIQ_RELATION (sender_id, receiver_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, is_read TINYINT NOT NULL, attachment VARCHAR(255) DEFAULT NULL, attachment_type VARCHAR(50) DEFAULT NULL, sender_id INT NOT NULL, receiver_id INT NOT NULL, INDEX IDX_B6BD307FF624B39D (sender_id), INDEX IDX_B6BD307FCD53EDB6 (receiver_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE riot_stats (id INT AUTO_INCREMENT NOT NULL, puuid VARCHAR(78) NOT NULL, game_name VARCHAR(255) NOT NULL, tag_line VARCHAR(255) NOT NULL, region VARCHAR(10) NOT NULL, game VARCHAR(20) NOT NULL, rank VARCHAR(50) DEFAULT NULL, tier VARCHAR(20) DEFAULT NULL, division VARCHAR(5) DEFAULT NULL, league_points INT DEFAULT NULL, wins INT DEFAULT NULL, losses INT DEFAULT NULL, last_updated DATETIME NOT NULL, recent_matches JSON DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_9CA76696CFCB9868 (puuid), INDEX IDX_9CA76696A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE steam_stats (id INT AUTO_INCREMENT NOT NULL, steam_id VARCHAR(255) NOT NULL, total_matches INT DEFAULT NULL, total_playtime INT DEFAULT NULL, wins INT DEFAULT NULL, losses INT DEFAULT NULL, current_rank VARCHAR(100) DEFAULT NULL, kills INT DEFAULT NULL, deaths INT DEFAULT NULL, assists INT DEFAULT NULL, headshots INT DEFAULT NULL, last_updated DATETIME NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_7F8CC382F3FD4ECA (steam_id), INDEX IDX_7F8CC382A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE team_co_owners (team_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_1B04AF38296CD8AE (team_id), INDEX IDX_1B04AF38A76ED395 (user_id), PRIMARY KEY (team_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE team_message (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, image VARCHAR(255) DEFAULT NULL, sender_id INT NOT NULL, team_id INT NOT NULL, INDEX IDX_49C44148F624B39D (sender_id), INDEX IDX_49C44148296CD8AE (team_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE comment_likes (comment_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_E050D68CF8697D13 (comment_id), INDEX IDX_E050D68CA76ED395 (user_id), PRIMARY KEY (comment_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE teamcraft_media_comment (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, media_submission_id INT NOT NULL, INDEX IDX_85C28483A76ED395 (user_id), INDEX IDX_85C2848359A7792 (media_submission_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE teamcraft_media_like (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, media_submission_id INT NOT NULL, INDEX IDX_F30DEB2CA76ED395 (user_id), INDEX IDX_F30DEB2C59A7792 (media_submission_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE teamcraft_media_submission (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(10) NOT NULL, title VARCHAR(255) DEFAULT NULL, url VARCHAR(255) NOT NULL, thumbnail_url VARCHAR(255) DEFAULT NULL, views_count INT NOT NULL, likes_count INT NOT NULL, created_at DATETIME NOT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, visibility VARCHAR(20) NOT NULL, width INT DEFAULT NULL, height INT DEFAULT NULL, duration INT DEFAULT NULL, size INT DEFAULT NULL, updated_at DATETIME DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_8CDBB95FA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE post_likes (post_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_DED1C2924B89032C (post_id), INDEX IDX_DED1C292A76ED395 (user_id), PRIMARY KEY (post_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE teamcraft_signalement (id INT AUTO_INCREMENT NOT NULL, motif VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, date_signalement DATETIME NOT NULL, status VARCHAR(50) NOT NULL, post_id INT NOT NULL, reporter_id INT NOT NULL, INDEX IDX_8B4375DB4B89032C (post_id), INDEX IDX_8B4375DBE1CFE6F5 (reporter_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE friend_request ADD CONSTRAINT FK_F284D94F624B39D FOREIGN KEY (sender_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE friend_request ADD CONSTRAINT FK_F284D94CD53EDB6 FOREIGN KEY (receiver_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF624B39D FOREIGN KEY (sender_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FCD53EDB6 FOREIGN KEY (receiver_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE riot_stats ADD CONSTRAINT FK_9CA76696A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE steam_stats ADD CONSTRAINT FK_7F8CC382A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE team_co_owners ADD CONSTRAINT FK_1B04AF38296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE team_co_owners ADD CONSTRAINT FK_1B04AF38A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE team_message ADD CONSTRAINT FK_49C44148F624B39D FOREIGN KEY (sender_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE team_message ADD CONSTRAINT FK_49C44148296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE comment_likes ADD CONSTRAINT FK_E050D68CF8697D13 FOREIGN KEY (comment_id) REFERENCES teamcraft_comments (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment_likes ADD CONSTRAINT FK_E050D68CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE teamcraft_media_comment ADD CONSTRAINT FK_85C28483A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE teamcraft_media_comment ADD CONSTRAINT FK_85C2848359A7792 FOREIGN KEY (media_submission_id) REFERENCES teamcraft_media_submission (id)');
        $this->addSql('ALTER TABLE teamcraft_media_like ADD CONSTRAINT FK_F30DEB2CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE teamcraft_media_like ADD CONSTRAINT FK_F30DEB2C59A7792 FOREIGN KEY (media_submission_id) REFERENCES teamcraft_media_submission (id)');
        $this->addSql('ALTER TABLE teamcraft_media_submission ADD CONSTRAINT FK_8CDBB95FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE post_likes ADD CONSTRAINT FK_DED1C2924B89032C FOREIGN KEY (post_id) REFERENCES teamcraft_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_likes ADD CONSTRAINT FK_DED1C292A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE teamcraft_signalement ADD CONSTRAINT FK_8B4375DB4B89032C FOREIGN KEY (post_id) REFERENCES teamcraft_post (id)');
        $this->addSql('ALTER TABLE teamcraft_signalement ADD CONSTRAINT FK_8B4375DBE1CFE6F5 FOREIGN KEY (reporter_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE evenement CHANGE average_rating average_rating DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE offer ADD views INT DEFAULT 0 NOT NULL, ADD status VARCHAR(20) DEFAULT \'DRAFT\' NOT NULL, ADD activated_at DATETIME DEFAULT NULL, ADD offer_type VARCHAR(20) DEFAULT \'FREE\' NOT NULL, ADD visibility_score INT DEFAULT 0 NOT NULL, ADD premium_expires_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE player ADD experience_years INT DEFAULT NULL, ADD winrate DOUBLE PRECISION DEFAULT NULL, ADD kd DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE postulation ADD match_score DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE teamcraft_comments DROP FOREIGN KEY `FK_42FEE15F4B89032C`');
        $this->addSql('ALTER TABLE teamcraft_comments DROP FOREIGN KEY `FK_42FEE15F727ACA70`');
        $this->addSql('ALTER TABLE teamcraft_comments ADD image VARCHAR(255) DEFAULT NULL, ADD moderation_status VARCHAR(20) DEFAULT NULL, ADD image_sensitivity VARCHAR(20) DEFAULT NULL, CHANGE contenu contenu VARCHAR(255) DEFAULT NULL, CHANGE date_commentaire date_commentaire DATETIME NOT NULL');
        $this->addSql('ALTER TABLE teamcraft_comments ADD CONSTRAINT FK_42FEE15F4B89032C FOREIGN KEY (post_id) REFERENCES teamcraft_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE teamcraft_comments ADD CONSTRAINT FK_42FEE15F727ACA70 FOREIGN KEY (parent_id) REFERENCES teamcraft_comments (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE teamcraft_post ADD image_sensitivity VARCHAR(20) DEFAULT NULL, CHANGE titre titre VARCHAR(50) NOT NULL, CHANGE contenu contenu LONGTEXT DEFAULT NULL, CHANGE date_creation date_creation DATETIME NOT NULL');
        $this->addSql('ALTER TABLE teamcraft_rubrique ADD ai_summary LONGTEXT DEFAULT NULL, ADD image VARCHAR(255) DEFAULT NULL, CHANGE nom_rubrique nom_rubrique VARCHAR(50) NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE topic topic VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD is_banned TINYINT DEFAULT 0 NOT NULL, ADD last_activity_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE friend_request DROP FOREIGN KEY FK_F284D94F624B39D');
        $this->addSql('ALTER TABLE friend_request DROP FOREIGN KEY FK_F284D94CD53EDB6');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FF624B39D');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FCD53EDB6');
        $this->addSql('ALTER TABLE riot_stats DROP FOREIGN KEY FK_9CA76696A76ED395');
        $this->addSql('ALTER TABLE steam_stats DROP FOREIGN KEY FK_7F8CC382A76ED395');
        $this->addSql('ALTER TABLE team_co_owners DROP FOREIGN KEY FK_1B04AF38296CD8AE');
        $this->addSql('ALTER TABLE team_co_owners DROP FOREIGN KEY FK_1B04AF38A76ED395');
        $this->addSql('ALTER TABLE team_message DROP FOREIGN KEY FK_49C44148F624B39D');
        $this->addSql('ALTER TABLE team_message DROP FOREIGN KEY FK_49C44148296CD8AE');
        $this->addSql('ALTER TABLE comment_likes DROP FOREIGN KEY FK_E050D68CF8697D13');
        $this->addSql('ALTER TABLE comment_likes DROP FOREIGN KEY FK_E050D68CA76ED395');
        $this->addSql('ALTER TABLE teamcraft_media_comment DROP FOREIGN KEY FK_85C28483A76ED395');
        $this->addSql('ALTER TABLE teamcraft_media_comment DROP FOREIGN KEY FK_85C2848359A7792');
        $this->addSql('ALTER TABLE teamcraft_media_like DROP FOREIGN KEY FK_F30DEB2CA76ED395');
        $this->addSql('ALTER TABLE teamcraft_media_like DROP FOREIGN KEY FK_F30DEB2C59A7792');
        $this->addSql('ALTER TABLE teamcraft_media_submission DROP FOREIGN KEY FK_8CDBB95FA76ED395');
        $this->addSql('ALTER TABLE post_likes DROP FOREIGN KEY FK_DED1C2924B89032C');
        $this->addSql('ALTER TABLE post_likes DROP FOREIGN KEY FK_DED1C292A76ED395');
        $this->addSql('ALTER TABLE teamcraft_signalement DROP FOREIGN KEY FK_8B4375DB4B89032C');
        $this->addSql('ALTER TABLE teamcraft_signalement DROP FOREIGN KEY FK_8B4375DBE1CFE6F5');
        $this->addSql('DROP TABLE friend_request');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE riot_stats');
        $this->addSql('DROP TABLE steam_stats');
        $this->addSql('DROP TABLE team_co_owners');
        $this->addSql('DROP TABLE team_message');
        $this->addSql('DROP TABLE comment_likes');
        $this->addSql('DROP TABLE teamcraft_media_comment');
        $this->addSql('DROP TABLE teamcraft_media_like');
        $this->addSql('DROP TABLE teamcraft_media_submission');
        $this->addSql('DROP TABLE post_likes');
        $this->addSql('DROP TABLE teamcraft_signalement');
        $this->addSql('ALTER TABLE evenement CHANGE average_rating average_rating DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE offer DROP views, DROP status, DROP activated_at, DROP offer_type, DROP visibility_score, DROP premium_expires_at');
        $this->addSql('ALTER TABLE player DROP experience_years, DROP winrate, DROP kd');
        $this->addSql('ALTER TABLE postulation DROP match_score');
        $this->addSql('ALTER TABLE teamcraft_comments DROP FOREIGN KEY FK_42FEE15F4B89032C');
        $this->addSql('ALTER TABLE teamcraft_comments DROP FOREIGN KEY FK_42FEE15F727ACA70');
        $this->addSql('ALTER TABLE teamcraft_comments DROP image, DROP moderation_status, DROP image_sensitivity, CHANGE contenu contenu VARCHAR(255) NOT NULL, CHANGE date_commentaire date_commentaire DATE NOT NULL');
        $this->addSql('ALTER TABLE teamcraft_comments ADD CONSTRAINT `FK_42FEE15F4B89032C` FOREIGN KEY (post_id) REFERENCES teamcraft_post (id)');
        $this->addSql('ALTER TABLE teamcraft_comments ADD CONSTRAINT `FK_42FEE15F727ACA70` FOREIGN KEY (parent_id) REFERENCES teamcraft_comments (id)');
        $this->addSql('ALTER TABLE teamcraft_post DROP image_sensitivity, CHANGE titre titre VARCHAR(30) NOT NULL, CHANGE contenu contenu VARCHAR(255) DEFAULT NULL, CHANGE date_creation date_creation DATE NOT NULL');
        $this->addSql('ALTER TABLE teamcraft_rubrique DROP ai_summary, DROP image, CHANGE nom_rubrique nom_rubrique VARCHAR(30) NOT NULL, CHANGE description description VARCHAR(255) DEFAULT NULL, CHANGE topic topic VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user DROP is_banned, DROP last_activity_at');
    }
}
