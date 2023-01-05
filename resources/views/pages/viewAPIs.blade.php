@extends('layouts.default')
@section('content')

{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('css/style_datatable.css') }}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
    
<table align="center" cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblOnco" style='width:70%'>
		<thead>
			<tr>
				<th>Type</th>
				<th>Link</th>
				<th>Description</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>Sample</td>
				<td><a href='{{url('/getSample/all')}}'>{{url('/getSample/all')}}</a></td>
				<td>Get all samples</td>
			</tr>
			<tr>
				<td>Patient</td>
				<td><a href='{{url('/getSample/all')}}'>{{url('/getPatient/all')}}</a></td>
				<td>Get all patients</td>
			</tr>
		</tbody>
</table>
<!--
<ul class="nav nav-pills">
  <li class="active"><a data-toggle="pill" href="#home">Home</a></li>
  <li><a data-toggle="pill" href="#menu1">Menu 1</a></li>
  <li><a data-toggle="pill" href="#menu2">Menu 2</a></li>
</ul>

<div class="tab-content">
  <div id="home" class="tab-pane fade in active">
    <h3>HOME</h3>
    <p>Some content.</p>
  </div>
  <div id="menu1" class="tab-pane fade">
    <h3>Menu 1</h3>
    <p>Some content in menu 1.</p>
  </div>
  <div id="menu2" class="tab-pane fade">
    <h3>Menu 2</h3>
    <p>Some content in menu 2.</p>
  </div>
</div>
-->
@stop
