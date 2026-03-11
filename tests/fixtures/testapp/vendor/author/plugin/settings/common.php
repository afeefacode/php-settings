<?php

use Afeefa\Component\Settings\Test\TypedConfig;

return [
    'author' => [
        'plugin' => [
            'common' => true,
            'production' => false,
            'development' => false,
            'test' => false,
            'secret' => false, // never overridden since secret.php not included for plugins
            'local' => false, // never overridden since local.php not included for plugins

            'delegates' => [
                'first' => 'author.plugin.delegates.first',
                'second' => 'author.plugin.delegates.second'
            ],

            'typed' => TypedConfig::cast([
                'author.plugin' => true
            ])
        ]
    ],

    'global' => [
        'list[]' => 'author.plugin',
        'params' => [
            'common' => 'author.plugin',
            'development' => 'author.plugin',
            'production' => 'author.plugin',
            'test' => 'author.plugin'
        ]
    ]
];
