@extends('layouts.default')
@section('content')

{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}


{{ HTML::style('packages/d3/d3.css') }}
{{ HTML::script('packages/d3/d3.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}

<div id="tree_layout" class="easyui-panel" style="height:100%;padding:0px;">
	<div class="easyui-layout" data-options="fit:true">
		<div data-options="region:'center',split:true" style="width:100%;padding:10px;overflow:none;" title="">
			<div id="tree-container"></div>
		</div>
	</div>
</div>

<script src="{{url('packages/d3/dndTree.js')}}"></script>

<script type="text/javascript">

tree_width = $('#tree_layout').width() - 100;
tree_height = $('#tree_layout').height()  - 100;
data_source = '{{$data_source}}';
treeJSON = d3.json(data_source, d3_callback);

</script>


@stop
