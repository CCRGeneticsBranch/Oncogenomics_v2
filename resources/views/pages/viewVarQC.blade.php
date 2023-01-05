{{ HTML::style('packages/w2ui/w2ui-1.4.min.css') }}
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
{{ HTML::style('packages/jquery-easyui/themes/bootstrap/easyui.css') }}
{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}
{{ HTML::style('packages/bootstrap-switch-master/dist/css/bootstrap3/bootstrap-switch.css') }}
{{ HTML::style('css/filter.css') }}
{{ HTML::style('packages/tooltipster-master/dist/css/tooltipster.bundle.min.css') }}
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

<style>

.block_details {
    display:none;
    width:90%;
    border-radius: 10px;
	border: 2px solid #73AD21;
	padding: 10px; 
	margin: 10px; 
	overflow: auto; 
}


</style>

<script type="text/javascript">
	
	var hide_cols = {"tblGenoTyping" : [], "tblDNAQC" : [0,2,12,13,14,17,18,19,26], "tblRNAQC" : [2]};
	var options = [];
	var tbls = [];
	var column_tbls = [];
	var col_html = [];
	var patient_state = 'collapse';
	var genotyping_state = 'collapse';
	var qc_cutoff_state = 'collapse';
	var tblGenotypingHistory = null;
	var hotspot_data;
	var sample_list=[];
	$(document).ready(function() {
		var width = $('#tabQC').width() * 0.95;
		var height = $('#tabQC').height() * 0.80;
		//$('#circos_plot').attr("width", width);
		$('#circos_plot').attr("height", height);
		$('#coverage_plot').attr("height", height);
		$('#trans_cov_plot').attr("height", height);
		$('#hotspot_plot').attr("height", height);
		$("a.img_group").fancybox();
		
		console.log('{{url("/getQCPlot/$patient_id/$case_id/circos")}}');
		@if ($has_geno)
		$.ajax({ url: '{{url('/getPatientGenotyping')}}' + '/' + '{{$patient_id}}' + '/{{$case_id}}/{{$project_id}}', async: true, dataType: 'json', success: function(data) {
				console.log(data.cols.length);
				if (data.cols.length == 1) {
					
					return;
				}
				console.log(data);
				showTable(data, 'tblGenoTyping');				
			}			
		});
		@endif

		var url = '{{url('/getHotspotCoverage')}}' + '/' + '{{$patient_id}}' + '/{{$case_id}}' + '/{{$project_id}}';
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				hotspot_data = JSON.parse(data);
				
				var sample_list = [];		
				for (var sample_name in hotspot_data) {
					console.log(sample_name);
					sample_list.push(sample_name);
					$("#sample_list").append("<H5><input id='hotspot" + sample_name + "' type='checkbox' class='hotspot_sample' checked>" + sample_name + "</input></H5>")
				}
				if (sample_list.length == 0) {
					$("#hotspot").css("display", "none");
					var t = $('#tabQC').tabs('getTab','Hotspot');
					t.panel('options').tab.hide();
					return;
				}
				
				showHotspotCoverage();
				$(".hotspot_sample").on('change', function() {
					showHotspotCoverage();
				});
			}		
		});
		var khanlab_version_url = '{{url("/getpipeline_summary/")}}' + '/' + '{{$patient_id}}'+'/{{$case_id}}';
		if('{{$case_id}}' != "any"){
			console.log(khanlab_version_url);
			$.ajax({ url: khanlab_version_url, async: true, dataType: 'text', success: function(version_data) {
				version_data = JSON.parse(version_data);
				if (version_data.data.length == 0) {
					var t = $('#tabQC').tabs('getTab','Khanlab versions');
					t.panel('options').tab.hide();
					return;				
				}
				showTable(version_data, 'khanlab_versions');
				}
			});
			var avia_version_url = '{{url("/getAvia_summary")}}';
			console.log(avia_version_url);
			$.ajax({ url: avia_version_url, async: true, dataType: 'text', success: function(version_data) {
				version_data = JSON.parse(version_data);
				showTable(version_data, 'avia_versions');
				}
			});
		}

		var cov_url = '{{url("/getCoveragePlotData/$project_id/$patient_id/$case_name/all")}}';
		console.log(cov_url);
		$.ajax({ url: cov_url, async: true, dataType: 'text', success: function(json_data) {
				json_data = parseJSON(json_data);
				if (json_data.coverage_data.length == 0) {
					$("#coverage").css("display", "none");
					var t = $('#tabQC').tabs('getTab','Coverage');
					t.panel('options').tab.hide();
					return;
				}
				var sample_list_coverage = [];		
				for (i = 0; i < json_data.samples.length; i++) {
					$("#coverage_sample_list").append("<H5><input id='" + json_data.samples[i]+ "' type='checkbox' class='coverage_sample' checked>" + json_data.samples[i] + "</input></H5>");
					sample_list_coverage.push(json_data.samples[i]);
				}				
				drawLinePlot("cov_lineplot", "{{$case_id}}", sample_list_coverage, json_data.coverage_data )
				$(".coverage_sample").on('change', function() {
					drawLinePlot("cov_lineplot", "{{$case_id}}", sample_list_coverage, json_data.coverage_data);
				});
			}
		});
		@if ($qc_cnt["dna"] > 0)
		$.ajax({ url: '{{url('/getQC')}}' + '/' + '{{$patient_id}}' + '/' + '{{$case_id}}' + '/dna/' + '{{$project_id}}', async: true, dataType: 'text', success: function(data) {
				data = JSON.parse(data);
				if (data.qc_data.cols.length == 1) {
					return;
				}
				if (data.qc_data.length == 0) {
					return;
				}
				showTable(data.qc_data, 'tblDNAQC');
				//showTable(data.qc_cutoff, 'tblDNAQCCutoff');
				var tbl = $('#tblDNAQCCutoff').DataTable( {"data": data.qc_cutoff.data,"columns": data.qc_cutoff.cols});
			}
		});
		@endif

		@if ($qc_cnt["rna"] > 0)
		$.ajax({ url: '{{url('/getQC')}}' + '/' + '{{$patient_id}}' + '/' + '{{$case_id}}' + '/rna' + '{{$project_id}}', async: true, dataType: 'text', success: function(data) {
				data = JSON.parse(data);
				if (data.qc_data.cols.length == 1) {
					return;
				}
				if (data.qc_data.data.length == 0) {
					return;
				}
				showTable(data.qc_data, 'tblRNAQC');
			}
		});
		@endif		
		@if (isset($qc_cnt["rnaV2"]))
			@if ($qc_cnt["rnaV2"] > 0)
			var url = '{{url('/getQC')}}' + '/' + '{{$patient_id}}' + '/' + '{{$case_id}}' + '/rnaV2/' + '{{$project_id}}';
			console.log(url);
			$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
					data = JSON.parse(data);
					if (data.qc_data.cols.length == 1) {
						return;
					}
					if (data.qc_data.data.length == 0) {
						return;
					}
					showTable(data.qc_data, 'tblRNAQCV2');
				}
			});
			@endif
		@endif
		$("#btnSave").click(function() {
			var log = {patient_id: '{{$patient_id}}', case_id: '{{$case_id}}', log_type: 'genotyping', log_decision: $('input[name=decision]:checked').val(), log_comment: $('#txtComment').val()};
			var url = '{{url("/saveQCLog")}}';
			$.ajax({ url: url, async: true, type: 'POST', dataType: 'text', data: log, success: function(data) {
					if (data == "NoUserID")
						alert("Please login first!");
					else if (data == "Success") {
						$('#txtComment').val("");						
						getHistoryData();
						alert("Save successful!");
					}
					else
						alert("Save failed: reason:" + data);
				}, error: function(xhr, textStatus, errorThrown){
					//console.log('save failed! Reason:' + JSON.stringify(xhr) + ' ' + errorThrown);
					alert('save failed! Reason:' + JSON.stringify(xhr) + ' ' + errorThrown);
				}
			});		
		});

		getHistoryData();

	});
function drawLinePlot(div_id, title, sample_list_coverage, coverage_data ) {	
		
		var chart=$('#' + div_id).highcharts({
			credits: false,
	        chart: {
	            type: 'line',
	            zoomType: 'xy'
	        },

	        title: {
	            text: title+" Target Region Coverage"
	        },

	        legend: {
	            enabled: true
	        },
	        xAxis: {
	        	type: 'logarithmic',
	        	title: {
	                text: 'Coverage',
	                style: {
	                    fontSize:'18px'
	                }
	            },

	            labels: {
	                style: {
	                    fontSize:'15px'
	                }
	            },
        	
	        },

	        yAxis: {

	            title: {
	                text: 'Fraction of Capture target bases >= depth',
	                style: {
	                    fontSize:'12px'
	                }
	            },
	            labels: {
	                style: {
	                    fontSize:'15px'
	                }
	            },
	            floor : 0,
	            tickInterval: .1,
    			min: 0,
    			max: 1,	            
	        },

	        

	    });

    	var chart = $('#' + div_id).highcharts();
    	for (i = 0; i < sample_list_coverage.length; i++) {
    		if (!$("#" + sample_list_coverage[i]).is(":checked"))
    			continue;
    		chart.addSeries({
        		name: sample_list_coverage[i],
	    		data: coverage_data[i],
   			 });
    	} 



	}
	function showHotspotCoverage() {
		var idx = 0;
		var box_values = [];
		var outliers = [];
		var sample_list = [];		

		for (var sample_name in hotspot_data) {
			if (!$("#hotspot" + sample_name).is(":checked"))
				continue;
			var cov = [];
			var hotspots = [];
			sample_list.push(sample_name);
			hotspot_data[sample_name].forEach(function (d){
				cov.push(+d[2]);
				hotspots.push(sample_name + ' ' + d[0] + ' ' + d[1]);
			})
	    	var box_value = getBoxValues(cov, idx, hotspots);
	    	box_values.push(box_value.data);
	    	if (box_value.outliers != null)
				outliers = outliers.concat(box_value.outliers);
			idx++;	    			
		}		
		drawBoxPlot('hotspot_cov_boxplot', '{{$patient_id}}', sample_list, box_values, outliers );
	}
	
	function getBoxValues(data, series_idx, names) {
        //if (data.length == 1)
        //   return {data:[data[0], data[0], data[0], data[0], data[0]], outliers:[]]};
        var q1     = getPercentile(data, 25);
        var median = getPercentile(data, 50);
        var q3     = getPercentile(data, 75);
                
        //var low    = Math.min.apply(Math,data);    
        //var high   = Math.max.apply(Math,data);        
        var low    = getMin(data);
        var high    = getMax(data);
        low    = Math.max(low, q1-(q3-q1)*1.5);
        high    = Math.min(high, q3+(q3-q1)*1.5);
        var outliers = [];
        data.forEach(function(d, idx) {
            if (d > high || d < low) {
            	var point_data = names[idx].split(' ');
              	outliers.push({gene:point_data[1], aachange: point_data[2], x:series_idx, y:d});
            }
        });
        return {data:[low, q1, median, q3, high], outliers:outliers};
    }


	function drawBoxPlot(div_id, title, sample_list, box_values, outliers ) {		
	    $('#' + div_id).highcharts({
			credits: false,
	        chart: {
	            type: 'boxplot',
	            zoomType: 'xy'
	        },

	        title: {
	            text: title
	        },

	        legend: {
	            enabled: false
	        },
	        xAxis: {
	            categories: sample_list,
	            labels: {
	                style: {
	                    fontSize:'15px'
	                }
	            }
	        },

	        yAxis: {
	            title: {
	                text: 'Coverage',
	                style: {
	                    fontSize:'18px'
	                }
	            },
	            labels: {
	                style: {
	                    fontSize:'15px'
	                }
	            },
	            floor : 0	            
	        },

	        series: [{
	            name: 'Coverage',
	            data: box_values,
	            tooltip: {
	                headerFormat: '<em> {point.key}</em><br/>'
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
            		pointFormat: '<b>Gene: </b>{point.gene}<br><b>AA Change: </b>{point.aachange}<br><b>Coverage: </b>{point.y}'
		        	
		    	}
	        }]

	    });
	}

	function getFirstProperty(obj) {
		for (key in obj) {
			if (obj.hasOwnProperty(key))
				return key;
		}
	}	
	
	function switch_details(state, block_class, image_id) {
		if ( state === 'collapse' ) {
			state = 'expand';
			$("#" + block_class).css("display","block");
			$("#" + image_id).attr("src",'{{url('/images/details_close.png')}}');
		}
		else {
			state = 'collapse';
			$("#"  + block_class).css("display","none");
			$("#"  + image_id).attr("src",'{{url('/images/details_open.png')}}');
    	}
    	return state;
	}

	function getHistoryData() {
		var url = '{{url("/getQCLogs")}}' + '/' + '{{$patient_id}}' + '/' + '{{$case_id}}' + '/genotyping';
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
			data = JSON.parse(data);	
			var order_col = 3;	
			if (data.cols.length == 0) {
				data = {cols:[{'title':'No data found'}],data:[['.']]};
				order_col = 0;
			}	
			if (tblGenotypingHistory != null) {
				tblGenotypingHistory.destroy();
				$('#tblGenotypingHistory').empty();
			}
			tblGenotypingHistory = $('#tblGenotypingHistory').DataTable( 
			{
				"data": data.data,
				"columns": data.cols,
				"order": [[ order_col, "desc" ]]
			});
		}});
	}

	function showTable(data, tblId) {		
		var tbl = $('#' + tblId).DataTable( 
		{
			"data": data.data,
			"columns": data.cols,
			"ordering":    true,
			"deferRender": true,
			"lengthMenu": [[25, 50, 100], [25, 50, 100]],
			"pageLength":  50,
			"pagingType":  "simple_numbers",
			//"paging" : false,
			"dom": 'Bl<"toolbar">frtip',
			"buttons": [
        		{
            		text: '<img width=15 height=15 src={{url("images/download.svg")}}></img>&nbsp;Download',
            		extend:'csv',
            		title: "{{$patient_id}}+'_'+genoTyping"
        		},

   			],
			"columnDefs": [{
                			"render": function ( data, type, row ) {
                						if (tblId != 'tblGenoTyping')
                							return data;                						
                						if (isNaN(data))
                							return data;
                						else {
                							//color_value = getColor(data);
                							data = parseFloat(data);
                							if (data >= 0.9)
                								return data;
                							if (data < 0.9 & data >= 0.8)
                								return "<b><font color='purple' style='background-color:yellow'>" + data + '</font></b>';
                							if (data < 0.8)
                								return "<b><font color='red' style='background-color:yellow'>" + data + '</font></b>';
                							color_value = "red";
                							
                    						return $("<div></div>", {"class": "bar-chart-cell"}).append(function () {
                    													var bars = [];
                    													bars.push($("<div></div>",{"class": "bar"}).text(Math.round((data * 100)) + '%').css({"width": (data * 100) + '%', "background-color" : color_value}));
                    													return bars;
                    											}).prop("outerHTML");
                    								}

                						},
                			"targets": '_all'
            				}]					
		} );		
		tbls[tblId] = tbl;
		var columns =[];
		col_html[tblId] = '';
		
		//$("div.toolbar").html('<button id="popover" data-toggle="popover" data-placement="bottom" type="button" class="btn btn-default" style="font-size: 12px;">Select Columns</button>');
		
		$("#" + tblId + "_wrapper").children("div.toolbar").html('<button id="' + tblId + '_popover" data-toggle="popover" data-placement="bottom" type="button" class="btn btn-default" style="font-size: 12px;">Select Columns</button>');
		//$("#" + tblId + "_wrapper").children("div.toolbar").html(tblId);
		tbl.columns().iterator('column', function ( context, index ) {
			var show = true;
			if (hide_cols[tblId] != undefined)
				show = (hide_cols[tblId].indexOf(index) == -1);
			tbl.column(index).visible(show);
			columns.push(tbl.column(index).header().innerHTML);
			checked = (show)? 'checked' : '';
			//checked = 'checked';
			col_html[tblId] += '<input type=checkbox ' + checked + ' class="onco_checkbox data_column" id="data_column_' + tblId + '" value=' + index + '><font size=3>&nbsp;' + tbl.column(index).header().innerHTML + '</font></input><BR>';
		});
		column_tbls[tblId] = columns;
	        
		$("#" + tblId + "_popover").popover({				
				title: 'Select column <a href="#" class="close" data-dismiss="alert">×</a>',
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
				//console.log(tblId);
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

	}

	function do_switch(type) {
		if (type == "genotyping")
			genotyping_state = switch_details(genotyping_state, 'genotyping_history', 'imgGenotypingHistory');
		if (type == "qc_cutoff")
			qc_cutoff_state = switch_details(qc_cutoff_state, 'qc_cutoff', 'imgQCCutoff');
	}
	

</script>

<div id="tabQC" class="easyui-tabs" data-options="tabPosition:'top',fit:true,plain:true,false:true,border:false,headerWidth:170" style="width:98%;padding:0px;overflow:visible;border-width:0px">
			@if ($case_id != "any")
				<div title="Avia versions">
						<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="avia_versions" style='width:100%'>
						</table> 
				</div>
				<div title="Khanlab versions">
						<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="khanlab_versions" style='width:100%'>
						</table> 
				</div>
				@if (count($metrics_tables) > 0)
				<div title="TSO Metrics">
					<H4>TruSight Oncology 500 - Metrics Output</H4><H4>For Research Use Only. Not for use in diagnostic procedures.</H4>
					@foreach ($metrics_tables as $metrics_table)
						<hr>
						<H4>{{$metrics_table["name"]}}</H4>
						<table cellpadding="0" cellspacing="0" border="1" class="pretty" word-wrap="break-word">
						@foreach ($metrics_table["data"] as $row)
						<tr>
							@foreach ($row as $item)
								@if (count($row) < $metrics_table["max_col"])
									<td colspan="{{$metrics_table["max_col"] - count($row) + 1}}">{{$item}}</td>
								@else
									<td>{{$item}}</td>
								@endif
							@endforeach							
						</tr>
						@endforeach	
						</table>
					@endforeach
						<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="avia_versions" style='width:100%'>
						</table> 

				</div>
				@endif
				@if ($has_circos)
				<div title="Circos" style="width:98%;padding:10px;">
					<img id='circos_plot' src='{{url("/getQCPlot/$patient_id/$case_id/circos")}}' style="width:800px;height:800px;"></img>
				</div>
				@endif
				<div title="Coverage" id="coverage">
					<div class="container-fluid">
						<div class="row">
							<div class="col-md-3">
								<div class="panel panel-primary">
									<div class="panel-heading">
										<h3 class="panel-title">Samples</h3>
									</div>
									<div id="coverage_sample_list" class="panel-body"></div>
								</div>
							</div>
							<div class="col-lg-9 col-md-9 col-sm-9 col-md-9">
								<div class="panel panel-primary">
									<div class="panel-heading">
										<h3 class="panel-title">Coverage</h3>
									</div>
									<div class="panel-body">
										<div id='cov_lineplot' style="height:600px"></div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>				
				@if ($qc_cnt["rna"] > 0 || $qc_cnt["rnaV2"] > 0)
					<div title="Transcript Coverage" id="transcript_coverage">
						<img id='trans_cov_plot' src='{{url("/getQCPlot/$patient_id/$case_id/transcriptCoverage")}}' style="width:800px;height:800px;"></img>						
					</div>
				@endif
				<div title="Hotspot" id="hotspot">
					<div class="container-fluid">
						<div class="row">
							<div class="col-md-2">
								<div class="panel panel-primary">
									<div class="panel-heading">
										<h3 class="panel-title">Samples</h3>
									</div>
									<div id="sample_list" class="panel-body">
									</div>
								</div>
							</div>
							<div class="col-md-9">
								<div class="panel panel-primary">
									<div class="panel-heading">
										<h3 class="panel-title">Hotspot Coverage</h3>
									</div>
									<div class="panel-body">
										<div id='hotspot_cov_boxplot' style="height:600px; width:800px"></div>
										<!--img id='hotspot_plot' src='{{url("/getQCPlot/$patient_id/$case_id/hotspot_coverage")}}'></img-->	
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			@endif
				@if (count($cnv_samples) > 0)
					<div id="Contours" title="Contours" style="width:98%;padding:5px;">				
						<div id="tabCNV" class="easyui-tabs" data-options="tabPosition:top,fit:true,plain:true,pill:true" style="width:98%;padding:10px;overflow:visible;">
							@foreach ($cnv_samples as $sample_name => $sample_case_id)
								<div id="{{$sample_name}}" title="{{$sample_name}}">
									<object data="{{url("/getCNVPlot/$patient_id/$sample_name/$sample_case_id/CP_contours")}}" type="application/pdf" style="width:98%;height:800px"></object>
								</div>
							@endforeach
						</div>	
					</div>						
				@endif
				@if (count($conpair_samples) > 0)
					<div id="Conpair" title="Conpair" style="width:98%;padding:5px;">				
						<div id="tabCNV" class="easyui-tabs" data-options="tabPosition:top,fit:true,plain:true,pill:true" style="width:98%;padding:10px;overflow:visible;">
							@foreach ($conpair_samples as $sample_name => $content)
								<div id="{{$sample_name}}" title="{{$sample_name}}">
									<pre style="font-size:15px">{{$content}}</pre>
								</div>
							@endforeach
						</div>	
					</div>						
				@endif
				@if ($qc_cnt["dna"] > 0)	
				<div title="DNA QC">
					<div style="height:98%;overflow:auto;padding:10px">
						<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblDNAQC" style='width:95%;'>
						</table>
						<a href=javascript:do_switch('qc_cutoff');><img id="imgQCCutoff" style="width:20px;height:20px" src='{{url('/images/details_open.png')}}'></img></a> QC threshold
						<div id="qc_cutoff" class="block_details" style="width:98%;height:450px;">
							<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblDNAQCCutoff" style='width:95%;height:80%'></table>
						</div>	 						
					</div>				
				</div>
				@endif
				@if ($qc_cnt["rna"] > 0)	
				<div title="RNA QC">
					<div style="height:98%;overflow:auto;;padding:10px">
						<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblRNAQC" style='width:95%;'>
						</table> 			
					</div>				
				</div>
				@endif
				@if (count($rnaqc_samples) > 0)
				<div title="RNA QC V2">

					<div id="tabRNAQC" class="easyui-tabs" data-options="tabPosition:top,fit:true,plain:true,pill:true" style="width:98%;padding:10px;overflow:visible;">
						@foreach ($rnaqc_samples as $rnaqc_sample => $rnaqc_path)
							<div id="{{$rnaqc_sample}}" title="{{$rnaqc_sample}}">
								<div style="overflow:auto;padding:10px">
									<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblRNAQCV2" style='width:95%;'></table> 			
								</div>
								<object data="{{url("/viewrnaQC/$patient_id/$case_id/$rnaqc_path")}}" type="text/html" width="100%" height="100%"></object>
							</div>
						@endforeach
					</div>
				</div>
				@endif
				@if (count($fastqc_samples) > 0)
					<div id="FASTQC" title="FASTQC" style="width:98%;padding:5px;">				
						<div id="tabFASTQC" class="easyui-tabs" data-options="tabPosition:top,fit:true,plain:true,pill:true" style="width:98%;padding:10px;overflow:visible;">
							@foreach ($fastqc_samples as $fastqc_sample => $fatqc_path)
								<div id="{{$fastqc_sample}}" title="{{$fastqc_sample}}">
									<object data="{{url("/viewFASTQC/$patient_id/$case_id/$fatqc_path")}}" type="text/html" width="100%" height="100%"></object>
								</div>
							@endforeach
						</div>	
					</div>						
				@endif
			@if ($case_id != "any")
				@if ($has_geno)
				<div id="Genotyping" title="Genotyping" style="width:98%;padding:5px;">				
					<!--div id="tabGenotyping" class="easyui-tabs" data-options="tabPosition:top,fit:true,plain:true,pill:true" style="width:98%;padding:10px;overflow:visible;"-->
						<div class="comment">
							<table cellpadding="20">
							<tr><td>
		        			<div>
		            			<input id="radio-1" class="radio-custom" name="decision" type="radio" value="pass" checked>
		            			<label for="radio-1" class="radio-custom-label">Pass</label>
		        			</div>
		        			<div>
					            <input id="radio-2" class="radio-custom"name="decision" type="radio" value="fail">
					            <label for="radio-2" class="radio-custom-label">Fail</label>
					        </div>
					        </td>
					        <td>
					        	<textarea id="txtComment" rows=6 cols=50 placeholder="Comment..."></textarea>
					        	<a href="#" id="btnSave" class="btn btn-success" >Save</a>
					        </td>					        					        
					        </tr></table>					        
					        <a href=javascript:do_switch('genotyping');><img id="imgGenotypingHistory" style="height:20" src='{{url('/images/details_open.png')}}'></img></a>History
							<div id="genotyping_history" class="block_details">
								<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblGenotypingHistory" style='width:100%;overflow:auto'></table>
							</div>							
							<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblGenoTyping" style='width:100%'>
							</table>
						</div>						
					<!--/div-->
				</div>
				@endif								
			@endif

				
</div>		

