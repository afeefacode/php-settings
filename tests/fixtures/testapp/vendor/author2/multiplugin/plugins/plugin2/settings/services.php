<?php

use Tests\Kollektiv\Core\DI\Helpers\Services\TestService;
use function DI\create;

return [
    'common' => [
        'author2.plugin2.common' => true,
        'author2.plugin2.test' => false
    ],

    'test' => [
        'author2.plugin2.test' => true,

        TestService::class => create()
            ->constructor('author2.plugin2.test')
    ]
];
