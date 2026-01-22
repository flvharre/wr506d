<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251119175613 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE movie_category DROP FOREIGN KEY FK_DABA824C8F93B6FC');
        $this->addSql('ALTER TABLE movie_category DROP FOREIGN KEY FK_DABA824C12469DE2');
        $this->addSql('DROP TABLE movie_category');
        $this->addSql('ALTER TABLE actor CHANGE dob dob DATETIME NOT NULL, CHANGE dod dod DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE movie ADD poster VARCHAR(255) DEFAULT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE released released DATETIME NOT NULL, CHANGE metascore metascore DOUBLE PRECISION DEFAULT NULL, CHANGE online online TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE movie_category (movie_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_DABA824C12469DE2 (category_id), INDEX IDX_DABA824C8F93B6FC (movie_id), PRIMARY KEY(movie_id, category_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE movie_category ADD CONSTRAINT FK_DABA824C8F93B6FC FOREIGN KEY (movie_id) REFERENCES movie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE movie_category ADD CONSTRAINT FK_DABA824C12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE movie DROP poster, CHANGE description description VARCHAR(1000) DEFAULT NULL, CHANGE released released DATE DEFAULT NULL, CHANGE metascore metascore INT DEFAULT NULL, CHANGE online online TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE actor CHANGE dob dob DATE DEFAULT NULL, CHANGE dod dod DATE DEFAULT NULL');
    }
}
