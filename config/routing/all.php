<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->import('@EMSCoreBundle/config/routing/action.php');
    $routes->import('@EMSCoreBundle/config/routing/data.php')->prefix('/data');
    $routes->import('@EMSCoreBundle/config/routing/api.php');
    $routes->import('@EMSCoreBundle/config/routing/public.php');
    $routes->import('@EMSCoreBundle/config/routing/dashboard.php');
    $routes->import('@EMSCoreBundle/config/routing/form.php');
    $routes->import('@EMSCoreBundle/config/routing/group.php');
    $routes->import('@EMSCoreBundle/config/routing/channel.php')->prefix('/channel-admin');
    $routes->import('@EMSCoreBundle/config/routing/component.php')->prefix('/component');
    $routes->import('@EMSCoreBundle/config/routing/publisher/uploaded-asset.php')->prefix('/publisher/uploaded-file');
    $routes->import('@EMSCoreBundle/config/routing/admin/uploaded-asset.php')->prefix('/admin/uploaded-file-logs');
    $routes->import('@EMSCoreBundle/config/routing/uploaded-file-wysiwyg.php')->prefix('/browser/uploaded-file');
    $routes->import('@EMSCoreBundle/config/routing/asset.php')->prefix('/bundles');
    $routes->import('@EMSCoreBundle/config/routing/release.php')->prefix('/publisher/release-admin');
    $routes->import('@EMSCoreBundle/config/routing/query-search.php')->prefix('/query-search');
    $routes->import('@EMSCoreBundle/config/routing/datatable.php')->prefix('/datatable');
    $routes->import('@EMSCoreBundle/config/routing/data/file.php')->prefix('/data/file');
    $routes->import('@EMSCoreBundle/config/routing/data/revision.php')->prefix('/data');
    $routes->import('@EMSCoreBundle/config/routing/edit.php')->prefix('/data');
    $routes->import('@EMSCoreBundle/config/routing/search.php');
    $routes->import('@EMSCoreBundle/config/routing/task.php');

    $routes->import('@EMSCoreBundle/config/routing/views.php')->prefix('/views');
    $routes->import('@EMSCoreBundle/config/routing/admin/job.php')->prefix('/admin/job');
    $routes->import('@EMSCoreBundle/config/routing/job.php')->prefix('/job');
    $routes->import('@EMSCoreBundle/config/routing/wysiwyg.php')->prefix('/wysiwyg');
    $routes->import('@EMSCoreBundle/config/routing/admin/wysiwyg.php')->prefix('/admin/wysiwyg');
    $routes->import('@EMSCoreBundle/config/routing/user/user.php');
    $routes->import('@EMSCoreBundle/config/routing/log.php')->prefix('/admin/log');
    $routes->import('@EMSCoreBundle/config/routing/admin/elasticsearch.php')->prefix('/admin/elasticsearch/');
    $routes->import('@EMSCoreBundle/config/routing/admin/environment.php')->prefix('/admin/environment');
    $routes->import('@EMSCoreBundle/config/routing/admin/managed-alias.php')->prefix('/admin/environment/managed-alias');
    $routes->import('@EMSCoreBundle/config/routing/publisher.php')->prefix('/publisher');
    $routes->import('@EMSCoreBundle/config/routing/publish.php')->prefix('/publish');
    $routes->import('@EMSCoreBundle/config/routing/revision.php')->prefix('/revision');
    $routes->import('@EMSCoreBundle/config/routing/search-options.php')->prefix('/search-options');
    $routes->import('@EMSCoreBundle/config/routing/admin/analyzer.php')->prefix('/admin/analyzer');
    $routes->import('@EMSCoreBundle/config/routing/admin/filter.php')->prefix('/admin/filter');
    $routes->import('@EMSCoreBundle/config/routing/admin/i18n.php')->prefix('/admin/i18n');
    $routes->import('@EMSCoreBundle/config/routing/admin/content-type.php')->prefix('/admin/content-type');
    $routes->import('@EMSCoreBundle/config/routing/admin/action.php')->prefix('/admin/content-type/action');
    $routes->import('@EMSCoreBundle/config/routing/admin/view.php')->prefix('/admin/content-type/view');
    $routes->import('@EMSCoreBundle/config/routing/admin/webhook.php')->prefix('/admin/webhook');
    $routes->import('@EMSCoreBundle/config/routing/elasticsearch.php')->prefix('/elasticsearch');
    $routes->import('@EMSCoreBundle/config/routing/public-key.php');
    $routes->import('@EMSCoreBundle/config/routing/interface.php');
    $routes->import('@EMSCoreBundle/config/routing/notifications.php')->prefix('/notifications');
    $routes->import('@EMSCoreBundle/config/routing/submission.php')->prefix('/submissions');
    $routes->import('@EMSCoreBundle/config/routing/default.php');
    $routes->import('@EMSCoreBundle/config/routing/file.php')->prefix('/file');
    $routes->import('@EMSCoreBundle/config/routing/images.php')->prefix('/images');
    $routes->import('@EMSCoreBundle/config/routing/icons.php');
    $routes->import('@EMSCoreBundle/config/routing/mercure.php');
    $routes->import('@EMSCoreBundle/config/routing/inline-editor.php');
};
