@extends('layouts.default')
@section('content')

{{ HTML::style('packages/d3/d3.css') }}

{{ HTML::script('packages/d3/d3.min.js') }}
{{ HTML::script('packages/gene_fusion/gene-fusion.js') }}

<style>
.bar--positive {
  fill: steelblue;
}

.bar--negative {
  fill: darkorange;
}

.axis text {
  font: 10px sans-serif;
}

.axis path,
.axis line {
  fill: none;
  stroke: #000;
  shape-rendering: crispEdges;
}
</style>

<script type="text/javascript">
	
	var hide_cols = [];
	var options = [];
	var tbls = [];
	var state = 'collapse';

	$(document).ready(function() {		

		//plot_fus();
		drawGeneFusionPlot();


	});
	

	function showTable(data, tblId) {		
		var tbl = $('#' + tblId).DataTable( 
		{
			"data": data.data,
			"columns": data.column,
			"ordering":    true,
			"deferRender": true,
			"lengthMenu": [[15, 25, 50], [15, 25, 50]],
			"pageLength":  15,
			"pagingType":  "simple_numbers",			
			"dom": '<"toolbar">lfrtip'			
		} );
		tbls[tblId] = tbl;		
		
	}

	function showPatientTable(data) {		
		hide_cols = data.hide_cols;
		var tbl = $('#tblPatient').DataTable( 
		{
			"data": data.data,
			"columns": data.column,
			"paging":   false,
			"ordering": false,
			"info":     false,
			"dom": ''
		} );
		
		tbl.columns().iterator('column', function ( context, index ) {
				var show = (hide_cols.indexOf(index) == -1);
				tbl.column(index).visible(show);		
		} );

		//do not show patient_id
		tbl.column(3).visible(false);
		
	}
		

	function drawGeneFusionPlot(plotConfig) {
		// Instantiate a plot
		genes = {"gene1":{"name":"gene1","chr":"chr13","strand":"+","color":"green","junction":22111828,"exons":[[22111366,22111427],[22111480,22111636],[22111728,22111977],[22112216,22118383]]},
				 "gene2":{"name":"gene2","chr":"chr8","strand":"-","color":"orange","junction":22221808,"exons":[[22221066,22221427],[22221480,22221536],[22221700,22221977],[22222116,22222683],[22223116,22223983]]}};		
		
		//Crate config Object
		plotConfig = {
			height:         350,
			targetElement : "fus_plot",
			cytobandFile : '{{url('/packages/gene_fusion/data/cytoband.tsv')}}',
			genes:     genes			
		};

		fusionPlot = new GeneFusionPlot(plotConfig);				
	}





</script>
<div id="out_container" class="easyui-panel" style="width:100%;height:100%"padding:10px;">  	
	<div id="fus_plot" style="border: 1px solid #ccc;""></div>
</div>



@stop
