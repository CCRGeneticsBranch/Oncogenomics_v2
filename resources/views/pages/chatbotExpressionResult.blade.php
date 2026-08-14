{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::script('js/jquery-3.6.0.min.js') }}
{{ HTML::script('packages/plotly/plotly.min.js') }}

<style>
	* { box-sizing: border-box; }
	html, body { width: 100%; height: 100%; margin: 0; padding: 0; }
	body { background: #f5f6f6; color: #252525; font-family: Verdana, sans-serif; }
	.expression-result { width: 100%; padding: 14px; }
	.expression-header, #expression_plot, .expression-summary { background: #fff; width: 100%; }
	.expression-header { border-left: 4px solid #287271; padding: 10px 14px; margin-bottom: 10px; box-shadow: 0 1px 3px rgba(0, 0, 0, .12); }
	.expression-header h3 { margin: 0 0 7px; font-size: 18px; }
	.expression-meta { color: #5b5b5b; font-size: 12px; }
	.expression-meta span { display: inline-block; margin: 0 14px 3px 0; }
	.expression-status { margin-bottom: 10px; padding: 9px 14px; border: 1px solid #b8d7d4; background: #eef7f6; font-size: 12px; line-height: 1.5; }
	.expression-status.error { border-color: #d9a5a5; background: #fff1f1; color: #8b2525; }
	#expression_plot { min-height: 680px; width: 100%; overflow-x: auto; overflow-y: hidden; display: block; }
	.expression-summary { padding: 10px 14px; border-top: 1px solid #ddd; font-size: 12px; line-height: 1.5; }
	.raw-json { margin-top: 10px; font-size: 11px; }
	.raw-json summary { cursor: pointer; font-weight: 600; }
	.raw-json pre { max-height: 260px; overflow: auto; padding: 10px; border: 1px solid #ddd; background: #fff; white-space: pre-wrap; word-break: break-word; }
</style>

<div class="expression-result">
	<div id="chatbot_trace_meta" style="display:none"
		data-trace-mode="{{ $trace_mode ?? '' }}"
		data-trace-provider="{{ $trace_provider ?? '' }}"
		data-trace-model="{{ $trace_model ?? '' }}"></div>

	<div class="expression-header">
		<h3>{{ $plot_spec['title'] ?? ($gene . ' expression') }}</h3>
		<div class="expression-meta">
			<span><strong>Gene:</strong> {{ $gene }}</span>
			<span><strong>Project:</strong> {{ $project->name }}</span>
			<span><strong>Plot:</strong> {{ ucfirst($plot_type ?? 'violin') }}</span>
			<span><strong>Data:</strong> {{ ucfirst($dataset_scope ?? 'all') }}</span>
			<span><strong>Transform:</strong> {{ $transform ?? 'none' }}</span>
			@if(($group_order ?? 'none') !== 'none')
				<span><strong>Order:</strong> {{ $group_order }}</span>
			@endif
			@if(!empty($group_by))
				<span><strong>Grouped by:</strong> {{ $group_by }}</span>
				@foreach(($metadata_fields ?? []) as $dataset => $metadataField)
					@if(!empty($metadataField))
						<span><strong>{{ ucfirst($dataset) }} metadata:</strong> {{ $metadataField }}</span>
					@endif
				@endforeach
			@endif
		</div>
	</div>

	<div id="expression_status" class="expression-status">
		<strong>Status:</strong> Chart computed from raw expression values and rendered with Plotly.
		@if(!empty($trace_provider))
			Source: {{ $trace_provider }}@if(!empty($trace_model)) / {{ $trace_model }}@endif.
		@endif
		{{ $llm_decision_summary ?? '' }}
	</div>
	<div id="expression_plot"></div>
	@if(!empty($plot_spec['summary']))
		<div class="expression-summary">{{ $plot_spec['summary'] }}</div>
	@endif
	<details class="raw-json">
		<summary>Original raw getExpressionByGeneList JSON (plot transformation is applied separately)</summary>
		<pre id="expression_json"></pre>
	</details>
</div>

<script type="text/javascript">
(function () {
	// Listen for cleanup message from parent when new query starts
	window.addEventListener('message', function(event) {
		if (event.data && event.data.type === 'chatbot-cleanup') {
			var plotDiv = document.getElementById('expression_plot');
			if (plotDiv) {
				if (window.Plotly) {
					try { window.Plotly.purge(plotDiv); } catch (e) {}
				}
				plotDiv.innerHTML = '';
			}
			var statusDiv = document.getElementById('expression_status');
			if (statusDiv) {
				statusDiv.className = 'expression-status';
				statusDiv.textContent = 'Loading...';
			}
		}
	}, false);

	var spec = {!! json_encode($plot_spec, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
	var rawExpressionJson = {!! json_encode($raw_expression_json, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
	var provider = {!! json_encode($trace_provider ?? 'server') !!};
	var model = {!! json_encode($trace_model ?? '') !!};
	var llmPrompts = {!! json_encode($llm_prompts ?? [], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
	var rowCount = {{ (int)($plot_row_count ?? 0) }};

	if (window.console) {
		console.groupCollapsed('ClinOmics expression plot');
		console.log('Metadata selection prompt:\n' + (llmPrompts.metadata_selection || 'Not available'));
		console.log('Plot presentation:\n' + (llmPrompts.plot_presentation || 'Not available'));
		console.log('Deterministic Plotly chart specification:', spec);
		console.groupEnd();
	}

	function report(message) {
		if (window.parent && window.parent !== window) {
			window.parent.postMessage({ type: 'chatbot-status', message: message }, window.location.origin);
		}
	}

	try {
		document.getElementById('expression_json').textContent = JSON.stringify(JSON.parse(rawExpressionJson), null, 2);
		if (!spec || !spec.plotly || !Array.isArray(spec.plotly.data) || !spec.plotly.data.length) {
			throw new Error('The chart specification contains no data traces.');
		}
		if (typeof Plotly === 'undefined') throw new Error('The Plotly library could not be loaded.');

		var plotDiv = document.getElementById('expression_plot');
		Plotly.newPlot(plotDiv, spec.plotly.data, spec.plotly.layout || {}, spec.plotly.config || { responsive: true });

		var providerLabel = provider + (model ? ' / ' + model : '');
		document.getElementById('chatbot_trace_meta').setAttribute('data-result-status', 'Plotly chart rendered from ' + rowCount + ' raw values');
		report('Results: chart rendered from ' + rowCount + ' raw expression values (' + providerLabel + ').');
	} catch (error) {
		var status = document.getElementById('expression_status');
		status.className += ' error';
		status.textContent = 'Plot rendering failed: ' + String(error.message || error);
		document.getElementById('expression_plot').textContent = 'Unable to render the expression plot. See the status above.';
		document.getElementById('chatbot_trace_meta').setAttribute('data-result-status', 'plot rendering failed: ' + String(error.message || error));
		report('Results: plot rendering failed - ' + String(error.message || error));
		if (window.console) console.error(error);
	}
})();
</script>
