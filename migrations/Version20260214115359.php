<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260214115359 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE message_image (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, message_id INT NOT NULL, INDEX IDX_3A9BFBB4537A1329 (message_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE message_image ADD CONSTRAINT FK_3A9BFBB4537A1329 FOREIGN KEY (message_id) REFERENCES message (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE message_image DROP FOREIGN KEY FK_3A9BFBB4537A1329');
        $this->addSql('DROP TABLE message_image');
    }
}
