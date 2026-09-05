<?php

namespace App\Services\Line;

use GuzzleHttp\Client as GuzzleHttpClient;
use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Configuration;
use LINE\Clients\MessagingApi\Model\FlexContainer;
use LINE\Clients\MessagingApi\Model\FlexMessage;
use LINE\Clients\MessagingApi\Model\Message;
use LINE\Clients\MessagingApi\Model\PostbackAction;
use LINE\Clients\MessagingApi\Model\QuickReply;
use LINE\Clients\MessagingApi\Model\QuickReplyItem;
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
        $this->sendReply($replyToken, [
            new TextMessage([
                'type' => 'text',
                'text' => $text,
            ]),
        ]);
    }

    public function replyHelp(string $replyToken): void
    {
        $this->sendReply($replyToken, [
            new TextMessage([
                'type' => 'text',
                'text' => "你好，我可以協助你查常見問題與建立案件。\n\n你也可以直接輸入：\n問題 <內容>\n我的案件\n關閉 #<案件編號>",
                'quickReply' => new QuickReply([
                    'items' => [
                        new QuickReplyItem([
                            'action' => new PostbackAction([
                                'type' => 'postback',
                                'label' => '常見問題',
                                'data' => 'action=faq',
                                'displayText' => '常見問題',
                            ]),
                        ]),
                        new QuickReplyItem([
                            'action' => new PostbackAction([
                                'type' => 'postback',
                                'label' => '建立問題',
                                'data' => 'action=ticket',
                                'displayText' => '建立問題',
                            ]),
                        ]),
                        new QuickReplyItem([
                            'action' => new PostbackAction([
                                'type' => 'postback',
                                'label' => '我的案件',
                                'data' => 'action=tickets',
                                'displayText' => '我的案件',
                            ]),
                        ]),
                    ],
                ]),
            ]),
        ]);
    }

    public function replyFaq(string $replyToken): void
    {
        $this->sendReply($replyToken, [
            new FlexMessage([
                'type' => 'flex',
                'altText' => '常見問題',
                'contents' => FlexContainer::fromAssocArray([
                    'type' => 'bubble',
                    'body' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'spacing' => 'md',
                        'contents' => [
                            [
                                'type' => 'text',
                                'text' => '常見問題',
                                'weight' => 'bold',
                                'size' => 'xl',
                            ],
                            [
                                'type' => 'text',
                                'text' => '這是一個可自行延伸的 Laravel LINE Bot 範例。若無法解決問題，請建立案件。',
                                'wrap' => true,
                                'color' => '#666666',
                            ],
                        ],
                    ],
                    'footer' => [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'contents' => [
                            [
                                'type' => 'button',
                                'style' => 'primary',
                                'action' => [
                                    'type' => 'postback',
                                    'label' => '建立問題',
                                    'data' => 'action=ticket',
                                    'displayText' => '建立問題',
                                ],
                            ],
                        ],
                    ],
                ]),
            ]),
        ]);
    }

    /**
     * @param  list<Message>  $messages
     */
    private function sendReply(string $replyToken, array $messages): void
    {
        $request = new ReplyMessageRequest([
            'replyToken' => $replyToken,
            'messages' => $messages,
        ]);

        $this->messagingApi->replyMessage($request);
    }
}
