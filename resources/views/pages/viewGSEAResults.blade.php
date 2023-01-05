{{ HTML::style('packages/w2ui/w2ui-1.4.min.css') }}
{{ HTML::style('packages/bootstrap-3.3.7/css/bootstrap.min.css') }}
{{ HTML::style('css/style.css') }}

{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-core-css.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-blue/sm-blue.css') }}    
{{ HTML::script('js/jquery-3.6.0.min.js') }}
{{ HTML::script('packages/smartmenus-1.0.0-beta1/jquery.smartmenus.min.js') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}


{{ HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') }}
{{ HTML::style('css/style_datatable.css') }} 
{{ HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') }}
{{ HTML::style('packages/jquery-easyui/themes/icon.css') }}
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}
{{ HTML::style('packages/muts-needle-plot/build/muts-needle-plot.css') }}
{{ HTML::style('packages/bootstrap-switch-master/dist/css/bootstrap3/bootstrap-switch.css') }}
{{ HTML::style('css/filter.css') }}
{{ HTML::style('packages/tooltipster-master/dist/css/tooltipster.bundle.min.css') }}
{{ HTML::style('packages/DataTables-1.10.8/extensions/Highlight/dataTables.searchHighlight.css') }}

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
{{ HTML::script('packages/DataTables-1.10.8/extensions/Highlight/jquery.highlight.js') }}
{{ HTML::script('packages/DataTables-1.10.8/extensions/Highlight/dataTables.searchHighlight.min.js') }}
{{ HTML::script('js/filter.js') }}
{{ HTML::script('js/onco.js') }}
{{ HTML::script('packages/highchart/js/highcharts.js')}}
{{ HTML::script('packages/highchart/js/highcharts-more.js')}}
{{ HTML::style('css/GSEA.css') }}

<style>

.block_details {
    display:none;
    width:90%;
    height:130px;    
  border-radius: 10px;
  border: 2px solid #73AD21;
  padding: 10px; 
  margin: 10px; 
  overflow: auto; 
}

.toolbar {
  display:inline;
}

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
    background:url('{{url('/images/close-button.png')}}') no-repeat center center;  
}

.btn-default:focus,
.btn-default:active,
.btn-default.active {
    background-color: DarkCyan;
    border-color: #000000;
    color: #fff;
}
.btn-default.active:hover {
    background-color: #005858;
    border-color: gray;
    color: #fff;


}
#View_Results{
  width:100%;
  border:none;
  height:100%;
}

</style>
  

<iframe id="View_Results"></iframe>
<script type="text/javascript">

  $(document).ready(function() {
  	get_results_page({{$token_id}});

 });


function get_results_page(token_id){
	  	console.log("HERE");
        var url='{{url('/getGSEAResults')}}'+ '/' + '{{$project_id}}' +  '/' + token_id
       $('#View_Results').attr('src', url)
}
</script>

