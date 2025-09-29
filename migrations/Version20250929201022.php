<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250929201022 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE booking (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , confirmed_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , confirmation_token VARCHAR(64) NOT NULL, course_period VARCHAR(100) NOT NULL, desired_time_slot VARCHAR(32) NOT NULL, child_name VARCHAR(160) NOT NULL, child_birthdate DATE NOT NULL --(DC2Type:date_immutable)
        , child_address CLOB NOT NULL, has_swim_experience BOOLEAN NOT NULL, swim_experience_details VARCHAR(500) DEFAULT NULL, health_notes CLOB DEFAULT NULL, may_swim_without_aid BOOLEAN NOT NULL, parent_name VARCHAR(160) NOT NULL, parent_phone VARCHAR(40) DEFAULT NULL, parent_email VARCHAR(200) NOT NULL, is_member_of_club BOOLEAN NOT NULL, payment_method VARCHAR(20) NOT NULL, participation_consent BOOLEAN NOT NULL, liability_acknowledged BOOLEAN NOT NULL, photo_consent BOOLEAN NOT NULL, data_consent BOOLEAN NOT NULL, booking_confirmation BOOLEAN NOT NULL, meta_ip VARCHAR(64) DEFAULT NULL, meta_ua VARCHAR(400) DEFAULT NULL, meta_host VARCHAR(200) DEFAULT NULL, meta_time VARCHAR(40) DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E00CEDDEC05FB297 ON booking (confirmation_token)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE booking');
    }
}
