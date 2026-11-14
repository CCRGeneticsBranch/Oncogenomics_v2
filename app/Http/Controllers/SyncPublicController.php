<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Throwable;

class SyncPublicController extends BaseController
{
    public function projects(string $target): JsonResponse
    {
        if (!$this->isSuperAdmin()) return response()->json(['message' => 'Forbidden.'], 403);
        if ($target === 'internal') return response()->json(Project::all());
        if ($target !== 'public') return response()->json(['message' => 'Invalid target.'], 404);
        $token = config('site.public_token');
        $url = rtrim((string) (config('site.public_sync_url') ?: config('site.url_public')), '/');
        if (!is_string($token) || $token === '' || !str_starts_with($url, 'https://')) {
            return response()->json(['message' => 'Public sync is not configured.'], 503);
        }
        try {
            $response = Http::acceptJson()->withToken($token)->withoutRedirecting()->timeout(30)
                // Keep the legacy body credential for the existing public server.
                ->post($url.'/getProjects', ['token' => $token]);
            $data = $response->json();
            if (!$response->successful() || !is_array($data) || !array_is_list($data)) {
                return response()->json(['message' => 'Public project listing failed. Check the public server configuration.'], 502);
            }
            return response()->json($data);
        } catch (Throwable $e) {
            // Never forward upstream responses/errors which may contain credentials.
            return response()->json(['message' => 'Public project listing is unavailable.'], 502);
        }
    }

    protected function isSuperAdmin(): bool
    {
        return User::isSuperAdmin();
    }
}
