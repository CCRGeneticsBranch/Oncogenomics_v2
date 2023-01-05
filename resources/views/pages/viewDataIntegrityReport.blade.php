@extends('layouts.default')
@section('content')

{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::style('css/style.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-core-css.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-blue/sm-blue.css') }}    
{{ HTML::script('js/jquery-3.6.0.min.js') }}
{{ HTML::script('packages/smartmenus-1.0.0-beta1/jquery.smartmenus.min.js') }}

{{ HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') }}
{{ HTML::style('packages/DataTables-1.10.8/media/css/jquery.dataTables.min.css') }}
{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}

{{ HTML::script('packages/DataTables-1.10.8/media/js/jquery.dataTables.min.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/dataTables.buttons.min.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.flash.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.html5.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.print.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.colVis.js') }}
{{ HTML::script('packages/DataTables-1.10.8/extensions/ColReorder/js/dataTables.colReorder.min.js') }}
{{ HTML::script('packages/DataTables-1.10.8/extensions/FixedColumns/js/dataTables.fixedColumns.min.js') }}
{{ HTML::script('packages/yadcf-0.8.8/jquery.dataTables.yadcf.js')}}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') }}
{{ HTML::script('js/onco.js') }}

<style>

html, body { height:100%; width:100%;}

th {
    white-space: nowrap;
}

.btn-default.active {
    background-color: DarkCyan;
    border-color: #000000;
    color: #fff;
}

</style>
<script type="text/javascript">
	var tbls = [];
	$(document).ready(function() {
		var summary = {{$summary}};
		summary.data.forEach(function(d,i) {
			var id = d[0].replaceAll(" ","_").toLowerCase();
			summary.data[i][0] = "<a href='#" + id + "'>" + d[0] + "</a>";
		});
		showTable(summary, 'Summary');
		@foreach ($detail_tables as $name => $table)
			console.log('{{$name}}');
			var data = {{$table}};
			showTable(data, '{{$name}}');
		@endforeach

		$('.reportTable').on( 'draw.dt', function () {
    		var tbl_id = $(this).attr('id');
    		var id = tbl_id.replace('tbl', '');
    		var t = tbls[id];
    		console.log(id);
    		if (t == null)
    			return;
    		console.log(tbl_id);
			$('#lblCountDisplay' + id).text(t.page.info().recordsDisplay);
    		$('#lblCountTotal' + id).text(t.page.info().recordsTotal);
    	});
    	
    	$('.btn-download').on('click', function() {
    		var btn_id = $(this).attr('id');
    		var id = btn_id.replace('btnDownload', '');
			var url = '{{url('/downloadDataIntegrityReport')}}' + '/' + id + '/' + '{{$target}}';
			window.location.replace(url);
		});

		$('#selTarget').on('change', function() {
			var target = $('#selTarget').val();
			var url = '{{url('/viewDataIntegrityReport')}}' + '/' + target;
			window.location.replace(url);
		});
	});

	function showTable(data, id) {
		cols = data.cols;		
		tbl = $('#tbl' + id).DataTable( 
		{
				"data": data.data,
				"columns": data.cols,
				"ordering":    true,
				"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
				"pageLength":  15,			
				"processing" : true,			
				"pagingType":  "simple_numbers",			
				"dom": 'lfrtip'
		} );
		tbls[id] = tbl;

		$('#lblCountDisplay' + id).text(tbl.page.info().recordsDisplay);
    	$('#lblCountTotal' + id).text(tbl.page.info().recordsTotal);    	
	}		

        

	
</script>

	<div style="margin:10px">				
			Target: 
			<select class="form-control" id="selTarget" style="width:200px;display:inline">
					<option value="Khanlab" {{($target=="Khanlab")?"selected":""}}>Khanlab</option>
					<option value="COMPASS" {{($target=="COMPASS")?"selected":""}}>COMPASS</option>
			</select>
			<H2> Summary </H2>
			<div style="width:50%">
			<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblSummary" style='white-space: nowrap;width:100%;'>
			</table>
			</div>
			<H3>Explanation:</H3>
			<div style="border:1;margin:20px">
			  <H4 style="color:red">Cases on Biowulf only</H4>
			  <b>Description:</b> Cases found on Biowulf only<br>
			  <b>Possible reasons:</b> 1. Old cases 2. The sync was failed<br>
			  <b>Actions:</b> 1. Delete the cases on Biowulf 2. Re-sync the cases<br>
			  <H4  style="color:red">Cases on Frederick only</H4>
			  <b>Description:</b> Cases found on Frederick only<br>
			  <b>Possible reasons:</b> Failed cases on Biowulf<br>
			  <b>Actions:</b> Delete the cases on Frederick<br>
			@if ($target == "Khanlab") 			  
			  <H4 style="color:red">Case content inconsistency</H4>
			  <b>Description:</b> Samples processed does not match samples defined in master file<br>
			  <b>Possible reasons:</b> Case definition was changed in master file<br>
			  <b>Actions:</b> Check master file or reprocess the cases<br>
			  <H4 style="color:red">Case name inconsistency</H4>
			  <b>Description:</b> Case name is not the same as case ID (folder name)<br>
			  <b>Possible reasons:</b> Case name was changed in master file or cases were not processed properly<br>
			  <b>Actions:</b> 1. Rename the folder 2. Check master file 3. reprocess the cases<br>
			@endif
			  <H4 style="color:red">Missing BAMs</H4>
			  <b>Description:</b> BAM files not found<br>
			  <b>Possible reasons:</b> 1. Pipeline was not finished properly 2. syncing was failed<br>
			  <b>Actions:</b> 1. Check the BAM files on Biowulf 2. Remake squeeze bam and touch the successful.txt<br>
			  <H4 style="color:red">Missing RSEMs</H4>
			  <b>Description:</b> RSEM files not found<br>
			  <b>Possible reasons:</b> Cases too old<br>
			  <b>Actions:</b> Reprocess the cases<br>
              <H4 style="color:red">Sample inconsistency</H4>
              <b>Description:</b> Sample ID/name in processed data is different from master file<br>
              <b>Possible reasons:</b> Flowcell ID has been added/changed after cases were uploaded<br>
              <b>Actions:</b> Reprocess the cases<br>
			  <H4 style="color:red">No successful cases</H4>
			  <b>Description:</b> Cases has no successful.txt<br>
			  <b>Possible reasons:</b> Old Cases<br>
			  <b>Actions:</b> 1. check if cases should be deleted 2. Touch the successful.txt<br>
			  <H4 style="color:red">Unprocessed cases</H4>
			  <b>Description:</b> Cases defined in master file but not processed<br>
			  <b>Possible reasons:</b> 1. Cases to be processed 2. Cases failed 3. Forgotten cases 4. Not main pipeline cases<br>
			  <b>Actions:</b> 1. check if cases are failed 2. Reprocess the cases<br>
			  <H4 style="color:red">Unused cases</H4>
			  <b>Description:</b> Cases were processed but could not find the match in master file<br>
			  <b>Possible reasons:</b> Case definition was changed in master file<br>
			  <b>Actions:</b> Check if cases should be deleted<br>
			</div>
			<hr>
			<H2> Details </H2>
			@foreach ($detail_tables as $name => $table)
			<H3 id={{strtolower($name)}}>{{ucfirst(str_replace("_", " ", $name))}}</H3>
			<div style="width:70%">
				<button id="btnDownload{{$name}}" class="btn btn-info btn-download"><img width=15 height=15 src={{url("images/download.svg")}}></img>&nbsp;Download</button>
				<span style="font-family: monospace; font-size: 20;float:right;">				
				Total: <span id="lblCountDisplay{{$name}}" style="text-align:left;color:red;" text=""></span>/<span id="lblCountTotal{{$name}}" style="text-align:left;" text=""></span></span>			
				<table cellpadding="0" cellspacing="0" border="0" class="pretty reportTable" word-wrap="break-word" id="tbl{{$name}}" style='white-space: nowrap;width:100%;'>
				</table>
			</div>
			<hr>
			@endforeach
	</div>		
		
	

@stop
