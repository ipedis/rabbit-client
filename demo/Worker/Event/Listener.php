<?php

declare(strict_types=1);

namespace Ipedis\Demo\Rabbit\Worker\Event;

use Closure;
use Exception;
use Ipedis\Demo\Rabbit\Utils\WorkerAbstract;
use Ipedis\Rabbit\Event\EventListener;
use Ipedis\Rabbit\Lifecyle\Hook\OnAfterMessage;
use Ipedis\Rabbit\Lifecyle\Hook\OnBeforeMessage;
use Ipedis\Rabbit\MessagePayload\EventMessagePayload;

class Listener extends WorkerAbstract implements OnBeforeMessage, OnAfterMessage
{
    use EventListener;

    public const ENABLE_LIFE_CYCLE_PRINTING = true;

    /**
     * Process messages coming from queue
     */
    public function makeMessageHandler(): Closure
    {
        return function (EventMessagePayload $messagePayload): void {
            printf("%s - %s \n\n", 'makeMessageHandler', $messagePayload->getChannel());
        };
    }

    protected function onExportedPublication(EventMessagePayload $messagePayload)
    {
        printf("%s - %s \n\n", 'onExportedPublication specific handler', $messagePayload->getChannel());
    }

    protected function onUpdatedPublication(): Closure
    {
        return function (EventMessagePayload $messagePayload): void {
            printf("%s - %s \n\n", 'onUpdatedPublication specific handler with Closure', $messagePayload->getChannel());
        };
    }

    /**
     * Handle errors during processing of message
     */
    public function makeExceptionHandler(): Closure
    {
        return function (Exception $exception, ?EventMessagePayload $messagePayload): void {
            printf($exception->getMessage()."\n\n");
        };
    }

    /**
     *  Accepted return value:
     * - string
     * - Array of string
     * - RouteKeyResolverInterface
     */
    public function getBindingKey(): array
    {

//        return 'v1.preview.admin.publication.was-updated';
        return ['v1.admin.publication.*', 'v1.preview.publication.was-updated'];
    }

    /**
     * Optional method to have more filtering on what it will be handle by MakeMessageHandler closure.
     */
    protected function isSubscribed(string $eventName): bool
    {
        return in_array($eventName, [
            'v1.admin.publication.was-created',
            'v1.admin.publication.was-exported',
            'v1.preview.publication.was-updated',
            'v1.admin.publication.was-deleted',
        ]);
    }

    protected function getHandledMessages(): iterable
    {
        yield 'v1.admin.publication.was-exported' => ['method' => 'onExportedPublication'];
        yield 'v1.preview.publication.was-updated' => ['method' => 'onUpdatedPublication'];
    }

    /**
     * Hook to run before worker handles message
     */
    public function beforeMessageHandled(): void
    {
        printf("Worker lifecycle hook : before handling message..."."\n\n");
    }

    /**
     * Hook to run after worker handles message
     */
    public function afterMessageHandled(): void
    {
        printf("Worker lifecycle hook : after handling message..."."\n\n");
    }

    public function getQueuePrefix(): string
    {
        return 'demo.event';
    }
}
