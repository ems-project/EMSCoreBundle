<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DependencyInjection;

use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Routes;
use Ramsey\Uuid\Doctrine\UuidType;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class EMSCoreExtension extends Extension implements PrependExtensionInterface
{
    final public const TRANS_DOMAIN = 'EMSCoreBundle';

    /**
     * @param array<mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $xmlLoader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $xmlLoader->load('command.xml');
        $xmlLoader->load('contracts.xml');
        $xmlLoader->load('controllers.xml');
        $xmlLoader->load('core.xml');
        $xmlLoader->load('form.xml');
        $xmlLoader->load('log.xml');
        $xmlLoader->load('repositories.xml');
        $xmlLoader->load('view_types.xml');
        $xmlLoader->load('dashboards.xml');
        $xmlLoader->load('datatable.xml');
        $xmlLoader->load('controllers.xml');
        $xmlLoader->load('services.xml');
        $xmlLoader->load('twig.xml');
        $xmlLoader->load('security/security.xml');
        $xmlLoader->load('security/ldap.xml');

        $container->setParameter('ems_core.from_email', $config['from_email']);
        $container->setParameter('ems_core.instance_id', $config['instance_id']);
        $container->setParameter('ems_core.shortname', $config['shortname']);
        $container->setParameter('ems_core.name', $config['name']);
        $container->setParameter('ems_core.theme_color', $config['theme_color']);
        $container->setParameter('ems_core.date_time_format', $config['date_time_format']);
        $container->setParameter('ems_core.date_format', $config['date_format']);
        $container->setParameter('ems_core.time_format', $config['time_format']);
        $container->setParameter('ems_core.paging_size', $config['paging_size']);
        $container->setParameter('ems_core.circles_object', $config['circles_object']);
        $container->setParameter('ems_core.elasticsearch_cluster', $config['elasticsearch_cluster']);
        $container->setParameter('ems_core.datepicker_daysofweek_highlighted', $config['datepicker_daysofweek_highlighted']);
        $container->setParameter('ems_core.datepicker_weekstart', $config['datepicker_weekstart']);
        $container->setParameter('ems_core.datepicker_format', $config['datepicker_format']);
        $container->setParameter('ems_core.notification_pending_timeout', $config['notification_pending_timeout']);
        $container->setParameter('ems_core.trigger_job_from_web', $config['trigger_job_from_web']);
        $container->setParameter('ems_core.lock_time', $config['lock_time']);
        $container->setParameter('ems_core.template_options', $config['template_options']);
        $container->setParameter('ems_core.asset_config', $config['asset_config']);
        $container->setParameter('ems_core.tika_server', $config['tika_server']);
        $container->setParameter('ems_core.tika_max_content', $config['tika_max_content']);
        $container->setParameter('ems_core.pre_generated_ouuids', $config['pre_generated_ouuids']);
        $container->setParameter('ems_core.private_key', $config['private_key']);
        $container->setParameter('ems_core.public_key', $config['public_key']);
        $container->setParameter('ems_core.health_check_allow_origin', $config['health_check_allow_origin']);
        $container->setParameter('ems_core.tika_download_url', $config['tika_download_url']);
        $container->setParameter('ems_core.default_bulk_size', $config['default_bulk_size']);
        $container->setParameter('ems_core.clean_jobs_time_string', $config['clean_jobs_time_string']);
        $container->setParameter('ems_core.url_user', $config['url_user']);
        $container->setParameter('ems_core.custom_user_options_form', $config['custom_user_options_form']);
        $container->setParameter('ems_core.template_namespace', $config['template_namespace']);
        $container->setParameter('ems_core.dynamic_mapping', $config['dynamic_mapping']);
        $container->setParameter('ems_core.image_max_size', $config['image_max_size']);

        $container->setParameter('ems_core.security.firewall.core', $config['security']['firewall']['core']);
        $container->setParameter('ems_core.security.firewall.api', $config['security']['firewall']['api']);

        $container->setParameter('ems_core.security.ldap.enabled', $config['ldap']['enabled']);
        $container->setParameter('ems_core.security.ldap.config', $config['ldap']);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');
        $configs = $container->getExtensionConfig($this->getAlias());

        $globals = [
            'theme_color' => $configs[0]['theme_color'] ?? Configuration::THEME_COLOR,
            'ems_name' => $configs[0]['name'] ?? Configuration::NAME,
            'ems_shortname' => $configs[0]['shortname'] ?? Configuration::SHORTNAME,
            'paging_size' => $configs[0]['paging_size'] ?? Configuration::PAGING_SIZE,
            'circles_object' => $configs[0]['circles_object'] ?? Configuration::CIRCLES_OBJECT,
            'datepicker_daysofweek_highlighted' => $configs[0]['datepicker_daysofweek_highlighted'] ?? Configuration::DATEPICKER_DAYSOFWEEK_HIGHLIGHTED,
            'datepicker_weekstart' => $configs[0]['datepicker_weekstart'] ?? Configuration::DATEPICKER_WEEKSTART,
            'datepicker_format' => $configs[0]['datepicker_format'] ?? Configuration::DATEPICKER_FORMAT,
            'date_time_format' => $configs[0]['date_time_format'] ?? Configuration::DATE_TIME_FORMAT,
            'date_format' => $configs[0]['date_format'] ?? Configuration::DATE_FORMAT,
            'time_format' => $configs[0]['time_format'] ?? Configuration::TIME_FORMAT,
            'trigger_job_from_web' => $configs[0]['trigger_job_from_web'] ?? Configuration::TRIGGER_JOB_FROM_WEB,
            'routes' => (new \ReflectionClass(Routes::class))->getConstants(),
            'image_max_size' => $configs[0]['image_max_size'] ?? Configuration::IMAGE_MAX_SIZE,
        ];

        if (!empty($configs[0]['template_options'])) {
            $globals = \array_merge($globals, $configs[0]['template_options']);
        }

        if (\is_array($bundles) && isset($bundles['TwigBundle'])) {
            $themeNamespace = $configs[0]['template_namespace'] ?? 'EMSCore';
            $container->prependExtensionConfig('twig', [
                'globals' => $globals,
                'form_themes' => [
                    "@$themeNamespace/form/fields.html.twig",
                ],
            ]);
        }

        if (\is_array($bundles) && isset($bundles['DoctrineBundle'])) {
            $container->prependExtensionConfig('doctrine', [
                'dbal' => [
                    'types' => [
                        'uuid' => UuidType::class,
                    ],
                ],
                'orm' => [
                    'resolve_target_entities' => [
                        UserInterface::class => User::class,
                    ],
                ],
            ]);
        }
    }
}
