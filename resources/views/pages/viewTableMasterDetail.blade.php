@extends('layouts.default')
@section('content')

{{ HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') }}
{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}

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
    
<script type="text/javascript">
	var tbl;	
	var cols = {{json_encode($cols)}};
	var detail_cols = {{json_encode($detail_cols)}};
	var hide_cols = {{json_encode($col_hide)}};
	var primary_key = '{{$primary_key}}';
	var expanded = false;
	var col_html = '';
	var columns = [];
	$(document).ready(function() {
		tbl = $('#tblOnco').DataTable( 
		{
			"ajax": '{{$url}}',
			"columns": cols,
			"ordering":    true,
			"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
			"pageLength":  15,			
			"processing" : true,			
			"pagingType":  "simple_numbers",			
			"dom": 'B<"toolbar">lfrtip',
			"buttons": ['csv', 'excel']
		} );


		$("div.toolbar").html('<button id="popover" data-toggle="popover" title="Select Column" data-placement="bottom" type="button" class="btn btn-default" style="font-size: 12px;">Select Columns</button>');
		tbl.columns().iterator('column', function ( context, index ) {
			var show = (hide_cols.indexOf(index) == -1);
			tbl.column(index).visible(show);
			columns.push(tbl.column(index).header().innerHTML);
			checked = (show)? 'checked' : '';
			col_html += '<input type=checkbox ' + checked + ' class="onco_checkbox" id="data_column" value=' + index + '><font size=3>&nbsp;' + tbl.column(index).header().innerHTML + '</font></input><BR>';
		} );

		yadcf.init(tbl, [
			@foreach ($filters as $filter)
				{column_number : {{$filter}}},
			@endforeach
		]);
        

		$('[data-toggle="popover"]').popover({
			placement : 'bottom',  
			html : true,
			content : function() {
				return col_html;
			}
		}); 

		$('body').on('change', 'input#data_column', function() {             
			col_html = '';
			for (i = 0; i < columns.length; i++) { 
				if (i == $(this).attr("value"))
					checked = ($(this).is(":checked"))?'checked' : '';
				else
					checked = (tbl.column(i).visible())?'checked' : '';
				col_html += '<input type=checkbox ' + checked + ' class="onco_checkbox" id="data_column" value=' + i + '><font size=3>&nbsp;' + columns[i] + '</font></input><BR>';
			}
			tbl.column($(this).attr("value")).visible($(this).is(":checked"));
			
		});

		$('#tblOnco').on( 'xhr.dt', function () {			
			$('#loadingMaster').css('display', 'none');
			$('#onco_layout').css('visibility', 'visible');
		} );

		tblDetail = $('#tblDetail').DataTable( 
		{
			"processing" : true,
			"language" : {
				"processing": "<img src='{{url('/images/ajax-loader.gif')}}'></img>"
			},
			"paging":   false,
			"ordering": true,
			"info":     false,
			"columns": detail_cols,
		} );

		if (primary_key != 'all') {
			getDetails(primary_key);
		}


	});

	$(document).ajaxStart(function(){
		$("#loadingDiv").css("display","block");
        });

        $(document).ajaxComplete(function(){
		$("#loadingDiv").css("display","none");
        });

	function getDetails(key) {
		if (!expanded) {
			$("#onco_layout").layout('expand','{{$detail_pos}}');
			expanded = true;
		}
		var url = '{{$detail_url}}' + '/' + key;
		tblDetail.ajax.url(url).load();		
		$("#onco_layout").layout('panel', '{{$detail_pos}}').panel('setTitle', 'Details - ' + key);
	}

</script>
<div class="easyui-panel" style="height:100%;padding:0px;">
	<div id='loadingMaster'>
    		<img src='{{url('/images/ajax-loader.gif')}}'></img>
	</div>
	<div id="onco_layout" class="easyui-layout" data-options="fit:true" style="visibility:hidden">
@if ($detail_pos == 'east') {
		<div data-options="region:'center',split:true" style="width:70%;height:100%;padding:10px;overflow:none;" title="{{$title}}">
@else
		<div data-options="region:'center',split:true" style="width:100%;height:70%;padding:10px;overflow:none;" title="{{$title}}">
@endif
		
			<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblOnco" style='width:100%;'>
			</table> 
			<div id='loadingDiv'>
    				<img src='{{url('/images/ajax-loader.gif')}}'></img>
			</div>

		</div>
@if ($detail_pos == 'east') {
		<div id="panelDetail" class="easyui-panel" data-options="region:'east',split:true,collapsed:true" style="width:30%;height:100%;padding:10px;overflow:auto;" title="{{$detail_title}}">
@else
		<div id="panelDetail" class="easyui-panel" data-options="region:'south',split:true,collapsed:true" style="width:100%;height:35%;padding:10px;overflow:auto;" title="{{$detail_title}}">
@endif
			<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblDetail" style='width:100%'>
			</table>
		</div>
	</div>
</div>
@stop
