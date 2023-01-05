<!DOCTYPE html>
<head>
    <meta charset="utf-8">
    <title>User signup</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="keywords" content="">
    <meta name="author" content="">

    {{ HTML::style('packages/jacopo/laravel-authentication-acl/css/bootstrap.min.css') }}
    {{ HTML::style('packages/jacopo/laravel-authentication-acl/css/style.css') }}
    {{ HTML::style('packages/jacopo/laravel-authentication-acl/css/strength.css') }}
    {{ HTML::style('//netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css') }}
    {{ HTML::style('packages/jacopo/laravel-authentication-acl/css/fonts.css') }}
    <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
    <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
    <![endif]-->
    <style>
      .panel-heading{
        text-align:center;
    }
    h3.panel-title{
        text-decoration:bold;
        font-size: 1.5em;
    }
  </style>
</head>

<body>
<div class="container">
    <div class="row centered-form">
        <div class="col-xs-12 col-sm-8 col-md-4 col-sm-offset-2 col-md-offset-4">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title ">{{Config::get('laravel-authentication-acl::app_name')}}</h3>
                </div>
                 <?php $message = Session::get('message'); ?>
            
                @if( isset($message) )
                <div class="alert alert-success">{{$message}}</div>
                @endif
                <div class="panel-body">
                    {{Form::open(["action" => 'Jacopo\Authentication\Controllers\AuthController@postTokenLogin', "method" => "POST", "id" => "user_signup"])}}
                    {{-- Field hidden to fix chrome and safari autocomplete bug --}}
                    {{Form::password('__to_hide_password_autocomplete', ['class' => 'hidden'])}}
                    

                        <div class="row">
                            <div class="col-xs-12 col-sm-12 col-md-12">
                                <div class="form-group">
                                    <div class="input-group">
                                         <span class="input-group-addon"><label for="tokenpass">Token</label></span>
                                        {{Form::text('tokenpass', '',['id' => 'tokenpass', 'class' => 'form-control', 'placeholder' => 'Enter Token ID here', 'required', 'autocomplete' => 'off'])}}
                                    </div>
                                    <span id="token-msg"class="text-danger name">{{$errors->first('tokenpass')}}</span>                                  
                                </div>
                            </div>
                          </div>
                          Create a user name and password:
                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="fa fa-envelope"></i></span>
                                {{Form::text('username', '', ['id' => 'username', 'class' => 'form-control', 'placeholder' => 'Username', 'required', 'autocomplete' => 'off'])}}
                            </div>
                            <span class="text-danger name">{{$errors->first('username')}}</span>
                        </div>
{{Form::password('__to_hide_password_autocomplete', ['class' => 'hidden'])}}
                        <div class="row">
                            <div class="col-xs-12 col-sm-12 col-md-12">
                                <div class="form-group">
                                    <div class="input-group">
                                        <span class="input-group-addon"><i class="fa fa-lock"></i></span>
                                        {{Form::password('password', ['id' => 'password', 'class' => 'form-control', 'placeholder' => 'Password', 'required', 'autocomplete' => 'off'])}}
                                        <!-- <?php //echo "token=$token";?> -->
                                    </div>
                                   
                                </div>
                            </div>
                            <div class="col-xs-12 col-sm-12 col-md-12">
                                <div class="form-group">
                                    <div class="input-group">
                                        <span class="input-group-addon"><i class="fa fa-lock"></i></span>
                                        {{Form::password('password2', ['class' => 'form-control', 'id' =>'password2', 'placeholder' => 'Confirm password', 'required'])}}
                                    </div>
                                     <span class="text-danger">{{$errors->first('password')}}</span>
                                </div>
                            </div>
                            <div class="col-xs-12 col-sm-12 col-md-12">
                              <div class="form-group">
                                <div id="pass-info"></div>
                              </div>
                            </div>
                            {{Form::hidden('loginID','')}}
                            {{Form::hidden('idp','other')}}
                            {{-- Captcha validation --}}
                            @if(isset($captcha) )
                            <div class="col-xs-12 col-sm-12 col-md-12">
                                <div class="form-group">
                                    <div class="input-group">
                                        <span id="captcha-img-container">
                                            @include('laravel-authentication-acl::client.auth.captcha-image')
                                        </span>
                                        <a id="captcha-gen-button" href="#" class="btn btn-small btn-info margin-left-5"><i class="fa fa-refresh"></i></a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xs-12 col-sm-12 col-md-12">
                                <div class="form-group">
                                    <div class="input-group">
                                        <span class="input-group-addon"><i class="fa fa-picture-o"></i></span>
                                        {{Form::text('captcha_text',null, ['class'=> 'form-control', 'placeholder' => 'Fill in with the text of the image', 'required', 'autocomplete' => 'off'])}}
                                    </div>
                                </div>
                                <span class="text-danger">{{$errors->first('captcha_text')}}</span>
                            </div>
                            @endif
                        </div>
                        <input type="submit" value="Register" id='submitBtn' class="btn btn-info btn-block">
                    </form>
                <div class="row">
                    <div class="col-xs-12 col-sm-12 col-md-12 margin-top-10">
                        {{link_to_action('Jacopo\Authentication\Controllers\AuthController@getClientLogin','Already have an account? Login here')}}
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
</div>
  {{-- Js files --}}
  {{ HTML::script('js/jquery-3.6.0.min.js') }}
  {{ HTML::script('packages/jacopo/laravel-authentication-acl/js/vendor/password_strength/strength.js') }}

  <script>
    $(document).ready(function() {
      //------------------------------------
      // password checking
      //------------------------------------
      $('#submitBtn').attr("disabled", true);
      var password1 		= $('#password'); //id of first password field
      var password2		= $('#password2'); //id of second password field
      var passwordsInfo 	= $('#pass-info'); //id of indicator element
      passwordStrengthCheck(password1,password2,passwordsInfo);
      //------------------------------------
      // captcha regeneration
      //------------------------------------
      $("#captcha-gen-button").click(function(e){
      		e.preventDefault();

      		$.ajax({
              url: "/captcha-ajax",
              method: "POST"
            }).done(function(image) {
              $("#captcha-img-container").html(image);
            });
      });

      $("#tokenpass").on("focusout",function(e){         
      });
      
      $("#username").on("focusout",function(e){
        if ($("#username").val().match(/[^a-zA-Z0-9\_]/)){
          $('.text-danger.name').html("Note: Spaces and special characters removed.");
          var newname = $("#username").val().replace(/[^a-zA-Z0-9\_]/g,'');
          $("#username").val(newname);
        }  
      });

      /*
      $("#submitBtn").click(function(e){
        if (!inArray($.trim($("#tokenpass").val()),validTokens)){
            alert("Invalid or Expired Token");
            return false;
        }  
      });
      */


       
    });
    function inArray(needle,haystack){
      var length = haystack.length;
      for(var i = 0; i < length; i++) {
          if(haystack[i] == needle) return true;
      }
      return false;
    }
  </script>
</body>
</html>
