<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Command\ActivateContentTypeCommand;
use EMS\CoreBundle\Command\AlignManagedAliases;
use EMS\CoreBundle\Command\Asset\HeadAssetCommand;
use EMS\CoreBundle\Command\Asset\RefreshFileFieldCommand;
use EMS\CoreBundle\Command\Check\AliasesCheckCommand;
use EMS\CoreBundle\Command\CleanAssetCommand;
use EMS\CoreBundle\Command\CleanDeletedContentTypeCommand;
use EMS\CoreBundle\Command\ContentType\RecomputeCommand;
use EMS\CoreBundle\Command\ContentType\SwitchDefaultCommand;
use EMS\CoreBundle\Command\ContentType\TransformCommand;
use EMS\CoreBundle\Command\CreateEnvironmentCommand;
use EMS\CoreBundle\Command\DeleteOrphanIndexesCommand;
use EMS\CoreBundle\Command\DocumentCommand;
use EMS\CoreBundle\Command\EmailSubmissionsCommand;
use EMS\CoreBundle\Command\Environment\AbstractEnvironmentCommand;
use EMS\CoreBundle\Command\Environment\AlignCommand;
use EMS\CoreBundle\Command\Environment\UnpublishCommand;
use EMS\CoreBundle\Command\EnvironmentCommand;
use EMS\CoreBundle\Command\ExportDocumentsCommand;
use EMS\CoreBundle\Command\ExtractAssetCommand;
use EMS\CoreBundle\Command\IndexFileCommand;
use EMS\CoreBundle\Command\JobCommand;
use EMS\CoreBundle\Command\ManageAlias\AddEnvironmentIndexCommand;
use EMS\CoreBundle\Command\ManageAlias\CreateCommand;
use EMS\CoreBundle\Command\ManagedAliases;
use EMS\CoreBundle\Command\MediaLibrary\AbstractMediaLibraryCommand;
use EMS\CoreBundle\Command\MediaLibrary\MediaLibraryFolderDeleteCommand;
use EMS\CoreBundle\Command\MediaLibrary\MediaLibraryFolderMoveCommand;
use EMS\CoreBundle\Command\MediaLibrary\MediaLibraryFolderRenameCommand;
use EMS\CoreBundle\Command\MigrateCommand;
use EMS\CoreBundle\Command\Notification\BulkActionCommand;
use EMS\CoreBundle\Command\Notification\SendAllCommand;
use EMS\CoreBundle\Command\RebuildCommand;
use EMS\CoreBundle\Command\ReindexCommand;
use EMS\CoreBundle\Command\Release\CreateReleaseCommand;
use EMS\CoreBundle\Command\RemoveExpiredSubmissionsCommand;
use EMS\CoreBundle\Command\Revision\ArchiveCommand;
use EMS\CoreBundle\Command\Revision\CopyCommand;
use EMS\CoreBundle\Command\Revision\DeleteCommand;
use EMS\CoreBundle\Command\Revision\DiscardDraftCommand;
use EMS\CoreBundle\Command\Revision\LockAllCommand;
use EMS\CoreBundle\Command\Revision\LockCommand;
use EMS\CoreBundle\Command\Revision\Task\TaskCreateCommand;
use EMS\CoreBundle\Command\Revision\Task\TaskNotificationMailCommand;
use EMS\CoreBundle\Command\Revision\TimeMachineCommand;
use EMS\CoreBundle\Command\Revision\UnLockAllCommand;
use EMS\CoreBundle\Command\Revision\UnlockCommand;
use EMS\CoreBundle\Command\Submission\ExportCommand;
use EMS\CoreBundle\Command\Submission\GenerateDummySubmissionsCommand;
use EMS\CoreBundle\Command\SynchronizeAssetCommand;
use EMS\CoreBundle\Command\UpdateMetaFieldCommand;
use EMS\CoreBundle\Command\User\AbstractUserCommand;
use EMS\CoreBundle\Command\User\ActivateUserCommand;
use EMS\CoreBundle\Command\User\AddGroupToUserCommand;
use EMS\CoreBundle\Command\User\ChangePasswordCommand;
use EMS\CoreBundle\Command\User\CreateUserCommand;
use EMS\CoreBundle\Command\User\DeactivateUserCommand;
use EMS\CoreBundle\Command\User\DemoteUserCommand;
use EMS\CoreBundle\Command\User\PromoteUserCommand;
use EMS\CoreBundle\Command\User\RemoveGroupFromUserCommand;
use EMS\CoreBundle\Command\User\UpdateUserOptionCommand;
use EMS\CoreBundle\Command\Webhook\DispatchWebhookCommand;
use EMS\CoreBundle\Command\Xliff\ExtractCommand;
use EMS\CoreBundle\Command\Xliff\UpdateCommand;
use EMS\CoreBundle\Repository\RevisionRepository;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('ems.contenttype.lock', LockCommand::class)
        ->args([
            service('ems.service.contenttype'),
            service('ems_common.service.elastica'),
            service('ems.service.data'),
        ])
        ->tag('console.command');

    $services->set('ems.contenttype.transform', TransformCommand::class)
        ->args([
            service('ems_core.core_revision_search.revision_searcher'),
            service('ems.service.contenttype'),
            service('ems_core.core_content_type_transformer.content_transformer'),
        ])
        ->tag('console.command');

    $services->set('emsco.contenttype.create', SwitchDefaultCommand::class)
        ->args([
            service('ems.service.environment'),
            service('ems.service.contenttype'),
        ])
        ->tag('console.command');

    $services->set('ems_core.command_environment.abstract_environment_command', AbstractEnvironmentCommand::class)
        ->abstract()
        ->args([
            service('ems_core.core_revision_search.revision_searcher'),
            service('ems.service.environment'),
            service('ems.service.publish'),
        ]);

    $services->set('ems_core.command_environment.align_command', AlignCommand::class)
        ->parent('ems_core.command_environment.abstract_environment_command')
        ->tag('console.command');

    $services->set('ems_core.command_environment.unpublish_command', UnpublishCommand::class)
        ->parent('ems_core.command_environment.abstract_environment_command')
        ->tag('console.command');

    $services->set('ems.command.revision.archive', ArchiveCommand::class)
        ->args([
            service('ems_core.core_revision_search.revision_searcher'),
            service('ems.service.revision'),
            service('ems.service.contenttype'),
        ])
        ->tag('console.command');

    $services->set('ems.command.revision.copy', CopyCommand::class)
        ->args([
            service('ems_core.core_revision_search.revision_searcher'),
            service('ems.service.environment'),
            service('ems.service.revision'),
        ])
        ->tag('console.command');

    $services->set('ems.command.revision.delete', DeleteCommand::class)
        ->args([
            service('ems.service.revision'),
            service('ems.service.contenttype'),
            service('ems.service.publish'),
            service(ElasticaService::class),
        ])
        ->tag('console.command')
        ->tag('console.command', ['command' => 'ems:contenttype:delete']);

    $services->set('ems.command.revision.time_machine', TimeMachineCommand::class)
        ->args([
            service('ems.service.revision'),
            service('ems.service.data'),
            service('doctrine'),
            service('ems.service.index'),
        ])
        ->tag('console.command');

    $services->set('emsco.command.revision.task.create', TaskCreateCommand::class)
        ->args([
            service('ems_core.core_revision_search.revision_searcher'),
            service('ems.service.environment'),
            service('ems.service.user'),
            service('emsco.revision.task.manager'),
            '%ems_core.date_format%',
        ])
        ->tag('console.command');

    $services->set('emsco.command.revision.task.notification_mail', TaskNotificationMailCommand::class)
        ->args([
            service('emsco.revision.task.manager'),
            service('emsco.revision.task.mailer'),
        ])
        ->tag('console.command');

    $services->set('emsco.release.create', CreateReleaseCommand::class)
        ->args([
            service('ems.service.release'),
            service('ems.service.environment'),
            service('ems.service.contenttype'),
            service('ems.service.revision'),
            service('ems_common.service.elastica'),
        ])
        ->tag('console.command');

    $services->set('emsco.command.media_lib', AbstractMediaLibraryCommand::class)
        ->abstract()
        ->args([
            service('emsco.core.media_library.config_factory'),
            service('emsco.core.media_library'),
        ]);

    $services->set('emsco.command.media_lib.folder.delete', MediaLibraryFolderDeleteCommand::class)
        ->parent('emsco.command.media_lib')
        ->tag('console.command');

    $services->set('emsco.command.media_lib.folder.rename', MediaLibraryFolderRenameCommand::class)
        ->parent('emsco.command.media_lib')
        ->tag('console.command');

    $services->set('emsco.command.media_lib.folder.move', MediaLibraryFolderMoveCommand::class)
        ->parent('emsco.command.media_lib')
        ->tag('console.command');

    $services->set('ems.command.notification.bulk_action', BulkActionCommand::class)
        ->args([
            service('ems.service.notification'),
            service('ems.service.environment'),
            service('ems.service.contenttype'),
            service('ems_common.service.elastica'),
            service('ems.service.revision'),
        ])
        ->tag('console.command');

    $services->set('ems.command.notification.send', SendAllCommand::class)
        ->args([
            service('doctrine'),
            service('ems.service.notification'),
            '%ems_core.notification_pending_timeout%',
        ])
        ->tag('console.command');

    $services->set('ems.command.check.aliases', AliasesCheckCommand::class)
        ->args([
            service('ems.service.environment'),
            service('ems.service.alias'),
            service('ems.service.job'),
        ])
        ->tag('console.command');

    $services->set('ems.command.asset.head', HeadAssetCommand::class)
        ->args([
            service('logger'),
            service('ems.service.file'),
        ])
        ->tag('console.command');

    $services->set('emsco.command.asset.refresh_file_field', RefreshFileFieldCommand::class)
        ->args([
            service('ems.service.revision'),
            service('ems_common.storage.manager'),
            service('ems.service.file'),
            '%ems_core.image_max_size%',
        ])
        ->tag('console.command');

    $services->set('ems_core.command_user.abstract_user_command', AbstractUserCommand::class)
        ->abstract()
        ->args([service('emsco.manager.user')]);

    $services->set('ems.command.activate_user', ActivateUserCommand::class)
        ->parent('ems_core.command_user.abstract_user_command')
        ->tag('console.command')
        ->tag('console.command', ['command' => 'fos:user:activate']);

    $services->set('ems.command.change_password', ChangePasswordCommand::class)
        ->parent('ems_core.command_user.abstract_user_command')
        ->tag('console.command')
        ->tag('console.command', ['command' => 'fos:user:change-password']);

    $services->set('ems.command.create_user', CreateUserCommand::class)
        ->parent('ems_core.command_user.abstract_user_command')
        ->tag('console.command')
        ->tag('console.command', ['command' => 'fos:user:create']);

    $services->set('ems.command.deactivate_user', DeactivateUserCommand::class)
        ->parent('ems_core.command_user.abstract_user_command')
        ->tag('console.command')
        ->tag('console.command', ['command' => 'fos:user:deactivate']);

    $services->set('ems.command.demote_user', DemoteUserCommand::class)
        ->parent('ems_core.command_user.abstract_user_command')
        ->tag('console.command')
        ->tag('console.command', ['command' => 'fos:user:demote']);

    $services->set('ems.command.promote_user', PromoteUserCommand::class)
        ->parent('ems_core.command_user.abstract_user_command')
        ->tag('console.command')
        ->tag('console.command', ['command' => 'fos:user:promote']);

    $services->set('ems.command.add_group_to_user', AddGroupToUserCommand::class)
        ->parent('ems_core.command_user.abstract_user_command')
        ->args([service('ems.group.manager')])
        ->tag('console.command');

    $services->set('ems.command.remove_group_from_user', RemoveGroupFromUserCommand::class)
        ->parent('ems_core.command_user.abstract_user_command')
        ->args([service('ems.group.manager')])
        ->tag('console.command');

    $services->set('ems.command.update_user_option', UpdateUserOptionCommand::class)
        ->parent('ems_core.command_user.abstract_user_command')
        ->tag('console.command');

    $services->set('ems.command.xliff.extract', ExtractCommand::class)
        ->args([
            service('ems.service.contenttype'),
            service('ems.service.environment'),
            service('ems_common.service.elastica'),
            service('ems.service.internationalization.xliff'),
            service('ems_common.twig.runtime.asset'),
            service('ems_core.core_mail.mailer_service'),
            service('ems_common.storage.manager'),
            '%ems_core.default_bulk_size%',
        ])
        ->tag('console.command');

    $services->set('ems.command.xliff.update', UpdateCommand::class)
        ->args([
            service('ems.service.environment'),
            service('ems.service.internationalization.xliff'),
            service('ems.service.publish'),
            service('ems.service.revision'),
            service('ems_common.storage.manager'),
            service('ems_common.twig.runtime.asset'),
        ])
        ->tag('console.command');

    $services->set('ems.command.revision.discard-drafts', DiscardDraftCommand::class)
        ->args([
            service('ems.service.data'),
            service('ems.service.revision'),
        ])
        ->tag('console.command');

    $services->set('ems.contenttype.migrate', MigrateCommand::class)
        ->args([
            service('doctrine'),
            service('ems_common.service.elastica'),
            service('ems.service.document'),
        ])
        ->tag('console.command');

    $services->set('ems.make.document', DocumentCommand::class)
        ->args([
            service('ems.service.contenttype'),
            service('ems.service.document'),
            service('ems.service.data'),
            '%ems_core.default_bulk_size%',
        ])
        ->tag('console.command');

    $services->set('ems.contenttype.export', ExportDocumentsCommand::class)
        ->args([
            service('logger'),
            service('ems.service.template'),
            service('ems.service.data'),
            service('ems.service.contenttype'),
            service('ems.service.environment'),
            service('ems_common.twig.runtime.asset'),
            service('ems_common.service.elastica'),
            service('ems_common.storage.manager'),
            '%ems_core.instance_id%',
        ])
        ->tag('console.command');

    $services->set('ems.environment.rebuild', RebuildCommand::class)
        ->args([
            service('doctrine'),
            service('logger'),
            service('ems.service.contenttype'),
            service('ems.service.environment'),
            service('ems.environment.reindex'),
            service('ems_common.service.elastica'),
            service('ems.service.mapping'),
            service('ems.service.alias'),
            '%ems_core.instance_id%',
            '%ems_core.default_bulk_size%',
        ])
        ->tag('console.command');

    $services->set('ems.delete.orphans', DeleteOrphanIndexesCommand::class)
        ->args([service('ems.service.index')])
        ->tag('console.command');

    $services->set('ems.environment.recompute', RecomputeCommand::class)
        ->args([
            service('ems.service.data'),
            service('doctrine'),
            service('form.factory'),
            service('ems.service.publish'),
            service('logger'),
            service('ems.service.contenttype'),
            service(RevisionRepository::class),
            service('ems.service.index'),
            service('ems.service.search'),
        ])
        ->tag('console.command');

    $services->set('ems.environment.removeexpiredsubmissions', RemoveExpiredSubmissionsCommand::class)
        ->args([
            service('ems.form_submission'),
            service('logger'),
        ])
        ->tag('console.command');

    $services->set('ems.environment.emailsubmissions', EmailSubmissionsCommand::class)
        ->args([
            service('ems.form_submission'),
            service('logger'),
            service('ems_core.core_mail.mailer_service'),
        ])
        ->tag('console.command');

    $services->set('ems.submission.export', ExportCommand::class)
        ->args([
            service('emsco.submission.exporter'),
            service('ems.config.resolver'),
        ])
        ->tag('console.command');

    $services->set('ems.submission.generate-dummies', GenerateDummySubmissionsCommand::class)
        ->args([service('ems.form_submission')])
        ->tag('console.command');

    $services->set('ems.environment.updatemetafield', UpdateMetaFieldCommand::class)
        ->args([
            service('doctrine'),
            service('logger'),
            service('ems.service.data'),
        ])
        ->tag('console.command');

    $services->set('ems.environment.reindex', ReindexCommand::class)
        ->args([
            service('doctrine'),
            service('logger'),
            service('ems.service.mapping'),
            service('service_container'),
            service('ems.service.data'),
            service('ems.elasticsearch.bulker'),
            '%ems_core.default_bulk_size%',
        ])
        ->tag('console.command');

    $services->set('ems.contenttype.clean', CleanDeletedContentTypeCommand::class)
        ->args([
            service('doctrine'),
            service('logger'),
            service('ems.service.mapping'),
            service('service_container'),
        ])
        ->tag('console.command');

    $services->set('ems.revisions.index-file-fields', IndexFileCommand::class)
        ->args([
            service('logger'),
            service('doctrine'),
            service('ems.service.contenttype'),
            service('ems.service.asset_extractor'),
            service('ems.service.file'),
        ])
        ->tag('console.command');

    $services->set('ems.environment.list', EnvironmentCommand::class)
        ->args([service('ems.service.environment')])
        ->tag('console.command');

    $services->set(SynchronizeAssetCommand::class)
        ->args([
            service('logger'),
            service('doctrine'),
            service('ems.service.contenttype'),
            service('ems.service.asset_extractor'),
            service('ems.service.file'),
        ])
        ->tag('console.command');

    $services->set(CleanAssetCommand::class)
        ->args([
            service('logger'),
            service('doctrine'),
            service('ems.service.file'),
        ])
        ->tag('console.command');

    $services->set(AlignManagedAliases::class)
        ->args([
            service('logger'),
            service('ems.service.alias'),
        ])
        ->tag('console.command');

    $services->set(ManagedAliases::class)
        ->args([
            service('logger'),
            service('ems.service.alias'),
        ])
        ->tag('console.command');

    $services->set(ExtractAssetCommand::class)
        ->args([
            service('logger'),
            service('ems.service.asset_extractor'),
            service('ems_common.storage.manager'),
        ])
        ->tag('console.command');

    $services->set('ems.contenttype.activate', ActivateContentTypeCommand::class)
        ->args([
            service('logger'),
            service('ems.service.contenttype'),
        ])
        ->tag('console.command');

    $services->set('ems.environment.create', CreateEnvironmentCommand::class)
        ->args([
            service('logger'),
            service('ems.service.environment'),
            service('ems.service.data'),
        ])
        ->tag('console.command');

    $services->set('ems.revisions.lock_all', LockAllCommand::class)
        ->args([service('ems.service.data')])
        ->tag('console.command');

    $services->set('ems.revisions.unlock', UnlockCommand::class)
        ->args([
            service('ems.service.data'),
            service('ems.service.contenttype'),
        ])
        ->tag('console.command');

    $services->set('ems.revisions.unlock_all', UnLockAllCommand::class)
        ->args([service('ems.service.data')])
        ->tag('console.command');

    $services->set('ems.job.run', JobCommand::class)
        ->args([
            service('ems.service.job'),
            service('ems.service.release'),
            '%ems_core.date_time_format%',
            '%ems_core.clean_jobs_time_string%',
        ])
        ->tag('console.command');

    $services->set('ems.manage_alias.create', CreateCommand::class)
        ->args([service('ems.managed_alias.manager')])
        ->tag('console.command');

    $services->set('ems.manage_alias.add_environment', AddEnvironmentIndexCommand::class)
        ->args([
            service('ems.managed_alias.manager'),
            service('ems.service.environment'),
            service('ems.service.index'),
        ])
        ->tag('console.command');

    $services->set(DispatchWebhookCommand::class)
        ->args([service('emsco.service.webhook')])
        ->tag('console.command');
};
