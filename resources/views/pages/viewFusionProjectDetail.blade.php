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

</style>    
<script type="text/javascript">
	var tbl = null;
	var filter_settings = [];
	@if (property_exists($setting, "filters"))
		filter_settings = {{$setting->filters}};
	@endif
	var filter_list = {'Select filter' : -1}; 
	var onco_filter;
	var user_list_idx = 13;
	var show_cols = [0,1,2,3,4,5,6,7,8,9,10,11,12,13];
	var tblId = "tblFusion";
	var columns = [];
	var col_html = '';

	$(document).ready(function() {
		$("#loadingFusion").css("display","block");
		var url = '{{url("/getFusionProjectDetail/$project_id")}}';
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				$("#loadingFusion").css("display","none");
				$("#var_layout").css("display","block");				
				jsonData = JSON.parse(data);
				tbl = $('#' + tblId).DataTable( 
					{
						"data": jsonData.data,
						"columns": jsonData.cols,
						"ordering":    true,
						"order":[[1, "Desc"]],
						"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
						"pageLength":  15,
						"pagingType":  "simple_numbers",			
						"dom": '<"toolbar">lfrtip',
						//"buttons": ['csv', 'excel']
					} 
				);				

				$('#' + tblId).on( 'draw.dt', function () {
					$('#lblCountDisplay').text(tbl.page.info().recordsDisplay);
    				$('#lblCountTotal').text(tbl.page.info().recordsTotal);
    				$('.mytooltip').tooltipster();
    			});

    			for (var i=user_list_idx;i<jsonData.cols.length;i++) {
					filter_list[jsonData.cols[i].title] = i;					
				}						

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
					placement : 'bottom',  
					html : true,
					content : function() {
						return col_html;
					}
				}); 				

    			$('.mytooltip').tooltipster();

				onco_filter = new OncoFilter(Object.keys(filter_list), filter_settings, function() {doFilter();});

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

		$('#selTypes').on('change', function() {
			doFilter();
		});

		$('#selMinPatients').on('change', function() {
			doFilter();
		});

		$('#ckInterChr').on('change', function() {
			doFilter();
		});

		$('#btnAddFilter').on('click', function() {						
			onco_filter.addFilter();			
        });

		$('.filter').on('change', function() {
			if (!$('#ckTier1').is(":checked") || !$('#ckTier2').is(":checked") || !$('#ckTier3').is(":checked") || !$('#ckTier4').is(":checked"))
				$('#ckTierAll').prop('checked', false);
			doFilter();
		});

		$('#tiers').on('change', function() {
			if (!$('#ckTier1').is(":checked") || !$('#ckTier2').is(":checked") || !$('#ckTier3').is(":checked") || !$('#ckTier4').is(":checked")) {
				$('#btnTierAll').removeClass('active');
				$('#ckTierAll').prop('checked', false);
			}
			doFilter();
		});

		$('#tier_all').on('change', function() {	
	       	if ($('#ckTierAll').is(":checked")) {
	       		$('.tier_filter').addClass('active');
	       		$('.ckTier').prop('checked', true);		        		
	       	}
			doFilter();
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


		$('body').on('change', 'input#data_column', function() {             
			tbl.column($(this).attr("value")).visible($(this).is(":checked"));			
		});

		$.fn.dataTableExt.afnFiltering.push( function( oSettings, aData, iDataIndex ) {
			//return true;
			var tier1_idx = 5;
			var inframe_idx = 9;
			var left_chr_idx = 0;
			var right_chr_idx = 2;
			//if ($('#ckTierAll').is(":checked"))
			//	return true;
			var has_tier1 = false;
			var has_tier2 = false;
			var has_tier3 = false;
			var has_tier4 = false;
			var has_null = false;
			if ($('#ckTier1').is(":checked") && aData[tier1_idx] != "")
				has_tier1 = true;
			if ($('#ckTier2').is(":checked") && aData[tier1_idx + 1] != "")
				has_tier2 = true;
			if ($('#ckTier3').is(":checked") && aData[tier1_idx + 2] != "")
				has_tier3 = true;
			if ($('#ckTier4').is(":checked") && aData[tier1_idx + 3] != "")
				has_tier4 = true;
			if ($('#ckTierAll').is(":checked") && aData[tier1_idx + 4] != "")
				has_null = true;
			if (!has_tier1 && !has_tier2 && !has_tier3 && !has_tier4 && !has_null)
				return false;
			var type_idx_offset = parseInt($('#selTypes').val());
			if (type_idx_offset != -1) {
				var type_idx = inframe_idx + type_idx_offset;
				if (aData[type_idx] =="")
					return false;
			}
			if ($('#ckInterChr').is(":checked") && aData[left_chr_idx]==aData[right_chr_idx])
				return false;
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
				
		$('#btnClearFilter').on('click', function() {
			showAll();				
		});			

	});	

	function showAll() {
		$('#btnTierAll').addClass('active');
		$('#ckTierAll').prop('checked', true);
		$('.tier_filter').addClass('active');
		$('.mut').removeClass('active');
		$('.ckTier').prop('checked', true);
		$('#selTypes').val("-1");
		$('#ckInterChr').prop('checked', false);
		tbl.search('');
		onco_filter.clearFilter();
	}

	function getFirstProperty(obj) {
		for (key in obj) {
			if (obj.hasOwnProperty(key))
				return key;
		}
	}
	function doFilter() {
		tbl.draw();
		$('#lblCountDisplay').text(tbl.page.info().recordsDisplay);
    	$('#lblCountTotal').text(tbl.page.info().recordsTotal);
    	uploadSetting();
	}

	function uploadSetting() {
		var setting = {
						'tier1' : $('#ckTier1').is(":checked"), 
						'tier2' : $('#ckTier2').is(":checked"), 
						'tier3' : $('#ckTier3').is(":checked"),
						'tier4' : $('#ckTier4').is(":checked"),
						'tier_all' : $('#ckTierAll').is(":checked"),
						'inframe' : $('#ckInFrame').is(":checked"),
						'inter_chr' : $('#ckInterChr').is(":checked"),
						'filters' : JSON.stringify(filter_settings)
					};		
		var url = '{{url("/saveSetting")}}' + '/page.fusion';
		$.ajax({ url: url, async: true, type: 'POST', dataType: 'text', data: setting, success: function(data) {
			}, error: function(xhr, textStatus, errorThrown){
					console.log('save failed! Reason:' + JSON.stringify(xhr) + ' ' + errorThrown);
				}
		});	

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
<div class="easyui-panel" style="padding:10px;height:100%;width:100%">
	<div id='loadingFusion' class='loading_img' style="height:90%">
				<img src='{{url('/images/ajax-loader.gif')}}'></img>
	</div>
	<div id="var_layout" class="easyui-layout" data-options="fit:true" style="display:none;height:100%">
		<table style='width:99%;'>
									<tr>
									<td colspan="2">
										<span id='filter' style='display: inline;height:200px;width:80%'>
											<button id="btnAddFilter" class="btn btn-primary">Add filter</button>&nbsp;<a id="fb_filter_definition" href="#filter_definition" title="Filter definitions" class="fancybox mytooltip"><img src={{url("images/help.png")}}></img></a>&nbsp;
											<span style="font-family: monospace; font-size: 20;float:right;">
												Fusion:&nbsp;<span id="lblCountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="lblCountTotal" style="text-align:left;" text=""></span>
											</span>
										</span>
										<button id="btnClearFilter" type="button" class="btn btn-info" style="font-size: 12px;">Show all</button>
									</td>
									</tr>
									<tr>
									<td colspan="2">
										<div style="height:20px;"><HR></div>
									</td>
									</tr>
									<tr>
										<td>
										<img class="mytooltip" src={{url("images/help.png")}}></img>Types: 
											<select id="selTypes">
												<option value="-1" selected>All</option>
												<option value="0">In-frame</option>												
												<option value="1">Right gene intact</option>												
												<option value="2">Out-of-frame</option>
												<option value="3">No protein</option>
											</select>
										<span class="btn-group" id="interchr" data-toggle="buttons">
			  								<label class="mut btn btn-default">
												<input class="ck" id="ckInterChr" type="checkbox" autocomplete="off">Inter-chromosomal
											</label>
										</span>	
										<a target=_blank href="{{url("data/".Config::get('onco.classification_fusion'))}}" title="Tier definitions" class="mytooltip"><img src={{url("images/help.png")}}></img></a>
										<!--a id="fb_tier_definition" href="{{url("data/".Config::get('onco.classification_fusion'))}}" title="Tier definitions" class="fancybox mytooltip"><img src={{url("images/help.png")}}></img></a-->
										<span class="btn-group" id="tiers" data-toggle="buttons">
					  						<label class="btn btn-default {{($setting->tier1 == "true")?"active":""}} tier_filter">
												<input id="ckTier1" class="ckTier" type="checkbox" {{($setting->tier1 == "true")?"checked":""}} autocomplete="off">Tier 1
											</label>
											<label class="btn btn-default {{($setting->tier2 == "true")?"active":""}} tier_filter">
												<input id="ckTier2" class="ckTier" type="checkbox" {{($setting->tier2 == "true")?"checked":""}} autocomplete="off">Tier 2
											</label>
											<label class="btn btn-default {{($setting->tier3 == "true")?"active":""}} tier_filter">
												<input id="ckTier3" class="ckTier" type="checkbox" {{($setting->tier2 == "true")?"checked":""}} autocomplete="off">Tier 3
											</label>
											<label class="btn btn-default {{($setting->tier4 == "true")?"active":""}} tier_filter">
												<input id="ckTier4" class="ckTier" type="checkbox" {{($setting->tier4 == "true")?"checked":""}} autocomplete="off">Tier 4
											</label>
										</span>
										<span class="btn-group" id="tier_all" data-toggle="buttons">
											<label id="btnTierAll" class="btn btn-default"}}>
												<input id="ckTierAll" type="checkbox" autocomplete="off">All
											</label>
										</span>
										@if (!Config::get('site.isPublicSite'))
										<span>
											Minimum number of patients: {{Config::get('onco.minPatients')}}				
										</span>										
										@endif
								</td></tr></table>
			
		<div style='padding:10px;width:100%;height:90%;overflow:auto;'>
				<table cellpadding="10" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblFusion" style='width:100%;overflow:auto;'>
				</table> 
		</div>		
	</div>
</span>