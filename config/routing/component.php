<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Component\JsonMenuNestedController;
use EMS\CoreBundle\Controller\Component\MediaLibraryController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    // Json Menu Nested
    $routes->add('emsco.json_menu_nested.render', '/json-menu-nested/{hash}/render')
        ->controller([JsonMenuNestedController::class, 'render'])
        ->methods(['POST'])
        ->requirements(['hash' => '.*']);

    $routes->add('emsco.json_menu_nested.item_modal_add', '/json-menu-nested/{hash}/item/{itemId}/modal-add/{nodeId}')
        ->controller([JsonMenuNestedController::class, 'itemModalAdd'])
        ->methods(['GET', 'POST'])
        ->requirements(['hash' => '.*', 'itemId' => '.*', 'nodeId' => '.*']);

    $routes->add('emsco.json_menu_nested.item_modal_view', '/json-menu-nested/{hash}/item/{itemId}/modal-view')
        ->controller([JsonMenuNestedController::class, 'itemModalView'])
        ->methods(['GET'])
        ->requirements(['hash' => '.*', 'itemId' => '.*']);

    $routes->add('emsco.json_menu_nested.item_modal_edit', '/json-menu-nested/{hash}/item/{itemId}/modal-edit')
        ->controller([JsonMenuNestedController::class, 'itemModalEdit'])
        ->methods(['GET', 'POST'])
        ->requirements(['hash' => '.*', 'itemId' => '.*']);

    $routes->add('emsco.json_menu_nested.item_modal_custom', '/json-menu-nested/{hash}/item/{itemId}/modal-custom/{modalName}')
        ->controller([JsonMenuNestedController::class, 'itemModalCustom'])
        ->methods(['GET'])
        ->requirements(['hash' => '.*', 'itemId' => '.*', 'modalName' => '.*']);

    $routes->add('emsco.json_menu_nested.item_add', '/json-menu-nested/{hash}/item/{itemId}/add')
        ->controller([JsonMenuNestedController::class, 'itemAdd'])
        ->methods(['POST'])
        ->requirements(['hash' => '.*', 'itemId' => '.*']);

    $routes->add('emsco.json_menu_nested.item_delete', '/json-menu-nested/{hash}/item/{itemId}/delete')
        ->controller([JsonMenuNestedController::class, 'itemDelete'])
        ->methods(['POST'])
        ->requirements(['hash' => '.*', 'itemId' => '.*']);

    $routes->add('emsco.json_menu_nested.item_move', '/json-menu-nested/{hash}/item/{itemId}/move')
        ->controller([JsonMenuNestedController::class, 'itemMove'])
        ->methods(['POST'])
        ->requirements(['hash' => '.*', 'itemId' => '.*']);

    $routes->add('emsco.json_menu_nested.item_copy', '/json-menu-nested/{hash}/item/{itemId}/copy')
        ->controller([JsonMenuNestedController::class, 'itemCopy'])
        ->methods(['POST'])
        ->requirements(['hash' => '.*', 'itemId' => '.*']);

    $routes->add('emsco.json_menu_nested.item_paste', '/json-menu-nested/{hash}/item/{itemId}/paste')
        ->controller([JsonMenuNestedController::class, 'itemPaste'])
        ->methods(['POST'])
        ->requirements(['hash' => '.*', 'itemId' => '.*']);

    $routes->add('emsco.json_menu_nested.item', '/json-menu-nested/{hash}/item/{itemId}')
        ->controller([JsonMenuNestedController::class, 'item'])
        ->methods(['GET'])
        ->requirements(['hash' => '.*', 'itemId' => '.*']);

    // Media library
    $routes->add('emsco.media_library.layout', '/media-lib/{hash}/layout')
        ->controller([MediaLibraryController::class, 'getLayout'])
        ->methods(['GET'])
        ->requirements(['hash' => '.*']);

    $routes->add('emsco.media_library.file.rename', '/media-lib/{hash}/file/{fileId}/rename')
        ->controller([MediaLibraryController::class, 'renameFile'])
        ->methods(['GET', 'POST'])
        ->requirements(['hash' => '.*', 'fileId' => '.*']);

    $routes->add('emsco.media_library.file.view', '/media-lib/{hash}/file/{fileId}/view')
        ->controller([MediaLibraryController::class, 'viewFile'])
        ->methods(['GET'])
        ->requirements(['hash' => '.*']);

    $routes->add('emsco.media_library.file.delete', '/media-lib/{hash}/file/{fileId}/delete')
        ->controller([MediaLibraryController::class, 'deleteFile'])
        ->methods(['POST'])
        ->requirements(['hash' => '.*']);

    $routes->add('emsco.media_library.file.move', '/media-lib/{hash}/file/{fileId}/move')
        ->controller([MediaLibraryController::class, 'moveFile'])
        ->methods(['POST'])
        ->requirements(['hash' => '.*']);

    $routes->add('emsco.media_library.files', '/media-lib/{hash}/files/{folderId}')
        ->controller([MediaLibraryController::class, 'getFiles'])
        ->methods(['GET'])
        ->requirements(['hash' => '.*'])
        ->defaults(['folderId' => null]);

    $routes->add('emsco.media_library.folders', '/media-lib/{hash}/folders')
        ->controller([MediaLibraryController::class, 'getFolders'])
        ->methods(['GET'])
        ->requirements(['hash' => '.*']);

    $routes->add('emsco.media_library.folder.delete', '/media-lib/{hash}/folder/{folderId}/delete')
        ->controller([MediaLibraryController::class, 'deleteFolder'])
        ->methods(['GET', 'POST'])
        ->requirements(['hash' => '.*', 'folderId' => '.*']);

    $routes->add('emsco.media_library.folder.rename', '/media-lib/{hash}/folder/{folderId}/rename')
        ->controller([MediaLibraryController::class, 'renameFolder'])
        ->methods(['GET', 'POST'])
        ->requirements(['hash' => '.*', 'folderId' => '.*']);

    $routes->add('emsco.media_library.folder.move', '/media-lib/{hash}/folder/{folderId}/move')
        ->controller([MediaLibraryController::class, 'moveFolder'])
        ->methods(['GET', 'POST'])
        ->requirements(['hash' => '.*', 'folderId' => '.*']);

    $routes->add('emsco.media_library.add_folder', '/media-lib/{hash}/add-folder/{folderId}')
        ->controller([MediaLibraryController::class, 'addFolder'])
        ->methods(['GET', 'POST'])
        ->requirements(['hash' => '.*'])
        ->defaults(['folderId' => null]);

    $routes->add('emsco.media_library.create_file', '/media-lib/{hash}/add-file/{folderId}')
        ->controller([MediaLibraryController::class, 'addFile'])
        ->methods(['POST'])
        ->requirements(['hash' => '.*'])
        ->defaults(['folderId' => null])
        ->options(['openapi' => true]);

    $routes->add('emsco.media_library.files.delete', '/media-lib/{hash}/delete-files/{folderId}')
        ->controller([MediaLibraryController::class, 'deleteFiles'])
        ->methods(['GET', 'POST'])
        ->requirements(['hash' => '.*'])
        ->defaults(['folderId' => null]);

    $routes->add('emsco.media_library.files.move', '/media-lib/{hash}/move-files/{folderId}')
        ->controller([MediaLibraryController::class, 'moveFiles'])
        ->methods(['GET', 'POST'])
        ->requirements(['hash' => '.*'])
        ->defaults(['folderId' => null]);
};
