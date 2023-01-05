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
	var tbl;	
	var col_html = '';
	var columns = [];
	var project_list = [{"id":"any", "text" : "(ANY)"}];
	var project_id = '{{$project_id}}';
	var current_mode = 0; //0:insert, 1:edit, 2:delete
	$(document).ready(function() {
		@foreach ($projects as $project)
			project_list.push({"id": '{{$project->id}}', "text": "{{$project->name}}"});
		@endforeach

		$('#selProjectList').combobox({
		        panelHeight: '400px',
		        selectOnNavigation: false,
		        valueField: 'id',
		        textField: 'text',
		        editable: true,
		        filter: function (q, row) {
		        	var opts = $(this).combobox('options');
					return row[opts.textField].toUpperCase().indexOf(q.toUpperCase()) >= 0;
		        },
		        onSelect: function(d) {		        	
		        	project_id = d.id;
		        	window.location = '{{url("/viewCases/")}}' + '/' + project_id;
		        },
		        data: project_list
		});

		$('#ckPending').on('change', function() {
			doFilter();
		});

		$('#selProjectList').combobox('setValue', '{{$project_id}}');
		
		getData();

		$.fn.dataTableExt.afnFiltering.push( function( oSettings, aData, iDataIndex ) {
			var status_idx = 6;
			if ($('#ckPending').is(":checked"))
				return (aData[6] == "pending");
			return true;
		});
			
	});	

	function doFilter() {
		tbl.draw();
	}

	function getData() {
		$("#loadingMaster").css("display","block");
		$('#onco_layout').css('visibility', 'hidden');
		var url = '{{url("/getCases")}}' + '/' + project_id;
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

		//hide_cols = data.hide_cols;
		hide_cols = [];
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

		var html = '';
		$("div.toolbar").html(html + '<button id="popover" data-toggle="popover" data-placement="bottom" type="button" class="btn btn-default" style="font-size: 12px;">Select Columns</button>');
		tbl.columns().iterator('column', function ( context, index ) {
				var show = (hide_cols.indexOf(index) == -1);
				tbl.column(index).visible(show);
				columns.push(tbl.column(index).header().innerHTML);
				checked = (show)? 'checked' : '';
				col_html += '<input type=checkbox ' + checked + ' class="onco_checkbox" id="data_column" value=' + index + '><font size=3>&nbsp;' + tbl.column(index).header().innerHTML + '</font></input><BR>';
			});
		

		$('[data-toggle="popover"]').popover({
				title: 'Select column <a href="#" class="close" data-dismiss="alert">×</a>',
				placement : 'bottom',  
				html : true,
				content : function() {
					return col_html;
				}
			});

		
	}		

        

	
</script>

<div class="easyui-panel" style="padding:0px;">
	<div id='loadingMaster' style="height:90%">
    		<img src='{{url('/images/ajax-loader.gif')}}'></img>
	</div>	
	<div id="onco_layout" class="easyui-layout" data-options="fit:true" style="height:100%;visibility:hidden">		
		<div data-options="region:'center',split:true" style="width:100%;padding:10px;overflow:none;" >
			<div style="margin:10px 0">				
				Projects: 
				<input class="easyui-combobox" id="selProjectList" name="selProjectList" />				
				<span class="btn-group" id="interchr" data-toggle="buttons">
			  		<label class="mut btn btn-default">
							<input class="ck" id="ckPending" type="checkbox" autocomplete="off">Pending cases
					</label>
				</span>
				<span style="font-family: monospace; font-size: 20;float:right;">					
				Cases: <span id="lblCountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="lblCountTotal" style="text-align:left;" text=""></span>
			</div>
			<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblOnco" style='white-space: nowrap;width:100%;'>
			</table> 			
		</div>		
	</div>
</div>

@stop
