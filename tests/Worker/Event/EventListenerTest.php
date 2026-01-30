<?php

use Ipedis\Demo\Rabbit\Worker\Event\Listener;
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use Ipedis\Rabbit\Event\EventListener;

/**
 * can be a good source of inspiration
 * https://github.com/php-amqplib/RabbitMqBundle/blob/master/Tests/RabbitMq/ConsumerTest.php
 */
it('Should call makeMessageHandler callback', function (): void {
    $isCalled = false;
    $makeMessageHandler = function ($payload) use (&$isCalled): void {
        $isCalled = true;
    };
    // when method makeMessageHandler is call, we return $makeMessageHandler closure.
    $this->eventListenerMock
        ->method('makeMessageHandler')
        ->willReturn($makeMessageHandler)
    ;

    $this->eventListenerMock->main($this->envelopMock, $this->queueMock);
    // if Closure is run, then $isCall will be turned to true.
    $this->assertTrue($isCalled);
});


it('Should call makeErrorHandler callback when exception is throw', function (): void {
    $isCalled = false;
    $makeMessageHandler = function (): void {
        throw new LogicException('error');
    };
    $makeExceptionHandler = function () use (&$isCalled): void {
        $isCalled = true;
    };
    // when method makeMessageHandler is call, we return $makeMessageHandler closure.
    $this->eventListenerMock
        ->method('makeMessageHandler')
        ->willReturn($makeMessageHandler)
    ;

    $this->eventListenerMock
        ->method('makeExceptionHandler')
        ->willReturn($makeExceptionHandler)
    ;

    $this->eventListenerMock->main($this->envelopMock, $this->queueMock);
    // if $makeExceptionHandler Closure is run, then $isCall will be turned to true.
    $this->assertTrue($isCalled);
});

/**
 * - SECTION  SETUP -
 * we Mock AMQPEnvelope, goal is to at least have getBody which return valid stringify json.
 */
beforeEach(function (): void {
    $this->envelopMock = $this->createMock(AMQPEnvelope::class);

    $this->envelopMock->method('getBody')
        ->willReturn('{
            "header": {"channel": "v1.admin.publication.was-created"},
            "data": {}
        }');
    $this->envelopMock->method('getDeliveryTag')
        ->willReturn(1);
    /**
     * we Mock AMQPQueue
     */
    $this->queueMock = $this->createMock(AMQPQueue::class);

    /**
     * we Mock trait EventListener
     */
    $this->eventListenerMock = $this->getMockBuilder(Listener::class)
        ->setConstructorArgs([
            'host' => 'localhost',
            'port' => 5672,
            'user' => 'guest',
            'password' => 'guest',
            'exchange' => 'rabbit-client_events',
            'type' => 'topic',
            'channelFactory' => new ChannelFactory('v1', 'rabbitclient'),
        ])
        ->onlyMethods([
            'makeMessageHandler',
            'makeExceptionHandler',
        ])
        ->getMock()
    ;
//    $this->eventListenerMock
//        ->method('getChannelFactory')
//        ->willReturn(new ChannelFactory('v1', 'rabbitclient'))
//    ;
});
