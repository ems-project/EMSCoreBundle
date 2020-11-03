<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    const PAGING_SIZE = 20;
    const SHORTNAME = 'e<b>ms</b>';
    const NAME = 'elastic<b>ms</b>';
    const THEME_COLOR = 'blue';
    const DATE_TIME_FORMAT = 'j/m/Y \a\t G:i';
    const FROM_EMAIL_ADDRESS = 'noreply@example.com';
    const FROM_EMAIL_NAME = 'elasticms';
    const INSTANCE_ID = 'ems_';
    const CIRCLES_OBJECT = null;
    const ELASTICSEARCH_DEFAULT_CLUSTER = ['http://localhost:9200'];
    const DATEPICKER_FORMAT = 'dd/mm/yyyy';
    const DATEPICKER_WEEKSTART = 1;
    const DATEPICKER_DAYSOFWEEK_HIGHLIGHTED = [0, 6];
    const NOTIFICATION_PENDING_TIMEOUT = 'P0Y0M15DT0H0M0S';
    const ALLOW_USER_REGISTRATION = false;
    const LOCK_TIME = '+1 minutes';
    const USER_LOGIN_ROUTE = 'fos_user_security_login';
    const USER_PROFILE_ROUTE = 'fos_user_profile_show';
    const USER_LOGOUT_ROUTE = 'fos_user_security_logout';
    const USER_REGISTRATION_ROUTE = 'fos_user_registration_register';
    const ADD_USER_ROUTE = 'user.add';
    const APPLICATION_MENU_CONTROLLER = null;
    const PRIVATE_KEY = null;
    const PUBLIC_KEY = null;
    const ASSET_CONFIG = [];
    const TIKA_SERVER = null;
    const ELASTICSEARCH_VERSION = '5.4';
    const SINGLE_TYPE_INDEX = false;
    const SAVE_ASSETS_IN_DB = false;

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('ems_core');

        $rootNode->addDefaultsIfNotSet()->children()
            ->scalarNode('paging_size')->defaultValue(self::PAGING_SIZE)->end()
            ->scalarNode('circles_object')->defaultValue(self::CIRCLES_OBJECT)->end()
            ->scalarNode('shortname')->defaultValue(self::SHORTNAME)->end()
            ->scalarNode('name')->defaultValue(self::NAME)->end()
            ->scalarNode('theme_color')->defaultValue(self::THEME_COLOR)->end()
            ->scalarNode('date_time_format')->defaultValue(self::DATE_TIME_FORMAT)->end()
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
            ->scalarNode('lock_time')->defaultValue(self::LOCK_TIME)->end()
            ->scalarNode('user_login_route')->defaultValue(self::USER_LOGIN_ROUTE)->end()
            ->scalarNode('user_profile_route')->defaultValue(self::USER_PROFILE_ROUTE)->end()
            ->scalarNode('user_logout_route')->defaultValue(self::USER_LOGOUT_ROUTE)->end()
            ->scalarNode('user_registration_route')->defaultValue(self::USER_REGISTRATION_ROUTE)->end()
            ->scalarNode('add_user_route')->defaultValue(self::ADD_USER_ROUTE)->end()
            ->scalarNode('application_menu_controller')->defaultValue(self::APPLICATION_MENU_CONTROLLER)->end()
            ->variableNode('asset_config')->defaultValue(self::ASSET_CONFIG)->end()
            ->scalarNode('private_key')->defaultValue(self::PRIVATE_KEY)->end()
            ->scalarNode('public_key')->defaultValue(self::PUBLIC_KEY)->end()
            ->scalarNode('upload_folder')->defaultValue(null)->end()
            ->scalarNode('storage_folder')->defaultValue(null)->end()
            ->scalarNode('sftp_server')->defaultValue(null)->end()
            ->scalarNode('sftp_path')->defaultValue(null)->end()
            ->scalarNode('sftp_user')->defaultValue(null)->end()
            ->scalarNode('ems_remote_host')->defaultValue(null)->end()
            ->scalarNode('ems_remote_authkey')->defaultValue(null)->end()
            ->scalarNode('tika_server')->defaultValue(self::TIKA_SERVER)->end()
            ->scalarNode('elasticsearch_version')->defaultValue(self::ELASTICSEARCH_VERSION)->end()
            ->booleanNode('single_type_index')->defaultValue(self::SINGLE_TYPE_INDEX)->end()
            ->scalarNode('save_assets_in_db')->defaultValue(self::SAVE_ASSETS_IN_DB)->end()
            ->scalarNode('s3_bucket')->defaultValue(null)->end()
            ->variableNode('s3_credentials')->defaultValue([])->end()
            ->arrayNode('template_options')->defaultValue([])->prototype('variable')->end()->end()
            ->scalarNode('health_check_allow_origin')->defaultValue(null)->end()
            ->scalarNode('tika_download_url')->defaultValue(null)->end()
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

        return $treeBuilder;
    }
}
