<div class="navbar navbar-inverse navbar-fixed-top" role="navigation">
    <div class="container-fluid margin-right-15">
        <div class="navbar-header">
            <!--a class="navbar-brand bariol-thin" href="{{url('/')}}">{{$app_name}}</a-->
            <a class="navbar-brand bariol-thin" href={{url("/")}}><img style="height:22px;" src="{{url('images/logo.JK.db.gif')}}" alt="OncoGenomics Database"/></a>
            <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#nav-main-menu">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            </button>
        </div>
        <div class="collapse navbar-collapse" id="nav-main-menu">
            <ul class="nav navbar-nav">
                <li> <a href="{{url('/')}}">Home</a></li>
                @if(isset($menu_items))
                    @foreach($menu_items as $item)
                        @if ($item->getName() != "Permission" || \App\Models\User::isSuperAdmin())
                        <li class="{!! LaravelAcl\Library\Views\Helper::get_active_route_name($item->getRoute())!!}"> <a href="{{url($item->getLink())}}">{{$item->getName()}}</a></li>
                        @endif
                    @endforeach
                @endif
            </ul>
            <div class="navbar-nav nav navbar-right">
                <li class="dropdown dropdown-user">
                    <a class="dropdown-toggle" data-toggle="dropdown" href="#" id="dropdown-profile">
                        <span id="nav-email">{{isset($logged_user) ? $logged_user->email : 'User'}}</span> <i class="fa fa-caret-down"></i>
                    </a>
                    <ul class="dropdown-menu">
                            <li>
                                <a href="{{URL::route('users.selfprofile.edit')}}"><i class="fa fa-user"></i> Your profile</a>
                            </li>
                            <li class="divider"></li>
                        <li>
                            <a href="{{URL::action('\LaravelAcl\Authentication\Controllers\AuthController@getLogout')}}"><i class="fa fa-sign-out"></i> Logout</a>
                        </li>
                    </ul>
                </li>
            </div><!-- nav-right -->
        </div><!--/.nav-collapse -->
    </div>
</div>