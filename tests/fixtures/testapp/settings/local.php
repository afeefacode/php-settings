<?php

use Afeefa\Component\Settings\Test\TypedConfig2;

return [
    'testapp' => [
        'local' => true,

        'typed' => [
            'local' => true
        ],

        'retyped' => TypedConfig2::cast([ // does not work
            'local' => true
        ])
    ]
];
