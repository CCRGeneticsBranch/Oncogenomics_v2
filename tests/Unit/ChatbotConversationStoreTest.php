<?php

namespace Tests\Unit;

use App\Services\ChatbotConversationStore;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChatbotConversationStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_conversation_is_encrypted_and_owned_by_the_current_user(): void
    {
        $store = app(ChatbotConversationStore::class);
        $conversation = $store->create(81, 'global', 'all', 'Clinomics');
        $store->appendUserMessage($conversation['id'], 81, 'private sample question');
        $store->appendAssistantMessage($conversation['id'], 81, 'private answer');

        $raw = Cache::get('clinomics-chat:conversation:'.$conversation['id']);

        $this->assertIsString($raw);
        $this->assertStringNotContainsString('private sample question', $raw);
        $this->assertCount(2, $store->get($conversation['id'], 81)['messages']);
        $this->assertNull($store->get($conversation['id'], 82));
    }

    public function test_active_conversations_are_isolated_by_scope_and_cohort(): void
    {
        $store = app(ChatbotConversationStore::class);
        $global = $store->open(81, 'global', 'all', 'Clinomics');
        $project = $store->open(81, 'project', 25062, 'RNA landscape');

        $this->assertSame(
            $global['id'],
            $store->open(81, 'global', 'all', 'Clinomics')['id']
        );
        $this->assertSame(
            $project['id'],
            $store->open(81, 'project', 25062, 'RNA landscape')['id']
        );
        $this->assertNotSame($global['id'], $project['id']);
    }

    public function test_model_history_is_bounded_and_excludes_failed_answers(): void
    {
        config()->set('chatbot.conversations.agent_history_messages', 4);
        $store = app(ChatbotConversationStore::class);
        $conversation = $store->create(81, 'global', 'all', 'Clinomics');

        $store->appendUserMessage($conversation['id'], 81, 'first');
        $store->appendAssistantMessage($conversation['id'], 81, 'first answer');
        $store->appendUserMessage($conversation['id'], 81, 'failed question');
        $store->appendAssistantMessage($conversation['id'], 81, 'failure text', ['failed' => true]);
        $store->appendUserMessage($conversation['id'], 81, 'latest');
        $store->appendAssistantMessage($conversation['id'], 81, 'latest answer');

        $history = $store->historyForAgent($store->get($conversation['id'], 81));

        $this->assertSame(
            ['first', 'first answer', 'latest', 'latest answer'],
            array_column($history, 'content')
        );
        $this->assertNotContains('failure text', array_column($history, 'content'));
    }

    public function test_only_one_generation_lock_can_be_held_per_user(): void
    {
        $store = app(ChatbotConversationStore::class);
        $conversation = $store->create(81, 'global', 'all', 'Clinomics');
        $otherConversation = $store->create(81, 'project', 20, 'Compass');

        $first = $store->acquireRunLock($conversation['id'], 81);
        $second = $store->acquireRunLock($otherConversation['id'], 81);
        $otherUser = $store->acquireRunLock($otherConversation['id'], 82);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertNotNull($otherUser);

        $first->release();
        $otherUser->release();
        $third = $store->acquireRunLock($conversation['id'], 81);
        $this->assertNotNull($third);
        $third->release();
    }

    public function test_model_history_has_a_total_character_budget(): void
    {
        config()->set('chatbot.conversations.agent_history_chars', 4000);
        $store = app(ChatbotConversationStore::class);
        $conversation = $store->create(81, 'global', 'all', 'Clinomics');
        $store->appendUserMessage($conversation['id'], 81, str_repeat('u', 3000));
        $store->appendAssistantMessage($conversation['id'], 81, str_repeat('a', 3000));

        $history = $store->historyForAgent($store->get($conversation['id'], 81));

        $this->assertCount(2, $history);
        $this->assertLessThanOrEqual(4000, array_sum(array_map(
            static fn (array $message): int => mb_strlen($message['content']),
            $history,
        )));
    }

    public function test_large_execution_metadata_is_pruned_from_the_cached_thread(): void
    {
        config()->set('chatbot.conversations.max_document_bytes', 100000);
        $store = app(ChatbotConversationStore::class);
        $conversation = $store->create(81, 'global', 'all', 'Clinomics');
        $store->appendUserMessage($conversation['id'], 81, 'show a plot');
        $store->appendAssistantMessage($conversation['id'], 81, 'plot result', [
            'executions' => [['artifacts' => [['data_url' => str_repeat('x', 200000)]]]],
        ]);

        $stored = $store->get($conversation['id'], 81);

        $this->assertSame([], $stored['messages'][1]['meta']['executions']);
        $this->assertTrue($stored['messages'][1]['meta']['evidence_pruned']);
    }

    public function test_document_pruning_preserves_complete_turn_boundaries_and_latest_turn(): void
    {
        config()->set('chatbot.conversations.max_messages', 10);
        config()->set('chatbot.conversations.max_document_bytes', 100000);
        $store = app(ChatbotConversationStore::class);
        $conversation = $store->create(81, 'global', 'all', 'Clinomics');

        for ($turn = 1; $turn <= 6; $turn++) {
            $store->appendUserMessage($conversation['id'], 81, 'U'.$turn.str_repeat('u', 10000));
            $store->appendAssistantMessage($conversation['id'], 81, 'A'.$turn.str_repeat('a', 10000));
        }

        $messages = $store->get($conversation['id'], 81)['messages'];

        $this->assertNotEmpty($messages);
        $this->assertSame('user', $messages[0]['role']);
        $this->assertSame(['user', 'assistant'], array_slice(array_column($messages, 'role'), -2));
        $this->assertStringStartsWith('U6', $messages[count($messages) - 2]['content']);
        $this->assertStringStartsWith('A6', $messages[count($messages) - 1]['content']);
    }

    public function test_recent_conversation_index_preserves_previous_threads(): void
    {
        $store = app(ChatbotConversationStore::class);
        $first = $store->create(81, 'global', 'all', 'Clinomics');
        $store->appendUserMessage($first['id'], 81, 'First question');
        $second = $store->create(81, 'project', 20, 'Compass');
        $store->appendUserMessage($second['id'], 81, 'Second question');

        $recent = $store->recent(81);

        $this->assertSame([$second['id'], $first['id']], array_column($recent, 'id'));
        $this->assertSame(['Second question', 'First question'], array_column($recent, 'title'));
        $this->assertSame([], $store->recent(82));
    }
}
