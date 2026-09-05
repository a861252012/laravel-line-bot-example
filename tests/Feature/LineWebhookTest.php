<?php

namespace Tests\Feature;

use App\Models\LineEvent;
use App\Services\Line\LineMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class LineWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_signature_stores_processed_text_event(): void
    {
        config(['services.line.channel_secret' => 'test-channel-secret']);

        $this->mock(LineMessagingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('replyText')
                ->once()
                ->with('reply-token', '已記錄文字訊息。');
        });

        $response = $this->postWebhook([
            'events' => [[
                'type' => 'message',
                'webhookEventId' => 'event-001',
                'replyToken' => 'reply-token',
                'source' => ['type' => 'user', 'userId' => 'U123'],
                'message' => ['id' => 'message-001', 'type' => 'text', 'text' => '你好'],
            ]],
        ]);

        $response->assertOk()->assertJson(['status' => 'ok']);
        $this->assertDatabaseHas('line_events', [
            'webhook_event_id' => 'event-001',
            'event_type' => 'message',
            'status' => LineEvent::STATUS_PROCESSED,
        ]);
        $lineEvent = LineEvent::query()->firstOrFail();

        $this->assertSame('U123', data_get($lineEvent->payload, 'source.userId'));
    }

    public function test_invalid_signature_is_rejected_before_an_event_is_stored(): void
    {
        config(['services.line.channel_secret' => 'test-channel-secret']);

        $response = $this->postWebhook(['events' => []], 'invalid-signature');

        $response->assertUnauthorized();
        $this->assertDatabaseCount('line_events', 0);
    }

    public function test_image_event_is_stored_and_acknowledged(): void
    {
        config(['services.line.channel_secret' => 'test-channel-secret']);

        $this->mock(LineMessagingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('replyText')
                ->once()
                ->with('reply-token', '已記錄圖片事件。');
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
        $this->assertDatabaseHas('line_events', [
            'webhook_event_id' => 'event-002',
            'event_type' => 'message',
            'status' => LineEvent::STATUS_PROCESSED,
        ]);
    }

    public function test_location_event_is_stored_and_acknowledged(): void
    {
        config(['services.line.channel_secret' => 'test-channel-secret']);

        $this->mock(LineMessagingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('replyText')
                ->once()
                ->with('reply-token', '已記錄位置事件。');
        });

        $response = $this->postWebhook([
            'events' => [[
                'type' => 'message',
                'webhookEventId' => 'event-003',
                'replyToken' => 'reply-token',
                'source' => ['type' => 'user', 'userId' => 'U789'],
                'message' => [
                    'id' => 'message-003',
                    'type' => 'location',
                    'title' => '台北車站',
                    'address' => '台北市中正區',
                    'latitude' => 25.0478,
                    'longitude' => 121.5170,
                ],
            ]],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('line_events', [
            'webhook_event_id' => 'event-003',
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
                'webhookEventId' => 'event-004',
                'replyToken' => 'reply-token',
                'source' => ['type' => 'user', 'userId' => 'U789'],
                'message' => ['id' => 'message-004', 'type' => 'text', 'text' => '重複事件'],
            ]],
        ];

        $this->postWebhook($payload)->assertOk();
        $this->postWebhook($payload)->assertOk();

        $this->assertDatabaseCount('line_events', 1);
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
                'webhookEventId' => 'event-005',
                'replyToken' => 'reply-token',
                'source' => ['type' => 'user', 'userId' => 'U999'],
                'message' => ['id' => 'message-005', 'type' => 'text', 'text' => 'LINE API 失敗'],
            ]],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('line_events', [
            'webhook_event_id' => 'event-005',
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
