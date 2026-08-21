@extends(!empty($chatbot_embedded) ? 'layouts.chatbot_embedded' : 'layouts.default')
@section('title', 'OncoGenomics Chatbot')
@section('content')

<style>
    .clinomics-chat-page {
        background: #f5f7fa;
        min-height: calc(100vh - 118px);
        padding: 18px;
        font-size: 17px;
    }
    .clinomics-chat-shell {
        width: 95%;
        max-width: none;
        height: calc(100vh - 154px);
        min-height: 620px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: #fff;
        border: 1px solid #dce2e8;
        border-radius: 12px;
        box-shadow: 0 5px 22px rgba(32, 45, 58, .08);
    }
    .clinomics-chat-header {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        padding: 15px 20px;
        border-bottom: 1px solid #e4e8ed;
        background: #fff;
    }
    .clinomics-chat-title { margin: 0; font-size: 24px; font-weight: 650; color: #1f2933; }
    .clinomics-chat-context { margin-top: 3px; color: #68737f; font-size: 15px; }
    .clinomics-chat-actions { display: flex; align-items: center; gap: 8px; margin-left: auto; max-width: 100%; }
    .clinomics-chat-history { max-width: 300px; min-width: 0; font-size: 14px; }
    .clinomics-chat-new { flex: 0 0 auto; min-height: 40px; font-size: 15px; white-space: nowrap; }
    .clinomics-chat-embedded-actions {
        display: flex;
        flex: 0 0 auto;
        justify-content: flex-end;
        padding: 8px 12px;
        border-bottom: 1px solid #e4e8ed;
        background: #fff;
    }
    .clinomics-chat-messages {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        padding: 24px 18px 16px;
        scroll-behavior: smooth;
    }
    .clinomics-chat-empty {
        max-width: 640px;
        margin: 12vh auto 0;
        padding: 30px;
        text-align: center;
        color: #5f6b76;
    }
    .clinomics-chat-empty h2 { color: #27313b; font-size: 28px; margin: 0 0 10px; }
    .chat-message {
        display: flex;
        gap: 12px;
        width: 100%;
        max-width: none;
        margin: 0 auto 24px;
    }
    .chat-avatar {
        width: 34px;
        height: 34px;
        flex: 0 0 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #1769aa;
        color: #fff;
        font-size: 14px;
        font-weight: 700;
    }
    .chat-message-user .chat-avatar { background: #586574; }
    .chat-message-body { flex: 1; min-width: 0; }
    .chat-message-role { margin: 1px 0 7px; color: #39434d; font-size: 15px; font-weight: 700; }
    .chat-message-user .chat-message-content {
        display: inline-block;
        max-width: 82%;
        padding: 11px 14px;
        border-radius: 15px 15px 4px 15px;
        background: #eaf2fb;
    }
    .chat-message-content {
        color: #202932;
        font-size: 17px;
        line-height: 1.6;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }
    .chat-message-assistant .chat-message-content.chat-markdown { white-space: normal; }
    .chat-markdown > :first-child { margin-top: 0; }
    .chat-markdown > :last-child { margin-bottom: 0; }
    .chat-markdown p, .chat-markdown ul, .chat-markdown ol { margin: 0 0 10px; }
    .chat-markdown pre {
        max-width: 100%;
        padding: 10px;
        overflow: auto;
        border: 1px solid #e1e5e9;
        border-radius: 5px;
        background: #f7f9fb;
        white-space: pre;
    }
    .chat-markdown code { color: #35414c; }
    .chat-markdown table {
        display: block;
        width: max-content;
        min-width: 100%;
        max-width: 100%;
        margin: 10px 0 14px;
        overflow-x: auto;
        border-collapse: collapse;
        font-size: 15px;
    }
    .chat-markdown th {
        background: #eef3f7;
        font-weight: 650;
    }
    .chat-markdown th, .chat-markdown td {
        padding: 7px 9px;
        border: 1px solid #dfe5ea;
        text-align: left;
        vertical-align: top;
        white-space: nowrap;
    }
    .chat-message-error .chat-message-content { color: #9c2f2f; }
    .chat-cursor::after { content: '▍'; color: #1769aa; animation: chat-blink 1s steps(2, start) infinite; }
    @keyframes chat-blink { 50% { opacity: 0; } }
    .chat-activity {
        margin: 10px 0 12px;
        border: 1px solid #e1e6eb;
        border-radius: 7px;
        background: #fafbfc;
    }
    .chat-activity > summary,
    .chat-evidence > summary {
        cursor: pointer;
        padding: 9px 12px;
        color: #46525e;
        font-size: 15px;
        font-weight: 650;
    }
    .chat-activity-note { padding: 0 12px 8px; color: #7b858f; font-size: 13px; }
    .chat-activity-list { padding: 0 12px 9px; }
    .chat-activity-row {
        position: relative;
        margin: 0 0 6px 6px;
        padding-left: 16px;
        color: #5a6570;
        font-size: 14px;
        line-height: 1.45;
    }
    .chat-activity-row::before {
        content: '';
        position: absolute;
        left: 0;
        top: 6px;
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #7b8b99;
    }
    .chat-activity-row.is-running::before { background: #d88c00; }
    .chat-activity-row.is-complete::before { background: #238636; }
    .chat-activity-row.is-error::before { background: #c43d3d; }
    .chat-tool-arguments {
        margin-top: 4px;
        padding: 7px;
        max-height: 150px;
        overflow: auto;
        border: 1px solid #e4e8ec;
        border-radius: 4px;
        background: #fff;
        color: #505b66;
        font-size: 13px;
        white-space: pre-wrap;
    }
    .chat-meta { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
    .chat-badge {
        padding: 3px 8px;
        border: 1px solid #cfe0f0;
        border-radius: 12px;
        background: #eef6fd;
        color: #315d7d;
        font-size: 13px;
    }
    .chat-evidence {
        margin-top: 12px;
        border: 1px solid #dce3e9;
        border-radius: 7px;
        background: #fff;
    }
    .chat-execution { padding: 10px 12px; border-top: 1px solid #edf0f2; }
    .chat-execution-title { font-size: 15px; font-weight: 650; color: #35414c; }
    .chat-execution-summary { margin: 5px 0; color: #66717c; font-size: 14px; }
    .chat-execution details summary { cursor: pointer; color: #62707d; font-size: 13px; }
    .chat-table-wrap { max-height: 430px; margin-top: 8px; overflow: auto; border: 1px solid #e1e5e9; }
    .chat-table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .chat-table th { position: sticky; top: 0; z-index: 1; background: #eef3f7; }
    .chat-table th, .chat-table td {
        padding: 6px 8px;
        border-right: 1px solid #e8ebee;
        border-bottom: 1px solid #e4e8eb;
        text-align: left;
        white-space: nowrap;
    }
    .chat-table-note { margin-top: 5px; color: #78838d; font-size: 13px; }
    .chat-artifact { margin-top: 10px; }
    .chat-artifact-title { margin-bottom: 5px; color: #48545f; font-size: 14px; font-weight: 650; }
    .chat-artifact img { display: block; max-width: 100%; max-height: 760px; border: 1px solid #e0e5e9; border-radius: 5px; background: #fff; }
    .chat-chart {
        margin-top: 10px;
        padding: 10px;
        border: 1px solid #dfe5ea;
        border-radius: 6px;
        background: #fff;
    }
    .chat-chart-title { margin-bottom: 7px; color: #35414c; font-size: 15px; font-weight: 650; }
    .chat-chart-viewport {
        width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        border: 1px solid #edf0f2;
        border-radius: 4px;
        background: #fff;
    }
    .chat-chart-canvas { width: 100%; min-width: 620px; min-height: 560px; }
    .chat-chart-status { margin-top: 7px; color: #66717c; font-size: 14px; line-height: 1.45; }
    .chat-chart-status.is-loading { color: #5f6f7d; }
    .chat-chart-status.is-complete { color: #3d6750; }
    .chat-chart-status.is-error { color: #9c2f2f; }
    .chat-result-links { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 9px; }
    .chat-primary-result-links {
        margin: 12px 0 2px;
        padding: 10px 12px;
        border: 1px solid #cfe0f0;
        border-radius: 7px;
        background: #f4f9fd;
    }
    .chat-pruned-note { margin-top: 8px; color: #7a6a3c; font-size: 13px; }
    .clinomics-chat-composer-wrap {
        position: sticky;
        bottom: 0;
        z-index: 10;
        flex: 0 0 auto;
        padding: 10px 16px 14px;
        border-top: 1px solid #e1e6eb;
        background: linear-gradient(to bottom, rgba(255,255,255,.94), #fff 28%);
    }
    .clinomics-chat-composer {
        width: 100%;
        max-width: none;
        margin: 0 auto;
        display: flex;
        align-items: flex-end;
        gap: 10px;
    }
    .clinomics-chat-input {
        min-height: 82px;
        max-height: 210px;
        resize: vertical;
        border-radius: 10px;
        padding: 10px 12px;
        font-size: 17px;
        line-height: 1.45;
    }
    .clinomics-chat-send { min-width: 82px; height: 44px; font-size: 16px; }
    .clinomics-chat-status {
        width: 100%;
        max-width: none;
        min-height: 18px;
        margin: 5px auto 0;
        color: #707b86;
        font-size: 13px;
    }
    @media (max-width: 700px) {
        .clinomics-chat-page { padding: 7px; }
        .clinomics-chat-shell { height: calc(100vh - 105px); min-height: 520px; border-radius: 6px; }
        .clinomics-chat-header { padding: 11px 12px; }
        .clinomics-chat-actions { width: 100%; justify-content: flex-end; }
        .clinomics-chat-history { flex: 1 1 140px; max-width: none; }
        .clinomics-chat-new { min-height: 40px; }
        .clinomics-chat-messages { padding: 16px 10px 8px; }
        .clinomics-chat-composer-wrap { padding: 8px; }
        .chat-message-user .chat-message-content { max-width: 100%; }
        .chat-chart { padding: 6px; }
        .chat-chart-canvas { min-width: 460px; min-height: 480px; }
    }
    .clinomics-chat-page.is-embedded {
        box-sizing: border-box;
        width: 100%;
        height: 100vh;
        height: 100dvh;
        min-height: 0;
        padding: 0;
        overflow: hidden;
        background: #fff;
    }
    .clinomics-chat-page.is-embedded .clinomics-chat-shell {
        box-sizing: border-box;
        width: 100%;
        height: 100%;
        min-height: 0;
        margin: 0;
        border: 0;
        border-radius: 0;
        box-shadow: none;
    }
    .clinomics-chat-page.is-embedded .clinomics-chat-composer-wrap {
        padding-bottom: max(10px, env(safe-area-inset-bottom));
    }
</style>

<div class="clinomics-chat-page{{ !empty($chatbot_embedded) ? ' is-embedded' : '' }}">
    <section class="clinomics-chat-shell" aria-label="OncoGenomics chatbot conversation">
        @unless (!empty($chatbot_embedded))
        <header class="clinomics-chat-header">
            <div>
                <h1 class="clinomics-chat-title">OncoGenomics Chatbot</h1>
                <div class="clinomics-chat-context">
                    {{ ucfirst(str_replace('_', ' ', $chatbot_scope)) }} scope
                    @if (strtolower((string) $chatbot_scope) !== 'global')
                        · {{ $chatbot_context_name }}
                    @endif
                </div>
            </div>
            <div class="clinomics-chat-actions">
                @if (count($chatbot_recent_conversations) > 1)
                    <label for="chat_history" class="sr-only">Recent conversations</label>
                    <select id="chat_history" class="form-control form-control-sm clinomics-chat-history">
                        @foreach ($chatbot_recent_conversations as $recent)
                            <option value="{{ url('/viewChatbot').'?'.http_build_query(array_filter([
                                'conversation_id' => $recent['id'],
                                'embedded' => !empty($chatbot_embedded) ? 1 : null,
                            ])) }}"
                                    {{ $recent['id'] === $chatbot_conversation_id ? 'selected' : '' }}>
                                @if (strtolower((string) ($recent['scope'] ?? '')) === 'global')
                                    Global · {{ $recent['title'] }}
                                @else
                                    {{ $recent['cohort_name'] }} · {{ $recent['title'] }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                @endif
                <a class="btn btn-outline-primary btn-sm clinomics-chat-new" href="{{ $chatbot_new_url }}">New chat</a>
            </div>
        </header>
        @endunless

        @if (!empty($chatbot_embedded))
            <nav class="clinomics-chat-embedded-actions" aria-label="Conversation actions">
                <a class="btn btn-outline-primary btn-sm clinomics-chat-new"
                   href="{{ $chatbot_new_url }}"
                   aria-label="Start a new conversation">New chat</a>
            </nav>
        @endif

        <div id="chat_messages" class="clinomics-chat-messages" aria-live="polite">
            <div id="chat_empty" class="clinomics-chat-empty">
                <h2>What would you like to explore?</h2>
                <p>Ask about the OncoGenomics data available in this scope. Follow-up questions will use the conversation above for context.</p>
            </div>
        </div>

        <footer class="clinomics-chat-composer-wrap">
            <form id="chat_form" class="clinomics-chat-composer">
                <label for="chat_query" class="sr-only">Ask a follow-up question</label>
                <textarea id="chat_query" class="form-control clinomics-chat-input" rows="3" required
                          placeholder="Ask a question or a follow-up…"></textarea>
                <button id="chat_send" type="submit" class="btn btn-primary clinomics-chat-send">Send</button>
            </form>
            <div id="chat_status" class="clinomics-chat-status" role="status">
                Enter sends · Shift+Enter adds a new line. Activity shows tool use and progress, not private model reasoning.
            </div>
        </footer>
    </section>
</div>

<script type="text/javascript">
(function () {
    'use strict';

    var messagesElement = document.getElementById('chat_messages');
    var emptyElement = document.getElementById('chat_empty');
    var formElement = document.getElementById('chat_form');
    var queryElement = document.getElementById('chat_query');
    var sendElement = document.getElementById('chat_send');
    var statusElement = document.getElementById('chat_status');
    var historyElement = document.getElementById('chat_history');
    var streamUrl = @json($chatbot_stream_url);
    var canonicalUrl = @json($chatbot_conversation_url);
    var csrfToken = @json(csrf_token());
    var initialMessages = @json($chatbot_messages);
    var initialQuery = @json($chatbot_query);
    var plotlyUrl = @json(url('/packages/plotly/plotly.min.js'));
    var plotlyLoadPromise = null;
    var isRunning = false;

    window.history.replaceState({}, document.title, canonicalUrl);

    function text(value) {
        if (value === null || typeof value === 'undefined') return '';
        if (typeof value === 'string') return value;
        try { return JSON.stringify(value); } catch (error) { return String(value); }
    }

    function structuredLinkCell(value) {
        var candidate = value;
        if (typeof candidate === 'string') {
            var trimmed = candidate.trim();
            if (trimmed.charAt(0) !== '{' || trimmed.charAt(trimmed.length - 1) !== '}') return null;
            try { candidate = JSON.parse(trimmed); } catch (error) { return null; }
        }
        if (!candidate || typeof candidate !== 'object' || candidate.type !== 'link') return null;
        var url = text(candidate.url);
        var safe = /^https?:\/\//i.test(url) || (/^\/(?!\/)/.test(url));
        if (!safe) return null;
        return {url: url, label: text(candidate.label || url)};
    }

    function displayToolName(value) {
        var name = text(value);
        return name.toLowerCase() === 'clinomics_result_synthesizer'
            ? 'OncoGenomics result synthesizer'
            : name;
    }

    function createUuid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (character) {
            var random = Math.random() * 16 | 0;
            var value = character === 'x' ? random : (random & 3 | 8);
            return value.toString(16);
        });
    }

    function scrollToBottom(force) {
        var nearBottom = messagesElement.scrollHeight - messagesElement.scrollTop - messagesElement.clientHeight < 180;
        if (force || nearBottom) {
            messagesElement.scrollTop = messagesElement.scrollHeight;
        }
    }

    function clearEmptyState() {
        if (emptyElement && emptyElement.parentNode) {
            emptyElement.parentNode.removeChild(emptyElement);
        }
        emptyElement = null;
    }

    function appendActivity(bubble, event) {
        if (!bubble.activityDetails) return;
        bubble.activityDetails.style.display = '';
        bubble.activityDetails.open = true;
        var row = document.createElement('div');
        row.className = 'chat-activity-row';

        if (event.type === 'tool_started') {
            row.classList.add('is-running');
            row.textContent = 'Calling ' + displayToolName(event.tool);
            if (event.tool_id) bubble.toolRows[event.tool_id] = row;
            if (event.arguments && Object.keys(event.arguments).length > 0) {
                var args = document.createElement('pre');
                args.className = 'chat-tool-arguments';
                args.textContent = JSON.stringify(event.arguments, null, 2);
                row.appendChild(args);
            }
        } else if (event.type === 'tool_finished') {
            var existing = event.tool_id ? bubble.toolRows[event.tool_id] : null;
            var completionText = (event.successful === false ? 'Failed ' : 'Completed ') + displayToolName(event.tool);
            if (typeof event.row_count !== 'undefined' && event.row_count !== null) completionText += ' — ' + event.row_count + ' row(s)';
            else if (event.summary) completionText += ' — ' + text(event.summary);
            if (existing) {
                existing.classList.remove('is-running');
                existing.classList.add(event.successful === false ? 'is-error' : 'is-complete');
                existing.firstChild.nodeValue = completionText;
                return;
            }
            row.classList.add(event.successful === false ? 'is-error' : 'is-complete');
            row.textContent = completionText;
        } else {
            row.textContent = text(event.message || 'Working…');
        }

        bubble.activityList.appendChild(row);
    }

    function addMetaBadges(bubble, meta) {
        if (!meta) return;
        var values = [];
        if (meta.provider) values.push(['provider', 'Provider: ' + meta.provider]);
        if (meta.model) values.push(['model', 'Model: ' + meta.model]);
        if (typeof meta.steps !== 'undefined') values.push(['steps', 'Steps: ' + meta.steps]);
        if (typeof meta.tool_calls !== 'undefined') values.push(['tool_calls', 'Tool calls: ' + meta.tool_calls]);
        if (meta.used_summarizer) values.push(['summarizer', 'Synthesis subagent used']);
        if (values.length === 0) return;

        values.forEach(function (item) {
            if (bubble.metaKeys[item[0]]) {
                bubble.metaKeys[item[0]].textContent = item[1];
                return;
            }
            var badge = document.createElement('span');
            badge.className = 'chat-badge';
            badge.textContent = item[1];
            bubble.meta.appendChild(badge);
            bubble.metaKeys[item[0]] = badge;
        });
    }

    function plotlyIsReady() {
        return window.Plotly && typeof window.Plotly.newPlot === 'function';
    }

    function loadPlotly() {
        if (plotlyIsReady()) return Promise.resolve(window.Plotly);
        if (plotlyLoadPromise) return plotlyLoadPromise;

        plotlyLoadPromise = new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            var settled = false;
            var timeout = window.setTimeout(function () {
                if (settled) return;
                settled = true;
                plotlyLoadPromise = null;
                if (script.parentNode) script.parentNode.removeChild(script);
                reject(new Error('The Plotly library took too long to load.'));
            }, 15000);

            function finish(error) {
                if (settled) return;
                settled = true;
                window.clearTimeout(timeout);
                if (error || !plotlyIsReady()) {
                    plotlyLoadPromise = null;
                    reject(error || new Error('The Plotly library did not initialize.'));
                    return;
                }
                resolve(window.Plotly);
            }

            script.src = plotlyUrl;
            script.async = true;
            script.dataset.clinomicsPlotly = 'true';
            script.onload = function () { finish(null); };
            script.onerror = function () { finish(new Error('The Plotly library could not be loaded.')); };
            document.head.appendChild(script);
        });

        return plotlyLoadPromise;
    }

    function cloneJsonValue(value, fallback) {
        try {
            return JSON.parse(JSON.stringify(value));
        } catch (error) {
            return fallback;
        }
    }

    function numericMedian(values) {
        var sorted = (Array.isArray(values) ? values : [])
            .map(function (value) { return Number(value); })
            .filter(function (value) { return Number.isFinite(value); })
            .sort(function (left, right) { return left - right; });
        if (sorted.length === 0) return null;
        var middle = Math.floor(sorted.length / 2);
        return sorted.length % 2
            ? sorted[middle]
            : (sorted[middle - 1] + sorted[middle]) / 2;
    }

    function normalizeViolinPayload(data, layout, chart) {
        var violinTraces = data.filter(function (trace) {
            return trace && trace.type === 'violin';
        });
        if (violinTraces.length === 0) {
            return { data: data, layout: layout, omitted: 0, minimum: 0 };
        }

        var configuredMinimum = Number(chart && chart.minimum_group_size);
        var minimum = Number.isFinite(configuredMinimum) && configuredMinimum >= 2
            ? configuredMinimum
            : (violinTraces.length > 20 ? 5 : 2);
        var groups = Object.create(null);
        var encountered = [];
        violinTraces.forEach(function (trace) {
            var values = Array.isArray(trace.y) ? trace.y : [];
            var categories = Array.isArray(trace.x) ? trace.x : [];
            values.forEach(function (value, index) {
                value = Number(value);
                if (!Number.isFinite(value)) return;
                var group = text(categories[index] || trace.name || 'Group');
                if (!Object.prototype.hasOwnProperty.call(groups, group)) {
                    groups[group] = [];
                    encountered.push(group);
                }
                groups[group].push(value);
            });
        });

        var viable = encountered.filter(function (group) {
            return groups[group].length >= minimum;
        });
        var omitted = encountered.length - viable.length;

        var orderMatch = text(chart && chart.summary).match(/order:\s*(median_desc|median_asc)/i);
        if (orderMatch) {
            var descending = orderMatch[1].toLowerCase() === 'median_desc';
            viable.sort(function (left, right) {
                var leftMedian = numericMedian(groups[left]);
                var rightMedian = numericMedian(groups[right]);
                var comparison = descending
                    ? rightMedian - leftMedian
                    : leftMedian - rightMedian;
                return comparison || left.localeCompare(right);
            });
        }

        var normalizedTraces = viable.map(function (group) {
            var trace = cloneJsonValue(violinTraces[0], {});
            var median = numericMedian(groups[group]);
            trace.name = group;
            delete trace.x;
            delete trace.customdata;
            trace.x0 = group;
            trace.y = groups[group];
            trace.points = false;
            trace.spanmode = 'hard';
            trace.scalemode = 'width';
            trace.width = 0.8;
            trace.side = 'both';
            trace.box = { visible: false };
            trace.meanline = { visible: false };
            trace.line = { color: 'rgb(45, 111, 164)', width: 1.5 };
            trace.fillcolor = 'rgba(45, 111, 164, 0.48)';
            trace.hoveron = 'violins';
            trace.meta = [group, groups[group].length, median];
            trace.hovertemplate = 'Group: %{meta[0]} · Expression: %{y:.4f} · Samples: %{meta[1]} · Median: %{meta[2]:.4f}';
            return trace;
        });

        var nonViolin = data.filter(function (trace) {
            return !trace || trace.type !== 'violin';
        });
        data = nonViolin.concat(normalizedTraces);
        layout.xaxis = layout.xaxis && typeof layout.xaxis === 'object' ? layout.xaxis : {};
        layout.xaxis.categoryorder = 'array';
        layout.xaxis.categoryarray = viable;

        return { data: data, layout: layout, omitted: omitted, minimum: minimum };
    }

    function renderPlotlyChart(block, chart) {
        if (!chart || chart.type !== 'plotly') return;

        var chartBlock = document.createElement('section');
        chartBlock.className = 'chat-chart';
        var chartTitle = document.createElement('div');
        chartTitle.className = 'chat-chart-title';
        chartTitle.textContent = text(chart.title || 'Interactive result plot');
        var viewport = document.createElement('div');
        viewport.className = 'chat-chart-viewport';
        var canvas = document.createElement('div');
        canvas.className = 'chat-chart-canvas';
        canvas.setAttribute('role', 'img');
        canvas.setAttribute('aria-label', text(chart.title || 'Interactive result plot'));
        var chartStatus = document.createElement('div');
        chartStatus.className = 'chat-chart-status is-loading';
        chartStatus.setAttribute('role', 'status');
        chartStatus.setAttribute('aria-live', 'polite');
        chartStatus.textContent = 'Loading the interactive plot…';

        viewport.appendChild(canvas);
        chartBlock.appendChild(chartTitle);
        chartBlock.appendChild(viewport);
        chartBlock.appendChild(chartStatus);
        block.appendChild(chartBlock);

        if (!Array.isArray(chart.data) || chart.data.length === 0) {
            viewport.style.display = 'none';
            chartStatus.className = 'chat-chart-status is-error';
            chartStatus.textContent = 'Unable to render this plot because it contains no chart data.';
            return;
        }

        var data = cloneJsonValue(chart.data, []);
        var layout = cloneJsonValue(chart.layout && typeof chart.layout === 'object' ? chart.layout : {}, {});
        var config = cloneJsonValue(chart.config && typeof chart.config === 'object' ? chart.config : {}, {});
        var violinNormalization = normalizeViolinPayload(data, layout, chart);
        data = violinNormalization.data;
        layout = violinNormalization.layout;
        if (data.length === 0) {
            viewport.style.display = 'none';
            chartStatus.className = 'chat-chart-status is-error';
            chartStatus.textContent = 'A violin distribution requires at least '
                + violinNormalization.minimum + ' observations in a group.';
            return;
        }
        config.displaylogo = false;
        if (typeof config.responsive === 'undefined') config.responsive = true;

        loadPlotly()
            .then(function (plotly) {
                return Promise.resolve(plotly.newPlot(canvas, data, layout, config));
            })
            .then(function () {
                chartStatus.className = 'chat-chart-status is-complete';
                chartStatus.textContent = text(chart.summary || 'Interactive plot rendered.');
                if (violinNormalization.omitted > 0) {
                    chartStatus.textContent += ' ' + violinNormalization.omitted
                        + ' sparse group(s) were omitted because they cannot form a stable violin density.';
                }
                scrollToBottom(false);
            })
            .catch(function (error) {
                viewport.style.display = 'none';
                chartStatus.className = 'chat-chart-status is-error';
                chartStatus.textContent = 'Unable to render this plot: '
                    + text(error && error.message ? error.message : error || 'unknown error');
            });
    }

    function renderExecutions(bubble, executions) {
        if (!Array.isArray(executions) || executions.length === 0 || bubble.evidenceRendered) return;
        bubble.evidenceRendered = true;

        var primaryLinks = document.createElement('div');
        primaryLinks.className = 'chat-result-links chat-primary-result-links';
        primaryLinks.setAttribute('aria-label', 'Primary result links');
        var seenLinks = Object.create(null);
        executions.forEach(function (execution) {
            (Array.isArray(execution.links) ? execution.links : []).forEach(function (link) {
                var url = text(link && link.url);
                if (!/^https?:\/\//i.test(url) || seenLinks[url]) return;
                seenLinks[url] = true;

                var anchor = document.createElement('a');
                anchor.className = 'btn btn-primary btn-sm';
                anchor.href = url;
                anchor.target = '_blank';
                anchor.rel = 'noopener noreferrer';
                anchor.textContent = text(link && link.label ? link.label : 'Open result');
                primaryLinks.appendChild(anchor);
            });
        });
        if (primaryLinks.children.length > 0) {
            bubble.body.insertBefore(primaryLinks, bubble.meta);
        }

        var evidence = document.createElement('details');
        evidence.className = 'chat-evidence';
        var evidenceSummary = document.createElement('summary');
        evidenceSummary.textContent = 'Supporting tool results (' + executions.length + ')';
        evidence.appendChild(evidenceSummary);
        evidence.open = executions.some(function (execution) {
            var hasChart = Array.isArray(execution.charts)
                && execution.charts.some(function (chart) { return chart && chart.type === 'plotly'; });
            var hasTable = execution.table
                && Array.isArray(execution.table.rows)
                && execution.table.rows.length > 0;

            return hasChart || hasTable;
        });

        executions.forEach(function (execution, index) {
            var block = document.createElement('div');
            block.className = 'chat-execution';
            var title = document.createElement('div');
            title.className = 'chat-execution-title';
            title.textContent = (index + 1) + '. ' + text(execution.tool) + (execution.status ? ' — ' + execution.status : '');
            block.appendChild(title);

            if (execution.summary) {
                var summary = document.createElement('div');
                summary.className = 'chat-execution-summary';
                summary.textContent = text(execution.summary);
                block.appendChild(summary);
            }

            if (execution.arguments && Object.keys(execution.arguments).length > 0) {
                var argumentDetails = document.createElement('details');
                var argumentSummary = document.createElement('summary');
                argumentSummary.textContent = 'Arguments';
                var argumentPre = document.createElement('pre');
                argumentPre.className = 'chat-tool-arguments';
                argumentPre.textContent = JSON.stringify(execution.arguments, null, 2);
                argumentDetails.appendChild(argumentSummary);
                argumentDetails.appendChild(argumentPre);
                block.appendChild(argumentDetails);
            }

            (Array.isArray(execution.charts) ? execution.charts : []).forEach(function (chart) {
                renderPlotlyChart(block, chart);
            });

            var table = execution.table;
            if (table && Array.isArray(table.rows)) {
                var tableWrap = document.createElement('div');
                tableWrap.className = 'chat-table-wrap';
                var tableElement = document.createElement('table');
                tableElement.className = 'chat-table';
                var columns = Array.isArray(table.columns) ? table.columns.map(function (column) {
                    return typeof column === 'object' && column !== null
                        ? text(column.title || column.name || '')
                        : text(column);
                }) : [];

                if (columns.length > 0) {
                    var head = document.createElement('thead');
                    var headRow = document.createElement('tr');
                    columns.forEach(function (column) {
                        var th = document.createElement('th');
                        th.textContent = column;
                        headRow.appendChild(th);
                    });
                    head.appendChild(headRow);
                    tableElement.appendChild(head);
                }

                var body = document.createElement('tbody');
                table.rows.forEach(function (sourceRow) {
                    var row = document.createElement('tr');
                    var cells = Array.isArray(sourceRow) ? sourceRow : Object.values(sourceRow || {});
                    cells.forEach(function (cell) {
                        var td = document.createElement('td');
                        var linkCell = structuredLinkCell(cell);
                        if (linkCell) {
                            var cellLink = document.createElement('a');
                            cellLink.href = linkCell.url;
                            cellLink.target = '_blank';
                            cellLink.rel = 'noopener noreferrer';
                            cellLink.textContent = linkCell.label;
                            td.appendChild(cellLink);
                        } else {
                            td.textContent = text(cell);
                        }
                        row.appendChild(td);
                    });
                    body.appendChild(row);
                });
                tableElement.appendChild(body);
                tableWrap.appendChild(tableElement);
                block.appendChild(tableWrap);

                var note = document.createElement('div');
                note.className = 'chat-table-note';
                note.textContent = text(table.row_count || table.rows.length) + ' row(s).'
                    + (table.truncated ? ' Showing the first 100 rows.' : '');
                block.appendChild(note);
            } else if (execution.preview && Object.keys(execution.preview).length > 0) {
                var previewDetails = document.createElement('details');
                var previewSummary = document.createElement('summary');
                previewSummary.textContent = 'Result preview';
                var previewPre = document.createElement('pre');
                previewPre.className = 'chat-tool-arguments';
                previewPre.textContent = JSON.stringify(execution.preview, null, 2);
                previewDetails.appendChild(previewSummary);
                previewDetails.appendChild(previewPre);
                block.appendChild(previewDetails);
            }

            (execution.artifacts || []).forEach(function (artifact) {
                if (artifact.type !== 'image' || !artifact.data_url) return;
                var artifactBlock = document.createElement('div');
                artifactBlock.className = 'chat-artifact';
                var artifactTitle = document.createElement('div');
                artifactTitle.className = 'chat-artifact-title';
                artifactTitle.textContent = text(artifact.title || 'Result image');
                var artifactImage = document.createElement('img');
                artifactImage.src = artifact.data_url;
                artifactImage.alt = text(artifact.title || 'OncoGenomics result image');
                artifactBlock.appendChild(artifactTitle);
                artifactBlock.appendChild(artifactImage);
                block.appendChild(artifactBlock);
            });

            evidence.appendChild(block);
        });

        bubble.body.appendChild(evidence);
    }

    function renderAssistantMarkdown(element, html, fallbackText) {
        element.classList.add('chat-markdown');
        if (typeof html === 'string' && html.trim() !== '') {
            // answer_html/content_html are generated by the server's sanitized
            // CommonMark renderer. Raw model output never reaches innerHTML.
            element.innerHTML = html;
            element.querySelectorAll('a').forEach(function (anchor) {
                anchor.rel = 'noopener noreferrer';
                if (/^https?:\/\//i.test(anchor.href)) anchor.target = '_blank';
            });
            return;
        }
        element.textContent = fallbackText || '';
    }

    function createMessage(role, content, meta, contentHtml) {
        clearEmptyState();
        var article = document.createElement('article');
        article.className = 'chat-message chat-message-' + role;

        var avatar = document.createElement('div');
        avatar.className = 'chat-avatar';
        avatar.textContent = role === 'user' ? 'You' : 'O';
        article.appendChild(avatar);

        var body = document.createElement('div');
        body.className = 'chat-message-body';
        var roleElement = document.createElement('div');
        roleElement.className = 'chat-message-role';
        roleElement.textContent = role === 'user' ? 'You' : 'OncoGenomics';
        var contentElement = document.createElement('div');
        contentElement.className = 'chat-message-content';
        if (role === 'assistant' && typeof contentHtml === 'string') {
            renderAssistantMarkdown(contentElement, contentHtml, content || '');
        } else {
            contentElement.textContent = content || '';
        }
        body.appendChild(roleElement);
        body.appendChild(contentElement);

        var bubble = {
            article: article,
            body: body,
            content: contentElement,
            meta: document.createElement('div'),
            activityDetails: null,
            activityList: null,
            toolRows: {},
            metaKeys: {},
            evidenceRendered: false
        };

        if (role === 'assistant') {
            var activity = document.createElement('details');
            activity.className = 'chat-activity';
            activity.style.display = 'none';
            var activitySummary = document.createElement('summary');
            activitySummary.textContent = 'Activity';
            var activityNote = document.createElement('div');
            activityNote.className = 'chat-activity-note';
            activityNote.textContent = 'Live tool activity and status are shown here; private model reasoning is not recorded.';
            var activityList = document.createElement('div');
            activityList.className = 'chat-activity-list';
            activity.appendChild(activitySummary);
            activity.appendChild(activityNote);
            activity.appendChild(activityList);
            body.insertBefore(activity, contentElement);
            bubble.activityDetails = activity;
            bubble.activityList = activityList;

            bubble.meta.className = 'chat-meta';
            body.appendChild(bubble.meta);
        }

        article.appendChild(body);
        messagesElement.appendChild(article);

        if (role === 'assistant' && meta) {
            (meta.activity || []).forEach(function (event) { appendActivity(bubble, event); });
            if (bubble.activityDetails) bubble.activityDetails.open = false;
            addMetaBadges(bubble, meta);
            renderExecutions(bubble, meta.executions || []);
            if (meta.evidence_pruned) {
                var pruned = document.createElement('div');
                pruned.className = 'chat-pruned-note';
                pruned.textContent = 'Older supporting-data previews were removed to keep this conversation within its storage limit.';
                body.appendChild(pruned);
            }
            if (meta.failed) article.classList.add('chat-message-error');
        }

        return bubble;
    }

    function handleStreamEvent(bubble, event) {
        if (!event || !event.type) return;
        if (event.type === 'heartbeat') {
            statusElement.textContent = text(event.message || 'The analysis is still running…');
        } else if (event.type === 'status' || event.type === 'tool_started' || event.type === 'tool_finished') {
            appendActivity(bubble, event);
            statusElement.textContent = event.type === 'status'
                ? text(event.message)
                : (event.type === 'tool_started'
                    ? 'Running '
                    : (event.successful === false ? 'Failed ' : 'Completed ')) + displayToolName(event.tool) + '…';
        } else if (event.type === 'answer_delta') {
            if (bubble.content.dataset.placeholder === 'yes') {
                bubble.content.textContent = '';
                bubble.content.dataset.placeholder = 'no';
            }
            bubble.content.classList.remove('chat-markdown');
            bubble.content.classList.add('chat-cursor');
            bubble.content.textContent += text(event.delta);
            statusElement.textContent = 'Receiving the answer…';
        } else if (event.type === 'answer_reset') {
            bubble.content.textContent = 'Continuing with another AI provider…';
            bubble.content.dataset.placeholder = 'yes';
            bubble.content.classList.add('chat-cursor');
            bubble.article.classList.remove('chat-message-error');
            statusElement.textContent = 'Switching AI providers…';
        } else if (event.type === 'meta') {
            addMetaBadges(bubble, event);
        } else if (event.type === 'complete') {
            bubble.content.classList.remove('chat-cursor');
            renderAssistantMarkdown(
                bubble.content,
                event.answer_html,
                text(event.answer || 'No answer was returned.')
            );
            if (bubble.activityDetails) bubble.activityDetails.open = false;
            addMetaBadges(bubble, event);
            renderExecutions(bubble, event.executions || []);
            statusElement.textContent = 'Complete. You can ask a follow-up question.';
        } else if (event.type === 'error') {
            bubble.content.classList.remove('chat-cursor');
            bubble.content.textContent = text(event.message || 'The chatbot could not complete this request.');
            bubble.article.classList.add('chat-message-error');
            appendActivity(bubble, {type: 'status', message: 'The request ended with an error.'});
            statusElement.textContent = 'Request failed. You can retry.';
        }
        scrollToBottom(true);
    }

    async function readErrorResponse(response) {
        try {
            var payload = await response.json();
            return payload.message || ('Request failed with HTTP ' + response.status + '.');
        } catch (error) {
            return 'Request failed with HTTP ' + response.status + '.';
        }
    }

    async function submitQuery(forcedQuery) {
        if (isRunning) return;
        var query = typeof forcedQuery === 'string' ? forcedQuery.trim() : queryElement.value.trim();
        if (!query) {
            queryElement.focus();
            return;
        }

        isRunning = true;
        sendElement.disabled = true;
        sendElement.textContent = 'Working…';
        queryElement.disabled = true;
        queryElement.value = '';

        createMessage('user', query, null);
        var assistant = createMessage('assistant', 'Preparing the analysis…', null);
        assistant.content.dataset.placeholder = 'yes';
        assistant.content.classList.add('chat-cursor');
        appendActivity(assistant, {type: 'status', message: 'Submitting the question securely.'});
        statusElement.textContent = 'Starting the analysis…';
        scrollToBottom(true);

        var terminalEventReceived = false;
        try {
            var response = await fetch(streamUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/x-ndjson',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({query: query, message_id: createUuid()})
            });
            if (!response.ok) throw new Error(await readErrorResponse(response));
            if (!response.body || !response.body.getReader) {
                throw new Error('This browser cannot read the live response stream.');
            }

            var reader = response.body.getReader();
            var decoder = new TextDecoder('utf-8');
            var buffer = '';
            while (true) {
                var chunk = await reader.read();
                buffer += decoder.decode(chunk.value || new Uint8Array(), {stream: !chunk.done});
                var lines = buffer.split('\n');
                buffer = lines.pop() || '';
                lines.forEach(function (line) {
                    if (!line.trim()) return;
                    try {
                        var event = JSON.parse(line);
                        if (event.type === 'complete' || event.type === 'error') terminalEventReceived = true;
                        handleStreamEvent(assistant, event);
                    } catch (error) {
                        // Ignore a malformed transport line; later lines can still complete the response.
                    }
                });
                if (chunk.done) break;
            }
            if (buffer.trim()) {
                var finalEvent = JSON.parse(buffer);
                if (finalEvent.type === 'complete' || finalEvent.type === 'error') terminalEventReceived = true;
                handleStreamEvent(assistant, finalEvent);
            }
            if (!terminalEventReceived) {
                throw new Error('The live response ended before the answer was complete.');
            }
        } catch (error) {
            if (!terminalEventReceived) {
                handleStreamEvent(assistant, {
                    type: 'error',
                    message: error && error.message ? error.message : 'The chatbot request failed.'
                });
            }
        } finally {
            isRunning = false;
            sendElement.disabled = false;
            sendElement.textContent = 'Send';
            queryElement.disabled = false;
            queryElement.focus();
        }
    }

    initialMessages.forEach(function (message) {
        createMessage(
            message.role === 'user' ? 'user' : 'assistant',
            text(message.content),
            message.meta || {},
            message.role === 'assistant' ? message.content_html : null
        );
    });
    scrollToBottom(true);

    formElement.addEventListener('submit', function (event) {
        event.preventDefault();
        submitQuery();
    });
    if (historyElement) {
        historyElement.addEventListener('change', function () {
            if (historyElement.value) window.location.href = historyElement.value;
        });
    }
    queryElement.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            submitQuery();
        }
    });

    if (initialQuery.trim() !== '') {
        window.setTimeout(function () { submitQuery(initialQuery); }, 0);
    } else {
        queryElement.focus();
    }
}());
</script>

@stop
