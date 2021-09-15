<?php

declare(strict_types=1);

use EMS\CommonBundle\Twig\RequestRuntime;
use EMS\CoreBundle\Command\ActivateContentTypeCommand;
use EMS\CoreBundle\Command\AlignManagedAliases;
use EMS\CoreBundle\Command\Asset\HeadAssetCommand;
use EMS\CoreBundle\Command\Check\AliasesCheckCommand;
use EMS\CoreBundle\Command\CleanAssetCommand;
use EMS\CoreBundle\Command\CleanDeletedContentTypeCommand;
use EMS\CoreBundle\Command\ContentType\TransformCommand;
use EMS\CoreBundle\Command\CreateEnvironmentCommand;
use EMS\CoreBundle\Command\DeleteCommand;
use EMS\CoreBundle\Command\DeleteOrphanIndexesCommand;
use EMS\CoreBundle\Command\DocumentCommand;
use EMS\CoreBundle\Command\EmailSubmissionsCommand;
use EMS\CoreBundle\Command\Environment\AlignCommand;
use EMS\CoreBundle\Command\EnvironmentCommand;
use EMS\CoreBundle\Command\ExportDocumentsCommand;
use EMS\CoreBundle\Command\ExtractAssetCommand;
use EMS\CoreBundle\Command\IndexFileCommand;
use EMS\CoreBundle\Command\JobCommand;
use EMS\CoreBundle\Command\LockCommand;
use EMS\CoreBundle\Command\ManagedAliases;
use EMS\CoreBundle\Command\MigrateCommand;
use EMS\CoreBundle\Command\Notification\BulkActionCommand;
use EMS\CoreBundle\Command\Notification\SendAllCommand;
use EMS\CoreBundle\Command\RebuildCommand;
use EMS\CoreBundle\Command\RecomputeCommand;
use EMS\CoreBundle\Command\ReindexCommand;
use EMS\CoreBundle\Command\RemoveExpiredSubmissionsCommand;
use EMS\CoreBundle\Command\Revision\ArchiveCommand;
use EMS\CoreBundle\Command\Revision\TaskCreateCommand;
use EMS\CoreBundle\Command\Revision\TimeMachineCommand;
use EMS\CoreBundle\Command\RevisionCopyCommand;
use EMS\CoreBundle\Command\SynchronizeAssetCommand;
use EMS\CoreBundle\Command\UnlockRevisionsCommand;
use EMS\CoreBundle\Command\UpdateMetaFieldCommand;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\ref;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set('ems.contenttype.lock', LockCommand::class)
        ->args([
            ref(ContentTypeRepository::class),
            ref('ems_common.service.elastica'),
            ref(RevisionRepository::class),
        ])
        ->tag('console.command');

    $services->set('ems.contenttype.transform', TransformCommand::class)
        ->args([
            ref('ems_core.core_revision_search.revision_searcher'),
            ref('ems.service.contenttype'),
            ref('ems_core.core_content_type_transformer.content_transformer'),
        ])
        ->tag('console.command');

    $services->set('ems.command.environment.align', AlignCommand::class)
        ->args([
            ref('ems_core.core_revision_search.revision_searcher'),
            ref('logger'),
            ref('ems.service.environment'),
            ref('ems.service.publish'),
        ])
        ->tag('console.command');

    $services->set('ems.command.revision.archive', ArchiveCommand::class)
        ->args([
            ref('ems.service.contenttype'),
            ref('ems.service.revision'),
            '%ems_core.default_bulk_size%',
        ])
        ->tag('console.command');

    $services->set('ems.command.revision.time_machine', TimeMachineCommand::class)
        ->args([
            ref('ems.service.revision'),
            ref('ems.service.data'),
            ref('doctrine'),
            ref('ems.service.index'),
        ])
        ->tag('console.command');

    $services->set('ems.command_revision.task_create_command', TaskCreateCommand::class)
        ->args([
            ref('ems_core.core_revision_search.revision_searcher'),
            ref('ems.service.environment'),
            ref('ems.service.user'),
            ref('ems_core.core_revision_task.task_manager'),
        ])
        ->tag('console.command');

    $services->set('ems.command.notification.bulk_action', BulkActionCommand::class)
        ->args([
            ref('ems.service.notification'),
            ref('ems.service.environment'),
            ref('ems_common.service.elastica'),
            ref('ems.service.revision'),
        ])
        ->tag('console.command');

    $services->set('ems.command.notification.send', SendAllCommand::class)
        ->args([
            ref('doctrine'),
            ref('ems.service.notification'),
            '%ems_core.notification_pending_timeout%',
        ])
        ->tag('console.command');

    $services->set('ems.command.check.aliases', AliasesCheckCommand::class)
        ->args([
            ref('ems.service.environment'),
            ref('ems.service.alias'),
            ref('ems.service.job'),
        ])
        ->tag('console.command');

    $services->set('ems.command.asset.head', HeadAssetCommand::class)
        ->args([
            ref('logger'),
            ref('ems.service.file'),
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

    $services->set('ems.contenttype.migrate', MigrateCommand::class)
        ->args([
            ref('ems.service.revision'),
            ref('doctrine'),
            ref('ems_common.service.elastica'),
            ref('ems.service.document'),
        ])
        ->tag('console.command');

    $services->set('ems.make.document', DocumentCommand::class)
        ->args([
            ref('ems.service.contenttype'),
            ref('ems.service.document'),
            ref('ems.service.data'),
            '%ems_core.default_bulk_size%',
        ])
        ->tag('console.command');

    $services->set('ems.contenttype.export', ExportDocumentsCommand::class)
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

    $services->set('ems.environment.rebuild', RebuildCommand::class)
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

    $services->set('ems.delete.orphans', DeleteOrphanIndexesCommand::class)
        ->args([
            ref('ems.service.index'),
        ])
        ->tag('console.command');

    $services->set('ems.environment.recompute', RecomputeCommand::class)
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

    $services->set('ems.environment.removeexpiredsubmissions', RemoveExpiredSubmissionsCommand::class)
        ->args([
            ref('ems.form_submission'),
            ref('logger'),
        ])
        ->tag('console.command');

    $services->set('ems.environment.emailsubmissions', EmailSubmissionsCommand::class)
        ->args([
            ref('ems.form_submission'),
            ref('logger'),
            ref('ems_core.core_mail.mailer_service'),
        ])
        ->tag('console.command');

    $services->set('ems.environment.updatemetafield', UpdateMetaFieldCommand::class)
        ->args([
            ref('doctrine'),
            ref('logger'),
            ref('ems.service.data'),
        ])
        ->tag('console.command');

    $services->set('ems.environment.reindex', ReindexCommand::class)
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

    $services->set('ems.contenttype.delete', DeleteCommand::class)
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

    $services->set('ems.contenttype.clean', CleanDeletedContentTypeCommand::class)
        ->args([
            ref('doctrine'),
            ref('logger'),
            ref('ems.service.mapping'),
            ref('service_container'),
        ])
        ->tag('console.command');

    $services->set('ems.revisions.index-file-fields', IndexFileCommand::class)
        ->args([
            ref('logger'),
            ref('doctrine'),
            ref('ems.service.contenttype'),
            ref('ems.service.asset_extractor'),
            ref('ems.service.file'),
        ])
        ->tag('console.command');

    $services->set('ems.environment.list', EnvironmentCommand::class)
        ->args([
            ref('ems.service.environment'),
        ])
        ->tag('console.command');

    $services->set(SynchronizeAssetCommand::class)
        ->args([
            ref('logger'),
            ref('doctrine'),
            ref('ems.service.contenttype'),
            ref('ems.service.asset_extractor'),
            ref('ems.service.file'),
        ])
        ->tag('console.command');

    $services->set(CleanAssetCommand::class)
        ->args([
            ref('logger'),
            ref('doctrine'),
            ref('ems.service.file'),
        ])
        ->tag('console.command');

    $services->set(AlignManagedAliases::class)
        ->args([
            ref('logger'),
            ref('ems.service.alias'),
        ])
        ->tag('console.command');

    $services->set(ManagedAliases::class)
        ->args([
            ref('logger'),
            ref('ems.service.alias'),
        ])
        ->tag('console.command');

    $services->set(ExtractAssetCommand::class)
        ->args([
            ref('logger'),
            ref('ems.service.asset_extractor'),
            ref('ems_common.storage.manager'),
        ])
        ->tag('console.command');

    $services->set('ems.contenttype.activate', ActivateContentTypeCommand::class)
        ->args([
            ref('logger'),
            ref('ems.service.contenttype'),
        ])
        ->tag('console.command');

    $services->set('ems.environment.create', CreateEnvironmentCommand::class)
        ->args([
            ref('logger'),
            ref('ems.service.environment'),
            ref('ems.service.data'),
        ])
        ->tag('console.command');

    $services->set('ems.revisions.unlock', UnlockRevisionsCommand::class)
        ->args([
            ref('logger'),
            ref('ems.service.data'),
            ref('ems.service.contenttype'),
        ])
        ->tag('console.command');

    $services->set('ems.job.run', JobCommand::class)
        ->args([
            ref('ems.service.job'),
            '%ems_core.date_time_format%',
        ])
        ->tag('console.command');
};
