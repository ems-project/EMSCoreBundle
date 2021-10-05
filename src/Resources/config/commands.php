<?php

declare(strict_types=1);

use EMS\CommonBundle\Twig\RequestRuntime;
use EMS\CoreBundle\Command\Asset\AssetCleanCommand;
use EMS\CoreBundle\Command\Asset\AssetExtractCommand;
use EMS\CoreBundle\Command\Asset\AssetHeadCommand;
use EMS\CoreBundle\Command\Asset\AssetSynchronizeCommand;
use EMS\CoreBundle\Command\Check\CheckAliasesCommand;
use EMS\CoreBundle\Command\ContentType\ContentTypeActivateCommand;
use EMS\CoreBundle\Command\ContentType\ContentTypeCleanCommand;
use EMS\CoreBundle\Command\ContentType\ContentTypeDeleteCommand;
use EMS\CoreBundle\Command\ContentType\ContentTypeExportCommand;
use EMS\CoreBundle\Command\ContentType\ContentTypeImportCommand;
use EMS\CoreBundle\Command\ContentType\ContentTypeLockCommand;
use EMS\CoreBundle\Command\ContentType\ContentTypeTransformCommand;
use EMS\CoreBundle\Command\Delete\DeleteOrphanIndexesCommand;
use EMS\CoreBundle\Command\Environment\EnvironmentAlignCommand;
use EMS\CoreBundle\Command\Environment\EnvironmentCreateCommand;
use EMS\CoreBundle\Command\Environment\EnvironmentListCommand;
use EMS\CoreBundle\Command\Environment\EnvironmentMigrateCommand;
use EMS\CoreBundle\Command\Environment\EnvironmentRebuildCommand;
use EMS\CoreBundle\Command\Environment\EnvironmentRecomputeCommand;
use EMS\CoreBundle\Command\Environment\EnvironmentReindexCommand;
use EMS\CoreBundle\Command\Environment\EnvironmentUpdateMetaFieldCommand;
use EMS\CoreBundle\Command\Job\JobRunCommand;
use EMS\CoreBundle\Command\ManagedAlias\ManagedAliasAlignCommand;
use EMS\CoreBundle\Command\ManagedAlias\ManagedAliasListCommand;
use EMS\CoreBundle\Command\Notification\NotificationBulkActionCommand;
use EMS\CoreBundle\Command\Notification\NotificationSendCommand;
use EMS\CoreBundle\Command\Revision\RevisionArchiveCommand;
use EMS\CoreBundle\Command\Revision\RevisionCopyCommand;
use EMS\CoreBundle\Command\Revision\RevisionIndexFileCommand;
use EMS\CoreBundle\Command\Revision\RevisionTaskCreateCommand;
use EMS\CoreBundle\Command\Revision\RevisionTimeMachineCommand;
use EMS\CoreBundle\Command\Revision\RevisionUnlockCommand;
use EMS\CoreBundle\Command\Submission\SubmissionEmailCommand;
use EMS\CoreBundle\Command\Submission\SubmissionRemoveExpiredCommand;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\ref;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set('ems.command.asset.clean', AssetCleanCommand::class)
        ->args([
            ref('logger'),
            ref('doctrine'),
            ref('ems.service.file'),
        ])
        ->tag('console.command');

    $services->set('ems.command.asset.extract', AssetExtractCommand::class)
        ->args([
            ref('logger'),
            ref('ems.service.asset_extractor'),
            ref('ems_common.storage.manager'),
        ])
        ->tag('console.command');

    $services->set('ems.command.asset.head', AssetHeadCommand::class)
        ->args([
            ref('logger'),
            ref('ems.service.file'),
        ])
        ->tag('console.command');

    $services->set('ems.command.asset.synchronize', AssetSynchronizeCommand::class)
        ->args([
            ref('logger'),
            ref('doctrine'),
            ref('ems.service.contenttype'),
            ref('ems.service.asset_extractor'),
            ref('ems.service.file'),
        ])
        ->tag('console.command');

    $services->set('ems.command.check.aliases', CheckAliasesCommand::class)
        ->args([
            ref('ems.service.environment'),
            ref('ems.service.alias'),
            ref('ems.service.job'),
        ])
        ->tag('console.command');

    $services->set('ems.command.contenttype.activate', ContentTypeActivateCommand::class)
        ->args([
            ref('logger'),
            ref('ems.service.contenttype'),
        ])
        ->tag('console.command');

    $services->set('ems.command.contenttype.clean', ContentTypeCleanCommand::class)
        ->args([
            ref('doctrine'),
            ref('logger'),
            ref('ems.service.mapping'),
            ref('service_container'),
        ])
        ->tag('console.command');

    $services->set('ems.command.contenttype.delete', ContentTypeDeleteCommand::class)
        ->args([
            ref('doctrine'),
            ref('logger'),
            ref('ems.service.index'),
            ref('ems.service.mapping'),
            ref('service_container'),
            ref('ems.service.contenttype'),
            ref('ems.service.environment'),
        ])
        ->tag('console.command');

    $services->set('ems.command.contenttype.export', ContentTypeExportCommand::class)
        ->args([
            ref('logger'),
            ref('ems.service.template'),
            ref('ems.service.data'),
            ref('ems.service.contenttype'),
            ref('ems.service.environment'),
            ref(RequestRuntime::class),
            ref('ems_common.service.elastica'), '%ems_core.instance_id%',
        ])
        ->tag('console.command');

    $services->set('ems.command.contenttype.import', ContentTypeImportCommand::class)
        ->args([
            ref('ems.service.contenttype'),
            ref('ems.service.document'),
            ref('ems.service.data'),
            '%ems_core.default_bulk_size%',
        ])
        ->tag('console.command');

    $services->set('ems.command.contenttype.lock', ContentTypeLockCommand::class)
        ->args([
            ref(ContentTypeRepository::class),
            ref('ems_common.service.elastica'),
            ref(RevisionRepository::class),
        ])
        ->tag('console.command');

    $services->set('ems.command.contenttype.transform', ContentTypeTransformCommand::class)
        ->args([
            ref('ems_core.core_revision_search.revision_searcher'),
            ref('ems.service.contenttype'),
            ref('ems_core.core_content_type_transformer.content_transformer'),
        ])
        ->tag('console.command');

    $services->set('ems.command.delete.orphans_indexes', DeleteOrphanIndexesCommand::class)
        ->args([
            ref('ems.service.index'),
        ])
        ->tag('console.command');

    $services->set('ems.command.environment.align', EnvironmentAlignCommand::class)
        ->args([
            ref('ems_core.core_revision_search.revision_searcher'),
            ref('logger'),
            ref('ems.service.environment'),
            ref('ems.service.publish'),
        ])
        ->tag('console.command');

    $services->set('ems.command.environment.create', EnvironmentCreateCommand::class)
        ->args([
            ref('logger'),
            ref('ems.service.environment'),
            ref('ems.service.data'),
        ])
        ->tag('console.command');

    $services->set('ems.command.environment.list', EnvironmentListCommand::class)
        ->args([
            ref('ems.service.environment'),
        ])
        ->tag('console.command');

    $services->set('ems.command.environment.migrate', EnvironmentMigrateCommand::class)
        ->args([
            ref('ems.service.revision'),
            ref('doctrine'),
            ref('ems_common.service.elastica'),
            ref('ems.service.document'),
        ])
        ->tag('console.command');

    $services->set('ems.command.environment.rebuild', EnvironmentRebuildCommand::class)
        ->args([
            ref('doctrine'),
            ref('logger'),
            ref('ems.service.contenttype'),
            ref('ems.service.environment'),
            ref('ems.environment.reindex'),
            ref('ems_common.service.elastica'),
            ref('ems.service.mapping'),
            ref('ems.service.alias'),
            '%ems_core.instance_id%',
            '%ems_core.default_bulk_size%',
        ])
        ->tag('console.command');

    $services->set('ems.command.environment.recompute', EnvironmentRecomputeCommand::class)
        ->args([
            ref('ems.service.data'),
            ref('doctrine'),
            ref('form.factory'),
            ref('ems.service.publish'),
            ref('logger'),
            ref('ems.service.contenttype'),
            ref(ContentTypeRepository::class),
            ref(RevisionRepository::class),
            ref('ems.service.index'),
            ref('ems.service.search'),
        ])
        ->tag('console.command');

    $services->set('ems.command.environment.reindex', EnvironmentReindexCommand::class)
        ->args([
            ref('doctrine'),
            ref('logger'),
            ref('ems.service.mapping'),
            ref('service_container'),
            '%ems_core.instance_id%',
            ref('ems.service.data'),
            ref('ems.elasticsearch.bulker'),
            '%ems_core.default_bulk_size%',
        ])
        ->tag('console.command');

    $services->set('ems.command.environment.update_meta_field', EnvironmentUpdateMetaFieldCommand::class)
        ->args([
            ref('doctrine'),
            ref('logger'),
            ref('ems.service.data'),
        ])
        ->tag('console.command');

    $services->set('ems.command.job.run', JobRunCommand::class)
        ->args([
            ref('ems.service.job'),
            '%ems_core.date_time_format%',
        ])
        ->tag('console.command');

    $services->set('ems.command.managed_alias.align', ManagedAliasAlignCommand::class)
        ->args([
            ref('logger'),
            ref('ems.service.alias'),
        ])
        ->tag('console.command');

    $services->set('ems.command.managed_alias.list', ManagedAliasListCommand::class)
        ->args([
            ref('logger'),
            ref('ems.service.alias'),
        ])
        ->tag('console.command');

    $services->set('ems.command.notification.bulk_action', NotificationBulkActionCommand::class)
        ->args([
            ref('ems.service.notification'),
            ref('ems.service.environment'),
            ref('ems_common.service.elastica'),
            ref('ems.service.revision'),
        ])
        ->tag('console.command');

    $services->set('ems.command.notification.send', NotificationSendCommand::class)
        ->args([
            ref('doctrine'),
            ref('ems.service.notification'),
            '%ems_core.notification_pending_timeout%',
        ])
        ->tag('console.command');

    $services->set('ems.command.revision.archive', RevisionArchiveCommand::class)
        ->args([
            ref('ems.service.contenttype'),
            ref('ems.service.revision'),
            '%ems_core.default_bulk_size%',
        ])
        ->tag('console.command');
    $services->set('ems.command.revision.copy', RevisionCopyCommand::class)
        ->args([
            ref('ems.factory.revision_copy_context'),
            ref('ems.service.revision_copy'),
            ref('ems.service.elasticsearch'),
            ref('ems_common.service.elastica'),
        ])
        ->tag('console.command');
    $services->set('ems.command.revision.index_file', RevisionIndexFileCommand::class)
        ->args([
            ref('logger'),
            ref('doctrine'),
            ref('ems.service.contenttype'),
            ref('ems.service.asset_extractor'),
            ref('ems.service.file'),
        ])
        ->tag('console.command');
    $services->set('ems.command.revision.task_create', RevisionTaskCreateCommand::class)
        ->args([
            ref('ems_core.core_revision_search.revision_searcher'),
            ref('ems.service.environment'),
            ref('ems.service.user'),
            ref('ems_core.core_revision_task.task_manager'),
        ])
        ->tag('console.command');
    $services->set('ems.command.revision.time_machine', RevisionTimeMachineCommand::class)
        ->args([
            ref('ems.service.revision'),
            ref('ems.service.data'),
            ref('doctrine'),
            ref('ems.service.index'),
        ])
        ->tag('console.command');

    $services->set('ems.command.revision.unlock', RevisionUnlockCommand::class)
        ->args([
            ref('logger'),
            ref('ems.service.data'),
            ref('ems.service.contenttype'),
        ])
        ->tag('console.command');

    $services->set('ems.command.submission.email', SubmissionEmailCommand::class)
        ->args([
            ref('ems.form_submission'),
            ref('logger'),
            ref('ems_core.core_mail.mailer_service'),
        ])
        ->tag('console.command');

    $services->set('ems.command.submission.remove_expired', SubmissionRemoveExpiredCommand::class)
        ->args([
            ref('ems.form_submission'),
            ref('logger'),
        ])
        ->tag('console.command');
};
