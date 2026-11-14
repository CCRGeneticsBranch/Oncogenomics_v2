<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('chatbot.logs.connection'))->create('chatbot_query_logs', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->unsignedBigInteger('user_id');
            $table->string('conversation_id', 36)->nullable();
            $table->string('scope', 32)->nullable();
            $table->string('cohort_id', 255)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->string('status', 24);
            $table->string('provider', 128)->nullable();
            $table->string('model', 255)->nullable();
            foreach (['question', 'answer', 'tools_json', 'llm_calls_json', 'feedback'] as $column) $table->longText($column)->nullable();
            foreach (['input_tokens', 'output_tokens', 'total_tokens', 'cache_read_tokens', 'cache_write_tokens', 'reasoning_tokens'] as $column) $table->unsignedBigInteger($column)->nullable();
            $table->string('usage_status', 24)->default('unavailable');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->timestamp('feedback_at')->nullable();
            $table->index('started_at', 'chatlog_started_idx');
            $table->index(['user_id', 'started_at'], 'chatlog_user_idx');
            $table->index(['rating', 'started_at'], 'chatlog_rating_idx');
            $table->index(['status', 'started_at'], 'chatlog_status_idx');
        });
    }

    public function down(): void
    {
        Schema::connection(config('chatbot.logs.connection'))->dropIfExists('chatbot_query_logs');
    }
};
