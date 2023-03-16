@extends('layouts.default')
@section('content')

{!! HTML::style('css/style.css') !!}
{!! HTML::style('css/style_datatable.css') !!}
{!! HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') !!}
{!! HTML::style('packages/jquery-easyui/themes/bootstrap/easyui.css') !!}
{!! HTML::style('css/heatmap.css') !!}
{!! HTML::style('css/bootstrap.min.css') !!}
{!! HTML::style('css/sb-admin.css') !!}
{!! HTML::style('css/font-awesome.min.css') !!}
{!! HTML::style('packages/w2ui/w2ui-1.4.min.css') !!}
{!! HTML::style('css/light-bootstrap-dashboard.css') !!}

{!! HTML::script('packages/DataTables/datatables.min.js') !!}
{!! HTML::script('packages/jquery-easyui/jquery.easyui.min.js') !!}
{!! HTML::script('packages/yadcf-0.8.8/jquery.dataTables.yadcf.js')!!}
{!! HTML::script('js/onco.js') !!}
{!! HTML::script('packages/highchart/js/highcharts.js')!!}
{!! HTML::script('packages/highchart/js/highcharts-more.js')!!}
{!! HTML::script('packages/highcharts-regression/highcharts-regression.js')!!}
{!! HTML::script('packages/highchart/js/modules/exporting.js')!!}
{!! HTML::script('packages/w2ui/w2ui-1.4.min.js')!!}
{!! HTML::script('js/bootstrap.min.js') !!}

<style>

.btn-default:focus,
.btn-default:active,
.btn-default.active {
    background-color: DarkCyan;
    border-color: #000000;
    color: #fff;
}
.popover-content {
    height: 500px;
    overflow-y: auto;
    white-space:pre-wrap;
}
</style>

<script type="text/javascript">
	var group_plot = null;
	var detail_plot = null;
	var combine_plot = null;
	var ttest_plot = null;
	var corr_plot = null;
	var survival_plot = null;
	var heatmap = null;
	var ttest_data = null;
	var survival_data = null;
	var exp_data = null;
	var plot_data = null;
	var combine_plot_data = null;
	var data = null;
	var levels = [];
	var target_type = "refseq";
	var library_type = "all";
	var tbl_corr = null;
	var corr_data = null;
	var corr_gene1 = null;
	var corr_gene2 = null;
	var tab_urls = [];
	var loaded_list = [];
	var pvalue_data;
	var selected_pvalue = 0;
	var tiers = [];
	var fusion_tiers = [];
	var tbl;
	var summary_exp_data = null;
	var col_html = '';
	var exp_checked_cat = [];
	var select_all = true;

	$(document).ready(function() {

		refreshTiers();
		refreshFusionTiers();
		getVarSummaryData();
		getCNVSummaryData();
		getFusionSummaryData();
		getExpSummaryData();

		$('.summary_filter').on('change', function() {
			refreshTiers();
			if (!$('#ckTier1').is(":checked") || !$('#ckTier2').is(":checked") || !$('#ckTier3').is(":checked") || !$('#ckTier4').is(":checked") || !$('#ckNoTier').is(":checked")) {
				$('#btnTierAll').removeClass('active');
				$('#ckTierAll').prop('checked', false);
			}
			getVarSummaryData();	
		});

		$('.summary_cnv_filter').on('change', function() {			
			getCNVSummaryData();	
		});

		$('.summary_exp_filter').on('change', function() {			
			getExpSummaryData();	
		});

		$('.summary_exp_refresh').on('change', function() {			
			showGroupExpData();	
		});

		$('.summary_fusion_filter').on('change', function() {
			refreshFusionTiers();
			if (!$('#ckFusionTier1').is(":checked") || !$('#ckFusionTier2').is(":checked") || !$('#ckFusionTier3').is(":checked") || !$('#ckFusionTier4').is(":checked") || !$('#ckFusionNoTier').is(":checked")) {
				$('#btnFusionTierAll').removeClass('active');
				$('#ckFusionTierAll').prop('checked', false);
			}
			getFusionSummaryData();	
		});
		
		$('#tier_all').on('change', function() {	
	       	if ($('#ckTierAll').is(":checked")) {
	       		$('.tier_filter').addClass('active');
	       		$('.ckTier').prop('checked', true);		        		
	       	}
			refreshTiers();
			getVarSummaryData();
        });

        $('#fusion_tier_all').on('change', function() {	
	       	if ($('#ckFusionTierAll').is(":checked")) {
	       		$('.fusion_tier_filter').addClass('active');
	       		$('.ckFusionTier').prop('checked', true);		        		
	       	}
			refreshTiers();
			getFusionSummaryData();
        });		

		var first_tab = null;
		@foreach ( $var_types as $type )
			url = '{!!url("/viewVarAnnotationByGene/any/$gene_id/$type")!!}';
			console.log(url);
			var tab_id = '{!!Lang::get("messages.$type")!!}';
			tab_urls[tab_id] = url;				
			if (first_tab == null)
				first_tab = tab_id;			
		@endforeach		

		url = '{!!url("/viewCNVByGene/any/$gene_id")!!}';
		tab_urls['CNV-Details'] = url;

		url = '{!!url("/viewFusionGenes/any/$gene_id")!!}';
		tab_urls['Fusion-Details'] = url;		

		$('#gene_id').keyup(function(e){
			if(e.keyCode == 13) {
        		$('#btnGene').trigger("click");
    		}
		});

		$('#btnGene').on('click', function() {
			window.location.replace("{!!url('/viewGeneDetail')!!}" + '/' + $('#gene_id').val().toUpperCase());
        });		

		$('#gene_id').focus();

		var general_data = {!!json_encode($general_data);!!};
		var general_header = {!!json_encode($general_header);!!};
		$('#tbl_general').html( '<table cellpadding="0" cellspacing="0" border="1" class="pretty" style="width:100%;" word-wrap="break-word" id="tbl_general_table"></table>');
		$('#tbl_general_table').DataTable( 
			{
				"data":        general_data,
				"columns":     general_header,
				"ordering":    true,
				"paging":   false,
				"dom":         'T<"clear">lfrtip',                 
			} 
		);

		<?php $tab_id=0; ?>
		@foreach ($anno_header as $key => $header)
			var content = '<div id="tbl_{!!$tab_id!!}"></div>';
			var table_data_{!!$tab_id!!} = {!!$anno_data[$key]!!};
			var header_{!!$tab_id!!} = {!!$header!!};            
			$('#tbl_{!!$tab_id!!}').html( '<div align="center" width="80%"><table cellpadding="0" cellspacing="0" border="0" class="pretty" width="100%" word-wrap="break-word" id="tbl_{!!$tab_id!!}_data_table"></table></div>');
			$('#tbl_{!!$tab_id!!}_data_table').DataTable( 
				{
					"data":        table_data_{!!$tab_id!!},
					"columns":     header_{!!$tab_id!!},
					"ordering":    true,
					"paging":   false,
					"dom":         'T<"clear">lfrtip',
				} 
			);
			<?php $tab_id++; ?>
		@endforeach

		$('.easyui-tabs').tabs({
			onSelect:function(title) {				
				if (title == "Mutations")
					tab = $('#tab' + title).tabs('getSelected');
				else
					tab = $(this).tabs('getSelected');				
				var id = tab.panel('options').id;				
				showFrameHtml(id);	
		   }
		});
		
		$('#tabDetails').tabs('select', 0);

		$('body').on('change', 'input#data_column', function() { 
			var cat = $(this).val();
			if ($(this).is(":checked"))
				exp_checked_cat.push(cat);
			else {
				var idx = exp_checked_cat.indexOf(cat);
				if ( idx != -1) {
					exp_checked_cat.splice(idx, 1);
				}
			}			
		});

		$('#popover').on('hidden.bs.popover', function() {
			//$('[data-toggle="popover"]').popover('hide');
			showGroupExpData();
		});

		$('body').on('click', 'a#expClose', function() {
			$('[data-toggle="popover"]').popover('hide');		
		});

		$('body').on('click', 'button#btnSelectAll', function() {
			$('input#data_column').prop('checked', select_all);
			if (select_all) {
				for(var category in summary_exp_data)
					exp_checked_cat.push(category);					
			}
			else
				exp_checked_cat = [];
			$('input#data_column').trigger('change');
			select_all = !select_all;
		});


	});

	function refreshFusionTiers() {
		fusion_tiers = [];
		if ($('#ckFusionTier1').is(":checked")) {
				$('#btnFusionTier1').addClass('active');
				$('#ckFusionTier1').prop('checked', true);
				fusion_tiers.push('Tier1');
		}else {
				$('#btnFusionTier1').removeClass('active');
				$('#ckFusionTier1').prop('checked', false);	
		}
		if ($('#ckFusionTier2').is(":checked")) {
				$('#btnFusionTier2').addClass('active');
				$('#ckFusionTier2').prop('checked', true);
				fusion_tiers.push('Tier2');
		}else {
				$('#btnFusionTier2').removeClass('active');
				$('#ckFusionTier2').prop('checked', false);	
		}
		if ($('#ckFusionTier3').is(":checked")) {
				$('#btnFusionTier3').addClass('active');
				$('#ckFusionTier3').prop('checked', true);
				fusion_tiers.push('Tier3');
		}else {
				$('#btnFusionTier3').removeClass('active');
				$('#ckFusionTier3').prop('checked', false);	
		}
		if ($('#ckFusionTier4').is(":checked")) {
				$('#btnFusionTier4').addClass('active');
				$('#ckFusionTier4').prop('checked', true);
				fusion_tiers.push('Tier4');
		}else {
				$('#btnFusionTier4').removeClass('active');
				$('#ckFusionTier4').prop('checked', false);	
		}
		if ($('#ckFusionNoTier').is(":checked")) {
				$('#btnFusionNoTier').addClass('active');
				$('#ckFusionNoTier').prop('checked', true);
				fusion_tiers.push('NoTier');
		}else {
				$('#btnFusionNoTier').removeClass('active');
				$('#ckFusionNoTier').prop('checked', false);	
		}
	}

	function refreshTiers() {
		tiers = [];
		if ($('#ckTier1').is(":checked")) {
				$('#btnTier1').addClass('active');
				$('#ckTier1').prop('checked', true);
				tiers.push('Tier 1');
		}else {
				$('#btnTier1').removeClass('active');
				$('#ckTier1').prop('checked', false);	
		}
		if ($('#ckTier2').is(":checked")) {
				$('#btnTier2').addClass('active');
				$('#ckTier2').prop('checked', true);
				tiers.push('Tier 2');
		}else {
				$('#btnTier2').removeClass('active');
				$('#ckTier2').prop('checked', false);	
		}
		if ($('#ckTier3').is(":checked")) {
				$('#btnTier3').addClass('active');
				$('#ckTier3').prop('checked', true);
				tiers.push('Tier 3');
		}else {
				$('#btnTier3').removeClass('active');
				$('#ckTier3').prop('checked', false);	
		}
		if ($('#ckTier4').is(":checked")) {
				$('#btnTier4').addClass('active');
				$('#ckTier4').prop('checked', true);
				tiers.push('Tier 4');
		}else {
				$('#btnTier4').removeClass('active');
				$('#ckTier4').prop('checked', false);	
		}
		if ($('#ckNoTier').is(":checked")) {
				$('#btnNoTier').addClass('active');
				$('#ckNoTier').prop('checked', true);
				tiers.push('NoTier');
		}else {
				$('#btnNoTier').removeClass('active');
				$('#ckNoTier').prop('checked', false);	
		}
	}

	function addTab(title, url){
		var type='add';		
		//var content = '<iframe scrolling="auto" frameborder="0"  src="'+url+'" style="width:100%;height:95%;overflow:none"></iframe>';
		var content = '<iframe scrolling="auto" frameborder="0"  src="'+url+'" style="width:100%;height:100%;overflow:auto"></iframe>';
			$('#tabDetails').tabs(type, {
					title:title,
					content:content,
					closable:false,
			});
	}

	function showFrameHtml(id) {
		if (loaded_list.indexOf(id) == -1) {
			var url = tab_urls[id];
			if (url != undefined) {
				var html = '<iframe scrolling="auto" frameborder="0"  src="' + url + '" style="width:100%;height:85%;overflow:auto;border-width:0px"></iframe>';
				$('#' + id).html(html);
				console.log('#' + id);
				console.log(html);
				loaded_list.push(id);
			}
		}
	}

	function getExpSummaryData() {
		var cat = $("#selExpSummaryCat").val();
		var tissue = $("#selExpSummaryTissue").val();
		var library_type = $("#selExpLibType").val();
		var target_type = $("#selExpTargetType").val();		
		var url = '{!!url("/getExpGeneSummary/$gene_id")!!}' + '/' + cat + '/' +tissue +'/' + target_type + '/' + library_type;
		console.log(url);
		//return;
		$("#loadingExp").css("display","inline");
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				summary_exp_data = JSON.parse(data);
				exp_checked_cat = [];
				var top_n = 30;
				var current_top = 1;
				for(var category in summary_exp_data) {
//					if(tissue=="normal"&&category=="Normal")
//						exp_checked_cat.push(category);
					
//					else if(tissue=="tumor"&&category!="Normal")
//						exp_checked_cat.push(category);

//					else if(tissue=="all")
					if (current_top <= top_n)
						exp_checked_cat.push(category);
					current_top++;

				}
				showGroupExpData();
				$("#loadingExp").css("display","none");
			}			
		});
	}

	function showGroupExpData() {
		var cat = $("#selExpSummaryCat").val();
		var library_type = $("#selExpLibType").val();
		var target_type = $("#selExpTargetType").val();
		var logged = $('#ckLog').is(":checked");
		var median_centered = $('#ckMedian').is(":checked");

		if (summary_exp_data != null) {
			if (summary_exp_data.length == 0) {
				$("#exp_not_found").css("display","block");
				$("#exp_plot").css("display","none");					
			}
			else {
				$("#exp_not_found").css("display","none");
				$("#exp_plot").css("display","block");
				$("#popover").html("Select " + $("#selExpSummaryCat").val());
				var categories = [];
				var cat_medians = [];
				var total_samples = 0;
				var median = 0;
				if (median_centered) {
					var values = [];
					for(var category in summary_exp_data) {						
						summary_exp_data[category].forEach(function(d){
							v = d[2];
							if (logged)
                    			v = Math.log2(v+1);                
                			values.push(v);
                		});
                	}             
                	median = getPercentile(values, 50);
				}					
				var cats = [];
				for(var category in summary_exp_data) {
					cats.push(category);
					if (exp_checked_cat.indexOf(category) == -1)
						continue;
					var names = [];
					var values = [];
					summary_exp_data[category].forEach(function(d){
							total_samples++;
							v = d[2];
							if (logged)
                    			v = Math.log2(v+1);                
                			if (median_centered)
                				v = v - median;
                			values.push(v);
							names.push({patient_id:d[0], sample_name:d[1]});
						})
					var sorted_data = sortByArray(values, names);
						//console.log(category);
					categories.push({category: category, data: sorted_data});
					cat_medians.push(getPercentile(values, 50));
					
						
				}
				col_html = '<button class="btn btn-default" id="btnSelectAll">Select/Unselect All</button>&nbsp;<br>';
				//col_html = '';
				
				cats.sort().forEach(function (c){
					var checked = (exp_checked_cat.indexOf(c) == -1)? '' : 'checked';
					col_html += '<input type=checkbox ' + checked + ' class="onco_checkbox" id="data_column" value="' + c + '"><font size=2>&nbsp;' + c + '</font></input><BR>';
				})
				

				var plot_width = total_samples*1.5;
				if (plot_width < 800)
					plot_width = 800;
				$("#exp_main").css("width", plot_width + 100 + 'px');
				$("#exp_plot").css("width",plot_width + 'px');
				var sorted = sortByArray(cat_medians, categories);
				var y_label = (logged)? "log2 (TPM + 1)" : "TPM";
				//console.log(JSON.stringify(sorted));
				drawGroupScatterPlot('exp_plot', "EXPRESSION - " + target_type.toUpperCase() + " - " + library_type.toUpperCase() + ' (' + total_samples + ' Samples)', sorted, capitalize(cat), y_label, function(p) {
								//console.log(p);
								var url = '{!!url("/viewPatient/any")!!}' + '/' + p.patient_id;
								window.open(url, '_blank');							
						});
				
				$('[data-toggle="popover"]').popover({
					title: 'Select <a href="#" id="expClose" class="close" data-dismiss="alert">×</a>',
					placement : 'bottom',  
					html : true,
					content : function() {
						return col_html;
					}
				});									
			}
		}	
	}

	function getFusionSummaryData() {
		var value_type = $("#selFusionSummaryValue").val();
		var cat = $("#selFusionSummaryCat").val();
		var min_pat = $("#txtFusionMinPatients").val();
		var fusion_type = $("#selFusionType").val();
		if (!isInt(min_pat)) {			
			min_pat = "1";
			$("#txtFusionMinPatients").val(min_pat);
		} 
		min_pat = parseInt(min_pat);
		if (min_pat < 1) {
			min_pat = 1;
			$("#txtFusionMinPatients").val(min_pat);
		}
		var tier_str = fusion_tiers.join();
		var url = '{!!url("/getFusionGeneSummary/$gene_id")!!}' + '/' + value_type + '/' + cat + '/' + min_pat + '/' + fusion_type + '/' + tier_str;
		console.log(url);		
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				summary_var_data = JSON.parse(data);
				if (summary_var_data.length == 0) {
					$("#fusion_not_found").css("display","block");
					$("#fusion_plot").css("display","none");
					$("#fusion_pair_plot").css("display","none");
				}
				else {
					$("#fusion_not_found").css("display","none");
					$("#fusion_plot").css("display","block");
					$("#fusion_pair_plot").css("display","block");					
					var y_label = (value_type == "frequency")? "Patietns(%)" : "Patient count";
					drawStackPlot("fusion_plot", "Fusion", summary_var_data.category, summary_var_data.series, true, cat, y_label, function(p) {
								//console.log(p);
								var url = '{!!url("/getPatientsByFusionGene/$gene_id")!!}' + '/' + cat + '/' + p.category + '/' + fusion_type + '/' + tier_str;
								$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {										
										console.log(url);
										var patient_list = JSON.parse(data);
										showTable(patient_list);										
									}
								});								
							});
				}
				
			}			
		});
		var url = '{!!url("/getFusionGenePairSummary/$gene_id")!!}' + '/' + value_type + '/' + cat + '/' + min_pat + '/' + fusion_type + '/' + tier_str;
		console.log(url);
		//return;
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				summary_var_data = JSON.parse(data);
				var y_label = (value_type == "frequency")? "Count" : "Patient count";
				drawStackPlot("fusion_pair_plot", "Fusion Pairs", summary_var_data.category, summary_var_data.series, true, cat, y_label, function(p) {
								console.log(p);
								var genes = p.category.split("->");
								var left_gene = genes[0];
								var right_gene = genes[1];
								var url = '{!!url("/getPatientsByFusionPair")!!}' + '/' + left_gene + '/' + right_gene + '/' + fusion_type + '/' + tier_str;
								$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {										
										console.log(url);
										var patient_list = JSON.parse(data);
										showTable(patient_list);										
									}
								});								
							});						
			}			
		});
	}

	function getCNVSummaryData() {
		var value_type = $("#selCNVSummaryValue").val();
		var cat = $("#selCNVSummaryCat").val();
		var min_pat = $("#txtCNVMinPatients").val();
		var min_amplified = $("#selCNVMinAmplified").val();
		var max_deleted = $("#selCNVMaxDeleted").val();
		if (!isInt(min_pat)) {			
			min_pat = "1";
			$("#txtCNVMinPatients").val(min_pat);
		} 
		min_pat = parseInt(min_pat);
		if (min_pat < 1) {
			min_pat = 1;
			$("#txtCNVMinPatients").val(min_pat);
		}
		var url = '{!!url("/getCNVGeneSummary/$gene_id")!!}' + '/' + value_type + '/' + cat + '/' + min_pat + '/' + min_amplified + '/' + max_deleted;
		console.log(url);		
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				summary_var_data = JSON.parse(data);
				if (summary_var_data.length == 0) {
					$("#cnv_not_found").css("display","block");
					$("#cnv_plot").css("display","none");					
				}
				else {
					$("#cnv_not_found").css("display","none");
					$("#cnv_plot").css("display","block");
					var y_label = (value_type == "frequency")? "Patietns(%)" : "Patient count";
					drawStackPlot("cnv_plot", "CNV", summary_var_data.category, summary_var_data.series, true, cat, y_label, function(p, t) {
								console.log(p.category);
								var url = '{!!url("/getPatientsByCNVGene/$gene_id")!!}' + '/' + cat + '/' + p.category + '/' + min_amplified + '/' + max_deleted;
								console.log(url);
								$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {																				
										var patient_list = JSON.parse(data);
										cols = [{title:'Patient ID'},{title:'Diagnosis'},{title:'Type'},{title:'Projects'}]
										showTable(patient_list, cols);
									}
								});
					});																
				}
				
			}			
		});
	}

	function getVarSummaryData() {
		var value_type = $("#selSummaryValue").val();
		var cat = $("#selSummaryCat").val();
		var min_pat = $("#txtMinPatients").val();
		if (!isInt(min_pat)) {			
			min_pat = "1";
			$("#txtMinPatients").val(min_pat);
		} 
		min_pat = parseInt(min_pat);
		if (min_pat < 1) {
			min_pat = 1;
			$("#txtMinPatients").val(min_pat);
		}
		console.log(JSON.stringify(tiers));
		var tier_str = tiers.join();
		var url = '{!!url("/getVarGeneSummary/$gene_id")!!}' + '/' + value_type + '/' + cat + '/' + min_pat + '/' + tier_str;
		console.log(url);		
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				summary_var_data = JSON.parse(data);
				if (summary_var_data.length == 0) {
					$("#var_not_found").css("display","block");										
				}
				else {
					$("#var_not_found").css("display","none");
					$("#germline").css("display","block");
					$("#somatic").css("display","block");
				}
				if (!summary_var_data.hasOwnProperty("germline"))
					$("#germline").css("display","none");
				if (!summary_var_data.hasOwnProperty("somatic"))
					$("#somatic").css("display","none");
				var y_label = (value_type == "frequency")? "Patietns(%)" : "Patient count";
				for(var type in summary_var_data) {
					var v = type;
					console.log(v);					
					drawStackPlot(type, capitalize(type), summary_var_data[type].category, summary_var_data[type].series, true, cat, y_label, function(p, t) {
								console.log(p.category);
								var url = '{!!url("/getPatientsByVarGene/$gene_id")!!}' + '/' + t.toLowerCase() + '/' + cat + '/' + p.category + '/' + tier_str;
								console.log(url);
								$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {																				
										var patient_list = JSON.parse(data);
										showTable(patient_list);
									}
								});
							});											
				}
			}			
		});
	}

	function showTable(patient_list, cols=[{title:'Patient ID'},{title:'Diagnosis'},{title:'Projects'}]) {
		tbl_id = 'tblSelPatients';
		patient_list.forEach(function(d) {
			d[0] = "<a target=_blank href='" + "{!!url("/viewPatient")!!}" + "/any/" + d[0] + "'>" + d[0] + "</a>"; 
		})
		$('#selected_patients').w2popup();
		$('#w2ui-popup #lblTotalPatients').text(patient_list.length + ' patients');
		if (tbl != null) {
			tbl.destroy();
			$('#w2ui-popup #' + tbl_id).empty();
		}

		tbl = $('#w2ui-popup #' + tbl_id).DataTable( 
				{				
					"paging":   true,
					"ordering": true,
					"info":     true,
					"dom": 'lfrtip',
					"data": patient_list,
					"columns": cols,
					"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
					"pageLength":  15,
					"pagingType":  "simple_numbers",									
				} );		
	}

   </script>

<div id="selected_patients" style="display: none; width: 80%; height: 80%; overflow: auto; background-color=white;">	
	<div rel="title">
        <lable id="lblTotalPatients" style="color: red;"></lable>
    </div>
    <div rel="body" style="text-align:left;">    	 
    	<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblSelPatients" style='width:100%'>
		</table>		
	</div>
</div>

<div id="out_container" style="width:100%;padding:5px;height:100%;overflow:hidden;"> 
	<font size=2>
		<div style="padding-top:0px;padding-left:15px">
			<ol class="breadcrumb" style="margin-bottom:0px;padding:4px 20px 0px 0px;background-color:#ffffff">				
				<span style="float:right;">
					<img width="20" height="20" src="{!!url('images/search-icon.png')!!}"></img> <span style="font-size:18">Gene: <input id='gene_id' class="form-control" type='text' value='{!!$gene_id!!}' style="width:150px;display:inline"/>&nbsp;&nbsp;<button id='btnGene' class="btn btn-info">GO</button></span>
				</span>
			</ol>
		</div>
	</font>	
	<div id="tabDetails" class="easyui-tabs" data-options="tabPosition:top,fit:true,plain:true,pill:false" style="height:100%;width:100%;padding:10px;overflow:visible;">
		<div id="Mutations" title="Mutations" style="height:90%;width:100%;padding:10px;">
			<div id="tabMutations" class="easyui-tabs" data-options="tabPosition:top,plain:true,pill:false" style="width:98%;padding:0px;overflow:visible;border-width:0px">
				<div id="Summary" title="Summary" style="width:98%;padding:0px;">
						<div class="pane-content" style="text-align: center; padding: 15px 15px 15px 15px; background:rgba(203, 203, 210, 0.15);">
							<div id="main" class="container-fluid" style="padding:10px" >
								<div class="row">
									<div class="card">
				                		<div class="row">
											<div class="col-md-12 text-left">
												<span style="font-size:16">
													Value&nbsp;:&nbsp;<select id="selSummaryValue" class="form-control summary_filter" style="width:100px;display:inline"><option value="count">Count</option><option value="frequency">Frequency</option></select>
													&nbsp;Category&nbsp;:&nbsp;<select id="selSummaryCat" class="form-control summary_filter" style="width:100px;display:inline"><option value="diagnosis">Diagnosis</option><option value="project">Project</option></select>
													&nbsp;Min Patients&nbsp;:&nbsp;<input id="txtMinPatients" class="form-control summary_filter" style="width:50px;display:inline" value="2"></input>
													<span class="btn-group summary_filter" id="tiers" data-toggle="buttons">
								  						<label id="btnTier1" class="btn btn-default tier_filter active">
															<input id="ckTier1" class="ckTier" type="checkbox" checked autocomplete="off">Tier 1
														</label>
														<label id="btnTier2" class="btn btn-default tier_filter active">
															<input id="ckTier2" class="ckTier" type="checkbox" checked autocomplete="off">Tier 2
														</label>
														<label id="btnTier3" class="btn btn-default tier_filter" >
															<input id="ckTier3" class="ckTier" type="checkbox" checked autocomplete="off">Tier 3
														</label>
														<label id="btnTier4" class="btn btn-default tier_filter active">
															<input id="ckTier4" class="ckTier" type="checkbox" checked autocomplete="off">Tier 4
														</label>
														<label id="btnNoTier" class="btn btn-default tier_filter active">
															<input id="ckNoTier" class="ckTier" type="checkbox" autocomplete="off">No Tier
														</label>
													</span>
													<span class="btn-group" id="tier_all" data-toggle="buttons">
														<label id="btnTierAll" class="btn btn-default">
															<input id="ckTierAll" type="checkbox" autocomplete="off">All
														</label>
													</span>
												</span>
											</div>										
										</div>
									</div>
								</div>
								<br>
								<div class="row">
									<div id="var_not_found" style="display:none"><H3>No mutations found!</H3></div>
									<div class="card">
				                		<div class="row">
											<div class="col-md-12">
												<div id="germline" style="min-width: 310px; height: 400px; margin: 0 auto"></div>
											</div>										
										</div>
									</div>
								</div>
								<br>
								<div class="row">
									<div class="card">
				                		<div class="row">
											<div class="col-md-12">
												<div id="somatic" style="min-width: 310px; height: 400px; margin: 0 auto"></div>
											</div>										
										</div>
									</div>
								</div>
							</div>
						</div>
					
				</div>
				@foreach ( $var_types as $type)
					@if ($type != "hotspot")					
					<div id="{!!Lang::get("messages.$type")!!}" title="{!!Lang::get("messages.$type")!!}" data-options="tools:'#{!!$type!!}_mutation_help'" style="width:98%;padding:0px;">
					</div>
					@endif	
				@endforeach
			</div>
		</div>
		<div id="CNV" title="CNV" style="height:90%;width:100%;padding:10px;">
			<div id="tabCNV" class="easyui-tabs" data-options="tabPosition:top,plain:true,pill:false" style="width:98%;padding:0px;overflow:visible;border-width:0px">
				<div id="Summary" title="Summary" style="width:98%;padding:0px;">
						<div class="pane-content" style="text-align: center; padding: 15px 15px 15px 15px; background:rgba(203, 203, 210, 0.15);">
							<div id="main" class="container-fluid" style="padding:10px" >
								<div class="row">									
									<div class="card">
				                		<div class="row">
											<div class="col-md-12 text-left">
												<span style="font-size:16">
													Value&nbsp;:&nbsp;<select id="selCNVSummaryValue" class="form-control summary_cnv_filter" style="width:100px;display:inline"><option value="count">Count</option><option value="frequency">Frequency</option></select>
													&nbsp;Category&nbsp;:&nbsp;<select id="selCNVSummaryCat" class="form-control summary_cnv_filter" style="width:100px;display:inline"><option value="diagnosis">Diagnosis</option><option value="project">Project</option></select>
													&nbsp;Min Patients&nbsp;:&nbsp;<input id="txtCNVMinPatients" class="form-control summary_cnv_filter" style="width:50px;display:inline" value="1"></input>
													&nbsp;Min Amplified Copy Number&nbsp;:&nbsp;
													<select id="selCNVMinAmplified" class="form-control summary_cnv_filter" style="width:60px;display:inline">
														<option value="3" selected>3</option>
														<option value="4">4</option>
														<option value="5">5</option>
														<option value="6">6</option>
													</select>
													&nbsp;Max Deleted Copy Number&nbsp;:&nbsp;
													<select id="selCNVMaxDeleted" class="form-control summary_cnv_filter" style="width:60px;display:inline">
														<option value="0">0</option>
														<option value="1" selected>1</option>														
													</select>													
												</span>
											</div>										
										</div>
									</div>
								</div>
								<br>
								<div class="row">
									<div class="card">
				                		<div class="row">
				                			<div id="cnv_not_found" style="display:none"><H3>No CNV found!</H3></div>
											<div class="col-md-12">
												<div id="cnv_plot" style="min-width: 310px; width: 95%; height: 500px; margin: 0 auto"></div>
											</div>										
										</div>
									</div>
								</div>								
							</div>
						</div>					
				</div>
				<div id="CNV-Details" title="CNV-Details" style="width:98%;padding:0px;">
				</div>				
			</div>
		</div>	
		<div id="Fusion" title="Fusion" style="height:100%;width:100%;padding:10px;">
			<div id="tabFusion" class="easyui-tabs" data-options="tabPosition:top,plain:true,pill:false" style="width:98%;padding:0px;overflow:visible;border-width:0px">
				<div id="Summary" title="Summary" style="width:98%;padding:0px;">
						<div class="pane-content" style="text-align: center; padding: 5px 5px 5px 5px; background:rgba(203, 203, 210, 0.15);">
							<div id="main" class="container-fluid" style="padding:5px" >
								<div class="row">
									<div class="card">
				                		<div class="row">
											<div class="col-md-12 text-left">
												<span style="font-size:16">
													Value&nbsp;:&nbsp;<select id="selFusionSummaryValue" class="form-control summary_fusion_filter" style="width:100px;display:inline"><option value="count">Count</option><option value="frequency">Frequency</option></select>
													&nbsp;Category&nbsp;:&nbsp;<select id="selFusionSummaryCat" class="form-control summary_fusion_filter" style="width:100px;display:inline"><option value="diagnosis">Diagnosis</option><option value="project">Project</option></select>
													&nbsp;Min Patients&nbsp;:&nbsp;<input id="txtFusionMinPatients" class="form-control summary_fusion_filter" style="width:50px;display:inline" value="3"></input>
													&nbsp;Type&nbsp;:&nbsp;
													<select id="selFusionType" class="form-control summary_fusion_filter" style="width:100px;display:inline">
														<option value="All" selected>All</option>
														<option value="in-frame">In-frame</option>
														<option value="right gene intact">Right gene intact</option>
														<option value="out-of-frame">Out-of-frame</option>														
													</select>&nbsp;&nbsp;
													<span class="btn-group summary_fusion_filter" id="tiers" data-toggle="buttons">
								  						<label id="btnFusionTier1" class="btn btn-default fusion_tier_filter active">
															<input id="ckFusionTier1" class="ckFusionTier" type="checkbox" checked autocomplete="off">Tier 1
														</label>
														<label id="btnFusionTier2" class="btn btn-default fusion_tier_filter active">
															<input id="ckFusionTier2" class="ckFusionTier" type="checkbox" checked autocomplete="off">Tier 2
														</label>
														<label id="btnFusionTier3" class="btn btn-default fusion_tier_filter active">
															<input id="ckFusionTier3" class="ckFusionTier" type="checkbox" checked autocomplete="off">Tier 3
														</label>
														<label id="btnFusionTier4" class="btn btn-default fusion_tier_filter active">
															<input id="ckFusionTier4" class="ckFusionTier" type="checkbox" checked autocomplete="off">Tier 4
														</label>														
													</span>
													<span class="btn-group" id="fusion_tier_all" data-toggle="buttons">
														<label id="btnFusionTierAll" class="btn btn-default">
															<input id="ckFusionTierAll" type="checkbox" autocomplete="off">All
														</label>
													</span>
												</span>
											</div>										
										</div>
									</div>
								</div>
								<br>
								<div class="row">
									<div class="card">
				                		<div class="row">
				                			<div id="fusion_not_found" style="display:none"><H3>No fusions found!</H3></div>
											<div class="col-md-12">
												<div id="fusion_plot" style="min-width: 310px; width: 100%; height: 350px; margin: 0 auto;overflow: auto;"></div>
											</div>										
										</div>
									</div>
								</div>
								<br>
								<div class="row">
									<div class="card">
				                		<div class="row">				                			
											<div class="col-md-12">
												<div id="fusion_pair_plot" style="min-width: 310px; width: 100%; height: 350px; margin: 0 auto;overflow: auto;"></div>
											</div>										
										</div>
									</div>
								</div>								
							</div>
						</div>					
				</div>
				<div id="Fusion-Details" title="Fusion-Details" style="width:98%;padding:0px;">
				</div>				
			</div>
		</div>	
	
		<div id="Expression" title="Expression" style="width:100%;padding:10px;">
						<div class="pane-content" style="overflow: auto;text-align: center; padding: 15px 15px 15px 15px; background:rgba(203, 203, 210, 0.15);">
							<div id="exp_main" class="container-fluid" style="padding:10px;" >
								<div class="row">
									<div class="card">
				                		<div class="row">
											<div class="col-md-12 text-left">
												<span style="font-size:16">													
													<input type="checkbox" id="ckMedian" class="summary_exp_refresh" checked></input>&nbsp;Median centered&nbsp;:&nbsp;
													<input type="checkbox" id="ckLog" class="summary_exp_refresh" checked></input>&nbsp;Log scale&nbsp;:&nbsp;
													&nbsp;Tissue Type&nbsp;:&nbsp;<select id="selExpSummaryTissue" class="form-control summary_exp_filter" style="width:100px;display:inline"><option value="all">All</option><option value="tumor">Tumor</option><option value="normal">Normal</option></select>

													&nbsp;Category&nbsp;:&nbsp;<select id="selExpSummaryCat" class="form-control summary_exp_filter" style="width:100px;display:inline"><option value="diagnosis">Diagnosis</option><option value="project">Project</option></select>
													&nbsp;Type&nbsp;:&nbsp;
													<select id="selExpTargetType" class="form-control summary_exp_filter" style="width:100px;display:inline">
														<option value="ensembl">ENSEMBL</option>														
													</select>&nbsp;&nbsp;
													&nbsp;Library Type&nbsp;:&nbsp;
													<select id="selExpLibType" class="form-control summary_exp_filter" style="width:100px;display:inline">
														<option value="all" selected>All</option>
														<option value="polyA">PolyA</option>
														<option value="nonPolyA">Non-PolyA</option>
													</select>&nbsp;&nbsp;
													<button id="popover" data-toggle="popover" data-placement="bottom" type="button" class="btn btn-default" >Select Columns</button>&nbsp;&nbsp;
													<img id='loadingExp' width=40 height=40 src='{!!url('/images/ajax-loader-sm.gif')!!}'></img>													
												</span>
											</div>										
										</div>									
									</div>
								</div>
								<br>								
								<div class="row">
									<div class="card">
				                		<div class="row">				                			
											<div class="col-md-12">
												<div id="exp_not_found" style="display:none"><H3>No expression data found!</H3></div>
												<div id="exp_plot" style="min-width: 310px; "></div>
											</div>										
										</div>
									</div>
								</div>								
							</div>
						</div>								
		</div>
			
		<div title="Gene Annotation" style="height:inherit">
			<div id="tabAnno" class="easyui-tabs" data-options="tabPosition:top" style="height:600px;width:100%;padding:10px">
				<div title="General Info">
					<div id="tbl_general"></div>
				</div>   
				<?php $tab_id=0; ?>
				@foreach ($anno_header as $key => $header)
				<div title="{!!$key!!}"> 
					<div id="tbl_{!!$tab_id!!}" align="center" style='width:100%'></div>
				</div>
				<?php $tab_id++; ?>
				@endforeach  
			</div>
		</div>

</div>



@stop
