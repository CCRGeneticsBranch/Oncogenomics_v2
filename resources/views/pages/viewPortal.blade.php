@extends('layouts.default_no_menu')
@section('content')

{{ HTML::style('css/light-bootstrap-dashboard.css') }}
{{ HTML::style('css/sb-admin.css') }}
{{ HTML::style('css/font-awesome.min.css') }}

{{ HTML::script('js/onco.js') }}
<style>
  .card-title {
    font-weight: 750;
    font-family: Montserrat,Open Sans,sans-serif
  }
  .card-text {
    font-size: 0.85rem;
    text-align: justify;
  }
  
</style>

<div class="row" style="background-color: #01579B;height: 250px;">
	<!--div class="col-md-5" style="height: 250px;background:transparent url('https://ccr.cancer.gov/sites/default/files/styles/half_4_3/public/2022-11/cancer_genomic_clouds.jpg?h=d8102449&itok=p4qlY3cL') no-repeat center center /cover">
	</div-->
	<div class="col-md-12" style="height: 250px;background:transparent url('https://ccr.cancer.gov/sites/default/files/styles/wysiwyg_original/public/2021-07/research%20%281%29.png?itok=GJI5wIAC') no-repeat center center /cover">
		<div class="card-title py-5" style="font-size:40px;text-align: center;color:#F8F8F8;text-shadow:0 0 2px #888;">OncoGenomics Applications</div>
		<div class="row justify-content-sm-center">
                <div class="col-sm-4">
                    <a href="https://ccr.cancer.gov/staff-directory/javed-khan/lab" target="_blank" class="contact-link mt-4 btn btn-lg btn-outline-light btn-block">
                        <i class="fa fa-info-circle"></i> Learn More</a>
                </div>
        </div>
	</div>
	
	<!--div class="col-md-7 px-4 py-4">
		<div class="card px-4 py-4">
			<h2 class="card-title" >OncoGenomics Tools</h2>
			<p class="card-text">Oncogenomics tools provide a cancer discovery platform using start-of-art Omics pipelines, database management and machine learning technologies. </p>
			<a target=_blank href="https://ccr.cancer.gov/staff-directory/javed-khan/lab" class="btn btn-primary" style="width:200px">Learn more</a>
		</div>
	</div-->	
</div>
<div class="row px-2 py-2" style="background-color: #F5F5F5;">
	<div class="col-md-12">
		<div class="card-deck">
			<div class="card px-4 py-2" >
				<h5 class="card-title" >
					<i class="fa fa-database fa-1x"></i>&nbsp;&nbsp;<a target="_blank" href="{{url('/')}}">OncoGenomics Portal</a></h5>
				<p class="card-text" >OncoGenomics portal is a visualization platform for both clinical and research purposes. The portal provides a comprehensive patient-centric page for clinically annotated data, including a series of cases sequenced at different time points or conditions. </p>
			</div>			
			<div class="card px-4 py-2">
				<h5 class="card-title" >
					<i class="fa fa-line-chart fa-1x"></i>&nbsp;&nbsp;<a target="_blank" href="https://fsabcl-onc01d.ncifcrf.gov/rms">RMS AI Portal</a></h5>
				<p class="card-text">Rhabdomyosarcoma (RMS) AI portal description.</p>
			</div>
			<div class="card px-4 py-2">
				<h5 class="card-title" >
					<i class="fa fa-line-chart fa-1x"></i>&nbsp;&nbsp;<a target="_blank" href="https://fsabcl-onc01d.ncifcrf.gov/rms">OncoGenomics Analysis Tools</a></h5>
				<p class="card-text">Ben's tool description.</p>
			</div>
			<div class="card px-4 py-2">
				<h5 class="card-title" >
					<i class="fa fa-database fa-1x"></i>&nbsp;&nbsp;<a target="_blank" href="https://omics-oncogenomics.ccr.cancer.gov/cgi-bin/JK">OncoGenomics Expression Portal</a></h5>
				<p class="card-text">Xinyu's portal description.  </p>
			</div>
			<div class="card px-4 py-2" style="width:300px">
				<h5 class="card-title" >
					<i class="fa fa-code fa-1x"></i>&nbsp;&nbsp;<a target="_blank" href="https://github.com/CCRGeneticsBranch/ngs_pipeline_4.1">OncoGenomics Pipeline</a></h5>
				<p class="card-text">This pipeline is available on NIH biowulf cluster, contact me if you would like to do a test run. The data from this pipeline could directly be ported in OncoGenomics portal, an application created to visualize NGS data available to NIH users.  </p>
			</div>			
		</div>
	</div>
</div>
						
@stop
