<style>
	.error-panel { font-family: Verdana, sans-serif; max-width: 900px; margin: 16px auto; padding: 0 14px; }
	.error-panel h3 { margin: 0 0 10px; }
	.error-code-box { border: 1px solid #d9a5a5; background: #fff1f1; color: #8b2525; padding: 12px 14px; border-radius: 4px; margin-bottom: 12px; font-size: 13px; line-height: 1.6; }
	.error-code-box .code-label { font-weight: bold; text-transform: uppercase; letter-spacing: .5px; font-size: 11px; color: #a33; }
	.error-code-box .code-value { font-family: Consolas, monospace; font-size: 15px; font-weight: bold; }
	.error-conn { border-color: #d6a95a; background: #fff8ec; color: #7a521a; }
	.error-conn .code-label { color: #a6741f; }
	.error-back a { color: #287271; font-weight: 600; }
</style>

<div class="error-panel">
	@if(!empty($error_code))
		<div class="error-code-box {{ !empty($is_connection_issue) ? 'error-conn' : '' }}">
			<div><span class="code-label">Error code:</span> <span class="code-value">{{ $error_code }}</span></div>
			@if(!empty($is_connection_issue))
				<div style="margin-top:6px;">&#9888; This looks like a <strong>connection / provider availability issue</strong> (network, API key, rate limit, or timeout) rather than a data problem. Please try again in a moment.</div>
			@else
				<div style="margin-top:6px;">This is a <strong>content / validation issue</strong> (the model produced output that did not meet the chart requirements), not a connection problem.</div>
			@endif
			@if(!empty($tried_providers))
				<div style="margin-top:6px;"><span class="code-label">Providers tried:</span> {{ implode(', ', (array)$tried_providers) }}</div>
			@endif
		</div>
	@endif

	<h3>{{ $message }}</h3>
	<h3 class="error-back"><a href="javascript:history.back()">Go Back</a></h3>
</div>

@if(!empty($console_error))
<script type="text/javascript">
(function () {
	var details = {!! json_encode($console_error, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
	var isConnection = {!! !empty($is_connection_issue) ? 'true' : 'false' !!};
	if (window.console) {
		console.error('Expression plot generation failed [' + (details.code || 'unknown') + ']:', details);
		if (isConnection) {
			console.warn('This appears to be a connection/provider issue. Providers tried:', details.tried_providers || []);
		}
	}
	if (window.parent && window.parent !== window) {
		window.parent.postMessage({
			type: 'chatbot-status',
			message: 'Results: plot generation failed [' + (details.code || 'unknown') + '] - ' + (details.message || 'unknown error')
		}, window.location.origin);
	}
})();
</script>
@endif
