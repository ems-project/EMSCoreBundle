<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ContentManagement\CrudController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('emsco_interface_document_create', '/{interface}/data/{name}/create/{ouuid}')
        ->controller([CrudController::class, 'create'])
        ->methods(['POST'])
        ->format('json')
        ->defaults([
            'ouuid' => null,
            'interface' => 'api',
        ])
        ->requirements([
            'interface' => 'api|json',
        ])
        ->options(['openapi' => true]);

    $routes->add('emsco_interface_document_init_draft', '/{interface}/data/{name}/init-draft/{uuid}')
        ->controller([CrudController::class, 'initDraft'])
        ->methods(['POST'])
        ->format('json')
        ->defaults([
            'ouuid' => null,
            'interface' => 'api',
        ])
        ->requirements([
            'interface' => 'api|json',
        ]);

    $routes->add('emsco_interface_document_draft', '/{interface}/data/{name}/draft/{ouuid}')
        ->controller([CrudController::class, 'create'])
        ->methods(['POST'])
        ->format('json')
        ->defaults([
            'ouuid' => null,
            'interface' => 'api',
        ])
        ->requirements([
            'interface' => 'api|json',
        ]);

    $routes->add('emsco_interface_document_auto_save', '/{interface}/data/{name}/auto-save/{revisionId}')
        ->controller([CrudController::class, 'autoSave'])
        ->methods(['POST'])
        ->format('json')
        ->defaults([
            'interface' => 'api',
        ])
        ->requirements([
            'interface' => 'api|json',
        ])
        ->options(['openapi' => true]);

    $routes->add('emsco_interface_document_get_short', '/{interface}/data/{name}/{ouuid}')
        ->controller([CrudController::class, 'get'])
        ->methods(['GET'])
        ->format('json')
        ->defaults([
            'ouuid' => null,
            'interface' => 'api',
        ])
        ->requirements([
            'interface' => 'api|json',
        ])
        ->options(['openapi' => true]);

    $routes->add('emsco_interface_document_get', '/{interface}/data/{name}/get/{ouuid}')
        ->controller([CrudController::class, 'get'])
        ->methods(['GET'])
        ->format('json')
        ->defaults([
            'ouuid' => null,
            'interface' => 'api',
        ])
        ->requirements([
            'interface' => 'api|json',
        ]);

    $routes->add('emsco_interface_document_get_draft', '/{interface}/data/{name}/draft/{revisionId}')
        ->controller([CrudController::class, 'getDraft'])
        ->methods(['GET'])
        ->format('json')
        ->defaults([
            'interface' => 'api',
        ])
        ->requirements([
            'interface' => 'api|json',
        ]);

    $routes->add('emsco_interface_document_finalize', '/{interface}/data/{name}/finalize/{id}')
        ->controller([CrudController::class, 'finalize'])
        ->methods(['POST'])
        ->format('json')
        ->defaults([
            'interface' => 'api',
        ])
        ->requirements([
            'interface' => 'api|json',
        ]);

    $routes->add('emsco_interface_document_discard', '/{interface}/data/{name}/discard/{id}')
        ->controller([CrudController::class, 'discard'])
        ->methods(['POST'])
        ->format('json')
        ->defaults([
            'interface' => 'api',
        ])
        ->requirements([
            'interface' => 'api|json',
        ])
        ->options(['openapi' => true]);

    $routes->add('emsco_interface_document_environments', '/{interface}/data/{name}/environments/{ouuid}')
        ->controller([CrudController::class, 'environments'])
        ->methods(['GET'])
        ->format('json')
        ->defaults([
            'interface' => 'api',
        ])
        ->requirements([
            'interface' => 'api|json',
        ])
        ->options(['openapi' => true]);

    $routes->add('emsco_interface_document_delete', '/{interface}/data/{name}/delete/{ouuid}')
        ->controller([CrudController::class, 'delete'])
        ->methods(['POST'])
        ->format('json')
        ->defaults([
            'interface' => 'api',
        ])
        ->requirements([
            'interface' => 'api|json',
        ])
        ->options(['openapi' => true]);

    $routes->add('emsco_interface_document_replace', '/{interface}/data/{name}/replace/{ouuid}')
        ->controller([CrudController::class, 'replace'])
        ->methods(['POST'])
        ->format('json')
        ->defaults([
            'interface' => 'api',
        ])
        ->requirements([
            'interface' => 'api|json',
        ])
        ->options(['openapi' => true]);

    $routes->add('emsco_interface_document_index', '/{interface}/data/{name}/index/{ouuid}')
        ->controller([CrudController::class, 'index'])
        ->methods(['POST'])
        ->format('json')
        ->defaults([
            'ouuid' => null,
            'interface' => 'api',
        ])
        ->requirements([
            'interface' => 'api|json',
        ])
        ->options(['openapi' => true]);

    $routes->add('emsco_interface_document_update', '/{interface}/data/{name}/update/{ouuid}')
        ->controller([CrudController::class, 'index'])
        ->methods(['POST'])
        ->format('json')
        ->defaults([
            'replaceOrMerge' => 'merge',
            'interface' => 'api',
        ])
        ->requirements([
            'interface' => 'api|json',
        ])
        ->options(['openapi' => true]);

    $routes->add('emsco_interface_document_index_from_asset', '/{interface}/data/{name}/index-from-asset/{ouuid}')
        ->controller([CrudController::class, 'indexFromAsset'])
        ->methods(['POST'])
        ->format('json')
        ->defaults([
            'ouuid' => null,
            'interface' => 'api',
        ])
        ->requirements([
            'interface' => 'api|json',
        ])
        ->options(['openapi' => true]);

    $routes->add('emsco_interface_document_update_from_asset', '/{interface}/data/{name}/update-from-asset/{ouuid}')
        ->controller([CrudController::class, 'indexFromAsset'])
        ->methods(['POST'])
        ->format('json')
        ->defaults([
            'replaceOrMerge' => 'merge',
            'interface' => 'api',
        ])
        ->requirements([
            'interface' => 'api|json',
        ])
        ->options(['openapi' => true]);

    $routes->add('emsco_interface_document_merge', '/{interface}/data/{name}/merge/{ouuid}')
        ->controller([CrudController::class, 'merge'])
        ->methods(['POST'])
        ->format('json')
        ->defaults([
            'interface' => 'api',
        ])
        ->requirements([
            'interface' => 'api|json',
        ])
        ->options(['openapi' => true]);

    $routes->add('emsco_interface_test', '/{interface}/test')
        ->controller([CrudController::class, 'test'])
        ->methods(['GET'])
        ->format('json')
        ->defaults([
            'interface' => 'api',
        ])
        ->requirements([
            'interface' => 'api|json',
        ])
        ->options(['openapi' => true]);

    $routes->add('emsco_interface_content_type_meta', '/{interface}/meta/{name}')
        ->controller([CrudController::class, 'getContentTypeInfo'])
        ->methods(['GET'])
        ->format('json')
        ->defaults([
            'interface' => 'api',
        ])
        ->requirements([
            'interface' => 'api|json',
        ])
        ->options(['openapi' => true]);

    $routes->add('emsco_interface_user_profile', '/{interface}/user-profile')
        ->controller([CrudController::class, 'getUserProfile'])
        ->methods(['GET'])
        ->format('json')
        ->defaults([
            'interface' => 'api',
        ])
        ->requirements([
            'interface' => 'api|json',
        ])
        ->options(['openapi' => true]);

    $routes->add('emsco_interface_user_profiles', '/{interface}/user-profiles')
        ->controller([CrudController::class, 'getUserProfiles'])
        ->methods(['GET'])
        ->format('json')
        ->defaults([
            'interface' => 'api',
        ])
        ->requirements([
            'interface' => 'api|json',
        ])
        ->options(['openapi' => true]);
};
