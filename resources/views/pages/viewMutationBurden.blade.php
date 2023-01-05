{{ HTML::style('packages/w2ui/w2ui-1.4.min.css') }}
{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::style('css/style.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-core-css.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-blue/sm-blue.css') }}    
{{ HTML::script('js/jquery-3.6.0.min.js') }}
{{ HTML::script('packages/smartmenus-1.0.0-beta1/jquery.smartmenus.min.js') }}


{{ HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') }}
{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('css/style.css') }}
{{ HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') }}
{{ HTML::style('packages/jquery-easyui/themes/icon.css') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}
{{ HTML::style('packages/muts-needle-plot/build/muts-needle-plot.css') }}
{{ HTML::style('css/heatmap.css') }}
{{ HTML::style('packages/canvasXpress/css/canvasXpress.css') }}
{{ HTML::style('packages/bootstrap-switch-master/dist/css/bootstrap3/bootstrap-switch.css') }}
{{ HTML::style('css/filter.css') }}
{{ HTML::style('packages/tooltipster-master/dist/css/tooltipster.bundle.min.css') }}


{{ HTML::script('packages/DataTables-1.10.8/media/js/jquery.dataTables.min.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/dataTables.buttons.min.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.flash.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.html5.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.print.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.colVis.js') }}
{{ HTML::script('packages/DataTables-1.10.8/extensions/ColReorder/js/dataTables.colReorder.min.js') }}
{{ HTML::script('packages/DataTables-1.10.8/extensions/FixedColumns/js/dataTables.fixedColumns.min.js') }}
{{ HTML::script('packages/yadcf-0.8.8/jquery.dataTables.yadcf.js')}}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('js/togglebutton.js') }}
{{ HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/tooltipster-master/dist/js/tooltipster.bundle.min.js') }}
{{ HTML::script('packages/bootstrap-switch-master/dist/js/bootstrap-switch.js') }}
{{ HTML::script('js/onco.js') }}
{{ HTML::script('js/filter.js') }}

<style>
html, body { height:100%; width:100%;} ​
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

a.boxclose{
    float:right;
    margin-top:-12px;
    margin-right:-12px;
    cursor:pointer;
    color: #fff;
    border-radius: 10px;
    font-weight: bold;
    display: inline-block;
    line-height: 0px;
    padding: 8px 3px; 
    width:25px;
    height:25px;    
    background:url('{{url('/images/close-button.png')}}') no-repeat center center;  
}

.toolbar {
	display:inline;
}

.popover-content {
	top:0px;
	height: 450px;
	overflow-y: auto;
 }

.fade{
	top:0px;	
 }

</style>    
<script type="text/javascript">
	var tbl = null;
	var columns = [];
	var col_html = '';
	var user_list_idx = 6;
	var show_cols = [2,3,4,5,6,7,8];
	$(document).ready(function() {
		
		getData();
		
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


		$('#selCallers').on('change', function() {	
	       	doFilter();
        });

		$('body').on('change', 'input#data_column', function() {             
			tbl.column($(this).attr("value")).visible($(this).is(":checked"));			
		});
		
		$.fn.dataTableExt.afnFiltering.push( function( oSettings, aData, iDataIndex ) {
			var sel_caller = $('#selCallers').val();
			if (sel_caller == "all")
				return true;
			var caller = aData[5];
			console.log(sel_caller);
			console.log(caller);
			return (sel_caller == caller);
 		});

		$('.mytooltip').tooltipster();		

	});

	function getData() {
		$("#loadingMutation").css("display","block");
		var url = '{{url("/getMutationBurden/$project_id/$patient_id/$case_id")}}';		
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				$("#loadingMutation").css("display","none");
				$("#var_layout").css("display","block");
				
				jsonData = JSON.parse(data);

				if (tbl != null) {				
					tbl.destroy();
					$('#tblMutations').empty();
				}

				tbl = $('#tblMutations').DataTable( 
					{
						"data": jsonData.data,
						"columns": jsonData.cols,
						"ordering":    true,
						"order":[[1, "Desc"]],
						"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
						"pageLength":  15,
						"pagingType":  "simple_numbers",			
						"dom": '<"toolbar">lfrtip'						
						//"buttons": ['csv', 'excel']
					} 
				);
				

				$('#lblCountDisplay').text(tbl.page.info().recordsDisplay);
    			$('#lblCountTotal').text(tbl.page.info().recordsTotal);

				$('#tblMutations').on( 'draw.dt', function () {
					$('#lblCountDisplay').text(tbl.page.info().recordsDisplay);
    				$('#lblCountTotal').text(tbl.page.info().recordsTotal);
    				$('.mytooltip').tooltipster();
    			});    			

				tbl.columns().iterator('column', function ( context, index ) {			
					tbl.column(index).visible(true);
				} );

				$("div.toolbar").html('<div><table><tr></div><button id="popover" data-toggle="popover" title="Select Column" data-placement="bottom" type="button" class="btn btn-default" style="font-size: 12px;">Select Columns</button>');
				col_html = '';

				tbl.columns().iterator('column', function ( context, index ) {
					var show = (show_cols.indexOf(index) != -1);
					tbl.column(index).visible(show);
					checked = (show)? 'checked' : '';
					columns.push(tbl.column(index).header().innerHTML);
					col_html += '<input type=checkbox ' + checked + ' class="onco_checkbox" id="data_column" value=' + index + '><font size=3>&nbsp;' + tbl.column(index).header().innerHTML + '</font></input><BR>';
				} );
				
				$('[data-toggle="popover"]').popover({
					placement : 'right',  
					html : true,
					content : function() {
						return col_html;
					}
				}); 

    			$('.mytooltip').tooltipster();
			}
		});
	}	

	function doFilter() {
		tbl.draw();
	}	

</script>

<div class="easyui-panel" data-options="border:false" style="height:100%;padding:10px;">	
	<div id='loadingMutation' class='loading_img' style="height:100%">
		<img src='{{url('/images/ajax-loader.gif')}}'></img>
	</div>
	<div id="var_layout" class="easyui-layout" data-options="fit:true,border:false" style="display:none;overflow:auto">	
		<div style="margin:10px 0">
			<span style="font-size:18">Callers: <select id="selCallers" class="form-control" style="width:200px;display:inline">
				<option value="all" selected>All</option>
				<option value="MuTect">MuTect</option>
				<option value="strelka.indels">Strelka-Indels</option>
				<option value="strelka.snvs">Strelka-SNVs</option>
			</select>
			</span>
			<span style="font-family: monospace; font-size: 20;float:right;">	
			Records: <span id="lblCountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="lblCountTotal" style="text-align:left;" text=""></span>
		</div>		
		<table cellpadding="10" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblMutations" style='width:100%;overflow:auto;'>
		</table>		
	</div>
</span>