@extends('layouts.default')
@section('content')

{{ HTML::style('css/style.css') }}
{{ HTML::script('js/jquery-3.6.0.min.js') }}

{{ HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') }}
{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') }}
{{ HTML::style('packages/jquery-easyui/themes/bootstrap/easyui.css') }}
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}

{!! HTML::script('packages/DataTables/datatables.min.js') !!}
{{ HTML::script('js/bootstrap.bundle.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') }}
{{ HTML::script('js/onco.js') }}

<style>
</style>
    
<script type="text/javascript">
	

	$(document).ready(function() {
	});
</script>

<div id="summary_header" style="width:100%;padding:5 5 5 5px;">
				<font size=3>
						<div class="container-fluid card">
							<div class="row mx-1 my-1">
								<div class="col-md-2">Gene: <span class="onco-label">{!!$gene!!}</span></div>
								<div class="col-md-2">Chr: <span class="onco-label">{!!$chr!!}</span></div>
								<div class="col-md-2">Start: <span class="onco-label">{!!$start!!}</span></div>
								<div class="col-md-2">End: <span class="onco-label">{!!$end!!}</span></div>
								<div class="col-md-2">Ref: <span class="onco-label">{!!$ref!!}</span></div>
								<div class="col-md-2">Alt: <span class="onco-label">{!!$alt!!}</span></div>
							</div>							
						</div>
					
				</font>
</div>

<div id="out_container" class="easyui-panel" data-options="border:false" style="width:100%;height:100%;padding:0px;border-width:0px">	
<div id="tabAnnoType" class="easyui-tabs" data-options="tabPosition:'top',fit:true,plain:true,false:true,border:false,headerWidth:150" style="width:98%;height: 100px;padding:0px;overflow:auto;border-width:0px">	
	@foreach ($annotators as $annotator_type => $annotator_rows)
		<div title={{$annotator_type}} style="padding:10px">
			<div id="tabAnno" class="easyui-tabs" data-options="tabPosition:'left',fit:true,plain:true,false:true,border:false,headerWidth:250" style="width:98%;height: 100px;padding:0px;overflow:auto;border-width:0px">					
				@foreach ($annotator_rows as $annotator => $annotator_row)
				<div title={{str_replace("_", "&nbsp;", $annotator)}} style="padding:5px">
					<font size=3>
						<div class="container-fluid" style="padding: 0px">
							<div class="row mx-1 my-1">
								<div class="col-md-12">Annotator: <span class="onco-label">{!!$annotator_row[0]!!}</span></div>
							</div>
							<div class="row mx-1 my-1">
								<div class="col-md-12">Description: <span class="onco-label">{!!$annotator_row[1]!!}</span></div>
							</div>							
						</div>
					</font>
					<table cellpadding="0" cellspacing="0" border="1" class="pretty" word-wrap="break-word" id="tblOnco" style='width:90%;'>
						<thead>
						<th>Column</th><th>Description</th><th>Value</th>
						</thead>
						@foreach ($annotator_row[2] as $row)						
						<tr>
							<td>{{$row[1]}}</td>
							<td>{{$row[2]}}</td>
							<td>{!!$row[3]!!}</td>
						</tr>
						@endforeach
					</table>
				</div>
				@endforeach
			</div>			
		</div>
	@endforeach	
</div>
</div>
@stop

