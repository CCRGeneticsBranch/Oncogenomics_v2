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
{{ HTML::script('packages/jquery-ui-1.11.4/jquery-ui.min.js') }}
{{HTML::script('packages/igv.js/igv.min.js')}}

{{ HTML::script('packages/DataTables-1.10.8/media/js/jquery.dataTables.min.js') }}

{{ HTML::script('packages/Buttons-1.0.0/js/dataTables.buttons.min.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.flash.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.html5.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.print.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.colVis.js') }}

{{ HTML::script('packages/DataTables-1.10.8/extensions/ColReorder/js/dataTables.colReorder.min.js') }}
{{ HTML::script('packages/DataTables-1.10.8/extensions/FixedColumns/js/dataTables.fixedColumns.min.js') }}
{{ HTML::script('packages/yadcf-0.8.8/jquery.dataTables.yadcf.js')}}

{{ HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') }}
{{ HTML::style('packages/DataTables-1.10.8/media/css/jquery.dataTables.min.css') }}
{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') }}

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
	
	function getRandomColor() {
	  var letters = '0123456789ABCDEF';
	  var color = '#';
	  for (var i = 0; i < 6; i++) {
	    color += letters[Math.floor(Math.random() * 16)];
	  }
	  return color;
	}

	$(document).ready(function() {
		var div = $("#igvDiv")[0],
                options = {
                    showNavigation: true,
                    showKaryo : false,
                    showRuler : true,
                    showCenterGuide : true,
                    showCursorTrackingGuide : true,
                    //genome: "hg19",
                    reference: {fastaURL: "{{url('/ref/hg19.fasta')}}", cytobandURL: "{{url('/ref/cytoBand.txt')}}"},
                    locus: "chr11:17,724,132-17,760,668",
                    tracks: [
                   		 @foreach ($chip_bws as $sid => $chip_bw)		
                    	{
                            type: "wig",
                            url: '{{url("/getBigWig/$path/$patient_id/$case_id/$sid/$chip_bw")}}',
                            name: '{{$sid}}',
                            removable : true,  
                            color: getRandomColor(),                          
                            //autoscaleGroup: "group1",
                            height : 80                                                        
                        },
                        @endforeach
                        {
                            //url: "{{url('/ref/06302016_refseq.gtf.gz')}}",
                            //indexURL: "{{url('/ref/06302016_refseq.gtf.gz.tbi')}}",                            
                            url: "{{url('/ref/gencode.v38lift37.annotation.sorted.genename_changed.gtf.gz')}}",
                            indexURL: "{{url('/ref/gencode.v38lift37.annotation.sorted.genename_changed.gtf.gz.tbi')}}",
                            name: 'Gencode',
                            height : 150,
                            format: 'gtf',
                            //displayMode: "COLLAPSED",
                            displayMode: "EXPANDED",
                            visibilityWindow: 10000000
                        }
                    ]
                };

        igv.createBrowser(div, options).then(function (browser) {
                    igv.browser = browser;
                    console.log("Created IGV browser");                    
                });

	});

</script>

<div class="container-fluid" id="igvDiv" style="padding:5px; border:1px solid lightgray"></div></div>
