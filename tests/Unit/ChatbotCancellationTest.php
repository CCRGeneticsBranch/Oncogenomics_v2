<?php
namespace Tests\Unit;

use App\Services\ChatbotConversationStore;
use App\Services\ChatbotRunCancelled;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatbotCancellationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_cancel_releases_only_matching_run_and_blocks_late_answer(): void
    {
        $old = clone app(ChatbotConversationStore::class);
        $thread = $old->create(81, 'global', 'all', 'All');
        $id = (string) Str::uuid();
        $lock = $old->acquireRunLock($thread['id'], 81, $id);
        $this->assertNotNull($lock);
        $old->appendUserMessage($thread['id'], 81, 'old question', $id);
        $cancel = app(ChatbotConversationStore::class);
        $this->assertFalse($cancel->cancelRun($thread['id'], 82, $id));
        $this->assertFalse($cancel->cancelRun($thread['id'], 81, (string) Str::uuid()));
        $this->assertTrue($cancel->cancelRun($thread['id'], 81, $id));
        $newId = (string) Str::uuid();
        $newLock = $cancel->acquireRunLock($thread['id'], 81, $newId);
        $this->assertNotNull($newLock);
        $cancel->appendUserMessage($thread['id'], 81, 'new question', $newId);
        $this->assertFalse($cancel->cancelRun($thread['id'], 81, $id));
        $this->assertFalse($lock->release());
        $this->assertTrue($newLock->isOwnedByCurrentProcess());
        try { $old->appendAssistantMessage($thread['id'], 81, 'late old answer'); $this->fail('Late answer saved'); }
        catch (ChatbotRunCancelled) { $this->addToAssertionCount(1); }
        $cancel->appendAssistantMessage($thread['id'], 81, 'new answer');
        $messages=$cancel->get($thread['id'],81)['messages'];
        $this->assertSame(['new question','new answer'],array_column($messages,'content'));
        $this->assertNotContains('late old answer', array_column($messages,'content'));
        $newLock->release();
    }

    public function test_server_deadline_blocks_work_even_without_browser_cancellation(): void
    {
        $store=app(ChatbotConversationStore::class);
        $thread=$store->create(81,'global','all','All');
        $lock=$store->acquireRunLock($thread['id'],81,(string)Str::uuid());
        $deadline=new \ReflectionProperty($store,'runDeadline');
        $deadline->setValue($store,microtime(true)-1);
        try {
            $this->expectException(ChatbotRunCancelled::class);
            $store->assertCurrentRunActive();
        } finally { $lock->release(); }
    }
}
