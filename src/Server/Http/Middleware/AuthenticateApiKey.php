<?php

namespace Kaveh\Server\Http\Middleware;

use Kaveh\Server\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            return response()->json(['message' => 'Missing API key'], 401);
        }

        $plain = trim(substr($header, 7));
        $key = ApiKey::findActiveByPlain($plain);
        if (! $key) {
            return response()->json(['message' => 'Invalid API key'], 401);
        }

        $key->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('kaveh_api_key', $key);
        $request->attributes->set('kaveh_project', $key->project);

        return $next($request);
    }
}
