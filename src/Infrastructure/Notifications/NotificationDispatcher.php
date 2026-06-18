<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Notifications;

use ABTests\Infrastructure\Notifications\Channels\MailChannel;
use ABTests\Infrastructure\Notifications\Channels\SlackChannel;
use ABTests\Infrastructure\Notifications\Channels\WebhookChannel;

/**
 * Reads the notification config and delivers a payload to every enabled
 * channel. Each channel failure is isolated — an exception in one channel
 * never prevents the others from running.
 */
final readonly class NotificationDispatcher
{
    public function __construct(
        private WebhookChannel $webhook,
        private SlackChannel $slack,
        private MailChannel $mail,
    ) {
        //
    }

    public function dispatch(NotificationPayload $payload): void
    {
        /** @var array<string, mixed> $channels */
        $channels = config('ab-testing.notifications.channels', []);

        if (! empty($channels['webhook']['enabled'])) {
            $this->webhook->send($payload);
        }

        if (! empty($channels['slack']['enabled'])) {
            $this->slack->send($payload);
        }

        if (! empty($channels['mail']['enabled'])) {
            $this->mail->send($payload);
        }
    }
}
