<?php

declare(strict_types=1);

use EMS\CommonBundle\Twig\RequestRuntime;
use EMS\CoreBundle\Command\AbstractCommand;
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

    $services->set('ems.command.abstract', AbstractCommand::class)
        ->call('setLogger', [ref('logger')]);

    $services->set('ems.command.asset.clean', AssetCleanCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('doctrine'),
            ref('ems.service.file'),
        ])
        ->tag('console.command');

    $services->set('ems.command.asset.extract', AssetExtractCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems.service.asset_extractor'),
            ref('ems_common.storage.manager'),
        ])
        ->tag('console.command');

    $services->set('ems.command.asset.head', AssetHeadCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems.service.file'),
        ])
        ->tag('console.command');

    $services->set('ems.command.asset.synchronize', AssetSynchronizeCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('doctrine'),
            ref('ems.service.file'),
        ])
        ->tag('console.command');

    $services->set('ems.command.check.aliases', CheckAliasesCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems.service.environment'),
            ref('ems.service.alias'),
            ref('ems.service.job'),
        ])
        ->tag('console.command');

    $services->set('ems.command.contenttype.activate', ContentTypeActivateCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems.service.contenttype'),
        ])
        ->tag('console.command');

    $services->set('ems.command.contenttype.clean', ContentTypeCleanCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('doctrine'),
        ])
        ->tag('console.command');

    $services->set('ems.command.contenttype.delete', ContentTypeDeleteCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('doctrine'),
            ref('ems.service.index'),
        ])
        ->tag('console.command');

    $services->set('ems.command.contenttype.export', ContentTypeExportCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems.service.template'),
            ref('ems.service.data'),
            ref('ems.service.contenttype'),
            ref('ems.service.environment'),
            ref(RequestRuntime::class),
            ref('ems_common.service.elastica'),
        ])
        ->tag('console.command');

    $services->set('ems.command.contenttype.import', ContentTypeImportCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems.service.contenttype'),
            ref('ems.service.document'),
            ref('ems.service.data'),
            '%ems_core.default_bulk_size%',
        ])
        ->tag('console.command');

    $services->set('ems.command.contenttype.lock', ContentTypeLockCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref(ContentTypeRepository::class),
            ref('ems_common.service.elastica'),
            ref(RevisionRepository::class),
        ])
        ->tag('console.command');

    $services->set('ems.command.contenttype.transform', ContentTypeTransformCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems_core.core_revision_search.revision_searcher'),
            ref('ems.service.contenttype'),
            ref('ems_core.core_content_type_transformer.content_transformer'),
        ])
        ->tag('console.command');

    $services->set('ems.command.delete.orphans_indexes', DeleteOrphanIndexesCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems.service.index'),
        ])
        ->tag('console.command');

    $services->set('ems.command.environment.align', EnvironmentAlignCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems_core.core_revision_search.revision_searcher'),
            ref('ems.service.environment'),
            ref('ems.service.publish'),
        ])
        ->tag('console.command');

    $services->set('ems.command.environment.create', EnvironmentCreateCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems.service.environment'),
            ref('ems.service.data'),
        ])
        ->tag('console.command');

    $services->set('ems.command.environment.list', EnvironmentListCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems.service.environment'),
        ])
        ->tag('console.command');

    $services->set('ems.command.environment.migrate', EnvironmentMigrateCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('doctrine'),
            ref('ems_common.service.elastica'),
            ref('ems.service.document'),
        ])
        ->tag('console.command');

    $services->set('ems.command.environment.rebuild', EnvironmentRebuildCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('doctrine'),
            ref('ems.service.contenttype'),
            ref('ems.service.environment'),
            ref('ems.command.environment.reindex'),
            ref('ems_common.service.elastica'),
            ref('ems.service.mapping'),
            ref('ems.service.alias'),
            '%ems_core.instance_id%',
            '%ems_core.default_bulk_size%',
        ])
        ->tag('console.command');

    $services->set('ems.command.environment.recompute', EnvironmentRecomputeCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems.service.data'),
            ref('doctrine'),
            ref('form.factory'),
            ref('ems.service.publish'),
            ref('ems.service.contenttype'),
            ref(ContentTypeRepository::class),
            ref(RevisionRepository::class),
            ref('ems.service.index'),
            ref('ems.service.search'),
        ])
        ->tag('console.command');

    $services->set('ems.command.environment.reindex', EnvironmentReindexCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('doctrine'),
            ref('ems.elasticsearch.bulker'),
            '%ems_core.default_bulk_size%',
        ])
        ->tag('console.command');

    $services->set('ems.command.environment.update_meta_field', EnvironmentUpdateMetaFieldCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('doctrine'),
            ref('ems.service.data'),
        ])
        ->tag('console.command');

    $services->set('ems.command.job.run', JobRunCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems.service.job'),
            '%ems_core.date_time_format%',
        ])
        ->tag('console.command');

    $services->set('ems.command.managed_alias.align', ManagedAliasAlignCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems.service.alias'),
        ])
        ->tag('console.command');

    $services->set('ems.command.managed_alias.list', ManagedAliasListCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems.service.alias'),
        ])
        ->tag('console.command');

    $services->set('ems.command.notification.bulk_action', NotificationBulkActionCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems.service.notification'),
            ref('ems.service.environment'),
            ref('ems_common.service.elastica'),
            ref('ems.service.revision'),
        ])
        ->tag('console.command');

    $services->set('ems.command.notification.send', NotificationSendCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('doctrine'),
            ref('ems.service.notification'),
            '%ems_core.notification_pending_timeout%',
        ])
        ->tag('console.command');

    $services->set('ems.command.revision.archive', RevisionArchiveCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems.service.contenttype'),
            ref('ems.service.revision'),
            '%ems_core.default_bulk_size%',
        ])
        ->tag('console.command');
    $services->set('ems.command.revision.copy', RevisionCopyCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems.factory.revision_copy_context'),
            ref('ems.service.revision_copy'),
            ref('ems_common.service.elastica'),
        ])
        ->tag('console.command');
    $services->set('ems.command.revision.index_file', RevisionIndexFileCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('doctrine'),
            ref('ems.service.contenttype'),
            ref('ems.service.asset_extractor'),
            ref('ems.service.file'),
        ])
        ->tag('console.command');
    $services->set('ems.command.revision.task_create', RevisionTaskCreateCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems_core.core_revision_search.revision_searcher'),
            ref('ems.service.environment'),
            ref('ems.service.user'),
            ref('ems_core.core_revision_task.task_manager'),
        ])
        ->tag('console.command');
    $services->set('ems.command.revision.time_machine', RevisionTimeMachineCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems.service.revision'),
            ref('ems.service.data'),
            ref('doctrine'),
            ref('ems.service.index'),
        ])
        ->tag('console.command');

    $services->set('ems.command.revision.unlock', RevisionUnlockCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems.service.data'),
            ref('ems.service.contenttype'),
        ])
        ->tag('console.command');

    $services->set('ems.command.submission.email', SubmissionEmailCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems.form_submission'),
            ref('ems_core.core_mail.mailer_service'),
        ])
        ->tag('console.command');

    $services->set('ems.command.submission.remove_expired', SubmissionRemoveExpiredCommand::class)
        ->parent('ems.command.abstract')
        ->args([
            ref('ems.form_submission'),
        ])
        ->tag('console.command');
};
