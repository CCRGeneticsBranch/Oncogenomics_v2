{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::style('css/style.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-core-css.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-blue/sm-blue.css') }}    
{{ HTML::script('js/jquery-3.6.0.min.js') }}
{{ HTML::script('packages/smartmenus-1.0.0-beta1/jquery.smartmenus.min.js') }}
{{ HTML::style('css/style_datatable.css') }}

{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{!! HTML::script('packages/DataTables/datatables.min.js') !!}
{{ HTML::script('js/bootstrap.bundle.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
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
	var fdr_idx = 9;
	var cutoff = 0.05;
	var better_group_idx=7;
	$(document).ready(function() {	
		@if ($source == "pmin")
			$('#lblSource').text("P-value minimization");
		@elseif ($source == "kmcut_s")
			$('#lblSource').text("KMCut - LogRank Test");
			fdr_idx = 7;
		@else
			$('#lblSource').text("KMCut - Permutation Test");
			fdr_idx = 7;
		@endif
		getData();
		$.fn.dataTableExt.afnFiltering.push( function( oSettings, aData, iDataIndex ) {
			
			if ($('#ckSig').is(":checked")) {
				if (aData[fdr_idx] > cutoff || aData[fdr_idx] == "NA")
					return false;				
			}
			var better_group = $('#selBetterGroup').val();
			if (better_group != "All") {
				if (aData[better_group_idx] != better_group)
					return false;				
			}
			return true;
		});

		$('#selType').on('change', function() {
			getData();			
		});
		$('#selBetterGroup').on('change', function() {
			doFilter();		
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
		var url = '{!!url("/getSurvivalListByExpression")!!}' + '/' + project_id + '/' + type + '/' + diagnosis + '/{!!$source!!}';
		var survival_url = '{!!url("/viewSurvivalByExpression")!!}' + '/' + project_id;
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
			<div style="margin-right:20px">				
				<span class="btn-group" id="interchr" data-toggle="buttons">
					&nbsp;&nbsp;<H5>Survival Types: </H5>&nbsp;&nbsp;
					<select class="form-select" id="selType" style="width:300px;display:inline">
						@foreach ($types as $type_label => $values)
						<option value="{{$values[0]}},{{$values[1]}}">{{$type_label}}</option>
						@endforeach						
					</select>
					&nbsp;&nbsp;<H5>Better Survival Group: </H5>&nbsp;&nbsp;
					<select class="form-select" id="selBetterGroup" style="width:100px;display:inline">
						<option value="All">All</option>
						<option value="High">High</option>
						<option value="Low">Low</option>
					</select>
					&nbsp;&nbsp;
					<span class="btn-group" role="group" id="sig">
						<input id="ckSig" class="btn-check" type="checkbox" autocomplete="off">
						<label class="btn btn-outline-primary" for="ckSig">Significant genes
						</label>
					</span>
					&nbsp;&nbsp;<H5>Source: &nbsp;<span id="lblSource" style="text-align:left;" text=""></span></H5>
				</span>
				<span style="font-family: monospace; font-size: 20;float:right;">					
				Cases: <span id="lblCountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="lblCountTotal" style="text-align:left;" text=""></span>
			</div>
			<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblOnco" style='white-space: nowrap;width:95%;'>
			</table> 			
		</div>		
	</div>
</div>

