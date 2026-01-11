<?php

namespace App\Http\Middleware;

use App\Models\LoginSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractBearerToken($request);

        if (!$token) {
            return response()->json(['message' => 'Missing token.'], 401);
        }

        $session = LoginSession::query()
            ->where('token', $token)
            ->first();

        if (!$session) {
            return response()->json(['message' => 'Invalid token.'], 401);
        }

        if ($session->expires_at->isPast()) {
            $session->delete();
            return response()->json(['message' => 'Token expired.'], 401);
        }

        $user = $session->user;

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 401);
        }

        Auth::setUser($user);
        $request->setUserResolver(static fn () => $user);
        $request->attributes->set('login_session', $session);

        return $next($request);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (!$header) {
            return null;
        }

        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return trim(substr($header, 7));
    }
}
