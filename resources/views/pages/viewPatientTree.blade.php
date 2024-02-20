@extends('layouts.default')
@section('content')

{!! HTML::style('packages/jquery-easyui/themes/default/easyui.css') !!}
{!! HTML::style('packages/d3/d3.css') !!}
{!! HTML::style('css/style_datatable.css') !!}

{!! HTML::script('packages/d3/d3.min.js') !!}
{!! HTML::script('packages/jquery-easyui/jquery.easyui.min.js') !!}
{!! HTML::script('packages/DataTables/datatables.min.js') !!}
{!! HTML::script('js/tree.js') !!}
<style type="text/css">
  
	.node {
    cursor: pointer;
  }

  .overlay{
      background-color:#EEE;
  }
   
  .node circle {
    fill: ;
    stroke: steelblue;
    stroke-width: 1.5px;
  }
   
  .node text {
    font-size:10px; 
    font-family:sans-serif;
  }
   
  .link {
    fill: none;
    stroke: #ccc;
    stroke-width: 1.5px;
  }

  .templink {
    fill: none;
    stroke: red;
    stroke-width: 3px;
  }

  .ghostCircle.show{
      display:block;
  }

  .ghostCircle, .activeDrag .ghostCircle{
       display: none;
  }

</style>

<div id="tree_layout" class="easyui-panel" style="height:100%;padding:0px;">
	<div class="easyui-layout" data-options="fit:true">
		<div id="panelTree" data-options="region:'center',split:true" style="width:100%;height:65%;padding:10px;overflow:auto;" title="TreeView">
			<div id="tree-container"></div>
		</div>
		<div id="panelTable" class="easyui-panel" data-options="region:'south',split:true,collapsed:false" style="width:100%;height:35%;padding:10px;overflow:auto;" title="Table View">
			<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblDetail" style='width:100%'>
			</table>
		</div>
	</div>
</div>

<script src="{!!url('packages/d3/dndTree.js')!!}"></script>

<script type="text/javascript">

	tree_width = $('#panelTree').width() - 30;
	tree_height = $('#panelTree').height()  - 30;
	data_source = '{!!$data_source!!}';
	treeJSON = d3.json(data_source, d3_callback);
	var tblDetail;
	var data = {!!json_encode($data)!!};
	var cols = {!!json_encode($cols)!!};

	$(document).ready(function() {
		
		tblDetail = $('#tblDetail').DataTable( 
		{
			"data": data,
			"columns": cols,
			"processing": true,
			"paging":   false,
			"ordering": true,
			"info":     false,
			"dom": 'Blfrtip',
			"buttons": ['csv', 'excel']
		} );
		

     });


</script>


@stop
