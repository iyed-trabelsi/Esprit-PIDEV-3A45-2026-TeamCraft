<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260209052049 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE player ADD game VARCHAR(100) DEFAULT NULL, ADD role VARCHAR(100) DEFAULT NULL, ADD region VARCHAR(100) DEFAULT NULL, ADD status VARCHAR(50) DEFAULT NULL, DROP selected_games, CHANGE game_rank game_rank VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE player ADD selected_games JSON DEFAULT NULL, DROP game, DROP role, DROP region, DROP status, CHANGE game_rank game_rank VARCHAR(50) DEFAULT NULL');
    }
}
