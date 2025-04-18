@section('title', "QCIProject--$project_id--$type")
{{ HTML::style('packages/w2ui/w2ui-1.4.min.css') }}
{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::style('css/style.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-core-css.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-blue/sm-blue.css') }}    
{{ HTML::script('js/jquery-3.6.0.min.js') }}
{{ HTML::script('packages/smartmenus-1.0.0-beta1/jquery.smartmenus.min.js') }}


{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('css/style.css') }}
{{ HTML::style('packages/jquery-easyui/themes/icon.css') }}
{{ HTML::style('packages/jquery-easyui/themes/bootstrap/easyui.css') }}
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}
{{ HTML::style('packages/bootstrap-switch-master/dist/css/bootstrap3/bootstrap-switch.css') }}
{{ HTML::style('css/filter.css') }}
{{ HTML::style('packages/tooltipster-master/dist/css/tooltipster.bundle.min.css') }}


{!! HTML::script('packages/DataTables/datatables.min.js') !!}
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
	$(document).ready(function() {
		var url = '{{url("/getProjectQCI/$project_id/$type")}}';
		
		console.log(url);		
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {				
				var data = JSON.parse(data);				
				
				showTable(data, 'tblQCI_{{$type}}');					
				$("#loading").css("display","none");	
				$("#tableArea").css("display","block");

				/*				
				for (var i=user_list_idx;i<data.cols.length;i++) {
					filter_list[data.cols[i].title] = i;
					hide_cols.tblSplice.push(i);
				}
				*/
				//onco_filter = new OncoFilter(Object.keys(filter_list), null, function() {doFilter();});	
				//doFilter();		
			}			
		});

		$('#btnDownload').on('click', function() {
			var url = '{!!url("/getProjectQCI/$project_id/$type/text")!!}';
			window.location.replace(url);	
		});
	});

	function showTable(data, tblId) {
		var root_url="{{url("/")}}";
		data.cols[2].render = function(data, type, row){
			return "<a target=_blank href='" + root_url + "/viewVarAnnotationByGene/{{$project_id}}/" + data + "/{{($type=="TSO")?"variants":$type}}'>"+ data + "</a>";
		};
		var tbl = $('#' + tblId).DataTable( {
				"data": data.data,
				"columns": data.cols,
				"ordering":    true,
				"order": [[ 3, "desc" ]],
				"deferRender": true,
				"lengthMenu": [[20, 40, 60], [20, 40, 60]],
				"pageLength":  20,
				"pagingType":  "simple_numbers",			
				//"dom": '<"toolbar">lfrtip',
			});		
	};	
	

</script>
<div style="display:none;">	
	<div id="filter_definition" style="display:none;width:800px;height=600px">
		<H4>
		The definition of filters:<HR>
		</H4>
		<table>
			@foreach ($filter_definition as $filter_name=>$content)
			<tr valign="top"><td><font color="blue">{{$filter_name}}:</font></td><td>{{$content}}</td></tr>
			@endforeach
		</table>

	</div>
</div>
<div id='loading'><img src='{{url('/images/ajax-loader.gif')}}'></img></div>
<div id='tableArea' style="height:98%;width:98%;padding:10px;overflow:auto;display:none;text-align: left;font-size: 12px;">		<button id="btnDownload" type="button" class="btn btn-default" style="font-size: 12px;">
					<img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Download</button>
				
<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblQCI_{{$type}}" style='width:100%'></table>	
