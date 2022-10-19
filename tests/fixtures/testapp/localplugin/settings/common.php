<?php

use Afeefa\Component\Settings\Config;

return [
    'localplugin' => [
        'common' => true,
        'development' => false,

        'delegates' => Config::delegate('author.plugin.delegates.first')
    ],

    'global' => [
        'list[]' => 'localplugin'
    ]
];
