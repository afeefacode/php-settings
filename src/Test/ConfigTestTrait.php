<?php

namespace Afeefa\Component\Settings\Test;

use Afeefa\Component\Settings\Config;
use Afeefa\Component\Settings\ConfigDelegate;
use Afeefa\Component\Settings\ConfigLoader;
use Afeefa\Component\Settings\Environment;
use Symfony\Component\Filesystem\Path;

trait ConfigTestTrait
{
    protected function assertConfig(Config $config, string $env, bool $pluginsSortedCaseInsensitive)
    {
        // vendor/author/plugin

        $this->assertTrue($config->get('author.plugin.common'));
        $this->assertFalse($config->get('author.plugin.local'));
        $this->assertFalse($config->get('author.plugin.secret'));

        $this->assertEquals([
            'first' => 'author.plugin.delegates.first',
            'second' => 'author.plugin.delegates.second'
        ], $config->get('author.plugin.delegates')->toArray());

        if ($env === Environment::PRODUCTION) {
            $this->assertTrue($config->get('author.plugin.production'));
            $this->assertFalse($config->get('author.plugin.development'));
            $this->assertFalse($config->get('author.plugin.test'));
        } else {
            $this->assertFalse($config->get('author.plugin.production'));
        }

        if ($env === Environment::DEVELOPMENT) {
            $this->assertFalse($config->get('author.plugin.production'));
            $this->assertTrue($config->get('author.plugin.development'));
            $this->assertFalse($config->get('author.plugin.test'));
        } else {
            $this->assertFalse($config->get('author.plugin.development'));
        }

        if ($env === Environment::TEST) {
            $this->assertFalse($config->get('author.plugin.production'));
            $this->assertFalse($config->get('author.plugin.development'));
            $this->assertTrue($config->get('author.plugin.test'));
        } else {
            $this->assertFalse($config->get('author.plugin.test'));
        }

        $this->assertTrue($config->get('author.plugin.typed.author.plugin'));
        $this->assertTrue($config->get('author.plugin.typed.testapp'));

        // vendor/author/multiplugin

        $this->assertTrue($config->get('author2.plugin1.common'));
        $this->assertTrue($config->get('author2.plugin2.common'));
        $this->assertTrue($config->get('author2.plugin3.common'));

        if ($env === Environment::PRODUCTION) {
            $this->assertTrue($config->get('author2.plugin3.production'));
        } else {
            $this->assertFalse($config->get('author2.plugin3.production'));
        }

        if ($env === Environment::DEVELOPMENT) {
            $this->assertTrue($config->get('author2.plugin1.development'));
        } else {
            $this->assertFalse($config->get('author2.plugin1.development'));
        }

        if ($env === Environment::TEST) {
            $this->assertTrue($config->get('author2.plugin2.test'));
        } else {
            $this->assertFalse($config->get('author2.plugin2.test'));
        }

        // localplugin

        $this->assertTrue($config->get('localplugin.common'));

        $this->assertEquals('author.plugin.delegates.first', $config->get('localplugin.delegates'));
        $raw = $config->raw('localplugin.delegates');
        $this->assertInstanceOf(ConfigDelegate::class, $raw);
        $this->assertEquals('author.plugin.delegates.first', TestReflectionUtils::getProperty($raw, 'rootKey'));

        if ($env === Environment::DEVELOPMENT) {
            $this->assertTrue($config->get('localplugin.development'));
        } else {
            $this->assertFalse($config->get('localplugin.development'));
        }

        // testapp

        $this->assertEquals(TypedConfig::class, get_class($config->get('testapp')));
        $this->assertTrue($config->get('testapp') instanceof TypedConfig);
        $this->assertTrue($config->get('testapp') instanceof Config);

        $this->assertTrue($config->get('testapp.common'));
        $this->assertTrue($config->get('testapp.local'));
        $this->assertTrue($config->get('testapp.secret'));

        $this->assertEquals(TypedConfig::class, get_class($config->get('testapp.typed')));
        $this->assertTrue($config->get('testapp.typed') instanceof TypedConfig);
        $this->assertTrue($config->get('testapp.typed') instanceof Config);

        $this->assertTrue($config->get('testapp.typed.common'));
        $this->assertTrue($config->get('testapp.typed.local'));

        // retyped (does not work, @see testapp/settings/local.php)
        $this->assertEquals(TypedConfig::class, get_class($config->get('testapp.retyped')));
        $this->assertTrue($config->get('testapp.retyped') instanceof TypedConfig);
        $this->assertTrue($config->get('testapp.retyped') instanceof Config);

        $this->assertTrue($config->get('testapp.retyped.common'));
        $this->assertTrue($config->get('testapp.retyped.local'));

        $this->assertEquals([
            'first' => 'author.plugin.delegates.first',
            'second' => 'author.plugin.delegates.second'
        ], $config->get('testapp.delegates')->toArray());
        $raw = $config->raw('testapp.delegates');
        $this->assertInstanceOf(ConfigDelegate::class, $raw);
        $this->assertEquals('author.plugin.delegates', TestReflectionUtils::getProperty($raw, 'rootKey'));

        // global list

        if ($pluginsSortedCaseInsensitive) {
            $this->assertEquals([
                'author.plugin',
                'author2.plugin1',
                'author2.plugin2',
                'author2.plugin3',
                'localplugin',
                'testapp'
            ], $config->get('global.list')->toArray());
        } else {
            $this->assertEquals([
                'author.plugin',
                'author2.plugin3', // case sensitive plugin name
                'author2.plugin1',
                'author2.plugin2',
                'localplugin',
                'testapp'
            ], $config->get('global.list')->toArray());
        }

        // global config

        if ($env === Environment::PRODUCTION) {
            $this->assertEquals([
                'common' => 'testapp',
                'development' => 'author.plugin',
                'production' => 'author.plugin',
                'test' => 'author.plugin'
            ], $config->get('global.params')->toArray());
        }

        if ($env === Environment::DEVELOPMENT) {
            $this->assertEquals([
                'common' => 'testapp',
                'development' => 'localplugin',
                'production' => 'author.plugin',
                'test' => 'author.plugin'
            ], $config->get('global.params')->toArray());
        }

        if ($env === Environment::TEST) {
            $this->assertEquals([
                'common' => 'testapp',
                'development' => 'author.plugin',
                'production' => 'author.plugin',
                'test' => $pluginsSortedCaseInsensitive ? 'author2.plugin3' : 'author2.plugin2'
            ], $config->get('global.params')->toArray());
        }
    }

    protected function addPaths(ConfigLoader $loader)
    {
        $pathFixtures = Path::join(__DIR__, '..', '..', 'tests', 'fixtures', 'testapp');

        $loader->addPaths(
            $loader->path(Path::join($pathFixtures, 'vendor', 'author', 'plugin', 'settings'))
                ->files('common', '$ENV'),
            $loader->path(Path::join($pathFixtures, 'vendor', 'author', 'plugin', 'plugins', '*', 'settings'))
                ->files('common', '$ENV'),
            $loader->path(Path::join($pathFixtures, 'vendor', 'author2', 'multiplugin', 'settings'))
                ->files('common', '$ENV'),
            $loader->path(Path::join($pathFixtures, 'vendor', 'author2', 'multiplugin', 'plugins', '*', 'settings'))
                ->files('common', '$ENV'),
            $loader->path(Path::join($pathFixtures, 'localplugin', 'settings'))
                ->files('common', '$ENV'),
            $loader->path(Path::join($pathFixtures, 'localplugin', 'plugins', '*', 'settings'))
                ->files('common', '$ENV'),
            $loader->path(Path::join($pathFixtures, 'settings', 'app'))
                ->files('common', '$ENV', 'local', 'secret')
        );
    }
}
