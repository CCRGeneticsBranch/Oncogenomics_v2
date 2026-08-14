@extends('layouts.default')
@section('title', 'Clinomics Chatbot')
@section('content')

<div class="container-fluid" style="padding:20px;">
    <div class="card" style="padding:16px;">
        <div class="card-header bg-primary text-white" style="margin:-16px -16px 16px;">
            Clinomics Chatbot
        </div>

        <form id="chatbot_form" method="get" action="{!!url('/viewChatbot')!!}">
            <input type="hidden" name="scope" value="{!!$chatbot_scope!!}">
            <input type="hidden" name="cohort_id" value="{!!htmlspecialchars($chatbot_cohort_id, ENT_QUOTES, 'UTF-8')!!}">
            <label for="chatbot_page_query" style="font-weight:600;">Ask a question:</label>
            <textarea id="chatbot_page_query" name="query" rows="3" class="form-control" required
                      placeholder="Examples: show available projects; show available cancer types">{!!htmlspecialchars($chatbot_query, ENT_QUOTES, 'UTF-8')!!}</textarea>
            <div style="display:flex;gap:8px;align-items:center;margin-top:10px;">
                <button type="submit" class="btn btn-primary">Run</button>
                <span id="chatbot_page_hint" style="color:#666;">
                    Scope: {!!str_replace('_', ' ', $chatbot_scope)!!}
                </span>
            </div>
        </form>
    </div>

    <iframe id="chatbot_page_result" title="Chatbot results"
            style="display:none;margin-top:16px;width:100%;height:calc(100vh - 260px);min-height:720px;border:1px solid #ccc;border-radius:4px;background:#fff;"></iframe>
</div>

<script type="text/javascript">
    $(function() {
        var query = @json($chatbot_query);
        var scope = @json($chatbot_scope);
        var cohortId = @json($chatbot_cohort_id);

        if (query !== '') {
            $('#chatbot_page_hint').text('Status: running query...');
            var resultUrl = @json(url('/runChatbot'))
                + '/' + encodeURIComponent(scope)
                + '/' + encodeURIComponent(cohortId)
                + '/' + encodeURIComponent(query);

            $('#chatbot_page_result').off('load').on('load', function() {
                $('#chatbot_page_hint').text('Results:');
            }).attr('src', resultUrl).show();
        }
    });
</script>

@stop
