<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use EMS\CommonBundle\Helper\Text\Encoder;

final class Version20220716091159 extends AbstractMigration
{
    #[\Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE template ADD label VARCHAR(255)');
        $this->addSql('UPDATE template SET label=name');
        $this->addSql('ALTER TABLE template ALTER label SET NOT NULL');
        $this->addSql('ALTER TABLE view ADD label VARCHAR(255)');
        $this->addSql('UPDATE view SET label=name');
        $this->addSql('ALTER TABLE view ALTER label SET NOT NULL');

        $this->webalizeName('view');
        $this->webalizeName('template');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\PostgreSQLPlatform'."
        );

        $this->addSql('ALTER TABLE view DROP label');
        $this->addSql('ALTER TABLE template DROP label');
    }

    public function webalizeName(string $table): void
    {
        $stmt = $this->connection->prepare("SELECT * FROM $table");
        foreach ($stmt->executeQuery()->iterateAssociative() as $view) {
            if (!isset($view['id']) || !isset($view['name'])) {
                continue;
            }
            $this->addSql("UPDATE $table SET name = :name WHERE id = :id", [
                'id' => $view['id'],
                'name' => new Encoder()->slug(text: $view['name'], separator: '_')->toString(),
            ]);
        }
    }
}
