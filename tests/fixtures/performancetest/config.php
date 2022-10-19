<?php

use Faker\Factory;

$faker = Factory::create('de_DE');
$config = [];

foreach (range(0, 100) as $index) {
    $config['entry' . $index] = [
        'index' => $index,
        'value' => $faker->sentence(),
        'nested' => [
            'first' => rand(0, 10),
            'second' => rand(0, 10)
        ]
    ];
}

return $config;
