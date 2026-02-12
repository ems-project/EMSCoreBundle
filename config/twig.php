<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use EMS\CoreBundle\Core\Revision\Json\JsonMenuRenderer;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Twig\Components\JsonMenuNestedComponent;
use EMS\CoreBundle\Twig\Components\MediaLibraryComponent;
use EMS\CoreBundle\Twig\ContentTypeExtension;
use EMS\CoreBundle\Twig\CoreExtension;
use EMS\CoreBundle\Twig\DataExtractorExtension;
use EMS\CoreBundle\Twig\DatatableExtension;
use EMS\CoreBundle\Twig\EnvironmentExtension;
use EMS\CoreBundle\Twig\FormExtension;
use EMS\CoreBundle\Twig\I18nExtension;
use EMS\CoreBundle\Twig\JobExtension;
use EMS\CoreBundle\Twig\RevisionExtension;
use EMS\CoreBundle\Twig\UserExtension;
use EMS\CoreBundle\Twig\WysiwygExtension;
use Twig\Extension\StringLoaderExtension;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->private();

    $services->set('emsco.twig_extension.string_loader', StringLoaderExtension::class)->tag('twig.extension');

    $services->set('emsco.twig_extension.content_type', ContentTypeExtension::class)
        ->args([service(ContentTypeService::class)])
        ->tag('twig.attribute_extension')
        ->tag('twig.runtime');

    $services->set('emsco.twig_extension.core', CoreExtension::class)
        ->args([
            service('ems_core.core_mail.mailer_service'),
            service(JsonMenuRenderer::class),
            service('logger'),
            service('event_dispatcher'),
            service('doctrine'),
            service('security.authorization_checker'),
            service('ems.service.revision'),
            service('ems.service.contenttype'),
            service('router'),
            service('twig'),
            service('ems.form.factories.objectChoiceListFactory'),
            service('ems.service.file'),
            service('ems_common.service.elastica'),
            service('ems.service.search'),
            service('ems.twig_extension.asset'),
            '%ems_core.asset_config%',
        ])
        ->tag('twig.attribute_extension')
        ->tag('twig.runtime');

    $services->set('emsco.twig_extension.data_extractor', DataExtractorExtension::class)
        ->args([service('ems.service.asset_extractor')])
        ->tag('twig.attribute_extension')
        ->tag('twig.runtime');

    $services->set('emsco.twig_extension.datatable', DatatableExtension::class)
        ->args([
            service('ems.service.datatable'),
            service('twig'),
            '%ems_core.template_namespace%',
        ])
        ->tag('twig.attribute_extension')
        ->tag('twig.runtime');

    $services->set('emsco.twig_extension.environment', EnvironmentExtension::class)
        ->args([service('ems.service.environment')])
        ->tag('twig.attribute_extension')
        ->tag('twig.runtime');

    $services->set('emsco.twig_extension.form', FormExtension::class)
        ->args([
            service('ems.form.manager'),
            service('ems.service.data'),
            service('ems.service.revision'),
            service('form.factory'),
            service('request_stack'),
        ])
        ->tag('twig.attribute_extension')
        ->tag('twig.runtime');

    $services->set('emsco.twig_extension.i18n', I18nExtension::class)
        ->args([
            service('ems.service.i18n'),
            service('emsco.manager.user'),
        ])
        ->tag('twig.attribute_extension')
        ->tag('twig.runtime');

    $services->set('emsco.twig_extension.job', JobExtension::class)
        ->args([
            service(JobService::class),
            service('emsco.manager.user'),
            service('router'),
        ])
        ->tag('twig.attribute_extension')
        ->tag('twig.runtime');

    $services->set('emsco.twig_extension.revision', RevisionExtension::class)
        ->args([service('ems.service.revision')])
        ->tag('twig.attribute_extension')
        ->tag('twig.runtime');

    $services->set('emsco.twig_extension.user', UserExtension::class)
        ->args([
            service('ems.service.user'),
        ])
        ->tag('twig.attribute_extension')
        ->tag('twig.runtime');

    $services->set('emsco.twig_extension.wysiwyg', WysiwygExtension::class)
        ->args([
            service('ems.service.wysiwyg_styles_set'),
            service('emsco.manager.user'),
            service('router'),
            service('ems.dashboard.manager'),
        ])
        ->tag('twig.attribute_extension')
        ->tag('twig.runtime');

    $services->set('emsco.twig_components.json_menu_nested', JsonMenuNestedComponent::class)
        ->args([
            service('emsco.core.json_menu_nested.config_factory'),
            service('emsco.core.json_menu_nested.template_factory'),
        ])
        ->tag('twig.component', ['key' => 'json_menu_nested', 'template' => '@%ems_core.template_namespace%/components/json_menu_nested/component.html.twig']);

    $services->set('emsco.twig_components.media_library', MediaLibraryComponent::class)
        ->args([
            service('emsco.core.media_library.config_factory'),
            service('emsco.core.media_library.template_factory'),
        ])
        ->tag('twig.component', ['key' => 'media_library', 'template' => '@%ems_core.template_namespace%/components/media_library/component.html.twig']);
};
