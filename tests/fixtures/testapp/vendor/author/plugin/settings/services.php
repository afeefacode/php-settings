<?php

use Tests\Kollektiv\Core\DI\Helpers\Services\ServiceCommon;
use Tests\Kollektiv\Core\DI\Helpers\Services\ServiceDevelopment;
use Tests\Kollektiv\Core\DI\Helpers\Services\ServiceProduction;
use Tests\Kollektiv\Core\DI\Helpers\Services\TestService;
use function DI\create;

return [
    'common' => [
        ServiceCommon::class => create()
            ->constructor('author.plugin.common'),

        ServiceDevelopment::class => create()
            ->constructor('author.plugin.common'),

        ServiceProduction::class => create()
            ->constructor('author.plugin.common'),

        TestService::class => create()
            ->constructor('author.plugin.common'),

        'author.plugin.common' => true,
        'author.plugin.production' => false,
        'author.plugin.development' => false,
        'author.plugin.test' => false
    ],

    'production' => [
        'author.plugin.production' => true
    ],

    'development' => [
        'author.plugin.development' => true
    ],

    'test' => [
        'author.plugin.test' => true
    ]
];
