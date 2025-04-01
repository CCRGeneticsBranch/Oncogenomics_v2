@extends('laravel-authentication-acl::client.layouts.base')
@section('title')
User login
@stop
@section('content')
{{ HTML::script('js/jquery-3.6.0.min.js') }}
@if (Config::get('site.google_analytics_id') != "NA")
<!-- Google tag (gtag.js) --> <script async src=https://www.googletagmanager.com/gtag/js?id={!!Config::get('site.google_analytics_id')!!}></script> <script> window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag('js', new Date()); gtag('config', '{!!Config::get('site.google_analytics_id')!!}'); </script>
@endif
<style>
    .logissues{
        text-align:right;
        margin:0;padding:0;
    }
    .ff-alert{
        padding:0px;
        text-align:center;
        margin-bottom:0px;
    }
    .panel-heading{
        text-align:center;
    }
    h3.panel-title{
        text-decoration:bold;
        font-size: 1.5em;
    }

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
</style>

<?php 
$auth = Config::get('site.auth');
$isPub = Config::get('site.isPublicSite');
$reviwer_login = Config::get('site.reviwer_login');
$isCILogon = Config::get('site.isCILogon');
$auth_website = $auth['website'];
$auth_oauth = $auth['oauth'];
$OIDCClientID = $auth['client_id'];
$OIDCClientSecret = $auth['client_secrete'];
$OIDCRedirectURI = $auth['redirect'];
$OIDCScope = $auth['scope'];
$cilogin_url="${auth_oauth}?response_type=code&client_id=$OIDCClientID&skin=nih&redirect_uri=$OIDCRedirectURI&scope=$OIDCScope";
if ($isCILogon) {
    $cilogin_url="https://cilogon.org/authorize/?response_type=code&client_id=$OIDCClientID&skin=nih&redirect_uri=$OIDCRedirectURI&scope=$OIDCScope";
}

$name='';
$email='';
$last='';
$authenticated=false;
$idp='';
$isLogged=0;
Log::info("Request:");
Log::info($_REQUEST);
if (isset($_REQUEST['code'])){    
    Log::info("Auth site:".$auth['website'] . "/token");
    $isLogged='hide';
    $auth_curl = curl_init();
    $post_fields = "grant_type=authorization_code&client_id=" . $OIDCClientID . "&client_secret=" . $OIDCClientSecret . "&code=" . urlencode($_REQUEST["code"]) . "&redirect_uri=$OIDCRedirectURI&skin=nih&scope=" . $OIDCScope;    
    if ($isCILogon)
        curl_setopt($auth_curl, CURLOPT_URL, $auth['website']. "/token");
    else
        curl_setopt($auth_curl, CURLOPT_URL, $auth['website']. "/auth/oauth/v2/token");
    curl_setopt($auth_curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($auth_curl, CURLOPT_POSTFIELDS, $post_fields);
     curl_setopt($auth_curl, CURLOPT_POST,true);
    $auth_return = curl_exec($auth_curl);
    curl_close($auth_curl);
    Log::info("iTrust return:");
    Log::info($auth_return);
    $bearer_token_json = json_decode($auth_return, true);
    // unset ( $auth_return );

    if (isset($bearer_token_json["access_token"]) && isset($bearer_token_json["id_token"]) && isset($bearer_token_json["token_type"]) && $bearer_token_json["token_type"] === "Bearer") {
        $get_user_info_curl = curl_init();
        if ($isCILogon)
            curl_setopt($get_user_info_curl, CURLOPT_URL, $auth['website'] . "/userinfo");
        else
            curl_setopt($get_user_info_curl, CURLOPT_URL, $auth['website'] . "/openid/connect/v1/userinfo");

        curl_setopt($get_user_info_curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($get_user_info_curl, CURLOPT_POSTFIELDS, "access_token=" . urlencode($bearer_token_json["access_token"]));
        $get_user_info_return = curl_exec($get_user_info_curl);
        curl_close($get_user_info_curl);
        Log::info($get_user_info_return);
        $user_info = json_decode($get_user_info_return, true);
        // echo "userinfo:<br />";
        // dd($user_info);
        // we do not need this for iTrust
        if (!$reviwer_login && isset($user_info["idp_name"]) && !preg_match('/National Institutes of Health/',$user_info['idp_name'])){
            $authenticated = false;
            $message = "Please use NIH Credentials";
            $isLogged='';
        }else if ($isCILogon) {
            if (isset($user_info["given_name"]) && isset($user_info["family_name"]) && isset($user_info["email"])) {
                $name= $user_info["given_name"] ;
                $last = $user_info["family_name"] ;
                $email = $user_info["email"] ; 
                $idp =$user_info["idp_name"] ;
                if (preg_match("/nih/",$email)){
                    $idp='National Institutes of Health';
                }
                $authenticated=true;
            } else {
                echo "NIH Authentication failed! No email info<br /><br /><br />";
            }
        }else if (isset($user_info["first_name"]) && isset($user_info["last_name"]) && isset($user_info["email"])) {
        
            $name= $user_info["first_name"] ;
            $last = $user_info["last_name"] ;
            $email = $user_info["email"] ; 
            #$idp =$user_info["idp_name"] ;
            if (preg_match("/nih/",$email)){
                $idp='National Institutes of Health';
            }
            $authenticated=true;
            Log::info("idp: $idp");
            Log::info("Authentication successful!");
        } else {
            echo "NIH Authentication failed! No email info<br /><br /><br />";
        }
    }else{
        echo "NIH Authentication failed! No token info.<br />";
        //dd($bearer_token_json);
    }
}
?>
<script type="text/javascript">
    $(document).ready(function() {
        var name = '<?php echo $name;?>';
        var last = '<?php echo $last;?>';
        var email = '<?php echo $email;?>';
        var authenticated = '<?php echo $authenticated;?>';
        var idp = '<?php echo $idp;?>';
        console.log("found name " + email);
        if (email ){
            $('#loginID').val(email);
            $('#password').val(name + '||' + last + '||' + idp);
            $('#submitbtn').trigger('click');
        }else{
             $('input[type=radio][name=login_type]').change(            
                function() {  
                    changeLabel();
                }

            );

            changeLabel();        
            var x = document.getElementById("password");
            var style = window.getComputedStyle(x);
            if(style.webkitTextSecurity) {
            }
            else {
                x.setAttribute("type","password");        
            }
        }
        checkBrowserFF();
        //document.getElementById("password").setAttribute("type", "password");
    });


    function changeLabel() {
        if ($('input[type=radio][name=login_type]:checked').val() == "nih_login") {
             $('.reglogon').hide();
            $('.tokendiv').hide();
            $('.cilogondiv').show();
            // $("label[for='loginID'").text("Username");
            // $("label[for='password'").text("Password");$("label[for='loginID'").focus();
        }
        else if ($('input[type=radio][name=login_type]:checked').val() == "cilogon") { //Reinstate for CIlogon
            $('.reglogon').hide();
            $('.tokendiv').hide();
            $('.cilogondiv').show();
        }
        else if ($('input[type=radio][name=login_type]:checked').val() == "token") {
            $('.reglogon').hide();
            $('.cilogondiv').hide();
            $('.tokendiv').show();
        }
        
    }
    function checkBrowserFF() { 
        if(navigator.userAgent.indexOf("Chrome") != -1 ){
            $('.ff-alert').hide();
        }else{
           $('.ff-alert').html("Please use Chrome browser.");
        }
    }
    </script>

</script>
<div class="row centered-form <?php echo $isLogged;?>">
    <div class="col-xs-12 col-sm-8 col-md-4 col-sm-offset-2 col-md-offset-4">
        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title">{{Config::get('laravel-authentication-acl::app_name')}}</h3>
            </div>
            
            <?php if (!isset($message)) {$message = Session::get('message'); } ?>
            <div class="ff-alert alert alert-danger"></div>
            @if( isset($message) )
            <div class="alert alert-success">{{$message}}</div>
            @endif
            @if($errors && ! $errors->isEmpty() )
            @foreach($errors->all() as $error)
            <div class="alert alert-danger">{{$error}}</div>
            @endforeach
            @endif
            <div class="panel-body">
                {{Form::open(array('url' => URL::action("\LaravelAcl\Authentication\Controllers\AuthController@postClientLogin"), 'method' => 'post') )}}
                <fieldset>
                    <legend>Login type:</legend> 
                   <?php if ($reviwer_login!=1){?> 
                    @if( !isset($message) )
                   Please use your NIH Credentials<br /> <br /> 
                   @endif          
                   <div class="row cilogondiv" style="text-align:center;min-height:50px;">
                        <a class='btn btn-primary' href='<?php echo $cilogin_url; ?>'> Log in </a>
                  </div>
                    <?php }else{?> 
                        {!! Form::token() !!}                                 
                        {{Form::radio('login_type', 'cilogon', true) }}
                        {{Form::label('cilogon','Log in')}} <!-- Reinstate for CILogon --><br /> 
                        {{Form::radio('login_type', 'token') }}
                        {{Form::label('token','Reviewer Log in')}} <!-- Reinstate for CILogon -->
                    <?php } ?>
                </fieldset><HR />
                <div class="row reglogon hide">
                    <div class="col-xs-12 col-sm-12 col-md-12">
                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-addon"><label for="loginID">Username</label></span>
                                {{Form::text('loginID', '', ['id' => 'loginID', 'class' => 'form-control', 'text' => 'Username', 'required', 'autocomplete' => 'off'])}}
                            </div>
                        </div>
                    </div>
                </div>
               
                <div class="row reglogon hide">
                    <div class="col-xs-12 col-sm-12 col-md-12">
                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-addon"><label for="password">Password</label></i></span>
                                {{Form::text('password', '', ['id' => 'password', 'class' => 'form-control pw', 'text' => 'Password', 'required', 'autocomplete' => 'off'])}}
                            </div>
                        </div>
                    </div>
                    <div class="col-xs-12 col-sm-12 col-md-12">
                    <input type="submit" id='submitbtn' value="Login" class="btn btn-info btn-block"> 
                    </div>
                 </div>
        {{Form::close()}}

        @if($reviwer_login==1)
            <div class="row tokendiv">{{Form::open(array('url' => URL::action("\LaravelAcl\Authentication\Controllers\AuthController@postTokenLogin"), 'method' => 'post') )}}
                    
                    <div class="col-xs-12 col-sm-12 col-md-12">
                    <div >
                    If you have previously created an account with a token within the last 30 days, log in with your Username and Password:
                    </div>  
                    <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-addon"><label for="username">Username</label></span>
                                {{Form::text('username', '', ['id' => 'username', 'class' => 'form-control', 'text' => 'Username', 'required'=>0, 'autocomplete' => 'off'])}}
                            </div>
                        </div>
                    </div> 
                    <div class="col-xs-12 col-sm-12 col-md-12">

                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-addon"><label for="tokenpass">Password</label></i></span>
                                {{Form::text('password', '', ['id' => 'tokenpass', 'class' => 'form-control pw', 'text' => 'Password', 'required', 'autocomplete' => 'off'])}}
                            </div>
                        </div>
                        {{Form::hidden('idp','other')}}
                    </div>
                    <div class="col-xs-12 col-sm-12 col-md-12">
                    <input type="submit" id='submitbtn2' value="Submit" class="btn btn-info btn-block"> 
                    </div>                    
                </div>
             
                   
                 
                {{Form::close()}}
                 <!-- Reinstate for CILogon -->
                 <div class="row cilogondiv" style="text-align:center;min-height:50px;">
                    <!-- <div class="cilogondiv"> -->
                        <a class='btn btn-primary' href='<?php echo $cilogin_url; ?>'> Log in </a>
                    <!-- </div> -->
                  </div>
                  <div class="col-xs-12 col-sm-12 col-md-12 logissues" >
                     <div class="col-xs-6 col-sm-6 col-md-6" style="float:right">

        {{link_to_action('\LaravelAcl\Authentication\Controllers\AuthController@getContacts','Log in issues?')}} 
       
            </div>
                    <div class="col-xs-6 col-sm-6 col-md-6 tokendiv" style="float:right;text-align:left;">
      {{link_to_action('\LaravelAcl\Authentication\Controllers\UserController@signup','Have a token?')}} 
            </div>
           
                </div>
                                
               @endif   
               
                    
        
                
        
            </div>

        </div>
    </div>
</div>
@stop
