<?php

namespace EMS\CoreBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class EMSCoreExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container) {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
		 
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
        
        $container->setParameter('ems_core.from_email', $config['from_email']);
        $container->setParameter('ems_core.instance_id', $config['instance_id']);
        $container->setParameter('ems_core.shortname', $config['shortname']);
        $container->setParameter('ems_core.name', $config['name']);
        $container->setParameter('ems_core.theme_color', $config['theme_color']);
        $container->setParameter('ems_core.date_time_format', $config['date_time_format']);
        $container->setParameter('ems_core.paging_size', $config['paging_size']);
        $container->setParameter('ems_core.circles_object', $config['circles_object']);
        $container->setParameter('ems_core.elasticsearch_cluster', $config['elasticsearch_cluster']);
        $container->setParameter('ems_core.datepicker_daysofweek_highlighted', $config['datepicker_daysofweek_highlighted']);
        $container->setParameter('ems_core.datepicker_weekstart', $config['datepicker_weekstart']);
        $container->setParameter('ems_core.datepicker_format', $config['datepicker_format']);
        $container->setParameter('ems_core.audit_index', $config['audit_index']);
        $container->setParameter('ems_core.date_time_format', $config['date_time_format']);
        $container->setParameter('ems_core.notification_pending_timeout', $config['notification_pending_timeout']);
        $container->setParameter('ems_core.allow_user_registration', $config['allow_user_registration']);
        $container->setParameter('ems_core.lock_time', $config['lock_time']);
        $container->setParameter('ems_core.template_options', $config['template_options']);
        
    }
    
    public function prepend(ContainerBuilder $container) {

    	// get all bundles
    	$bundles = $container->getParameter('kernel.bundles');
    	
	    $configs = $container->getExtensionConfig($this->getAlias());
	    
	    $globals = [
	    	'theme_color' => isset($configs[0]['theme_color'])?$configs[0]['theme_color']:Configuration::THEME_COLOR,
    		'ems_name' => isset($configs[0]['name'])?$configs[0]['name']:Configuration::NAME,
    		'ems_shortname' => isset($configs[0]['shortname'])?$configs[0]['shortname']:Configuration::SHORTNAME,
    		'date_time_format' => isset($configs[0]['date_time_format'])?$configs[0]['date_time_format']:Configuration::DATE_TIME_FORMAT,
   			'paging_size' => isset($configs[0]['paging_size'])?$configs[0]['paging_size']:Configuration::PAGING_SIZE,
   			'circles_object' => isset($configs[0]['circles_object'])?$configs[0]['circles_object']:Configuration::CIRCLES_OBJECT,
   			'datepicker_daysofweek_highlighted' => isset($configs[0]['datepicker_daysofweek_highlighted'])?$configs[0]['datepicker_daysofweek_highlighted']:Configuration::DATEPICKER_DAYSOFWEEK_HIGHLIGHTED,
   			'datepicker_weekstart' => isset($configs[0]['datepicker_weekstart'])?$configs[0]['datepicker_weekstart']:Configuration::DATEPICKER_WEEKSTART,
    		'datepicker_format' => isset($configs[0]['datepicker_format'])?$configs[0]['datepicker_format']:Configuration::DATEPICKER_FORMAT,
	    	'date_time_format' => isset($configs[0]['date_time_format'])?$configs[0]['date_time_format']:Configuration::DATE_TIME_FORMAT,
	    	'allow_user_registration' => isset($configs[0]['allow_user_registration'])?$configs[0]['allow_user_registration']:Configuration::ALLOW_USER_REGISTRATION,
    		'user_login_route' => isset($configs[0]['user_login_route'])?$configs[0]['user_login_route']:Configuration::USER_LOGIN_ROUTE,
	    ];
	    
	    if(!empty($configs[0]['template_options'])){
	    	$globals = array_merge($globals, $configs[0]['template_options']);
	    }
	    
	    
	    if (isset($bundles['TwigBundle'])) {
    		$container->prependExtensionConfig('twig', [
    			'globals' => $globals,
    			'form_themes' => ["EMSCoreBundle:form:fields.html.twig"],
    		]);
    	}
    	
    	
    }
}
