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
	var project_id = '{{$project_id}}';
	$(document).ready(function() {	
		getData();
		$.fn.dataTableExt.afnFiltering.push( function( oSettings, aData, iDataIndex ) {
			var fdr_idx = 5;
			var cutoff = 0.05;
			if ($('#ckSig').is(":checked")) {
				if (aData[fdr_idx] > cutoff || aData[fdr_idx] == "NA")
					return false;				
			}
			return true;
		});

		$('#selType').on('change', function() {
			getData();			
		});
			
	});	

	function doFilter() {
		tbl.draw();
	}

	function getData() {
		$("#loadingMaster").css("display","block");
		$('#onco_layout').css('visibility', 'hidden');
		var values = $('#selType').val();
		var values = values.split(",");
		var type = values[0];
		var diagnosis = values[1];
		var url = '{{url("/getSurvivalListByExpression")}}' + '/' + project_id + '/' + type + '/' + diagnosis;
		var survival_url = '{{url("/viewSurvivalByExpression")}}' + '/' + project_id;
		console.log(url);
       	$.ajax({ url: url, async: true, dataType: 'text', success: function(json_data) {
				$("#loadingMaster").css("display","none");
				$('#onco_layout').css('visibility', 'visible');
				data = JSON.parse(json_data);
				if (data.data.length == 0) {
					alert('no data!');
					return;
				}
				data.data.forEach(function(d,i) {
					var symbol = d[0];
					data.data[i][0] = "<a target=_blank href='" + survival_url + "/" + symbol + "/Y/Y/" + type + '/' + diagnosis + "'>" + symbol + "</a>";
				});
				showTable(data);
			}
		});

		$('#ckSig').on('change', function() {
			doFilter();
		});
	}

	function showTable(data) {
		cols = data.cols;		

		hide_cols = [];
		if (tbl != null)
			tbl.destroy();
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

<div class="easyui-panel" style="padding:0px;">
	<div id='loadingMaster' >
    		<img src='{{url('/images/ajax-loader.gif')}}'></img>
	</div>	
	<div id="onco_layout" class="easyui-layout" data-options="fit:true" style="height:100%;visibility:hidden">		
		<div data-options="region:'center',split:true" style="height:100%;width:100%;padding:0px;overflow:none;" >
			<div style="margin:20px">				
				<span class="btn-group" id="interchr" data-toggle="buttons">
					&nbsp;&nbsp;&nbsp;Survival Types: 
					<select class="form-control" id="selType" style="width:400px;display:inline">
						@foreach ($types as $type_label => $values)
						<option value="{{$values[0]}},{{$values[1]}}">{{$type_label}}</option>
						@endforeach						
					</select>
			  		<label class="mut btn btn-default">
							<input class="ck" id="ckSig" type="checkbox" autocomplete="off">Significant genes
					</label>
				</span>
				<span style="font-family: monospace; font-size: 20;float:right;">					
				Cases: <span id="lblCountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="lblCountTotal" style="text-align:left;" text=""></span>
			</div>
			<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblOnco" style='white-space: nowrap;width:95%;'>
			</table> 			
		</div>		
	</div>
</div>

