{{ HTML::style('packages/heatmap/heatmap.css') }}
{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::style('packages/tooltipster-master/dist/css/tooltipster.bundle.min.css') }}
{{ HTML::style('packages/w2ui/w2ui-1.4.min.css')}}
{{ HTML::style('packages/d3/d3.tip.css') }}

{{ HTML::script('js/jquery-3.6.0.min.js') }}
{{ HTML::script('packages/DataTables-1.10.8/media/js/jquery.dataTables.min.js') }}
{{ HTML::script('packages/yadcf-0.8.8/jquery.dataTables.yadcf.js')}}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('packages/tooltipster-master/dist/js/tooltipster.bundle.min.js') }}
{{ HTML::script('js/onco.js') }}
{{ HTML::script('packages/w2ui/w2ui-1.4.min.js')}}
{{ HTML::script('packages/highchart/js/highcharts.js')}}
{{ HTML::script('packages/highchart/js/highcharts-more.js')}}
{{ HTML::script('packages/d3/d3.min.js') }}
{{ HTML::script('packages/d3/d3.tip.js') }}
{{ HTML::script('js/rainbowvis.js') }}
{{ HTML::script('packages/heatmap/heatmap.js') }}

<style>
	.panel-body {
    	padding: 5px;
	}
</style>
  <script type="text/javascript">

	var exp_plot = null;
	var data = null;
	var plot_data = null;
	var data_type = 'ensembl';
	var old_width = 0;
	var old_height = 0;
	var gene_list = 'null';
	var state = 'collapse';
	var current_gene = '';
	var current_sample = '';
	var boxplot = null;
	var gene_list = '{{$setting->gene_list}}';
	var target_type = '{{$setting->annotation}}';
	var search_type = '{{$setting->search_type}}';
	var value_type = '{{$setting->value_type}}';
	var library_type = '{{$setting->library_type}}';
	var norm_type = '{{$setting->norm_type}}';
	var meta_type = '{{$meta_type}}';
	var chr = '{{$setting->chr}}';
	var start_pos = '{{$setting->start_pos}}';
	var end_pos = '{{$setting->end_pos}}';
	var sample_meta;
	var heatmap;
	if (target_type == '')
		target_type = "ensembl";
	$(document).ready(function() {
		$("#txtGeneList").val(gene_list);
		$("#selValueType").val(value_type);
		//$("#selColor").val(colorScheme);
		$("#loadingHeatmap").css("display","block");		

		if (gene_list == '') gene_list = 'null';
		if (search_type == 'gene_list' || search_type == 'gene')
			getDataByGenes();
		if (search_type == 'locus')
			getDataByLocus();
		$('.plotInput').change(function() {
			showPlot();
			var setting = getSetting();
			var url = '{{url("/saveSetting")}}' + '/page.expression';
			$.ajax({ url: url, async: true, type: 'POST', dataType: 'text', data: setting, success: function(data) {
				}, error: function(xhr, textStatus, errorThrown){
						console.log('save failed! Reason:' + JSON.stringify(xhr) + ' ' + errorThrown);
					}
			});	
		});

		$('#showOncoprint').change(
			function() {
				if ($(this).is(":checked")) {
					exp_plot.isOncoprint = "mutation";
				}
				else {
					exp_plot.isOncoprint = "";
				}
				exp_plot.draw();
			}
		);
		$('#selLibType').on('change', function() {
			//setting = getSetting();
			//reloadPage(setting);
			showPlot();
		});

		$('#selSampleMeta').on('change', function() {
			meta_type = $('#selSampleMeta').val();
			heatmap.drawMeta($('#selSampleMeta').val(), sample_meta[$('#selSampleMeta').val()]);			
		});
		

		$('#selTargetType').on('change', function() {
			setting = getSetting();
			reloadPage(setting);
		});

		$('#selColorScheme').on('change', function() {
			showPlot();
		});

		$('#selNormType').on('change', function() {
			setting = getSetting();
			reloadPage(setting);
		});

		$('#range').slider({onComplete : function (value) {
			exp_plot.setMinX = value[0];
			exp_plot.setMaxX = value[1];
        	exp_plot.draw();
		}});

		$('#selGeneSet').on('change', function() {
			gene_set = {"0":"TP53 MDM2 MDM4 CDKN2A CDKN2B TP53BP1", "1":"AKR1C3 AR CYB5A CYP11A1 CYP11B1 CYP11B2 CYP17A1 CYP19A1 CYP21A2 HSD17B1 HSD17B10 HSD17B11 HSD17B12 HSD17B13 HSD17B14 HSD17B2 HSD17B3 HSD17B4 HSD17B6 HSD17B7 HSD17B8 HSD3B1 HSD3B2 HSD3B7 RDH5 SHBG SRD5A1 SRD5A2 SRD5A3 STAR", "2":"EGFR ERBB2 PDGFRA MET KRAS NRAS HRAS NF1 SPRY2 FOXO1 FOXO3 AKT1 AKT2 AKT3 PIK3R1 PIK3CA PTEN","3":"TP53 MDM2 MDM4 CDKN2A CDKN2B TP53BP1"};
			$("#txtGeneList").val(gene_set[$("#selGeneSet").val()]);
		});				

		$('#btnGene').on('click', function() {
			gene_list = $("#txtGeneList").val().toUpperCase();
    		gene_list = gene_list.replace(/(?:\r\n|\r|\n)/g, ' ');
    		$("#txtGeneList").val(gene_list);
			setting = getSetting('gene_list');			
    		reloadPage(setting);			
		});

		$('#btnLocus').on('click', function() {
			if (!isInt($('#start_pos').val())) {
				alert('The start position is not a number!');
				$('#start_pos').focus();
				return;
			}
			if (!isInt($('#end_pos').val())) {
				alert('The end position is not a number!');
				$('#end_pos').focus();
				return;
			}
			if (parseInt($('#end_pos').val()) < parseInt($('#start_pos').val())) {
				alert('The end position must be larger than start position!');
				$('#end_pos').focus();
				return;
			}
			var check_url = '{{url("/getGeneListByLocus/")}}' + '/' + $('#selChr').val() + '/' + $('#start_pos').val() + '/' + $('#end_pos').val() + '/' + $('#selTargetType').val();    	
    		console.log(check_url);
			$("#loadingHeatmap").css("display","block");
			$.ajax({ url: check_url, async: true, dataType: 'text', success: function(gene_json) {
						$("#loadingHeatmap").css("display","none");
						var genes = JSON.parse(gene_json);
						if (genes.length == 0) {
							alert("no genes found in this locus");
							return;
						}
						setting = getSetting('locus');
	    				reloadPage(setting);
	    			}
	    	});
        });
		
	});
    
    function reloadPage(setting) {
    	var url = '{{url("/viewExpression/$project_id/$patient_id/$case_id/")}}' + '/' + $("#selSampleMeta").val() + '/' + JSON.stringify(setting);    	
    	console.log(url);
    	window.location.href = url;

    }
    function getDataByGenes() {
    	$("#loadingHeatmap").css("display","block");
    	@if ($gene_id == '')
    		if (search_type == 'gene_list') {
    			gene_list = $("#txtGeneList").val().toUpperCase();
    			gene_list = gene_list.replace(/(?:\r\n|\r|\n)/g, ' ');
    			$("#txtGeneList").val(gene_list);
    		}
    	@endif
    	var target_type = $('#selTargetType').val();
    	var url = '{{url("/getExpressionByGeneList/$project_id/$patient_id/$case_id/")}}' + '/' + gene_list + '/' + target_type + '/' + library_type + '/' + norm_type;
    	console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(json_data) {
				$("#loadingHeatmap").css("display","none");				
				if (json_data == '') {
					$("#status").css("display","block");
				}
				data = JSON.parse(json_data);
				console.log(target_type);				
				setOptions();		
				showPlot();				
			}
		});

    } 

    function getDataByLocus() {    	
    	$("#loadingHeatmap").css("display","block");
		var url = '{{url("/getExpressionByLocus/$project_id/$patient_id/$case_id/")}}' + '/' + chr + '/' + start_pos + '/' + end_pos + '/' + target_type + '/' + library_type;
		$.ajax({ url: url, async: true, dataType: 'text', success: function(json_data) {					
				$("#loadingHeatmap").css("display","none");				
				if (json_data == '') {
					$("#status").css("display","block");
				}
				data = JSON.parse(json_data);					
				setOptions();				
				showPlot();		
			}
		});

    }       
	var showPlot = function() {		
		
		var value_type = $("#selValueType").val();
		var color_scheme = $("#selColorScheme").val();
		//if (data == null) return;
		//var include_normal = (data.normal_project_data != null);
		var include_normal = false;
		plot_data = prepareData($("#selTargetType").val(), include_normal, $("#selLibType").val(), value_type);

		sample_meta = plot_data.z;

		$('#selSampleMeta').empty();

		for (var meta in sample_meta) {
			$('#selSampleMeta').append($('<option>', { value : meta }).text(meta.toUpperCase()));
			if (meta == '{{Lang::get("messages.tissue_type")}}')
				$("#selSampleMeta option[value='" + meta + "']").prop("selected", "selected");
		}

		if (meta_type != "null")
			$('#selSampleMeta option[value="' + meta_type + '"]').prop("selected", "selected");
		var config = {
				margin : { top: 120, right: 10, bottom: 120, left: 120 },
				cellSize : 18
		};
		
		//return {z: sample_meta, y: {vars: sample_list, smps: gene_list, data: exp_data}, x:gene_meta};

		config.rowLabel = plot_data.y.smps;
		config.colLabel = plot_data.y.vars;
		config.colorScheme = color_scheme;
  		var data = [];
  		for (var i=0; i<config.colLabel.length; i++) {
  			for (var j=0; j<config.rowLabel.length; j++) {
  				data.push({row: j+1, col: i+1, value: parseFloat(plot_data.y.data[i][j])})
  			}
  		}
  		var hcrow = [];
  		var hccol = [];
  		for (var i=0; i<config.colLabel.length; i++)
  			hccol.push(i+1);
  		for (var i=0; i<config.rowLabel.length; i++)
  			hcrow.push(i+1);
  		config.hcrow = hcrow;
  		config.hccol = hccol;
  		config.data = data;
  		//console.log(JSON.stringify(config.rowLabel));
  		//console.log(JSON.stringify(config.colLabel));

  		var min = min_arr(plot_data.y.data);
		var max = max_arr(plot_data.y.data);
		if (value_type != "log2") {		
			var range = Math.min(Math.abs(min), Math.abs(max));
			config.colorMin = range * -1;
			config.colorMax = range;
		} else {
			config.colorMin = min;
			config.colorMax = max;
		}

		config.targetElementID = "chart";
		config.getHint = function (gene, sample, value) {
			state='collapse';
			return "<H6><b>Gene:&nbsp;</b><a target=_blank href='{{url("viewProjectGeneDetail/$project_id")}}" + "/" + gene + "/0'>" + gene + "</a>&nbsp;&nbsp;<b>Sample:&nbsp;</b>" + sample + "&nbsp;&nbsp;&nbsp;&nbsp;<a href='javascript:d3.select(\"#celltooltip\").classed(\"hidden\", true);'>[X]</a><br><b>Expression:</b>&nbsp;" + value + 
					"<a href=javascript:switch_details('detail_info','img_details');drawDetailPlot('" + gene + "','" + sample + "');>" + 
					"<img id='img_details' width=25 height=25 src='{{url('/images/expand.png')}}'></img></a></H6>" + 
					"<div id='detail_info' style='display:none; height: 450px; margin: auto; min-width: 320; max-width: 600px;'>" + 
					"<div id='box_plot' style='height: 200px; margin: auto; min-width: 320; max-width: 600px'></div><BR>" + 
					"<div id='scatter_plot' style='height: 200px; margin: auto; min-width: 320; max-width: 600px'></div></div>";
		};
		heatmap = new oncoHeatmap(config);
		heatmap.drawMeta($('#selSampleMeta').val(), sample_meta[$('#selSampleMeta').val()]);

		
		return;
		
				
	} 	

	function setPlotOptions() {
		var value_type = $("#selValueType").val();
		var min = min_arr(exp_plot.data.y.data);
		var max = max_arr(exp_plot.data.y.data);
		if (value_type != "log2") {		
			var range = Math.min(Math.abs(min), Math.abs(max));
			exp_plot.setMinX = range * -1;
			exp_plot.setMaxX = range;
		} else {
			exp_plot.setMinX = min;
			exp_plot.setMaxX = max;
		}
		var color_low = "green";
		var color_high = "red";		
		if ($('#selColor').val() == "1") {
			color_low = "blue";
			color_high = "green";
		}
		exp_plot.colorSpectrum = [color_low, "black", color_high];
		if ($("#clusterGenes").is(":checked")) {
			exp_plot.clusterSamples();
			exp_plot.showSmpDendrogram = true;
		} else
			exp_plot.showSmpDendrogram = false;
		if ($("#clusterSamples").is(":checked")) {
			exp_plot.clusterVariables();
			exp_plot.showVarDendrogram = true;
		} else
			exp_plot.showVarDendrogram = false;

	}

	function switch_details(block_class, image_id) {		
		if ( state === 'collapse' ) {
			state = 'expand';
			$("#" + block_class).css("display","block");
			$("#" + image_id).attr("src",'{{url('/images/collapse.png')}}');
		}
		else {
			state = 'collapse';
			$("#"  + block_class).css("display","none");
			$("#"  + image_id).attr("src",'{{url('/images/expand.png')}}');
    	}    	
	}

	function drawDetailPlot(gene, sample) {	
		console.log("----------------------------");
		console.log($('#box_plot').html());
		console.log(document.getElementById("box_plot").innerHTML);
		var meta_type = $('#selSampleMeta').val();
		var genes = plot_data.y.smps;
		var samples = plot_data.y.vars;
		var data = plot_data.y.data;
		var sample_meta_type = meta_type;
		var sample_meta_list = plot_data.z[sample_meta_type];
		var sample_meta_list_uniq = sample_meta_list.reduce((a, x) => ~a.indexOf(x) ? a : a.concat(x), []).sort();					                	
		var gene_idx = genes.indexOf(gene);					                	
		var exp_data = [];
		var exp_data_all = [];
		var sample_list = [];		

		sample_meta_list_uniq.forEach(function(d){
							exp_data.push([]);							
					        sample_list.push([]);
					    });
		data.forEach(function(arr, idx) {
							var meta_idx = sample_meta_list_uniq.indexOf(sample_meta_list[idx]);
							exp_data[meta_idx].push(parseFloat(arr[gene_idx]));
							exp_data_all.push(parseFloat(arr[gene_idx]));
							sample_list[meta_idx].push(samples[idx]);
						});
	    var outliers = [];
	    var box_values = [];

	    sample_meta_list_uniq.forEach(function(d, idx) {
	    	var box_value = getBoxValues(exp_data[idx], idx, sample_list[idx]);
	    	//console.log(JSON.stringify(box_value));	    	
	    	box_values.push(box_value.data);
	    	if (box_value.outliers != null)
				outliers = outliers.concat(box_value.outliers);
	    });
	    //d3.select("#celltooltip").style("width", sample_meta_list_uniq.length*40);
	    drawBoxPlot('box_plot', gene, sample_meta_list_uniq, box_values, outliers, meta_type );
	    drawScatterPlot('scatter_plot',exp_data_all, samples, sample );

	}

	function drawBoxPlot(div_id, gene, sample_meta_list, box_values, outliers, title ) {		
	    $('#' + div_id).highcharts({
			credits: false,
	        chart: {
	            type: 'boxplot'
	        },

	        title: {
	            text: title
	        },

	        legend: {
	            enabled: false
	        },

	        xAxis: {
	            categories: sample_meta_list,
	            //title: {
	            //    text: 'Experiment No.'
	            //}
	        },

	        yAxis: {
	            title: {
	                text: 'Expression'
	            },
	            plotLines: [{
	                value: 932,
	                color: 'red',
	                width: 1,
	                label: {
	                    text: 'Theoretical mean: 932',
	                    align: 'center',
	                    style: {
	                        color: 'gray'
	                    }
	                }
	            }]
	        },

	        series: [{
	            name: 'Observations',
	            data: box_values,
	            tooltip: {
	                headerFormat: '<em>Experiment {point.key}</em><br/>'
	            }
	        }, {
	            name: 'Outlier',
	            color: Highcharts.getOptions().colors[0],
	            type: 'scatter',
	            data: outliers,
	            marker: {
	                fillColor: 'white',
	                lineWidth: 1,
	                lineColor: 'pink'
	            },
	            tooltip: {
	                pointFormat: 'Observation: {point.name}:{point.y}'
	            }
	        }]

	    });
	}

	function drawScatterPlot(div_id, exp_data, sample_list, sample_id) {
		var values = getSortedScatterValues(exp_data, sample_list, [sample_id]);		
		$('#' + div_id).highcharts({
			credits: false,
	        chart: {
	            type: 'scatter',
	            zoomType: 'xy'
	        },
	        title: {
	            text: 'Sorted by Expression'
	        },       
	        xAxis: {
	            title: {
	                enabled: true,
	                text: 'Samples'
	            },
	            startOnTick: false,
	            endOnTick: false
	        },
	        yAxis: {
	            title: {
	                text: 'Expression'
	            }
	        },
	        
	        legend: {
	            enabled: false
	        },
	        
	        plotOptions: {
	            scatter: {
	                marker: {
	                    //radius: 8,
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
	                },
	                tooltip: {
	                    headerFormat: '',
	                    pointFormat: '<B>{point.name}:</B><BR>{point.y}'
	                }
	            }
	        },
	        series: [{
	            name: '',
	            //color: 'rgba(223, 83, 83, .5)',
	            data: values

	        }]
	    });
	}
	
	function setOptions() {
		/*
		data.tumor_project_data.library_type.forEach(function(d) {
			$('#selLibType').append($('<option>', { value : d }).text(d.toUpperCase()));
		});
		$('#selLibType').val(library_type);
		data.tumor_project_data.target_type.forEach(function(d) {
			$('#selTargetType').append($('<option>', { value : d }).text(d.toUpperCase()));
		});
		$('#selTargetType').val(target_type);
		*/
	}

	function generateSampleList(sample_data, sample_meta, library_type) {
		var lib_idx = 2;
		var samples = [];
		for (var i in sample_data) {
			var sample_name = sample_data[i];
			if (library_type.toLowerCase() == "all") {
				samples.push({'sample_name': sample_name, 'index': i});
			}
			else if (library_type.toLowerCase() == "polya") {
				if (sample_meta[sample_name][lib_idx].toLowerCase() == "polya")
					samples.push({'sample_name': sample_name, 'index': i});
			} else {
				if (sample_meta[sample_name][lib_idx].toLowerCase() != "polya")
					samples.push({'sample_name': sample_name, 'index': i});
			}
		}
		return samples;
	}

	function prepareData(target_type, include_normal, library_type, value_type) {
		//library_type = "polyA"
		//console.log(JSON.stringify(data.tumor_project_data.samples));
		//console.log(JSON.stringify(data.tumor_project_data.meta_data.data));		

		console.log("include normal? " + include_normal);
		//x: gene annotation
		//var gene_list = Object.keys(data.gene_meta.data);
		var gene_list = [];
		if (data.tumor_project_data == undefined) {
			alert("no data");
			return;
		}
		for (var i in data.tumor_project_data.target_list[target_type]) {
			gene_list.push(data.tumor_project_data.target_list[target_type][i].id);
		}
		//console.log(JSON.stringify(gene_list));
		var gene_meta = [];
		for (var i in data.gene_meta.attr_list) {
			var gene_meta_arr = [];
			gene_list.forEach(function(gene) {				
				if (data.gene_meta.data[gene] != null)
					gene_meta_arr.push(data.gene_meta.data[gene][i]);
				else
					gene_meta_arr.push('NA');
			});
			gene_meta[data.gene_meta.attr_list[i]] = gene_meta_arr;
		}

		//y" vars, smps and values
		var sample_list = [];
		var exp_data = [];	
		
		var tumor_samples = generateSampleList(data.tumor_project_data.samples, data.tumor_project_data.meta_data.data, library_type);
		var median = [];
		var mean = {};
		var std = {};
		//calcalate standard deviation
		if (value_type != "log2") {			
			gene_list.forEach(function(gene) {				
				var values = [];
				if (data.normal_project_data != null)
					values = data.normal_project_data.exp_data[gene][target_type];
				if (value_type == "zscore" || value_type == "mcenter")
					values = values.concat(data.tumor_project_data.exp_data[gene][target_type]);				
				values = values.map(function(v){return Math.log2(parseFloat(v)+1);});
				//console.log(gene + JSON.stringify(values));
				if (value_type == "mcenter" || value_type == "mcenter_normal")
					median[gene] = getPercentile(values, 50);
				else {
					std[gene] = standardDeviation(values);
					mean[gene] = average(values);
				}
			});			
		}
		tumor_samples.forEach(function(sample) {			
			gene_exp = [];
			gene_list.forEach(function(gene) {
				var value = parseFloat(data.tumor_project_data.exp_data[gene][target_type][sample.index]);
				var log2_value = Math.log2(value + 1);
				if (value_type == "log2")
					value = log2_value;
				if (value_type == "zscore" || value_type == "zscore_normal")
					value = (log2_value - mean[gene]) / std[gene];
				if (value_type == "mcenter" || value_type == "mcenter_normal")
					value = (log2_value - median[gene]);
				gene_exp.push(value.toFixed(2));
			});
			exp_data.push(gene_exp);
			sample_list.push(sample.sample_name);
		});
		var normal_samples = [];
		if (include_normal){
			normal_samples = generateSampleList(data.normal_project_data.samples, data.normal_project_data.meta_data.data, library_type);
			normal_samples.forEach(function(sample) {
				gene_exp = [];
				gene_list.forEach(function(gene) {
					var value = parseFloat(data.normal_project_data.exp_data[gene][target_type][sample.index]);
					var log2_value = Math.log2(value + 1);
					if (value_type == "log2")
						value = log2_value;
					if (value_type == "zscore" || value_type == "zscore_normal")
						value = (log2_value - mean[gene]) / std[gene];
					if (value_type == "mcenter" || value_type == "mcenter_normal")
						value = (log2_value - median[gene]);
					gene_exp.push(value.toFixed(2));					
				});
				exp_data.push(gene_exp);
				sample_list.push(sample.sample_name);
			});
		}

		//z : sample annotation
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
				if (idx) {
					if (data.tumor_project_data.meta_data.data[sample_name] == null)
						console.log("===============================not found!!! " + sample_name);
					sample_meta[attr_name].push(data.tumor_project_data.meta_data.data[sample_name][idx]);
				}
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
		console.log(JSON.stringify(data.tumor_project_data.samples));
		console.log("===== sample meta =====");
		console.log(JSON.stringify(sample_meta));
		console.log("===== sample list =====");
		console.log(JSON.stringify(sample_list));
		console.log("===== gene list =====");
		console.log(JSON.stringify(gene_list));
		console.log("===== exp data =====");
		console.log(JSON.stringify(exp_data));
		*/
		return {z: sample_meta, y: {vars: sample_list, smps: gene_list, data: exp_data}, x:gene_meta};
	}

	function getSetting(_search_type = "") {		
		var setting = {
						'annotation' : $('#selTargetType').val(),
						'gene_list' : gene_list,
						'chr' : chr,
						'start_pos' : start_pos,
						'end_pos' : end_pos,
						'search_type' : search_type,
						'library_type' : $('#selLibType').val(),
						'target_type' : $('#selTargetType').val(),
						'value_type' : $('#selValueType').val(),
						'norm_type' : $('#selNormType').val(),
						'color_scheme' : $('#selColor').val(),
						'cluster_genes' : $('#clusterGenes').is(":checked"),
						'cluster_samples' : $('#clusterSamples').is(":checked"),
					};
		if (_search_type == "gene_list") {
			setting.gene_list = $('#txtGeneList').val();
			setting.search_type = _search_type;
		}
		if (_search_type == "locus") {
			setting.chr = $('#selChr').val();
			setting.start_pos = $('#start_pos').val();
			setting.end_pos = $('#end_pos').val();
			setting.search_type = _search_type;
		}
		return setting;		
		
	}

	function savePNG() {
		//var colLabel = plot_data.y.vars;
		d3.select("CL0031_T_T").style("fill", "blue");
		d3.selectAll(".colLabel").each(function (d) { if (d == "CL0031_T_T") d3.select(this).style("fill","blue");});
		
	}


  </script>
<div id="celltooltip" class="hidden">
        <H6><span id="value"></H6>
</div>
<span id="row_highlighter" style="display:none; opacity: 0.2;"></span>
<span id="col_highlighter" style="display:none; opacity: 0.2;"></span>
<div class="container-fluid">
	<div class="row">
		<div class="col-md-2">
			<div class="panel panel-primary">
				<div class="panel-heading">
					<h3 class="panel-title">Heatmap setting</h3>
				</div>
				<div class="panel-body">
					<!--input type="checkbox" id='clusterGenes' class="plotInput" value="" {{($setting->cluster_genes=="true")? 'checked': ""}}>Cluster genes</input><br>
					<input type="checkbox" id='clusterSamples' class="plotInput" value="" {{($setting->cluster_samples=="true")? 'checked': ""}}>Cluster samples</input><br-->
					<!--label for="selTargetType">Annotation:</label-->
					<select id="selTargetType" class="form-control" style="display:none">
						@foreach ($target_types as $target_type)
							<option value="{{$target_type}}" {{($setting->target_type==$target_type)? "selected": ""}}>{{strtoupper($target_type)}}</option>
						@endforeach						
					</select>
					<label for="selNormType">Normalized by:</label>
					<select id="selNormType" class="form-control">
						<option value="tmm-rpkm" {{($setting->norm_type=="tmm-rpkm")? "selected": ""}}>TMM-FPKM</option>
						<option value="tpm" {{($setting->norm_type=="tpm")? "selected": ""}}>TPM</option>
					</select>
					<!--div class="checkbox">
	  					<label><input type="checkbox" id='showProject' checked>Show all project</label>
					</div>
					<div class="checkbox">
	  					<label><input type="checkbox" id='showNormalProject' checked>Show normal project</label>
					</div-->
					<label for="selValueType">Value type:</label>
					<select id="selValueType" class="form-control plotInput">
							<option value="log2">Log2</option>
							<option value="zscore" >Z-score</option>
							<option value="mcenter">Median Centered</option>
							<!--option value="zscore_normal">Z-score by normal samples </option-->
							<!--option value="mcenter_normal">Median Centered by normal samples</option-->
					</select>
					<!--label for="selLibType">Library type:</label-->
					<select id="selLibType" class="form-control" style="display:none">
						<option value="all">All</option>
						<option value="polya">PolyA</option>
						<option value="nonpolya">Non-PolyA</option>
					</select>
					<label for="selSampleMeta">Sample metadata:</label>
					<select id="selSampleMeta" class="form-control">							
					</select>
					<label for="selColorScheme">Color Scheme:</label>
					<select id="selColorScheme" class="form-control">
						<option value="0">Green-Red</option>
						<option value="1">Green-Black-Red</option>
						<option value="2">Black-Red</option>
					</select>
					<!--label for="selColor">Color scheme:</label>
					<select id="selColor" class="form-control plotInput">
							<option value="0">Green-Red</option>
							<option value="1">Blue-Green</option>							
					</select>
					<br>
					<label for="selColor">Color range:</label>
					<div class="input-group">
  						<span class="input-group-addon">Min</span>
  						<input type="number" min="0" id="colorMin" class="form-control">						
  						<span class="input-group-addon">Max</span>
  						<input type="number" min="0" id="colorMax" class="form-control">
					</div-->
					<hr>
					<!--a href='javascript:savePNG();' role="button" class="btn btn-primary">
						<span class="glyphicon"></span> Export PNG
					</a-->					
				</div>
			</div>
			@if ($gene_id == "")
			<div class="panel panel-primary">
				<div class="panel-heading">
					<h3 class="panel-title">Gene set</h3>
				</div>
				<div class="panel-body">					
					<ul class="nav nav-tabs">
						<li {{($setting->search_type=="gene_list")? 'class="active"': ""}}><a data-toggle="tab" href="#gene_symbols">Gene symbols</a></li>											
						<!--li {{($setting->search_type=="locus")? 'class="active"': ""}}><a data-toggle="tab" href="#locus">Locus</a>						
						</li-->
					</ul>
					<div class="tab-content">
						<div id="gene_symbols" class="tab-pane fade {{($setting->search_type=="gene_list")? 'in active': ""}}">
							<br>
							<label for="selGeneSet">Select gene sets:</label>
							<select id="selGeneSet" class="form-control">
									<option value="-1">User defined list</option>
									<option value="0">p53 signaling (6 genes)</option>
									<option value="1">Prostate Cancer (30 genes)</option>
									<option value="2">Glioblastoma (17 genes) </option>
									<option value="3">DNA Damage Response (12 genes)</option>
							</select>
							<textarea class="form-control" rows="6" id="txtGeneList">{{$setting->gene_list}}</textarea>
							<br>
							<button id="btnGene" class="btn btn-primary">Submit</button>
						</div>
						<div id="locus" class="tab-pane fade {{($setting->search_type=="locus")? 'in active': ""}}">
							<br>
							<label for="selChr">Chromosome:</label>
							<select id="selChr" class="form-control">
								@for ($i = 1; $i <= 22; $i++)
									<option value="chr{{$i}}" {{("chr".$i == $setting->chr)? "selected": ""}}>chr{{$i}}</option>
								@endfor
								<option value="chrX">chrX</option>
							</select>
							<label for="start_pos">From:</label>
							<input type="text" class="form-control" id="start_pos" value="{{$setting->start_pos}}"/>
							<label for="end_pos">To:</label>
							<input type="text" class="form-control" id="end_pos" value="{{$setting->end_pos}}"/>
							<br>
							<button id="btnLocus" class="btn btn-primary">Submit</button>
						</div>						
					</div>					
				</div>
			</div>
			@endif
			
		</div>
		<div class="col-md-10" style="display: inline-block;">
			<div id='loadingHeatmap' style="display:none">
				<img src='{{url('/images/ajax-loader.gif')}}'></img>
			</div>
			<div id='status' style="display:none">
				<h2>No results!</h2>				
			</div>
			<div id="chart" style='overflow:auto; width:100%; '></div>								
			<!--canvas id='exp_plot'></canvas-->
		</div>
	</div>
</div>
