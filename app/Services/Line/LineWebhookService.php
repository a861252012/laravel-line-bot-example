<?php

namespace App\Services\Line;

use App\Repositories\LineWebhookRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

class LineWebhookService
{
    public function __construct(
        private readonly LineMessagingService $lineMessagingService,
        private readonly LineWebhookRepository $lineWebhookRepository
    ) {}

    /**
     * @param  array<string, mixed>  $event
     */
    public function process(array $event): void
    {
        $webhookEventId = $event['webhookEventId'] ?? null;

        if (! is_string($webhookEventId) || $webhookEventId === '') {
            Log::warning('LINE webhook event was missing webhookEventId.');

            return;
        }

        $lineEvent = $this->lineWebhookRepository->createLineEvent($event, $webhookEventId);

        if ($lineEvent === null) {
            Log::info('Duplicate LINE webhook event skipped.', ['webhook_event_id' => $webhookEventId]);

            return;
        }

        try {
            $this->handleEvent($event);

            $this->lineWebhookRepository->markEventProcessed($lineEvent);
        } catch (Throwable $exception) {
            $this->lineWebhookRepository->markEventFailed($lineEvent);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function handleEvent(array $event): void
    {
        if (($event['type'] ?? null) !== 'message') {
            return;
        }

        $replyToken = $event['replyToken'] ?? null;

        if (! is_string($replyToken) || $replyToken === '') {
            return;
        }

        $messageType = data_get($event, 'message.type');
        $replyText = match ($messageType) {
            'text' => '已記錄文字訊息。',
            'image' => '已記錄圖片事件。',
            'location' => '已記錄位置事件。',
            default => '已記錄 LINE 訊息。',
        };

        $this->lineMessagingService->replyText($replyToken, $replyText);
    }
}
