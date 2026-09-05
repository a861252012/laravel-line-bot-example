<?php

namespace App\Services\Line;

use App\Models\LineUser;
use App\Models\Ticket;
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

        $lineUser = $this->findOrCreateLineUser($event);
        $lineEvent = $this->lineWebhookRepository->createLineEvent($event, $lineUser, $webhookEventId);

        if ($lineEvent === null) {
            Log::info('Duplicate LINE webhook event skipped.', ['webhook_event_id' => $webhookEventId]);

            return;
        }

        try {
            $this->handleEvent($event, $lineUser);

            $this->lineWebhookRepository->markEventProcessed($lineEvent);
        } catch (Throwable $exception) {
            $this->lineWebhookRepository->markEventFailed($lineEvent);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function findOrCreateLineUser(array $event): ?LineUser
    {
        $lineUserId = data_get($event, 'source.userId');

        if (! is_string($lineUserId) || $lineUserId === '') {
            return null;
        }

        $sourceType = data_get($event, 'source.type');

        return $this->lineWebhookRepository->findOrCreateLineUser(
            $lineUserId,
            is_string($sourceType) ? $sourceType : null
        );
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function handleEvent(array $event, ?LineUser $lineUser): void
    {
        $replyToken = $this->getReplyToken($event);

        if ($replyToken === null) {
            return;
        }

        $eventType = $event['type'] ?? null;

        if ($eventType === 'follow') {
            $this->lineMessagingService->replyHelp($replyToken);

            return;
        }

        if ($eventType === 'postback') {
            $this->handlePostbackEvent($event, $lineUser, $replyToken);

            return;
        }

        if ($eventType === 'message') {
            $this->handleMessageEvent($event, $lineUser, $replyToken);

            return;
        }

        $this->lineMessagingService->replyText($replyToken, '已收到你的事件，目前可使用「幫助」查看支援功能。');
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function getReplyToken(array $event): ?string
    {
        $replyToken = $event['replyToken'] ?? null;

        return is_string($replyToken) && $replyToken !== '' ? $replyToken : null;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function handlePostbackEvent(array $event, ?LineUser $lineUser, string $replyToken): void
    {
        $data = data_get($event, 'postback.data');

        if (! is_string($data) || $data === '') {
            $this->lineMessagingService->replyHelp($replyToken);

            return;
        }

        parse_str($data, $postbackData);
        $action = $postbackData['action'] ?? null;

        if ($action === 'faq') {
            $this->lineMessagingService->replyFaq($replyToken);

            return;
        }

        if ($action === 'ticket') {
            $this->lineMessagingService->replyText($replyToken, '請輸入「問題 <內容>」建立案件。');

            return;
        }

        if ($action === 'tickets') {
            $this->replyTickets($lineUser, $replyToken);

            return;
        }

        $this->lineMessagingService->replyHelp($replyToken);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function handleMessageEvent(array $event, ?LineUser $lineUser, string $replyToken): void
    {
        $message = $event['message'] ?? null;

        if (! is_array($message)) {
            $this->lineMessagingService->replyHelp($replyToken);

            return;
        }

        $messageType = $message['type'] ?? null;

        if ($messageType !== 'text') {
            $this->replyNonTextMessage(is_string($messageType) ? $messageType : null, $replyToken);

            return;
        }

        $text = $message['text'] ?? null;

        if (! is_string($text) || trim($text) === '') {
            $this->lineMessagingService->replyHelp($replyToken);

            return;
        }

        $text = trim($text);
        $normalizedText = mb_strtolower($text);

        if (in_array($normalizedText, ['faq', '常見問題'], true)) {
            $this->lineMessagingService->replyFaq($replyToken);

            return;
        }

        if (in_array($normalizedText, ['幫助', 'help'], true)) {
            $this->lineMessagingService->replyHelp($replyToken);

            return;
        }

        if (in_array($normalizedText, ['我的案件', '案件'], true)) {
            $this->replyTickets($lineUser, $replyToken);

            return;
        }

        if (str_starts_with($text, '問題')) {
            $this->createTicket($lineUser, trim(mb_substr($text, mb_strlen('問題'))), $replyToken);

            return;
        }

        if (preg_match('/^關閉\s*#?(\d+)$/u', $text, $matches) === 1) {
            $this->closeTicket($lineUser, (int) $matches[1], $replyToken);

            return;
        }

        $this->lineMessagingService->replyHelp($replyToken);
    }

    private function replyNonTextMessage(?string $messageType, string $replyToken): void
    {
        $text = match ($messageType) {
            'image' => '已收到圖片。這個範例會保存 webhook 事件；圖片下載與 OCR 不在第一版範圍。',
            'location' => '已收到位置。這個範例會保存 webhook 事件；如需協助請輸入「問題 <內容>」。',
            'video', 'audio', 'file' => '已收到附件。這個範例會保存 webhook 事件；檔案下載與分析不在第一版範圍。',
            default => '已收到訊息，目前支援文字、圖片、位置與附件事件記錄。',
        };

        $this->lineMessagingService->replyText($replyToken, $text);
    }

    private function createTicket(?LineUser $lineUser, string $subject, string $replyToken): void
    {
        if ($lineUser === null) {
            $this->lineMessagingService->replyText($replyToken, '請在一對一聊天室建立案件，才能追蹤你的案件狀態。');

            return;
        }

        if ($subject === '') {
            $this->lineMessagingService->replyText($replyToken, '請輸入「問題 <內容>」建立案件。');

            return;
        }

        $ticket = $this->lineWebhookRepository->createTicket($lineUser, $subject);

        $this->lineMessagingService->replyText(
            $replyToken,
            "案件 #{$ticket->id} 已建立。輸入「我的案件」可查看目前進度。"
        );
    }

    private function replyTickets(?LineUser $lineUser, string $replyToken): void
    {
        if ($lineUser === null) {
            $this->lineMessagingService->replyText($replyToken, '請在一對一聊天室查看案件。');

            return;
        }

        $tickets = $this->lineWebhookRepository->getOpenTickets($lineUser);

        if ($tickets->isEmpty()) {
            $this->lineMessagingService->replyText($replyToken, '目前沒有開啟中的案件。');

            return;
        }

        $ticketSummary = $tickets
            ->map(fn (Ticket $ticket) => "#{$ticket->id} {$ticket->subject}")
            ->implode("\n");

        $this->lineMessagingService->replyText(
            $replyToken,
            "目前開啟中的案件：\n{$ticketSummary}\n\n關閉案件請輸入「關閉 #案件編號」。"
        );
    }

    private function closeTicket(?LineUser $lineUser, int $ticketId, string $replyToken): void
    {
        if ($lineUser === null) {
            $this->lineMessagingService->replyText($replyToken, '請在一對一聊天室關閉案件。');

            return;
        }

        $ticket = $this->lineWebhookRepository->findOpenTicket($lineUser, $ticketId);

        if ($ticket === null) {
            $this->lineMessagingService->replyText($replyToken, "找不到開啟中的案件 #{$ticketId}。");

            return;
        }

        $this->lineWebhookRepository->closeTicket($ticket);

        $this->lineMessagingService->replyText($replyToken, "案件 #{$ticket->id} 已關閉。");
    }
}
