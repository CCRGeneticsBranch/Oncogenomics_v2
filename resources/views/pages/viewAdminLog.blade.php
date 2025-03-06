@extends('layouts.default')
@section('content')
@section('title', "AdminLog")

{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::style('css/style.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-core-css.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-blue/sm-blue.css') }}   
{!! HTML::style('css/font-awesome.min.css') !!} 
{{ HTML::script('js/jquery-3.6.0.min.js') }}
{{ HTML::script('packages/smartmenus-1.0.0-beta1/jquery.smartmenus.min.js') }}

{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}

{!! HTML::script('packages/DataTables/datatables.min.js') !!}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') }}
{{ HTML::script('js/onco.js') }}

<style>

html, body { height:100%; width:100%;}

th {
    white-space: nowrap;
}

.btn-default:focus,
.btn-default:active,
.btn-default.active {
    background-color: DarkCyan;
    border-color: #000000;
    color: #fff;
}
.btn-default.active:hover {
    background-color: #005858;
    border-color: gray;
    color: #fff;    
}

</style>
<script type="text/javascript">
	var tbl;	
	var col_html = '';
	var columns = [];
	$(document).ready(function() {	
		getData();			
	});	

	
	function getData() {
		$("#loadingMaster").css("display","block");
		$('#onco_layout').css('visibility', 'hidden');
		var url = '{{url("/getAdminLog")}}';
		console.log(url);
       	$.ajax({ url: url, async: true, dataType: 'text', success: function(json_data) {
				$("#loadingMaster").css("display","none");
				$('#onco_layout').css('visibility', 'visible');
				data = JSON.parse(json_data);
				if (data.data.length == 0) {
					alert('no data!');
					return;
				}
				showTable(data);
			}
		});
	}

	function showTable(data) {
		cols = data.cols;
		tbl = $('#tblOnco').DataTable( 
		{
				"data": data.data,
				"columns": cols,
				"ordering":    true,
				"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
				"pageLength":  15,			
				"processing" : true,			
				"pagingType":  "simple_numbers",			
				"dom": 'lfrtip'
		} );

		$('#lblCountDisplay').text(tbl.page.info().recordsDisplay);
    	$('#lblCountTotal').text(tbl.page.info().recordsTotal);

		$('#tblOnco').on( 'draw.dt', function () {
			$('#lblCountDisplay').text(tbl.page.info().recordsDisplay);
    		$('#lblCountTotal').text(tbl.page.info().recordsTotal);
    	});
	}		

        

	
</script>

<div class="easyui-panel" style="padding:10px;">
	<div id='loadingMaster' style="height:90%">
    		<img src='{{url('/images/ajax-loader.gif')}}'></img>
	</div>	
	<div id="onco_layout" class="easyui-layout" data-options="fit:true" style="width:80%;visibility:hidden">		
		<div data-options="region:'center',split:true" style="padding:10px;" >
			<div style="margin:10px;width:95%">				
				<span style="font-family: monospace; font-size: 20;float:right;">					
				Events: <span id="lblCountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="lblCountTotal" style="text-align:left;" text=""></span>
			</div>
			<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblOnco" style='white-space: nowrap;width:98%;'>
			</table> 			
		</div>		
	</div>
</div>

@stop
