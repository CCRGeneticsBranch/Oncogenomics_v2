
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

.layout-panel {overflow: auto}

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
	var type = '{{$type}}';

	$(document).ready(function() {			

		$('.filter').on('change', function() {
			doFilter();
		});		

		getData();

		$.fn.dataTableExt.afnFiltering.push( function( oSettings, aData, iDataIndex ) {
			var count_idx = (type=="summary")? 6 : 5;
			if ($('#selChain').val() != "all") {
				if (aData[4] != $('#selChain').val())
					return false;
			}
			return parseInt(aData[count_idx]) > parseInt($('#selCount').val());
		});

		$('#btnDownload').on('click', function() {
			@if (isset($patient_id))
				var url = '{{url("/getMixcr")}}' + '/{{$patient_id}}/{{$case_id}}/{{$type}}/text';
			@else
				var url = '{{url("/getProjectMixcr")}}' + '/{{$project_id}}/{{$type}}/text';
			@endif
			window.location.replace(url);	
		});
			
	});	

	function doFilter() {
		tbl.draw();
	}

	function getData() {
		$("#loadingMaster").css("display","block");
		$('#onco_layout').css('visibility', 'hidden');
		@if (isset($patient_id))
		var url = '{{url("/getMixcr")}}' + '/{{$patient_id}}/{{$case_id}}/{{$type}}';
		$('#selCount').append($('<option>', {value: -1,text: "All" }));
		$('#selCount').append($('<option>', {value: 1,text: ">1" }));
		$('#selCount').append($('<option>', {value: 2,text: ">2" }));
		$('#selCount').append($('<option>', {value: 3,text: ">3" }));
		$('#selCount').append($('<option>', {value: 4,text: ">4" }));
		$('#selCount').append($('<option>', {value: 5,text: ">5" }));
		@else
		var url = '{{url("/getProjectMixcr")}}' + '/{{$project_id}}/{{$type}}';
		$('#selCount').append($('<option>', {value: 2,text: ">2" }));
		$('#selCount').append($('<option>', {value: 3,text: ">3" }));
		$('#selCount').append($('<option>', {value: 4,text: ">4" }));
		$('#selCount').append($('<option>', {value: 5,text: ">5" }));
		@endif
		console.log(url);
       	$.ajax({ url: url, async: true, dataType: 'text', success: function(json_data) {
				$("#loadingMaster").css("display","none");
				$('#onco_layout').css('visibility', 'visible');
				data = JSON.parse(json_data);
				if (data.data.length == 0) {
					alert('no data!');
					return;
				}
				var chains = {};
				data.data.forEach(function(d, i) {
					chains[d[4]] = "";					
				});
				for(var chain in chains){
					$('#selChain').append($('<option>', {
					    value: chain,
					    text: chain
					}));
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
	<div id='loadingMaster' style="min-height: 70%;height:100%">
    		<img src='{{url('/images/ajax-loader.gif')}}'></img>
	</div>	
	<div id="onco_layout" class="easyui-layout" data-options="fit:true" style="visibility:hidden;height:auto;width:auto">		
		<div data-options="region:'center',split:true" style="width:100%;padding:5px;" >
			<div style="margin:0px">
				<span style="display: inline">
				<H4 style="display: inline">Chain:
				<select class="form-control filter" id="selChain" style="display: inline;width:200px">
					<option value="all">All</option>
				</select>
				Count:
				<select class="form-control filter" id="selCount" style="display: inline;width:200px">					
				</select>
				<button id="btnDownload" class="btn btn-info"><img width=15 height=15 src={{url("images/download.svg")}}></img>&nbsp;Download</button>
				 </H4>
				</span>
				<span style="font-family: monospace; font-size: 20;float:right;">					
				Cases: <span id="lblCountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="lblCountTotal" style="text-align:left;" text=""></span>
			</div>
			<table cellpadding="0" cellspacing="0" border="0" class="pretty" id="tblOnco" style='white-space: nowrap;width:100%'>
			</table>			
		</div>		
	</div>
</div>

