<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use EMS\CoreBundle\DataTable\Type\ChannelDataTableType;
use EMS\CoreBundle\DataTable\Type\ContentType\ContentTypeActionDataTableType;
use EMS\CoreBundle\DataTable\Type\ContentType\ContentTypeDataTableType;
use EMS\CoreBundle\DataTable\Type\ContentType\ContentTypeUnreferencedDataTableType;
use EMS\CoreBundle\DataTable\Type\ContentType\ContentTypeViewDataTableType;
use EMS\CoreBundle\DataTable\Type\DashboardDataTableType;
use EMS\CoreBundle\DataTable\Type\Environment\EnvironmentDataTableType;
use EMS\CoreBundle\DataTable\Type\Environment\EnvironmentManagedAliasDataTableType;
use EMS\CoreBundle\DataTable\Type\Environment\EnvironmentOrphanIndexDataTableType;
use EMS\CoreBundle\DataTable\Type\Environment\EnvironmentUnreferencedAliasDataTableType;
use EMS\CoreBundle\DataTable\Type\FormDataTableType;
use EMS\CoreBundle\DataTable\Type\FormSubmissionDataTableType;
use EMS\CoreBundle\DataTable\Type\GroupDataTableType;
use EMS\CoreBundle\DataTable\Type\I18nDataTableType;
use EMS\CoreBundle\DataTable\Type\Job\JobDataTableType;
use EMS\CoreBundle\DataTable\Type\Job\JobScheduleDataTableType;
use EMS\CoreBundle\DataTable\Type\LogDataTableType;
use EMS\CoreBundle\DataTable\Type\Mapping\AnalyzerDataTableType;
use EMS\CoreBundle\DataTable\Type\Mapping\FilterDataTableType;
use EMS\CoreBundle\DataTable\Type\QuerySearchDataTableType;
use EMS\CoreBundle\DataTable\Type\Release\ReleaseOverviewDataTableType;
use EMS\CoreBundle\DataTable\Type\Release\ReleasePickDataTableType;
use EMS\CoreBundle\DataTable\Type\Release\ReleaseRevisionDataTableType;
use EMS\CoreBundle\DataTable\Type\Release\ReleaseRevisionsPublishDataTableType;
use EMS\CoreBundle\DataTable\Type\Release\ReleaseRevisionsUnpublishDataTableType;
use EMS\CoreBundle\DataTable\Type\Revision\RevisionAuditDataTableType;
use EMS\CoreBundle\DataTable\Type\Revision\RevisionDraftsDataTableType;
use EMS\CoreBundle\DataTable\Type\Revision\RevisionTasksDataTableType;
use EMS\CoreBundle\DataTable\Type\Revision\RevisionTrashDataTableType;
use EMS\CoreBundle\DataTable\Type\UploadedAsset\UploadedAssetAdminDataTableType;
use EMS\CoreBundle\DataTable\Type\UploadedAsset\UploadedAssetDataTableType;
use EMS\CoreBundle\DataTable\Type\UserDataTableType;
use EMS\CoreBundle\DataTable\Type\Wysiwyg\WysiwygProfileDataTableType;
use EMS\CoreBundle\DataTable\Type\Wysiwyg\WysiwygStylesSetDataTableType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\I18nService;
use EMS\CoreBundle\Service\JobService;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->private();

    $services->set('emsco.data_table.content_type', ContentTypeDataTableType::class)
        ->args([
            service(ContentTypeRepository::class),
            service(ContentTypeService::class),
            '%ems_core.template_namespace%',
        ])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.content_type_unreferenced', ContentTypeUnreferencedDataTableType::class)
        ->args([
            service(ContentTypeService::class),
            '%ems_core.template_namespace%',
        ])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.content_type.action', ContentTypeActionDataTableType::class)
        ->args([
            service('ems.service.action'),
            service(ContentTypeService::class),
        ])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.content_type.view', ContentTypeViewDataTableType::class)
        ->args([
            service('ems.view.manager'),
            service(ContentTypeService::class),
            '%ems_core.template_namespace%',
        ])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.environment', EnvironmentDataTableType::class)
        ->args([
            service(EnvironmentService::class),
            '%ems_core.template_namespace%',
        ])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.environment_managed_alias', EnvironmentManagedAliasDataTableType::class)
        ->args([
            service('ems.service.alias'),
            '%ems_core.template_namespace%',
        ])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.environment_orphan_index', EnvironmentOrphanIndexDataTableType::class)
        ->args([service('ems.service.alias')])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.environment_unreferenced_alias', EnvironmentUnreferencedAliasDataTableType::class)
        ->args([
            service('ems.service.alias'),
            '%ems_core.template_namespace%',
        ])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.mapping.analyzer', AnalyzerDataTableType::class)
        ->args([service('emsco.helper.analyzer')])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.mapping.filter', FilterDataTableType::class)
        ->args([service('emsco.helper.filter')])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.release.publish', ReleaseRevisionsPublishDataTableType::class)
        ->args([
            service('ems.service.release_revision'),
            service('ems.service.release'),
            '%ems_core.template_namespace%',
        ])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.release.unpublish', ReleaseRevisionsUnpublishDataTableType::class)
        ->args([
            service('ems.service.release'),
            service('ems.service.revision'),
            service('ems_common.service.elastica'),
            service(ContentTypeService::class),
            '%ems_core.template_namespace%',
        ])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.release.overview', ReleaseOverviewDataTableType::class)
        ->args([
            service('ems.service.release'),
            '%ems_core.template_namespace%',
        ])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.release.pick', ReleasePickDataTableType::class)
        ->args([
            service('ems.service.release'),
            service('ems.service.revision'),
            '%ems_core.template_namespace%',
        ])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.release.revision', ReleaseRevisionDataTableType::class)
        ->args([
            service('ems.service.release_revision'),
            service('ems.service.release'),
            '%ems_core.template_namespace%',
        ])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.revision.audit', RevisionAuditDataTableType::class)
        ->args([
            service('ems.log.manager'),
            service('ems.service.revision'),
        ])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.revision.drafts', RevisionDraftsDataTableType::class)
        ->args([
            service(RevisionRepository::class),
            service('security.authorization_checker'),
            service(ContentTypeService::class),
            service('ems.service.user'),
        ])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.revision.tasks', RevisionTasksDataTableType::class)
        ->args([
            service('emsco.revision.task.data_table.query_service'),
            service('ems.repository.task'),
        ])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.revision_trash', RevisionTrashDataTableType::class)
        ->args([
            service(RevisionRepository::class),
            service('ems.service.user'),
            service(ContentTypeService::class),
            service('security.authorization_checker'),
        ])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.channel', ChannelDataTableType::class)
        ->args([
            service('ems.service.channel'),
            '%ems_core.template_namespace%',
        ])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.wysiwygProfile', WysiwygProfileDataTableType::class)
        ->args([service('ems.service.wysiwyg_profile')])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.wysiwygStylesSet', WysiwygStylesSetDataTableType::class)
        ->args([service('ems.service.wysiwyg_styles_set')])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.dashboard', DashboardDataTableType::class)
        ->args([
            service('ems.dashboard.manager'),
            '%ems_core.template_namespace%',
        ])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.form', FormDataTableType::class)
        ->args([service('ems.form.manager')])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.group', GroupDataTableType::class)
        ->args([service('ems.group.manager')])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.form_submission', FormSubmissionDataTableType::class)
        ->args([service('ems.form_submission')])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.i18n', I18nDataTableType::class)
        ->args([
            service(I18nService::class),
            '%ems_core.template_namespace%',
        ])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.job', JobDataTableType::class)
        ->args([
            service(JobService::class),
            '%ems_core.template_namespace%',
        ])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.job_schedule', JobScheduleDataTableType::class)
        ->args([service('ems.schedule.manager')])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.log', LogDataTableType::class)
        ->args([service('ems.log.manager')])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.query_search', QuerySearchDataTableType::class)
        ->args([service('ems.service.query_search')])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.uploaded_asset', UploadedAssetDataTableType::class)
        ->args([
            service('ems.repository.uploaded_asset_repository'),
            service('router'),
        ])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.uploaded_asset.admin', UploadedAssetAdminDataTableType::class)
        ->args([service('ems.service.file')])
        ->tag('emsco.datatable');

    $services->set('emsco.data_table.user', UserDataTableType::class)
        ->args([
            service('ems.service.user'),
            '%ems_core.circles_object%',
            '%ems_core.group_feature%',
        ])
        ->tag('emsco.datatable');
};
