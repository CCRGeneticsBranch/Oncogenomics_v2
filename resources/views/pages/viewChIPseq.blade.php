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
	var igv_loaded = false;
	$(document).ready(function() {
		var data = JSON.parse('{{$summary_table}}');
		var tbl = $('#tblSummary').DataTable( 
				{				
					"paging":   true,
					"ordering": true,
					"info":     true,
					"dom": 'lfrtip',
					"data": data.data,
					"columns": data.cols,
					"lengthMenu": [[25, 50, -1], [25, 50, "All"]],
					"pageLength":  25,
					"pagingType":  "simple_numbers",									
				});

        $('.easyui-tabs').tabs({
            onSelect:function(title, idx) {
                var tab = $(this).tabs('getSelected');              
                var id = tab.panel('options').id;
                console.log(id);
                if (id == "ChIPseqBWs" && !igv_loaded) {
                    var url = '{{url("/viewChIPseqIGV/$patient_id/$case_id")}}';
                    var html = '<iframe scrolling="auto" frameborder="0" frameborder="0" scrolling="no" onload="resizeIframe(this)" src="' + url + '" style="width:100%;height:100%;min-height:800px;border-width:0px"></iframe>';
                    $('#' + id).html(html);
                    igv_loaded = true;
                }
           }
        });

        $('.motif').on('change', function() {
            var smp_str = $('#selSample').val();
            var values = smp_str.split(",");
            var sample_id = values[0];
            var cutoff = values[1];
            var type = $('#selMotifType').val();
            var url = "{{url("/viewChIPseqMotif/$patient_id/$case_id/")}}" + "/" + sample_id + "/" + cutoff + "/" + type
            console.log(url);
            $('#loading_motif').css('display','block');
            $('#motif').prop('data', url);

        });

        $( "#selSample" ).trigger( "change" ); 
    });

</script>

<div id="tabChIPseq" class="easyui-tabs" data-options="tabPosition:top,fit:true,plain:true,pill:true" style="width:98%;padding:10px;overflow:visible;">
	<div id="ChIPseqSummary" title="Summary">
		<table cellpadding="0" cellspacing="0" border="0" class="order-column pretty" word-wrap="break-word" id="tblSummary" style='width:100%'></table>
	</div>
	<div id="ChIPseqBWs" title="BigWigs">
        <!--iframe src='{{url("/viewChIPseqIGV/$patient_id/$case_id")}}' type="application/html" width="100%" height="100%"></iframe-->
	</div>
	<div id="ChIPMotifs" title="Motifs">
        <H4 style="display:inline">Sample:&nbsp;</H4><select class="form-control motif" id="selSample" style="display:inline;width:500px">
            @foreach ($motifs as $key => $values)
                @foreach ($values as $value)
                <option value="{{$key}},{{$value}}">{{$key}}-{{$value}}</option>
                @endforeach
            @endforeach    
        </select>
        <H4 style="display:inline">Type:&nbsp;</H4><select class="form-control motif" id="selMotifType" style="display:inline;width:100px">
            <option value="known">Known</option>
            <option value="homer">Homer</option>
        </select>

        <div id="loading_motif"><img src="{{url('/images/ajax-loader.gif')}}""></img></div>
        <object id="motif" width="100%" height="100%" onload="$('#loading_motif').css('display','none');"></object>
	</div>
</div>
