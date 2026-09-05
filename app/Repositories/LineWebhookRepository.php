<?php

namespace App\Repositories;

use App\Models\LineEvent;
use App\Models\LineUser;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;

class LineWebhookRepository
{
    public function findOrCreateLineUser(string $lineUserId, ?string $sourceType): LineUser
    {
        return LineUser::query()->firstOrCreate(
            ['line_user_id' => $lineUserId],
            ['source_type' => $sourceType]
        );
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function createLineEvent(array $event, ?LineUser $lineUser, string $webhookEventId): ?LineEvent
    {
        $eventType = $event['type'] ?? null;

        try {
            return LineEvent::query()->create([
                'webhook_event_id' => $webhookEventId,
                'line_user_id' => $lineUser?->id,
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

    public function createTicket(LineUser $lineUser, string $subject): Ticket
    {
        return Ticket::query()->create([
            'line_user_id' => $lineUser->id,
            'subject' => $subject,
            'status' => Ticket::STATUS_OPEN,
        ]);
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function getOpenTickets(LineUser $lineUser): Collection
    {
        return Ticket::query()
            ->where('line_user_id', $lineUser->id)
            ->where('status', Ticket::STATUS_OPEN)
            ->latest('id')
            ->limit(5)
            ->get();
    }

    public function findOpenTicket(LineUser $lineUser, int $ticketId): ?Ticket
    {
        return Ticket::query()
            ->where('id', $ticketId)
            ->where('line_user_id', $lineUser->id)
            ->where('status', Ticket::STATUS_OPEN)
            ->first();
    }

    public function closeTicket(Ticket $ticket): void
    {
        $ticket->forceFill(['status' => Ticket::STATUS_CLOSED])->save();
    }
}
