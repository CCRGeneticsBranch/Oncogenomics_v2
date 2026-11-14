@extends('layouts.default')
@section('title', 'Chatbot query log')
@section('content')
<style>
.chat-log-page { padding: 24px; }
.chat-log-filters { display: flex; flex-wrap: wrap; gap: 12px; align-items: end; margin: 20px 0; }
.chat-log-filters label { display: block; font-weight: 600; }
.chat-log-filters .form-control, .chat-log-filters .form-select { width: auto; min-width: 120px; }
.chat-log-filters .chat-log-search { width: min(440px, 80vw); }
.chat-log-table { font-size: 14px; }
.chat-log-table td { vertical-align: top; }
.chat-log-text { white-space: pre-wrap; overflow-wrap: anywhere; max-height: 450px; overflow-y: auto; min-width: 240px; max-width: 650px; }
.chat-log-table details { margin-top: 6px; }
.chat-log-table summary { cursor: pointer; color: #1769aa; }
</style>
<div class="chat-log-page">
    <h1>Chatbot query log</h1>
    <p>Search questions, answers, feedback, models, and tools. Times are displayed in {{ config('chatbot.logs.display_timezone', 'America/New_York') }}.</p>
    <form method="get" action="{{ url('/viewChatbotLog') }}" class="chat-log-filters">
        <div><label for="log-q">Search</label><input id="log-q" class="form-control chat-log-search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="250" placeholder="Question, answer, feedback, model, or tool"></div>
        <div><label for="log-user">User name</label><input id="log-user" class="form-control" type="text" name="user_name" maxlength="250" placeholder="First or last name" value="{{ $filters['user_name'] ?? '' }}"></div>
        <div><label for="log-rating">Rating</label><select id="log-rating" class="form-select" name="rating">
            <option value="">All ratings</option>
            @foreach (['unrated' => 'Unrated', '1' => '★ 1', '2' => '★ 2', '3' => '★ 3', '4' => '★ 4', '5' => '★ 5'] as $value => $label)
                <option value="{{ $value }}" @selected((string) ($filters['rating'] ?? '') === (string) $value)>{{ $label }}</option>
            @endforeach
        </select></div>
        <div><label for="log-status">Status</label><select id="log-status" class="form-select" name="status">
            <option value="">All statuses</option>
            @foreach (['running', 'completed', 'error', 'interrupted'] as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select></div>
        <div><label for="log-from">From</label><input id="log-from" class="form-control" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
        <div><label for="log-to">Through</label><input id="log-to" class="form-control" type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
        <div><label for="log-sort">Order</label><select id="log-sort" class="form-select" name="sort">
            @foreach (['newest' => 'Newest first', 'slowest' => 'Slowest first', 'tokens' => 'Most tokens'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['sort'] ?? 'newest') === $value)>{{ $label }}</option>
            @endforeach
        </select></div>
        <button class="btn btn-primary" type="submit">Search</button>
        <a class="btn btn-outline-secondary" href="{{ url('/viewChatbotLog') }}">Reset</a>
    </form>
    @if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
    @if ($unavailable)
        <div class="alert alert-warning" role="alert">The query log is unavailable. Create the chatbot_query_logs table using the supplied migration or Oracle SQL script, and check the application's table permissions.</div>
    @else
        <p>{{ number_format($rows->total()) }} matching queries. Token counts include reported usage from agent, synthesis, and compatibility calls; unavailable usage is shown as unknown.</p>
        <div class="table-responsive">
        <table class="table table-striped table-bordered chat-log-table">
            <thead><tr><th>Date / user</th><th>Question / answer</th><th>Feedback</th><th>Duration / status</th><th>LLM / tools</th><th>Tokens</th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ \Carbon\CarbonImmutable::parse($row->started_at, 'UTC')->setTimezone(config('chatbot.logs.display_timezone', 'America/New_York'))->format('Y-m-d H:i:s T') }}<br>
                        {{ $row->user_name }}<br><small>{{ $row->cohort_name }}</small><br><small>{{ $row->id }}</small></td>
                    <td><strong>Question</strong><div>{{ \Illuminate\Support\Str::limit($row->question ?? '', 180) }}</div>
                        <details><summary>Full question and answer</summary><strong>Question</strong><div class="chat-log-text">{{ $row->question }}</div>
                            <strong>Answer</strong><div class="chat-log-text">{{ $row->answer ?? 'No answer recorded.' }}</div></details></td>
                    <td>{{ $row->rating === null ? 'Unrated' : str_repeat('★', (int) $row->rating).' ('.$row->rating.'/5)' }}
                        @if ($row->feedback !== null)<details><summary>{{ \Illuminate\Support\Str::limit($row->feedback, 90) }}</summary><div class="chat-log-text">{{ $row->feedback }}</div></details>@endif
                    </td>
                    <td>{{ $row->duration_ms === null ? '—' : number_format($row->duration_ms / 1000, 2).' s' }}<br>{{ $row->status }}</td>
                    <td>{{ $row->provider ?? 'Unknown' }}<br>{{ $row->model ?? '—' }}<br>
                        @foreach (json_decode($row->tools_json ?? '[]', true) ?: [] as $tool)<span>{{ $tool }}</span><br>@endforeach
                        @if ($row->llm_calls_json && $row->llm_calls_json !== '[]')<details><summary>LLM calls and usage</summary><pre class="chat-log-text">{{ json_encode(json_decode($row->llm_calls_json, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>@endif
                    </td>
                    <td>{{ $row->total_tokens === null ? 'Unknown' : number_format($row->total_tokens) }} total<br>
                        {{ $row->input_tokens === null ? '?' : number_format($row->input_tokens) }} input / {{ $row->output_tokens === null ? '?' : number_format($row->output_tokens) }} output<br>
                        <small>{{ $row->usage_status }}</small></td>
                </tr>
            @empty
                <tr><td colspan="6">No matching queries.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        {{ $rows->links('pagination::bootstrap-5') }}
    @endif
</div>
@endsection
