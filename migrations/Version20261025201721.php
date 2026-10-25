<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261025201721 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE author (name VARCHAR(255) NOT NULL, id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE book (title VARCHAR(255) NOT NULL, price_in_cents INT NOT NULL, genre VARCHAR(32) NOT NULL, published_on DATE NOT NULL, stock INT NOT NULL, id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_book_title_id ON book (title, id)');
        $this->addSql('CREATE TABLE book_author (author_id INT NOT NULL, book_id INT NOT NULL, PRIMARY KEY (author_id, book_id))');
        $this->addSql('CREATE INDEX idx_book_author_author_id ON book_author (author_id)');
        $this->addSql('CREATE INDEX IDX_9478D34516A2B381 ON book_author (book_id)');
        $this->addSql('CREATE TABLE discount (kind VARCHAR(16) NOT NULL, value INT NOT NULL, starts_on DATE NOT NULL, ends_on DATE NOT NULL, target VARCHAR(32) NOT NULL, genre VARCHAR(32) DEFAULT NULL, published_from DATE DEFAULT NULL, published_to DATE DEFAULT NULL, id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_discount_period ON discount (starts_on, ends_on)');
        $this->addSql('CREATE TABLE discount_author (author_id INT NOT NULL, discount_id INT NOT NULL, PRIMARY KEY (author_id, discount_id))');
        $this->addSql('CREATE INDEX idx_discount_author_author_id ON discount_author (author_id)');
        $this->addSql('CREATE INDEX IDX_D3EFF4F74C7C611F ON discount_author (discount_id)');
        $this->addSql('CREATE TABLE purchase (book_id INT NOT NULL, purchased_on DATE NOT NULL, base_price_in_cents INT NOT NULL, discount_id INT DEFAULT NULL, paid_in_cents INT NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE rental (book_id INT NOT NULL, rented_on DATE NOT NULL, due_on DATE NOT NULL, base_price_in_cents INT NOT NULL, discount_id INT DEFAULT NULL, price_in_cents INT NOT NULL, returned_on DATE DEFAULT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_rental_book_id ON rental (book_id)');
        $this->addSql('ALTER TABLE book_author ADD CONSTRAINT FK_9478D34516A2B381 FOREIGN KEY (book_id) REFERENCES book (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE discount_author ADD CONSTRAINT FK_D3EFF4F74C7C611F FOREIGN KEY (discount_id) REFERENCES discount (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE book_author DROP CONSTRAINT FK_9478D34516A2B381');
        $this->addSql('ALTER TABLE discount_author DROP CONSTRAINT FK_D3EFF4F74C7C611F');
        $this->addSql('DROP TABLE author');
        $this->addSql('DROP TABLE book');
        $this->addSql('DROP TABLE book_author');
        $this->addSql('DROP TABLE discount');
        $this->addSql('DROP TABLE discount_author');
        $this->addSql('DROP TABLE purchase');
        $this->addSql('DROP TABLE rental');
    }
}
