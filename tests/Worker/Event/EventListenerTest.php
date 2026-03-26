<?php

declare(strict_types=1);

namespace Ipedis\Test\Rabbit\Worker\Event;

use AMQPEnvelope;
use AMQPQueue;
use Ipedis\Demo\Rabbit\Worker\Event\Listener;
use Ipedis\Rabbit\Channel\Factory\ChannelFactory;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class EventListenerTest extends TestCase
{
    private MockObject $envelopMock;

    private MockObject $eventListenerMock;

    protected function setUp(): void
    {
        $this->envelopMock = $this->createMock(AMQPEnvelope::class);

        $this->envelopMock->method('getBody')
            ->willReturn('{
            "header": {"channel": "v1.admin.publication.was-created"},
            "data": {}
        }');
        $this->envelopMock->method('getDeliveryTag')
            ->willReturn(1);

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
            ->getMock();
    }

    #[Test]
    public function it_should_call_make_message_handler_callback(): void
    {
        $this->expectOutputRegex('/Worker lifecycle hook/');

        $isCalled = false;
        $makeMessageHandler = function ($payload) use (&$isCalled): void {
            $isCalled = true;
        };

        $this->eventListenerMock
            ->method('makeMessageHandler')
            ->willReturn($makeMessageHandler);

        $this->eventListenerMock->main($this->envelopMock, $this->createStub(AMQPQueue::class));
        $this->assertTrue($isCalled);
    }

    #[Test]
    public function it_should_call_make_error_handler_callback_when_exception_is_throw(): void
    {
        $this->expectOutputRegex('/Worker lifecycle hook/');

        $isCalled = false;
        $makeMessageHandler = function (): never {
            throw new LogicException('error');
        };
        $makeExceptionHandler = function () use (&$isCalled): void {
            $isCalled = true;
        };

        $this->eventListenerMock
            ->method('makeMessageHandler')
            ->willReturn($makeMessageHandler);

        $this->eventListenerMock
            ->method('makeExceptionHandler')
            ->willReturn($makeExceptionHandler);

        $this->eventListenerMock->main($this->envelopMock, $this->createStub(AMQPQueue::class));
        $this->assertTrue($isCalled);
    }
}
