<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use EMS\CoreBundle\Core\ContentType\ViewTypes;
use EMS\CoreBundle\Form\Field\ViewTypePickerType;
use EMS\CoreBundle\Form\View\CalendarViewType;
use EMS\CoreBundle\Form\View\CriteriaViewType;
use EMS\CoreBundle\Form\View\DataLinkViewType;
use EMS\CoreBundle\Form\View\ExportViewType;
use EMS\CoreBundle\Form\View\GalleryViewType;
use EMS\CoreBundle\Form\View\HierarchicalViewType;
use EMS\CoreBundle\Form\View\ImporterViewType;
use EMS\CoreBundle\Form\View\KeywordsViewType;
use EMS\CoreBundle\Form\View\ReportViewType;
use EMS\CoreBundle\Form\View\SorterViewType;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->public();

    $services->alias(ViewTypes::class, 'ems.content_type.view_types');

    $services->set('ems.content_type.view_types', ViewTypes::class)
        ->args([tagged_iterator('ems.form.viewtype', indexAttribute: 'id')]);

    $services->set('ems.form.field.viewtypepickertype', ViewTypePickerType::class)
        ->args([service('ems.content_type.view_types')])
        ->tag('form.type');

    $services->set('ems.view.data_link', DataLinkViewType::class)
        ->args([
            service('form.factory'),
            service('twig'),
            service('logger'),
            '%ems_core.template_namespace%',
        ])
        ->tag('ems.form.viewtype', ['alias' => 'data_link'])
        ->tag('form.type');

    $services->set('ems.view.keywords', KeywordsViewType::class)
        ->args([
            service('form.factory'),
            service('twig'),
            service('ems_common.service.elastica'),
            service('logger'),
            '%ems_core.template_namespace%',
        ])
        ->tag('ems.form.viewtype', ['alias' => 'keywords'])
        ->tag('form.type');

    $services->set('ems.view.criteria', CriteriaViewType::class)
        ->args([
            service('form.factory'),
            service('twig'),
            service('logger'),
            service('router'),
            '%ems_core.template_namespace%',
        ])
        ->tag('ems.form.viewtype', ['alias' => 'criteria'])
        ->tag('form.type');

    $services->set('ems.view.export', ExportViewType::class)
        ->args([
            service('form.factory'),
            service('twig'),
            service('ems_common.service.elastica'),
            service('logger'),
            service('ems_common.pdf.printer.dom'),
            '%ems_core.template_namespace%',
        ])
        ->tag('ems.form.viewtype', ['alias' => 'export'])
        ->tag('form.type');

    $services->set('ems.view.sorter', SorterViewType::class)
        ->args([
            service('form.factory'),
            service('twig'),
            service('ems.service.mapping'),
            service('ems_common.service.elastica'),
            service('logger'),
            service('ems.service.data'),
            service('router'),
            '%ems_core.template_namespace%',
        ])
        ->tag('ems.form.viewtype', ['alias' => 'sorter'])
        ->tag('form.type');

    $services->set('ems.view.hierarchical', HierarchicalViewType::class)
        ->args([
            service('form.factory'),
            service('twig'),
            service('ems.service.search'),
            service('ems.service.mapping'),
            service('logger'),
            service('ems.service.data'),
            service('router'),
            service('ems.service.contenttype'),
            '%ems_core.template_namespace%',
        ])
        ->tag('ems.form.viewtype', ['alias' => 'hierarchical'])
        ->tag('form.type');

    $services->set('ems.view.report', ReportViewType::class)
        ->args([
            service('form.factory'),
            service('twig'),
            service('ems_common.service.elastica'),
            service('logger'),
            '%ems_core.template_namespace%',
        ])
        ->tag('ems.form.viewtype', ['alias' => 'report'])
        ->tag('form.type');

    $services->set('ems.view.calendar', CalendarViewType::class)
        ->args([
            service('form.factory'),
            service('twig'),
            service('logger'),
            '%ems_core.template_namespace%',
        ])
        ->tag('ems.form.viewtype', ['alias' => 'calendar'])
        ->tag('form.type');

    $services->set('ems.view.gallery', GalleryViewType::class)
        ->args([
            service('form.factory'),
            service('twig'),
            service('ems_common.service.elastica'),
            service('logger'),
            service('ems.service.search'),
            '%ems_core.template_namespace%',
        ])
        ->tag('ems.form.viewtype', ['alias' => 'gallery'])
        ->tag('form.type');

    $services->set('ems.view.importer', ImporterViewType::class)
        ->args([
            service('form.factory'),
            service('twig'),
            service('logger'),
            service('ems.service.file'),
            service('ems.service.job'),
            service('security.token_storage'),
            service('router'),
            '%ems_core.template_namespace%',
        ])
        ->tag('ems.form.viewtype', ['alias' => 'importer'])
        ->tag('form.type');
};
