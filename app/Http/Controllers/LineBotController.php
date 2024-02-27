<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client as GuzzleHttpClient;
use Illuminate\Http\Request;
use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Configuration;
use LINE\Clients\MessagingApi\Model\ReplyMessageRequest;
use LINE\Clients\MessagingApi\Model\TextMessage;

class LineBotController extends Controller
{
    private MessagingApiApi $messagingApi;

    public function __construct()
    {
        $client = new GuzzleHttpClient();
        $config = new Configuration();
        $config->setAccessToken(env('LINE_CHANNEL_ACCESS_TOKEN'));

        $this->messagingApi = new MessagingApiApi(
            client: $client,
            config: $config
        );
    }

    public function webhook(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        if (!empty($data['events'])) {
            foreach ($data['events'] as $event) {
                if ($event['type'] === 'message' && $event['message']['type'] === 'text') {
                    $replyToken = $event['replyToken'];
                    $userMessage = $event['message']['text'];

                    $message = new TextMessage(['type' => 'text', 'text' => $userMessage]);
                    $replyRequest = new ReplyMessageRequest(['replyToken' => $replyToken, 'messages' => [$message]]);

                    try {
                        $this->messagingApi->replyMessage($replyRequest);
                    } catch (\Exception $e) {
                        logger()->error($e->getCode() . ' ' . $e->getMessage());
                    }
                }
            }
        }

        return response()->json(['status' => 'success']);
    }
}
