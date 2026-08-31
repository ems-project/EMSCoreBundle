<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\I18n;
use EMS\CoreBundle\Entity\Job;
use EMS\CoreBundle\Entity\ManagedAlias;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\UploadedAsset;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Repository\AnalyzerRepository;
use EMS\CoreBundle\Repository\AuthTokenRepository;
use EMS\CoreBundle\Repository\CacheActionRepository;
use EMS\CoreBundle\Repository\ChannelRepository;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\DashboardRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\EnvironmentRevisionRepository;
use EMS\CoreBundle\Repository\FieldTypeRepository;
use EMS\CoreBundle\Repository\FilterRepository;
use EMS\CoreBundle\Repository\FormRepository;
use EMS\CoreBundle\Repository\FormSubmissionFileRepository;
use EMS\CoreBundle\Repository\FormSubmissionRepository;
use EMS\CoreBundle\Repository\FormVerificationRepository;
use EMS\CoreBundle\Repository\GroupRepository;
use EMS\CoreBundle\Repository\I18nRepository;
use EMS\CoreBundle\Repository\JobRepository;
use EMS\CoreBundle\Repository\LogRepository;
use EMS\CoreBundle\Repository\ManagedAliasRepository;
use EMS\CoreBundle\Repository\McpToolRepository;
use EMS\CoreBundle\Repository\MessengerMessagesRepository;
use EMS\CoreBundle\Repository\NotificationRepository;
use EMS\CoreBundle\Repository\QuerySearchRepository;
use EMS\CoreBundle\Repository\ReleaseRepository;
use EMS\CoreBundle\Repository\ReleaseRevisionRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\ScheduleRepository;
use EMS\CoreBundle\Repository\SearchRepository;
use EMS\CoreBundle\Repository\TaskRepository;
use EMS\CoreBundle\Repository\TemplateRepository;
use EMS\CoreBundle\Repository\UploadedAssetRepository;
use EMS\CoreBundle\Repository\UserRepository;
use EMS\CoreBundle\Repository\ViewRepository;
use EMS\CoreBundle\Repository\WebhookSubscriptionRepository;
use EMS\CoreBundle\Repository\WysiwygProfileRepository;
use EMS\CoreBundle\Repository\WysiwygStylesSetRepository;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->public()
        ->autowire(false)
        ->autoconfigure(false);

    $services->set('ems.repository.auth_token', AuthTokenRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.cache_action', CacheActionRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.channel', ChannelRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.environment_revision', EnvironmentRevisionRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.query_search', QuerySearchRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.search', SearchRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.analyzer', AnalyzerRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.filter', FilterRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.field_type', FieldTypeRepository::class)
        ->args([service('doctrine')]);

    $services->alias('ems.repository.notification', NotificationRepository::class);

    $services->set(NotificationRepository::class)
        ->args([
            service('doctrine'),
            service('security.authorization_checker'),
        ])
        ->tag('doctrine.repository_service');

    $services->alias('ems.repository.template', TemplateRepository::class);

    $services->set(TemplateRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');

    $services->set('ems.repository.task', TaskRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.group', GroupRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.dashboard', DashboardRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.form', FormRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.release', ReleaseRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.release_revision', ReleaseRevisionRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.view', ViewRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.schedule', ScheduleRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.user', UserRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.log', LogRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.wysiwyg_profile', WysiwygProfileRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.wysiwyg_style_set', WysiwygStylesSetRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.i18n', I18nRepository::class)
        ->args([service('doctrine')]);

    $services->set(ContentTypeRepository::class)
        ->args([ContentType::class])
        ->factory([service('doctrine.orm.default_entity_manager'), 'getRepository']);

    $services->set(EnvironmentRepository::class)
        ->args([Environment::class])
        ->factory([service('doctrine.orm.default_entity_manager'), 'getRepository']);

    $services->set(I18nRepository::class)
        ->args([I18n::class])
        ->factory([service('doctrine.orm.default_entity_manager'), 'getRepository']);

    $services->set(JobRepository::class)
        ->args([Job::class])
        ->factory([service('doctrine.orm.default_entity_manager'), 'getRepository']);

    $services->set(ManagedAliasRepository::class)
        ->args([ManagedAlias::class])
        ->factory([service('doctrine.orm.default_entity_manager'), 'getRepository']);

    $services->set(RevisionRepository::class)
        ->args([Revision::class])
        ->factory([service('doctrine.orm.default_entity_manager'), 'getRepository']);

    $services->set(UserRepository::class)
        ->args([User::class])
        ->factory([service('doctrine.orm.default_entity_manager'), 'getRepository']);

    $services->set('ems.repository.form_submission', FormSubmissionRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.form_submission_file', FormSubmissionFileRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.form_verification', FormVerificationRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.uploaded_asset_repository', UploadedAssetRepository::class)
        ->lazy()
        ->args([UploadedAsset::class])
        ->factory([service('doctrine.orm.default_entity_manager'), 'getRepository']);

    $services->set('ems.repository.webhook_subscription', WebhookSubscriptionRepository::class)
        ->args([service('doctrine')]);

    $services->set('ems.repository.messenger_messages_repository', MessengerMessagesRepository::class)
        ->args([service('doctrine.dbal.default_connection')]);

    $services->set('ems.repository.mcp_tool', McpToolRepository::class)
        ->args([service('doctrine')]);
};
