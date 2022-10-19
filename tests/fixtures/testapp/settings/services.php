<?php

use Tests\Kollektiv\Core\DI\Helpers\Services\ServiceCommon;
use function DI\create;

return [
    'common' => [
        ServiceCommon::class => create()
            ->constructor('testapp.common'),

        'testapp.common' => true
    ]
];
