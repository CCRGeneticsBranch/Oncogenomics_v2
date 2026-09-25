<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 16px; color: #222; }
        h3 { margin: 0 0 8px; }
        .summary { margin-bottom: 14px; color: #555; }
        pre { padding: 12px; border: 1px solid #ddd; border-radius: 4px; background: #f7f7f7; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div id="chatbot_trace_meta" style="display:none"
         data-trace-mode="{{ $trace_mode }}"
         data-trace-provider="{{ $trace_provider }}"
         data-trace-model="{{ $trace_model }}"
         data-result-status="structured result"></div>
    <h3>{{ $title }}</h3>
    <div><strong>Scope:</strong> {{ $context_name }}</div>
    @if ($summary !== '')
        <div class="summary">{{ $summary }}</div>
    @endif
    <pre>{{ json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
</body>
</html>
