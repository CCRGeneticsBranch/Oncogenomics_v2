
<nav class="navbar navbar-default" role="navigation">
	<div class="row" style="padding:10px">
        	<a href={{url("https://ccr.cancer.gov")}} target='_blank' rel="noopener noreferrer"><img style="height:50px;float:left;padding-left:20px" src="{{url('images/nihlogo.svg')}}" alt="Clinomics CCR NIH logo"/></a>
        	@if (\Config::get('site.shutdown_banner'))
        	<div class="alert alert-warning" role="alert">
        	Because of a lapse in government funding, the information on this website may not be up to date, transactions submitted via the website may not be processed, and the agency may not be able to respond to inquiries until appropriations are enacted. The NIH Clinical Center (the research hospital of NIH) is open. For more details about its operating status, please visit <a target=_blank href="https://cc.nih.gov">cc.nih.gov</a>. Updates regarding government operating status and resumption of normal operations can be found at <a target=_blank href="https://opm.gov">opm.gov</a>.
	        </div>
	        @endif
    </div>
</nav>

<nav class="navbar navbar-expand-lg navbar-light" style="background-color: #01579B;">
  
  <div class="collapse navbar-collapse" id="navbarSupportedContent">
    <ul class="navbar-nav mr-auto">
    		<li class="nav-item active">
    			<a class="nav-link" href={{url("/")}}><img style="height:27px;" src="{{url('images/logo.JK.db.gif')}}" alt="Clinomics Database"/></a>
    		</li>
			<li class="nav-item">
				<a class="nav-link" href="{{url('/')}}" rel="nofollow">Home</a>
			</li>
			<li class="nav-item">
				<a class="nav-link" href="{{url('/viewProjects')}}" rel="nofollow">Projects</a>
			</li>
			<li class="nav-item">
				<a class="nav-link" href="{{url('/viewPatients/null/any/1/normal')}}" rel="nofollow">Patients</a>
			</li>
                        <li class="nav-item px-2">
                                <a class="nav-link" href="{{url('/viewCases/any')}}" rel="nofollow">Cases</a>
                        </li>
			@if (!\Config::get('site.isPublicSite'))
				@if(null != App\Models\User::isSuperAdmin())
				<li class="nav-item dropdown">
					<a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Upload</a>
					<div class="dropdown-menu" aria-labelledby="navbarDropdown">
						<a class="dropdown-item" href="{{url('/viewUploadVarData')}}" rel="nofollow">Case</a>						
						<a class="dropdown-item" href="{{url('/viewUploadClinicalData')}}" rel="nofollow">Clinical data</a>
					</div>
				</li>
				@endif
				<li class="nav-item dropdown">
					<a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Annotation</a>
					<div class="dropdown-menu" aria-labelledby="navbarDropdown">
						<a class="dropdown-item" href="{{url('/viewUploadVCF')}}" rel="nofollow">Upload VCF</a>
					</div>
				</li>
			@endif
			@if (App\Models\User::isSuperAdmin() || App\Models\User::isProjectManager())
				<li class="nav-item dropdown">
					<a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Report</a>
					<div class="dropdown-menu" aria-labelledby="navbarDropdown">
						@if (!\Config::get('site.isPublicSite'))
						<a class="dropdown-item" href="{{url('/viewDataIntegrityReport')}}" rel="nofollow">Data integrity</a>
						@endif
						<a class="dropdown-item" href="{{url('/viewAccessLogSummary')}}" rel="nofollow">Access log summary</a>
						<a class="dropdown-item" href="{{url('/viewAdminLog')}}" rel="nofollow">Admin log</a>
					</div>
				</li>
			@endif
					
		
			<li class="nav-item dropdown">
				<a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">About</a>
				<div class="dropdown-menu" aria-labelledby="navbarDropdown">
					<a class="dropdown-item" target=_blank href="https://github.com/CCRGeneticsBranch/oncogenomics/wiki/1.-Introduction" rel="noopener noreferrer">Tutorial</a>
					<a class="dropdown-item" href="{{url('/viewContact')}}" rel="nofollow">Contact</a>
				</div>
			</li>
    </ul>
    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
    	@if(null != App\Models\User::getCurrentUser())									
				<a class="nav-link" href="{{url('/viewSetting')}}" rel="nofollow"><img width="20" height="20" src="{{url('images/setting.png')}}"/>Setting</a>
			@if (App\Models\User::isSuperAdmin() || App\Models\User::isProjectManager())
				<a class="nav-link" href={{URL::route('users.list')}} rel="nofollow">Admin</a>
			@endif
			<a class="nav-link" href='#' rel="nofollow">{{App\Models\User::getCurrentUser()->email}}</a>
			<a class="nav-link" href="{{URL::action('\LaravelAcl\Authentication\Controllers\AuthController@getLogout')}}" rel="nofollow">Logout</a>			
		@else
			<a class="nav-link" href={{url("/login")}}>Login</a>
		@endif
		</ul>
  </div>
</nav>


