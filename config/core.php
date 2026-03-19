<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use EMS\CoreBundle\Core\Action\ActionRevisionService;
use EMS\CoreBundle\Core\Action\ActionService;
use EMS\CoreBundle\Core\Bridge\Core\CoreServiceBridge;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Config\JsonMenuNestedConfig;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Config\JsonMenuNestedConfigFactory;
use EMS\CoreBundle\Core\Component\JsonMenuNested\JsonMenuNestedService;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Template\JsonMenuNestedTemplateFactory;
use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfig;
use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfigFactory;
use EMS\CoreBundle\Core\Component\MediaLibrary\File\MediaLibraryFileFactory;
use EMS\CoreBundle\Core\Component\MediaLibrary\Folder\MediaLibraryFolderFactory;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryService;
use EMS\CoreBundle\Core\Component\MediaLibrary\Template\MediaLibraryTemplateFactory;
use EMS\CoreBundle\Core\Config\AbstractConfigFactory;
use EMS\CoreBundle\Core\Config\ConfigValueResolver;
use EMS\CoreBundle\Core\InlineEditor\InlineEditor;
use EMS\CoreBundle\Core\Metric\JobMetricCollector;
use EMS\CoreBundle\Repository\JobRepository;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Validator\Constraints\MediaLibrary\DocumentValidator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('emsco.core_action.action_service', ActionService::class)
        ->args([
            service('ems.repository.cache_action'),
            service('emsco.manager.user'),
            service('messenger.default_bus'),
            service('emsco.core_mercure.mercure_service'),
        ]);

    $services->set('emsco.core_action.action_revision_service', ActionRevisionService::class)
        ->args([
            service('emsco.core_action.action_service'),
            service('ems.service.revision'),
            service('emsco.core.content_type.field_type.service'),
            service('ems_common.ai.open_ai'),
        ]);

    $services->set('emsco.core_bridge.service', CoreServiceBridge::class)
        ->args([
            service(DataService::class),
            service('ems.service.revision'),
            service('ems_common.composer.info'),
            service(ContentTypeService::class),
            service('ems.service.file'),
            service('ems.service.user'),
        ]);

    $services->set('emsco.config.abstract_config_factory', AbstractConfigFactory::class)
        ->abstract()
        ->call('setStorageManager', [service('ems_common.storage.manager')]);

    $services->set('emsco.config.value_resolver', ConfigValueResolver::class)
        ->args([tagged_locator('emsco.config.factory', indexAttribute: 'config')])
        ->tag('controller.argument_value_resolver', ['name' => 'emsco.config', 'priority' => 150]);

    $services->set('emsco.core.json_menu_nested.config_factory', JsonMenuNestedConfigFactory::class)
        ->parent('emsco.config.abstract_config_factory')
        ->args([service('ems.service.revision')])
        ->tag('emsco.config.factory', ['config' => JsonMenuNestedConfig::class]);

    $services->set('emsco.core.json_menu_nested.template_factory', JsonMenuNestedTemplateFactory::class)
        ->args([
            service('twig'),
            '%ems_core.template_namespace%',
        ]);

    $services->set('emsco.core.json_menu_nested', JsonMenuNestedService::class)
        ->args([
            service('emsco.core.json_menu_nested.template_factory'),
            service('ems.service.revision'),
            service('ems.service.user'),
            service('ems_common.service.elastica'),
            service('request_stack'),
        ]);

    $services->set('emsco.core.media_library', MediaLibraryService::class)
        ->args([
            service('ems_common.service.elastica'),
            service('ems.service.revision'),
            service(DataService::class),
            service(JobService::class),
            service('emsco.core.media_library.config_factory'),
            service('emsco.core.media_library.template_factory'),
            service('emsco.core.media_library.file_factory'),
            service('emsco.core.media_library.folder_factory'),
        ]);

    $services->set('emsco.core.media_library.file_factory', MediaLibraryFileFactory::class)
        ->args([
            service('ems_common.service.elastica'),
            service('router'),
        ]);

    $services->set('emsco.core.media_library.folder_factory', MediaLibraryFolderFactory::class)
        ->args([service('ems_common.service.elastica')]);

    $services->set('emsco.core.media_library.config_factory', MediaLibraryConfigFactory::class)
        ->parent('emsco.config.abstract_config_factory')
        ->args([service(ContentTypeService::class)])
        ->tag('emsco.config.factory', ['config' => MediaLibraryConfig::class])
        ->call('setRequestStack', [service('request_stack')]);

    $services->set('emsco.core.media_library.template_factory', MediaLibraryTemplateFactory::class)
        ->args([
            service('twig'),
            '%ems_core.template_namespace%',
        ]);

    $services->set('emsco.core.media_library.validator.document', DocumentValidator::class)
        ->args([service('emsco.core.media_library')])
        ->tag('validator.constraint_validator');

    $services->set('emsco.metric.job_metric_collector', JobMetricCollector::class)
        ->args([service(JobRepository::class)])
        ->tag('ems.metric_collector');

    $services->set('emsco.core.inline_editor', InlineEditor::class)
        ->args([
            service('twig'),
            service(UrlGeneratorInterface::class),
            service('ems.service.revision'),
            service('ems.service.data'),
            service('ems.service.environment'),
            service('ems.service.user'),
        ]);
};
