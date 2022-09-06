<?php

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170519191354 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_FF561896772E836A ON i18n (identifier)');
        $this->addSql('INSERT INTO `i18n` (`id`, `created`, `modified`, `identifier`, `content`) VALUES (NULL, \'2017-05-19 21:04:48\', \'2017-05-19 21:21:27\', \'ems.documentation.body\', \'[{\"locale\":\"en\",\"text\":\"<div class=\\\"box\\\"><div class=\\\"box-header with-border\\\"><h3 class=\\\"box-title\\\">Based on elasticsearch, Symfony, Bootstrap and AdminLTE<\\/h3> <\\/div> <div class=\\\"box-body\\\" style=\\\"display: block;\\\"><p>Visit <a href=\\\"http:\\/\\/www.elasticms.eu\\/\\\">elasticms.eu<\\/a><\\/p><\\/div><\\/div>\"}]\')');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('DELETE FROM `i18n` WHERE `identifier` = \'ems.documentation.body\'');
        $this->addSql('DROP INDEX UNIQ_FF561896772E836A ON i18n');
    }
}
