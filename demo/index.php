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

if (isset($argv[1]) && ($argv[1] !== '' && $argv[1] !== '0')) {
    match ($argv[1]) {
        'worker' => (new WorkerProcess(
            $configOrder['host'],
            $configOrder['port'],
            $configOrder['use'],
            $configOrder['password'],
            $configOrder['exchange'],
            $configOrder['type'],
            $channelFactory
        ))->execute(),
        'manager' => (new OrderManager(
            $configOrder['host'],
            $configOrder['port'],
            $configOrder['use'],
            $configOrder['password'],
            $configOrder['exchange'],
            $configOrder['type'],
            $channelFactory,
            $messagePayloadValidator
        ))->main(),
        'success' => (new Success(
            $configOrder['host'],
            $configOrder['port'],
            $configOrder['use'],
            $configOrder['password'],
            $configOrder['exchange'],
            $configOrder['type'],
            $channelFactory
        ))->execute(),
        'failure' => (new Failure(
            $configOrder['host'],
            $configOrder['port'],
            $configOrder['use'],
            $configOrder['password'],
            $configOrder['exchange'],
            $configOrder['type'],
            $channelFactory
        ))->execute(),
        'waiter' => (new Waiter(
            $configOrder['host'],
            $configOrder['port'],
            $configOrder['use'],
            $configOrder['password'],
            $configOrder['exchange'],
            $configOrder['type'],
            $channelFactory
        ))->execute(),
        'workflow-callback' => (new AllCallbackManager(
            $configOrder['host'],
            $configOrder['port'],
            $configOrder['use'],
            $configOrder['password'],
            $configOrder['exchange'],
            $configOrder['type'],
            $channelFactory,
            $messagePayloadValidator
        ))->main(),
        'workflow-failure' => (new NoFailureManager(
            $configOrder['host'],
            $configOrder['port'],
            $configOrder['use'],
            $configOrder['password'],
            $configOrder['exchange'],
            $configOrder['type'],
            $channelFactory,
            $messagePayloadValidator
        ))->main(),
        'workflow-retry-on-failure' => (new RetryOnFailureManager(
            $configOrder['host'],
            $configOrder['port'],
            $configOrder['use'],
            $configOrder['password'],
            $configOrder['exchange'],
            $configOrder['type'],
            $channelFactory,
            $messagePayloadValidator
        ))->main(),
        'workflow-progress' => (new ProgressManager(
            $configOrder['host'],
            $configOrder['port'],
            $configOrder['use'],
            $configOrder['password'],
            $configOrder['exchange'],
            $configOrder['type'],
            $channelFactory,
            $messagePayloadValidator
        ))->main(),
        'listener' => (new Listener(
            $configEvent['host'],
            $configEvent['port'],
            $configEvent['use'],
            $configEvent['password'],
            $configEvent['exchange'],
            $configEvent['type'],
            $channelFactory
        ))->execute(),
        'dispatcher' => (new Dispatcher(
            $configEvent['host'],
            $configEvent['port'],
            $configEvent['use'],
            $configEvent['password'],
            $configEvent['exchange'],
            $configEvent['type'],
            $channelFactory,
            $messagePayloadValidator
        ))->main(),
        'generator' => (new GeneratorManager(
            $configOrder['host'],
            $configOrder['port'],
            $configOrder['use'],
            $configOrder['password'],
            $configOrder['exchange'],
            $configOrder['type'],
            $channelFactory,
            $messagePayloadValidator
        ))->main(),
        'generator-recursive' => (new RecursiveGeneratorManager(
            $configOrder['host'],
            $configOrder['port'],
            $configOrder['use'],
            $configOrder['password'],
            $configOrder['exchange'],
            $configOrder['type'],
            $channelFactory,
            $messagePayloadValidator
        ))->main(),
        'concurrency-manager' => (new ConcurrencyManager(
            $configOrder['host'],
            $configOrder['port'],
            $configOrder['use'],
            $configOrder['password'],
            $configOrder['exchange'],
            $configOrder['type'],
            $channelFactory,
            $messagePayloadValidator
        ))->main(),
        'generator.html' => (new Html(
            $configOrder['host'],
            $configOrder['port'],
            $configOrder['use'],
            $configOrder['password'],
            $configOrder['exchange'],
            $configOrder['type'],
            $channelFactory
        ))->execute(),
        'generator.image' => (new Image(
            $configOrder['host'],
            $configOrder['port'],
            $configOrder['use'],
            $configOrder['password'],
            $configOrder['exchange'],
            $configOrder['type'],
            $channelFactory
        ))->execute(),
        'generator.image-page' => (new Image\SimplePage(
            $configOrder['host'],
            $configOrder['port'],
            $configOrder['use'],
            $configOrder['password'],
            $configOrder['exchange'],
            $configOrder['type'],
            $channelFactory
        ))->execute(),
        'generator.double-thumb' => (new Image\ThumbnailDoublePage(
            $configOrder['host'],
            $configOrder['port'],
            $configOrder['use'],
            $configOrder['password'],
            $configOrder['exchange'],
            $configOrder['type'],
            $channelFactory
        ))->execute(),
        'generator.double-zoomable' => (new Image\ZoomableDoublePage(
            $configOrder['host'],
            $configOrder['port'],
            $configOrder['use'],
            $configOrder['password'],
            $configOrder['exchange'],
            $configOrder['type'],
            $channelFactory
        ))->execute(),
        default => printf("no match found \n"),
    };
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
