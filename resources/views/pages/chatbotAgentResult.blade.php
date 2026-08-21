<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clinomics chatbot result</title>
    <style>
        :root { color-scheme: light; }
        body { margin: 0; background: #f4f6f9; color: #20252b; font-family: Arial, sans-serif; }
        main { max-width: 1500px; margin: 0 auto; padding: 22px; }
        .card { background: #fff; border: 1px solid #dfe3e8; border-radius: 7px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.05); }
        .card-body { padding: 18px; }
        h2, h3 { margin: 0 0 12px; }
        .question { color: #59636e; margin-bottom: 16px; }
        .answer { white-space: pre-wrap; line-height: 1.5; }
        .meta { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 16px; }
        .badge { background: #e9f2ff; border: 1px solid #c9dcf5; border-radius: 12px; color: #24527c; padding: 4px 9px; font-size: 12px; }
        details { border-top: 1px solid #eceff2; }
        summary { cursor: pointer; font-weight: 600; padding: 13px 18px; }
        .execution { padding: 0 18px 18px; }
        .tool-summary { color: #515b66; margin: 0 0 10px; }
        pre { margin: 8px 0; padding: 11px; border: 1px solid #e1e4e8; border-radius: 4px; background: #f7f8fa; white-space: pre-wrap; overflow-wrap: anywhere; max-height: 360px; overflow: auto; }
        .table-wrap { overflow: auto; max-height: 580px; border: 1px solid #dde2e7; }
        table { border-collapse: collapse; width: 100%; font-size: 13px; }
        th { position: sticky; top: 0; background: #eef3f8; z-index: 1; }
        th, td { border-bottom: 1px solid #e4e7eb; border-right: 1px solid #edf0f2; padding: 7px 9px; text-align: left; white-space: nowrap; }
        .notice { color: #6d7782; font-size: 12px; margin-top: 7px; }
    </style>
</head>
<body>
<main>
    <div id="chatbot_trace_meta" style="display:none"
         data-trace-mode="agent"
         data-trace-provider="{{ $agent_result['provider'] ?? '' }}"
         data-trace-model="{{ $agent_result['model'] ?? '' }}"
         data-result-status="agent result"></div>

    <section class="card">
        <div class="card-body">
            <h2>Clinomics answer</h2>
            <div class="question"><strong>Question:</strong> {{ $query }}</div>
            <div class="answer">{{ $agent_result['answer'] ?? 'No answer was returned.' }}</div>
            <div class="meta">
                <span class="badge">Scope: {{ str_replace('_', ' ', $scope) }}</span>
                <span class="badge">Cohort: {{ $context_name }}</span>
                <span class="badge">Provider: {{ $agent_result['provider'] ?? 'unknown' }}</span>
                <span class="badge">Model: {{ $agent_result['model'] ?? 'unknown' }}</span>
                <span class="badge">Steps: {{ $agent_result['steps'] ?? 0 }}</span>
                <span class="badge">Tool calls: {{ $agent_result['tool_calls'] ?? 0 }}</span>
                @if (!empty($agent_result['used_summarizer']))
                    <span class="badge">Synthesis subagent used</span>
                @endif
            </div>
        </div>
    </section>

    @if (!empty($agent_result['executions']))
        <section class="card">
            <div class="card-body" style="padding-bottom:6px;">
                <h3>Supporting tool results</h3>
            </div>
            @foreach ($agent_result['executions'] as $index => $execution)
                <details {{ $index === 0 ? 'open' : '' }}>
                    <summary>{{ $index + 1 }}. {{ $execution['tool'] }}{{ !empty($execution['status']) ? ' — '.$execution['status'] : '' }}</summary>
                    <div class="execution">
                        @if (!empty($execution['summary']))
                            <p class="tool-summary">{{ $execution['summary'] }}</p>
                        @endif
                        <strong>Arguments</strong>
                        <pre>{{ json_encode($execution['arguments'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>

                        @if (!empty($execution['table']))
                            @php
                                $table = $execution['table'];
                                $columns = array_map(static function ($column) {
                                    return is_array($column) ? ($column['title'] ?? $column['name'] ?? '') : (string) $column;
                                }, (array) ($table['columns'] ?? []));
                            @endphp
                            <div class="table-wrap">
                                <table>
                                    @if (!empty($columns))
                                        <thead><tr>@foreach ($columns as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
                                    @endif
                                    <tbody>
                                    @foreach ((array) ($table['rows'] ?? []) as $row)
                                        <tr>
                                            @foreach ((array) $row as $cell)
                                                <td>{{ is_scalar($cell) || $cell === null ? $cell : json_encode($cell, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="notice">
                                {{ $table['row_count'] ?? count($table['rows'] ?? []) }} row(s).
                                @if (!empty($table['truncated'])) Showing the first 100 rows in this preview. @endif
                            </div>
                        @else
                            <strong>Result preview</strong>
                            <pre>{{ json_encode($execution['preview'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        @endif
                    </div>
                </details>
            @endforeach
        </section>
    @endif
</main>
</body>
</html>
