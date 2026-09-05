<?php

namespace App\Services\Line;

use GuzzleHttp\Client as GuzzleHttpClient;
use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Configuration;
use LINE\Clients\MessagingApi\Model\ReplyMessageRequest;
use LINE\Clients\MessagingApi\Model\TextMessage;

class LineMessagingService
{
    private readonly MessagingApiApi $messagingApi;

    public function __construct()
    {
        $configuration = new Configuration;
        $configuration->setAccessToken((string) config('services.line.channel_access_token'));

        $this->messagingApi = new MessagingApiApi(
            client: new GuzzleHttpClient,
            config: $configuration
        );
    }

    public function replyText(string $replyToken, string $text): void
    {
        $request = new ReplyMessageRequest([
            'replyToken' => $replyToken,
            'messages' => [new TextMessage([
                'type' => 'text',
                'text' => $text,
            ])],
        ]);

        $this->messagingApi->replyMessage($request);
    }
}
