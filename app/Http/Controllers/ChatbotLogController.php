<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ChatbotQueryLogStore;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ChatbotLogController extends Controller
{
    public function report(Request $request, ChatbotQueryLogStore $logs)
    {
        abort_unless(User::isSuperAdmin(), 403);
        $filters = $request->validate([
            'q' => 'nullable|string|max:250', 'user_id' => 'nullable|integer|min:1',
            'user_name' => 'nullable|string|max:250',
            'rating' => 'nullable|in:1,2,3,4,5,unrated',
            'status' => 'nullable|in:running,completed,error,interrupted',
            'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d'.($request->filled('from') ? '|after_or_equal:from' : ''),
            'sort' => 'nullable|in:newest,slowest,tokens', 'page' => 'nullable|integer|min:1',
        ]);
        try {
            $rows = $logs->search(array_filter($filters, static fn ($value) => $value !== null && $value !== ''));
            return response()->view('pages/viewChatbotLog', ['rows' => $rows, 'filters' => $filters, 'unavailable' => false])
                ->header('Cache-Control', 'private, no-store');
        } catch (Throwable) {
            return response()->view('pages/viewChatbotLog', ['rows' => null, 'filters' => $filters, 'unavailable' => true], 503)
                ->header('Cache-Control', 'private, no-store');
        }
    }

    public function feedback(Request $request, string $id, ChatbotQueryLogStore $logs)
    {
        $user = User::getCurrentUser();
        abort_if($user === null, 401);
        $values = $request->validate([
            'rating' => 'nullable|integer|between:1,5',
            'feedback' => 'nullable|string|max:5000',
        ]);
        try {
            $logs->feedback($id, (int) $user->id, isset($values['rating']) ? (int) $values['rating'] : null,
                isset($values['feedback']) && trim($values['feedback']) !== '' ? trim($values['feedback']) : null);
            return response()->json(['status' => 'success', 'message' => 'Feedback saved.']);
        } catch (HttpException $exception) {
            throw $exception;
        } catch (Throwable) {
            return response()->json(['message' => 'Feedback could not be saved. Please try again later.'], 503);
        }
    }
}
