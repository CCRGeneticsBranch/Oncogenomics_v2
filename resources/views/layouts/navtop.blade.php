
<nav class="navbar navbar-default" role="navigation">
	<div class="row" style="padding:10px">
        	<a href={{url("https://ccr.cancer.gov")}} target='_blank' rel="noopener noreferrer"><img style="height:50px;float:left;padding-left:20px" src="{{url('images/nihlogo.svg')}}" alt="Clinomics CCR NIH logo"/></a>
    </div>
</nav>
	<div id="logo" class="nav navbar-nav navbar-left"></div>
<ul id="main-menu" class="sm sm-blue">
	<li><a href={{url("/")}}><img style="height:27px;" src="{{url('images/logo.JK.db.gif')}}" alt="Clinomics Database"/></a></li>
	<li><a href="{{url('/')}}" rel="nofollow">Home</a></li>
	<li><a href="{{url('/viewProjects')}}" rel="nofollow">Projects</a>
			@if (!\Config::get('site.isPublicSite'))
			@if(null != App\Models\User::isSuperAdmin())
			<ul>
				<li><a href="{{url('/viewCreateProject')}}" rel="nofollow">Create Project</a></li>						
			</ul>
			@endif
			@endif
	</li>

	<li><a href="{{url('/viewPatients/null/any/1/normal')}}" rel="nofollow">Patients</a></li>
	<!-- Disbled next if block hv20190822 -->
	@if (!\Config::get('site.isPublicSite'))
		@if(null != App\Models\User::isSuperAdmin())
		<li><a href="{{url('/viewCases/any')}}" rel="nofollow">Cases</a></li>
		<li><a href="#" rel="nofollow">Upload</a>
			<ul>
				<li><a href="{{url('/viewUploadVarData')}}" rel="nofollow">Case</a></li>
				<li><a href="{{url('/viewUploadClinicalData')}}" rel="nofollow">Clinical data</a></li>
			</ul>
		</li>
		@endif
	
		<!--ul>
			<li><a href="{{url('/viewPatients/null/all/1')}}" rel="nofollow">View Patients</a></li>
			<li><a href="{{url('/viewSample/all')}}" rel="nofollow">View Samples</a></li>
			<li><a href="{{url('/viewProjectPatient/null')}}" rel="nofollow">View Patients beta</a></li>
			<li><a href="{{url('/viewBiomaterial/all')}}" rel="nofollow">View Biomaterials</a></li>
			<li><a href="{{url('/viewSTR/all')}}" rel="nofollow">View STR information</a></li>
			<li><a href="{{url('/viewGenotyping/UMB103')}}" rel="nofollow">View GenoTyping information</a></li>
		</ul>
	</li>
	<li><a href="{{url('/viewStudies')}}" rel="nofollow">Studies</a>
		<ul>
			<li><a href="{{url('/viewStudies')}}" rel="nofollow">View Studies</a></li>
			<li><a href="{{url('/createStudy')}}" rel="nofollow">Create Study</a></li>
		</ul>	
	</li-->

	<!-- <li><a href="{{url('/viewPatient/null/null')}}" rel="nofollow">Variants</a></li> -->
	<!-- <li><a href="{{url('/viewGenotyping/CL0035,CL0036')}}" rel="nofollow">GenoTyping</a></li> HVR-->
		
	<li><a href="#" rel="nofollow">About</a>
			<ul>
				<li><a target=_blank href="https://github.com/CCRGeneticsBranch/oncogenomics/wiki/1.-Introduction" rel="noopener noreferrer">Tutorial</a></li>
				<li><a href="{{url('/viewContact')}}" rel="nofollow">Contact</a></li>
			</ul>
		</li>	
@endif
<!--
	<li><a href="#" rel="nofollow">Upload</a>
		<ul>
			<li><a href="{{url('/viewUploadClinicalData')}}" rel="nofollow">Clinical data</a></li>
			<li><a href="{{url('/viewUploadClinicalData')}}" rel="nofollow">Expression</a></li>
			<li><a href="{{url('/viewUploadClinicalData')}}" rel="nofollow">Variant data</a></li>
		</ul>
	</li>
-->
	<!--li><a href="{{url('/viewAPIs')}}" rel="nofollow">WEB APIs</a></li-->
	<!--li><a href="{{url('/')}}" rel="nofollow">Tutorial</a></li-->
	
	<div class="navbar-right" style="margin-right:10px">
		@if(null != App\Models\User::getCurrentUser())						
			@if (App\Models\User::isSuperAdmin() || App\Models\User::isProjectManager())
				<li><a href="{{url('/viewSetting')}}" rel="nofollow"><img width="20" height="20" src="{{url('images/setting.png')}}"/>Setting</a></li>
				<li><a href={{URL::route('users.list')}} rel="nofollow">{{App\Models\User::getCurrentUser()->email}}</a></li>
			@else
				<li><a href='#' rel="nofollow">{{App\Models\User::getCurrentUser()->email}}</a></li>
			@endif
			<li><a href="{{URL::action('\LaravelAcl\Authentication\Controllers\AuthController@getLogout')}}" rel="nofollow">Logout</a> </li>
			<!--li><a href={{URL::route('users.selfprofile.edit')}} rel="nofollow">{{App\Models\User::getCurrentUser()->email}}</a></li-->
		@else
			<li><a href={{url("/login")}}>Login</a></li><li>&nbsp;</li>
		@endif
	</div>
</ul>

</nav>
