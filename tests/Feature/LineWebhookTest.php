<?php

namespace Tests\Feature;

use App\Models\LineEvent;
use App\Models\LineUser;
use App\Models\Ticket;
use App\Services\Line\LineMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class LineWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_signature_creates_user_event_and_ticket(): void
    {
        config(['services.line.channel_secret' => 'test-channel-secret']);

        $this->mock(LineMessagingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('replyText')
                ->once()
                ->withArgs(fn (string $replyToken, string $text): bool => $replyToken === 'reply-token' && str_contains($text, '案件 #1 已建立'));
        });

        $response = $this->postWebhook([
            'events' => [[
                'type' => 'message',
                'webhookEventId' => 'event-001',
                'replyToken' => 'reply-token',
                'source' => ['type' => 'user', 'userId' => 'U123'],
                'message' => ['id' => 'message-001', 'type' => 'text', 'text' => '問題 無法登入'],
            ]],
        ]);

        $response->assertOk()->assertJson(['status' => 'ok']);
        $this->assertDatabaseHas('line_users', ['line_user_id' => 'U123']);
        $this->assertDatabaseHas('tickets', ['id' => 1, 'subject' => '無法登入', 'status' => Ticket::STATUS_OPEN]);
        $this->assertDatabaseHas('line_events', [
            'webhook_event_id' => 'event-001',
            'event_type' => 'message',
            'status' => LineEvent::STATUS_PROCESSED,
        ]);
    }

    public function test_invalid_signature_is_rejected_before_an_event_is_stored(): void
    {
        config(['services.line.channel_secret' => 'test-channel-secret']);

        $response = $this->postWebhook(['events' => []], 'invalid-signature');

        $response->assertUnauthorized();
        $this->assertDatabaseCount('line_events', 0);
    }

    public function test_non_text_image_event_is_stored_and_acknowledged(): void
    {
        config(['services.line.channel_secret' => 'test-channel-secret']);

        $this->mock(LineMessagingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('replyText')
                ->once()
                ->withArgs(fn (string $replyToken, string $text): bool => $replyToken === 'reply-token' && str_contains($text, '已收到圖片'));
        });

        $response = $this->postWebhook([
            'events' => [[
                'type' => 'message',
                'webhookEventId' => 'event-002',
                'replyToken' => 'reply-token',
                'source' => ['type' => 'user', 'userId' => 'U456'],
                'message' => ['id' => 'message-002', 'type' => 'image'],
            ]],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('line_users', ['line_user_id' => 'U456']);
        $this->assertDatabaseHas('line_events', [
            'webhook_event_id' => 'event-002',
            'event_type' => 'message',
            'status' => LineEvent::STATUS_PROCESSED,
        ]);
    }

    public function test_duplicate_webhook_event_is_processed_only_once(): void
    {
        config(['services.line.channel_secret' => 'test-channel-secret']);

        $this->mock(LineMessagingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('replyText')->once();
        });

        $payload = [
            'events' => [[
                'type' => 'message',
                'webhookEventId' => 'event-003',
                'replyToken' => 'reply-token',
                'source' => ['type' => 'user', 'userId' => 'U789'],
                'message' => ['id' => 'message-003', 'type' => 'text', 'text' => '問題 重複事件'],
            ]],
        ];

        $this->postWebhook($payload)->assertOk();
        $this->postWebhook($payload)->assertOk();

        $this->assertDatabaseCount('line_events', 1);
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_faq_postback_is_processed(): void
    {
        config(['services.line.channel_secret' => 'test-channel-secret']);

        $this->mock(LineMessagingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('replyFaq')->once()->with('reply-token');
        });

        $response = $this->postWebhook([
            'events' => [[
                'type' => 'postback',
                'webhookEventId' => 'event-004',
                'replyToken' => 'reply-token',
                'source' => ['type' => 'user', 'userId' => 'U123'],
                'postback' => ['data' => 'action=faq'],
            ]],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('line_events', [
            'webhook_event_id' => 'event-004',
            'event_type' => 'postback',
            'status' => LineEvent::STATUS_PROCESSED,
        ]);
    }

    public function test_ticket_can_be_closed_by_its_owner(): void
    {
        config(['services.line.channel_secret' => 'test-channel-secret']);

        $lineUser = LineUser::query()->create(['line_user_id' => 'U123', 'source_type' => 'user']);
        $ticket = Ticket::query()->create(['line_user_id' => $lineUser->id, 'subject' => '無法登入']);

        $this->mock(LineMessagingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('replyText')
                ->once()
                ->withArgs(fn (string $replyToken, string $text): bool => $replyToken === 'reply-token' && str_contains($text, '案件 #1 已關閉'));
        });

        $response = $this->postWebhook([
            'events' => [[
                'type' => 'message',
                'webhookEventId' => 'event-005',
                'replyToken' => 'reply-token',
                'source' => ['type' => 'user', 'userId' => 'U123'],
                'message' => ['id' => 'message-005', 'type' => 'text', 'text' => '關閉 #1'],
            ]],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => Ticket::STATUS_CLOSED]);
    }

    public function test_line_api_failure_marks_the_event_as_failed(): void
    {
        config(['services.line.channel_secret' => 'test-channel-secret']);

        $this->mock(LineMessagingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('replyText')->once()->andThrow(new RuntimeException('LINE API failed'));
        });

        $response = $this->postWebhook([
            'events' => [[
                'type' => 'message',
                'webhookEventId' => 'event-006',
                'replyToken' => 'reply-token',
                'source' => ['type' => 'user', 'userId' => 'U999'],
                'message' => ['id' => 'message-004', 'type' => 'text', 'text' => '問題 API 失敗'],
            ]],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('line_events', [
            'webhook_event_id' => 'event-006',
            'status' => LineEvent::STATUS_FAILED,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postWebhook(array $payload, ?string $signature = null): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature ??= base64_encode(hash_hmac('sha256', $body, 'test-channel-secret', true));

        return $this->call('POST', '/api/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LINE_SIGNATURE' => $signature,
        ], $body);
    }
}
