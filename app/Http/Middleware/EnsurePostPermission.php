<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePostPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403);
        }

        $allowed = match ($ability) {
            'access' => $user->canAccessPostModule(),

            'write' => $user->canWritePosts(),

            'review' => $user->canReviewPosts(),

            'manage' => $user->can('quan_ly_bai_viet'),

            default => false,
        };

        if (!$allowed) {
            abort(403);
        }

        return $next($request);
    }
}
