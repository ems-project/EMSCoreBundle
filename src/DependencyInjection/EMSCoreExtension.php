<?php

namespace EMS\CoreBundle\DependencyInjection;

use Ramsey\Uuid\Doctrine\UuidType;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @see http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class EMSCoreExtension extends Extension implements PrependExtensionInterface
{
    const TRANS_DOMAIN = 'EMSCoreBundle';

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $yamlLoader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $xmlLoader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $xmlLoader->load('command.xml');
        $xmlLoader->load('controllers.xml');
        $xmlLoader->load('repositories.xml');
        $xmlLoader->load('view_types.xml');
        $yamlLoader->load('services.yml');
        $xmlLoader->load('controllers.xml');
        $xmlLoader->load('services.xml');

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
        $container->setParameter('ems_core.allow_user_registration', $config['allow_user_registration']);
        $container->setParameter('ems_core.trigger_job_from_web', $config['trigger_job_from_web']);
        $container->setParameter('ems_core.lock_time', $config['lock_time']);
        $container->setParameter('ems_core.template_options', $config['template_options']);
        $container->setParameter('ems_core.user_login_route', $config['user_login_route']);
        $container->setParameter('ems_core.user_logout_route', $config['user_logout_route']);
        $container->setParameter('ems_core.user_profile_route', $config['user_profile_route']);
        $container->setParameter('ems_core.user_registration_route', $config['user_registration_route']);
        $container->setParameter('ems_core.add_user_route', $config['add_user_route']);
        $container->setParameter('ems_core.application_menu_controller', $config['application_menu_controller']);
        $container->setParameter('ems_core.asset_config', $config['asset_config']);
        $container->setParameter('ems_core.tika_server', $config['tika_server']);
        $container->setParameter('ems_core.pre_generated_ouuids', $config['pre_generated_ouuids']);
        $container->setParameter('ems_core.version', $this->getCoreVersion($container->getParameter('kernel.root_dir')));
        $container->setParameter('ems_core.private_key', $config['private_key']);
        $container->setParameter('ems_core.public_key', $config['public_key']);
        $container->setParameter('ems_core.health_check_allow_origin', $config['health_check_allow_origin']);
        $container->setParameter('ems_core.tika_download_url', $config['tika_download_url']);
        $container->setParameter('ems_core.log_by_pass', $config['log_by_pass']);
        $container->setParameter('ems_core.log_level', $config['log_level']);
        $container->setParameter('ems_core.default_bulk_size', $config['default_bulk_size']);

        $this->loadLdap($container, $yamlLoader, $config['ldap'] ?? []);
    }

    public static function getCoreVersion(string $rootDir): string
    {
        $out = false;
        //try to identify the ems core version
        if (\file_exists($rootDir.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'composer.lock')) {
            $lockInfo = \json_decode(\file_get_contents($rootDir.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'composer.lock'), true);

            if (!empty($lockInfo['packages'])) {
                foreach ($lockInfo['packages'] as $package) {
                    if (!empty($package['name']) && 'elasticms/core-bundle' === $package['name']) {
                        if (!empty($package['version'])) {
                            $out = $package['version'];
                        }
                        break;
                    }
                }
            }
        }

        return $out;
    }

    public function prepend(ContainerBuilder $container)
    {
        // get all bundles
        $bundles = $container->getParameter('kernel.bundles');

        $coreVersion = $this->getCoreVersion($container->getParameter('kernel.root_dir'));

        $configs = $container->getExtensionConfig($this->getAlias());

        $globals = [
            'theme_color' => $configs[0]['theme_color'] ?? Configuration::THEME_COLOR,
            'ems_name' => $configs[0]['name'] ?? Configuration::NAME,
            'ems_shortname' => $configs[0]['shortname'] ?? Configuration::SHORTNAME,
            'ems_core_version' => $coreVersion,
            'paging_size' => $configs[0]['paging_size'] ?? Configuration::PAGING_SIZE,
            'circles_object' => $configs[0]['circles_object'] ?? Configuration::CIRCLES_OBJECT,
            'datepicker_daysofweek_highlighted' => $configs[0]['datepicker_daysofweek_highlighted'] ?? Configuration::DATEPICKER_DAYSOFWEEK_HIGHLIGHTED,
            'datepicker_weekstart' => $configs[0]['datepicker_weekstart'] ?? Configuration::DATEPICKER_WEEKSTART,
            'datepicker_format' => $configs[0]['datepicker_format'] ?? Configuration::DATEPICKER_FORMAT,
            'date_time_format' => $configs[0]['date_time_format'] ?? Configuration::DATE_TIME_FORMAT,
            'date_format' => $configs[0]['date_format'] ?? Configuration::DATE_FORMAT,
            'time_format' => $configs[0]['time_format'] ?? Configuration::TIME_FORMAT,
            'allow_user_registration' => $configs[0]['allow_user_registration'] ?? Configuration::ALLOW_USER_REGISTRATION,
            'trigger_job_from_web' => $configs[0]['trigger_job_from_web'] ?? Configuration::TRIGGER_JOB_FROM_WEB,
            'user_login_route' => $configs[0]['user_login_route'] ?? Configuration::USER_LOGIN_ROUTE,
            'user_logout_route' => $configs[0]['user_logout_route'] ?? Configuration::USER_LOGOUT_ROUTE,
            'user_profile_route' => $configs[0]['user_profile_route'] ?? Configuration::USER_PROFILE_ROUTE,
            'user_registration_route' => $configs[0]['user_registration_route'] ?? Configuration::USER_REGISTRATION_ROUTE,
            'add_user_route' => $configs[0]['add_user_route'] ?? Configuration::ADD_USER_ROUTE,
            'application_menu_controller' => $configs[0]['application_menu_controller'] ?? Configuration::APPLICATION_MENU_CONTROLLER,
        ];

        if (!empty($configs[0]['template_options'])) {
            $globals = \array_merge($globals, $configs[0]['template_options']);
        }

        if (isset($bundles['TwigBundle'])) {
            $container->prependExtensionConfig('twig', [
                'globals' => $globals,
                'form_themes' => [
                    '@EMSCore/form/fields.html.twig',
                ],
            ]);
        }

        if (isset($bundles['DoctrineBundle'])) {
            $container->prependExtensionConfig('doctrine', [
                'dbal' => [
                    'types' => [
                        'uuid' => UuidType::class,
                    ],
                ],
            ]);
        }

        $this->prependLocalUser($container, $configs, $bundles);
    }

    /**
     * @param array<mixed> $configs
     * @param mixed        $bundles
     */
    private function prependLocalUser(ContainerBuilder $container, array $configs, $bundles): void
    {
        if (isset($bundles['DoctrineBundle'])) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'resolve_target_entities' => [
                        'EMS\CoreBundle\Entity\UserInterface' => 'EMS\CoreBundle\Entity\User',
                    ],
                ],
            ]);
        }

        $fromEmail = [
            'address' => 'noreply@example.com',
            'sender_name' => 'elasticms',
        ];

        if (isset($configs[0]['from_email'])) {
            $fromEmail = $configs[0]['from_email'];
        }

        if (isset($bundles['FOSUserBundle'])) {
            $container->prependExtensionConfig('fos_user', [
                'db_driver' => 'orm',
                'from_email' => $fromEmail,
                'firewall_name' => 'main',
                'user_class' => 'EMS\CoreBundle\Entity\User',
                'profile' => [
                    'form' => [
                        'type' => 'EMS\CoreBundle\Form\UserProfileType',
                    ],
                ],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $ldapConfig
     */
    private function loadLdap(ContainerBuilder $container, Loader\YamlFileLoader $loader, array $ldapConfig): void
    {
        if ([] === $ldapConfig) {
            return;
        }

        $loader->load('ldap.yml');
        foreach ($ldapConfig as $name => $value) {
            $reference = \sprintf('ems_core.ldap.%s', $name);
            $container->setParameter($reference, $value);
        }
    }
}
