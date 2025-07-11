@section('title', "ChipseqIGV--$patient_id--$sample_id")
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



{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('js/togglebutton.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') }}
{{ HTML::script('packages/tooltipster-master/dist/js/tooltipster.bundle.min.js') }}
{{ HTML::script('packages/bootstrap-switch-master/dist/js/bootstrap-switch.js') }}

{{ HTML::script('js/filter.js') }}
{{ HTML::script('js/onco.js') }}
{{ HTML::script('packages/highchart/js/highcharts.js')}}
{{ HTML::script('packages/highchart/js/highcharts-more.js')}}
{{ HTML::script('packages/highchart/js/modules/exporting.js')}}
{{HTML::script('packages/igv.js/igv.min.js')}}


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

<script type="module">

    import igv from '{!!url("packages/igv.js/igv.esm.min.js")!!}'

    const div = document.getElementById("igvDiv")

    const config = {
                    genome: "hg19",
                    locus: "chr11:17,724,132-17,760,668",
                    tracks: [
                        @foreach ($chip_bws as $sid => $chip_bw)        
                        {
                            type: "wig",
                            url: '{{url("/getSampleBigWig/$patient_id/$sample_id/$chip_bw")}}',
                            name: '{{$chip_bw}}',
                            removable : true,  
                            color: getRandomColor(),                          
                            //autoscaleGroup: "group1",
                            height : 60                                                        
                        },
                        @endforeach
                        @foreach ($chip_beds as $cutoff => $chip_bed)        
                        {
                            type: "annotation",
                            format: "bed",
                            url: '{{url("/getSamplePeakBed/$patient_id/$sample_id/$cutoff/$chip_bed")}}',
                            name: '{!!"$cutoff/$chip_bed"!!}',
                            removable : true,  
                            color: getRandomColor(),                          
                            //autoscaleGroup: "group1",
                            height : 50                                                        
                        },
                        @endforeach
                        @foreach ($se_beds as $cutoff => $se_bed)        
                        {
                            type: "annotation",
                            format: "bed",
                            url: '{{url("/getSampleSEBed/$patient_id/$sample_id/$cutoff/$se_bed")}}',
                            name: '{!!"$cutoff/$se_bed"!!}',
                            removable : true,  
                            color: getRandomColor(),                          
                            //autoscaleGroup: "group1",
                            height : 50                                                        
                        },
                        @endforeach
                    ]
                };

    browser = await igv.createBrowser(div, config) 

</script> 

<script type="text/javascript">
	
	function getRandomColor() {
	  var letters = '0123456789ABCDEF';
	  var color = '#';
	  for (var i = 0; i < 6; i++) {
	    color += letters[Math.floor(Math.random() * 16)];
	  }
	  return color;
	}

	$(document).ready(function() {

	});

</script>

<div class="container-fluid" id="igvDiv" style="padding:5px; border:1px solid lightgray"></div></div>
