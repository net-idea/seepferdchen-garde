<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the spam quarantine columns to the contact form table.
 *
 * Uses the schema API instead of raw SQL so that it runs on SQLite (dev) as well as on
 * MariaDB (prod). As this is the first migration of the project, installations whose
 * tables were never created get the complete schema here.
 */
final class Version20261113120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Contact form: quarantine suspected spam instead of dropping it';
    }

    public function isTransactional(): bool
    {
        // MariaDB commits implicitly on DDL statements
        return false;
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('form_submission_meta')) {
            $meta = $schema->createTable('form_submission_meta');
            $meta->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
            $meta->addColumn('ip', Types::STRING, ['length' => 64, 'notnull' => false]);
            $meta->addColumn('user_agent', Types::STRING, ['length' => 400, 'notnull' => false]);
            $meta->addColumn('time', Types::STRING, ['length' => 40, 'notnull' => false]);
            $meta->addColumn('host', Types::STRING, ['length' => 200, 'notnull' => false]);
            $meta->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create());
        }

        if (!$schema->hasTable('form_contact')) {
            $contact = $schema->createTable('form_contact');
            $contact->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
            $contact->addColumn('name', Types::STRING, ['length' => 160]);
            $contact->addColumn('email_address', Types::STRING, ['length' => 200]);
            $contact->addColumn('phone', Types::STRING, ['length' => 40, 'notnull' => false]);
            $contact->addColumn('consent', Types::BOOLEAN);
            $contact->addColumn('message', Types::TEXT);
            $contact->addColumn('copy', Types::BOOLEAN);
            $contact->addColumn('created_at', Types::DATETIME_IMMUTABLE);
            $contact->addColumn('meta_id', Types::INTEGER, ['notnull' => false]);
            $contact->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create());
            $contact->addUniqueIndex(['meta_id']);
            $contact->addForeignKeyConstraint('form_submission_meta', ['meta_id'], ['id'], ['onDelete' => 'SET NULL']);
        }

        $contact = $schema->getTable('form_contact');

        if (!$contact->hasColumn('quarantined')) {
            $contact->addColumn('quarantined', Types::BOOLEAN, ['default' => false]);
        }

        if (!$contact->hasColumn('quarantine_reason')) {
            $contact->addColumn('quarantine_reason', Types::STRING, ['length' => 255, 'notnull' => false]);
        }
    }

    public function down(Schema $schema): void
    {
        $contact = $schema->getTable('form_contact');
        $contact->dropColumn('quarantined');
        $contact->dropColumn('quarantine_reason');
    }
}
