<?php

namespace App\Services;

use Illuminate\Http\Request;

final class SiteTokenAuthenticator
{
    public static function valid(mixed $presented): bool
    {
        $expected = config('site.token');
        return is_string($expected) && $expected !== ''
            && is_string($presented) && $presented !== ''
            && hash_equals($expected, $presented);
    }

    public static function fromRequest(Request $request): mixed
    {
        // An invalid Authorization header must not fall back to another token.
        if ($request->hasHeader('Authorization')) return $request->bearerToken();
        return $request->route('token') ?? $request->input('token');
    }
}
