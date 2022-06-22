<?php

namespace EMS\CoreBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    public const PAGING_SIZE = 20;
    public const SHORTNAME = 'e<b>ms</b>';
    public const NAME = 'elastic<b>ms</b>';
    public const THEME_COLOR = 'blue';
    public const DATE_TIME_FORMAT = 'j/m/Y \a\t G:i';
    public const DATE_FORMAT = 'j/m/Y';
    public const TIME_FORMAT = 'G:i:s';
    public const FROM_EMAIL_ADDRESS = 'noreply@example.com';
    public const FROM_EMAIL_NAME = 'elasticms';
    public const INSTANCE_ID = 'ems_';
    public const CIRCLES_OBJECT = null;
    public const ELASTICSEARCH_DEFAULT_CLUSTER = ['http://localhost:9200'];
    public const DATEPICKER_FORMAT = 'dd/mm/yyyy';
    public const DATEPICKER_WEEKSTART = 1;
    public const DATEPICKER_DAYSOFWEEK_HIGHLIGHTED = [0, 6];
    public const NOTIFICATION_PENDING_TIMEOUT = 'P0Y0M15DT0H0M0S';
    public const ALLOW_USER_REGISTRATION = false;
    public const TRIGGER_JOB_FROM_WEB = true;
    public const LOCK_TIME = '+1 minutes';
    public const PRIVATE_KEY = null;
    public const PUBLIC_KEY = null;
    public const ASSET_CONFIG = [];
    public const TIKA_SERVER = null;
    public const SAVE_ASSETS_IN_DB = false;
    public const DEFAULT_BULK_SIZE = 500;
    public const CLEAN_JOBS_TIME_STRING = '-7 days';
    public const FALLBACK_LOCALE = 'en';

    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ems_core');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode->addDefaultsIfNotSet()->children()
            ->scalarNode('paging_size')->defaultValue(self::PAGING_SIZE)->end()
            ->scalarNode('circles_object')->defaultValue(self::CIRCLES_OBJECT)->end()
            ->scalarNode('shortname')->defaultValue(self::SHORTNAME)->end()
            ->scalarNode('name')->defaultValue(self::NAME)->end()
            ->scalarNode('theme_color')->defaultValue(self::THEME_COLOR)->end()
            ->scalarNode('date_time_format')->defaultValue(self::DATE_TIME_FORMAT)->end()
            ->scalarNode('date_format')->defaultValue(self::DATE_FORMAT)->end()
            ->scalarNode('time_format')->defaultValue(self::TIME_FORMAT)->end()
            ->scalarNode('instance_id')->defaultValue(self::INSTANCE_ID)->end()
            ->scalarNode('datepicker_format')->defaultValue(self::DATEPICKER_FORMAT)->end()
            ->scalarNode('datepicker_weekstart')->defaultValue(self::DATEPICKER_WEEKSTART)->end()
            ->variableNode('elasticsearch_cluster')->defaultValue(self::ELASTICSEARCH_DEFAULT_CLUSTER)->end()
            ->variableNode('datepicker_daysofweek_highlighted')->defaultValue([self::DATEPICKER_DAYSOFWEEK_HIGHLIGHTED])->end()
            ->arrayNode('from_email')->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('address')->defaultValue(self::FROM_EMAIL_ADDRESS)->end()
            ->scalarNode('sender_name')->defaultValue(self::FROM_EMAIL_NAME)->end()
            ->end()
            ->end()
            ->scalarNode('notification_pending_timeout')->defaultValue(self::NOTIFICATION_PENDING_TIMEOUT)->end()
            ->scalarNode('allow_user_registration')->defaultValue(self::ALLOW_USER_REGISTRATION)->end()
            ->scalarNode('trigger_job_from_web')->defaultValue(self::TRIGGER_JOB_FROM_WEB)->end()
            ->scalarNode('lock_time')->defaultValue(self::LOCK_TIME)->end()
            ->variableNode('asset_config')->defaultValue(self::ASSET_CONFIG)->end()
            ->scalarNode('private_key')->defaultValue(self::PRIVATE_KEY)->end()
            ->scalarNode('public_key')->defaultValue(self::PUBLIC_KEY)->end()
            ->scalarNode('tika_server')->defaultValue(self::TIKA_SERVER)->end()
            ->scalarNode('elasticsearch_version')->defaultValue('depreacted')->end()
            ->booleanNode('pre_generated_ouuids')->defaultValue(false)->end()
            ->arrayNode('template_options')->defaultValue([])->prototype('variable')->end()->end()
            ->scalarNode('health_check_allow_origin')->defaultValue(null)->end()
            ->scalarNode('tika_download_url')->defaultValue(null)->end()
            ->scalarNode('default_bulk_size')->defaultValue(self::DEFAULT_BULK_SIZE)->end()
            ->scalarNode('url_user')->defaultValue(null)->end()
            ->scalarNode('clean_jobs_time_string')->defaultValue(self::CLEAN_JOBS_TIME_STRING)->end()
            ->scalarNode('fallback_locale')->defaultValue(self::FALLBACK_LOCALE)->end()
            ->arrayNode('ldap')
            ->children()
            ->scalarNode('base_dn')->end()
            ->scalarNode('search_dn')->end()
            ->scalarNode('search_password')->end()
            ->variableNode('default_roles')->end()
            ->scalarNode('uid_key')->end()
            ->scalarNode('filter')->end()
            ->scalarNode('password_attribute')->end()
            ->variableNode('extra_fields')->end()
            ->scalarNode('email_field')->end()
            ->scalarNode('display_name_field')->end()
            ->scalarNode('given_name_field')->end()
            ->scalarNode('last_name_field')->end()
            ->end()
            ->end();

        $this->addSecuritySection($rootNode);

        return $treeBuilder;
    }

    private function addSecuritySection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('security')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('firewall')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('core')->defaultValue('ems_core')->cannotBeEmpty()->end()
                                ->scalarNode('api')->defaultValue('ems_core_api')->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
