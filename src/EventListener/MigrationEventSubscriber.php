<?php

declare(strict_types=1);

namespace EMS\CoreBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;

class MigrationEventSubscriber implements EventSubscriberInterface
{
    public function getSubscribedEvents(): array
    {
        return [ToolEvents::postGenerateSchema => 'postGenerateSchema'];
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        $platform = $args->getEntityManager()->getConnection()->getDatabasePlatform();

        if (!$platform instanceof PostgreSQLPlatform) {
            return;
        }

        $schema = $args->getSchema();

        if (!$schema->hasNamespace($schema->getName())) {
            $schema->createNamespace($schema->getName());
        }
    }
}
