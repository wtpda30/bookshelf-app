<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReadingPlanReminder extends Notification
{
    use Queueable;

    private string $title;

    private string $body;

    private string $timing;

    private int $readingPlanId;

    public function __construct(
        string $title,
        string $body,
        string $timing,
        int $readingPlanId
    ) {
        $this->title = $title;
        $this->body = $body;
        $this->timing = $timing;
        $this->readingPlanId = $readingPlanId;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'timing' => $this->timing,
            'reading_plan_id' => $this->readingPlanId,
        ];
    }
}
