<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    final public const PAGING_SIZE = 20;
    final public const SHORTNAME = 'e<b>ms</b>';
    final public const NAME = 'elastic<b>ms</b>';
    final public const THEME_COLOR = 'blue';
    final public const DATE_TIME_FORMAT = 'j/m/Y \a\t G:i';
    final public const DATE_FORMAT = 'j/m/Y';
    final public const TIME_FORMAT = 'G:i:s';
    final public const FROM_EMAIL_ADDRESS = 'noreply@example.com';
    final public const FROM_EMAIL_NAME = 'elasticms';
    final public const INSTANCE_ID = 'ems_';
    final public const CIRCLES_OBJECT = null;
    final public const ELASTICSEARCH_DEFAULT_CLUSTER = ['http://localhost:9200'];
    final public const DATEPICKER_FORMAT = 'dd/mm/yyyy';
    final public const DATEPICKER_WEEKSTART = 1;
    final public const DATEPICKER_DAYSOFWEEK_HIGHLIGHTED = [0, 6];
    final public const NOTIFICATION_PENDING_TIMEOUT = 'P0Y0M15DT0H0M0S';
    final public const TRIGGER_JOB_FROM_WEB = true;
    final public const LOCK_TIME = '+1 minutes';
    final public const PRIVATE_KEY = null;
    final public const PUBLIC_KEY = null;
    final public const ASSET_CONFIG = [];
    final public const TIKA_SERVER = null;
    final public const TIKA_MAX_CONTENT = 5120;
    final public const SAVE_ASSETS_IN_DB = false;
    final public const DEFAULT_BULK_SIZE = 500;
    final public const CLEAN_JOBS_TIME_STRING = '-7 days';
    final public const TEMPLATE_NAMESPACE = 'EMSCore';
    final public const DYNAMIC_MAPPING = 'false';
    final public const IMAGE_MAX_SIZE = 2048;

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ems_core');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
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
                ->scalarNode('trigger_job_from_web')->defaultValue(self::TRIGGER_JOB_FROM_WEB)->end()
                ->scalarNode('lock_time')->defaultValue(self::LOCK_TIME)->end()
                ->variableNode('asset_config')->defaultValue(self::ASSET_CONFIG)->end()
                ->scalarNode('private_key')->defaultValue(self::PRIVATE_KEY)->end()
                ->scalarNode('public_key')->defaultValue(self::PUBLIC_KEY)->end()
                ->scalarNode('tika_server')->defaultValue(self::TIKA_SERVER)->end()
                ->scalarNode('tika_max_content')->defaultValue(self::TIKA_MAX_CONTENT)->end()
                ->scalarNode('elasticsearch_version')->defaultValue('depreacted')->end()
                ->booleanNode('pre_generated_ouuids')->defaultValue(false)->end()
                ->arrayNode('template_options')->defaultValue([])->prototype('variable')->end()->end()
                ->scalarNode('health_check_allow_origin')->defaultValue(null)->end()
                ->scalarNode('tika_download_url')->defaultValue(null)->end()
                ->scalarNode('default_bulk_size')->defaultValue(self::DEFAULT_BULK_SIZE)->end()
                ->scalarNode('url_user')->defaultValue(null)->end()
                ->scalarNode('clean_jobs_time_string')->defaultValue(self::CLEAN_JOBS_TIME_STRING)->end()
                ->scalarNode('custom_user_options_form')->defaultValue(null)->end()
                ->scalarNode('template_namespace')->defaultValue(self::TEMPLATE_NAMESPACE)->end()
                ->scalarNode('dynamic_mapping')->defaultValue(self::DYNAMIC_MAPPING)->end()
                ->scalarNode('image_max_size')->defaultValue(self::IMAGE_MAX_SIZE)->end()
            ->end()
        ;

        $this->addSecuritySection($rootNode);
        $this->addLdapSection($rootNode);

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

    private function addLdapSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('ldap')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('dn_string')->end()
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
                    ->end()
                ->end()
            ->end()
        ;
    }
}
