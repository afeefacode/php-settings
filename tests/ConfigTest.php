<?php

namespace Tests\Afeefa\Component\Settings;

use Afeefa\Component\Settings\Config;
use Afeefa\Component\Settings\ConfigVisitor;
use Afeefa\Component\Settings\IllegalValueException;
use Afeefa\Component\Settings\NotFoundException;
use Afeefa\Component\Settings\Test\TestReflectionUtils;
use Afeefa\Component\Settings\Test\TypedConfig;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private $testConfig = [
        'driver' => 'mysql',
        'credentials' => [
            'user' => 'root',
            'password' => 'you.wont.guess.it'
        ]
    ];

    public function test_init()
    {
        $config = new Config([
            'db' => $this->testConfig
        ]);

        $this->assertEquals('mysql', $config->get('db.driver'));
        $this->assertEquals('you.wont.guess.it', $config->get('db.credentials.password'));

        $credentials = $config->get('db.credentials');
        $this->assertEquals('you.wont.guess.it', $credentials->get('password'));
    }

    public function test_root()
    {
        $config = new Config([
            'db' => $this->testConfig
        ]);

        $this->assertEquals($config, $this->getProperty($config, 'root'));
        $this->assertEquals($config, $this->getProperty($config->get('db'), 'root'));
        $this->assertEquals($config, $this->getProperty($config->get('db.credentials'), 'root'));

        $config2 = new Config(['servers' => [
            'www.server.de',
            'www.server2.de'
        ]]);

        $this->assertEquals($config2, $this->getProperty($config2, 'root'));
        $this->assertEquals($config2, $this->getProperty($config2->get('servers'), 'root'));
        $config->set('app', $config2);

        $this->assertEquals($config, $this->getProperty($config2, 'root'));
        $this->assertEquals($config, $this->getProperty($config2->get('servers'), 'root'));
    }

    public function test_parent()
    {
        $config = new Config([
            'db' => $this->testConfig
        ]);

        $this->assertEquals(null, $this->getProperty($config, 'parent'));
        $this->assertEquals($config, $this->getProperty($config->get('db'), 'parent'));
        $this->assertEquals($config->get('db'), $this->getProperty($config->get('db.credentials'), 'parent'));

        $config2 = new Config(['servers' => [
            'www.server.de',
            'www.server2.de'
        ]]);

        $this->assertEquals(null, $this->getProperty($config2, 'parent'));
        $this->assertEquals($config2, $this->getProperty($config2->get('servers'), 'parent'));

        $config->set('app', $config2);

        $this->assertEquals($config, $this->getProperty($config2, 'parent'));
        $this->assertEquals($config2, $this->getProperty($config2->get('servers'), 'parent'));
    }

    public function test_cast_to_type()
    {
        $config = new Config([
            'untyped' => [
                'value' => 'untyped'
            ],
            'typed' => [
                '__class' => TypedConfig::class,
                'value' => 'typed'
            ],
            'typed2' => TypedConfig::cast([
                'value' => 'typed2'
            ]),
            'typed3' => Config::cast([
                'value' => 'typed3'
            ])
        ]);

        $this->assertEquals('untyped', $config->get('untyped.value'));
        $this->assertEquals('typed', $config->get('typed.value'));
        $this->assertEquals('typed2', $config->get('typed2.value'));

        $this->assertEquals(Config::class, get_class($config->get('untyped')));

        $this->assertEquals(TypedConfig::class, get_class($config->get('typed')));
        $this->assertTrue($config->get('typed') instanceof TypedConfig);
        $this->assertTrue($config->get('typed') instanceof Config);

        $this->assertEquals(TypedConfig::class, get_class($config->get('typed2')));
        $this->assertTrue($config->get('typed2') instanceof TypedConfig);
        $this->assertTrue($config->get('typed2') instanceof Config);

        $this->assertEquals(Config::class, get_class($config->get('typed3')));
        $this->assertTrue($config->get('typed3') instanceof Config);
    }

    public function test_cast_to_type_list()
    {
        $config = new Config([
            'list' => [
                'item1' => ['key' => 'value1'],
                'item2' => TypedConfig::cast(['key' => 'value2']),
                'item3' => TypedConfig::cast(['key' => 'value3'])
            ],
            'list2' => [
                ['key' => 'value1'],
                TypedConfig::cast(['key' => 'value2']),
                TypedConfig::cast(['key' => 'value3'])
            ]
        ]);

        $this->assertEquals('value1', $config->get('list.item1.key'));
        $this->assertEquals(Config::class, get_class($config->get('list.item1')));
        $this->assertEquals('value2', $config->get('list.item2.key'));
        $this->assertEquals(TypedConfig::class, get_class($config->get('list.item2')));
        $this->assertEquals('value3', $config->get('list.item3.key'));
        $this->assertEquals(TypedConfig::class, get_class($config->get('list.item3')));

        $this->assertEquals('value1', $config->get('list2.0.key'));
        $this->assertEquals(Config::class, get_class($config->get('list2.0')));
        $this->assertEquals('value2', $config->get('list2.1.key'));
        $this->assertEquals(TypedConfig::class, get_class($config->get('list2.1')));
        $this->assertEquals('value3', $config->get('list2.2.key'));
        $this->assertEquals(TypedConfig::class, get_class($config->get('list2.2')));
    }

    public function test_set_cast()
    {
        $config = new Config([]);

        $this->assertFalse($config->has('title'));

        $config->set('title', TypedConfig::cast([
            'greeting' => 'hello'
        ]));

        $this->assertEquals('hello', $config->get('title.greeting'));
        $this->assertEquals(TypedConfig::class, get_class($config->get('title')));

        $config->set('title.greeting', 'hello2');
        $this->assertEquals('hello2', $config->get('title.greeting'));
        $this->assertEquals(TypedConfig::class, get_class($config->get('title')));
    }

    public function test_nested_cast_delegate()
    {
        $config = new Config([
            'original' => [
                'value' => 'reallytrue'
            ]
        ]);

        $this->assertFalse($config->has('title'));

        $config->set('typed', TypedConfig::cast([
            'title' => 'hello',
            'original' => Config::delegate('original')
        ]));

        $this->assertEquals('reallytrue', $config->get('original.value'));
        $this->assertEquals('reallytrue', $config->get('typed.original.value'));
    }

    public function test_nested_extend_delegate()
    {
        $config = new Config([
            'original' => [
                'value' => 'reallytrue'
            ]
        ]);

        $this->assertFalse($config->has('title'));

        $config->set('typed', TypedConfig::extend([
            'title' => 'hello',
            'original' => Config::delegate('original')
        ]));

        $this->assertEquals('reallytrue', $config->get('original.value'));
        $this->assertEquals('reallytrue', $config->get('typed.original.value'));
    }

    public function test_cast_to_wrong_type()
    {
        $this->expectException(IllegalValueException::class);
        $this->expectExceptionMessage('Class for "typed" does not exist: NotexistingClass.');

        new Config([
            'untyped' => [
                'value' => 'untyped'
            ],
            'typed' => [
                '__class' => 'NotexistingClass',
                'value' => 'typed'
            ]
        ]);
    }

    public function test_init_numeric_lists()
    {
        $config = new Config([
            'servers' => [
                ['host' => 'host1'],
                ['host' => 'host2']
            ],
            'clouds' => [
                [
                    ['host' => 'cloud1.host1'],
                    ['host' => 'cloud1.host2']
                ],
                [
                    ['host' => 'cloud2.host1'],
                    ['host' => 'cloud2.host2']
                ]
            ]
        ]);

        $this->assertEquals('host1', $config->get('servers.0.host'));
        $this->assertEquals('host1', $config->get('servers.0')->get('host'));
        $this->assertEquals('host1', $config->get('servers.0')['host']);
        $this->assertEquals('host1', $config->get('servers')[0]['host']);
        $this->assertEquals('host1', $config->get('servers')[0]->get('host'));
        $this->assertEquals('host1', $config->get('servers')->get(0)['host']);
        $this->assertEquals('host1', $config->get('servers')->get(0)->get('host'));
        $this->assertEquals('host1', $config['servers'][0]['host']);
        $this->assertTrue($config->has('servers.0'));
        $this->assertTrue($config->has('servers.0.host'));
        $this->assertTrue($config->get('servers')->has(0));
        $this->assertTrue($config->get('servers')->get(0)->has('host'));
        $this->assertTrue($config['servers'][0]->has('host'));

        $this->assertTrue($config->has('servers.1'));
        $this->assertTrue($config->has('servers.1.host'));

        $this->assertFalse($config->has('servers.2'));
        $this->assertFalse($config->has('servers.2.host'));

        $this->assertEquals('cloud1.host2', $config->get('clouds.0.1.host'));
        $this->assertEquals('cloud2.host2', $config->get('clouds.1.1.host'));
        $this->assertFalse($config->has('clouds.1.2.host'));
        $this->assertFalse($config->has('clouds.2.0.host'));
    }

    /**
     * @dataProvider setInvalidDatatypeDataprovider
     */
    public function test_init_invalid_datatype($type, $info)
    {
        $this->expectException(IllegalValueException::class);

        // test from root config
        try {
            new Config([
                'test' => 'hoho',
                'invalid' => $type
            ]);
        } catch (\Exception $e) {
            $this->assertEquals('Value for "invalid" is not allowed: ' . $info . '.', $e->getMessage());
            throw $e;
        }
    }

    public function setInvalidDatatypeDataprovider()
    {
        return [
            [new \stdClass(), 'stdClass'],
            [function () {
            }, 'Closure'],
            [new RandomInvalidConfigType(), RandomInvalidConfigType::class]
        ];
    }

    /**
     * @dataProvider setValidDatatypeDataprovider
     */
    public function test_init_valid_datatype($type, $expectedType)
    {
        $config = new Config([
            'test' => 'hoho',
            'valid' => $type
        ]);

        $validValue = $config->get('valid');
        if ($validValue instanceof Config) {
            $validValue = $validValue->toArray();
        }

        $this->assertEquals($expectedType, $validValue);
    }

    public function setValidDatatypeDataprovider()
    {
        $config = new Config(['a' => 'b']);

        return [
            [null, null],
            ['', ''],
            [1, 1],
            [-1, -1],
            [1.3, 1.3],
            ['1.3', '1.3'],
            [$config, ['a' => 'b']],
            [[], []],
            [[[], []], [[], []]]
        ];
    }

    public function test_to_array()
    {
        $config = new Config([
            'db' => $this->testConfig
        ]);

        $this->assertEquals(['db' => $this->testConfig], $config->toArray());
    }

    public function test_visit()
    {
        $config = new Config([
            'db' => $this->testConfig
        ]);

        $result = [];
        $visitor = new ConfigVisitor((function (?Config $parent, string $parentKey, string $rootKey, Config $config) use (&$result) {
            $result[] = [
                'parent' => $parent ? $parent->toArray() : [],
                'parentKey' => $parentKey,
                'rootKey' => $rootKey,
                'config' => $config->toArray()
            ];
        }));
        $config->visit($visitor);

        // 'db' => [
        //     'driver' => 'mysql',
        //     'credentials' => [
        //         'user' => 'root',
        //         'password' => 'you.wont.guess.it'
        //     ]
        // ]
        $array = $config->toArray();
        $this->assertEquals([
            [
                'parent' => [],
                'parentKey' => '',
                'rootKey' => '',
                'config' => $array
            ],
            [
                'parent' => $array,
                'parentKey' => 'db',
                'rootKey' => 'db',
                'config' => $array['db']
            ],
            [
                'parent' => $array['db'],
                'parentKey' => 'credentials',
                'rootKey' => 'db.credentials',
                'config' => $array['db']['credentials']
            ]
        ], $result);
    }

    public function test_get_empty()
    {
        $config = new Config([
            'empty' => ''
        ]);

        $this->assertEquals('', $config->get('empty'));
    }

    public function test_get_default()
    {
        $config = new Config([]);

        $this->assertEquals('', $config->get('empty', ''));
        $this->assertEquals('', $config->get('empty.empty2.empty3', ''));
    }

    /**
     * @dataProvider getNotExistsDateProvider
     */
    public function test_get_not_exists($key, $messageKey)
    {
        $this->expectException(NotFoundException::class);

        $config = new Config([
            'test' => 'hoho',
            'exists' => [
                'exists2' => [
                    'key' => 'value'
                ]
            ]
        ]);

        // test from root config
        try {
            $this->assertEquals('', $config->get($key));
        } catch (\Exception $e) {
            $this->assertEquals('Identifier "' . $messageKey . '" is not defined.', $e->getMessage());
        }

        // test from sub config
        try {
            $this->assertEquals('', $config->get('exists')->get($key));
        } catch (\Exception $e) {
            $this->assertEquals('Identifier "' . $messageKey . '" is not defined.', $e->getMessage());
            throw $e;
        }
    }

    public function getNotExistsDateProvider()
    {
        return [
            ['unknown', 'unknown'],
            ['unknown.unknown2', 'unknown.unknown2'],
            ['test.unknown.unknown2', 'test.unknown.unknown2'],
            ['exists.exists2.key2', 'exists.exists2.key2'],
            ['exists.key2', 'exists.key2']
        ];
    }

    public function test_has()
    {
        $config = new Config([
            'mykey' => 'myvar'
        ]);

        $this->assertFalse($config->has('eins'));
        $this->assertFalse($config->has('eins.zwei'));
        $this->assertFalse($config->has('eins.zwei.drei'));

        $this->assertTrue($config->has('mykey'));
        $this->assertFalse($config->has('mykey.eins'));
        $this->assertFalse($config->has('mykey.eins.zwei'));
    }

    public function test_has_with_emtpy()
    {
        $config = new Config([
            'empty' => null,
            'empty2' => '',
            'empty3' => 0,
            'parent' => [
                'empty' => null,
                'empty2' => '',
                'empty3' => 0
            ]
        ]);

        $this->assertTrue($config->has('empty'));
        $this->assertTrue($config->has('empty2'));
        $this->assertTrue($config->has('empty3'));

        $this->assertTrue($config->has('parent.empty'));
        $this->assertTrue($config->has('parent.empty2'));
        $this->assertTrue($config->has('parent.empty3'));

        $this->assertFalse($config->has('empty', true));
        $this->assertFalse($config->has('empty2', true));
        $this->assertFalse($config->has('empty3', true));
        $this->assertFalse($config->has('parent.empty', true));
        $this->assertFalse($config->has('parent.empty2', true));
        $this->assertFalse($config->has('parent.empty3', true));
    }

    public function test_set()
    {
        $config = new Config([]);

        $this->assertFalse($config->has('title'));

        $config->set('title', 'hallo');

        $this->assertEquals('hallo', $config->get('title'));
    }

    public function test_set_purges_cache()
    {
        $config = new Config(['a' => 'b']);
        $this->assertFalse($config->hasCached('a'));

        $config->has('a');
        $config->has('b', true);
        $this->assertTrue($config->hasCached('a'));
        $this->assertFalse($config->hasCached('b'));

        $config->set('b', true);
        $this->assertFalse($config->hasCached('a'));
        $this->assertFalse($config->hasCached('b'));
    }

    public function test_set_config_purges_cache()
    {
        $config = new Config([]);
        $this->assertFalse($config->has('sub'));

        $configToSet = new Config(['new' => 'here']);
        $this->assertFalse($configToSet->hasCached('new'));

        $configToSet->has('new');
        $this->assertTrue($configToSet->hasCached('new'));

        $config->set('sub', $configToSet);
        $this->assertEquals(['new' => 'here'], $config->get('sub')->toArray());
        $this->assertFalse($configToSet->hasCached('new'));
    }

    public function test_set_config_purges_cache_complex()
    {
        $config = new Config([
            'level0' => [
                'level1' => [
                    'level2' => null
                ]
            ]
        ]);

        $this->assertFalse($config->hasCached('level0'));
        $this->assertFalse($config->hasCached('level0.level1'));
        $this->assertFalse($config->hasCached('level0.level1.level2'));

        $config->get('level0.level1.level2');

        $this->assertTrue($config->hasCached('level0'));
        $this->assertTrue($config->hasCached('level0.level1'));
        $this->assertTrue($config->hasCached('level0.level1.level2'));
        $this->assertTrue($config->get('level0.level1')->hasCached('level2'));
        $this->assertTrue($config->get('level0')->hasCached('level1'));
        $this->assertTrue($config->get('level0')->get('level1')->hasCached('level2'));

        $configToSet = new Config([
            'level3' => [
                'level4' => [
                    'level5' => true
                ]
            ]
        ]);

        $this->assertFalse($configToSet->hasCached('level3'));
        $this->assertFalse($configToSet->hasCached('level3.level4'));
        $this->assertFalse($configToSet->hasCached('level3.level4.level5'));

        $configToSet->get('level3.level4.level5');

        $this->assertTrue($configToSet->hasCached('level3'));
        $this->assertTrue($configToSet->hasCached('level3.level4'));
        $this->assertTrue($configToSet->hasCached('level3.level4.level5'));
        $this->assertTrue($configToSet->get('level3.level4')->hasCached('level5'));
        $this->assertTrue($configToSet->get('level3')->hasCached('level4'));
        $this->assertTrue($configToSet->get('level3')->get('level4')->hasCached('level5'));

        $config->set('level0.level1.level2', $configToSet);

        $this->assertFalse($config->hasCached('level0'));
        $this->assertFalse($config->hasCached('level0.level1'));
        $this->assertFalse($config->hasCached('level0.level1.level2'));

        $this->assertFalse($configToSet->hasCached('level3'));
        $this->assertFalse($configToSet->hasCached('level3.level4'));
        $this->assertFalse($configToSet->hasCached('level3.level4.level5'));
    }

    public function test_set_append()
    {
        $config = new Config([]);

        $this->assertFalse($config->has('title'));

        $config->set('title[]', 'hallo');

        $this->assertEquals(['hallo'], $config->get('title')->toArray());

        $config->set('title[]', 'hallo nochmal');

        $this->assertEquals(['hallo', 'hallo nochmal'], $config->get('title')->toArray());

        $config->set('nested', [
            'title[]' => 'hallo nested'
        ]);

        $this->assertEquals(['hallo nested'], $config->get('nested.title')->toArray());

        $config->set('nested.title[]', 'hallo nested nochmal');

        $this->assertEquals(['hallo nested', 'hallo nested nochmal'], $config->get('nested.title')->toArray());
    }

    public function test_set_complex()
    {
        $config = new Config([]);

        $this->assertFalse($config->has('db'));

        $config->set('db', $this->testConfig);

        $this->assertEquals('mysql', $config->get('db.driver'));
        $this->assertEquals('you.wont.guess.it', $config->get('db.credentials.password'));

        $credentials = $config->get('db.credentials');
        $this->assertEquals('you.wont.guess.it', $credentials->get('password'));
    }

    public function test_set_empty()
    {
        $config = new Config([]);

        $config->set('', 'somevar');
        $config->set(0, 'somevar');
        $config->set(' ', 'somevar');

        $this->assertFalse($config->has(''));

        $this->assertEquals('somevar', $config->get(0));
        $this->assertEquals('somevar', $config->get('0'));
        $this->assertEquals('somevar', $config->get(' '));
    }

    public function test_set_empty_null()
    {
        $this->expectException(\TypeError::class);

        $config = new Config([]);
        $config->set(null, 'somevar');
    }

    public function test_get_null()
    {
        $this->expectException(\TypeError::class);

        $config = new Config([]);
        $config->get(null);
    }

    /**
     * @dataProvider setNotExistsDateProvider
     */
    public function test_set_not_exists($key, $messageKey)
    {
        $this->expectException(NotFoundException::class);

        $config = new Config([
            'test' => 'hoho',
            'exists' => [
                'exists2' => [
                    'key' => 'value'
                ]
            ]
        ]);

        // test from root config
        try {
            $this->assertEquals('', $config->set($key, 'somevar'));
        } catch (\Exception $e) {
            $this->assertEquals('Identifier "' . $messageKey . '" is not a config object.', $e->getMessage());
        }

        // test from sub config
        try {
            $this->assertEquals('', $config->get('exists')->set($key, 'somevar'));
        } catch (\Exception $e) {
            $this->assertEquals('Identifier "' . $messageKey . '" is not a config object.', $e->getMessage());
            throw $e;
        }
    }

    public function setNotExistsDateProvider()
    {
        return [
            ['unknown.unknown2', 'unknown'],
            ['test.unknown2', 'test'],
            ['exists.exists2.key2.key3', 'exists.exists2.key2'],
            ['test.unknown.unknown2', 'test.unknown']
        ];
    }

    public function test_remove()
    {
        $config = new Config([
            'db' => $this->testConfig
        ]);

        $this->assertTrue($config->has('db.credentials.password'));
        $this->assertTrue($config->get('db.credentials')->has('password'));
        $this->assertTrue($config->get('db')->has('credentials.password'));
        $this->assertTrue($config->get('db')->get('credentials')->has('password'));

        $this->assertEquals('you.wont.guess.it', $config->get('db.credentials.password'));
        $this->assertEquals('you.wont.guess.it', $config->get('db.credentials')->get('password'));
        $this->assertEquals('you.wont.guess.it', $config->get('db')->get('credentials.password'));
        $this->assertEquals('you.wont.guess.it', $config->get('db')->get('credentials')->get('password'));

        $config->remove('db.credentials.password');

        $this->assertFalse($config->has('db.credentials.password'));
        $this->assertFalse($config->get('db.credentials')->has('password'));
        $this->assertFalse($config->get('db')->has('credentials.password'));
        $this->assertFalse($config->get('db')->get('credentials')->has('password'));
    }

    public function test_array()
    {
        $config = new Config([
            'assoc' => [
                'eins' => '1',
                'zwei' => '2'
            ],
            'numeric' => ['1', '2']
        ]);

        $this->assertTrue($config->has('assoc'));
        $this->assertTrue($config->has('assoc.eins'));
        $this->assertFalse($config->has('assoc.0'));
        $this->assertFalse($config->get('assoc')->has(0));

        $this->assertTrue($config->has('numeric'));
        $this->assertTrue($config->has('numeric.0'));
        $this->assertTrue($config->get('numeric')->has('0'));

        $this->assertEquals(['1', '2'], $config->get('numeric')->toArray());
    }

    public function test_array_access()
    {
        $config = new Config([
            'db' => $this->testConfig
        ]);

        $this->assertEquals($config->get('db'), $config['db']);
        $this->assertEquals($config->has('db'), isset($config['db']));

        $config['test'] = 'test';
        $this->assertEquals('test', $config->get('test'));

        unset($config['test']);
        $this->assertFalse($config->has('test'));
    }

    public function test_array_append()
    {
        $config = new Config([
            'list[]' => ['1', '2']
        ]);

        $this->assertFalse($config->has('list[]'));
        $this->assertTrue($config->has('list'));
        $this->assertEquals(['1', '2'], $config->get('list')->toArray());

        $config->set('list[]', ['3']);
        $this->assertEquals(['1', '2', '3'], $config->get('list')->toArray());

        $config->set('list[]', '4');
        $this->assertEquals(['1', '2', '3', '4'], $config->get('list')->toArray());

        $config->set('list[]', [['5']]);
        $this->assertEquals(['1', '2', '3', '4', ['5']], $config->get('list')->toArray());

        $config->set('list[]', ['assoc' => '6']);
        $this->assertEquals(['1', '2', '3', '4', ['5'], '6'], $config->get('list')->toArray());

        $config->set('list[]', [['assoc' => '7']]);
        $this->assertEquals([
            '1', '2', '3', '4', ['5'], '6',
            ['assoc' => '7']
        ], $config->get('list')->toArray());

        $config->set('list[]', [['assoc2' => '8', 'assoc2b' => '9']]);
        $this->assertEquals([
            '1', '2', '3', '4', ['5'], '6',
            ['assoc' => '7'],
            ['assoc2' => '8', 'assoc2b' => '9']
        ], $config->get('list')->toArray());

        $config->set('list[]', [['assoc2' => '10', 'assoc2b' => '11']]);
        $this->assertEquals([
            '1', '2', '3', '4', ['5'], '6',
            ['assoc' => '7'],
            ['assoc2' => '8', 'assoc2b' => '9'],
            ['assoc2' => '10', 'assoc2b' => '11']
        ], $config->get('list')->toArray());

        $config->set('list[]', ['assoc1' => '12', 'assoc2' => '13']);

        $this->assertEquals([
            '1', '2', '3', '4', ['5'], '6',
            ['assoc' => '7'],
            ['assoc2' => '8', 'assoc2b' => '9'],
            ['assoc2' => '10', 'assoc2b' => '11'],
            '12', '13'
        ], $config->get('list')->toArray());
    }

    public function test_array_append2()
    {
        $config = new Config([
            'list' => [
                'item1' => ['key' => 'value1'],
                'item2' => TypedConfig::cast(['key' => 'value2'])
            ]
        ]);

        $config->set('list[]', [
            'item3' => TypedConfig::cast(['key' => 'value3'])
        ]);

        $this->assertEquals('value3', $config->get('list.item3.key'));
        $this->assertEquals(TypedConfig::class, get_class($config->get('list.item3')));

        $config->set('list.item3.key', 'value3_updated');

        $this->assertEquals('value3_updated', $config->get('list.item3.key'));
        $this->assertEquals(TypedConfig::class, get_class($config->get('list.item3')));
    }

    public function test_array_append_cast()
    {
        $config = new Config([
            'key' => 'value',
            'key[]' => 'othervalue'
        ]);

        $this->assertEquals(['othervalue'], $config->get('key')->toArray());

        $config = new Config([
            'key' => ['a' => 'b'],
            'key[]' => 'othervalue'
        ]);

        $this->assertEquals(['othervalue'], $config->get('key')->toArray());
    }

    public function test_array_append_override()
    {
        $config = new Config([
            'key' => 'value',
            'key[]' => 'othervalue'
        ]);

        $this->assertEquals(['othervalue'], $config->get('key')->toArray());

        $config = new Config([
            'key' => ['a' => 'b'],
            'key[]' => 'othervalue'
        ]);

        $this->assertEquals(['othervalue'], $config->get('key')->toArray());
    }

    public function test_merge()
    {
        $config = new Config([
            'somekey' => 'somevalue',
            'somenested' => [
                'somekey' => 'somevalue',
                'list[]' => ['first'],
                'list2' => []
            ],
            'list' => ['first']
        ]);

        $this->assertEquals('somevalue', $config->get('somekey'));
        $this->assertEquals('somevalue', $config->get('somenested.somekey'));
        $this->assertEquals(['first'], $config->get('somenested.list')->toArray());
        $this->assertEquals([], $config->get('somenested.list2')->toArray());
        $this->assertEquals(['first'], $config->get('list')->toArray());

        $config->merge([
            'somekey' => 'someothervalue'
        ]);

        $this->assertEquals('someothervalue', $config->get('somekey'));
        $this->assertEquals('somevalue', $config->get('somenested.somekey'));
        $this->assertEquals(['first'], $config->get('somenested.list')->toArray());
        $this->assertEquals([], $config->get('somenested.list2')->toArray());
        $this->assertEquals(['first'], $config->get('list')->toArray());

        $config->merge([
            'somenested' => [
                'somekey' => 'someothervalue'
            ]
        ]);

        $this->assertEquals('someothervalue', $config->get('somekey'));
        $this->assertEquals('someothervalue', $config->get('somenested.somekey'));
        $this->assertEquals(['first'], $config->get('somenested.list')->toArray());
        $this->assertEquals([], $config->get('somenested.list2')->toArray());
        $this->assertEquals(['first'], $config->get('list')->toArray());

        $config->merge([
            'somenested' => [
                'list[]' => 'second'
            ]
        ]);

        $this->assertEquals('someothervalue', $config->get('somekey'));
        $this->assertEquals('someothervalue', $config->get('somenested.somekey'));
        $this->assertEquals(['first', 'second'], $config->get('somenested.list')->toArray());
        $this->assertEquals([], $config->get('somenested.list2')->toArray());
        $this->assertEquals(['first'], $config->get('list')->toArray());

        $config->merge([
            'somenested' => [
                'list' => ['renewed']
            ]
        ]);

        $this->assertEquals('someothervalue', $config->get('somekey'));
        $this->assertEquals('someothervalue', $config->get('somenested.somekey'));
        $this->assertEquals(['renewed'], $config->get('somenested.list')->toArray());
        $this->assertEquals([], $config->get('somenested.list2')->toArray());
        $this->assertEquals(['first'], $config->get('list')->toArray());

        $config->merge([
            'list[]' => ['second'],
            'list2' => null
        ]);

        $this->assertEquals('someothervalue', $config->get('somekey'));
        $this->assertEquals('someothervalue', $config->get('somenested.somekey'));
        $this->assertEquals(['renewed'], $config->get('somenested.list')->toArray());
        $this->assertEquals([], $config->get('somenested.list2')->toArray());
        $this->assertEquals(['first', 'second'], $config->get('list')->toArray());
        $this->assertNull($config->get('list2'));
    }

    public function test_merge2()
    {
        $config = new Config([
            'nested' => [
                'list1[]' => null,
                'list2' => null
            ],
            'list1[]' => null,
            'list2' => null
        ]);

        $this->assertEquals([null], $config->get('nested.list1')->toArray());
        $this->assertEquals(null, $config->get('nested.list2'));
        $this->assertEquals([null], $config->get('list1')->toArray());
        $this->assertEquals(null, $config->get('list2'));

        $config->merge([
            'nested' => [
                'list1[]' => null,
                'list2' => null
            ],
            'list1[]' => null,
            'list2' => null
        ]);

        $this->assertEquals([null, null], $config->get('nested.list1')->toArray());
        $this->assertEquals(null, $config->get('nested.list2'));
        $this->assertEquals([null, null], $config->get('list1')->toArray());
        $this->assertEquals(null, $config->get('list2'));

        $config->merge([
            'nested' => [
                'list1[]' => [] // ignored
            ],
            'list1[]' => [] // ignored
        ]);

        $this->assertEquals([null, null], $config->get('nested.list1')->toArray());
        $this->assertEquals(null, $config->get('nested.list2'));
        $this->assertEquals([null, null], $config->get('list1')->toArray());
        $this->assertEquals(null, $config->get('list2'));

        $config->merge([
            'nested' => [
                'list1[]' => [[]]
            ],
            'list1[]' => [[]]
        ]);

        $this->assertEquals([null, null, []], $config->get('nested.list1')->toArray());
        $this->assertEquals(null, $config->get('nested.list2'));
        $this->assertEquals([null, null, []], $config->get('list1')->toArray());
        $this->assertEquals(null, $config->get('list2'));

        $config->merge([
            'nested' => [
                'list1' => null
            ],
            'list1' => null
        ]);

        $this->assertEquals(null, $config->get('nested.list1'));
        $this->assertEquals(null, $config->get('nested.list2'));
        $this->assertEquals(null, $config->get('list1'));
        $this->assertEquals(null, $config->get('list2'));

        $config->merge([
            'nested' => [
                'list1[]' => [],
                'list2' => []
            ],
            'list1[]' => [],
            'list2' => []
        ]);

        $this->assertEquals([], $config->get('nested.list1')->toArray());
        $this->assertEquals([], $config->get('nested.list2')->toArray());
        $this->assertEquals([], $config->get('list1')->toArray());
        $this->assertEquals([], $config->get('list2')->toArray());

        $config->merge([
            'nested' => [
                'list1[]' => 'a',
                'list2' => ['a']
            ],
            'list1[]' => 'a',
            'list2' => ['a']
        ]);

        $this->assertEquals(['a'], $config->get('nested.list1')->toArray());
        $this->assertEquals(['a'], $config->get('nested.list2')->toArray());
        $this->assertEquals(['a'], $config->get('list1')->toArray());
        $this->assertEquals(['a'], $config->get('list2')->toArray());

        $config->merge([
            'nested' => [
                'list1[]' => 'b',
                'list2' => 'b'
            ],
            'list1[]' => 'b',
            'list2' => 'b'
        ]);

        $this->assertEquals(['a', 'b'], $config->get('nested.list1')->toArray());
        $this->assertEquals('b', $config->get('nested.list2'));
        $this->assertEquals(['a', 'b'], $config->get('list1')->toArray());
        $this->assertEquals('b', $config->get('list2'));

        $config->merge([
            'nested' => [
                'list2[]' => 'c'
            ],
            'list2[]' => 'c'
        ]);

        $this->assertEquals(['a', 'b'], $config->get('nested.list1')->toArray());
        $this->assertEquals(['c'], $config->get('nested.list2')->toArray());
        $this->assertEquals(['a', 'b'], $config->get('list1')->toArray());
        $this->assertEquals(['c'], $config->get('list2')->toArray());

        $config->remove('nested.list2');
        $config->remove('list2');

        $config->merge([
            'nested' => [
                'list2[]' => 'd'
            ],
            'list2[]' => 'd'
        ]);

        $this->assertEquals(['a', 'b'], $config->get('nested.list1')->toArray());
        $this->assertEquals(['d'], $config->get('nested.list2')->toArray());
        $this->assertEquals(['a', 'b'], $config->get('list1')->toArray());
        $this->assertEquals(['d'], $config->get('list2')->toArray());
    }

    public function test_merge_list()
    {
        $config = new Config([
            'list' => [
                ['key' => 'item1', 'value' => 'value1'],
                ['key' => 'item2', 'value' => 'value2']
            ]
        ]);

        $config->merge([
            'list' => [
                1 => ['value' => 'value2updated']
            ]
        ]);

        $this->assertEquals('value2updated', $config->get('list.1.value'));
    }

    public function test_index_access()
    {
        $config = new Config(['a', 'b', 'c']);

        $this->assertEquals('a', $config->get(0));
        $this->assertEquals('a', $config->get('0'));

        $config->set(0, 'a2');
        $this->assertEquals('a2', $config->get(0));
        $this->assertEquals('a2', $config->get('0'));

        $config->set('0', 'a3');
        $this->assertEquals('a3', $config->get(0));
        $this->assertEquals('a3', $config->get('0'));

        $config = new Config([
            'a', 'b', 'c',
            'd' => ['d1', 'd2']
        ]);

        $this->assertEquals('d1', $config->get('d.0'));

        $config->set('d.0', 'd1updated');

        $this->assertEquals('d1updated', $config->get('d.0'));
    }

    public function test_foreach()
    {
        $config = new Config([
            'key1' => [
                'list' => [1, 2, 3, 4]
            ],
            'key2' => 'key2',
            'key3' => [
                'subkey1' => 'subvalue1',
                'subkey2' => 'subvalue2',
                'subkey3' => 'subvalue3'
            ]
        ]);

        $expectedKeys = ['key1', 'key2', 'key3'];
        $index = 0;
        foreach ($config as $key => $value) {
            $this->assertEquals($expectedKeys[$index], $key);
            $index++;
        }

        $expectedValues = [1, 2, 3, 4];
        $index = 0;
        foreach ($config->get('key1.list') as $key => $value) {
            $this->assertEquals($index, $key);
            $this->assertEquals($expectedValues[$index], $value);
            $index++;
        }

        $expectedKeys = ['subkey1', 'subkey2', 'subkey3'];
        $expectedValues = ['subvalue1', 'subvalue2', 'subvalue3'];
        $index = 0;
        foreach ($config->get('key3') as $key => $value) {
            $this->assertEquals($expectedKeys[$index], $key);
            $this->assertEquals($expectedValues[$index], $value);
            $index++;
        }
    }

    protected function getProperty($object, string $name)
    {
        return TestReflectionUtils::getProperty($object, $name);
    }
}

class RandomInvalidConfigType
{
}
