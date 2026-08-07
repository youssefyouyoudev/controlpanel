<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class YouPanelNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly string $severity = 'info',
        private readonly ?string $url = null,
        private readonly array $metadata = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'severity' => $this->severity,
            'url' => $this->url,
            'metadata' => $this->metadata,
        ];
    }
}
