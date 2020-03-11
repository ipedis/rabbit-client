<?php

use Ipedis\Demo\Rabbit\Utils\MessagePayloadValidator\MessagePayloadValidator;
use Ipedis\Demo\Rabbit\Worker\Binding;
use Ipedis\Demo\Rabbit\Worker\Dispatcher;
use Ipedis\Demo\Rabbit\Worker\Manager;
use Ipedis\Demo\Rabbit\Worker\Step1;
use Ipedis\Demo\Rabbit\Worker\Step2;
use Ipedis\Demo\Rabbit\Worker\Worker as WorkerProcess;
use Ipedis\Demo\Rabbit\Worker\WorkflowManager;
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;

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

$channelFactory = new ChannelFactory('v1', 'admin');
$messagePayloadValidator = new MessagePayloadValidator();

if ( !empty($argv[1]) ) {
    switch ($argv[1]) {
        case 'worker':
            (new WorkerProcess(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type']
            ))->execute();
        break;

        case 'manager':
            (new Manager(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type'],
                $channelFactory
            ))->main();
            break;


        case 'step1':
            (new Step1(
                $configEvent['host'],
                $configEvent['port'],
                $configEvent['use'],
                $configEvent['password'],
                $configEvent['exchange'],
                $configEvent['type']
            ))->main();
            break;

        case 'step2':
            (new Step2(
                $configEvent['host'],
                $configEvent['port'],
                $configEvent['use'],
                $configEvent['password'],
                $configEvent['exchange'],
                $configEvent['type']
            ))->main();
            break;

        case 'workflow':
            (new WorkflowManager(
                $configEvent['host'],
                $configEvent['port'],
                $configEvent['use'],
                $configEvent['password'],
                $configEvent['exchange'],
                $configEvent['type']
            ))->main();
            break;

        case 'binding':
            (new Binding(
                $configEvent['host'],
                $configEvent['port'],
                $configEvent['use'],
                $configEvent['password'],
                $configEvent['exchange'],
                $configEvent['type']
            ))->execute();
            break;

        case 'event':
            (new Dispatcher(
                $configEvent['host'],
                $configEvent['port'],
                $configEvent['use'],
                $configEvent['password'],
                $configEvent['exchange'],
                $configEvent['type'],
                $channelFactory,
                $messagePayloadValidator
            ))->main();
            break;
        default:
            printf("no match found \n");

    }
} else {
    printf('you should provide type parameter, possible value : %s', implode(', ', [
        'manager',
        'worker',
        'event',
        'binding'
    ]));
}
