{{ HTML::style('packages/w2ui/w2ui-1.4.min.css') }}
{{ HTML::style('css/bootstrap.min.css') }}
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
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}
{{ HTML::style('packages/muts-needle-plot/build/muts-needle-plot.css') }}
{{ HTML::style('css/heatmap.css') }}
{{ HTML::style('packages/bootstrap-switch-master/dist/css/bootstrap3/bootstrap-switch.css') }}
{{ HTML::style('css/filter.css') }}
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
{{ HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/tooltipster-master/dist/js/tooltipster.bundle.min.js') }}
{{ HTML::script('packages/bootstrap-switch-master/dist/js/bootstrap-switch.js') }}
{{ HTML::script('js/onco.js') }}
{{ HTML::script('js/filter.js') }}

<style>
html, body { height:100%; width:100%;} ​
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

div.toolbar {
	display:inline;
}

.popover-content {
	top:0px;
	height: 450px;
	overflow-y: auto;
 }

.fade{
	top:0px;	
 }

</style>    
<script type="text/javascript">
	var tab_urls = [];
	var loaded_list = [];

	$(document).ready(function() {
		@foreach ( $types as $type)
			//console.log('{{url("/viewQCITypeProjectDetail/$project->id/$type")}}');
			tab_urls["tab{{Lang::get("messages.$type")}}"] = '{{url("/viewQCITypeProjectDetail/$project->id/$type")}}';
		@endforeach		

		$('.easyui-tabs').tabs({
			onSelect:function(title) {
				console.log(title);
				tab = $('#tabQCI').tabs('getSelected');
				var id = tab.panel('options').id;
				console.log(id);
				showFrameHtml(id);	
		   }
		});	
		console.log("tab{{Lang::get("messages.$types[0]")}}");

		showFrameHtml("tab{{Lang::get("messages.$types[0]")}}");
	});

	function showFrameHtml(id) {
		if (loaded_list.indexOf(id) == -1) {
			var url = tab_urls[id];
			if (url != undefined) {
				var html = '<iframe scrolling="no" frameborder="0"  src="' + url + '" style="width:100%;height:85%;overflow:none;border-width:0px"></iframe>';
				$('#' + id).html(html);
				console.log('#' + id);
				console.log(html);
				loaded_list.push(id);
			}
		}
	}	
	

</script>
<div id="tabQCI" class="easyui-tabs" data-options="tabPosition:'top',plain:true,pill:false" style="width:100%;padding:5px;border-width:0px">
		@foreach ( $types as $type)
			<div id="tab{{Lang::get("messages.$type")}}" title="QCI-{{Lang::get("messages.$type")}}{{($type=="variants")?"(TSO)":""}}" data-options="tools:'#{{$type}}_mutation_help'" style="width:98%;padding:5px;overflow:auto">				
			</div>				
		@endforeach
</div>