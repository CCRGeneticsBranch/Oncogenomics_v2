@section('title', "Project Mutation--$cohort_id--$type")
{{ HTML::style('packages/w2ui/w2ui-1.4.min.css') }}
{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::style('css/style.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-core-css.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-blue/sm-blue.css') }}    
{{ HTML::script('js/jquery-3.6.0.min.js') }}
{{ HTML::script('packages/smartmenus-1.0.0-beta1/jquery.smartmenus.min.js') }}


{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('css/style.css') }}
{{ HTML::style('packages/jquery-easyui/themes/icon.css') }}
{{ HTML::style('packages/jquery-easyui/themes/bootstrap/easyui.css') }}
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}
{{ HTML::style('packages/muts-needle-plot/build/muts-needle-plot.css') }}
{{ HTML::style('css/heatmap.css') }}
{{ HTML::style('packages/bootstrap-switch-master/dist/css/bootstrap3/bootstrap-switch.css') }}
{{ HTML::style('css/filter.css') }}
{{ HTML::style('packages/tooltipster-master/dist/css/tooltipster.bundle.min.css') }}


{!! HTML::script('packages/DataTables/datatables.min.js') !!}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('js/bootstrap.bundle.min.js') }}
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

div.toolbar {
	display:inline;
}

.fade{
	top:0px;	
 }

</style>    
<script type="text/javascript">
	var tbl = null;
	var filter_settings = [];
	@if (property_exists($setting, "filters"))
		filter_settings = {!!$setting->filters!!};
	@endif
	var filter_list = {'Select filter' : -1}; 
	var onco_filter;
	var attr_values = {!!json_encode($meta)!!};
	var columns = [];
	var col_html = '';
	var first_loading = true;
	var user_list_idx = 6;
	var show_cols = [0,1,2,3,4,5];
	@if ($type == 'rnaseq' || $type == "variants") 
		show_cols = [0,6,7,8,9];
		user_list_idx = 10;
	@endif
	$(document).ready(function() {
		
		$('#freq_max').numberbox({
    				min:0,
    				max:1,
    				precision:20,
    				formatter:function(v){    					    					
    					var f = parseFloat(v.toString());
    					if (isNaN(f))
    						f = 1;
    					return f.toString();
					}
		});

		$('#vaf_min').numberbox({
    				min:0,
    				max:1,
    				precision:20,
    				formatter:function(v){ 
    					var f = parseFloat(v.toString());
    					if (isNaN(f))
    						f = 0;   					    					
    					return f.toString();
					}
		});

		$('#total_cov_min').numberbox({
    				min:0,
    				max:10000,
    				precision:0,
    				formatter:function(v){ 
    					var f = parseFloat(v.toString());
    					if (isNaN(f))
    						f = 0;   					    					
    					return f.toString();
					}
		});

		$('.num_filter').numberbox({onChange : function () {
				if (!first_loading)
					getData();
			}
		});	

		applySetting();

		getData();

		first_loading = false;
		
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


		$('body').on('change', 'input#data_column', function() {             
			tbl.column($(this).attr("value")).visible($(this).is(":checked"));			
		});

		$('.box').fancybox({
    		width  : '90%',
    		height : '90%',
    		type   :'iframe',
    		autoSize: false
		});

		$('#fb_filter_definition').fancybox({    		
		});

		$.fn.dataTableExt.afnFiltering.push( function( oSettings, aData, iDataIndex ) { 
			if ($('#ckTierAll').is(":checked"))
				return true;			
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
 		});

		$('#btnAddFilter').on('click', function() {						
			onco_filter.addFilter();			
        });

		$('#btnDownload').on('click', function() {
			var url = '{{url("/download${cohort_type}Variants")}}' + '/' + '{{$cohort_id}}' + '/' + '{{$type}}';
			window.location.replace(url);	
		});

		$('.tier_filter').on('change', function() {
			doFilter();
	    });

	    $('#btnSubmit').on('click', function() {
			getData();
		});

	    $('#ckGermlineLevel').on('change', function() {
	    	tbl.column(1).visible($('#ckGermlineLevel').is(":checked"));
	    	tbl.column(2).visible($('#ckGermlineLevel').is(":checked"));
	    	tbl.column(3).visible($('#ckGermlineLevel').is(":checked"));
	    	tbl.column(4).visible($('#ckGermlineLevel').is(":checked"));
	    	tbl.column(5).visible($('#ckGermlineLevel').is(":checked"));
		});

		$('#tiers').on('change', function() {
			if (!$('#ckTier1').is(":checked") || !$('#ckTier2').is(":checked") || !$('#ckTier3').is(":checked") || !$('#ckTier4').is(":checked")) {
				$('#btnTierAll').removeClass('active');
				$('#ckTierAll').prop('checked', false);
			}
			doFilter();
		});

		@if (!Config::get('site.isPublicSite'))
		$("#selAnnotation").on('change', function() {
			var url = '{{url("/saveSettingGet/default_annotation")}}' + '/' + $('#selAnnotation').val();
			$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				if (data == "Success")
					location.reload();
				}, error: function(xhr, textStatus, errorThrown){
					alert('save failed! Reason:' + JSON.stringify(xhr) + ' ' + errorThrown);
				}
			});		
		});
		@endif		

		$('#btnClearFilter').on('click', function() {
			showAll();				
		});

		$('#selMeta').on('change', function() {
			$('#selMetaValue').empty();
			var value = $('#selMeta').val();
			if (value == "any") {
				$('#selMetaValue').css("display","none");								
			}
			else {
				$('#selMetaValue').css("display","inline");
				attr_value = attr_values[value];
					attr_value.forEach(function(attr){
					$('#selMetaValue').append('<option value="' + attr + '">' + attr + '</option>');
					});
			}
        });

		

		$('.mytooltip').tooltipster();		

	});

	function getData() {
		$("#loadingMutation").css("display","block");
		var url = '{{url("/get{$cohort_type}MutationGenes/$cohort_id/$type")}}';
		var meta_type = $('#selMeta').val();
		var meta_value = "any";
		if (meta_type != "any")
			meta_value = $('#selMetaValue').val();

		url = url + '/' + encodeURIComponent(encodeURIComponent(meta_type)) + '/' + encodeURIComponent(encodeURIComponent(meta_value)) + '/' + parseFloat($('#freq_max').numberbox("getValue")) + '/' + $('#total_cov_min').numberbox("getValue") + '/' + parseFloat($('#vaf_min').numberbox("getValue"));
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				$("#loadingMutation").css("display","none");
				$("#var_layout").css("display","block");
				
				jsonData = JSON.parse(data);
				console.log("Got data");
				if (tbl != null) {				
					tbl.destroy();
					$('#tblMutations').empty();
				}

				tbl = $('#tblMutations').DataTable( 
					{
						"data": jsonData.data,
						"columns": jsonData.cols,
						"ordering":    true,
						"deferRender": true,
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

    			for (var i=user_list_idx;i<jsonData.cols.length;i++) {
					filter_list[jsonData.cols[i].title] = i;					
				}						

				onco_filter = new OncoFilter(Object.keys(filter_list), filter_settings, function() {doFilter();});

				/*
				tbl.columns().iterator('column', function ( context, index ) {			
					tbl.column(index).visible(true, false);
				} );
				*/

				$("div.toolbar").html('<button id="popover" data-toggle="popover" data-placement="bottom" type="button" class="btn btn-success" >' + 
								'Select Columns</button>');
				
				col_html = '';

				console.log("before show columns");
				tbl.columns().iterator('column', function ( context, index ) {
					var show = (show_cols.indexOf(index) != -1);
					tbl.column(index).visible(show, false);
					checked = (show)? 'checked' : '';
					columns.push(tbl.column(index).header().innerHTML);
					col_html += '<input type=checkbox ' + checked + ' class="onco_checkbox" id="data_column" value=' + index + '><font size=3>&nbsp;' + tbl.column(index).header().innerHTML + '</font></input><BR>';
				} );
				console.log("done");
				$('[data-toggle="popover"]').popover({
					title: 'Select column <a href="#inline" class="close" data-dismiss="alert">×</a>',
					placement : 'right',  
					html : true,
					sanitize: false,
					content : function() {
						return col_html;
					}
				}); 

				$(document).on("click", ".popover .close" , function(){
					$('[data-toggle="popover"]').popover('hide');
				});

    			$('.mytooltip').tooltipster();
			}
		});
	}
	function showAll() {		
		tbl.search('');
		onco_filter.clearFilter();
	}

	function doFilter() {
		tbl.draw();
	}

	function checkTier(aData, idx) {
		if ($('#ckTier1').is(":checked") && aData[idx + 1] != '')
			return true;
		if ($('#ckTier2').is(":checked") && aData[idx + 2] != '')
			return true;
		if ($('#ckTier3').is(":checked") && aData[idx + 3] != '')
			return true;
		if ($('#ckTier4').is(":checked") && aData[idx + 4] != '')
			return true;
		return false;
	}

	function applySetting() {
		var maf = {{!is_numeric($setting->maf)?"0.05":$setting->maf}}; 
		var total_cov = {{!is_numeric($setting->total_cov)?"10":$setting->total_cov}}; 
		var vaf = {{!is_numeric($setting->vaf)?"0.25":$setting->vaf}}; 
		
		$('#freq_max').numberbox("setValue" , maf);
		$('#total_cov_min').numberbox("setValue", total_cov);
		$('#vaf_min').numberbox("setValue", vaf);

		var tier1 = {{empty($setting->tier1)?"true":$setting->tier1}};
		var tier2 = {{empty($setting->tier2)?"true":$setting->tier2}};
		var tier3 = {{empty($setting->tier3)?"false":$setting->tier3}};
		var tier4 = {{empty($setting->tier4)?"false":$setting->tier4}};
		var no_tier = {{empty($setting->no_tier)?"false":$setting->no_tier}};
		var no_fp = {{empty($setting->no_fp)?"false":$setting->no_fp}};
		

		if (tier1) {
			$('#btnTier1').addClass('active');
			$('#ckTier1').prop('checked', true);
		}else {
			$('#btnTier1').removeClass('active');
			$('#ckTier1').prop('checked', false);	
		}
		if (tier2) {
			$('#btnTier2').addClass('active');
			$('#ckTier2').prop('checked', true);
		}else {
			$('#btnTier2').removeClass('active');
			$('#ckTier2').prop('checked', false);	
		}
		if (tier3) {
			$('#btnTier3').addClass('active');
			$('#ckTier3').prop('checked', true);
		}else {
			$('#btnTier3').removeClass('active');
			$('#ckTier3').prop('checked', false);	
		}
		if (tier4) {
			$('#btnTier4').addClass('active');
			$('#ckTier4').prop('checked', true);
		}else {
			$('#btnTier4').removeClass('active');
			$('#ckTier4').prop('checked', false);	
		}
		@if ($type == "rnaseq")		
		if (no_fp) {
			$('#btnNoFP').addClass('active');
			$('#ckNoFP').prop('checked', true);
		}else {
			$('#btnNoFP').removeClass('active');
			$('#ckNoFP').prop('checked', false);	
		}
		@endif
		if (tier1 && tier2 && tier3 && tier4) {
			$('#btnTierAll').addClass('active');
			$('#ckTierAll').prop('checked', true);	
		} else {
			$('#btnTierAll').removeClass('active');
			$('#ckTierAll').prop('checked', false);	
		}
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

<div class="easyui-panel" data-options="border:false" style="height:100%;padding:10px;">	
	<div id='loadingMutation' class='loading_img' style="height:100%">
		<img src='{{url('/images/ajax-loader.gif')}}'></img>
	</div>
	<div id="var_layout" class="easyui-layout" data-options="fit:true,border:false" style="display:none;overflow:auto">	
		<div style="margin:10px 0">
			<span id='filter' style='display: inline;height:200px;width:80%'>
				<button id="btnAddFilter" class="btn btn-primary">Gene List Filter</button>&nbsp;<a id="fb_filter_definition" href="#filter_definition" title="Filter definitions" class="fancybox mytooltip"><img src={{url("images/help.png")}}></img></a>&nbsp;
			</span>
			<button id="btnClearFilter" type="button" class="btn btn-success" >Show all</button>
			@if ($type == 'rnaseq' || $type == "variants") 
			<span class="btn-group-toggle" id="tiers" data-toggle="buttons" style="padding:5px">
				<input id="ckGermlineLevel" class="btn-check ckTier" type="checkbox" autocomplete="off"> 
				<label id="btnHighConf" class="btn btn-outline-primary" for="ckGermlineLevel">Show Germline Tiering</label>
			</span>
			@endif
			@if ($type != 'somatic')
				<a href='{{url("data/".Config::get('onco.classification_germline_file'))}}' title="Germline tier definitions" class="fancybox mytooltip box"><img src={{url("images/help.png")}}></img></a>
			@endif
			@if ($type != 'germline')
				<a href='{{url("data/".Config::get('onco.classification_somatic_file'))}}' title="Somatic tier definitions" class="fancybox mytooltip box"><img src={{url("images/help.png")}}></img></a>
			@endif
			@if ($has_variant_file)
			<button id="btnDownload" class="btn btn-info"><img width=15 height=15 src={{url("images/download.svg")}}></img>&nbsp;Download all variants</button>
			@endif
			<br><hr>			
			<span class="mytooltip" title="Maximum population allele frequency">MAF:&nbsp;</span><input id="freq_max" class="easyui-numberbox num_filter" data-options="min:0,max:1,precision:20" style="width:60px;height:26px">
			<span class="mytooltip" title="Minimum total coverage">Min Total Cov:&nbsp;</span><input id="total_cov_min" class="easyui-numberbox num_filter" data-options="min:0,max:10000,precision:1" style="width:50px;height:26px">
			<span class="mytooltip" title="Minimum allele frequency">Min VAF:&nbsp;</span><input id="vaf_min" class="easyui-numberbox num_filter" data-options="min:0,max:1,precision:20" style="width:50px;height:26px">
			
				<!--
			Diagnosis:<select id="selDiagnosis" class="form-control" style="width:300px;display:inline">
			@foreach ($diag_counts as $diag_count)
				<option value="{{$diag_count->diagnosis}}" {{($diagnosis == $diag_count->diagnosis)? "selected" : ""}}>{{$diag_count->diagnosis}} ({{$diag_count->patient_count}} patients)</option>						
			@endforeach
			</select-->
			Meta:<select id="selMeta" class="form-select" style="width:120px;display:inline;font-size:12">
				<option value="any">All data</option>
				@foreach ($meta as $key => $value)
				<option value="{{$key}}">{{$key}}</option>
				@endforeach				
			</select>
			<select id="selMetaValue" class="form-control" style="width:150px;display:none"></select>
			@if (!Config::get('site.isPublicSite'))
			<!--
			Annotation:<select id="selAnnotation" class="form-control" style="width:150px;display:inline"> 
							<option value="khanlab" {{($annotation == 'khanlab')? 'selected' : ''}}>Khan Lab</option>
							<option value="avia" {{($annotation == 'avia')? 'selected' : ''}}>AVIA</option>
						</select>
			-->
			@endif
			
			<button id='btnSubmit' class="btn btn-success">Submit</button>

			<span style="font-family: monospace; font-size: 20;float:right;">	
			Genes: <span id="lblCountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="lblCountTotal" style="text-align:left;" text=""></span>
		</div>		
		<table cellpadding="5" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblMutations" style='width:100%;'>			
		</table>		
	</div>
</span>