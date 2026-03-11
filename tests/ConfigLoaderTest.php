<?php

namespace Tests\Afeefa\Component\Settings;

use Afeefa\Component\Settings\ConfigLoader;
use Afeefa\Component\Settings\Environment;
use Afeefa\Component\Settings\Test\ConfigTestTrait;
use Afeefa\Component\TestingUtils\Reflection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class ConfigLoaderTest extends TestCase
{
    use ConfigTestTrait {ConfigTestTrait::addPaths as addConfigPaths;}

    public function test_load()
    {
        $loader = new ConfigLoader();
        $this->addConfigPaths($loader);

        $config = $loader->load(Environment::PRODUCTION);

        $this->assertConfig($config, Environment::PRODUCTION, false);

        $config = $loader->load(Environment::DEVELOPMENT);

        $this->assertConfig($config, Environment::DEVELOPMENT, false);

        $config = $loader->load(Environment::TEST);

        $this->assertConfig($config, Environment::TEST, false);
    }

    public function test_transform_paths_to_array()
    {
        $loader = $this->createLoader();

        $array = [
            'a.b.c' => true
        ];

        $transformed = $this->invokeMethod($loader, 'transformPathsToArrays', $array);

        $this->assertEquals([
            'a' => [
                'b' => [
                    'c' => true
                ]
            ]
        ], $transformed);

        // test nested

        $array = [
            'key' => 'value',
            'b.c' => true,
            'key2' => 'value2'
        ];

        $transformed = $this->invokeMethod($loader, 'transformPathsToArrays', $array);

        $this->assertEquals([
            'key' => 'value',
            'b' => [
                'c' => true
            ],
            'key2' => 'value2'
        ], $transformed);

        // test nested2

        $array = [
            'a' => [
                'key' => 'value',
                'b.c' => true,
                'key2' => 'value2'
            ]
        ];

        $transformed = $this->invokeMethod($loader, 'transformPathsToArrays', $array);

        $this->assertEquals([
            'a' => [
                'key' => 'value',
                'b' => [
                    'c' => true
                ],
                'key2' => 'value2'
            ]
        ], $transformed);

        // test nested3

        $array = [
            'a.b' => [
                'c' => [
                    'd.e' => [
                        'f' => true
                    ]
                ]
            ]
        ];

        $transformed = $this->invokeMethod($loader, 'transformPathsToArrays', $array);

        $this->assertEquals([
            'a' => [
                'b' => [
                    'c' => [
                        'd' => [
                            'e' => [
                                'f' => true
                            ]
                        ]
                    ]
                ]
            ]
        ], $transformed);
    }

    public function test_transform_paths_to_array_override()
    {
        $loader = $this->createLoader();

        $array = [
            'a.b.c' => 'true',
            'a' => 'false',
            'test' => [1, 2, 3]
        ];

        $transformed = $this->invokeMethod($loader, 'transformPathsToArrays', $array);

        $this->assertEquals([
            'a' => 'false',
            'test' => [1, 2, 3]
        ], $transformed);

        // test 2

        $array = [
            'a.b.c' => true,
            'a' => [
                'b' => false
            ],
            'test' => [1, 2, 3]
        ];

        $transformed = $this->invokeMethod($loader, 'transformPathsToArrays', $array);

        $this->assertEquals([
            'a' => [
                'b' => false
            ],
            'test' => [1, 2, 3]
        ], $transformed);
    }

    public function test_transform_paths_to_array_merge()
    {
        $loader = $this->createLoader();

        $array = [
            'a.b.c' => true,
            'a.b.d' => true,
            'a.b.d.e' => true
        ];

        $transformed = $this->invokeMethod($loader, 'transformPathsToArrays', $array);

        $this->assertEquals([
            'a' => [
                'b' => [
                    'c' => true,
                    'd' => [
                        'e' => true
                    ]
                ]
            ]
        ], $transformed);
    }

    public function test_deploy_file_loaded_after_local_and_secret()
    {
        $loader = new ConfigLoader();
        $pathFixtures = Path::join(__DIR__, 'fixtures', 'testapp');

        $loader->addPaths(
            $loader->path(Path::join($pathFixtures, 'settings'))
                ->files('common', '$ENV', 'local', 'secret', '$DEPLOY')
        );

        $loader->determineDeploymentFileKey(
            fn ($config) => $config->has('testapp.local') && $config->get('testapp.local') ? 'test_server' : null
        );

        $config = $loader->load(Environment::PRODUCTION);

        // deploy file was loaded and merged
        $this->assertEquals('test_server', $config->get('testapp.deploy'));
        $this->assertEquals('from_deploy_file', $config->get('deploy_setting'));

        // deploy file overrides values from common/local
        $this->assertEquals('overridden_by_deploy', $config->get('testapp.common'));
    }

    public function test_deploy_without_callback_skips()
    {
        $loader = new ConfigLoader();
        $pathFixtures = Path::join(__DIR__, 'fixtures', 'testapp');

        $loader->addPaths(
            $loader->path(Path::join($pathFixtures, 'settings'))
                ->files('common', '$ENV', 'local', 'secret', '$DEPLOY')
        );

        $config = $loader->load(Environment::PRODUCTION);

        $this->assertFalse($config->has('deploy_setting'));
        $this->assertFalse($config->has('testapp.deploy'));
    }

    public function test_deploy_callback_returns_null_skips()
    {
        $loader = new ConfigLoader();
        $pathFixtures = Path::join(__DIR__, 'fixtures', 'testapp');

        $loader->addPaths(
            $loader->path(Path::join($pathFixtures, 'settings'))
                ->files('common', '$ENV', 'local', 'secret', '$DEPLOY')
        );

        $loader->determineDeploymentFileKey(fn ($config) => null);

        $config = $loader->load(Environment::PRODUCTION);

        $this->assertFalse($config->has('deploy_setting'));
    }

    public function test_deploy_nonexistent_file_skips()
    {
        $loader = new ConfigLoader();
        $pathFixtures = Path::join(__DIR__, 'fixtures', 'testapp');

        $loader->addPaths(
            $loader->path(Path::join($pathFixtures, 'settings'))
                ->files('common', '$ENV', 'local', 'secret', '$DEPLOY')
        );

        $loader->determineDeploymentFileKey(fn ($config) => 'nonexistent_server');

        $config = $loader->load(Environment::PRODUCTION);

        $this->assertFalse($config->has('deploy_setting'));
    }

    public function test_deploy_callback_receives_config_with_local_values()
    {
        $loader = new ConfigLoader();
        $pathFixtures = Path::join(__DIR__, 'fixtures', 'testapp');

        $loader->addPaths(
            $loader->path(Path::join($pathFixtures, 'settings'))
                ->files('common', '$ENV', 'local', 'secret', '$DEPLOY')
        );

        $receivedValue = null;
        $loader->determineDeploymentFileKey(function ($config) use (&$receivedValue) {
            // local.php sets testapp.local = true (common.php sets it to false)
            $receivedValue = $config->get('testapp.local');
            return 'test_server';
        });

        $loader->load(Environment::PRODUCTION);

        // callback received config where local.php was already merged
        $this->assertTrue($receivedValue);
    }

    private function createLoader(): ConfigLoader
    {
        return new ConfigLoader();
    }

    private function addPaths()
    {
        // avoid trait collision
    }

    private function invokeMethod($object, string $methodName, ...$arguments)
    {
        return Reflection::invokeProtectedMethod($object, $methodName, ...$arguments);
    }
}
