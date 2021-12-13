<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20211210184200 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('ALTER TABLE wysiwyg_styles_set ADD COLUMN content_js VARCHAR(2048) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TEMPORARY TABLE __temp__wysiwyg_styles_set AS SELECT id, created, modified, name, config, orderKey, format_tags, table_default_css, content_css, assets, save_dir FROM wysiwyg_styles_set');
        $this->addSql('DROP TABLE wysiwyg_styles_set');
        $this->addSql('CREATE TABLE wysiwyg_styles_set (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(255) NOT NULL, config CLOB DEFAULT NULL, orderKey INTEGER NOT NULL, format_tags VARCHAR(255) DEFAULT NULL, table_default_css VARCHAR(255) DEFAULT \'table table-bordered\' NOT NULL, content_css VARCHAR(2048) DEFAULT NULL, assets CLOB DEFAULT NULL --(DC2Type:json)
        , save_dir VARCHAR(2048) DEFAULT NULL)');
        $this->addSql('INSERT INTO wysiwyg_styles_set (id, created, modified, name, config, orderKey, format_tags, table_default_css, content_css, assets, save_dir) SELECT id, created, modified, name, config, orderKey, format_tags, table_default_css, content_css, assets, save_dir FROM __temp__wysiwyg_styles_set');
        $this->addSql('DROP TABLE __temp__wysiwyg_styles_set');
    }
}
