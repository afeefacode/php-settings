<?php

namespace Tests\Afeefa\Component\Settings;

use Afeefa\Component\Settings\ConfigLoader;
use Afeefa\Component\Settings\Environment;
use Afeefa\Component\Settings\Test\ConfigTestTrait;
use Afeefa\Component\Settings\Test\TestReflectionUtils;
use PHPUnit\Framework\TestCase;

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
        return TestReflectionUtils::invokeMethod($object, $methodName, ...$arguments);
    }
}
