<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use EMS\CoreBundle\Core\Dashboard\DashboardService;
use EMS\CoreBundle\Core\Dashboard\Services\AdvancedSearch;
use EMS\CoreBundle\Core\Dashboard\Services\Export;
use EMS\CoreBundle\Core\Dashboard\Services\RevisionTask;
use EMS\CoreBundle\Core\Dashboard\Services\Template;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->private();

    $services->set('ems_core.dashboard.dashboards', DashboardService::class)
        ->args([tagged_iterator('ems.dashboard', indexAttribute: 'id')]);

    $services->set('ems_core.dashboard.template', Template::class)
        ->args([
            service('twig'),
            '%ems_core.template_namespace%',
        ])
        ->tag('ems.dashboard', ['alias' => 'template']);

    $services->set('ems_core.dashboard.export', Export::class)
        ->args([
            service('twig'),
            '%ems_core.template_namespace%',
        ])
        ->tag('ems.dashboard', ['alias' => 'export']);

    $services->set('ems_core.dashboard.revision_task', RevisionTask::class)
        ->args([
            service('twig'),
            service('request_stack'),
            service('form.factory'),
            service('emsco.revision.task.manager'),
            service('emsco.data_table.factory'),
            '%ems_core.template_namespace%',
        ])
        ->tag('ems.dashboard', ['alias' => 'revision_task']);

    $services->set('ems_core.dashboard.advanced_search', AdvancedSearch::class)
        ->args([
            service('emsco.logger'),
            service('twig'),
            service('request_stack'),
            service('form.factory'),
            service('router'),
            service('ems_common.storage.manager'),
            service('ems.service.search'),
            service('ems_common.service.elastica'),
            service(ContentTypeRepository::class),
            service(EnvironmentRepository::class),
            '%ems_core.paging_size%',
            '%ems_core.template_namespace%',
        ])
        ->tag('ems.dashboard', ['alias' => 'advanced_search']);
};
