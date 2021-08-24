<?php


use Ipedis\Demo\Rabbit\Utils\MessagePayloadValidator\MessagePayloadValidator;
use Ipedis\Demo\Rabbit\Worker\Event\Listener;
use Ipedis\Demo\Rabbit\Worker\Event\Dispatcher;
use Ipedis\Demo\Rabbit\Worker\Order\Manager as OrderManager;
use Ipedis\Demo\Rabbit\Worker\Workflow\Manager\GeneratorManager;
use Ipedis\Demo\Rabbit\Worker\Workflow\Manager\NoFailureManager;
use Ipedis\Demo\Rabbit\Worker\Workflow\Manager\ProgressManager;
use Ipedis\Demo\Rabbit\Worker\Workflow\Manager\RecursiveGeneratorManager;
use Ipedis\Demo\Rabbit\Worker\Workflow\Manager\ConcurrencyManager;
use Ipedis\Demo\Rabbit\Worker\Workflow\Manager\RetryOnFailureManager;
use Ipedis\Demo\Rabbit\Worker\Workflow\Worker\Generator\Html;
use Ipedis\Demo\Rabbit\Worker\Workflow\Worker\Generator\Image;
use Ipedis\Demo\Rabbit\Worker\Workflow\Worker\Success;
use Ipedis\Demo\Rabbit\Worker\Workflow\Worker\Failure;
use Ipedis\Demo\Rabbit\Worker\Order\Worker as WorkerProcess;
use Ipedis\Demo\Rabbit\Worker\Workflow\Manager\AllCallbackManager;
use Ipedis\Demo\Rabbit\Worker\Workflow\Worker\Waiter;
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;

require __DIR__.'/../vendor/autoload.php';

$configOrder = [
    'host' => 'localhost',
    'port' => 5672,
    'use' => 'guest',
    'password' => 'guest',
    'exchange' => 'rabbit-client_orders',
    'type' => 'topic'
];

$configEvent = array_merge($configOrder, [
    'exchange' => 'rabbit-client_events',
]);

$channelFactory = new ChannelFactory('v1', 'rabbitclient');
$messagePayloadValidator = new MessagePayloadValidator();

if (!empty($argv[1])) {
    switch ($argv[1]) {
        case 'worker':
            (new WorkerProcess(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type'],
                $channelFactory
            ))->execute();
        break;

        case 'manager':
            (new OrderManager(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type'],
                $channelFactory,
                $messagePayloadValidator
            ))->main();
            break;


        case 'success':
            (new Success(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type'],
                $channelFactory
            ))->execute();
            break;

        case 'failure':
            (new Failure(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type'],
                $channelFactory
            ))->execute();
            break;

        case 'waiter':
            (new Waiter(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type'],
                $channelFactory
            ))->execute();
            break;

        case 'workflow-callback':
            (new AllCallbackManager(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type'],
                $channelFactory,
                $messagePayloadValidator
            ))->main();
            break;
        case 'workflow-failure':
            (new NoFailureManager(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type'],
                $channelFactory,
                $messagePayloadValidator
            ))->main();
            break;
        case 'workflow-retry-on-failure':
            (new RetryOnFailureManager(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type'],
                $channelFactory,
                $messagePayloadValidator
            ))->main();
            break;
        case 'workflow-progress':
            (new ProgressManager(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type'],
                $channelFactory,
                $messagePayloadValidator
            ))->main();
            break;
        case 'listener':
            (new Listener(
                $configEvent['host'],
                $configEvent['port'],
                $configEvent['use'],
                $configEvent['password'],
                $configEvent['exchange'],
                $configEvent['type'],
                $channelFactory
            ))->execute();
            break;

        case 'dispatcher':
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
        case 'generator':
            (new GeneratorManager(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type'],
                $channelFactory,
                $messagePayloadValidator
            ))->main();
            break;
        case 'generator-recursive':
            (new RecursiveGeneratorManager(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type'],
                $channelFactory,
                $messagePayloadValidator
            ))->main();
            break;
        case 'concurrency-manager':
            (new ConcurrencyManager(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type'],
                $channelFactory,
                $messagePayloadValidator
            ))->main();
            break;
        case 'generator.html':
            (new Html(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type'],
                $channelFactory
            ))->execute();
        break;
        case 'generator.image':
            (new Image(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type'],
                $channelFactory
            ))->execute();
        break;
        case 'generator.image-page':
            (new Image\SimplePage(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type'],
                $channelFactory
            ))->execute();
        break;

        case 'generator.double-thumb':
            (new Image\ThumbnailDoublePage(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type'],
                $channelFactory
            ))->execute();
            break;
        case 'generator.double-zoomable':
            (new Image\ZoomableDoublePage(
                $configOrder['host'],
                $configOrder['port'],
                $configOrder['use'],
                $configOrder['password'],
                $configOrder['exchange'],
                $configOrder['type'],
                $channelFactory
            ))->execute();
            break;

        default:
            printf("no match found \n");

    }
} else {
    printf('you should provide type parameter, possible value : %s', implode(', ', [
        'manager',
        'worker',
        'dispatcher',
        'listener',
        'workflow-callback',
        'workflow-progress',
        'workflow-failure',
        'workflow-retry',
        'success',
        'failure',
        'waiter',
        'generator',
        'generator.html',
        'generator.image',
        'generator.image-page',
        'generator.double-thumb',
        'generator.double-zoomable',
    ]));
}
