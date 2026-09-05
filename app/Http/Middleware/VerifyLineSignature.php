<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyLineSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $channelSecret = config('services.line.channel_secret');

        if (! is_string($channelSecret) || $channelSecret === '') {
            Log::critical('LINE channel secret is not configured.');

            return response()->json(['message' => 'Service unavailable.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $signature = $request->header('x-line-signature');

        if (! is_string($signature) || $signature === '') {
            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $expectedSignature = base64_encode(hash_hmac('sha256', $request->getContent(), $channelSecret, true));

        if (! hash_equals($expectedSignature, $signature)) {
            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
