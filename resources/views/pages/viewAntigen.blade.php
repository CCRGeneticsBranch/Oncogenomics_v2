{!! HTML::style('packages/w2ui/w2ui-1.4.min.css') !!}
{!! HTML::style('css/bootstrap.min.css') !!}
{!! HTML::script('js/jquery-3.6.0.min.js') !!}


{!! HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') !!}
{!! HTML::style('css/style_datatable.css') !!}
{!! HTML::style('css/style.css') !!}
{!! HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') !!}
{!! HTML::style('packages/jquery-easyui/themes/icon.css') !!}
{!! HTML::style('packages/jquery-easyui/themes/default/easyui.css') !!}
{!! HTML::style('packages/fancyBox/source/jquery.fancybox.css') !!}
{!! HTML::style('packages/muts-needle-plot/build/muts-needle-plot.css') !!}
{!! HTML::style('packages/bootstrap-switch-master/dist/css/bootstrap3/bootstrap-switch.css') !!}
{!! HTML::style('css/filter.css') !!}
{!! HTML::style('packages/tooltipster-master/dist/css/tooltipster.bundle.min.css') !!}

{!! HTML::script('packages/DataTables/datatables.min.js') !!}
{!! HTML::script('packages/jquery-easyui/jquery.easyui.min.js') !!}
{!! HTML::script('js/bootstrap.bundle.min.js') !!}
{!! HTML::script('js/togglebutton.js') !!}
{!! HTML::script('packages/jquery-easyui/jquery.easyui.min.js') !!}
{!! HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') !!}
{!! HTML::script('packages/tooltipster-master/dist/js/tooltipster.bundle.min.js') !!}
{!! HTML::script('packages/bootstrap-switch-master/dist/js/bootstrap-switch.js') !!}
{!! HTML::script('packages/w2ui/w2ui-1.4.min.js')!!}
{!! HTML::script('js/filter.js') !!}
{!! HTML::script('js/onco.js') !!}
{!! HTML::script('packages/highchart/js/highcharts.js')!!}
{!! HTML::script('packages/highchart/js/highcharts-more.js')!!}


<style>

.block_details {
    display:none;
    width:90%;
    height:130px;    
	border-radius: 10px;
	border: 2px solid #73AD21;
	padding: 10px; 
	margin: 10px; 
	overflow: auto; 
}

.toolbar {
	display:inline;
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
    background:url('{!!url('/images/close-button.png')!!}') no-repeat center center;  
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
	var hide_columns = {!!$hide_columns!!};
	var col_html = '';
	var columns =[];
	var filter_list = {'Select filter' : -1}; 
	var onco_filter;
	var high_conf_idx = 0;
	var gene_list_idx = 0;
	var tumor_total_idx = 0;
	var tumor_var_idx = 0;
	var normal_total_idx = 0;
	var normal_var_idx = 0;
	var rnaseq_total_idx = 0;
	var rnaseq_var_idx = 0;
	var fc_idx = 0;
	$(document).ready(function() {
		var url = '{!!url('/getAntigenData')!!}' + '/' + '{!!$project_id!!}' + '/' + '{!!$patient_id!!}' + '/' + '{!!$case_id!!}' + '/' + '{!!$sample_id!!}';
		
		console.log(url);		
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				$("#loading").css("display","none");	
				$("#tableArea").css("display","block");
				data = parseJSON(data);
				if (data.cols.length == 1) {
					return;
				}
				for (var i in data.cols) {
					if (data.cols[i].title == "{!!Lang::get('messages.var_cov')!!}")
						tumor_var_idx = i;
					if (data.cols[i].title == "{!!Lang::get('messages.total_cov')!!}")
						tumor_total_idx = i;
					if (data.cols[i].title == "{!!Lang::get('messages.normal_var_cov')!!}")
						normal_var_idx = i;
					if (data.cols[i].title == "{!!Lang::get('messages.normal_total_cov')!!}")
						normal_total_idx = i;
					if (data.cols[i].title == "{!!Lang::get('messages.matched_var_cov')!!}")
						rnaseq_var_idx = i;
					if (data.cols[i].title == "{!!Lang::get('messages.matched_total_cov')!!}")
						rnaseq_total_idx = i;
					if (data.cols[i].title == "Corresponding fold change")
						fc_idx = i;
					if (data.cols[i].title == "High conf")
						high_conf_idx = i;
				}
				gene_list_idx = data.gene_list_idx;
				for (var i=gene_list_idx;i<data.cols.length;i++) {
					filter_list[data.cols[i].title] = i;
					if (hide_columns.indexOf(data.cols[i].title) == -1)
						hide_columns.push(data.cols[i].title);
				}
				showTable(data, 'tblOnco');				
				onco_filter = new OncoFilter(Object.keys(filter_list), null, function() {doFilter();});	
				doFilter();		
			}			
		});

		$('#fb_tier_definition').fancybox({ 
			width  : 1200,
    		height : 800,
    		type   :'iframe'   		
		});

		$('#fb_filter_definition').fancybox({    		
		});

		$('#btnAddFilter').on('click', function() {						
			onco_filter.addFilter();			
        });

		$('.num_filter').numberbox({onChange : function () {
				doFilter();
			}
		});

		$('#btnClearFilter').on('click', function() {
			showAll();		
		});
	});
		
	function showExp(d) {
		//alert(JSON.stringify(d.innerHTML));
		@if (count($rnaseq_samples) == 0)
			alert("No RNAseq data");
			return;
		@endif
		var gene_id = d.innerHTML;
		gene_id = gene_id.replace('<span class="highlight">','');
		gene_id = gene_id.replace('</span>','');
		var url = '{!!url("/getExpression/$project_id/")!!}' + '/' + gene_id + '/refseq';
		console.log(JSON.stringify(url));
		$(d).w2overlay('<H4><div style="padding:30px">loading...<div></H4>');
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				var data = parseJSON(data);
				var rnaseq_sample_names = {!!json_encode(array_values($rnaseq_samples))!!};
				//console.log(JSON.stringify(data.exp_data));
				console.log(JSON.stringify(data.samples));
				console.log(JSON.stringify(rnaseq_sample_names));
				//return;
				var values = getSortedScatterValues(data.exp_data[gene_id].refseq, data.samples, rnaseq_sample_names);
				var sample_idx = 0;
				for (var i in data.samples) {
					for (var j in rnaseq_sample_names) {
						if (data.samples[i] == rnaseq_sample_names[j]) {
							sample_idx = i;
							break;
						}
					}
				}
				fpkm = (sample_idx == 0)? "NA" : Math.round(data.exp_data[gene_id].refseq[sample_idx] * 100) / 100;
				var title = gene_id + ', ' + rnaseq_sample_names[0] + ' <font color="red">(FPKM: ' + fpkm + ')</font>';
				$(d).w2overlay('<div id="exp_plot" style="width:380px;height:260px"></div>', { css: { width: '400px', height: '250px', padding: '10px' } });
				drawScatterPlot('exp_plot', title, values);
			}							
		});
	}	

	function showTable(data, tblId) {		
		tbl = $('#' + tblId).DataTable( 
		{
			"data": data.data,
			"columns": data.cols,
			"ordering":    true,
			"deferRender": true,
			"searchHighlight": true,
			"lengthMenu": [[15, 25, 50], [15, 25, 50]],
			"pageLength":  15,
			"pagingType":  "simple_numbers",			
			"dom": 'l<"toolbar">frtip',
			"columnDefs": [ {
				"targets": [ 0 ],
				"orderData": [ 0, 1 ]
				},
				{
				"targets": [ 1 ],
				"orderData": [ 1, 0 ]
				}]
		} );		

		columns =[];		
		col_html = '';
		
		var toolbar_html = '<button id="' + tblId + '_popover" data-toggle="popover" data-placement="bottom" type="button" class="btn btn-default" style="font-size: 12px;">Select Columns</button>';		
		$("div.toolbar").html(toolbar_html);
		
		tbl.columns().iterator('column', function ( context, index ) {
			var column_html = tbl.column(index).header().innerHTML;
			var column_text = column_html;			
			column_text = getInnerText(column_html);

			var show = (hide_columns.indexOf(column_text) == -1);
			tbl.column(index).visible(show);
			columns.push(column_text);
			checked = (show)? 'checked' : '';
			
			col_html += '<input type=checkbox ' + checked + ' class="onco_checkbox data_column" id="data_column" value=' + index + '><font size=3>&nbsp;' + column_text + '</font></input><BR>';
		} );

	        
		$("#" + tblId + "_popover").popover({				
				title: 'Select column <a href="#inline" class="close" data-dismiss="alert">×</a>',
				placement : 'bottom',  
				html : true,
				sanitize: false,
				content : function() {					
					return col_html;
				}
		});

		$(document).on("click", ".popover .close" , function(){
				$(this).parents(".popover").popover('hide');
		});

		
		$('body').on('change', 'input.data_column', function() {  
				tbl.column($(this).attr("value")).visible($(this).is(":checked"));
				col_html = '';
				for (i = 0; i < columns.length; i++) { 
					if (i == $(this).attr("value"))
						checked = ($(this).is(":checked"))?'checked' : '';
					else
						checked = (tbl.column(i).visible())?'checked' : '';
					col_html += '<input type=checkbox ' + checked + ' class="onco_checkbox data_column" id="data_column" value=' + i + '><font size=3>&nbsp;' + columns[i] + '</font></input><BR>';
				}
				if ($(this).is(":checked"))
					removeElement(hide_columns, columns[$(this).attr("value")]);
				else
					hide_columns.push(columns[$(this).attr("value")]);
									
				uploadColumnSetting();
				
		});
		
		
		$('#tblOnco').on( 'draw.dt', function () {
			$('#lblCountDisplay').text(tbl.page.info().recordsDisplay);
    		$('#lblCountTotal').text(tbl.page.info().recordsTotal);    		
    	});

		$('.filter').on('change', function() {	
		  	if ($('#ckMatched').is(":checked")) 
		  		$("#matchedCovFilter").css("display","inline");
		   	else
		   		$("#matchedCovFilter").css("display","none"); 
			doFilter();
        });


    	$.fn.dataTableExt.afnFiltering.push( function( oSettings, aData, iDataIndex ) {	
			if (oSettings.nTable == document.getElementById('tblOnco')) {
				if (onco_filter == null)
					return true;
				if (parseInt(aData[rnaseq_total_idx]) < $('#matched_total_cov_min').numberbox("getValue"))
					return false;
				if (parseInt(aData[rnaseq_var_idx]) < $('#matched_var_cov_min').numberbox("getValue"))
					return false;
				if (parseInt(aData[tumor_total_idx]) < $('#total_cov_min').numberbox("getValue"))
					return false;
				if (parseInt(aData[tumor_var_idx]) < $('#var_cov_min').numberbox("getValue"))
					return false;				
				if ($('#ckHighConf').is(":checked")){
					if (aData[high_conf_idx] != 'Y')
						return false;
				}
				
				if (parseFloat(aData[fc_idx]) < $('#fc_cutoff').numberbox("getValue"))
					return false;
				var outer_comp_list = [];
				filter_settings = [];
				for (var filter in onco_filter.filters) {
					var comp_list = [];
					var filter_setting = [];				
					for (var i in onco_filter.filters[filter]) {
						var filter_item_setting = [];
						var filter_name = onco_filter.getFilterName(filter, i);
						var idx = filter_list[filter_name];
						filter_item_setting.push(filter_name);
						if (idx == -1)
							currentEval = true;
						else
							currentEval = (aData[idx] != '');
	        			if (onco_filter.hasFilterOperator(filter, i)) {
	        				var op = (onco_filter.getFilterOperator(filter, i))? "&&" : "||";
	        				filter_item_setting.push(op);
	        				comp_list.push(op);
	        			}
	        			filter_setting.push(filter_item_setting);
	        			comp_list.push(currentEval);
					}				
					outer_comp_list.push('(' + comp_list.join(' ') + ')');
					filter_settings.push(filter_setting);
				}

				if (outer_comp_list.length == 0)
					final_decision = true;
				else	
					final_decision = eval(outer_comp_list.join('||'));
	        	return final_decision;
			}
			return true;
		});			

		$('.mytooltip').tooltipster();

	}

	function uploadColumnSetting() {		
		var url = '{!!url("/saveSetting")!!}' + '/antigen.columns';
		$.ajax({ url: url, async: true, type: 'POST', dataType: 'text', data: {"hide":hide_columns}, success: function(data) {
			}, error: function(xhr, textStatus, errorThrown){
					console.log('save failed! Reason:' + JSON.stringify(xhr) + ' ' + errorThrown);
				}
		});	

	}

	function showAll() {
		tbl.search('');
		$('#fc_cutoff').numberbox("setValue", 0);
		$('#var_cov_min').numberbox("setValue", 0);
		$('#total_cov_min').numberbox("setValue", 0);
		$('#matched_var_cov_min').numberbox("setValue", 0);
		$('#matched_total_cov_min').numberbox("setValue", 0);
		onco_filter.clearFilter();		
	}

	function doFilter() {
		tbl.draw();
		//uploadSetting();
	}

</script>
<div style="display:none;">	
	<div id="filter_definition" style="display:none;width:800px;height=600px">
		<H4>
		The definition of filters:<HR>
		</H4>
		<table>
			@foreach ($filter_definition as $filter_name=>$content)
			<tr valign="top"><td><font color="blue">{!!$filter_name!!}:</font></td><td>{!!$content!!}</td></tr>
			@endforeach
		</table>

	</div>
</div>
<div id='loading'><img src='{!!url('/images/ajax-loader.gif')!!}'></img></div>					
<div id='tableArea' style="width:98%;padding:10px;overflow:auto;display:none;text-align: left;font-size: 12px;">
	<div style="padding:10px;">
		<span id='filter' style='display: inline;height:200px;width:80%'>
			<button id="btnAddFilter" class="btn btn-primary">Add filter</button>&nbsp;<a id="fb_filter_definition" href="#filter_definition" title="Filter definitions" class="fancybox mytooltip"><img src={!!url("images/help.png")!!}></img></a>&nbsp;			
			<span style="font-family: monospace; font-size: 20;float:right;padding:10px;">
			&nbsp;&nbsp;Count:&nbsp;<span id="lblCountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="lblCountTotal" style="text-align:left;" text=""></span>
			</span>
		</span>
		<button id="btnClearFilter" type="button" class="btn btn-info" style="font-size: 12px;">Show all</button>		
		<span style="font-size: 14px;">			
			Min Fold Change: <input id="fc_cutoff" class="easyui-numberbox num_filter" value="0" data-options="min:0,max:100000,precision:0" style="width:70px;height:26px" >
			<span class="btn-group-toggle filter" id="Highconf" data-toggle="buttons">
  				<label id="btnHighConf" class="btn btn-default mut">
				<input id="ckHighConf" type="checkbox" autocomplete="off">High Conf
			</label></span>
			<!-- <span class="btn-group filter" id="matched" data-toggle="buttons">
  				<label id="btnMatched" class="btn btn-default mut">
				<input id="ckMatched" type="checkbox" autocomplete="off">In RNA
				</label>
			</span> -->
			<span id="CovFilter" style="display:inline">&nbsp;
				<input id="var_cov_min" class="easyui-numberbox num_filter" data-options="min:0,max:10000,value:2,precision:0" style="width:40px;height:26px">/<input id="total_cov_min" class="easyui-numberbox num_filter" data-options="min:0,max:10000,value:10,precision:0" style="width:40px;height:26px">
				(Variant/Total)
				<input id="matched_var_cov_min" class="easyui-numberbox num_filter" data-options="min:0,max:10000,value:2,precision:0" style="width:40px;height:26px">/<input id="matched_total_cov_min" class="easyui-numberbox num_filter" data-options="min:0,max:10000,value:10,precision:0" style="width:40px;height:26px">
			(RNAseq Variant/Total)</span>
			<a target=_blank class="btn btn-info" href='{!!url("/downloadAntigenData/$patient_id/$case_id/$sample_id")!!}'><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Download</a>
		</span>
	</div>
	<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblOnco" style='width:100%'>
	</table> 
</div>

