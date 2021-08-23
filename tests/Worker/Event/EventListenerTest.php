<?php


use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use Ipedis\Rabbit\Event\EventListener;



it('Should call makeMessageHandler callback', function () {
    $isCalled = false;
    $makeMessageHandler = function () use (&$isCalled) {
        $isCalled = true;
    };
    // when method makeMessageHandler is call, we return $makeMessageHandler closure.
    $this->eventListenerMock
        ->method('makeMessageHandler')
        ->will($this->returnValue($makeMessageHandler))
    ;

    $this->eventListenerMock->main($this->envelopMock,  $this->queueMock);
    // if Closure is run, then $isCall will be turned to true.
    $this->assertTrue($isCalled);
});


it('Should call makeErrorHandler callback when exception is throw', function () {
    $isCalled = false;
    $makeMessageHandler = function () {
        throw new LogicException('error');
    };
    $makeExceptionHandler = function () use (&$isCalled) {
        $isCalled = true;
    };
    // when method makeMessageHandler is call, we return $makeMessageHandler closure.
    $this->eventListenerMock
        ->method('makeMessageHandler')
        ->will($this->returnValue($makeMessageHandler))
    ;

    $this->eventListenerMock
        ->method('makeExceptionHandler')
        ->will($this->returnValue($makeExceptionHandler))
    ;

    $this->eventListenerMock->main($this->envelopMock,  $this->queueMock);
    // if $makeExceptionHandler Closure is run, then $isCall will be turned to true.
    $this->assertTrue($isCalled);
});

/**
 * - SECTION  SETUP -
 * we Mock AMQPEnvelope, goal is to at least have getBody which return valid stringify json.
 */
beforeEach(function () {
    $this->envelopMock = $this->getMockBuilder(AMQPEnvelope::class)
        ->disableOriginalConstructor()
        ->getMock();

    $this->envelopMock->method('getBody')
        ->will($this->returnValue('{
            "header": {"channel": "v1.admin.publication.was-created"},
            "data": {}
        }'));
    /**
     * we Mock AMQPQueue
     */
    $this->queueMock = $this->getMockBuilder(AMQPQueue::class)
        ->disableOriginalConstructor()
        ->getMock();

    /**
     * we Mock trait EventListener
     */

    $this->eventListenerMock = $this->getMockForTrait(EventListener::class);
    $this->eventListenerMock
        ->method('getChannelFactory')
        ->will($this->returnValue(new ChannelFactory('v1', 'rabbitclient')))
    ;
});
