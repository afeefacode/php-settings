<?php

use Tests\Kollektiv\Core\DI\Helpers\Services\ServiceDevelopment;
use function DI\create;

return [
    'common' => [
        'localplugin.common' => true,
        'localplugin.development' => false
    ],

    'development' => [
        'localplugin.development' => true,

        ServiceDevelopment::class => create()
            ->constructor('localplugin.development')
    ]
];
