<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251029221807 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE media_object (id INT AUTO_INCREMENT NOT NULL, actor_id INT DEFAULT NULL, movie_id INT DEFAULT NULL, file_path VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_14D4313210DAF24A (actor_id), INDEX IDX_14D431328F93B6FC (movie_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE media_object ADD CONSTRAINT FK_14D4313210DAF24A FOREIGN KEY (actor_id) REFERENCES actor (id)');
        $this->addSql('ALTER TABLE media_object ADD CONSTRAINT FK_14D431328F93B6FC FOREIGN KEY (movie_id) REFERENCES movie (id)');
        $this->addSql('ALTER TABLE actor ADD photo_id INT DEFAULT NULL, DROP photo');
        $this->addSql('ALTER TABLE actor ADD CONSTRAINT FK_447556F97E9E4C8C FOREIGN KEY (photo_id) REFERENCES media_object (id)');
        $this->addSql('CREATE INDEX IDX_447556F97E9E4C8C ON actor (photo_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE actor DROP FOREIGN KEY FK_447556F97E9E4C8C');
        $this->addSql('ALTER TABLE media_object DROP FOREIGN KEY FK_14D4313210DAF24A');
        $this->addSql('ALTER TABLE media_object DROP FOREIGN KEY FK_14D431328F93B6FC');
        $this->addSql('DROP TABLE media_object');
        $this->addSql('DROP INDEX IDX_447556F97E9E4C8C ON actor');
        $this->addSql('ALTER TABLE actor ADD photo VARCHAR(255) DEFAULT NULL, DROP photo_id');
    }
}
