@extends('layouts.default')
@section('title', "Access Log Summary")
@section('content')

{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::style('css/style.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-core-css.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-blue/sm-blue.css') }}   
{!! HTML::style('css/font-awesome.min.css') !!} 
{{ HTML::script('js/jquery-3.6.0.min.js') }}
{{ HTML::script('packages/smartmenus-1.0.0-beta1/jquery.smartmenus.min.js') }}

{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}

{!! HTML::script('packages/DataTables/datatables.min.js') !!}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') }}
{{ HTML::script('js/onco.js') }}
{!! HTML::script('packages/highchart/js/highcharts.js')!!}

<style>

.card {margin:5px;padding:5px}

.btn {margin:5px;width:100px}

html, body { height:100%; width:100%;}

th {
    white-space: nowrap;
}

.btn-default.active {
    background-color: DarkCyan;
    border-color: #000000;
    color: #fff;
}

</style>
<script type="text/javascript">
	var tbls = [];
	$(document).ready(function() {
		getData();

		$('#selPeriod').on('change', function() {
			getData();			
		});

		$('#selByTime').on('change', function() {
			getData();			
		});

		$('.btnDownload').on('click', function() {
			var url = '{!!url('/downloadAccessLogSummary')!!}' + '/' + $('#selPeriod').val() + '/' + $('#selByTime').val() + '/' + $(this).attr('id').replace('btnDownload','').toLowerCase();
			console.log(url);
			window.location.replace(url);
        });	
	});

	function getData() {
		var url = '{!!url('/getAccessLogSummary')!!}' + '/' + $('#selPeriod').val() + '/' + $('#selByTime').val();
		console.log(url);		
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				//console.log(data);
				var data = JSON.parse(data);
				var total = 0;
				for (var i in data.events) {
					var event = data.events[i];
					total += parseInt(event.cnt);
					$('#lblEvent_' + event.type).text(numberWithCommas(event.cnt));
				}
				$('#lblEvent_total').text(numberWithCommas(total));
				for (var i in data.distinct) {
					var dist = data.distinct[i];
					$('#lblDist_' + dist.type).text(numberWithCommas(dist.cnt));
				}
				$('#lblDist_users').text(numberWithCommas(data.distinct_users));
				var event_by_time_data = [];
				for (var i in data.events_by_time) {
					var event = data.events_by_time[i];
					event_by_time_data.push({name: event.period, y: parseInt(event.cnt)})
				}
				showPlot('events_by_time', 'Total Events', 'Events', event_by_time_data);
				var groups = ["project", "patient", "gene"];
				var colors = ["red", "green", "purple"];
				var max_in_plot = 30;
				for (var i in groups) {
					var group = groups[i];
					console.log(group);
					var event_data = [];
					var n = 0;
					for (var j in data.event_gourps) {
						if (n > max_in_plot)
							break;
						var event = data.event_gourps[j];
						if (event.type == group) {
							n++;
							var target = event.target;
							if (event.type == "project")
								target = event.name;
							event_data.push({name: target, y: parseInt(event.cnt)})
						}
					}
					showPlot('events_by_' + group, firstUpperCase(group), 'Events', event_data, "column", colors[i]);
				}
				var events_by_diagnosis_data = [];
				for (var i in data.event_diagnosis) {
					if (i > max_in_plot)
						break;
					var event = data.event_diagnosis[i];
					events_by_diagnosis_data.push({name: event.diagnosis, y: parseInt(event.cnt)})
				}
				showPlot('events_by_diagnosis', 'Diagnosis', 'Events', events_by_diagnosis_data, "column", "orange");

				var event_project_groups = [];
				for (var i in data.event_project_groups) {
					var event = data.event_project_groups[i];
					event_project_groups.push({name: event.project_group.toUpperCase(), y: parseInt(event.cnt)})
				}
				showPlot('events_project_groups', 'Project Groups Events', 'Events', event_project_groups, "pie");

				var event_email_domain = [];
				for (var i in data.event_email_domain) {
					if (i > max_in_plot)
						break;
					var event = data.event_email_domain[i];
					event_email_domain.push({name: event.email_domain, y: parseInt(event.cnt)})
				}
				showPlot('events_by_email_domain', 'Email Domain', 'Events', event_email_domain, "column", "grey");
				var event_users = [];
				for (var i in data.event_users) {
					if (i > max_in_plot)
						break;
					var event = data.event_users[i];
					event_users.push({name: event.name, y: parseInt(event.cnt)})
				}
				showPlot('events_by_users', 'Users', 'Events', event_users, "column", "blue");
			}			
		});
	}	

	function showTable(data, id) {
		cols = data.cols;		
		tbl = $('#tbl' + id).DataTable( 
		{
				"data": data.data,
				"columns": data.cols,
				"ordering":    true,
				"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
				"pageLength":  15,			
				"processing" : true,			
				"pagingType":  "simple_numbers",			
				"dom": 'lfrtip'
		} );
		tbls[id] = tbl;

		$('#lblCountDisplay' + id).text(tbl.page.info().recordsDisplay);
    	$('#lblCountTotal' + id).text(tbl.page.info().recordsTotal);    	
	}

	function showPlot(div_id, title, y_label, data, type="line", color="blue", click_handler=null) {
        $('#' + div_id).highcharts({
	        credits: false,
	        exporting: {
	              enabled: true
	            },
	        chart: {
	            type: type,
	            borderColor: 'lightgrey',
        		borderRadius: 20,
        		borderWidth: 0
	        },
	        title: {
	            text: title
	        },        
	        xAxis: {
	            type: 'category',
	            labels: {
	                rotation: -65,
	                style: {
	                    fontSize: '12px',
	                    fontFamily: 'Verdana, sans-serif'
	                }
	            }
	        },
	        yAxis: {
	            //min: 0,
	            title: {
	                text: y_label
	            }
	        },
	        legend: {
	            enabled: false
	        },
	        series: [{
	            name: 'Events',
	            data: data,
	            color: color,
	            point: {
	                    events: {
	                        click: function(e) {
	                            if (click_handler != null)
	                                click_handler(this);                            
	                        }
	                    }
	            }
	        }]
	    });
    }		

        

	
</script>

	<div  class="container-fluid" style="padding:5px" >									
		<div class="row">
			<div class="col-md-12">				
				Period: 
				<select class="form-control" id="selPeriod" style="width:400px;display:inline">
					<option value="last30">Last 30 days</option>
					<option value="last12month">Last 12 months</option>
					<option value="this_year">This year(Jan - Today)</option>
					<option value="all" selected>All</option>
				</select>
				</div>
			</div>
		</div>
		<div class="card">
		<div class="row" style="margin:5px;padding:5px">
			<div class="col-md-5">
				<div class="h5 text-primary">Total Events</div><div class="h3 font-weight-bold"><span id="lblEvent_total" text="0"></span></div> 
				<div class="row">					
					<div class="col-md-2">
						<div class="h7 text-primary">Project</div><div class="h7 font-weight-bold"><span id="lblEvent_project" text="0"></span></div>
					</div>
					<div class="col-md-2">
						<div class="h7 text-primary">Patient</div><div class="h7 font-weight-bold"><span id="lblEvent_patient" text="0"></span></div>
					</div>
					<div class="col-md-2">
						<div class="h7 text-primary">Gene</div><div class="h7 font-weight-bold"><span id="lblEvent_gene" text="0"></span></div>
					</div>
				</div>
			</div>
			<div class="col-md-1">
				<div class="h5 text-primary">Users</div><div class="h3 font-weight-bold"><span id="lblDist_users" text="0"></span></div> 
			</div>
			<div class="col-md-1">
				<div class="h5 text-primary">Projects</div><div class="h3 font-weight-bold"><span id="lblDist_project" text="0"></span></div> 
			</div>
			<div class="col-md-1">
				<div class="h5 text-primary">Patients</div><div class="h3 font-weight-bold"><span id="lblDist_patient" text="0"></span></div> 
			</div>
			<div class="col-md-1">
				<div class="h5 text-primary">Genes</div><div class="h3 font-weight-bold"><span id="lblDist_gene" text="0"></span></div> 
			</div>
		</div>
	</div>
	<div class="card">
		<div class="row" style="margin:10px;padding:10px">			
			<div class="col-md-5 card">
				<span style:"display:inline">
					<button id="btnDownloadEvent" class="btn btn-info btnDownload" style="margin:5px"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Details</button>
					By: 
					<select class="form-control" id="selByTime" style="width:200px;display:inline">
						<option value="YYYY">Year</option>
						<option value="YYYY-MM" selected>Month</option>
						<!--option value="YYYY-MM-DD">Day</option-->
					</select>
				</span>
				<div id="events_by_time"></div>
			</div>
			<div class="col-md-5 card"><button id="btnDownloadProject" class="btn btn-info btnDownload" style="margin:5px"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Details</button><div id="events_by_project"></div></div>
		
		</div>	
		<div class="row" style="margin:10px;padding:10px">		
			<div class="col-md-5 card"><button id="btnDownloadProjectGroup" class="btn btn-info btnDownload" style="margin:5px"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Details</button><div id="events_project_groups"></div></div>
			<div class="col-md-5 card"><button id="btnDownloadPatient" class="btn btn-info btnDownload" style="margin:5px"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Details</button><div id="events_by_patient"></div></div>			
		</div>
		<div class="row" style="margin:10px;padding:10px">		
			<div class="col-md-5 card"><button id="btnDownloadDiagnosis" class="btn btn-info btnDownload" style="margin:5px"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Details</button><div id="events_by_diagnosis"></div></div>
			<div class="col-md-5 card"><button id="btnDownloadGene" class="btn btn-info btnDownload" style="margin:5px"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Details</button><div id="events_by_gene"></div></div>
		</div>
		<div class="row" style="margin:10px;padding:10px">		
			<div class="col-md-5 card"><button id="btnDownloadEmailDomain" class="btn btn-info btnDownload" style="margin:5px"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Details</button><div id="events_by_email_domain"></div></div>
			<div class="col-md-5 card"><button id="btnDownloadUser" class="btn btn-info btnDownload" style="margin:5px"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Details</button><div id="events_by_users"></div></div>
		</div>
	</div>
</div>	

@stop
