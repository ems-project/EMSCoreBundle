<?php

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
	const SHORTNAME = '<b>e</b>MS';
	const NAME = '<b>elastic</b>MS';
	const THEME_COLOR = 'blue';
	const DATE_TIME_FORMAT = 'j/m/Y \a\t G:i';
	const FROM_EMAIL_ADDRESS = 'noreply@example.com';
	const FROM_EMAIL_NAME = 'elasticMS';
	const INSTANCE_ID = 'ems_';
	const CIRCLES_OBJECT = null;
	const ELASTICSEARCH_DEFAULT_SERVER = 'http://localhost:9200';
	const DATEPICKER_FORMAT = 'dd/mm/yyyy';
	const DATEPICKER_WEEKSTART = 1;
	const DATEPICKER_DAYSOFWEEK_HIGHLIGHTED = [0,6];
	const UPLOADING_FOLDER = null;
	const STORAGE_SERVICES = [[ 'service' => 'ems.storage.filesystem', 'path' => null ]];
	const AUDIT_INDEX = null;
	const NOTIFICATION_PENDING_TIMEOUT = 'P0Y0M15DT0H0M0S';
	const ALLOW_USER_REGISTRATION = false;
	const LOCK_TIME = '+1 minutes';
	const FILESYSTEM_STORAGE_FOLDER = null;
	
	
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
		        ->arrayNode('elasticsearch_cluster')->requiresAtLeastOneElement()->defaultValue([self::ELASTICSEARCH_DEFAULT_SERVER])
		       		->prototype('scalar')->end()
		       	->end()
		        ->arrayNode('datepicker_daysofweek_highlighted')->requiresAtLeastOneElement()->defaultValue([self::DATEPICKER_DAYSOFWEEK_HIGHLIGHTED])
		       		->prototype('scalar')->end()
		       	->end()
		        ->arrayNode('from_email')->addDefaultsIfNotSet()
		        	->children()
			        	->scalarNode('address')->defaultValue(self::FROM_EMAIL_ADDRESS)->end()
			        	->scalarNode('sender_name')->defaultValue(self::FROM_EMAIL_NAME)->end()
			        ->end()
		        ->end()
		        ->arrayNode('storage_services')->defaultValue(self::STORAGE_SERVICES)
		        	->prototype('array')
				        ->children()
				        	->scalarNode('service')->cannotBeEmpty()->end()
				        	->scalarNode('path')->cannotBeEmpty()->end()
				        	->scalarNode('identifier')->end()
				        	->scalarNode('authkey')->end()
				        	->end()
				       ->end()
		        ->end()
		        ->scalarNode('filesystem_storage_folder')->defaultValue(self::FILESYSTEM_STORAGE_FOLDER)->end()
		        ->scalarNode('uploading_folder')->defaultValue(self::UPLOADING_FOLDER)->end()
		        ->scalarNode('audit_index')->defaultValue(self::AUDIT_INDEX)->end()
		        ->scalarNode('date_time_format')->defaultValue(self::DATE_TIME_FORMAT)->end()
		        ->scalarNode('notification_pending_timeout')->defaultValue(self::NOTIFICATION_PENDING_TIMEOUT)->end()
		        ->scalarNode('allow_user_registration')->defaultValue(self::ALLOW_USER_REGISTRATION)->end()
		        ->scalarNode('lock_time')->defaultValue(self::LOCK_TIME)->end()
		        ->arrayNode('template_options')->defaultValue([])
		        	->prototype('array')
		        	->end()
		        ->end()
	        ->end();

        return $treeBuilder;
    }
}
