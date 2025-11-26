<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251119111142 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du champ username à la table user et mise à jour de l\'index.';
    }

    public function up(Schema $schema): void
    {
        // pour tous les utilisateurs existants qui ont un username vide (NULL ou '')
        $this->addSql("UPDATE user SET username = CONCAT('user_', id) WHERE username IS NULL OR username = ''");

        // 2. Création de l'index d'unicité
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME ON user (username)');

        // Mise à jour de la colonne email pour enlever l'ancien index
        $this->addSql('DROP INDEX UNIQ_IDENTIFIER_EMAIL ON user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON user (email)');
    }

    public function down(Schema $schema): void
    {

        $this->addSql('DROP INDEX UNIQ_IDENTIFIER_USERNAME ON user');
    }
}
