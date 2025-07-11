@extends('layouts.default')
@section('title', "Chipseq--$patient_id--$sample_id")
@section('content')

{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::style('css/style.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-core-css.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-blue/sm-blue.css') }}    
{{ HTML::script('js/jquery-3.6.0.min.js') }}
{{ HTML::script('packages/smartmenus-1.0.0-beta1/jquery.smartmenus.min.js') }}

{{ HTML::style('css/style_datatable.css') }}
{!! HTML::style('packages/jquery-easyui/themes/bootstrap/easyui.css') !!}
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}

{!! HTML::script('packages/DataTables/datatables.min.js') !!}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') }}
{{ HTML::script('js/onco.js') }}
{!! HTML::script('packages/highchart/js/highcharts.js')!!}

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

#list-circos td {
    border: 1px solid black;
    padding: 10px;

}

th, td { white-space: nowrap; padding: 0px;}
	div.dataTables_wrapper {
		margin: 0 auto;
	}

</style>

<script type="text/javascript">
	var igv_loaded = false;

    function findPointByName(chart, gene) {
      let total_found = 0;
      chart.series[0].points.forEach(point => {
            names = point.name.split(",");
            if (names.indexOf(gene) > -1) {
                total_found++;
                point.update({marker:{radius:12, fillColor: "red", lineColor: "darkred", lineWidth: 1}});
                //point.select(true);
            } else {
                point.update({marker:{radius:5, fillColor: "red", lineColor: "darkred", lineWidth: 1}},false, );
            }
      });
      return total_found;
    }

    function drawScatterPlot(div_id, title, values, x_title="Samples", y_title="Expression", click_handler=null) {
        var chart = new Highcharts.Chart({
            chart: {
                renderTo: div_id,
                type: 'scatter',
                zoomType: 'xy'
            },
            credits: false,
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
                title: {
                    text: y_title
                }
            },
            
            legend: {
                enabled: false
            },
            
            plotOptions: {                
                series: {
                    turboThreshold: 10000,
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
                        pointFormat: '<B>{point.name}:</B><BR>Signal: {point.y}<BR>Rank: {point.rank}'
                    }
                }
            },
            series: [{
                name: '',
                //color: 'rgba(223, 83, 83, .5)',
                data: values,
                cursor: 'pointer',
                point: {
                    events: {
                        click: function() {
                            if (click_handler != null) {
                                click_handler(this);
                            }
                            //console.log(this.patient_id);
                        }
                    }
                }

            }]
        });
        return chart;
    }

	$(document).ready(function() {

        var charts = [];
		@foreach ($annotations as $cutoff => $data)
        var data = JSON.parse('{!!$data!!}');
		var tbl = $('#tblAnnotation_{!!$cutoff!!}').DataTable( 
				{				
                "data": data.data,
                "columns": data.cols,
                "ordering":    true,
                "lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
                "pageLength":  15,          
                "processing" : true,            
                "pagingType":  "simple_numbers",            
                "dom": 'lfrtip'								
				});
        @endforeach

        @foreach ($se as $cutoff => $data)
        var data = JSON.parse('{!!$data!!}');
        var tbl = $('#tblse_{!!$cutoff!!}').DataTable( 
                {               
                "data": data.data,
                "columns": data.cols,
                "ordering":    true,
                "lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
                "pageLength":  15,          
                "processing" : true,            
                "pagingType":  "simple_numbers",            
                "dom": 'lfrtip'                             
                });
        plot_data = {!!$plot_data[$cutoff]!!};
        var chart = drawScatterPlot("enhancer_plot{!!$cutoff!!}", "Enhancer Plot", plot_data, "Enhancers", "Signal");
        charts["{!!$cutoff!!}"] = chart
        
        @endforeach

        $('.easyui-tabs').tabs({
            onSelect:function(title, idx) {
                var tab = $(this).tabs('getSelected');              
                var id = tab.panel('options').id;
                console.log(id);
                if (id == "ChIPseqBWs" && !igv_loaded) {
                    var url = '{!!url("/viewChIPseqIGV/$patient_id/$sample_id")!!}';
                    var html = '<iframe scrolling="auto" frameborder="0" frameborder="0" scrolling="no" onload="resizeIframe(this)" src="' + url + '" style="width:100%;height:100%;min-height:800px;border-width:0px"></iframe>';
                    $('#' + id).html(html);
                    igv_loaded = true;
                }
           }
        });

        $('.btnDownload').on('click', function() {
            var url = '{!!url("/downloadChIPseqFile/$patient_id/$sample_id")!!}' + '/' + $('#selDownload').val();
            console.log(url);
            window.location.replace(url);
        });

        $('.search_plot').on('keypress', function (e) {
             if(e.which === 13){
                //Disable textbox to prevent multiple submit
                $(this).attr("disabled", "disabled");

                var cutoff = $(this).attr("id").replace("search_plot", "")
                var chart = charts[cutoff];
                var gene = $(this).val().toUpperCase();

                var total_found = findPointByName(chart, gene);
                chart.redraw();
                if (total_found > 0) {
                  $('#lblSearchResults').text(" Found " + total_found + " super enhancers");
                } else {
                  $('#lblSearchResults').text(" Not found");
                }

                

                //Enable the textbox again if needed.
                $(this).removeAttr("disabled");
             }
        }); 
    });

</script>
<div id="summary_header" style="width:100%;padding:5 5 5 5px;">
    <font size=3>
        <div class="container-fluid card">
            <div class="row mx-1 my-1">
                <div class="col-md-12">
                    Sample: <span class="onco-label badge rounded-pill text-bg-success">{!!$sample->sample_name!!}</span>
                    &nbsp;&nbsp;Tumor/Normal: <span class="onco-label badge rounded-pill text-bg-successonco-label badge rounded-pill text-bg-success">{!!$sample->tissue_cat!!}</span>
                    &nbsp;&nbsp;Diagnosis: <span class="onco-label badge rounded-pill text-bg-success">{!!$sample->tissue_type!!}</span>
                    &nbsp;&nbsp;Target: <span class="onco-label badge rounded-pill text-bg-success">{!!$sample->library_type!!}</span>
                </div>
            </div>
        </div>
    </font>
</div>
<div id="tabChIPseq" class="easyui-tabs" data-options="tabPosition:'top',fit:true,plain:true,false:true,border:false,headerWidth:170" style="width:98%;padding:5px;overflow:visible;border-width:0px">

    <div id="Peaks" title="Peaks" style="padding:5px">
        <div id="tabPeaks" class="easyui-tabs" data-options="tabPosition:'top',fit:true,plain:true,false:true,border:false,headerWidth:170" style="width:98%;padding:0px;overflow:visible;border-width:0px">
            @foreach ($annotations as $cutoff => $data)
            <div id="{!!$call_type!!}-{!!$cutoff!!}" title="{!!$call_type!!}-{!!$cutoff!!}" style="padding:5px">
                <div id="tabPeaks" class="easyui-tabs" data-options="tabPosition:'top',fit:true,plain:true,false:true,border:false,headerWidth:170" style="width:98%;padding:0px;overflow:visible;border-width:0px">
                    <div id="Annotation" title="Annotation" style="padding:5px" >
		                  <table cellpadding="0" cellspacing="0" border="0" class="order-column pretty" word-wrap="break-word" id="tblAnnotation_{!!$cutoff!!}" style='width:100%'></table>
                    </div>
                    <div id="Motifs" title="Motifs">
                        <div id="tabMotifs" class="easyui-tabs" data-options="tabPosition:'top',fit:true,plain:true,false:true,border:false,headerWidth:170" style="width:98%;padding:0px;overflow:visible;border-width:0px">
                            <div id="known_{!!$cutoff!!}" title= "Known" style="padding:5px">
                                <object data="{!!url("/viewChIPseqMotif/$patient_id/$sample_id/$cutoff/$call_type/known")!!}" type="text/html" width="100%" height="100%"></object>
                            </div>
                            <div id="homer_{!!$cutoff!!}" title= "De novo" style="padding:5px">
                                <object data="{!!url("/viewChIPseqMotif/$patient_id/$sample_id/$cutoff/$call_type/homer")!!}" type="text/html" width="100%" height="100%"></object>
                            </div>
                        </div>
                    </div>
                    @if (array_key_exists($cutoff, $se))
                    <div id="SuperEnhancer" title="SuperEnhancer" style="padding:5px" >
                        <div id="tabMotifs" class="easyui-tabs" data-options="tabPosition:'top',fit:true,plain:true,false:true,border:false,headerWidth:170" style="width:98%;padding:0px;overflow:visible;border-width:0px">
                            <div id="se_{!!$cutoff!!}" title= "Table" style="padding:5px">
                                <table cellpadding="0" cellspacing="0" border="0" class="order-column pretty" word-wrap="break-word" id="tblse_{!!$cutoff!!}" style='width:100%'></table>
                            </div>
                            <div id="se_plot{!!$cutoff!!}" title= "All Enhancer Plot" style="padding:5px">
                                <object data="{!!url("/viewChIPseqSEPlot/$patient_id/$sample_id/$cutoff")!!}" type="text/html" width="100%" height="100%"></object>
                            </div>
                            <div title="Super Enhancer Plot" style="padding:5px">
                                Search Gene: <input id="search_plot{!!$cutoff!!}" type="text" class="form-control search_plot" style="display:inline;width:150px"/><span id="lblSearchResults" style="color:red"></span>
                                <div id="enhancer_plot{!!$cutoff!!}" style="width:600px"></div>
                            </div>
                            <div id="motif_regular" title= "Motif-Regular" style="padding:5px">
                                <div id="tabMotif_regular" class="easyui-tabs" data-options="tabPosition:'top',fit:true,plain:true,false:true,border:false,headerWidth:170" style="width:98%;padding:0px;overflow:visible;border-width:0px">
                                    <div id="known_{!!$cutoff!!}" title= "Known" style="padding:5px">
                                        <object data="{!!url("/viewChIPseqMotif/$patient_id/$sample_id/$cutoff/$call_type/known/regular")!!}" type="text/html" width="100%" height="100%"></object>
                                    </div>
                                    <div id="homer_{!!$cutoff!!}" title= "De novo" style="padding:5px">
                                        <object data="{!!url("/viewChIPseqMotif/$patient_id/$sample_id/$cutoff/$call_type/homer/regular")!!}" type="text/html" width="100%" height="100%"></object>
                                    </div>
                                </div>
                            </div>
                            <div id="motif_super" title= "Motif-Super" style="padding:5px">
                                <div id="tabMotif_regular" class="easyui-tabs" data-options="tabPosition:'top',fit:true,plain:true,false:true,border:false,headerWidth:170" style="width:98%;padding:0px;overflow:visible;border-width:0px">
                                    <div id="known_{!!$cutoff!!}" title= "Known" style="padding:5px">
                                        <object data="{!!url("/viewChIPseqMotif/$patient_id/$sample_id/$cutoff/$call_type/known/super")!!}" type="text/html" width="100%" height="100%"></object>
                                    </div>
                                    <div id="homer_{!!$cutoff!!}" title= "De novo" style="padding:5px">
                                        <object data="{!!url("/viewChIPseqMotif/$patient_id/$sample_id/$cutoff/$call_type/homer/super")!!}" type="text/html" width="100%" height="100%"></object>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        
	</div>
    <div id="IGV" title="IGV" style="padding:5px">
        <object data="{!!url("/viewChIPseqSampleIGV/$patient_id/$sample_id")!!}" type="text/html" width="100%" height="100%"></object>
    </div>
    <div id="QC" title="QC" style="padding:5px">
        <div id="tabQC" class="easyui-tabs" data-options="tabPosition:'top',fit:true,plain:true,false:true,border:false,headerWidth:170" style="width:98%;padding:0px;overflow:visible;border-width:0px">
            @foreach ($qc_files as $qc_file => $content_type)
            <div id="{!!$qc_file!!}" title= "{!!$qc_file!!}">
                <object data="{!!url("/viewChIPseqQC/$patient_id/$sample_id/$qc_file/$content_type")!!}" type="{!!$content_type!!}" width="100%" height="100%"></object>
            </div>
            @endforeach
        </div>
    </div>
    <div id="Download" title="Download" style="padding:5px">
        <H5>File: 
            <select class="form-select" id="selDownload" style="width:400px;display:inline">
                @foreach ($download_files as $download_file)
                <option value="{!!$download_file!!}">{!!$download_file!!}</option>
                @endforeach
            </select>
            <button id="btnDownload" class="btn btn-info btnDownload" style="margin:5px"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Download</button>
        </H5>
    </div>
</div>

@stop