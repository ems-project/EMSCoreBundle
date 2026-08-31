<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use EMS\ClientHelperBundle\Contracts\Environment\EnvironmentHelperInterface;
use EMS\CommonBundle\Contracts\Elasticsearch\QueryLoggerInterface;
use EMS\CommonBundle\Contracts\ExpressionServiceInterface;
use EMS\CommonBundle\Contracts\Spreadsheet\SpreadsheetGeneratorServiceInterface;
use EMS\CommonBundle\Elasticsearch\Client;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Core\ContentType\FieldType\FieldTypeService;
use EMS\CoreBundle\Core\ContentType\Transformer\ContentTransformer;
use EMS\CoreBundle\Core\ContentType\Transformer\ContentTransformers;
use EMS\CoreBundle\Core\ContentType\Transformer\HtmlAttributeTransformer;
use EMS\CoreBundle\Core\ContentType\Transformer\HtmlEmptyTransformer;
use EMS\CoreBundle\Core\ContentType\Transformer\HtmlRemoveNodeTransformer;
use EMS\CoreBundle\Core\ContentType\Transformer\HtmlUnwrapTransformer;
use EMS\CoreBundle\Core\Dashboard\DashboardManager;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\DataTable\TableExporter;
use EMS\CoreBundle\Core\DataTable\TableRenderer;
use EMS\CoreBundle\Core\DataTable\Type\DataTableTypeCollection;
use EMS\CoreBundle\Core\Document\DataLinksFactory;
use EMS\CoreBundle\Core\Entity\EntitiesHelper;
use EMS\CoreBundle\Core\Environment\EnvironmentPublisherFactory;
use EMS\CoreBundle\Core\Form\FieldTypeManager;
use EMS\CoreBundle\Core\Form\FormManager;
use EMS\CoreBundle\Core\Job\ScheduleManager;
use EMS\CoreBundle\Core\Log\LogManager;
use EMS\CoreBundle\Core\Mail\MailerService;
use EMS\CoreBundle\Core\ManagedAlias\ManagedAliasManager;
use EMS\CoreBundle\Core\Mapping\AnalyzerManager;
use EMS\CoreBundle\Core\Mapping\FilterManager;
use EMS\CoreBundle\Core\Mercure\MercureService;
use EMS\CoreBundle\Core\Messenger\Handler\ActionHandler;
use EMS\CoreBundle\Core\Messenger\Handler\JobHandler;
use EMS\CoreBundle\Core\Messenger\Handler\WebhookSubscriptionHandler;
use EMS\CoreBundle\Core\Messenger\Message\ActionMessage;
use EMS\CoreBundle\Core\Messenger\Message\JobMessage;
use EMS\CoreBundle\Core\Messenger\Message\WebhookSubscriberMessage;
use EMS\CoreBundle\Core\Messenger\Middleware\RestoreUserFromStampMiddleware;
use EMS\CoreBundle\Core\Messenger\Middleware\UserTokenStampMiddleware;
use EMS\CoreBundle\Core\Revision\Json\JsonMenuRenderer;
use EMS\CoreBundle\Core\Revision\Search\RevisionSearcher;
use EMS\CoreBundle\Core\Revision\Task\DataTable\TasksDataTableQueryService;
use EMS\CoreBundle\Core\Revision\Task\TaskEventSubscriber;
use EMS\CoreBundle\Core\Revision\Task\TaskMailer;
use EMS\CoreBundle\Core\Revision\Task\TaskManager;
use EMS\CoreBundle\Core\Submission\SubmissionExporter;
use EMS\CoreBundle\Core\UI\AjaxService;
use EMS\CoreBundle\Core\UI\FlashMessageLogger;
use EMS\CoreBundle\Core\User\GroupManager;
use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Core\View\ViewManager;
use EMS\CoreBundle\Core\Webhook\WebhookSubscriptionManager;
use EMS\CoreBundle\Elasticsearch\Bulker;
use EMS\CoreBundle\Elasticsearch\Indexer;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Event\RevisionFinalizeDraftEvent;
use EMS\CoreBundle\Event\RevisionNewDraftEvent;
use EMS\CoreBundle\Event\RevisionPublishEvent;
use EMS\CoreBundle\Event\RevisionUnpublishEvent;
use EMS\CoreBundle\Event\UpdateRevisionReferersEvent;
use EMS\CoreBundle\EventListener\AccessDeniedListener;
use EMS\CoreBundle\EventListener\EventsToWebhookSubscribers;
use EMS\CoreBundle\EventListener\InlineEditListener;
use EMS\CoreBundle\EventListener\LoginListener;
use EMS\CoreBundle\EventListener\PageListener;
use EMS\CoreBundle\EventListener\RequestListener;
use EMS\CoreBundle\EventListener\RevisionDoctrineListener;
use EMS\CoreBundle\EventListener\UserLocaleListener;
use EMS\CoreBundle\Form\Factory\ObjectChoiceListFactory;
use EMS\CoreBundle\Form\Revision\Task\RevisionTaskFiltersType;
use EMS\CoreBundle\Form\Revision\Task\RevisionTaskHandleType;
use EMS\CoreBundle\Mcp\ElasticmsMcpServerFactory;
use EMS\CoreBundle\Mcp\ElasticmsMcpToolAssetService;
use EMS\CoreBundle\Mcp\ElasticmsMcpToolCustomService;
use EMS\CoreBundle\Mcp\ElasticmsMcpToolDataService;
use EMS\CoreBundle\Mcp\ElasticmsMcpToolUserService;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\JobRepository;
use EMS\CoreBundle\Repository\ManagedAliasRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\ActionService;
use EMS\CoreBundle\Service\AggregateOptionService;
use EMS\CoreBundle\Service\AliasService;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\Channel\ChannelRegistrar;
use EMS\CoreBundle\Service\Channel\ChannelService;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\DatatableService;
use EMS\CoreBundle\Service\DocumentService;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\FileService;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;
use EMS\CoreBundle\Service\Form\Verification\FormVerificationService;
use EMS\CoreBundle\Service\I18nService;
use EMS\CoreBundle\Service\IndexService;
use EMS\CoreBundle\Service\Internationalization\XliffService;
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Service\Mapping;
use EMS\CoreBundle\Service\Mcp\McpToolService;
use EMS\CoreBundle\Service\NotificationService;
use EMS\CoreBundle\Service\ObjectChoiceCacheService;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\QuerySearchService;
use EMS\CoreBundle\Service\ReleaseRevisionService;
use EMS\CoreBundle\Service\ReleaseService;
use EMS\CoreBundle\Service\RestClientService;
use EMS\CoreBundle\Service\Revision\PostProcessingService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\CoreBundle\Service\SearchFieldOptionService;
use EMS\CoreBundle\Service\SearchService;
use EMS\CoreBundle\Service\SortOptionService;
use EMS\CoreBundle\Service\TemplateService;
use EMS\CoreBundle\Service\UserService;
use EMS\CoreBundle\Service\WebhookService;
use EMS\CoreBundle\Service\WebhookSubscriptionService;
use EMS\CoreBundle\Service\WysiwygProfileService;
use EMS\CoreBundle\Service\WysiwygStylesSetService;
use Mcp\Server;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->private();

    $services->set(Psr17Factory::class, Psr17Factory::class);

    $services->set(ServerRequestCreator::class)
        ->args([
            service(Psr17Factory::class),
            service(Psr17Factory::class),
            service(Psr17Factory::class),
            service(Psr17Factory::class),
        ]);

    $services->set('emsco.event_listener.access_denied_listener', AccessDeniedListener::class)
        ->args([
            service('router'),
            '%ems_core.security.firewall.core%',
        ])
        ->tag('kernel.event_subscriber');

    $services->set('emsco.event_listener.inline_editor', InlineEditListener::class)
        ->args([service('emsco.core.inline_editor')])
        ->tag('kernel.event_subscriber');

    $services->set('ems_core.event_listener.login_listener', LoginListener::class)
        ->args([service('emsco.manager.user')])
        ->tag('kernel.event_subscriber');

    $services->set('ems_core.event_listener.user_locale_listener', UserLocaleListener::class)
        ->args([
            service('security.token_storage'),
            service('translation.locale_switcher'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set('ems_core.event_listener.page_listener', PageListener::class)
        ->args([
            service('twig'),
            '%ems_core.template_namespace%',
        ])
        ->tag('kernel.event_subscriber');

    $services->set('ems.event_listener.request_listener', RequestListener::class)
        ->args([
            service('ems.service.channel.register'),
            service('twig'),
            service('doctrine'),
            service('logger'),
            service('router'),
        ])
        ->tag('kernel.event_listener', ['event' => 'kernel.exception', 'method' => 'onKernelException'])
        ->tag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'onKernelRequest', 'priority' => 110])
        ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onKernelResponse']);

    $services->set('emsco.event_listener.revision_listener', RevisionDoctrineListener::class)
        ->args([service('emsco.revision.task.manager')])
        ->tag('doctrine.orm.entity_listener', ['event' => 'preRemove', 'entity' => Revision::class, 'lazy' => true, 'entity_manager' => 'default', 'method' => 'preRemoveRevision'])
        ->tag('doctrine.orm.entity_listener', ['event' => 'postRemove', 'entity' => Revision::class, 'lazy' => true, 'entity_manager' => 'default', 'method' => 'postRemoveRevision']);

    $services->set('emsco.service.webhook', WebhookService::class)
        ->args([
            service('ems.repository.webhook_subscription'),
            service('messenger.default_bus'),
        ]);

    $services->set('emsco.event_listener.events_to_webhook_subscribers', EventsToWebhookSubscribers::class)
        ->args([
            service('emsco.service.webhook'),
            service('logger'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set('ems.dashboard.manager', DashboardManager::class)
        ->args([
            service('ems.repository.dashboard'),
            service('logger'),
            service('security.authorization_checker'),
        ])
        ->tag('emsco.entity.service', ['priority' => 50]);

    $services->set('ems.form.field-type.manager', FieldTypeManager::class)
        ->args([
            service('logger'),
            service('form.registry'),
        ]);

    $services->set('ems.form.manager', FormManager::class)
        ->args([
            service('ems.repository.form'),
            service('logger'),
        ])
        ->tag('emsco.entity.service', ['priority' => 70]);

    $services->set('ems.group.manager', GroupManager::class)
        ->args([
            service('ems.repository.group'),
            service('logger'),
        ])
        ->tag('emsco.entity.service', ['priority' => 70]);

    $services->set('ems.service.channel', ChannelService::class)
        ->args([
            service('ems.repository.channel'),
            service('logger'),
        ])
        ->tag('emsco.entity.service', ['priority' => 40]);

    $services->set('ems.service.channel.register', ChannelRegistrar::class)
        ->args([
            service('ems.repository.channel'),
            service(EnvironmentHelperInterface::class),
            service('logger'),
            service('ems.service.index'),
            '%ems_core.security.firewall.core%',
            '%ems_core.instance_id%',
        ]);

    $services->alias('ems.service.mcp_tool', McpToolService::class);

    $services->set(McpToolService::class)
        ->args([
            service('ems.repository.mcp_tool'),
            service('logger'),
        ])
        ->tag('emsco.entity.service', ['priority' => 40]);

    $services->set('emsco.data_table.type.collection', DataTableTypeCollection::class)
        ->args([tagged_iterator('emsco.datatable')]);

    $services->set('emsco.data_table.factory', DataTableFactory::class)
        ->args([
            service('emsco.data_table.type.collection'),
            service('router'),
            service(CacheItemPoolInterface::class),
            service('security.helper'),
            service('form.factory'),
            service('request_stack'),
            '%ems_core.template_namespace%',
        ]);

    $services->alias('ems.service.datatable', DatatableService::class);

    $services->set(DatatableService::class)
        ->args([
            service('logger'),
            service('router'),
            service('ems_common.service.elastica'),
            service('ems_common.storage.manager'),
            service('ems.service.environment'),
            '%ems_core.template_namespace%',
        ]);

    $services->set('ems_core.core_document.data_links_factory', DataLinksFactory::class)
        ->args([
            service('ems.service.search'),
            service('ems.service.contenttype'),
            service('ems.content_type.view_types'),
        ]);

    $services->set('emsco.core.content_type.field_type.service', FieldTypeService::class)
        ->args([service('ems.repository.field_type')]);

    $services->set('ems_core.core_content_type_transformer.content_transformer', ContentTransformer::class)
        ->args([
            service('ems_core.core_content_type_transformer.content_transformers'),
            service('ems.service.data'),
        ]);

    $services->set('ems_core.core_content_type_transformer.content_transformers', ContentTransformers::class);

    $services->set('ems_core.core_content_type_transformer.html_attribute_transformer', HtmlAttributeTransformer::class)
        ->tag('ems_core.content_type.transformer');

    $services->set('ems_core.core_content_type_transformer.html_empty_transformer', HtmlEmptyTransformer::class)
        ->tag('ems_core.content_type.transformer');

    $services->set('ems_core.core_content_type_transformer.html_remove_node_transformer', HtmlRemoveNodeTransformer::class)
        ->tag('ems_core.content_type.transformer');

    $services->set('ems_core.core_content_type_transformer.html_unwrap_transformer', HtmlUnwrapTransformer::class)
        ->tag('ems_core.content_type.transformer');

    $services->set('emsco.core_mercure.mercure_service', MercureService::class)
        ->args([
            service('mercure.hub.default'),
            service('emsco.manager.user'),
            '%ems_core.url_user%',
        ]);

    $services->alias('ems.service.query_search', QuerySearchService::class);

    $services->set(QuerySearchService::class)
        ->args([
            service('ems.service.contenttype'),
            service('ems.service.revision'),
            service('ems_common.service.elastica'),
            service('ems.repository.query_search'),
            service('logger'),
            service('ems.service.environment'),
        ])
        ->tag('emsco.entity.service', ['priority' => 30]);

    $services->alias('ems.service.internationalization.xliff', XliffService::class);

    $services->set(XliffService::class)
        ->args([
            service('logger'),
            service('ems.service.revision'),
            service('ems_common.service.elastica'),
        ]);

    $services->set('ems_core.core_ui.ajax_service', AjaxService::class)
        ->args([
            service('twig'),
            service('translator'),
        ]);

    $services->set('ems_core.core_revision_search.revision_searcher', RevisionSearcher::class)
        ->args([
            service('ems_common.service.elastica'),
            service(RevisionRepository::class),
            '%ems_core.default_bulk_size%',
        ]);

    $services->set('emsco.revision.task.mailer', TaskMailer::class)
        ->args([
            service('ems_core.core_mail.mailer_service'),
            service('emsco.revision.task.manager'),
            service('ems.service.user'),
            service('router.default'),
            service('translator'),
            '%ems_core.url_user%',
            '%ems_core.template_namespace%',
        ]);

    $services->set('emsco.revision.task.manager', TaskManager::class)
        ->args([
            service('ems.repository.task'),
            service(RevisionRepository::class),
            service('ems.service.data'),
            service('ems.service.user'),
            service('event_dispatcher'),
            service('logger'),
        ]);

    $services->set('emsco.revision.task.data_table.query_service', TasksDataTableQueryService::class)
        ->args([
            service('doctrine'),
            service('ems.service.user'),
            service('ems.service.revision'),
        ]);

    $services->set('emsco.revision.task.filter_type', RevisionTaskFiltersType::class)
        ->args([
            service(ContentTypeService::class),
            service(AuthorizationCheckerInterface::class),
        ])
        ->tag('form.type');

    $services->set('emsco.revision.task.handle_type', RevisionTaskHandleType::class)
        ->args([service('security.authorization_checker')])
        ->tag('form.type');

    $services->set('emsco.revision.task.event_subscriber', TaskEventSubscriber::class)
        ->args([
            service('emsco.revision.task.manager'),
            service('emsco.revision.task.mailer'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set('ems_core.core_mail.mailer_service', MailerService::class)
        ->args([
            service(MailerInterface::class),
            service('twig'),
            service('translator'),
            '%ems_core.from_email%',
            '%ems_core.name%',
        ]);

    $services->set('ems_core.core_data_table.table_renderer', TableRenderer::class)
        ->args([
            service('twig'),
            service('translator'),
            service(QueryLoggerInterface::class),
        ]);

    $services->set('ems_core.core_data_table.table_exporter', TableExporter::class)
        ->args([
            service('ems_core.core_data_table.table_renderer'),
            service(SpreadsheetGeneratorServiceInterface::class),
        ]);

    $services->alias('ems_core.service.uploaded-file', DatatableService::class);

    $services->set('ems_core.core_ui.flash_message_logger', FlashMessageLogger::class)
        ->args([
            service('request_stack'),
            service('translator'),
        ]);

    $services->set('ems.view.manager', ViewManager::class)
        ->args([
            service('ems.repository.view'),
            service('emsco.logger'),
        ]);

    $services->set('ems.schedule.manager', ScheduleManager::class)
        ->args([
            service('ems.repository.schedule'),
            service('logger'),
        ])
        ->tag('emsco.entity.service', ['priority' => 20]);

    $services->set(JsonMenuRenderer::class)
        ->args([
            service('twig'),
            service('security.authorization_checker'),
            service('router'),
            service(ContentTypeRepository::class),
            service('ems.service.revision'),
            '%ems_core.template_namespace%',
        ]);

    $services->set('ems.log.manager', LogManager::class)
        ->args([
            service('ems.repository.log'),
            service('logger'),
        ]);

    $services->set('emsco.helper.analyzer', AnalyzerManager::class)
        ->args([service('ems.repository.analyzer')])
        ->tag('emsco.entity.service', ['priority' => 120]);

    $services->set('emsco.helper.filter', FilterManager::class)
        ->args([service('ems.repository.filter')])
        ->tag('emsco.entity.service', ['priority' => 110]);

    $services->set('emsco.helper.entities', EntitiesHelper::class)
        ->args([tagged_iterator('emsco.entity.service')]);

    $services->set('emsco.manager.user', UserManager::class)
        ->args([
            service('security.token_storage'),
            service('ems_core.core_mail.mailer_service'),
            service('ems.repository.user'),
            service('security.user_password_hasher'),
            service('security.authorization_checker'),
            service('ems.repository.auth_token'),
            service('ems.service.wysiwyg_profile'),
            '%ems_core.template_namespace%',
        ]);

    $services->set('ems.service.mapping', Mapping::class)
        ->args([
            service('emsco.logger'),
            service(Client::class),
            service('ems.form.fieldtype.fieldtypetype'),
            service('ems.service.elasticsearch'),
            service('ems_common.service.elastica'),
            '%ems_core.instance_id%',
            '%ems_core.dynamic_mapping%',
        ]);

    $services->set(DataService::class, DataService::class)
        ->args([
            service('doctrine'),
            service('security.authorization_checker'),
            service('security.token_storage'),
            '%ems_core.lock_time%',
            service('ems_common.service.elastica'),
            service('ems.service.mapping'),
            '%ems_core.instance_id%',
            service('form.factory'),
            service('form.registry'),
            service('event_dispatcher'),
            service('ems.service.contenttype'),
            '%ems_core.private_key%',
            service('emsco.logger'),
            service('emsco.logger.audit'),
            service('ems_common.storage.manager'),
            service('twig'),
            service('ems.service.user'),
            service(RevisionRepository::class),
            service('ems.service.environment'),
            service('ems.service.search'),
            service('ems.service.index'),
            '%ems_core.pre_generated_ouuids%',
            service('ems.service.revision.post_processing'),
        ])
        ->tag('kernel.event_listener', ['event' => UpdateRevisionReferersEvent::class, 'method' => 'updateReferers', 'priority' => 0]);

    $services->set('ems.service.revision.post_processing', PostProcessingService::class)
        ->args([
            service('twig'),
            service('form.factory'),
            service('emsco.logger'),
        ]);

    $services->set('ems.service.revision', RevisionService::class)
        ->args([
            service('ems.service.data'),
            service('form.factory'),
            service('logger'),
            service('emsco.logger.audit'),
            service(RevisionRepository::class),
            service('ems.service.publish'),
            service(ContentTypeService::class),
            service(EnvironmentService::class),
            service('emsco.manager.user'),
            service(ExpressionServiceInterface::class),
            service('ems_common.service.elastica'),
            service('translator'),
        ]);

    $services->set('emsco.submission.exporter', SubmissionExporter::class)
        ->args([
            service('ems.form_submission'),
            service('ems_common.service.expression_service'),
            service(SpreadsheetGeneratorServiceInterface::class),
            service('ems_core.core_mail.mailer_service'),
            service('twig'),
            service(PropertyAccessorInterface::class),
        ]);

    $services->set('ems.service.alias', AliasService::class)
        ->args([
            service('logger'),
            service(Client::class),
            service(EnvironmentRepository::class),
            service(ManagedAliasRepository::class),
            service('ems_common.service.elastica'),
            service('event_dispatcher'),
        ]);

    $services->set('ems.service.index', IndexService::class)
        ->args([
            service('ems.service.alias'),
            service(Client::class),
            service('ems.service.contenttype'),
        ]);

    $services->set('ems.elasticsearch.bulker', Bulker::class)
        ->args([
            service(Client::class),
            service('logger'),
            service('ems.service.data'),
        ]);

    $services->set('ems.elasticsearch.indexer', Indexer::class)
        ->args([
            service('ems.service.index'),
            service('logger'),
            service('ems.service.mapping'),
            service('ems.service.alias'),
        ]);

    $services->set('emsco.environment.publisher_factory', EnvironmentPublisherFactory::class)
        ->args([
            service('twig'),
            service('ems.service.search'),
        ]);

    $services->set(EnvironmentService::class)
        ->args([
            service('doctrine'),
            service('ems.service.user'),
            service('security.authorization_checker'),
            service('logger'),
            service('ems_common.service.elastica'),
            service('ems.service.alias'),
            service('ems.repository.environment_revision'),
            '%ems_core.instance_id%',
        ])
        ->tag('emsco.entity.service', ['priority' => 80]);

    $services->set(ContentTypeService::class)
        ->args([
            service(ContentTypeRepository::class),
            service('doctrine'),
            service('emsco.logger'),
            service('ems.service.mapping'),
            service('ems_common.service.elastica'),
            service('ems.service.environment'),
            service('security.authorization_checker'),
            service(RevisionRepository::class),
            service('security.token_storage'),
            service('translator'),
            service('router.default'),
            '%ems_core.circles_object%',
        ])
        ->tag('emsco.entity.service', ['priority' => 60]);

    $services->set('ems.service.user', UserService::class)
        ->args([
            service('security.token_storage'),
            service('security.helper'),
            service('ems.repository.user'),
            service('ems.repository.search'),
            service('security.authorization_checker'),
            '%security.role_hierarchy.roles%',
        ]);

    $services->set('ems.service.wysiwyg_profile', WysiwygProfileService::class)
        ->args([
            service('ems.repository.wysiwyg_profile'),
            service('logger'),
        ])
        ->tag('emsco.entity.service', ['priority' => 110]);

    $services->set('ems.service.aggregate_option', AggregateOptionService::class)
        ->args([
            service('doctrine'),
            service('logger'),
            service('translator'),
        ]);

    $services->set('ems.service.sort_option', SortOptionService::class)
        ->args([
            service('doctrine'),
            service('logger'),
            service('translator'),
        ]);

    $services->set('ems.service.search_field_option', SearchFieldOptionService::class)
        ->args([
            service('doctrine'),
            service('logger'),
            service('translator'),
        ]);

    $services->set('ems.service.wysiwyg_styles_set', WysiwygStylesSetService::class)
        ->args([
            service('ems.repository.wysiwyg_style_set'),
            service('logger'),
        ])
        ->tag('emsco.entity.service', ['priority' => 100]);

    $services->set('ems.service.objectchoicecache', ObjectChoiceCacheService::class)
        ->args([
            service('logger'),
            service('ems.service.contenttype'),
            service('ems.service.revision'),
            service('security.authorization_checker'),
            service('security.token_storage'),
            service('ems_common.service.elastica'),
            service('ems.service.query_search'),
        ]);

    $services->set('ems.service.publish', PublishService::class)
        ->args([
            service('doctrine'),
            service('security.authorization_checker'),
            service('ems.service.index'),
            service('ems.service.contenttype'),
            service('ems.service.environment'),
            service('emsco.environment.publisher_factory'),
            service('ems.service.data'),
            service('ems.service.user'),
            service('event_dispatcher'),
            service('emsco.logger'),
            service('emsco.logger.audit'),
            service('ems.elasticsearch.bulker'),
            service('ems.repository.environment_revision'),
        ]);

    $services->set('ems.service.notification', NotificationService::class)
        ->args([
            service('doctrine'),
            service('ems.service.user'),
            service('logger'),
            service('ems.service.data'),
            service('ems_core.core_mail.mailer_service'),
            service('twig'),
        ])
        ->tag('kernel.event_listener', ['event' => RevisionNewDraftEvent::class, 'method' => 'newDraftEvent', 'priority' => 0])
        ->tag('kernel.event_listener', ['event' => RevisionFinalizeDraftEvent::class, 'method' => 'finalizeDraftEvent', 'priority' => 0])
        ->tag('kernel.event_listener', ['event' => RevisionPublishEvent::class, 'method' => 'publishEvent', 'priority' => 0])
        ->tag('kernel.event_listener', ['event' => RevisionUnpublishEvent::class, 'method' => 'unpublishEvent', 'priority' => 0]);

    $services->set(ElasticmsMcpToolUserService::class)
        ->args([
            service('ems.service.user'),
            service('logger'),
            service('emsco.logger.audit'),
        ]);

    $services->set(ElasticmsMcpToolAssetService::class)
        ->args([
            service('ems.service.user'),
            service('ems.service.file'),
            service('logger'),
            service('emsco.logger.audit'),
        ]);

    $services->set(ElasticmsMcpToolDataService::class)
        ->args([
            service('ems.service.user'),
            service(ContentTypeService::class),
            service('ems.service.revision'),
            service('ems.service.data'),
            service('form.registry'),
            service('security.authorization_checker'),
            service('logger'),
            service('emsco.logger.audit'),
            service('router'),
        ]);

    $services->set(ElasticmsMcpToolCustomService::class)
        ->args([
            service('ems.service.user'),
            service('ems.service.mcp_tool'),
            service('twig'),
            service('logger'),
            service('emsco.logger.audit'),
        ]);

    $services->set(ElasticmsMcpServerFactory::class)
        ->args([
            service('service_container'),
            '%kernel.cache_dir%',
            service('logger'),
            service(ElasticmsMcpToolUserService::class),
            service(ElasticmsMcpToolDataService::class),
            service(ElasticmsMcpToolAssetService::class),
            service(ElasticmsMcpToolCustomService::class),
        ]);

    $services->set('emsco.mcp.server', Server::class)
        ->factory([service(ElasticmsMcpServerFactory::class), 'create']);

    $services->alias(Server::class, 'emsco.mcp.server');

    $services->set(I18nService::class, I18nService::class)
        ->args([service('ems.repository.i18n')])
        ->tag('emsco.entity.service', ['priority' => 130]);

    $services->set('ems.service.rest_client', RestClientService::class);

    $services->set('ems.service.asset_extractor', AssetExtractorService::class)
        ->args([
            service('ems.service.rest_client'),
            service('logger'),
            service('doctrine'),
            service('ems.service.file'),
            '%ems_core.tika_server%',
            '%kernel.project_dir%',
            '%ems_core.tika_download_url%',
            '%ems_core.tika_max_content%',
        ])
        ->tag('kernel.cache_warmer');

    $services->set('ems.service.search', SearchService::class)
        ->args([
            service('doctrine'),
            service('ems.service.mapping'),
            service('ems_common.service.elastica'),
            service('ems.service.environment'),
            service('ems.service.contenttype'),
            service('ems.repository.search'),
            service(RevisionRepository::class),
        ]);

    $services->set('ems.service.file', FileService::class)
        ->args([
            service('doctrine'),
            service('ems_common.storage.manager'),
            service('ems_common.storage.processor'),
            service('ems.repository.uploaded_asset_repository'),
        ]);

    $services->set('ems.service.elasticsearch', ElasticsearchService::class)
        ->args([service('ems_common.service.elastica')]);

    $services->set('ems.service.template', TemplateService::class)
        ->args([service('twig')]);

    $services->set(JobService::class, JobService::class)
        ->args([
            service('doctrine'),
            service('kernel'),
            service('logger'),
            service(JobRepository::class),
            service('ems.schedule.manager'),
            service('ems.metric.collector'),
            service('security.token_storage'),
            service('messenger.default_bus'),
            '%ems_core.async.enabled%',
            '%ems_core.job_clean_time%',
        ])
        ->tag('emsco.entity.service', ['priority' => 10]);

    $services->set('ems.service.document', DocumentService::class)
        ->args([
            service('doctrine'),
            service('ems.service.data'),
            service('form.factory'),
            service('ems.elasticsearch.bulker'),
            service(RevisionRepository::class),
        ]);

    $services->set('ems.service.action', ActionService::class)
        ->args([
            service('ems.repository.template'),
            service('logger'),
            service('ems.service.search'),
            service('twig'),
            service(JobService::class),
        ]);

    $services->set('ems.service.release', ReleaseService::class)
        ->args([
            service('ems.repository.release'),
            service('ems.service.contenttype'),
            service('ems.service.data'),
            service('ems.service.release_revision'),
            service('ems.service.publish'),
            service('logger'),
        ]);

    $services->set('ems.service.release_revision', ReleaseRevisionService::class)
        ->args([
            service('ems.repository.release_revision'),
            service(RevisionRepository::class),
            service('logger'),
            service('ems.service.contenttype'),
        ])
        ->tag('kernel.event_listener', ['event' => RevisionFinalizeDraftEvent::class, 'method' => 'finalizeDraftEvent', 'priority' => 0]);

    $services->set('emsco.service.webhook_subscription', WebhookSubscriptionService::class)
        ->args([service('ems.repository.webhook_subscription')]);

    $services->set('ems.form_submission', FormSubmissionService::class)
        ->args([
            service('ems.repository.form_submission'),
            service('ems.repository.form_submission_file'),
            service('twig'),
            service('request_stack'),
            service('translator'),
            '%ems_core.template_namespace%',
        ]);

    $services->set('ems.form_verification', FormVerificationService::class)
        ->args([service('ems.repository.form_verification')]);

    $services->set('ems.managed_alias.manager', ManagedAliasManager::class)
        ->args([
            service(ManagedAliasRepository::class),
            '%ems_core.instance_id%',
        ])
        ->tag('emsco.entity.service', ['priority' => 90]);

    $services->set('emsco.core_messenger_handler.action_handler', ActionHandler::class)
        ->args([
            service('emsco.core_action.action_service'),
            service('ems_common.ai.open_ai'),
            service('emsco.core_mercure.mercure_service'),
        ])
        ->tag('messenger.message_handler', ['handles' => ActionMessage::class]);

    $services->set('ems_core.core_messenger_handler.job_handler', JobHandler::class)
        ->args([
            service('logger'),
            service('ems.service.job'),
            service('ems_common.runner.manager'),
        ])
        ->tag('messenger.message_handler', ['handles' => JobMessage::class]);

    $services->set('emsco.messenger.middleware.user_token_stamp', UserTokenStampMiddleware::class)
        ->args([service('security.token_storage')])
        ->tag('messenger.middleware');

    $services->set('emsco.messenger.middleware.restore_user_from_stamp', RestoreUserFromStampMiddleware::class)
        ->args([service('security.token_storage')])
        ->tag('messenger.middleware');

    $services->set('emsco.core_messenger_handler.webhook_subscription_handler', WebhookSubscriptionHandler::class)
        ->args([
            service('ems.repository.webhook_subscription'),
            service('http_client'),
        ])
        ->tag('messenger.message_handler', ['handles' => WebhookSubscriberMessage::class]);

    $services->set('emsco.webhook_subscription.manager', WebhookSubscriptionManager::class)
        ->args([
            service('ems.repository.webhook_subscription'),
        ]);

    $services->alias('ems.service.data', DataService::class)
        ->public();

    $services->alias('ems.service.environment', EnvironmentService::class);

    $services->alias('ems.service.contenttype', ContentTypeService::class);

    $services->alias('ems.service.i18n', I18nService::class);

    $services->alias('ems.service.job', JobService::class);

    $services->alias(AliasService::class, 'ems.service.alias');

    $services->alias(IndexService::class, 'ems.service.index');

    $services->alias(ActionService::class, 'ems.service.action');

    $services->alias(ReleaseService::class, 'ems.service.release');

    $services->alias(ReleaseRevisionService::class, 'ems.service.release_revision');

    $services->alias(Bulker::class, 'ems.elasticsearch.bulker');

    $services->alias(Indexer::class, 'ems.elasticsearch.indexer');

    $services->alias(FileService::class, 'ems.service.file');

    $services->alias(ElasticsearchService::class, 'ems.service.elasticsearch');

    $services->alias(AssetExtractorService::class, 'ems.service.asset_extractor');

    $services->alias(UserService::class, 'ems.service.user');

    $services->alias(Encoder::class, 'ems_common.text.encoder');

    $services->alias(SearchService::class, 'ems.service.search');

    $services->alias(PublishService::class, 'ems.service.publish');

    $services->alias(AggregateOptionService::class, 'ems.service.aggregate_option');

    $services->alias(Mapping::class, 'ems.service.mapping');

    $services->alias(SortOptionService::class, 'ems.service.sort_option');

    $services->alias(WysiwygProfileService::class, 'ems.service.wysiwyg_profile');

    $services->alias(SearchFieldOptionService::class, 'ems.service.search_field_option');

    $services->alias(WysiwygStylesSetService::class, 'ems.service.wysiwyg_styles_set');

    $services->alias(NotificationService::class, 'ems.service.notification');

    $services->alias(ObjectChoiceListFactory::class, 'ems.form.factories.objectChoiceListFactory');

    $services->alias(TaskManager::class, 'emsco.revision.task.manager');
};
