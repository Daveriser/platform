<?php

namespace Oro\Bundle\LayoutBundle\Tests\Unit\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;

use Oro\Bundle\LayoutBundle\DependencyInjection\Configuration;

class ConfigurationTest extends \PHPUnit_Framework_TestCase
{
    public function testGetConfigTreeBuilder()
    {
        $configuration = new Configuration();
        $treeBuilder = $configuration->getConfigTreeBuilder();
        $this->assertInstanceOf(TreeBuilder::class, $treeBuilder);
    }

    public function testProcessConfiguration()
    {
        $configuration = new Configuration();
        $processor     = new Processor();
        $expected = [
            'settings' => [
                'resolved' => true,
                'development_settings_feature_enabled' => [
                    'value' => '%kernel.debug%',
                    'scope' => 'app'
                ],
                'debug_block_info' => [
                    'value' => false,
                    'scope' => 'app'
                ],
                'debug_developer_toolbar' => [
                    'value' => true,
                    'scope' => 'app'
                ],
            ],
            'view' => ['annotations' => true],
            'templating' => [
                'default' => 'twig',
                'php' => [
                    'enabled' => true,
                    'resources' => [Configuration::DEFAULT_LAYOUT_PHP_RESOURCE]
                ],
                'twig' => [
                    'enabled' => true,
                    'resources' => [Configuration::DEFAULT_LAYOUT_TWIG_RESOURCE]
                ]
            ],
            'themes' => [
                'oro-black' => [
                    'label' => 'Oro Black theme',
                    'config' => [
                        'page_templates' => [
                            'templates' => [
                                [
                                    'label' => 'Some label',
                                    'key' => 'some_key',
                                    'route_name' => 'some_route_name',
                                    'description' => null,
                                    'screenshot' => null
                                ]
                            ]
                        ],
                        'assets' => [],
                    ],
                    'groups' => []
                ]
            ],
            'debug' => '%kernel.debug%'
        ];

        $configs = [
            'oro_layout' => [
                'themes' => [
                    'oro-black' => [
                        'label' => 'Oro Black theme',
                        'config' => [
                            'page_templates' => [
                                'templates' => [
                                    [
                                        'label' => 'Some label',
                                        'key' => 'some_key',
                                        'route_name' => 'some_route_name',
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $this->assertEquals($expected, $processor->processConfiguration($configuration, $configs));
    }
}
