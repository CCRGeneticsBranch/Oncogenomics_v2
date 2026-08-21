<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Str;

/**
 * Stores chatbot threads outside the browser session.
 *
 * Clinomics currently uses cookie-backed sessions, so putting a conversation
 * in the session would quickly exceed the browser cookie limit. The default
 * file cache keeps the full thread on the server; the browser only receives a
 * random conversation UUID.
 */
class ChatbotConversationStore
{
    public function __construct(
        private readonly Repository $cache,
        private readonly Encrypter $encrypter,
    ) {}

    /** @return array<string, mixed> */
    public function open(
        int $userId,
        string $scope,
        string|int $cohortId,
        string $cohortName,
        bool $startNew = false,
    ): array {
        if (! $startNew) {
            $activeId = $this->cache->get($this->activeKey($userId, $scope, $cohortId));
            if (is_string($activeId)) {
                $conversation = $this->get($activeId, $userId);
                if ($conversation !== null && $this->matchesContext($conversation, $scope, $cohortId)) {
                    return $conversation;
                }
            }
        }

        return $this->create($userId, $scope, $cohortId, $cohortName);
    }

    /** @return array<string, mixed> */
    public function create(int $userId, string $scope, string|int $cohortId, string $cohortName): array
    {
        $now = now()->toIso8601String();
        $conversation = [
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'scope' => $scope,
            'cohort_id' => (string) $cohortId,
            'cohort_name' => $cohortName,
            'messages' => [],
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $this->put($conversation);
        $this->rememberActive($conversation);
        $this->rememberInIndex($conversation);

        return $conversation;
    }

    /** @return array<string, mixed>|null */
    public function get(string $conversationId, int $userId): ?array
    {
        if (! Str::isUuid($conversationId)) {
            return null;
        }

        $conversation = $this->read($conversationId);
        if (! is_array($conversation) || (int) ($conversation['user_id'] ?? 0) !== $userId) {
            return null;
        }

        // Do not rewrite the full document during a read. A page refresh can
        // happen while a streamed turn is writing and must not restore a stale
        // snapshot over the newer messages.
        $this->rememberActive($conversation);
        $this->rememberInIndex($conversation);

        return $conversation;
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $userId): array
    {
        $limit = max(1, (int) config('chatbot.conversations.recent_conversations', 30));
        $ids = array_slice((array) $this->cache->get($this->indexKey($userId), []), 0, $limit);
        $recent = [];

        foreach ($ids as $id) {
            if (! is_string($id)) {
                continue;
            }
            $conversation = $this->read($id);
            if ($conversation === null || (int) ($conversation['user_id'] ?? 0) !== $userId) {
                continue;
            }

            $title = 'New conversation';
            foreach ((array) ($conversation['messages'] ?? []) as $message) {
                if (($message['role'] ?? null) === 'user' && trim((string) ($message['content'] ?? '')) !== '') {
                    $title = Str::limit(trim((string) $message['content']), 80);
                    break;
                }
            }
            $recent[] = [
                'id' => (string) $conversation['id'],
                'scope' => (string) $conversation['scope'],
                'cohort_name' => (string) $conversation['cohort_name'],
                'title' => $title,
                'updated_at' => $conversation['updated_at'] ?? null,
            ];
        }

        return $recent;
    }

    /**
     * Prevent two browser tabs from running turns against one thread at once.
     * The caller must release the returned lock in a finally block.
     */
    public function acquireRunLock(string $conversationId, ?int $userId = null): ?Lock
    {
        $store = $this->cache->getStore();
        if (! $store instanceof LockProvider) {
            return null;
        }

        $seconds = max(60, (int) config('chatbot.conversations.run_lock_seconds', 360));
        $lockKey = $userId !== null
            ? 'clinomics-chat:user:'.$userId.':run'
            : $this->conversationKey($conversationId).':run';
        $lock = $store->lock($lockKey, $seconds);

        return $lock->get() ? $lock : null;
    }

    /** @return array<string, mixed>|null */
    public function appendUserMessage(
        string $conversationId,
        int $userId,
        string $content,
        ?string $messageId = null,
    ): ?array {
        return $this->appendMessage(
            $conversationId,
            $userId,
            'user',
            $content,
            [],
            $messageId,
        );
    }

    /** @param array<string, mixed> $meta @return array<string, mixed>|null */
    public function appendAssistantMessage(
        string $conversationId,
        int $userId,
        string $content,
        array $meta = [],
        ?string $messageId = null,
    ): ?array {
        return $this->appendMessage(
            $conversationId,
            $userId,
            'assistant',
            $content,
            $meta,
            $messageId,
        );
    }

    /**
     * Return a bounded textual history for the model. Tool payloads remain in
     * the UI metadata and are deliberately not replayed to an outside model.
     *
     * @param  array<string, mixed>  $conversation
     * @return array<int, array{role: string, content: string}>
     */
    public function historyForAgent(array $conversation): array
    {
        $limit = max(2, (int) config('chatbot.conversations.agent_history_messages', 24));
        $completeMessages = [];
        $pendingUser = null;

        foreach ((array) ($conversation['messages'] ?? []) as $message) {
            if (! is_array($message) || trim((string) ($message['content'] ?? '')) === '') {
                continue;
            }
            if (($message['role'] ?? null) === 'user') {
                $pendingUser = $message;

                continue;
            }
            if (($message['role'] ?? null) !== 'assistant' || $pendingUser === null) {
                continue;
            }

            if (empty($message['meta']['failed'])) {
                $completeMessages[] = [
                    'role' => 'user',
                    'content' => (string) $pendingUser['content'],
                ];
                $completeMessages[] = [
                    'role' => 'assistant',
                    'content' => (string) $message['content'],
                ];
            }
            $pendingUser = null;
        }

        // Keep complete pairs, even when an odd limit is configured.
        $limit -= $limit % 2;
        $completeMessages = array_slice($completeMessages, -$limit);
        $characterLimit = max(4000, (int) config('chatbot.conversations.agent_history_chars', 48000));
        $bounded = [];
        $usedCharacters = 0;

        for ($index = count($completeMessages) - 2; $index >= 0; $index -= 2) {
            $user = $completeMessages[$index];
            $assistant = $completeMessages[$index + 1];
            $pairCharacters = mb_strlen($user['content']) + mb_strlen($assistant['content']);

            if ($bounded !== [] && $usedCharacters + $pairCharacters > $characterLimit) {
                break;
            }

            if ($pairCharacters > $characterLimit) {
                $perMessageLimit = intdiv($characterLimit, 2);
                $user['content'] = mb_substr($user['content'], 0, $perMessageLimit);
                $assistant['content'] = mb_substr(
                    $assistant['content'],
                    0,
                    $characterLimit - mb_strlen($user['content']),
                );
                $pairCharacters = mb_strlen($user['content']) + mb_strlen($assistant['content']);
            }

            array_unshift($bounded, $user, $assistant);
            $usedCharacters += $pairCharacters;
        }

        return $bounded;
    }

    /** @return array<string, mixed>|null */
    private function appendMessage(
        string $conversationId,
        int $userId,
        string $role,
        string $content,
        array $meta,
        ?string $messageId,
    ): ?array {
        $conversation = $this->read($conversationId);
        if ($conversation === null) {
            return null;
        }
        if ((int) ($conversation['user_id'] ?? 0) !== $userId) {
            return null;
        }

        $messageId = $messageId !== null && Str::isUuid($messageId)
            ? $messageId
            : (string) Str::uuid();

        foreach ((array) ($conversation['messages'] ?? []) as $existing) {
            if (is_array($existing) && ($existing['id'] ?? null) === $messageId) {
                return $existing;
            }
        }

        $maxCharacters = max(1000, (int) config('chatbot.conversations.max_message_chars', 100000));
        $message = [
            'id' => $messageId,
            'role' => $role,
            'content' => mb_substr($content, 0, $maxCharacters),
            'meta' => $meta,
            'created_at' => now()->toIso8601String(),
        ];

        $messages = (array) ($conversation['messages'] ?? []);
        $messages[] = $message;
        $maxMessages = max(10, (int) config('chatbot.conversations.max_messages', 100));
        $conversation['messages'] = $this->trimToRecentTurns($messages, $maxMessages);
        $conversation['updated_at'] = now()->toIso8601String();
        $conversation = $this->fitToDocumentLimit($conversation);

        $this->put($conversation);
        $this->rememberActive($conversation);
        $this->rememberInIndex($conversation);

        return $message;
    }

    /** @param array<string, mixed> $conversation @return array<string, mixed> */
    private function fitToDocumentLimit(array $conversation): array
    {
        $limit = max(100000, (int) config('chatbot.conversations.max_document_bytes', 10485760));
        if ($this->encodedSize($conversation) <= $limit) {
            return $conversation;
        }

        // Evidence previews and inline images are useful but expendable on an
        // old turn. Keep the conversational text when the cache document nears
        // its bound, pruning the oldest rich evidence first.
        foreach ($conversation['messages'] as &$message) {
            if (($message['role'] ?? null) !== 'assistant' || empty($message['meta']['executions'])) {
                continue;
            }
            $message['meta']['executions'] = [];
            $message['meta']['evidence_pruned'] = true;
            if ($this->encodedSize($conversation) <= $limit) {
                break;
            }
        }
        unset($message);

        $turns = $this->messageTurns((array) $conversation['messages']);
        while (count($turns) > 1 && $this->encodedSize($conversation) > $limit) {
            array_shift($turns);
            $conversation['messages'] = $this->flattenTurns($turns);
        }

        if ($this->encodedSize($conversation) > $limit) {
            foreach ($conversation['messages'] as &$message) {
                $meta = (array) ($message['meta'] ?? []);
                $message['meta'] = array_intersect_key($meta, array_flip([
                    'provider', 'model', 'steps', 'tool_calls', 'used_summarizer', 'failed', 'evidence_pruned',
                ]));
            }
            unset($message);
        }

        while ($this->encodedSize($conversation) > $limit) {
            $largestIndex = null;
            $largestLength = 0;
            foreach ($conversation['messages'] as $index => $message) {
                $length = mb_strlen((string) ($message['content'] ?? ''));
                if ($length > $largestLength) {
                    $largestLength = $length;
                    $largestIndex = $index;
                }
            }
            if ($largestIndex === null || $largestLength === 0) {
                break;
            }

            $excess = $this->encodedSize($conversation) - $limit;
            $newLength = max(0, $largestLength - $excess - 1024);
            $conversation['messages'][$largestIndex]['content'] = mb_substr(
                (string) $conversation['messages'][$largestIndex]['content'],
                0,
                $newLength,
            );
        }

        return $conversation;
    }

    /**
     * Keep complete user/assistant turns plus, at most, the newest pending user
     * message. This also repairs an older cache entry that starts with an
     * orphaned assistant message.
     *
     * @param  array<int, mixed>  $messages
     * @return array<int, array<string, mixed>>
     */
    private function trimToRecentTurns(array $messages, int $limit): array
    {
        $turns = $this->messageTurns($messages);
        $messageCount = array_sum(array_map('count', $turns));

        while (count($turns) > 1 && $messageCount > $limit) {
            $messageCount -= count((array) array_shift($turns));
        }

        return $this->flattenTurns($turns);
    }

    /**
     * @param  array<int, mixed>  $messages
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function messageTurns(array $messages): array
    {
        $turns = [];
        $pendingUser = null;

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            if (($message['role'] ?? null) === 'user') {
                // If a process died before writing an assistant response, only
                // the newest unanswered question remains actionable.
                $pendingUser = $message;

                continue;
            }

            if (($message['role'] ?? null) === 'assistant' && $pendingUser !== null) {
                $turns[] = [$pendingUser, $message];
                $pendingUser = null;
            }
        }

        if ($pendingUser !== null) {
            $turns[] = [$pendingUser];
        }

        return $turns;
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $turns
     * @return array<int, array<string, mixed>>
     */
    private function flattenTurns(array $turns): array
    {
        return array_values(array_merge([], ...$turns));
    }

    /** @param array<string, mixed> $conversation */
    private function encodedSize(array $conversation): int
    {
        return strlen((string) json_encode(
            $conversation,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        ));
    }

    /** @param array<string, mixed> $conversation */
    private function put(array $conversation): void
    {
        $encoded = json_encode(
            $conversation,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
        $this->cache->put(
            $this->conversationKey((string) $conversation['id']),
            $this->encrypter->encrypt($encoded, false),
            now()->addMinutes($this->ttlMinutes()),
        );
    }

    /** @return array<string, mixed>|null */
    private function read(string $conversationId): ?array
    {
        $encrypted = $this->cache->get($this->conversationKey($conversationId));
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            $decoded = json_decode($this->encrypter->decrypt($encrypted, false), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string, mixed> $conversation */
    private function rememberActive(array $conversation): void
    {
        $this->cache->put(
            $this->activeKey(
                (int) $conversation['user_id'],
                (string) $conversation['scope'],
                (string) $conversation['cohort_id'],
            ),
            (string) $conversation['id'],
            now()->addMinutes($this->ttlMinutes()),
        );
    }

    /** @param array<string, mixed> $conversation */
    private function rememberInIndex(array $conversation): void
    {
        $key = $this->indexKey((int) $conversation['user_id']);
        $ids = array_values(array_filter(
            (array) $this->cache->get($key, []),
            static fn (mixed $id): bool => is_string($id) && $id !== (string) $conversation['id'],
        ));
        array_unshift($ids, (string) $conversation['id']);
        $limit = max(1, (int) config('chatbot.conversations.recent_conversations', 30));
        $this->cache->put($key, array_slice($ids, 0, $limit), now()->addMinutes($this->ttlMinutes()));
    }

    /** @param array<string, mixed> $conversation */
    private function matchesContext(array $conversation, string $scope, string|int $cohortId): bool
    {
        return ($conversation['scope'] ?? null) === $scope
            && (string) ($conversation['cohort_id'] ?? '') === (string) $cohortId;
    }

    private function ttlMinutes(): int
    {
        return max(60, (int) config('chatbot.conversations.ttl_minutes', 43200));
    }

    private function conversationKey(string $conversationId): string
    {
        return 'clinomics-chat:conversation:'.$conversationId;
    }

    private function activeKey(int $userId, string $scope, string|int $cohortId): string
    {
        return 'clinomics-chat:active:'.$userId.':'.$scope.':'.sha1((string) $cohortId);
    }

    private function indexKey(int $userId): string
    {
        return 'clinomics-chat:index:'.$userId;
    }
}
