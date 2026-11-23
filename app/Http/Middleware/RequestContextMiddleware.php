<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $incomingId = (string) $request->header('X-Request-Id', '');
        $requestId = preg_match('/^[A-Za-z0-9._-]{8,64}$/', $incomingId) === 1
            ? $incomingId
            : (string) Str::uuid();

        Context::add('request_id', $requestId);

        if ($request->user()) {
            Context::add('user_id', (int) $request->user()->id);
        }

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
