@extends(($include_header=="Y")? 'layouts.default' : 'layouts.noheader')
@section('content')


{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') }}
{{ HTML::style('packages/jquery-easyui/themes/bootstrap/easyui.css') }}
{{ HTML::style('css/heatmap.css') }}
{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::style('css/light-bootstrap-dashboard.css') }}

{{ HTML::script('js/jquery-3.6.0.min.js') }}
{{ HTML::script('packages/DataTables-1.10.8/media/js/jquery.dataTables.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/dataTables.buttons.min.js') }}
{{ HTML::script('packages/yadcf-0.8.8/jquery.dataTables.yadcf.js')}}
{{ HTML::script('packages/highchart/js/highcharts.js')}}
{{ HTML::script('packages/highchart/js/highcharts-more.js')}}
{{ HTML::script('packages/highcharts-regression/highcharts-regression.js')}}
{{ HTML::script('packages/highchart/js/modules/exporting.js')}}

<script type="text/javascript">
	var survival_plot = null;
	var survival_data = null;
	var pvalue_data;
	var selected_pvalue = 0;
	console.log("show survival...");
	$(document).ready(function() {
		console.log("show survival");
		showSurvivalPlot('{{$symbol}}', 'gene');	

		$('#selColor').on('change', function() {
			setColor($(this).val(), 'black', combine_plot)
		});

		$('#gene_id').keyup(function(e){
			if ($('#gene_id').val() == "")
				return;
			if(e.keyCode == 13) {
        		window.location.replace("{{url('/viewSurvivalByExpression')}}" + "/{{$project->id}}/" + $('#gene_id').val() + "/{{$show_search}}"  + "/{{$include_header}}");
    		}
		});	
		
		$('.surv').on('change', function() {
			showSurvivalPlot('{{$symbol}}', 'gene');
		});

		$('#gene_id').focus();
		
	});


	function getAttrHtml(attrs) {
		var html = '';
		for (var attr in attrs) {
			html += '<b>' + attr + ': </b>' + attrs[attr] + '<br>';
		}
		return html;
	}


	function showSurvivalPlot(target_id, level) {
		$("#loadingAllSurvival").css("display","block");
		$("#survival_status").css("display","none");
		$("#survival_panel").css("visibility","hidden");
		var data_type = $("#selSurvType").val();
		var target_type = 'ensembl';
		var value_type = $("#selSurvNorm").val();
		var diag = $("#selSurvDiagnosis").val();
		url = '{{url("/getExpSurvivalData/".$project->id)}}' + '/' + target_id + '/' + level + '/null/' + target_type + '/' + data_type + '/' + value_type + '/' + encodeURIComponent(encodeURIComponent(diag));
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				$("#loadingAllSurvival").css("display","none");
				$("#survival_panel").css("visibility","visible");
				if (data == "only one group" || data == "no data") {
					$("#message_row").css("display","block");
					$("#plot_row").css("display","none");
					return;
				} else {
					$("#message_row").css("display","none");
					$("#plot_row").css("display","block");
				}
				//alert(data);
				survival_data = JSON.parse(data);
				pvalue_data = survival_data.pvalue_data;
				console.log(JSON.stringify(data));
				pvalue_plot_data = getPValuePlotData(pvalue_data, survival_data.user_data.cutoff);				
				showPvalueScatterPlot("pvalue_plot", "P-value Minimization", pvalue_plot_data, "Expression Cutoff (log2)", "P-value", target_type, data_type, value_type, diag);
				showSurvivalCutoffPlot(median_plot, "Median Survival", "Exp cutoff: " + survival_data.median_data.cutoff + ", P-value :" + survival_data.median_data.pvalue, survival_data.median_data.high_num, survival_data.median_data.low_num, survival_data.median_data.data);
				showSurvivalCutoffPlot(user_plot, "User Defined Survival", "Exp cutoff: " + survival_data.user_data.cutoff + ", P-value :" + survival_data.user_data.pvalue, survival_data.user_data.high_num, survival_data.user_data.low_num, survival_data.user_data.data);
				

				//showSurvivalPvaluePlot(survival_data.data);
				//d = new Date();
				//$("#median_plot").attr("src",survival_data.median_plot_url + "?timestamp=" + +d.getTime());
				//$("#min_plot").attr("src",survival_data.min_plot_url + "?timestamp=" + +d.getTime());
				
			}
		});	
	}

	function getPValuePlotData(pvalue_data, mark_cutoff) {
		var pvalue_plot_data = [];
		pvalue_data.forEach(function(d){
			var cutoff = parseFloat(d[0]);
			var pvalue = parseFloat(d[1]);
			var s = 4;
			var lc = 'rgb(119, 152, 191)';
			var fc = 'rgba(119, 152, 191, .1)';
		            
			if (mark_cutoff == cutoff) {
				s = 8;
				lc = 'rgba(223, 83, 83, 1)';
				fc = 'rgba(223, 83, 83, .5)';
			}          
		    pvalue_plot_data.push({x:cutoff, y:pvalue, marker: {
		        radius: s, fillColor:fc, lineColor: lc, lineWidth:1, states: { hover: { radius: s+2, fillColor:lc }}
		    }});

		});
		return pvalue_plot_data;
	}
	function showPvalueScatterPlot(div_id, title, values, x_title="Samples", y_title="Expression", target_type, data_type, value_type, diag) {
        $('#' + div_id).highcharts({
            credits: false,
            chart: {
                type: 'scatter',
                zoomType: 'xy'
            },
            title: {
                text: title,
                style: { "color": "#333333", "fontSize": "14px" }
            },       
            xAxis: {
                title: {
                    enabled: true,
                    text: x_title
                },
                startOnTick: false,
                endOnTick: false
            },
            yAxis: {
            	max: 1,
            	min: 0,
                title: {
                    text: y_title
                }
            },
            
            legend: {
                enabled: false
            },
            
            plotOptions: {
            	series: {
	                cursor: 'pointer',
	                point: {
	                    events: {
	                        click: function (e) {
	                        	if (this.series.name == "pvalue") {
	                        		selected_pvalue = this.y;
	                        		url = '{{url("/getExpSurvivalData/".$project->id)}}' + '/' + '{{$symbol}}' + '/gene/' + this.x + '/' + target_type + '/' + data_type + '/' + value_type + '/' + diag;		
	                            	console.log(url);
	                            	pvalue_plot_data = getPValuePlotData(pvalue_data, this.x);
									showPvalueScatterPlot("pvalue_plot", "P-value Minimization", pvalue_plot_data, "Expression Cutoff (log2)", "P-value", target_type, data_type, value_type, diag);
									$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {										
											survival_data = JSON.parse(data);
											if (data == "only one group" || data == "no data") {
												$("#message_row").css("display","block");
												$("#plot_row").css("display","none");
												return;
											} else {
												$("#message_row").css("display","none");
												$("#plot_row").css("display","block");
											}
											showSurvivalCutoffPlot(user_plot, "User Defined Survival", "Exp cutoff: " + survival_data.user_data.cutoff + ", P-value :" + selected_pvalue, survival_data.user_data.high_num, survival_data.user_data.low_num, survival_data.user_data.data);

										}
									});
	                        	}
	                        }
	                    }
	                },
	                marker: {
	                    lineWidth: 1
	                }
	            },
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
            tooltip: {
            	crosshairs: [true, true],
		        formatter: function(chart) {
		                	var p = this.point;
		                	return '<font color=red>' + x_title + ':</font>' + p.x + '<br>' + '<font color=red>' + y_title + ':</font>' + p.y;		                       
		        }
		    },
            series: [{            	
            	regression: true,
				regressionSettings: {
					type: 'polynomial',
					color: 'rgba(223, 183, 83, .9)',
					dashStyle: 'dash'
				},
                name: 'pvalue',
                //color: 'rgba(223, 83, 83, .5)',
                data: values

            }]
        });
    }

	Highcharts.Renderer.prototype.symbols.cross = function (x, y, w, h) {
		return [
        'M', x + w/2, y, // move to position
        'L', x + w/2, y + h, // line to position
        'M', x, y + h/2, // move to position
        'L', x + w, y + h/2, // line to position
        'z']; // close the shape, but there's nothing to close!!
	}

	function showSurvivalCutoffPlot(div, title, subtitle, high_num, low_num, data) {
		//console.log(JSON.stringify(data));
		var sample_num = {"Low" : low_num, "High" : high_num};
		//var plot_data = {"Low" : [0, 1], "High" : [0, 1]};
		var plot_data = {"Low" : [1], "High" : [1]};
		data.forEach(function(d){
			console.log(d[4][0][0]);
			var s = 5;
			var cencored = (d[3] == 0);
			plot_data[d[2]].push({name: d[4][0][0], cencored: cencored, x:parseFloat(d[0]), y:parseFloat(d[1]), 
					marker: {
                		radius: s, 
                		lineWidth:1,                		
                		states: { hover: { radius: s+2}},
                		enabled : cencored,
                		symbol : 'cross',                		
                	},                	
            });
		});		
		var series = [];
		for (var cat in plot_data) {
			series.push(
				{
					data: plot_data[cat], 
					step: 'left', 					
			 		name: cat + '(' + sample_num[cat] + ')',
			 		marker : {lineColor: null},
			 		cursor: 'pointer',
		            point: {
		               events: {
			                    click: function () {
			                    	var url = '{{url("/viewPatient/")}}' + '/' +  '{{$project->id}}' + '/' + this.name;
									window.open(url, '_blank');
								}
							}                    
					},
			 	});
		}		
		//console.log(JSON.stringify(series));
		Highcharts.chart(div, {
			credits: false,
		    title: {
		        text: title
		    },
		    subtitle: {
		    	text: subtitle
		    },		    
            tooltip: {
            	crosshairs: [true, true],
		        formatter: function(chart) {
		            var p = this.point;
		            var status = (p.cencored)? "Alive" : "Dead";
		            if (p.name == undefined)
		            	return '<b>Survival Rate: </b>' + p.y + ' <br><b>Days: </b>' + p.x;
		            return '<b>Patient ID: </b>' + p.name + '<br><b>Survival Rate: </b>' + p.y + ' <br><b>Days: </b>' + p.x  + ' <br><b>Status: </b>' + status;
		        }
		    },
            series: series
		});
	}

</script>

<html>	
	<body>		
					<div id='loadingAllSurvival'>
					    <img src='{{url('/images/ajax-loader.gif')}}'></img>
					</div>
					<div class="container-fluid" id="survival_panel">
						<div class="row">
							<div class="col-md-12">
								<div class="card" style="padding:8px;margin:0 auto">
									<H5  style="display:inline">&nbsp;&nbsp;Data Type:</H5>
									<select id="selSurvType" class="form-control surv" style="display:inline;width:150px">
										<option value="overall" {{($type=="overall")? "selected" : ""}}>Overall</option>
										<option value="event_free" {{($type=="event_free")? "selected" : ""}}>Event free</option>
									</select>
									<H5  style="display:inline">&nbsp;&nbsp;Normalization:</H5>
									<select id="selSurvNorm" class="form-control surv" style="display:inline;width:150px">
										<option value="tpm">TPM</option>
										<option value="tmm-rpkm">TMM-FPKM</option>										
									</select>
									<H5  style="display:inline">&nbsp;&nbsp;Diagnosis:</H5>
									<select id="selSurvDiagnosis" class="form-control surv" style="display:inline;width:150px">
										<option value="any">All Data</option>
										@foreach ($survival_diagnosis as $diag)
											<option value="{{$diag}}" {{($selected_diagnosis=="$diag")? "selected" : ""}}>{{$diag}}</option>
										@endforeach										
										
									</select>
									@if ($show_search == "Y")
									<H5  style="display:inline">&nbsp;&nbsp;Gene:</H5>
									<input id='gene_id' type='text' value='{{$symbol}}'/>
									@endif
								</div>
							</div>
						</div>
						<br>
						<div id="message_row" class="row" style="display:none">
							<div class="col-md-12">
								<H3>No data or only one group found.</H3>
							</div>
						</div>
						<div id="plot_row" class="row">
							<div class="col-md-4">
								<div class="card">
									<div id='pvalue_plot' style="height:450;width=100%"></div>								
								</div>
							</div>							
							<div class="col-md-4">
								<div class="card">
									<div id='user_plot' style="height:450;width=100%"></div>
								</div>								
							</div>
							<div class="col-md-4">
								<div class="card">							
									<div id='median_plot' style="height:450;width=100%"></div>
								</div>
							</div>							
						</div>
					</div>
		
	</body>
</html>				
@stop

