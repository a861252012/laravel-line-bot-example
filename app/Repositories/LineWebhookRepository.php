<?php

namespace App\Repositories;

use App\Models\LineEvent;
use Illuminate\Database\QueryException;

class LineWebhookRepository
{
    /**
     * @param  array<string, mixed>  $event
     */
    public function createLineEvent(array $event, string $webhookEventId): ?LineEvent
    {
        $eventType = $event['type'] ?? null;

        try {
            return LineEvent::query()->create([
                'webhook_event_id' => $webhookEventId,
                'event_type' => is_string($eventType) ? $eventType : 'unknown',
                'payload' => $event,
                'status' => LineEvent::STATUS_RECEIVED,
            ]);
        } catch (QueryException $exception) {
            if (LineEvent::query()->where('webhook_event_id', $webhookEventId)->exists()) {
                return null;
            }

            throw $exception;
        }
    }

    public function markEventProcessed(LineEvent $lineEvent): void
    {
        $lineEvent->forceFill([
            'status' => LineEvent::STATUS_PROCESSED,
            'processed_at' => now(),
        ])->save();
    }

    public function markEventFailed(LineEvent $lineEvent): void
    {
        $lineEvent->forceFill([
            'status' => LineEvent::STATUS_FAILED,
        ])->save();
    }
}
