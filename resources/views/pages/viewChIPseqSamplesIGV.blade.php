@section('title', "ChipseqIGV--$project->id")
{{ HTML::style('css/style.css') }}
{!! HTML::style('css/style_datatable.css') !!}
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
{!! HTML::style('packages/jquery/jquery.dataTables.css') !!}



{!! HTML::script('packages/DataTables/datatables.min.js') !!}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('js/bootstrap.bundle.min.js') }}
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
<script src="https://cdn.jsdelivr.net/npm/igv@3.0.2/dist/igv.min.js"></script>


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
                    locus: "chr11:17,724,132-17,760,668"
                };

    browser = await igv.createBrowser(div, config) 

</script>  

<script type="text/javascript">

    var browser;
    var tbl;

    function getRandomColor() {
	  var letters = '0123456789ABCDEF';
	  var color = '#';
	  for (var i = 0; i < 6; i++) {
	    color += letters[Math.floor(Math.random() * 16)];
	  }
	  return color;
	}

	$(document).ready(function() {
        tbl = $('#tblSample').DataTable( {ordering: true, scrollCollapse: true, paging:false, scrollY: '400px'});

        $('#lblCountDisplay').text(tbl.page.info().recordsDisplay);
        $('#lblCountTotal').text(tbl.page.info().recordsTotal);

        $('#tblSample').on( 'draw.dt', function () {
            $('#lblCountDisplay').text(tbl.page.info().recordsDisplay);
            $('#lblCountTotal').text(tbl.page.info().recordsTotal);
        });
        
        $('.ckSample').on('change', function() {
            console.log(browser);
            var info = $(this).val();
            var tokens = info.split("/");
            var sample_name = tokens[2];
            if ($(this).is(':checked')) {               
                var url = '{!!url("/getSampleBigWig")!!}' + '/' + tokens[0] + '/' + tokens[1] + '/' + tokens[2];
                console.log("loading...");
                $.fancybox.open({
                    content  : $('#loading'),
                    type : 'inline',
                    modal: true,
                    opts : {
                      onComplete : function() {
                        console.info('done!');
                      }
                    }
                  });
                var track = browser.loadTrack({url: url, name: sample_name, color: getRandomColor(), order:0}).then(function(track){
                    $.fancybox.close();
                    console.log("done!");
                });
                
            }
            else {
                var tracks = browser.trackViews;
                for (var i = 0; i < tracks.length; i++) {
                    var track = tracks[i].track;
                    if (track.name == sample_name)
                        browser.removeTrack(track);
                }
            }
        });

        $('.filter').on('change', function() {
            tbl.draw();          
        });

        $.fn.dataTableExt.afnFiltering.push( function( oSettings, aData, iDataIndex ) {
            var cellline = $('#selCellline').val();
            if (cellline != "all")
                if (aData[2] != cellline)
                    return false;
            var target = $('#selTarget').val();
            if (target != "all")
                if (aData[3] != target)
                    return false;
            return true;
        });

	});

</script>

<div style="padding:5px;text-align:left">
    <button class="btn btn-primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasRight" aria-controls="offcanvasRight"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-circle" viewBox="0 0 16 16">
  <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
  <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
</svg> Select samples</button>
</div>

<div style="display: none;width:300px;height:100px" id="loading">
    <H4>Loading...</H4>
    <H5>Please wait...</H5>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasRight" aria-labelledby="offcanvasRightLabel"  style="width:65%">
    <span style="text-align:left;padding:5px">      
        <button class="btn btn-danger" type="button" style="display:inline" data-bs-toggle="offcanvas" data-bs-target="#offcanvasRight" aria-controls="offcanvasRight"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x" viewBox="0 0 16 16"><path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708"/>
        </svg></button>
        &nbsp;&nbsp;Patient/Cellline:
        <select class="form-select filter" id="selCellline" style="width:150px;display:inline">
            <option value="all" selected>All</option>
            @foreach ($celllines as $cellline)
            <option value="{!!$cellline!!}">{!!$cellline!!}</option>
            @endforeach
        </select>
        &nbsp;&nbsp;Target:
        <select class="form-select filter" id="selTarget" style="width:150px;display:inline">
            <option value="all" selected>All</option>
            @foreach ($targets as $target)
            <option value="{!!$target!!}">{!!$target!!}</option>
            @endforeach
        </select>
        <span style="font-family: monospace; font-size: 20;float:right;">                   
                Samples: <span id="lblCountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="lblCountTotal" style="text-align:left;" text=""></span>
    </span>
  
    <table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblSample" style='width:100%'>
        <thead><tr><th>Select</th><th>Sample</th><th>Patient/Cell line</th><th>Target</th><th>Diagnosis</th><th>File</th></tr></thead>
        <tbody>
        @foreach ($chip_samples as $chip_sample)
            <tr>
                <td><input class='ckSample' type="checkbox" value="{!!$chip_sample[0]!!}/{!!$chip_sample[1]!!}/{!!$chip_sample[5]!!}"/></td>
                <td>{!!$chip_sample[2]!!}</td>
                <td>{!!$chip_sample[0]!!}</td>
                <td>{!!$chip_sample[3]!!}</td>
                <td>{!!$chip_sample[4]!!}</td>
                <td>{!!$chip_sample[5]!!}</td>
            </tr>
        @endforeach
    </tbody>
    </table>

</div>

<div class="container-fluid" id="igvDiv" style="padding:5px; border:1px solid lightgray"></div></div>



