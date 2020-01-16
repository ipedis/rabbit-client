<?php

require __DIR__.'/../vendor/autoload.php';

$configOrder = [
    'host' => 'localhost',
    'port' => 5672,
    'use' => 'guest',
    'password' => 'guest',
    'exchange' => 'publispeak_orders',
    'type' => 'topic'
];

$configEvent = array_merge($configOrder, [
    'exchange' => 'publispeak_events',
]);


if ( !empty($argv[1]) ) {
    switch ($argv[1]) {
        case 'worker':
            (new \Ipedis\Demo\Rabbit\Worker\Worker(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type']
            ))->execute();
        break;

        case 'manager':
            (new \Ipedis\Demo\Rabbit\Worker\Manager(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type']
            ))->main();
            break;

        case 'binding':
            (new \Ipedis\Demo\Rabbit\Worker\Binding(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type']
            ))->execute();
            break;

        case 'event':
            (new \Ipedis\Demo\Rabbit\Worker\Dispatcher(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type']
            ))->main();
            break;

    }
} else {
    printf('you should provide type parameter, possible value : %s', implode(', ', [
        'manager',
        'worker',
        'event',
        'binding'
    ]));
}