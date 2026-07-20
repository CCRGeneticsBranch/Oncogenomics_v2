@section('title', "Correlation")
{{ HTML::style('packages/heatmap/heatmap.css') }}
{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::style('packages/tooltipster-master/dist/css/tooltipster.bundle.min.css') }}
{{ HTML::style('packages/w2ui/w2ui-1.4.min.css')}}
{{ HTML::style('packages/d3/d3.tip.css') }}
{{ HTML::style('packages/jquery-easyui/themes/bootstrap/easyui.css') }}
{{ HTML::style('packages/bootstrap-4.6.1-dist/css/bootstrap.min.css') }}


{{ HTML::script('js/jquery-3.6.0.min.js') }}
{!! HTML::script('packages/DataTables/datatables.min.js') !!}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('packages/tooltipster-master/dist/js/tooltipster.bundle.min.js') }}
{{ HTML::script('js/onco.js') }}
{{ HTML::script('packages/w2ui/w2ui-1.4.min.js')}}
{{ HTML::script('packages/highchart/js/highcharts.js')}}
{{ HTML::script('packages/highchart/js/highcharts-more.js')}}
{{ HTML::script('packages/d3/d3.min.js') }}
{{ HTML::script('packages/d3/d3.tip.js') }}
{{ HTML::script('js/rainbowvis.js') }}
{{ HTML::script('packages/heatmap/heatmap.js') }}

<style>
	.correlation-container {
		max-width: 1200px;
		margin: 0 auto;
	}
	.correlation-header {
		background-color: #fff;
		padding: 15px;
		border-radius: 4px;
		margin-bottom: 15px;
		border-left: 4px solid #007bff;
		box-shadow: 0 1px 3px rgba(0,0,0,0.1);
	}
	.correlation-header h3 {
		margin: 0 0 8px 0;
		color: #333;
		font-size: 18px;
	}
	.correlation-header .meta-info {
		font-size: 12px;
		color: #666;
		margin: 3px 0;
	}
	.correlation-header .badge {
		display: inline-block;
		padding: 3px 6px;
		margin-right: 6px;
		background-color: #e7f3ff;
		color: #0066cc;
		border-radius: 3px;
		font-size: 11px;
	}
	.row {
		display: flex;
		gap: 20px;
		margin-top: 15px;
		flex-wrap: wrap;
	}
	.column {
		flex: 1;
		min-width: 450px;
		background-color: #fff;
		padding: 12px;
		border-radius: 4px;
		box-shadow: 0 1px 3px rgba(0,0,0,0.1);
	}
	.column h4 {
		margin: 0 0 10px 0;
		color: #333;
		font-size: 14px;
		border-bottom: 2px solid #007bff;
		padding-bottom: 5px;
	}
	#tblCorrelation {
		width: 100%;
		border-collapse: collapse;
		font-size: 12px;
	}
	#tblCorrelation thead {
		background-color: #f0f0f0;
		font-weight: bold;
	}
	#tblCorrelation th {
		padding: 8px;
		text-align: left;
		border-bottom: 2px solid #ddd;
	}
	#tblCorrelation td {
		padding: 8px;
		border-bottom: 1px solid #eee;
	}
	#tblCorrelation tbody tr:hover {
		background-color: #f5f5f5;
	}
	#tblCorrelation a {
		color: #007bff;
		text-decoration: none;
		cursor: pointer;
	}
	#tblCorrelation a:hover {
		text-decoration: underline;
	}
	.controls {
		margin-bottom: 10px;
		display: flex;
		gap: 8px;
		align-items: center;
		flex-wrap: wrap;
	}
	.controls label {
		font-weight: 600;
		margin: 0;
		font-size: 12px;
	}
	.controls select {
		padding: 5px 8px;
		border: 1px solid #ddd;
		border-radius: 3px;
		font-size: 12px;
	}
	#corr_plot {
		width: 100%;
		height: 100%;
		aspect-ratio: 1 / 1;
		min-height: 400px;
	}
	.no-data-message {
		padding: 20px;
		text-align: center;
		color: #666;
		font-size: 14px;
	}
	.dataTables_wrapper {
		font-size: 12px;
	}
	.dataTables_paginate {
		margin-top: 10px;
	}
</style>

<div class="correlation-container">
	<div id="chatbot_trace_meta" style="display:none"
		data-trace-mode="{{ $trace_mode ?? '' }}"
		data-trace-provider="{{ $trace_provider ?? '' }}"
		data-trace-model="{{ $trace_model ?? '' }}"></div>
	<div class="correlation-header">
		<h3>Gene Correlation Analysis</h3>
		<div class="meta-info">
			<strong>Query Gene:</strong> <span class="badge">{{ $gene }}</span>
			<strong>Project:</strong> <span class="badge">{{ $project->name }}</span>
			<strong>Method:</strong> <span class="badge">{{ ucfirst($method) }}</span>
		</div>
	</div>

	@if(count($correlation_data) > 0)
		<div class="row">
			<div class="column" style="flex: 0 0 calc(50% - 10px);">
				<h4>Correlated Genes</h4>
				<div class="controls">
					<label for="selCorFilter">Filter:</label>
					<select id="selCorFilter">
						<option value="all">All</option>
						<option value="positive">Positive</option>
						<option value="negative">Negative</option>
					</select>
				</div>
				<table cellpadding="0" cellspacing="0" border="0" id="tblCorrelation"></table>
			</div>
			<div class="column" style="flex: 0 0 calc(50% - 10px);">
				<h4>Scatter Plot</h4>
				<div class="controls">
					<label for="selCorGenomeVersion">Genome:</label>
					<select id="selCorGenomeVersion">
						@foreach($genome_versions as $genome)
							<option value="{{ $genome }}" {{ $loop->first ? 'selected' : '' }}>{{ $genome }}</option>
						@endforeach
					</select>
					<label for="selCorNorm">Norm:</label>
					<select id="selCorNorm">
						<option value="tmm-rpkm" selected>TMM-RPKM</option>
						<option value="tpm">TPM</option>
					</select>
				</div>
				<div id="corr_plot" style="width:500px;height:500px;"></div>
				<div id="plot_info" style="font-size:11px;color:#666;margin-top:5px;"></div>
			</div>
		</div>
	@else
		<div class="no-data-message">
			<p>No correlation data available for gene <strong>{{ $gene }}</strong>.</p>
		</div>
	@endif
</div>

<script type="text/javascript">
	var project_id = {{ $project->id }};
	var query_gene = "{{ $gene }}";
	var corr_gene1 = query_gene;
	var corr_gene2 = null;
	var corr_data = null;
	var corr_plot = null;

	$(document).ready(function() {
		var rawData = {!! json_encode($correlation_data) !!};
		
		console.log('Correlation data:', rawData);
		if (rawData.length === 0) {
			return;
		}

		var columns = [
			{ title: "Symbol" },
			{ title: "Coefficient" },
			{ title: "Sign" }
		];

		var processedData = [];
		for (var i = 0; i < rawData.length; i++) {
			var row = rawData[i];
			if (row && row.length >= 4) {
				var geneName = String(row[0]).trim();
				if (!geneName) continue; // Skip empty gene names
				var coefficient = parseFloat(row[2]).toFixed(4);
				var sign = String(row[3]).toLowerCase();
				processedData.push([
					"<a href='javascript:selectGeneForPlot(\"" + geneName + "\");' style='cursor:pointer;'>" + geneName + "</a>",
					coefficient,
					sign.charAt(0).toUpperCase() + sign.slice(1)
				]);
			}
		}

		var tblCorr = $('#tblCorrelation').DataTable({
			"data": processedData,
			"columns": columns,
			"ordering": true,
			"order": [[ 1, "desc" ]],
			"lengthMenu": [[10, 15, 25, -1], [10, 15, 25, "All"]],
			"pageLength": 10,
			"pagingType": "simple",
		});

		$.fn.dataTableExt.afnFiltering.push(function(oSettings, aData, iDataIndex) {
			if (oSettings.nTable != document.getElementById('tblCorrelation'))
				return true;
			var sign_idx = 2;
			var filter = $("#selCorFilter").val().toLowerCase();
			if (filter == 'all')
				return true;
			var sign = aData[sign_idx].toLowerCase();
			if (sign == filter)
				return true;
			return false;
		});

		$('#selCorFilter').on('change', function() {
			tblCorr.draw();
		});
	// Handle genome version and normalization dropdown changes
	$('#selCorGenomeVersion').on('change', function() {
		if (corr_gene2) {
			selectGeneForPlot(corr_gene2);
		}
	});

	$('#selCorNorm').on('change', function() {
		if (corr_gene2) {
			selectGeneForPlot(corr_gene2);
		}
	});
		// Select first gene for initial plot
		if (processedData.length > 0) {
			var firstGene = processedData[0][0];
			firstGene = firstGene.replace(/<[^>]*>/g, ''); // Remove HTML tags
			console.log('Auto-loading first gene:', firstGene);
			// Delay to ensure DOM is ready
			setTimeout(function() {
				selectGeneForPlot(firstGene);
			}, 500);
		}
	});

	function selectGeneForPlot(geneName) {
		corr_gene2 = geneName;
		console.log('Loading scatter plot for:', corr_gene1, 'vs', corr_gene2);
		var genome_version = $('#selCorGenomeVersion').val();
		var value_type = $('#selCorNorm').val();
		
		var url = '{!!url("/getTwoGenesDotplotData")!!}' + '/' + project_id + '/' + corr_gene1 + '/' + corr_gene2 + '/' + genome_version + '/' + value_type;
		console.log('URL:', url);
		
		$('#plot_info').html('Loading...');
		$.ajax({
			url: url,
			async: true,
			dataType: 'json',
			success: function(data) {
				try {
					corr_data = data;
					console.log('Scatter data loaded:', corr_data);
					drawScatterPlot();
				} catch (e) {
					console.error('Error processing scatter data:', e);
					$('#plot_info').html('Error loading scatter plot data: ' + e.message);
				}
			},
			error: function(jqXHR, textStatus, errorThrown) {
				console.error('AJAX error:', textStatus, errorThrown, jqXHR.responseText);
				$('#plot_info').html('Error: ' + (textStatus === 'parsererror' ? 'Invalid data format' : textStatus));
			}
		});
	}

	function drawScatterPlot() {
		if (!corr_data || !corr_data.data || !corr_data.pvalue) {
			$('#plot_info').html('Invalid data structure');
			return;
		}

		var title = corr_gene1 + " vs " + corr_gene2;
		var sub_title = "P-value: " + parseFloat(corr_data.pvalue.p_two).toFixed(4) + 
						" (pos: " + parseFloat(corr_data.pvalue.p_great).toFixed(4) + ", " +
						"neg: " + parseFloat(corr_data.pvalue.p_less).toFixed(4) + ")";
		
		var genome_version = $('#selCorGenomeVersion').val();
		var series = [];
		var allPoints = [];

		// Get samples and create data points
		var samples = corr_data.data.samples || [];
		var exp_g1 = corr_data.data.exp_data[corr_gene1] ? corr_data.data.exp_data[corr_gene1][genome_version] : [];
		var exp_g2 = corr_data.data.exp_data[corr_gene2] ? corr_data.data.exp_data[corr_gene2][genome_version] : [];

		if (!Array.isArray(exp_g1) || !Array.isArray(exp_g2)) {
			$('#plot_info').html('Error: Expression data not found');
			return;
		}

		// Create scatter points
		var points = [];
		for (var i = 0; i < Math.min(exp_g1.length, exp_g2.length); i++) {
			var x = parseFloat(exp_g1[i]);
			var y = parseFloat(exp_g2[i]);
			if (!isNaN(x) && !isNaN(y)) {
				var x_log = Math.log2(x + 1);
				var y_log = Math.log2(y + 1);
				var sample_name = samples[i] || 'Sample ' + (i + 1);
				points.push({
					name: sample_name,
					x: x_log,
					y: y_log
				});
				allPoints.push([x_log, y_log]);
			}
		}

		series.push({
			type: 'scatter',
			name: 'Samples',
			data: points,
			turboThreshold: 0
		});

		// Add regression line if we have enough points
		if (allPoints.length > 2) {
			var reg_points = calculateRegression(allPoints);
			if (reg_points.length > 0) {
				series.push({
					type: 'line',
					name: 'Linear Fit',
					data: reg_points,
					color: '#FF0000',
					enableMouseTracking: false
				});
			}
		}

		// Calculate equal axis limits for squared plot
		var minVal = Math.min.apply(null, allPoints.map(function(p) { return Math.min(p[0], p[1]); }));
		var maxVal = Math.max.apply(null, allPoints.map(function(p) { return Math.max(p[0], p[1]); }));

		// Create HighChart with squared axes
		Highcharts.chart('corr_plot', {
			chart: { type: 'scatter', zoomType: 'xy' },
			title: { text: title },
			subtitle: { text: sub_title, style: {fontSize: '12px'} },
			xAxis: { 
				title: { text: corr_gene1 + ' (log2(x+1))' },
				min: minVal,
				max: maxVal
			},
			yAxis: { 
				title: { text: corr_gene2 + ' (log2(x+1))' },
				min: minVal,
				max: maxVal
			},
			tooltip: {
				headerFormat: '<b>{point.name}</b><br>',
				pointFormat: corr_gene1 + ': {point.x:.4f}<br>' + corr_gene2 + ': {point.y:.4f}'
			},
			series: series,
			legend: { enabled: true },
			plotOptions: { scatter: { marker: { radius: 4 } } },
			exporting: { enabled: false },
			credits: { enabled: false }
		});

		$('#plot_info').html('');
	}

	function calculateRegression(points) {
		if (points.length < 2) return [];
		
		var n = points.length;
		var sumX = 0, sumY = 0, sumXY = 0, sumX2 = 0;
		
		for (var i = 0; i < n; i++) {
			var x = points[i][0];
			var y = points[i][1];
			sumX += x;
			sumY += y;
			sumXY += x * y;
			sumX2 += x * x;
		}
		
		var slope = (n * sumXY - sumX * sumY) / (n * sumX2 - sumX * sumX);
		var intercept = (sumY - slope * sumX) / n;
		
		var x_min = Math.min.apply(null, points.map(function(p) { return p[0]; }));
		var x_max = Math.max.apply(null, points.map(function(p) { return p[0]; }));
		
		return [
			{x: x_min, y: slope * x_min + intercept},
			{x: x_max, y: slope * x_max + intercept}
		];
	}
</script>
