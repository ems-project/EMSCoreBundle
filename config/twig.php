<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use EMS\CommonBundle\Twig\AssetRuntime;
use EMS\CoreBundle\Core\Revision\Wysiwyg\WysiwygRuntime;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Twig\AppExtension;
use EMS\CoreBundle\Twig\Components\JsonMenuNestedComponent;
use EMS\CoreBundle\Twig\Components\MediaLibraryComponent;
use EMS\CoreBundle\Twig\ContentTypeRuntime;
use EMS\CoreBundle\Twig\CoreRuntime;
use EMS\CoreBundle\Twig\DataExtractorRuntime;
use EMS\CoreBundle\Twig\DatatableRuntime;
use EMS\CoreBundle\Twig\EnvironmentRuntime;
use EMS\CoreBundle\Twig\FormRuntime;
use EMS\CoreBundle\Twig\I18nRuntime;
use EMS\CoreBundle\Twig\JobRuntime;
use EMS\CoreBundle\Twig\RevisionRuntime;
use EMS\CoreBundle\Twig\UserRuntime;
use Twig\Extension\StringLoaderExtension;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->private();

    $services->alias('app.twig_extension', AppExtension::class)
        ->public();

    $services->set(AppExtension::class)
        ->args([
            service('doctrine'),
            service('security.authorization_checker'),
            service('ems.service.user'),
            service('ems.service.revision'),
            service('ems.service.contenttype'),
            service('router'),
            service('twig'),
            service('ems.form.factories.objectChoiceListFactory'),
            service('logger'),
            service('form.factory'),
            service('ems.service.file'),
            service('ems_common.twig.runtime.request'),
            service('ems_core.core_mail.mailer_service'),
            service('ems_common.service.elastica'),
            service('ems.service.search'),
            service(AssetRuntime::class),
            '%ems_core.asset_config%',
        ])
        ->tag('twig.extension', ['priority' => -2000]);

    $services->alias('app.twig_extension_revision', RevisionRuntime::class);

    $services->set(RevisionRuntime::class)
        ->args([service('ems.service.revision')])
        ->tag('twig.runtime', ['priority' => -2000]);

    $services->alias('app.twig_extension_user', UserRuntime::class);

    $services->set(UserRuntime::class)
        ->args([service('ems.repository.user')])
        ->tag('twig.runtime', ['priority' => -2000]);

    $services->set('app.twig.extension.stringloader', StringLoaderExtension::class)
        ->tag('twig.extension');

    $services->alias('ems.twig.runtime.datatable', DatatableRuntime::class);

    $services->set(DatatableRuntime::class)
        ->args([
            service('ems.service.datatable'),
            service('twig'),
            '%ems_core.template_namespace%',
        ])
        ->tag('twig.runtime');

    $services->set('emsco.twig.content_type_runtime', ContentTypeRuntime::class)
        ->args([service(ContentTypeService::class)])
        ->tag('twig.runtime');

    $services->set('emsco.twig.environment_runtime', EnvironmentRuntime::class)
        ->args([service('ems.service.environment')])
        ->tag('twig.runtime');

    $services->set('ems_core.twig.core_runtime', CoreRuntime::class)
        ->args([
            service('logger'),
            service('event_dispatcher'),
        ])
        ->tag('twig.runtime');

    $services->set('ems_core.core_revision_wysiwyg.wysiwyg_runtime', WysiwygRuntime::class)
        ->args([
            service('ems.service.wysiwyg_styles_set'),
            service('emsco.manager.user'),
            service('router'),
            service('ems.dashboard.manager'),
        ])
        ->tag('twig.runtime');

    $services->set('ems.twig.runtime.i18n', I18nRuntime::class)
        ->args([
            service('ems.service.i18n'),
            service('emsco.manager.user'),
        ])
        ->tag('twig.runtime');

    $services->set('ems.twig.runtime.job', JobRuntime::class)
        ->args([
            service(JobService::class),
            service('emsco.manager.user'),
            service('router'),
        ])
        ->tag('twig.runtime');

    $services->set('ems.twig.runtime.data_extractor', DataExtractorRuntime::class)
        ->args([service('ems.service.asset_extractor')])
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

    $services->set('ems.twig.runtime.form', FormRuntime::class)
        ->args([
            service('ems.form.manager'),
            service('ems.service.data'),
            service('ems.service.revision'),
            service('form.factory'),
            service('request_stack'),
        ])
        ->tag('twig.runtime');
};
