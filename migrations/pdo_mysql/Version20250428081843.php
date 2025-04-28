<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250428081843 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Group entity that add properties (e.g. roles) to User entities';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );
        
        $this->addSql(<<<'SQL'
            CREATE TABLE user_group (name VARCHAR(255) NOT NULL, label VARCHAR(255) NOT NULL, roles JSON NOT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, id CHAR(36) NOT NULL, UNIQUE INDEX UNIQ_8F02BF9D5E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `user` ADD group_id CHAR(36) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `user` ADD CONSTRAINT FK_8D93D649FE54D947 FOREIGN KEY (group_id) REFERENCES user_group (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_8D93D649FE54D947 ON `user` (group_id)
        SQL);
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform
            && !$this->connection->getDatabasePlatform() instanceof MariaDBPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\MySQLPlatform'."
        );
        
        $this->addSql(<<<'SQL'
            ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649FE54D947
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_8D93D649FE54D947 ON `user`
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `user` DROP group_id
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_group
        SQL);
    }
}
