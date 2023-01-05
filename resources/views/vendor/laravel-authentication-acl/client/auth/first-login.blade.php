@extends('laravel-authentication-acl::client.layouts.base')
@section('title')
User login
@stop
@section('content')
{{ HTML::script('js/jquery-3.6.0.min.js') }}
<style>
    input.pw { 
        font-family: 'password';
        text-security:disc;
        -webkit-text-security:disc;
        -moz-text-security:disc;
     }

     @font-face {
      font-family: 'password';
      src: url('fonts/password.woff2') format('woff2'),
           url('fonts/password.woff') format('woff'),
           url('fonts/password.ttf') format('truetype');
      font-weight: normal;
      font-style: normal;
    }
    .input-group-addon {
    min-width:200px;
    text-align:right;
}

</style>
<script type="text/javascript">
    $(document).ready(function() {
        $('input[type=radio][name=login_type]').change(            
            function() {  
                changeLabel();
            }

        );

        changeLabel();        
        //document.getElementById("password").setAttribute("type", "password");
    });

    function changeLabel() {
        
        if ($('input[type=radio][name=login_type]:checked').val() == "nih_login") {
            $("label[for='loginID'").text("NIH Username");
            $("label[for='password'").text("NIH Password");
        }
        else {
            $("label[for='loginID'").text("Email");
            $("label[for='password'").text("Password");
        }
        $("label[for='loginID'").focus();
    }
</script>
<div class="row">
    <div class="col-xs-12 col-sm-8 col-md-6 col-sm-offset-2 col-md-offset-2">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title bariol-thin">First time login to {{Config::get('laravel-authentication-acl::app_name')}}</h3>
            </div>            
            <div class="panel-body">
                {{Form::open(array('url' => URL::action("Jacopo\Authentication\Controllers\AuthController@notifyAdmin"), 'method' => 'post') )}}
                <fieldset>
                    <legend>This is the first time you've log into Oncogenomics. Please fill in the following information and we will grant you permission. Thank you!<BR></legend>                                                
                </fieldset>
                <div class="row">
                    <div class="col-xs-12 col-sm-12 col-md-12">
                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-addon"><label for="user_id">ID</label></span>
                                {{Form::text('user_id', $user_id, ['id' => 'user_id', 'class' => 'form-control', 'text' => 'ID', 'required', 'autocomplete' => 'off', 'readonly' => 'true'])}}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-12 col-sm-12 col-md-12">
                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-addon"><label for="name">Name</label></span>
                                {{Form::text('name', $name, ['id' => 'name', 'class' => 'form-control', 'text' => 'Name', 'required', 'autocomplete' => 'off', 'readonly' => 'true'])}}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-12 col-sm-12 col-md-12">
                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-addon"><label for="name">Email</label></span>
                                {{Form::text('email', $email, ['id' => 'email', 'class' => 'form-control', 'text' => 'Email', 'required', 'autocomplete' => 'off', 'readonly' => 'true'])}}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-12 col-sm-12 col-md-12">
                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-addon"><label for="department">Institute</label></i></span>
                                {{Form::text('department', $department, ['id' => 'department', 'class' => 'form-control', 'text' => 'Institute/Branch/Lab', 'required', 'autocomplete' => 'off'])}}
                            </div>
                        </div>
                    </div>
                </div>                
               <!--  <div class="row">
                    <div class="col-xs-12 col-sm-12 col-md-12">
                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-addon"><label for="tel">Phone number</label></i></span>
                                {{Form::text('tel', $tel, ['id' => 'tel', 'class' => 'form-control', 'text' => 'Phone number', 'required', 'autocomplete' => 'off'])}}
                            </div>
                        </div>
                    </div>
                </div> -->
                <div class="row">
                    <div class="col-xs-12 col-sm-12 col-md-12">
                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-addon"><label for="project">Project/protocol</label></i></span>
                                {{Form::text('project', '', ['id' => 'project', 'class' => 'form-control', 'text' => 'Project/protocol', 'required', 'autocomplete' => 'off'])}}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-12 col-sm-12 col-md-12">
                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-addon"><label for="reason">Reason for access</label></i></span>
                                {{Form::textarea('reason', '', ['id' => 'reason', 'class' => 'form-control', 'text' => 'Reason for access', 'required', 'autocomplete' => 'off'])}}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
			<!--div class="col-xs-12 col-sm-12 col-md-12">                
                {{Form::label('remember','Remember me')}}
                {{Form::checkbox('remember')}}
			</div-->
		          </div>
		
                <input type="submit" value="Submit" class="btn btn-info btn-block">
                {{Form::close()}}
        <!--div class="row">
            <div class="col-xs-12 col-sm-12 col-md-12 margin-top-10">
        {{link_to_action('Jacopo\Authentication\Controllers\AuthController@getReminder','Forgot password?')}}
        or <a href="{{URL::action('Jacopo\Authentication\Controllers\UserController@signup')}}"><i class="fa fa-sign-in"></i> Signup here</a>
            </div>
        </div-->
            </div>
        </div>
    </div>
</div>
@stop
