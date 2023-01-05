{{ HTML::style('packages/w2ui/w2ui-1.4.min.css') }}
{{ HTML::style('css/style.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-core-css.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-blue/sm-blue.css') }}    
{{ HTML::script('js/jquery-3.6.0.min.js') }}
{{ HTML::script('packages/smartmenus-1.0.0-beta1/jquery.smartmenus.min.js') }}


{{ HTML::style('css/style.css') }}
{{ HTML::style('packages/jquery-easyui/themes/icon.css') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}
{{ HTML::style('packages/bootstrap-switch-master/dist/css/bootstrap3/bootstrap-switch.css') }}
{{ HTML::style('css/filter.css') }}
{{ HTML::style('packages/tooltipster-master/dist/css/tooltipster.bundle.min.css') }}
{{ HTML::style('packages/tooltipster-master/dist/css/tooltipster.bundle.min.css') }}
{{ HTML::style('css/font-awesome.min.css') }}
{{ HTML::script('packages/d3/d3.min.js') }}
{{ HTML::script('packages/d3/d3.tip.js') }}


{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('js/togglebutton.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') }}
{{ HTML::script('packages/tooltipster-master/dist/js/tooltipster.bundle.min.js') }}
{{ HTML::script('packages/bootstrap-switch-master/dist/js/bootstrap-switch.js') }}
{{ HTML::script('packages/w2ui/w2ui-1.4.min.js')}}
{{ HTML::script('js/filter.js') }}
{{ HTML::script('js/onco.js') }}
{{ HTML::script('packages/highchart/js/highcharts.js')}}
{{ HTML::script('packages/highchart/js/highcharts-more.js')}}
{{ HTML::script('packages/highchart/js/modules/exporting.js')}}


{{ HTML::script('packages/DataTables-1.10.8/media/js/jquery.dataTables.min.js') }}

{{ HTML::script('packages/Buttons-1.3.1/js/dataTables.buttons.min.js') }}
{{ HTML::script('packages/Buttons-1.3.1/js/buttons.flash.js') }}
{{ HTML::script('packages/Buttons-1.3.1/js/buttons.html5.js') }}
{{ HTML::script('packages/Buttons-1.3.1/js/buttons.print.js') }}
{{ HTML::script('packages/Buttons-1.3.1/js/buttons.colVis.js') }}

{{ HTML::script('packages/DataTables-1.10.8/extensions/ColReorder/js/dataTables.colReorder.min.js') }}
{{ HTML::script('packages/DataTables-1.10.8/extensions/FixedColumns/js/dataTables.fixedColumns.min.js') }}
{{ HTML::script('packages/DataTables-1.10.8/extensions/Highlight/dataTables.searchHighlight.min.js') }}
{{ HTML::script('packages/DataTables-1.10.8/extensions/Highlight/jquery.highlight.js') }}
{{ HTML::script('packages/yadcf-0.8.8/jquery.dataTables.yadcf.js')}}

{{ HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') }}
{{ HTML::style('packages/DataTables-1.10.8/media/css/jquery.dataTables.min.css') }}
{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') }}


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
    background:url('{{url('/images/close-button.png')}}') no-repeat center center;  
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
	
	var hide_cols = {"tblCNV" : []};
	var tbls = [];
	var column_tbls = [];
	var col_html = [];
	var filter_list = {'Select filter' : -1}; 
	var onco_filter;
	var dup_idx = 7;
	var cnt_idx = 8;
	var user_list_idx = 12;
	@if ($gene_id == 'null')
		hide_cols.tblCNV = [0,1,2,11];
		@if (!$has_qci)
			user_list_idx = user_list_idx - 3;
			hide_cols.tblCNV = [0,1,2,9,10,11];
		@endif
	
	@else
		hide_cols.tblCNV = [];
	@endif
	$(document).ready(function() {
		var url = '{{url("/getCNVTSO/$project_id/$patient_id/$case_id/")}}';
		
		console.log(url);		
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				$("#loading").css("display","none");	
				$("#tableArea").css("display","block");
				data = JSON.parse(data);
				if (data.cols.length == 1) {
					return;
				}				
				for (var i=user_list_idx;i<data.cols.length;i++) {
					filter_list[data.cols[i].title] = i;
					hide_cols.tblCNV.push(i);
				}
				showTable(data, 'tblCNV');				
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

		$('#btnClearFilter').on('click', function() {
			showAll();		
		});

	});
			

	function showTable(data, tblId) {		
		var tbl = $('#' + tblId).DataTable( 
		{
			"data": data.data,
			"columns": data.cols,
			"ordering":    true,
			"deferRender": true,
			"lengthMenu": [[15, 25, 50], [15, 25, 50]],
			"pageLength":  15,
			"pagingType":  "simple_numbers",			
			"dom": 'B<"toolbar">lfrtip',
			"buttons": [
        		{
            		text: '<img width=15 height=15 src={{url("images/download.svg")}}></img>&nbsp;Download',
            		extend:'csv'            		
        		},

   			],
			"columnDefs": [ {
				"targets": [ 0 ],
				"orderData": [ 0, 1 ]
				},
				{
				"targets": [ 1 ],
				"orderData": [ 1, 0 ]
				}]
		} );		

		tbls[tblId] = tbl;
		var columns =[];
		col_html[tblId] = '';
		$('#lblLength').text(data.nondiploid_length + "/" + data.total_length);
		$('#lblRatio').text((parseFloat(data.nondiploid_length)/parseFloat(data.total_length)).toFixed(2));
		$('#lblA').text(data.a);
		$('#lblC').text(data.c);
		$('#lblGI').text(data.gi);

		//$("div.toolbar").html('<button id="popover" data-toggle="popover" data-placement="bottom" type="button" class="btn btn-default" style="font-size: 12px;">Select Columns</button>');
		
		//$("#" + tblId + "_wrapper").children("div.toolbar").html('<button id="' + tblId + '_popover" data-toggle="popover" data-placement="bottom" type="button" class="btn btn-default" style="font-size: 12px;">Select Columns</button>');
		var toolbar_html = '<button id="' + tblId + '_popover" data-toggle="popover" data-placement="bottom" type="button" class="btn btn-default" style="font-size: 12px;">Select Columns</button>';
		@if ($has_qci)
			toolbar_html += ' <span><font color="red">&nbsp;&nbsp;&nbsp;Only CNV events with FC < 0.5 or FC > 2.5 are signed out in QCI. Few exceptions are applied based on Pathologist recommendation.</font></span>';
		@endif
		$("div.toolbar").html(toolbar_html);
		tbl.columns().iterator('column', function ( context, index ) {
			var show = (hide_cols[tblId].indexOf(index) == -1);
			tbl.column(index).visible(show);
			columns.push(tbl.column(index).header().innerHTML);
			checked = (show)? 'checked' : '';
			//checked = 'checked';
			col_html[tblId] += '<input type=checkbox ' + checked + ' class="onco_checkbox data_column" id="data_column_' + tblId + '" value=' + index + '><font size=3>' + tbl.column(index).header().innerHTML + '</font></input><BR>';
		});
		column_tbls[tblId] = columns;
	    $('[data-toggle="popover"]').popover({
				title: 'Select column <a href="#inline" class="close" data-dismiss="alert">×</a>',
				placement : 'bottom',  
				html : true,
				content : function() {
					var tblId= $(this).attr("id").substring(0, $(this).attr("id").indexOf('_popover'));
					return col_html[tblId];
				}
		});

		$(document).on("click", ".popover .close" , function(){
				$(this).parents(".popover").popover('hide');
		});

		
		$('body').on('change', 'input.data_column', function() {             				
				var tblId = $(this).attr("id").substring($(this).attr("id").indexOf('data_column_') + 12);
				console.log(tblId);
				var tbl = tbls[tblId];
				var columns = column_tbls[tblId];
				col_html[tblId] = '';
				for (i = 0; i < columns.length; i++) { 
					if (i == $(this).attr("value"))
						checked = ($(this).is(":checked"))?'checked' : '';
					else
						checked = (tbl.column(i).visible())?'checked' : '';
					col_html[tblId] += '<input type=checkbox ' + checked + ' class="onco_checkbox data_column" id="data_column_' + tblId + '" value=' + i + '><font size=3>&nbsp;' + columns[i] + '</font></input><BR>';
				}
				tbl.column($(this).attr("value")).visible($(this).is(":checked"));
				
		});

		$('.dataTables_filter input').on('keyup click', function() {
			doSearch();
		});

		$('#ckDupDel').on('change', function() {
			doFilter();
		});
		
		$('#tblCNV').on( 'draw.dt', function () {
			$('#lblCountDisplay').text(tbl.page.info().recordsDisplay);
    		$('#lblCountTotal').text(tbl.page.info().recordsTotal);    		
    	});

    	$.fn.dataTableExt.afnFiltering.push( function( oSettings, aData, iDataIndex ) {	
			if (oSettings.nTable == document.getElementById('tblCNV')) {
				var cnt_cutoff = $('#cnt_cutoff').numberbox("getValue");
				var cnt_op = $('#cnt_op').val();
				var cnt_val = parseFloat(aData[cnt_idx]);
				var allele_a_val = parseInt(aData[cnt_idx + 1]);
				var allele_b_val = parseInt(aData[cnt_idx + 2]);
				if (cnt_cutoff != NaN && cnt_op != "any") {
					if (cnt_op == "larger" && cnt_val < cnt_cutoff)
						return false;
					if (cnt_op == "smaller" && cnt_val > cnt_cutoff)
						return false;
					if (cnt_op == "equal" && cnt_val != cnt_cutoff)
						return false;
				}
				
				if ($('#ckDupDel').is(":checked")) {
					if (aData[dup_idx] == "." || aData[dup_idx] == "") {
						return false;
					}
				}

				if (onco_filter == null)
					return true;
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

		$('#cnt_cutoff').numberbox({onChange : function () {
				doFilter();
			}
		});

		$('#ckShowDiploid').on('change', function() {
			doFilter();
		});		

		$('#cnt_op').change(function() {
			doFilter();
		});

		$('.mytooltip').tooltipster();

	}

	function showAll() {
		tbls['tblCNV'].search('');
		$('#cnt_op').val("any");
		$('#cnt_cutoff').numberbox("setValue", 0);
		$('#ckDupDel').prop('checked', false);		
		onco_filter.clearFilter();		
	}

	function doFilter() {
		tbls['tblCNV'].draw();
		//uploadSetting();
	}

	function doSearch() {
		var body = $( tbls['tblCNV'].table().body() );
		//var value = $('#search_input').val();
		var value = $('.dataTables_filter input').val();
		if (value == "") {
			tbls['tblCNV'].search('');
			tbls['tblCNV'].draw();
			return;
		}
		body.unhighlight();
		if ($('#ckExactMatch').is(":checked")) {
			var pattern = '(\\s\\s' + value + '\,\|\,' + value + '\,\|\,' + value + '\\s\\s)';
			//console.log(pattern);
			body.highlight(tbls['tblCNV'].search(pattern, true));								
		}
		else
			body.highlight(tbls['tblCNV'].search(value));
		tbls['tblCNV'].draw();
	}
			

</script>
<div style="display:none;">	
	<div id="filter_definition" style="display:none;width:800px;height=600px">
		<H4>
		The definition of filters:<HR>
		</H4>
		<table>
			@foreach ($filter_definition as $filter_name=>$content)
			<tr valign="top"><td><font color="blue">{{$filter_name}}:</font></td><td>{{$content}}</td></tr>
			@endforeach
		</table>

	</div>
</div>
<div id='loading'><img src='{{url('/images/ajax-loader.gif')}}'></img></div>					
<div id='tableArea' style="width:98%;padding:10px;overflow:auto;display:none;text-align: left;font-size: 12px;">
	<div style="padding:10px;">
		<span id='filter' style='display: inline;height:200px;width:80%'>
			<button id="btnAddFilter" class="btn btn-primary">Add filter</button>&nbsp;<a id="fb_filter_definition" href="#filter_definition" title="Filter definitions" class="fancybox mytooltip"><img src={{url("images/help.png")}}></img></a>&nbsp;			
			<span style="font-family: monospace; font-size: 16;float:right;">				
				&nbsp;&nbsp;CNV:&nbsp;<span id="lblCountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="lblCountTotal" style="text-align:left;" text=""></span>
			</span>
		</span>
		<button id="btnClearFilter" type="button" class="btn btn-info" style="font-size: 12px;">Show all</button>		
		<span style="font-size: 14px;">
			 <input type="checkbox" class="form-check-input" id="ckDupDel" checked>
    			<label class="form-check-label" for="exampleCheck1">Dup/Del only</label>
			&nbsp;&nbsp;FC:&nbsp;
			<select class="form-control" id="cnt_op" style="width:100px;display:inline">				
				<option value="any">Any</option>
				<option value="larger">>=</option>
				<option value="smaller"><=</option>
				<option value="equal">=</option>
			</select>
			<input id="cnt_cutoff" class="easyui-numberbox num_filter" value="1" data-options="min:0,max:10000,precision:3" style="width:50px;height:26px">			
			&nbsp;			
		</span>
	</div>
	<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblCNV" style='width:100%'>
	</table> 
</div>

