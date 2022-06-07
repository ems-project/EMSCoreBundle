<?php

/*
 * This file is part of the FOSUserBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\UserBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This class contains the configuration information for the bundle.
 *
 * This information is solely responsible for how the different configuration
 * sections are normalized, and merged.
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('fos_user');
        $rootNode = $treeBuilder->getRootNode();

        $supportedDrivers = ['orm', 'custom'];

        $rootNode
            ->children()
                ->scalarNode('db_driver')
                    ->validate()
                        ->ifNotInArray($supportedDrivers)
                        ->thenInvalid('The driver %s is not supported. Please choose one of '.\json_encode($supportedDrivers))
                    ->end()
                    ->cannotBeOverwritten()
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('user_class')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('model_manager_name')->defaultNull()->end()
                ->booleanNode('use_authentication_listener')->defaultTrue()->end()
                ->booleanNode('use_listener')->defaultTrue()->end()
                ->booleanNode('use_flash_notifications')->defaultTrue()->end()
                ->booleanNode('use_username_form_type')->defaultTrue()->end()
                ->arrayNode('from_email')
                    ->isRequired()
                    ->children()
                        ->scalarNode('address')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('sender_name')->isRequired()->cannotBeEmpty()->end()
                    ->end()
                ->end()
            ->end()
            // Using the custom driver requires changing the manager services
            ->validate()
                ->ifTrue(function ($v) {
                    return 'custom' === $v['db_driver'] && 'fos_user.user_manager.default' === $v['service']['user_manager'];
                })
                ->thenInvalid('You need to specify your own user manager service when using the "custom" driver.')
            ->end()
            ->validate()
                ->ifTrue(function ($v) {
                    return 'custom' === $v['db_driver'] && !empty($v['group']) && 'fos_user.group_manager.default' === $v['group']['group_manager'];
                })
                ->thenInvalid('You need to specify your own group manager service when using the "custom" driver.')
            ->end();

        $this->addServiceSection($rootNode);

        return $treeBuilder;
    }

    private function addServiceSection(ArrayNodeDefinition $node)
    {
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('service')
                    ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('email_canonicalizer')->defaultValue('fos_user.util.canonicalizer.default')->end()
                            ->scalarNode('username_canonicalizer')->defaultValue('fos_user.util.canonicalizer.default')->end()
                            ->scalarNode('user_manager')->defaultValue('fos_user.user_manager.default')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }
}
