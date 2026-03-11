<?php

use Tests\Kollektiv\Core\DI\Helpers\Services\TestService;
use function DI\create;

return [
    'common' => [
        'author2.plugin3.common' => true,
        'author2.plugin3.production' => false
    ],

    'production' => [
        'author2.plugin3.production' => true
    ],

    'test' => [
        TestService::class => create()
            ->constructor('author2.plugin3.test')
    ]
];
