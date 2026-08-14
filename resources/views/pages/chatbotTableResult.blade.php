{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::style('packages/DataTables/datatables.min.css') }}
{{ HTML::script('js/jquery-3.6.0.min.js') }}
{!! HTML::script('packages/DataTables/datatables.min.js') !!}

<style>
	* { box-sizing: border-box; }
	html, body { width: 100%; min-height: 100%; margin: 0; padding: 0; }
	body { background: #f5f6f6; color: #252525; font-family: Verdana, sans-serif; }
	.table-result { width: 100%; padding: 14px; }
	.table-header { width: 100%; padding: 10px 14px; margin-bottom: 10px; border-left: 4px solid #287271; background: #fff; box-shadow: 0 1px 3px rgba(0, 0, 0, .12); }
	.table-header h3 { margin: 0 0 7px; font-size: 18px; }
	.table-meta { color: #5b5b5b; font-size: 12px; }
	.table-meta span { display: inline-block; margin: 0 14px 3px 0; }
	.table-status { margin-bottom: 10px; padding: 9px 14px; border: 1px solid #b8d7d4; background: #eef7f6; font-size: 12px; line-height: 1.5; }
	.table-status.error { border-color: #d9a5a5; background: #fff1f1; color: #8b2525; }
	.table-panel { width: 100%; padding: 12px; overflow-x: auto; background: #fff; }
	#chatbot_table { width: 100% !important; font-size: 12px; }
	.dataTables_wrapper { width: 100%; font-size: 12px; }
</style>

<div class="table-result">
	<div id="chatbot_trace_meta" style="display:none"
		data-trace-mode="{{ $trace_mode ?? '' }}"
		data-trace-provider="{{ $trace_provider ?? '' }}"
		data-trace-model="{{ $trace_model ?? '' }}"></div>

	<div class="table-header">
		<h3>{{ $title ?? 'Results' }}</h3>
		<div class="table-meta">
			@if(!empty($project_name))
				<span><strong>Project:</strong> {{ $project_name }}</span>
			@endif
			@if(!empty($summary))
				<span>{{ $summary }}</span>
			@endif
		</div>
	</div>

	<div id="table_status" class="table-status">Loading table...</div>
	<div class="table-panel">
		<table id="chatbot_table" cellpadding="0" cellspacing="0" border="0"></table>
	</div>
</div>

<script type="text/javascript">
(function () {
	var rawTableJson = {!! json_encode($table_json ?? '{}', JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
	var rawTableOrder = {!! json_encode($table_order ?? null, JSON_UNESCAPED_SLASHES) !!};
	var status = document.getElementById('table_status');

	function report(message) {
		if (window.parent && window.parent !== window) {
			window.parent.postMessage({ type: 'chatbot-status', message: message }, window.location.origin);
		}
	}

	function parsePayload(value) {
		var parsed = value;
		for (var depth = 0; depth < 3 && typeof parsed === 'string'; depth++) {
			parsed = JSON.parse(parsed);
		}
		return parsed;
	}

	function normalizeTable(payload) {
		if (payload && payload.table_json !== undefined) payload = parsePayload(payload.table_json);
		if (payload && payload.table !== undefined) payload = parsePayload(payload.table);
		// QC payloads nest the DataTable (cols/data) inside a qc_data element.
		if (payload && payload.qc_data !== undefined) payload = parsePayload(payload.qc_data);

		var rows = Array.isArray(payload) ? payload : (payload.data || payload.rows || []);
		var rawColumns = Array.isArray(payload) ? [] : (payload.columns || payload.cols || []);
		if (!Array.isArray(rows)) rows = [];

		var columns = rawColumns.map(function (column) {
			if (typeof column === 'string') return { title: column };
			return column && typeof column === 'object' ? column : { title: String(column || '') };
		});

		if (!columns.length && rows.length && rows[0] && !Array.isArray(rows[0]) && typeof rows[0] === 'object') {
			columns = Object.keys(rows[0]).map(function (key) {
				return { title: key, data: key };
			});
		}
		if (!columns.length && rows.length && Array.isArray(rows[0])) {
			columns = rows[0].map(function (_, index) {
				return { title: 'Column ' + (index + 1) };
			});
		}

		return { columns: columns, data: rows };
	}

	try {
		if (!window.jQuery || !$.fn.DataTable) throw new Error('jQuery DataTables could not be loaded.');
		var table = normalizeTable(parsePayload(rawTableJson));

		if (!table.data.length) {
			status.textContent = 'No data available for this query.';
			document.getElementById('chatbot_trace_meta').setAttribute('data-result-status', 'no data');
			report('Results: no data available for this query.');
			return;
		}

		if (!table.columns.length) throw new Error('The table response contains no columns.');

		var dtOptions = {
			data: table.data,
			columns: table.columns,
			ordering: true,
			pageLength: 15,
			lengthMenu: [[15, 25, 50, -1], [15, 25, 50, 'All']],
			pagingType: 'simple_numbers',
			autoWidth: false,
			deferRender: true
		};

		// Optional initial ordering supplied by the server as [[columnIndex, 'asc'|'desc'], ...].
		// Only valid pairs within the rendered column range are applied.
		if (Array.isArray(rawTableOrder) && rawTableOrder.length) {
			var safeOrder = rawTableOrder.filter(function (o) {
				return Array.isArray(o) && o.length === 2 &&
					typeof o[0] === 'number' && o[0] >= 0 && o[0] < table.columns.length &&
					(String(o[1]).toLowerCase() === 'asc' || String(o[1]).toLowerCase() === 'desc');
			});
			if (safeOrder.length) dtOptions.order = safeOrder;
		}

		$('#chatbot_table').DataTable(dtOptions);

		status.textContent = table.data.length + ' row' + (table.data.length === 1 ? '' : 's') + ' loaded.';
		document.getElementById('chatbot_trace_meta').setAttribute('data-result-status', 'table rendered with ' + table.data.length + ' rows');
		report('Results: table rendered with ' + table.data.length + ' rows.');
	} catch (error) {
		status.className += ' error';
		status.textContent = 'Table rendering failed: ' + String(error.message || error);
		report('Results: table rendering failed - ' + String(error.message || error));
		if (window.console) console.error(error);
	}
})();
</script>