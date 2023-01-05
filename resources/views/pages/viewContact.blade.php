@extends('layouts.default')
@section('content')

{{ HTML::style('css/light-bootstrap-dashboard.css') }}
{{ HTML::style('css/sb-admin.css') }}
{{ HTML::style('css/font-awesome.min.css') }}

{{ HTML::script('js/onco.js') }}
<div class="row" style="padding:20px">
				<div class="col-md-9">
	                			<div id="main" style='text-align: left; height:430px' role="main" >
									    <H2 style="font-size:28px; margin:20px 0 10px">If you have any questions with OncoGenomics DB, please contact us:<br><h2>
									    
									    <h4>NCI Oncogenomics: <a href="mailto:oncogenomics@mail.nih.gov">oncogenomics@mail.nih.gov</a></h4>
									    <br><br>
								</div>
							
				</div>
</div>
						
@stop
