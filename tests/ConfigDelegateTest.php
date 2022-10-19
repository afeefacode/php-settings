<?php

namespace Tests\Afeefa\Component\Settings;

use Afeefa\Component\Settings\Config;
use PHPUnit\Framework\TestCase;

class ConfigDelegateTest extends TestCase
{
    public function test_delegate()
    {
        $config = new Config([
            'a' => 'isA',
            'b' => Config::delegate('a')
        ]);

        $this->assertTrue($config->has('a'));
        $this->assertTrue($config->has('b'));

        $this->assertEquals('isA', $config->get('a'));
        $this->assertEquals('isA', $config->get('b'));

        $config->set('a', 'isAnotherA');

        $this->assertEquals('isAnotherA', $config->get('a'));
        $this->assertEquals('isAnotherA', $config->get('b'));

        $config->set('b', 'isNowB'); // removes delegate

        $this->assertEquals('isAnotherA', $config->get('a'));
        $this->assertEquals('isNowB', $config->get('b'));

        $config->set('b', Config::delegate('a')); // reset delegate

        $this->assertEquals('isAnotherA', $config->get('a'));
        $this->assertEquals('isAnotherA', $config->get('b'));
    }

    public function test_delegate_complex()
    {
        $config = new Config([
            'a' => 'isA',
            'b' => [
                Config::delegate('a'),
                Config::delegate('c')
            ],
            'c' => 'isC'
        ]);

        $this->assertTrue($config->has('a'));
        $this->assertTrue($config->has('b'));
        $this->assertTrue($config->has('b.0'));
        $this->assertTrue($config->has('b.1'));
        $this->assertTrue($config->has('c'));

        $this->assertEquals('isA', $config->get('a'));
        $this->assertEquals(['isA', 'isC'], $config->get('b')->toArray());

        $config->set('a', 'isAnotherA');

        $this->assertEquals('isAnotherA', $config->get('a'));
        $this->assertEquals(['isAnotherA', 'isC'], $config->get('b')->toArray());

        $config->set('c', [
            'isC' => 'yesIsC'
        ]);

        $this->assertEquals(['isC' => 'yesIsC'], $config->get('c')->toArray());
        $this->assertEquals(['isAnotherA', ['isC' => 'yesIsC']], $config->get('b')->toArray());
        $this->assertEquals([
            'a' => 'isAnotherA',
            'b' => [
                'isAnotherA',
                [
                    'isC' => 'yesIsC'
                ]
            ],
            'c' => [
                'isC' => 'yesIsC'
            ]
        ], $config->toArray());

        $config->set('b.0', 'isNowAB'); // removes b.0 delegate

        $this->assertEquals('isAnotherA', $config->get('a'));
        $this->assertEquals(['isNowAB', ['isC' => 'yesIsC']], $config->get('b')->toArray());

        $config->set('a', 'isAgainA');

        $this->assertEquals('isAgainA', $config->get('a'));
        $this->assertEquals(['isNowAB', ['isC' => 'yesIsC']], $config->get('b')->toArray());

        $config->set('b.1.isC', 'isUpdatedByB');

        $this->assertEquals(['isC' => 'isUpdatedByB'], $config->get('c')->toArray());
        $this->assertEquals(['isNowAB', ['isC' => 'isUpdatedByB']], $config->get('b')->toArray());

        $config->set('c.isC', 'isYetUpdatedByC');

        $this->assertEquals(['isC' => 'isYetUpdatedByC'], $config->get('c')->toArray());
        $this->assertEquals(['isNowAB', ['isC' => 'isYetUpdatedByC']], $config->get('b')->toArray());
    }

    public function test_merge_replace_delegate()
    {
        $config = new Config([
            'a' => 'isA',
            'b' => Config::delegate('a'),
            'c' => 'isC'
        ]);

        $this->assertEquals('isA', $config->get('b'));

        $config->merge([
            'b' => Config::delegate('c')
        ]);

        $this->assertEquals('isC', $config->get('b'));
    }
}
