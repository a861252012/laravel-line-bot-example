<?php

namespace App\Http\Controllers;

use App\Services\Line\LineWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class LineBotController extends Controller
{
    public function receive(Request $request, LineWebhookService $lineWebhookService): JsonResponse
    {
        $events = $request->json('events', []);

        if (! is_array($events)) {
            return response()->json(['status' => 'ok']);
        }

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            try {
                $lineWebhookService->process($event);
            } catch (Throwable $exception) {
                Log::error('LINE webhook processing failed.', [
                    'webhook_event_id' => $event['webhookEventId'] ?? null,
                    'exception' => $exception::class,
                    'code' => $exception->getCode(),
                ]);
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
