<?php

declare(strict_types=1);

namespace ABTests\Notifications\Jobs;

use ABTests\Notifications\NotificationDispatcher;
use ABTests\Notifications\NotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued job that hands a notification payload to the dispatcher. Running
 * delivery off-queue ensures that channel failures (network timeouts, Slack
 * rate limits, SMTP errors) never block the request that triggered the event.
 *
 * Retry policy: 3 attempts with exponential back-off (30 s → 60 s → 120 s).
 * Queue connection and queue name are pulled from config at dispatch time so
 * the consumer can route notification jobs to a dedicated low-priority queue.
 */
final class DispatchNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];

    public function __construct(public readonly NotificationPayload $payload)
    {
        $this->onConnection(config('ab-testing.notifications.queue_connection') ?? config('queue.default'));
        $this->onQueue(config('ab-testing.notifications.queue_name', 'default'));
    }

    public function handle(NotificationDispatcher $dispatcher): void
    {
        $dispatcher->dispatch($this->payload);
    }
}
