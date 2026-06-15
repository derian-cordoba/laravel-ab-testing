<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Notifications\Channels;

use ABTests\Infrastructure\Notifications\NotificationPayload;
use DateTimeInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Delivers a notification payload by email to the configured list of
 * recipients. Uses Laravel's Mail facade with an inline HTML body so no
 * Blade view file is required inside the package.
 */
final readonly class MailChannel
{
    public function send(NotificationPayload $payload): void
    {
        /** @var array<string, mixed> $config */
        $config = config('ab-testing.notifications.channels.mail', []);

        /** @var list<string> $recipients */
        $recipients = $config['recipients'] ?? [];

        if ($recipients === []) {
            Log::warning('[ABTesting] MailChannel: no recipients configured; skipping.');
            return;
        }

        $subject = '[A/B Testing] ' . $payload->title;
        $html    = $this->buildHtml($payload);

        $mailable = new class ($subject, $recipients, $html) extends Mailable {
            /** @param list<string> $recipients */
            public function __construct(
                private string $mailSubject,
                private array $recipients,
                private string $mailBody,
            ) {
            }

            public function envelope(): Envelope
            {
                return new Envelope(
                    subject: $this->mailSubject,
                    to: $this->recipients,
                );
            }

            public function content(): Content
            {
                return new Content(htmlString: $this->mailBody);
            }
        };

        try {
            Mail::send($mailable);
        } catch (Throwable $e) {
            Log::error(
                '[ABTesting] MailChannel: exception during delivery.',
                ['event' => $payload->event, 'error' => $e->getMessage()],
            );
        }
    }

    private function buildHtml(NotificationPayload $payload): string
    {
        $rows = '';

        if ($payload->experimentKey !== null) {
            $rows .= $this->row('Experiment', e($payload->experimentKey));
        }

        if ($payload->flagKey !== null) {
            $rows .= $this->row('Feature Flag', e($payload->flagKey));
        }

        foreach ($payload->data as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $label        = ucwords(str_replace('_', ' ', $key));
            $displayValue = is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value;
            $rows        .= $this->row(e($label), e($displayValue));
        }

        $rows .= $this->row('Occurred At', e($payload->occurredAt->format(DateTimeInterface::ATOM)));

        $appName = e(config('app.name', 'Laravel'));
        $title   = e($payload->title);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
        <body style="margin:0;padding:0;background:#f7fafc;font-family:sans-serif;">
          <table width="100%" cellpadding="0" cellspacing="0" style="background:#f7fafc;padding:32px 0;">
            <tr><td align="center">
              <table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;border:1px solid #e2e8f0;overflow:hidden;">
                <tr>
                  <td style="background:#4c1d95;padding:20px 28px;">
                    <span style="font-size:13px;color:#c4b5fd;letter-spacing:.05em;text-transform:uppercase;">A/B Testing · {$appName}</span>
                  </td>
                </tr>
                <tr>
                  <td style="padding:28px 28px 8px;">
                    <h1 style="margin:0;font-size:18px;font-weight:600;color:#1a202c;">{$title}</h1>
                  </td>
                </tr>
                <tr>
                  <td style="padding:16px 28px 28px;">
                    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                      {$rows}
                    </table>
                  </td>
                </tr>
                <tr>
                  <td style="background:#f7fafc;padding:14px 28px;border-top:1px solid #e2e8f0;">
                    <span style="font-size:11px;color:#a0aec0;">This notification was sent by the A/B Testing package. Configure or disable notifications in <code>config/ab-testing.php</code>.</span>
                  </td>
                </tr>
              </table>
            </td></tr>
          </table>
        </body>
        </html>
        HTML;
    }

    private function row(string $label, string $value): string
    {
        return <<<HTML
        <tr>
          <td style="padding:7px 0;font-size:12px;color:#718096;width:140px;vertical-align:top;">$label</td>
          <td style="padding:7px 0;font-size:13px;color:#2d3748;font-weight:500;">$value</td>
        </tr>
        HTML;
    }
}
