@extends('layouts.default')
@section('title', "Projects")
@section('content')

{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') }}
{!! HTML::style('packages/jquery-easyui/themes/bootstrap/easyui.css') !!}
{{ HTML::style('packages/bootstrap-switch-master/dist/css/bootstrap3/bootstrap-switch.css') }}
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}
{{ HTML::style('packages/w2ui/w2ui-1.4.min.css') }}

{!! HTML::script('packages/DataTables/datatables.min.js') !!}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('js/togglebutton.js') }}
{{ HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') }}
{{ HTML::script('packages/bootstrap-switch-master/dist/js/bootstrap-switch.js') }}
{{ HTML::script('packages/w2ui/w2ui-1.4.min.js')}}
<style>
.btn-group-sm>.btn, .btn-sm {
	padding: 0px 10px;
}
</style>
<script type="text/javascript">
	var tbl;
	var default_cols = [true, true, true, false, false, true, true, true, true];
	$(document).ready(function() {
		$("#loadingMaster").css("display","block");
		$('#onco_layout').css('visibility', 'hidden');
       	$.ajax({ url: '{!!url("/get$type")!!}', async: true, dataType: 'text', success: function(json_data) {
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
	});

	function showTable(data) {		
		hide_cols = data.hide_cols;
       	tbl = $('#tblOnco').DataTable( 
		{
				"data": data.data,
				"columns": data.cols,
				"ordering":    true,
				"deferRender": true,
				"lengthMenu": [[15, 20, 50, -1], [15, 30, 50, "All"]],
				"pageLength":  30,			
				"processing" : true,			
				"pagingType":  "simple_numbers",			
				"dom": 'B<"toolbar">lfrtip',
				"buttons": ['csv', 'excel']
		} );

		$('#lblCountDisplay').text(tbl.page.info().recordsDisplay);
    	$('#lblCountTotal').text(tbl.page.info().recordsTotal);
	}

	function deleteProject(project_id) {			
		w2confirm('<H4>Are you sure you want to delete this project?</H4>')
   			.yes(function () {
				var url = '{{url('/deleteProject')}}' + '/' + project_id;
				console.log(url);
				$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
						var results = parseJSON(data);
						if (results.code == "success") {
							w2alert("<H4>Delete Successful!</H4>", "Info", function () {
								location.reload();
							});
							return;
						}
						if (results.code == "error") {
							w2alert("<H4>Save failed: reason:" + results.desc + "</H4>");
							return;
						}					

					}, error: function(xhr, textStatus, errorThrown){
						w2popup.close();
						w2alert("<H4>Save failed: reason:" + JSON.stringify(xhr) + ' ' + errorThrown + "</H4>");					
					}
				});		
			});
			
	}
</script>

<div class="easyui-panel" style="padding:10px;">
	<div id='loadingMaster' style="height:90%">
    		<img src='{{url('/images/ajax-loader.gif')}}'></img>
	</div>	
	<div id="onco_layout">
		<span style="font-family: monospace; font-size: 20;float:right;">	
				{!!$type!!}: <span id="lblCountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="lblCountTotal" style="text-align:left;" text=""></span>
		</span>	
		<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblOnco" style='width:100%;'>
		</table>
	</div>
</div> 
@stop
