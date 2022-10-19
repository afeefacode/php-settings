<?php

use Afeefa\Component\Settings\Config;
use Afeefa\Component\Settings\Test\TypedConfig;

return [
    'testapp' => TypedConfig::cast([
        'common' => true,
        'local' => false,
        'secret' => false,

        'typed' => TypedConfig::cast([
            'common' => true,
            'local' => false
        ]),

        'retyped' => TypedConfig::cast([
            'common' => true,
            'local' => false
        ]),

        'delegates' => Config::delegate('author.plugin.delegates')
    ]),

    'global' => [
        'list[]' => 'testapp',
        'params' => [
            'common' => 'testapp'
        ]
    ],

    'author.plugin.typed.testapp' => true
];
