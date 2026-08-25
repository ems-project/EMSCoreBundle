<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use EMS\CommonBundle\Contracts\Spreadsheet\SpreadsheetGeneratorServiceInterface;
use EMS\CoreBundle\Controller\ActionController;
use EMS\CoreBundle\Controller\Admin\AnalyzerController;
use EMS\CoreBundle\Controller\Admin\EnvironmentController;
use EMS\CoreBundle\Controller\Admin\FilterController;
use EMS\CoreBundle\Controller\Admin\I18nController;
use EMS\CoreBundle\Controller\Admin\QuerySearchController;
use EMS\CoreBundle\Controller\Admin\ScheduleController;
use EMS\CoreBundle\Controller\Admin\WysiwygController;
use EMS\CoreBundle\Controller\Api\Admin\DocumentationController;
use EMS\CoreBundle\Controller\Api\Admin\EntitiesController;
use EMS\CoreBundle\Controller\Api\Admin\InfoController;
use EMS\CoreBundle\Controller\Api\Admin\MetaController;
use EMS\CoreBundle\Controller\Api\AuthTokenLoginController;
use EMS\CoreBundle\Controller\Api\File\ExtractDataController;
use EMS\CoreBundle\Controller\Api\Form\VerificationController;
use EMS\CoreBundle\Controller\Api\JobApiController;
use EMS\CoreBundle\Controller\Api\WebhookSubscriptionController;
use EMS\CoreBundle\Controller\ChannelController;
use EMS\CoreBundle\Controller\Component\JsonMenuNestedController;
use EMS\CoreBundle\Controller\Component\MediaLibraryController;
use EMS\CoreBundle\Controller\ContentManagement\AssetController;
use EMS\CoreBundle\Controller\ContentManagement\ContentTypeController;
use EMS\CoreBundle\Controller\ContentManagement\CrudController;
use EMS\CoreBundle\Controller\ContentManagement\DataController;
use EMS\CoreBundle\Controller\ContentManagement\DatatableController;
use EMS\CoreBundle\Controller\ContentManagement\FileController;
use EMS\CoreBundle\Controller\ContentManagement\JobController;
use EMS\CoreBundle\Controller\ContentManagement\ManagedAliasController;
use EMS\CoreBundle\Controller\ContentManagement\PublishController;
use EMS\CoreBundle\Controller\ContentManagement\ReleaseController;
use EMS\CoreBundle\Controller\ContentManagement\ViewController;
use EMS\CoreBundle\Controller\Dashboard\DashboardBrowserController;
use EMS\CoreBundle\Controller\DashboardController;
use EMS\CoreBundle\Controller\DefaultController;
use EMS\CoreBundle\Controller\ElasticsearchController;
use EMS\CoreBundle\Controller\Form\FormController;
use EMS\CoreBundle\Controller\Form\SubmissionController;
use EMS\CoreBundle\Controller\Log\LogController;
use EMS\CoreBundle\Controller\MercureController;
use EMS\CoreBundle\Controller\NotificationController;
use EMS\CoreBundle\Controller\Revision\Action\ActionImportController;
use EMS\CoreBundle\Controller\Revision\DetailController;
use EMS\CoreBundle\Controller\Revision\EditController;
use EMS\CoreBundle\Controller\Revision\TaskController;
use EMS\CoreBundle\Controller\Revision\TrashController;
use EMS\CoreBundle\Controller\SearchController;
use EMS\CoreBundle\Controller\TwigElementsController;
use EMS\CoreBundle\Controller\UploadedFileController;
use EMS\CoreBundle\Controller\UploadedFileWysiwygController;
use EMS\CoreBundle\Controller\User\GroupController;
use EMS\CoreBundle\Controller\User\LoginController;
use EMS\CoreBundle\Controller\User\ProfileController;
use EMS\CoreBundle\Controller\User\ResettingController;
use EMS\CoreBundle\Controller\UserController;
use EMS\CoreBundle\Controller\Views\CalendarController;
use EMS\CoreBundle\Controller\Views\CriteriaController;
use EMS\CoreBundle\Controller\Views\HierarchicalController;
use EMS\CoreBundle\Controller\Webhook\WebhookController;
use EMS\CoreBundle\Controller\Wysiwyg\AjaxPasteController;
use EMS\CoreBundle\Controller\Wysiwyg\ModalController;
use EMS\CoreBundle\Controller\Wysiwyg\StylesetController;
use EMS\CoreBundle\Core\Revision\Json\JsonMenuRenderer;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\ManagedAliasRepository;
use EMS\CoreBundle\Repository\NotificationRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\TemplateRepository;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set(ActionController::class)
        ->public()
        ->args([service('emsco.core_action.action_revision_service')])
        ->tag('controller.service_arguments');

    $services->set(AnalyzerController::class)
        ->public()
        ->args([
            service('emsco.helper.analyzer'),
            service('emsco.data_table.factory'),
            service('emsco.logger'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(\EMS\CoreBundle\Controller\Admin\ChannelController::class)
        ->public()
        ->args([
            service('emsco.logger'),
            service('ems.service.channel'),
            service('emsco.data_table.factory'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(\EMS\CoreBundle\Controller\Admin\DashboardController::class)
        ->public()
        ->args([
            service('emsco.logger'),
            service('ems.dashboard.manager'),
            service('emsco.data_table.factory'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(EnvironmentController::class)
        ->public()
        ->args([
            service('emsco.logger'),
            service('ems.service.environment'),
            service('ems.service.contenttype'),
            service('ems.service.index'),
            service('ems.service.mapping'),
            service('ems.service.job'),
            service('emsco.data_table.factory'),
            service('form.factory'),
            '%ems_core.circles_object%',
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber');

    $services->set(\EMS\CoreBundle\Controller\Admin\ElasticSearchController::class)
        ->public()
        ->args([
            service('ems.service.index'),
            service('ems.service.alias'),
            service(EnvironmentService::class),
            service('emsco.data_table.factory'),
            service('emsco.logger'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber');

    $services->set(FilterController::class)
        ->public()
        ->args([
            service('emsco.helper.filter'),
            service('emsco.data_table.factory'),
            service('emsco.logger'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(I18nController::class)
        ->public()
        ->args([
            service('ems.service.i18n'),
            service('emsco.data_table.factory'),
            service('emsco.logger'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(QuerySearchController::class)
        ->public()
        ->args([
            service('emsco.logger'),
            service('ems.service.query_search'),
            service('emsco.data_table.factory'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(ScheduleController::class)
        ->public()
        ->args([
            service('ems.schedule.manager'),
            service('emsco.data_table.factory'),
            service('emsco.logger'),
            service('ems_core.core_ui.flash_message_logger'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(WysiwygController::class)
        ->public()
        ->args([
            service('ems.service.wysiwyg_profile'),
            service('ems.service.wysiwyg_styles_set'),
            service('emsco.data_table.factory'),
            service('form.factory'),
            service('emsco.logger'),
            service('translator'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(EntitiesController::class)
        ->args([
            service('emsco.helper.entities'),
            service('emsco.logger'),
        ])
        ->tag('controller.service_arguments');

    $services->set(MetaController::class)
        ->args([
            service('ems.service.contenttype'),
            service('ems.service.revision'),
            service(EnvironmentService::class),
        ])
        ->tag('controller.service_arguments');

    $services->set(InfoController::class)
        ->args([service('ems_common.composer.info')])
        ->tag('controller.service_arguments');

    $services->set(DocumentationController::class)
        ->args([
            '%ems_core.template_namespace%',
            service('router'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(ExtractDataController::class)
        ->args([service('ems.service.asset_extractor')])
        ->tag('controller.service_arguments');

    $services->set(\EMS\CoreBundle\Controller\Api\Form\SubmissionController::class)
        ->public()
        ->args([
            service('ems.form_submission'),
            service('logger'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(VerificationController::class)
        ->public()
        ->args([
            service('ems.form_verification'),
            service('logger'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(\EMS\CoreBundle\Controller\Api\Search\SearchController::class)
        ->args([service('ems_common.service.elastica')])
        ->tag('controller.service_arguments');

    $services->set(AuthTokenLoginController::class);

    $services->set(JobApiController::class)
        ->args([service('ems.service.job')])
        ->tag('controller.service_arguments');

    $services->set(\EMS\CoreBundle\Controller\Api\UserController::class)
        ->args([
            service('emsco.manager.user'),
            service('ems.group.manager'),
        ])
        ->tag('controller.service_arguments');

    $services->set(JsonMenuNestedController::class)
        ->public()
        ->args([
            service('emsco.core.json_menu_nested'),
            service(DataService::class),
            service('form.factory'),
            service('translator'),
        ])
        ->tag('controller.service_arguments');

    $services->set(MediaLibraryController::class)
        ->public()
        ->args([
            service('emsco.core.media_library'),
            service('ems_core.core_ui.ajax_service'),
            service('ems_core.core_ui.flash_message_logger'),
            service('translator'),
            service('form.factory'),
            '%ems_core.template_namespace%',
            '%ems_core.async.enabled%',
        ])
        ->tag('controller.service_arguments');

    $services->set(\EMS\CoreBundle\Controller\ContentManagement\ActionController::class)
        ->public()
        ->args([
            service('ems.service.action'),
            service('emsco.data_table.factory'),
            service('emsco.logger'),
            service(TemplateRepository::class),
            service('ems_core.core_ui.flash_message_logger'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(AssetController::class)
        ->public()
        ->args([
            service('ems_common.storage.processor'),
            '%ems_core.asset_config%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(ContentTypeController::class)
        ->public()
        ->args([
            service('ems.service.contenttype'),
            service('emsco.data_table.factory'),
            service('emsco.logger'),
            service('ems.service.mapping'),
            service('ems.form.field-type.manager'),
            service(EnvironmentRepository::class),
            service('ems.repository.field_type'),
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(CrudController::class)
        ->public()
        ->args([
            service('emsco.logger'),
            service('ems.service.user'),
            service('ems.service.data'),
            service('ems.service.contenttype'),
            service('ems_core.core_ui.flash_message_logger'),
            service('ems.service.revision'),
            service('ems_common.storage.manager'),
            service(EnvironmentService::class),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber');

    $services->set(DataController::class)
        ->public()
        ->args([
            service('logger'),
            service('ems.service.data'),
            service('ems.service.search'),
            service('ems.service.contenttype'),
            service('ems.service.environment'),
            service('ems.service.index'),
            service('translator'),
            service('ems.content_type.view_types'),
            service(ContentTypeRepository::class),
            service('ems.repository.search'),
            service(RevisionRepository::class),
            service('ems.service.action'),
            service('ems_core.core_ui.flash_message_logger'),
            service('ems.service.publish'),
            service(ContentTypeService::class),
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(DatatableController::class)
        ->public()
        ->args([
            service('ems.service.datatable'),
            service('emsco.data_table.factory'),
            service('ems_core.core_data_table.table_renderer'),
            service('ems_core.core_data_table.table_exporter'),
            service('security.token_storage'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(\EMS\CoreBundle\Controller\ContentManagement\EnvironmentController::class)
        ->public()
        ->args([
            service('emsco.logger'),
            service('ems.service.search'),
            service('ems.service.environment'),
            service('ems.service.contenttype'),
            service('security.authorization_checker'),
            service('ems.service.publish'),
            service(RevisionRepository::class),
            '%ems_core.paging_size%',
            '%ems_core.template_namespace%',
            service('emsco.data_table.factory'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber');

    $services->set(FileController::class)
        ->public()
        ->args([
            service('ems.service.file'),
            service('ems.service.asset_extractor'),
            service('emsco.logger'),
            service('ems_core.core_ui.flash_message_logger'),
            service('ems.twig_extension.asset'),
            '%ems_core.asset_config%',
            '%ems_core.theme_color%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber');

    $services->set(JobController::class)
        ->public()
        ->args([
            service('ems.service.job'),
            service('emsco.data_table.factory'),
            service('emsco.logger'),
            '%ems_core.trigger_job_from_web%',
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(ManagedAliasController::class)
        ->public()
        ->args([
            service('emsco.logger'),
            service('ems.service.alias'),
            service(ManagedAliasRepository::class),
            '%ems_core.instance_id%',
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(PublishController::class)
        ->public()
        ->args([
            service('ems.service.publish'),
            service('ems.service.job'),
            service('ems.service.environment'),
            service('ems.service.contenttype'),
            service('ems.service.search'),
            service('ems_common.service.elastica'),
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(ReleaseController::class)
        ->public()
        ->args([
            service('logger'),
            service('ems.service.release'),
            service('emsco.data_table.factory'),
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(ViewController::class)
        ->public()
        ->args([
            service('ems.view.manager'),
            service('emsco.data_table.factory'),
            service('emsco.logger'),
            service('ems_core.core_ui.flash_message_logger'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(DashboardBrowserController::class)
        ->public()
        ->args([
            service('ems.dashboard.manager'),
            service('twig'),
            '%ems_core.template_namespace%',
        ])
        ->tag('controller.service_arguments');

    $services->set(FormController::class)
        ->public()
        ->args([
            service('emsco.logger'),
            service('ems.form.manager'),
            service('ems.form.field-type.manager'),
            service('emsco.data_table.factory'),
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(GroupController::class)
        ->public()
        ->args([
            service('emsco.logger'),
            service('ems.group.manager'),
            service('emsco.data_table.factory'),
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(SubmissionController::class)
        ->public()
        ->args([
            service('ems.form_submission'),
            service('logger'),
            service(SpreadsheetGeneratorServiceInterface::class),
            service('emsco.data_table.factory'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(LogController::class)
        ->public()
        ->args([
            service('ems.log.manager'),
            service('emsco.data_table.factory'),
            service('emsco.logger'),
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(\EMS\CoreBundle\Controller\Revision\Action\ActionController::class)
        ->public()
        ->args([
            service(TemplateRepository::class),
            service(EnvironmentService::class),
            service('ems.service.search'),
            service('ems_common.pdf.printer.dom'),
            service('ems_common.service.spreadsheet_generator_service'),
            service('logger'),
            service('twig'),
            '%ems_core.template_namespace%',
        ]);

    $services->set(ActionImportController::class)
        ->public()
        ->args([
            service(TemplateRepository::class),
            service('ems_core.core_ui.ajax_service'),
            service('form.factory'),
            service('ems_common.file.reader'),
            service('ems.service.revision'),
            service('twig'),
            service('emsco.logger'),
            '%ems_core.template_namespace%',
        ]);

    $services->set(DetailController::class)
        ->public()
        ->args([
            service('ems.service.contenttype'),
            service('ems.service.data'),
            service('ems.service.revision'),
            service(RevisionRepository::class),
            service('ems_common.service.elastica'),
            service('ems.service.search'),
            service('emsco.data_table.factory'),
            service('logger'),
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(EditController::class)
        ->public()
        ->args([
            service('ems.service.data'),
            service('emsco.logger'),
            service('ems.service.publish'),
            service('ems.service.revision'),
            service('translator'),
            service('emsco.data_table.factory'),
            service(ContentTypeService::class),
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(\EMS\CoreBundle\Controller\Revision\JsonMenuNestedController::class)
        ->public()
        ->args([
            service('ems_core.core_ui.ajax_service'),
            service(JsonMenuRenderer::class),
            service('ems.service.revision'),
            service('ems.service.data'),
            service('ems.service.user'),
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(TaskController::class)
        ->public()
        ->args([
            service('emsco.revision.task.manager'),
            service('ems.service.revision'),
            service('ems_core.core_ui.ajax_service'),
            service('form.factory'),
            '%ems_core.date_format%',
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(TrashController::class)
        ->public()
        ->args([
            service('ems.service.data'),
            service('emsco.data_table.factory'),
            service('emsco.logger'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(\EMS\CoreBundle\Controller\Search\QuerySearchController::class)
        ->public()
        ->args([
            service('ems.service.query_search'),
            service(ElasticsearchController::class),
            service('ems_core.core_document.data_links_factory'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(LoginController::class)
        ->args([
            service('twig'),
            '%ems_core.template_namespace%',
        ])
        ->tag('controller.service_arguments');

    $services->set(ProfileController::class)
        ->public()
        ->args([
            service('emsco.manager.user'),
            service('emsco.logger'),
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber');

    $services->set(ResettingController::class)
        ->public()
        ->args([
            service('emsco.manager.user'),
            service('ems_core.security.authenticator'),
            service('emsco.logger'),
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber');

    $services->set(CalendarController::class)
        ->public()
        ->args([
            service('emsco.logger'),
            service('ems_common.service.elastica'),
            service('ems.service.data'),
            service('ems.service.search'),
            service('ems_core.core_ui.flash_message_logger'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(CriteriaController::class)
        ->public()
        ->args([
            service('emsco.logger'),
            service('ems_common.service.elastica'),
            service('ems.service.data'),
            service('ems.service.contenttype'),
            service('ems.form.factories.objectChoiceListFactory'),
            service('security.authorization_checker'),
            service('form.registry'),
            service('ems.repository.field_type'),
            service(RevisionRepository::class),
            service('ems_core.core_ui.flash_message_logger'),
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(HierarchicalController::class)
        ->public()
        ->args([
            service('ems.service.contenttype'),
            service('ems.service.search'),
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(AjaxPasteController::class)
        ->public()
        ->args([
            service('ems.service.wysiwyg_profile'),
            service('emsco.logger'),
        ])
        ->tag('controller.service_arguments');

    $services->set(ModalController::class)
        ->public()
        ->args([
            service('ems.service.revision'),
            service('twig'),
            service('ems_core.core_ui.flash_message_logger'),
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber');

    $services->set(StylesetController::class)
        ->public()
        ->args([
            service('ems.service.wysiwyg_styles_set'),
            service('ems_common.storage.manager'),
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(ChannelController::class)
        ->public()
        ->args([
            service('ems.service.channel'),
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber');

    $services->set(DashboardController::class)
        ->public()
        ->args([
            service('ems.dashboard.manager'),
            service('ems_core.dashboard.dashboards'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(DefaultController::class)
        ->public()
        ->call('setContainer')
        ->tag('container.service_subscriber');

    $services->set(ElasticsearchController::class)
        ->public()
        ->args([
            service('logger'),
            service('ems.service.index'),
            service('ems_common.service.elastica'),
            service('ems.service.data'),
            service('ems.service.asset_extractor'),
            service('ems.service.environment'),
            service('ems.service.contenttype'),
            service('ems.service.revision'),
            service('ems.service.search'),
            service('security.authorization_checker'),
            service('ems.service.job'),
            service('ems.service.aggregate_option'),
            service('ems.service.sort_option'),
            service('ems.dashboard.manager'),
            service(ContentTypeRepository::class),
            service('ems.repository.search'),
            service(EnvironmentRepository::class),
            service('translator'),
            service('serializer'),
            service('ems.repository.messenger_messages_repository'),
            '%ems_core.paging_size%',
            '%ems_core.health_check_allow_origin%',
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(MercureController::class)
        ->public()
        ->args([service('emsco.core_mercure.mercure_service')])
        ->tag('controller.service_arguments');

    $services->set(NotificationController::class)
        ->public()
        ->args([
            service('logger'),
            service('ems.service.publish'),
            service('ems.service.environment'),
            service('doctrine'),
            service('ems.service.notification'),
            service('ems.dashboard.manager'),
            service(NotificationRepository::class),
            service('ems_core.core_ui.flash_message_logger'),
            '%ems_core.paging_size%',
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(SearchController::class)
        ->public()
        ->args([
            service('ems.service.sort_option'),
            service('ems.service.aggregate_option'),
            service('ems.service.search_field_option'),
            service('translator'),
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(TwigElementsController::class)
        ->public()
        ->args([
            service('ems.service.asset_extractor'),
            service('ems_common.service.elastica'),
            service('ems.service.user'),
            service('ems.service.job'),
            service('ems.dashboard.manager'),
            service('ems.service.contenttype'),
            '%ems_core.template_namespace%',
            '%ems_core.group_feature%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(UploadedFileController::class)
        ->public()
        ->args([
            service('emsco.logger'),
            service('ems.service.file'),
            service('emsco.data_table.factory'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber');

    $services->set(UploadedFileWysiwygController::class)
        ->public()
        ->args([
            service('ems_core.core_ui.ajax_service'),
            service('emsco.data_table.factory'),
            '%ems_core.template_namespace%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(UserController::class)
        ->public()
        ->args([
            service('logger'),
            service(ContentTypeRepository::class),
            service('ems.service.user'),
            service('emsco.manager.user'),
            service('ems.group.manager'),
            service(SpreadsheetGeneratorServiceInterface::class),
            service('emsco.data_table.factory'),
            service('ems.repository.auth_token'),
            service('ems_core.core_ui.flash_message_logger'),
            '%ems_core.template_namespace%',
            service('emsco.core.content_type.field_type.service'),
            '%ems_core.date_time_format%',
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(\EMS\CoreBundle\Controller\Api\Data\PublishController::class)
        ->args([
            service('ems.service.publish'),
            service('ems.service.revision'),
            service('ems.service.contenttype'),
            service('ems.service.environment'),
        ])
        ->tag('controller.service_arguments');

    $services->set(WebhookSubscriptionController::class)
        ->args([
            service('emsco.service.webhook_subscription'),
            service('event_dispatcher'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');

    $services->set(WebhookController::class)
        ->args([
            service('emsco.logger'),
            service('emsco.webhook_subscription.manager'),
            service('emsco.data_table.factory'),
            service('emsco.service.webhook'),
        ])
        ->call('setContainer')
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments');
};
