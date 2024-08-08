@extends('layouts.default')
@section('content')

{!! HTML::style('css/style_datatable.css') !!}
{!! HTML::style('packages/jquery-easyui/themes/bootstrap/easyui.css') !!}
{!! HTML::style('css/heatmap.css') !!}
{!! HTML::style('css/light-bootstrap-dashboard.css') !!}
{!! HTML::style('packages/w2ui/w2ui-1.4.min.css') !!}

{!! HTML::script('packages/DataTables/datatables.min.js') !!}
{!! HTML::script('packages/jquery-easyui/jquery.easyui.min.js') !!}
{!! HTML::script('js/onco.js') !!}
{!! HTML::script('js/FileSaver.js') !!}
{!! HTML::script('packages/highchart/js/highcharts.js')!!}
{!! HTML::script('packages/highchart/js/highcharts-more.js')!!}
{!! HTML::script('packages/highcharts-regression/highcharts-regression.js')!!}
{!! HTML::script('packages/highchart/js/modules/exporting.js')!!}
{!! HTML::script('packages/w2ui/w2ui-1.4.min.js')!!}
<style>
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
.form-control {
	font-size: 0.8rem;
}
.label {
 z-index: 1!important;
}

.highcharts-tooltip span {
	font-size: medium;
    background-color:white;
    padding: 10px;
    margin-left: 0px;
    opacity:1;
    z-index:9999!important;
}

</style>
<script type="text/javascript">
	var group_plot = null;
	var detail_plot = null;
	var combine_plot = null;
	var ttest_plot = null;
	var corr_plot = null;
	var heatmap = null;
	var ttest_data = null;
	var exp_data = null;
	var plot_data = null;
	var combine_plot_data = null;
	var data = null;
	var levels = [];
	var target_type = "{!!$project->getTargetType()!!}";
	var library_type = "all";
	var tbl_corr = null;
	var corr_data = null;
	var corr_gene1 = null;
	var corr_gene2 = null;
	var tab_urls = [];
	var loaded_list = [];
	var pvalue_data;
	var selected_pvalue = 0;
	var column_chart = null;
	var patients = [];
	var tbl = null;
	var download_exp_str = "";
	$(document).ready(function() {

	@if ($project->getExpressionCount() > 0)
		//addTab('Heatmap', '{!!url('/viewExpressionByGene/'.$project->id)!!}' + '/' + '{!!$gene->getSymbol()!!}');
		tab_urls['Heatmap'] = '{!!url('/viewExpressionByGene/'.$project->id)!!}' + '/' + '{!!$gene->getSymbol()!!}';
		tab_urls['GSEA'] = '{!!url('/viewGSEA/'.$project->id)!!}' + '/gene/' + '{!!$gene->getSymbol()!!}'+'/'+'{!!rand()!!}';
		var value_type = $("#selValueType").val();
		var width = $('#group_plot_area').width();
		/*
		var tbl_grp = document.getElementById("tblGroupPlots");
		var row = tbl_grp.insertRow(0);
		var cell = row.insertCell(0);
		cell.innerHTML = '';
		row = tbl_grp.insertRow(1);
		cell = row.insertCell(0);		
		cell.innerHTML = '';
		*/
		getExpressionData();		
		
		//console.log(JSON.stringify(data));
	
		$("#loadingCorr").css("display","block");		
		$(".loadingExp").css("display","block");
		$("#group_panel").css("visibility","hidden");
		$("#combined_panel").css("visibility","hidden");		
						
		
		@endif

		var first_tab = null;
		@foreach ( $project->getVarCountByGene($gene->getSymbol()) as $type => $cnt)
			@if ($cnt > 0)
				url = '{!!url("/viewVarAnnotationByGene/$project->id/".$gene->getSymbol()."/$type")!!}';
				//console.log(url);
				var tab_id = '{!!Lang::get("messages.$type")!!}';
				tab_urls[tab_id] = url;				
				if (first_tab == null)
					first_tab = tab_id;
				//addTab('{!!$type!!} mutation', url);
			@endif
		@endforeach

		@if ($project->getExpressionCount() == 0 && $selected_tab_id == 0)
			showFrameHtml(first_tab);
		@endif

		url = '{!!url("/viewCNVByGene/$project->id/".$gene->getSymbol())!!}';
		tab_urls['CNV'] = url;

		url = '{!!url("/viewFusionGenes/$project->id/".$gene->getSymbol())!!}';
		tab_urls['Fusions'] = url;

		url = '{!!url("/viewSurvivalByExpression/$project->id/".$gene->getSymbol())!!}';
		tab_urls['Survival'] = url;

		$('.plotInput').on('change', function() {
			drawGroupPlots();	
		});

		$('#selTargetType').on('change', function() {			
			getExpressionData();			
		});

		$('#selNorm').on('change', function() {			
			getExpressionData();			
		});

		$('.cor_filter ').on('change', function() {			
			loadCorrelation();			
		});

		$('#selCorFilter').on('change', function() {			
			tbl_corr.draw();			
		});		

		$('.easyui-tabs').tabs({
			onSelect:function(title) {				
				if (title=="Correlation") {
					if (corr_data == null)
						loadCorrelation();
					return;
				}
				if (title == "Mutations" || title == "Expression")
					tab = $('#tab' + title).tabs('getSelected');
				else
					tab = $(this).tabs('getSelected');				
				var id = tab.panel('options').id;				
				showFrameHtml(id);	
		   }
		});	

		$.fn.dataTableExt.afnFiltering.push( function( oSettings, aData, iDataIndex ) { 
			if (oSettings.nTable != document.getElementById('tblCorr'))
				return true;
			var sign_idx = aData.length -1 ;
			var filter = $("#selCorFilter").val().toLowerCase();
			if (filter == 'all')
				return true;
			var sign = aData[sign_idx].toLowerCase();
			if (sign == filter)
				return true;
			return false;			
		});
		
		$('#selCorGroup').on('change', function() {
			showCorrPlot();
		});

		$('#selColor').on('change', function() {
			setColor($(this).val(), 'black', combine_plot)
		});

		$('#showBoxplotValue').change(
			function() {
				group_plot.showBoxplotOriginalData = $(this).is(":checked");
				group_plot.draw();
			}
		);

		$('#gene_id').keyup(function(e){
			if(e.keyCode == 13) {
        			$('#btnGene').trigger("click");
    			}
		});	
		if ($('#gene_id').val()){
			$('#btnGene').trigger("click");
		}

		$('#btnDownloadExp').on('click', function() {
			var filename = "Expression-{!!$gene->getSymbol()!!}.txt";
			var blob = new Blob([download_exp_str], {
				type: "text/plain;charset=utf-8"
			});
			saveAs(blob, filename);
		});

		$('#btnGene').on('click', function() {
			console.log("clicked button");
			t = $('#tabDetails').tabs('getSelected');
			window.location.replace("{!!url('/viewProjectGeneDetail')!!}" + "/{!!$project->id!!}/" + $('#gene_id').val() + '/' + t.panel('options').index);
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

		//addTab('Mutation', '{!!url('/viewVarAnnotation/'.$project->id."/null/".$gene->getSymbol()."/germline")!!}');

		$('#tabDetails').tabs('select', {!!$selected_tab_id!!});

		$('#search_samples').keyup(function(e){
			if(e.keyCode == 13) {
				//highlightSample();
				drawGroupPlots();
   			}
		});
	});

	function highlightSample() {
		var search_text = $('#search_samples').val();
		if (search_text.trim() == "")
			return;		
		column_chart.xAxis[0].update({
					type: 'category',
		            labels: {
		                rotation: -60,
		                style: {
		                    fontSize: '10px',
		                    fontFamily: 'Verdana, sans-serif'
		                },	            
						formatter: function () {
		            		if (this.value.toUpperCase().indexOf(search_text.toUpperCase()) != -1)
		            			return "<font color='red'><b>" + this.value + "</b></font>";
		                	return this.value;
		                }
		            }
		});       			
	}
	function getExpressionData() {
		$("#loadingExp").css("display","block");
		$("#group_content").css("display","none");
		var target_type = $("#selTargetType").val();
		var norm_type = $("#selNorm").val();
    	var url = '{!!url("/getExpressionByGeneList/$project->id/null/null/".$gene->getSymbol())!!}' + '/' + target_type + '/all/' + norm_type;
    	console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(json_data) {
				$("#loadingExp").css("display","none");
				$("#group_content").css("display","block");
				var first = (data == null);
				data = JSON.parse(json_data);
				//console.log(json_data);
				setOptions();
				if (first) {
					data.tumor_project_data.meta_data.attr_list.forEach(function(d) {
						$('#selGroup').append($('<option>', { value : d }).text(d));
						if (d == '{!!Lang::get("messages.tissue_type")!!}')
							$("#selGroup option[value='" + d + "']").prop("selected", "selected");
					});
				}
				drawGroupPlots();						
			}
		});	
	}
	function getAttrs(sample_name) {
		//console.log(sample_name);
		var attrs = {};
		if ( data.tumor_project_data.meta_data.data.hasOwnProperty(sample_name)) {
			data.tumor_project_data.meta_data.attr_list.forEach(function(attr, idx) {
				attrs[attr] = data.tumor_project_data.meta_data.data[sample_name][idx];
			});
		} else {
			if ( data.normal_project_data != null && data.normal_project_data.meta_data.data.hasOwnProperty(sample_name)) {
				data.normal_project_data.meta_data.attr_list.forEach(function(attr, idx) {
					attrs[attr] = data.normal_project_data.meta_data.data[sample_name][idx];
				});
			}
		}
		return attrs;
	}
	function openPatientPage(sample_name) {
		var patient_id = null;
	    var project_id = "any";
	    if (data.tumor_project_data.patients.hasOwnProperty(sample_name)) {
			patient_id = data.tumor_project_data.patients[sample_name];
			project_id = '{!!$project->id!!}';
	    } else {
		    if (data.hasOwnProperty('normal_project_data'))
		    	if (data.normal_project_data.hasOwnProperty('patients'))
					if (data.normal_project_data.patients.hasOwnProperty(sample_name))
						patient_id = data.normal_project_data.patients[sample_name];
		}
		if (patient_id != null) {
			var url = '{!!url("/viewPatient/")!!}' + '/' +  project_id + '/' + patient_id + '/any';
			console.log(url);
			window.open(url, '_blank');		    		
	    }
	}
	function drawGroupPlots() {
		var processed_data = prepareData();
		var include_normal = $('#ckIncludeNormal').is(':checked');
		var show_value = $('#ckShowValue').is(':checked');
	    var show_tissue_type = $('#ckShowTissueType').is(':checked');
		var search_text = $('#search_samples').val();
		//console.log(JSON.stringify(processed_data));
		//return;
		var min_width = 1000;		

		var target = $('#selTarget').val();
		var sample_meta_list_uniq = processed_data.sample_meta.reduce((a, x) => ~a.indexOf(x) ? a : a.concat(x), []).sort();
		var exp_data = [];
		var sample_list = [];
		console.log("processed_data.exp_data.length: " + processed_data.exp_data.length);
		var plot_width = processed_data.exp_data.length*16;
		if (plot_width < min_width)
			plot_width = min_width;
		plot_height = 400;		
		if (show_tissue_type)
			plot_height = 650;
		console.log("plot_width: " + plot_width);
		$("#exp_column_plot").css("width",plot_width + 'px');		
		$("#exp_column_plot").css("height",plot_height + 'px');
		$("#exp_scatter_plot").css("width", plot_width + 200 + 'px');
		var plot_width = sample_meta_list_uniq.length*80;		
		$("#group_box_plot").css("width", plot_width + 300 + 'px');

		var pie_data = getPieChartData(processed_data.sample_meta);
		var chart_title = $('#selGroup').val();
		showPieChart('pie_plot',chart_title , pie_data, function(p) {
								//console.log(p.name);
								var cols = [{title:'Patient ID'},{title:'Sample Name'},{title:'Expression -' + $('#selValueType option:selected').text()}];
								data.tumor_project_data.meta_data.attr_list.forEach(function(attr, idx){
									cols.push({title:attr});
								});
								var table_data = [];
								var normal_attr_list = {};
								if (include_normal){
									for (var i in data.normal_project_data.meta_data.attr_list)
										normal_attr_list[data.normal_project_data.meta_data.attr_list[i]] = i;
								}
								for (var i in processed_data.sample_meta) {
									if (processed_data.sample_meta[i] == p.name) {
										var sample_name = processed_data.sample_list[i];
										var exp_value = processed_data.exp_data[i];
										var patient_id = null;
										if (data.tumor_project_data.patients.hasOwnProperty(sample_name))
											patient_id = data.tumor_project_data.patients[sample_name];
										if (patient_id == null)
											if (data.normal_project_data.patients.hasOwnProperty(sample_name))
												patient_id = data.normal_project_data.patients[sample_name];

										var patient_url = '<a target=_blank href="{!!url("/viewPatient/$project->id")!!}' + '/' + patient_id + '">' + patient_id + '</a>';
										var row_data = [patient_url, sample_name, exp_value];
										data.tumor_project_data.meta_data.attr_list.forEach(function(attr, idx){
											if (data.tumor_project_data.samples.indexOf(sample_name) != -1) {
												row_data.push(data.tumor_project_data.meta_data.data[sample_name][idx]);
											} else {
												if (include_normal){
													if (data.normal_project_data.samples.indexOf(sample_name) != -1) {
														normal_idx = normal_attr_list[attr];
														if (normal_idx)
															row_data.push(data.normal_project_data.meta_data.data[sample_name][normal_idx]);
														else
															row_data.push("NA");
													} else
														row_data.push("NA");
												} else
													row_data.push("NA");
											}																						
										});
										table_data.push(row_data);
										//console.log(patient);
									}
								}
								//console.log(JSON.stringify(cols));
								//console.log(JSON.stringify(table_data));
								//$('#' + div_id).w2overlay('HAHA');								
								$('#selected_patients').w2popup();
								showTable('tblSelSamples', {cols: cols, data: table_data});
								$('#w2ui-popup #lblTotalPatients').text(chart_title + ' : ' + p.name + ' (' + table_data.length + ' samples)');
								//alert(p.name);
							});

		sample_meta_list_uniq.forEach(function(d){
							exp_data.push([]);							
					        sample_list.push([]);
					    });
		processed_data.exp_data.forEach(function(d, idx) {
							var meta_idx = sample_meta_list_uniq.indexOf(processed_data.sample_meta[idx]);
							exp_data[meta_idx].push(parseFloat(d));
							sample_list[meta_idx].push(processed_data.sample_list[idx]);
						});
	    	    
	    var scatter_data = {target_array: []};
	    var cat_medians = [];
	    sample_meta_list_uniq.forEach(function(d, idx){
	    	sorted = sortByArray(exp_data[idx], sample_list[idx]);
	    	exp_data[idx] = sorted.value_array;
	    	sample_list[idx] = sorted.target_array;
	    	var target_array = [];
	    	sorted.target_array.forEach(function (sample_name, sidx) {
	    		att = getAttrs(sample_name);
	    		att.sample_name = sample_name;
	    		target_array.push(att);
	    		//target_array.push({"sample_name": t});
	    	});
	    	cat_medians.push(getPercentile(sorted.value_array, 50));
	    	scatter_data.target_array.push({"category": d, "data" : {"value_array" : sorted.value_array, "target_array": target_array}});
		});
		
		scatter_data.value_array = cat_medians;
		scatter_data = sortByArray(cat_medians, scatter_data.target_array);		

	    var outliers = [];
	    var box_values = [];

		sample_meta_list_uniq.forEach(function(d, idx) {
			var box_value = getBoxValues(exp_data[idx], idx, sample_list[idx]);			
	    	box_values.push(box_value.data);	    	
	    	//console.log(JSON.stringify(getBoxValues(exp_data[idx], idx, sample_list[idx])));
	    	//if (box_value.outliers != null)
			//	outliers = outliers.concat(box_value.outliers);
			outliers.push(box_value.outliers);
	    });
	    //console.log(JSON.stringify(box_values));
	    //console.log(JSON.stringify(outliers));
	    sample_meta_list_uniq_sorted = sortByArray(cat_medians, sample_meta_list_uniq).target_array;
		var box_values_sorted = sortByArray(cat_medians, box_values).target_array;
		var outliers = sortByArray(cat_medians, outliers).target_array;
		//flatten and reindex outliers
		var outliers_sorted = [];
		outliers.forEach(function(d, idx) {
			d.forEach(function(d_sub, idx_sub) {
				d_sub.x = idx;
				outliers_sorted.push(d_sub);
			});			
		});
		//outliers_sorted = outliers_sorted.flat();
		//console.log("after =========================");
		//console.log(JSON.stringify(sample_meta_list_uniq_sorted));
		//console.log(JSON.stringify(box_values_sorted));
	    //console.log(JSON.stringify(outliers_sorted));
	    //box_values = sortByArray(cat_medians, box_values);
	    var title = target + '-' + $('#selGroup').val();
	    var y_title = 'Expression -' + $('#selValueType option:selected').text();
		drawBoxPlot('group_box_plot', title, y_title, sample_meta_list_uniq_sorted, box_values_sorted, outliers_sorted, function (p) {
			//console.log(JSON.stringify(p.name));
	    	openPatientPage(p.name);
	    });

		
	    //console.log(JSON.stringify(exp_data));

		var column_series_data = [];
		var sample_labels = ["Sample"];
		var meta_labels = [$('#selGroup').val()];
		var exp_labeels = [$('#selValueType option:selected').text()];
		sample_meta_list_uniq.forEach(function(meta, meta_idx) {
			var points = [];
			exp_data[meta_idx].forEach(function(exp, idx) {
				var sample_name = sample_list[meta_idx][idx];
				var point = getAttrs(sample_name);
				point.html = getAttrHtml(point);
				point.name = sample_name;				
				point.y = parseFloat(exp);				
				points.push(point);
				sample_labels.push(sample_name);
				meta_labels.push(meta);
				exp_labeels.push(point.y);
			});
			
			var series = {turboThreshold : 0, name: meta, data: points};
	        column_series_data.push(series);
	    });
	    download_exp_str = sample_labels.join("\t") + "\n" + meta_labels.join("\t") + "\n" + exp_labeels.join("\t")
	    //console.log(download_exp_str);

	    //console.log(JSON.stringify(scatter_data));
	    var plot_type = $('#selPlotType').val();
	    if (plot_type == 'scatter') {
	    	drawGroupScatterPlot('exp_scatter_plot', title, scatter_data, 'Group', y_title, function (p) {
	    		openPatientPage(p.name);
	    	}, show_value, search_text);
	    } else {
	    	//console.log(JSON.stringify(column_series_data));
	    	drawColumnPlot('exp_scatter_plot', title, y_title, column_series_data, show_value, show_tissue_type, search_text, function (p) {
	    		openPatientPage(p.name);
	    	});
	    }
	    //highlightSample();
	}

	function setOptions() {
		//data.tumor_project_data.library_type.forEach(function(d) {
		//	$('#selLibType').append($('<option>', { value : d }).text(d.toUpperCase()));
		//});
		//$('#selLibType').val(library_type);

		var target_type = $('#selTargetType').val();

		$('#selTarget').empty();
		//console.log(JSON.stringify(data.tumor_project_data.target_list));
		//console.log(target_type);
		for (var i in data.tumor_project_data.target_list[target_type]) {
			var d = data.tumor_project_data.target_list[target_type][i].id;
			$('#selTarget').append($('<option>', { value : d }).text(d.toUpperCase()));
		};		
	}

	function generateSampleList(sample_data, sample_ids, sample_meta, library_type) {
		var lib_idx = 2;
		var samples = [];
		for (var i in sample_data) {
			var sample_name = sample_data[i];
			var sample_id = sample_ids[i];
			if (library_type.toLowerCase() == "all") {
				samples.push({'sample_name': sample_name, 'sample_id' : sample_id, 'index': i});
			}
			else if (library_type.toLowerCase() == "polya") {
				if (sample_meta[sample_name][lib_idx].toLowerCase() == "polya")
					samples.push({'sample_name': sample_name, 'sample_id' : sample_id, 'index': i});
			} else {
				if (sample_meta[sample_name][lib_idx].toLowerCase() != "polya")
					samples.push({'sample_name': sample_name, 'sample_id' : sample_id, 'index': i});
			}
		}
		return samples;
	}

	function prepareData() {
		//library_type = "polyA"
		//console.log(JSON.stringify(data.tumor_project_data.samples));
		//console.log(JSON.stringify(data.tumor_project_data.meta_data.data));		

		//x: gene annotation
		var target_type = $('#selTargetType').val();
		var target = $('#selTarget').val();
		var group = $('#selGroup').val();
		var value_type = $('#selValueType').val();
		var include_normal = $('#ckIncludeNormal').is(':checked');		
		
		var sample_list = [];
		var exp_data = [];		
		var tumor_samples = generateSampleList(data.tumor_project_data.samples, data.tumor_project_data.sample_ids, data.tumor_project_data.meta_data.data, library_type);
		var median = 0;
		var mean = 0;
		var std = 0;
		//calcalate standard deviation
		if (value_type != "log2" && value_type != "raw") {
			var values = [];
			if (include_normal)
				values = data.normal_project_data.exp_data[target][target_type];
			if (value_type == "zscore" || value_type == "mcenter") {
				values = values.concat(data.tumor_project_data.exp_data[target][target_type]);
			}
			values = values.map(function(v){return Math.log2(parseFloat(v)+1);});
			if (value_type == "mcenter" || value_type == "mcenter_normal")
				median = getPercentile(values, 50);
			else {
				std = standardDeviation(values);
				mean = average(values);
			}			
		}
		tumor_samples.forEach(function(sample) {			
			var value = parseFloat(data.tumor_project_data.exp_data[target][target_type][sample.index]);
			var log2_value = Math.log2(value + 1);
			if (value_type == "log2")
				value = log2_value;
			if (value_type == "zscore" || value_type == "zscore_normal")
				value = (log2_value - mean) / std;
			if (value_type == "mcenter" || value_type == "mcenter_normal")
				value = (log2_value - median);
			exp_data.push(value.toFixed(2));
			if (sample_list.indexOf(sample.sample_name) == -1)
				sample_list.push(sample.sample_name);
			else
				sample_list.push(sample.sample_id);
		});
		var normal_samples = [];
		if (include_normal){
			normal_samples = generateSampleList(data.normal_project_data.samples, data.normal_project_data.sample_ids, data.normal_project_data.meta_data.data, library_type);
			normal_samples.forEach(function(sample) {
				var value = parseFloat(data.normal_project_data.exp_data[target][target_type][sample.index]);
				var log2_value = Math.log2(value + 1);
				if (value_type == "log2")
					value = log2_value;
				if (value_type == "zscore" || value_type == "zscore_normal")
					value = (log2_value - mean) / std;
				if (value_type == "mcenter" || value_type == "mcenter_normal")
					value = (log2_value - median);
				exp_data.push(value.toFixed(2));
				sample_list.push(sample.sample_name);
			});			
		}

		var prj_attr_list = {};
		var normal_attr_list = {};

		for (var i in data.tumor_project_data.meta_data.attr_list)
			prj_attr_list[data.tumor_project_data.meta_data.attr_list[i]] = i;
		
		if (include_normal){
			for (var i in data.normal_project_data.meta_data.attr_list)
				normal_attr_list[data.normal_project_data.meta_data.attr_list[i]] = i;
		}

		var attr_list = (include_normal)? mergeArrays(data.tumor_project_data.meta_data.attr_list, data.normal_project_data.meta_data.attr_list) : data.tumor_project_data.meta_data.attr_list;

		var sample_meta = {};
		attr_list.forEach(function(attr_name) {
			sample_meta[attr_name] = [];
			var idx = prj_attr_list[attr_name];
			//tumor sample
			tumor_samples.forEach(function(sample) {
				var sample_name = sample.sample_name;
				if (idx && data.tumor_project_data.meta_data.data.hasOwnProperty(sample_name))
					sample_meta[attr_name].push(data.tumor_project_data.meta_data.data[sample_name][idx]);
				else
					sample_meta[attr_name].push('NA');
			});
			//normal samples
			if (include_normal){				
				var idx = normal_attr_list[attr_name];
				normal_samples.forEach(function(sample) {
					var sample_name = sample.sample_name;
					if (idx)
						sample_meta[attr_name].push(data.normal_project_data.meta_data.data[sample_name][idx]);
					else
						sample_meta[attr_name].push('NA');
				});
			}
		});

		/*
		console.log("===== sample meta =====");
		console.log(JSON.stringify(sample_meta[group]));
		console.log("===== sample list =====");
		console.log(JSON.stringify(sample_list));
		console.log("===== exp data =====");
		console.log(JSON.stringify(exp_data));
		*/
		return {sample_meta: sample_meta[group], sample_list : sample_list, exp_data : exp_data};
	}

	function showTable(tbl_id, data) {
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
					"data": data.data,
					"columns": data.cols,
					"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
					"pageLength":  15,
					"pagingType":  "simple_numbers",									
				} );		
	}

	function getAttrHtml(attrs) {
		var html = '';
		for (var attr in attrs) {
			html += '<b>' + attr + ': </b>' + attrs[attr] + '<br>';
		}
		return html;
	}

	function drawColumnPlot(div_id, title, y_title, series_data, show_value=false, show_tissue_type=false, search_text='', click_handler=null) {
		//$('#' + div_id).highcharts({
		var dataLabels = {};
		var xAxis = {
						type: 'category',
			            //categories : categories,
			            labels: {
			                rotation: -60,
			                useHTML : true,
			                //overflow: 'justify',
			                style: {
			                    fontSize: '10px',
			                    fontFamily: 'Verdana, sans-serif'
			                },
			                formatter: function () {
			                	if (search_text.trim() == '') return this.value;
				            	if (this.value.toUpperCase().indexOf(search_text.toUpperCase()) != -1)
				            		return "<font color='red'><b>" + this.value + "</b></font>";
				                return this.value;
				    		}
			            }
			        }
		var y_offset = -3;		

		if (show_value || show_tissue_type || search_text.trim() != '') {
				dataLabels = {
	                	enabled: true,
	                	useHTML: true,
	                	rotation : -50,
	                	overflow: 'none',
	                	align: 'left',
	                	crop: false,
	                	y : y_offset,
	                	//format: label_format,
	                	formatter: function() {
	                		var label_format = '';
	                		if (search_text.trim() != '')
		                		if (this.point.name.toUpperCase().indexOf(search_text.toUpperCase()) != -1) {
									y_offset = 0;
									return this.point.y + ' - <font color="red">' + this.point.name + '</font>';
								}
							if (show_value) {
								label_format = this.point.y;
								y_offset = 0;
							}
							if (show_tissue_type) {
								label_format += ' - <font color="gray">' + this.point["Tissue|Diagnosis"] + '</font>';
							}							
	                		return label_format;
	                	},
	                	style: {
	                		color: 'red',
		                    fontSize: '10px',
		                    //fontFamily: 'Verdana, sans-serif'
	                	}
	            	}
		}
		
		column_chart = Highcharts.chart(div_id, {	
			credits: false,
	        chart: {
	            type: 'column',
	            zoomType: 'x',           
	        },
	        title: {
	            text: title
	        },        
	        xAxis: xAxis,
	        yAxis: {
	            //min: 0,
	            title: {
	                text: y_title
	            }
	        },
	        legend: {
	            enabled: true,
	            align: 'left'
	        },
	        tooltip: {
	        	useHTML : true,
	        	borderWidth: 1,
	        	borderRadius: 0,
	        	shadow: false,
	        	formatter: function () {
	        		return '<div class="tooltop"><H5>Sample: ' + this.point.name + '</H5><H5>Expression: ' + this.y + '</H5><hr>'+ this.point.html + '</div>';
	        	}
	        	//pointFormat: '{point.html}'
	        },
	        plotOptions: {
		        series: {
					pointWidth: 14,
					cursor: 'pointer',
                    point: {
                            events: {
                                click: function () {
                                    if (click_handler != null) {
                                        click_handler(this);
                                    }                                    
                                }
                            }                    
                    }
				},
				column: {
					dataLabels: dataLabels
	            }
		    },
	        series: series_data
	    });	    
	}

	function showScatterPlot(div_id, title, sub_title, x_title, y_title, series_data) {
		$('#' + div_id).highcharts({
			credits: false,
			chart: {
				borderWidth : 1
			},
            title: {
                text: title
            },
            subtitle: {
                text: sub_title
            },
            xAxis: {
                title: {
                    enabled: true,
                    text: x_title
                },
                startOnTick: true,
                endOnTick: true,
                showLastLabel: true
            },
            yAxis: {
                title: {
                    text: y_title
                }
            },            
            plotOptions: {
                scatter: {
                    marker: {
                        radius: 5,
                        states: {
                            hover: {
                                enabled: true,
                                lineColor: 'rgb(100,100,100)'
                            }
                        }
                    },
                    states: {
                        hover: {
                            marker: {
                                enabled: false
                            }
                        }
                    }
                }
            },
            tooltip: {
		            	formatter: function(chart) {
		                	var p = this.point;
		                	return '<font color=red>Series:</font>' + this.series.name + '<br>' + 
		                		'<font color=red>Sample:</font>' + p.name + '<br>' + 
		                       '<font color=red>' + x_title + ':</font>' + p.x + '<br>' + 
		                       '<font color=red>' + y_title + ':</font>' + p.y;		                       
		            	}
		    },            
            series: series_data                
	    });
	}

	

	function addTab(title, url){
		var type='add';		
		//var content = '<iframe scrolling="auto" frameborder="0"  src="'+url+'" style="width:100%;height:95%;overflow:none"></iframe>';
		var content = '<iframe scrolling="auto" frameborder="0"  src="'+url+'" style="width:100%;overflow:auto"></iframe>';
			$('#tabDetails').tabs(type, {
					title:title,
					content:content,
					closable:false,
			});
	}
        
	function setColor(val, indicator, plot_obj) {
		if (val == '0') {
			plot_obj.heatmapType = "green-red";
				plot_obj.indicatorCenter = indicator;
		}
		if (val == '1') {
			plot_obj.heatmapType = "blue-green";
			plot_obj.indicatorCenter = 'rainbow';
		}
		plot_obj.draw();
	}

	function showTwoGeneScaterPlot(gene1, gene2) {
		$('#loadingTwoGeneCorr').css("display","block");
		$('#corr_plot').css("display","none");	
		var group_loaded = (corr_data != null);
		var target_type = $('#selCorTargetType').val();
		var value_type = $('#selCorNorm').val();
		url = '{!!url("/getTwoGenesDotplotData/".$project->id)!!}' + '/' + gene1 + '/' + gene2 + '/' + target_type + '/' + value_type;
		console.log(url);		
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {								
				$('#loadingTwoGeneCorr').css("display","none");
				$('#corr_plot').css("display","block");	
				corr_data = JSON.parse(data);
				if (!group_loaded) {
					corr_data.data.meta_data.attr_list.forEach(function(d) {
						$('#selCorGroup').append($('<option>', { value : d }).text(d));
					});
				}
				//$('#lblTwosided').text(corr_data.pvalue.p_two);
				//$('#lblPositive').text(corr_data.pvalue.p_great);
				//$('#lblNegative').text(corr_data.pvalue.p_less);
				corr_gene1 = gene1;
				corr_gene2 = gene2;	
				showCorrPlot();			
			}
		});

	}

	function showCorrPlot() {
		var title = corr_gene1 + " vs " + corr_gene2;
		var sub_title = "P-value:(two sided: " + parseFloat(corr_data.pvalue.p_two).toFixed(4) + ", Positive: " + parseFloat(corr_data.pvalue.p_great).toFixed(4) + ", Negative: " + parseFloat(corr_data.pvalue.p_less).toFixed(4) + ")"; 

		var sample_meta = $('#selCorGroup').val();
		var target_type = $('#selCorTargetType').val();
		var sample_meta_list = [];
		corr_data.data.samples.forEach(function(sample) {
			var sample_meta_idx = corr_data.data.meta_data.attr_list.indexOf(sample_meta);
			sample_meta_list.push(corr_data.data.meta_data.data[sample][sample_meta_idx]);
		});
		var groups = {};
		var all_data = [];
		corr_data.data.samples.forEach (function (sample, idx) {
			if (groups[sample_meta_list[idx]] == null)
				groups[sample_meta_list[idx]] = [];
			var x_value = parseFloat(corr_data.data.exp_data[corr_gene1][target_type][idx]);
			var y_value = parseFloat(corr_data.data.exp_data[corr_gene2][target_type][idx]);
			x_value = Math.log2(x_value + 1);
			y_value = Math.log2(y_value + 1);
			//x_value = parseFloat(x_value).toFixed(2);
			all_data.push([x_value, y_value]);
			groups[sample_meta_list[idx]].push({name: sample, x : x_value, y:y_value});
		});
		var reg = linear_regression(all_data, 2);
		var series = [];
		series.push({type: 'line', name: "regression", data: reg.points});
		for (var sample_attr in groups) {
			if (groups[sample_attr].length > 0)
				series.push({turboThreshold : 0, type: 'scatter', name: sample_attr, colorByPoint: false, data: groups[sample_attr]});
		}
		//console.log(JSON.stringify(series));
		showScatterPlot('corr_plot', title, sub_title, corr_gene1 + '  (log2(x+1))', corr_gene2 + '  (log2(y+1))', series);


		/*
		var sample_meta_list_uniq = sample_meta_list.reduce((a, x) => ~a.indexOf(x) ? a : a.concat(x), []).sort();
		var series_data = [];
		sample_meta_list_uniq.forEach(function(meta, meta_idx) {
			var points = [];
			exp_data[meta_idx].forEach(function(exp, idx) {
				points.push([sample_list[meta_idx][idx], parseFloat(exp)]);
			});
			var series = {name: meta, data: points};
	        column_series_data.push(series);
	    });
		show3DScatter('pca_plot', 'Principle component Analysis', 'PC1(' + pca_data.variance_prop[0] + '%)', 'PC2(' + pca_data.variance_prop[1] + '%)', 'PC3(' + pca_data.variance_prop[2] + '%)', series);
		*/
	}

	function savePNG(obj) {
		var i=obj.canvas.toDataURL("image/png");return window.open("about:blank","canvasXpressImage").document.write('<html><body><img src="'+i+'" /></body></html>');
	}
	
	function loadCorrelation() {
		$("#loadingCorr").css("display","block");
		$("#coexp_panel").css("display","none");
		var target_type = $('#selCorTargetType').val();
		var method = $('#selCorMethod').val();
		var value_type = $('#selCorNorm').val();
		var current_gene = '{!!$gene->getSymbol()!!}';
		var url = '{!!url("/getCorrelationData/".$project->id)!!}' + '/' + current_gene + '/0.2/' + target_type + '/' + method + '/' + value_type;
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {			
				$("#loadingCorr").css("display","none");
				$("#coexp_panel").css("display","block");				
				var corr_data = JSON.parse(data);
				var best_gene = corr_data.data[0][0];
				for (var i=0;i<corr_data.data.length;i++) {
					var gene = corr_data.data[i][0];
					corr_data.data[i][0] = "<a href=javascript:showTwoGeneScaterPlot('" + current_gene + "','" + gene + "');>" + gene + "</a>";
				}
				
				if (tbl_corr != null) {
					tbl_corr.destroy();
					$('#tblCorr').empty();
				}
				tbl_corr = $('#tblCorr').DataTable( 
				{
					"data": corr_data.data,
					"columns": corr_data.cols,
					"ordering":    true,
					"order": [[ 2, "desc" ]],
					"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
					"pageLength":  15,			
					"pagingType":  "simple_numbers",			
					
				} );
				//showCoexPlot('corrheatmap_canvas_p', data.p);
				//showCoexPlot('corrheatmap_canvas_n', data.n);
				showTwoGeneScaterPlot('{!!$gene->getSymbol()!!}', best_gene);
				//filterCorr();
			}
		});
	}

	function showFrameHtml(id) {
		if (loaded_list.indexOf(id) == -1) {
			var url = tab_urls[id];
			if (url != undefined) {
				var html = '<iframe scrolling="no" frameborder="0"  src="' + url + '" style="width:100%;height:100%;border-width:0px"></iframe>';
				$('#' + id).html(html);
				console.log('#' + id);
				console.log(html);
				loaded_list.push(id);
			}
		}
	}

   </script>

<div id="selected_patients" style="display: none; width: 80%; height: 80%; overflow: auto; background-color=white;">	
	<div rel="body" style="text-align:left;padding: 20px;">
		<a href="javascript:w2popup.close();" class="boxclose"></a>
    	<H4><lable id="lblTotalPatients"></lable></H4><HR>    	
    	<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblSelSamples" style='width:100%'>
		</table>		
	</div>
</div>
<div id="out_container" style="width:100%;height:100%;padding:0px;overflow:auto;"> 
	<div class="row" style="padding: 0px 20px 0px 20px">
		<div class="col-md-8">
			<ol class="breadcrumb" style="margin-bottom:0px;padding:4px 0px 0px 0px;background-color:#ffffff">
				<li class="breadcrumb-item active"><a href="{!!url('/')!!}">Home</a></li>
				<li class="breadcrumb-item active"><a href="{!!url('/viewProjects/')!!}">Projects</a></li>
				<li class="breadcrumb-item active"><a href="{!!url('/viewProjectDetails/'.$project->id)!!}">{!!$project->name!!}</a>
				<li class="breadcrumb-item active"><a href="{!!url('/viewProjectGeneDetail/'.$project->id.'/'.$gene->getSymbol())!!}">{!!$display_id!!}</a>
				</li>				
			</ol>
		</div>
		<div class="col-md-4">
			<span class="float-right h6">
				<img width="20" height="20" src="{!!url('images/search-icon.png')!!}"></img> Gene: <input id='gene_id' type='text' value='{!!$display_id!!}'/>&nbsp;&nbsp;<button id='btnGene' class="btn btn-info">GO</button>
			</span>
		</div>
	</div>	
	<div id="tabDetails" class="easyui-tabs" data-options="tabPosition:top,fit:true,plain:true,pill:false" style="height:100%;width:100%;padding:5px;overflow:hidden;">
	@if ($project->getExpressionCount() > 0)
	  @if ($project->showFeature("expression"))
		<div id="Expression" title="Expression" style="width:100%;padding:0px;">
			<div id="tabExpression" class="easyui-tabs" data-options="tabPosition:'left',plain:true,pill:false,border:false,headerWidth:100" style="width:100%;padding:0px;overflow:auto;border-width:0px;">
				<div title="Group plot" style="height:inherit;background:rgba(203, 203, 210, 0.15);">
					<div id='loadingExp'>
					    <img src='{!!url('/images/ajax-loader.gif')!!}'></img>
					</div>			
					<div id="group_content" class="container-fluid">
						<div class="row px-1 py-1">
							<div class="col-md-2">
								<div class="card px-2 py-2">									
										<label for="selTargetType">Annotation:</label>
										<select id="selTargetType" class="form-control">
										@foreach ($target_type_list as $target_type)
											<option value="{!!$target_type!!}">{!!$target_type!!}</option>
										@endforeach
										</select>
										<label for="selTarget">Targets:</label>
										<select id="selTarget" class="form-control plotInput">						
										</select>
										<label for="selGroup">Groups:</label>
										<select id="selGroup" class="form-control plotInput">						
										</select>										
										<label for="selValueType">Value type:</label>
										<select id="selValueType" class="form-control plotInput">
												<option value="log2">Log2(raw+1)</option>
												<option value="raw">Raw</option>
												<option value="zscore" >Z-score</option>
												<option value="mcenter">Median Centered</option>
												<!--option value="zscore_normal">Z-score by normal samples </option>
												<option value="mcenter_normal">Median Centered by normal samples</option-->
										</select>										
										<label for="selNorm">Normalization:</label>
										<select id="selNorm" class="form-control plotInput">												
												<option value="tmm-rpkm">TMM-FPKM</option>
												<option value="tpm">TPM</option>												
										</select>
										<label for="selPlotType">Plot Type:</label>
										<select id="selPlotType" class="form-control plotInput">												
												<option value="bar">Bar Plot</option>
												<option value="scatter">Scatter Plot</option>												
										</select>
									@if (!Config::get('site.isPublicSite'))										
										<label for="selLibType">Search samples:</label>
										<input id="search_samples" class="form-control"></input>
										<br>
										<div class="form-check">
											<label class="form-check-label">
												<input type="checkbox" id='ckShowValue' class="plotInput form-check-input">Show value</input>
											</label>
										</div>
										<div class="form-check">
											<label class="form-check-label">
												<input type="checkbox" id='ckIncludeNormal' class="plotInput form-check-input">Include normal project data</input>
											</label>
										</div>
										
										<br>
										<!--input type="checkbox" id='ckShowTissueType' class="plotInput">Show tissue type</input><br-->
										
										
									@endif
										<br><br><button id="btnDownloadExp" class="btn btn-info"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Download Expression</button>
										<br>
								</div>																	
							</div>							
							<div id="group_plot_area" class="col-md-10" style="display: inline-block;">
								<div class="row">
									<div class="col-md-12">
										<div class="card" style="padding:0px;margin:0 auto">
											<H4 style="margin: 5px 5px">Expression plot</H4>
											<div style="overflow:auto;">										
												<!--div style="height: 400px; padding-right:60px;margin: 5 auto" id="exp_column_plot"></div-->
												<div style="height: 400px; padding-right:60px;margin: 5 auto" id="exp_scatter_plot"></div>
											</div>
										</div>
									</div>
								</div>
								<br>
								<div class="row">
									<div class="col-md-12">
										<div class="card">
											<H4 style="margin: 5px 5px">Box plot</H4>
											<div style="overflow:auto;">
												<div style="height: 350px; margin: 5 auto" id="group_box_plot"></div>
											</div>
										</div>
									</div>
								</div>
								<br>
								<div class="row">
									<div class="col-md-12">
										<div class="card" style="overflow:auto">										
											<H4 style="margin: 5px 5px">Pie plot</H4>											
											<div style="height: 350px; margin: 5 auto" id="pie_plot"></div>											
										</div>
									</div>									
								</div>
							</div>
						</div>
					</div>
				</div>
				<div title="Correlation" style="background:rgba(203, 203, 210, 0.15);">
					<div id='loadingCorr' class='loading_img'>
						<img src='{!!url('/images/ajax-loader.gif')!!}'></img>
					</div>
					<div class="container-fluid" id="coexp_panel" style="display:none;">
						<div class="row">
							<div class="col-md-4">
								<div class="card mx-1 my-1" >
									<div class="card-header bg-info text-white h6">Correlation table</div>
									<div class="px-2">
										<label for="selCorTargetType">Annotation:</label>
										<select id="selCorTargetType" class="cor_filter form-control">						
										@foreach ($target_type_list as $target_type)
											<option value="{!!$target_type!!}">{!!$target_type!!}</option>
										@endforeach
										</select>
										<br>
										<label for="selCorMethod">Method:</label>
										<select id="selCorMethod" class="cor_filter form-control">													
											<option value="pearson">Pearson</option>
											<option value="spearman">Spearman</option>
										</select>
										<br>
										<label for="selCorNorm">Normalization:</label>
										<select id="selCorNorm" class="form-control cor_filter">
												<option value="tmm-rpkm">TMM-FPKM</option>
												<option value="tpm">TPM</option>												
										</select>
										<br>
										<label for="selCorFilter">Filter:</label>
										<select id="selCorFilter" class="form-control">													
											<option value="all">ALL</option>
											<option value="positive">Positive</option>
											<option value="negative">Negative</option>
										</select>
										<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblCorr" style='width:100%;'>
										</table>
									</div>									
								</div>					
							</div>
							<div class="col-md-8">
								<div class="card mx-1 my-1" >
									<h3 class="card-header bg-info text-white h6">Plot</h3>
									<div class="px-2">
										<label for="selCorGroup">Groups:</label>
											<select id="selCorGroup" class="form-control">						
										</select>
										<br>
										<div id='loadingTwoGeneCorr' style="display:none;">
											<img src='{!!url('/images/ajax-loader.gif')!!}'></img>
										</div>
										<div style="width:550;height:550; margin: 0 auto" id="corr_plot"></div>									\
									</div>
								</div>
							</div>
						</div>			
					</div>
				</div>			
				<div id="Heatmap" title="Heatmap">
				</div>
				@if ($project->showFeature("GSEA") && 1==2)
				<div id="GSEA" title="GSEA" style="width:100%;padding:10px;">
					object data='{!!url('/viewExpressionByGene/'.$project->id)!!}' + '/gene/' + '{!!$gene->getSymbol()!!}'+'/'+'{!!rand()!!}'></object>
				</div>
				@endif
				@if (count($survival_diagnosis) > 0)
				<div id="Survival" title="Survival" style="width:100%;padding:10px;"></div>
				@endif
			</div>
		</div>
		@endif
		@endif
		@if ($project->hasGeneMutation($gene->getSymbol()))
		<div id="Mutations" title="Mutations" style="width:100%;padding:10px;">
			<div id="tabMutations" class="easyui-tabs" data-options="tabPosition:top,plain:true,pill:false" style="width:98%;padding:0px;overflow:visible;border-width:0px">
				@foreach ( $project->getVarCountByGene($gene->getSymbol()) as $type => $cnt)
					@if ($cnt > 0)
					  @if ($project->showFeature($type))
						<div id="{!!Lang::get("messages.$type")!!}" title="{!!Lang::get("messages.$type")!!}" data-options="tools:'#{!!$type!!}_mutation_help'" style="width:98%;padding:0px;">
						</div>
					  @endif
					@endif
				@endforeach
			</div>
		</div>
		@endif
		@if ($project->showFeature("cnv"))
			@if ($project->hasCNV())
				<div id="CNV" title="CNV" style="width:100%;padding:10px;"></div>
			@endif
		@endif
		@if ($project->showFeature("fusion"))
			@if ($project->getSampleSummary("RNAseq") > 0)
				<div id="Fusions" title="Fusions" style="width:100%;padding:10px;"></div>
			@endif
        @endif


		
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
