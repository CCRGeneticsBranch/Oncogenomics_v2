<html>
<head>
   <title>@yield('title')</title>
   <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
   <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
   
{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::style('css/style.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-core-css.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-blue/sm-blue.css') }}
{{ HTML::style('packages/bootstrap-switch-master/dist/css/bootstrap3/bootstrap-switch.min.css')}}
{{ HTML::script('js/jquery-3.6.0.min.js')}}
{{ HTML::script('packages/bootstrap-switch-master/dist/js/bootstrap-switch.min.js') }}
{{ HTML::script('packages/smartmenus-1.0.0-beta1/jquery.smartmenus.min.js') }}
{{ HTML::script('js/onco.js') }}

<style>
.sm-blue li {
  z-index:9999;
}
</style>
<script type="text/javascript">

  var IDLE_TIMEOUT = {{Config::get('session.lifetime')}} * 60; //seconds
  var _idleSecondsCounter = 0;  
  var login_url = '{{url('/login')}}';

  document.onclick = function() {
    _idleSecondsCounter = 0;
  };
  document.onmousemove = function() {        
    _idleSecondsCounter = 0;
  };
    
  document.onkeypress = function() {
    _idleSecondsCounter = 0;
  };
    
  window.setInterval(CheckIdleTime, 1000);

  function CheckIdleTime() {
          _idleSecondsCounter++;
          //console.log(_idleSecondsCounter);
          var oPanel = document.getElementById("SecondsUntilExpire");
          if (oPanel)
              oPanel.innerHTML = (IDLE_TIMEOUT - _idleSecondsCounter) + "";
          if (_idleSecondsCounter >= IDLE_TIMEOUT) {
              //alert("Time expired!");
              document.location.href = login_url;
          }
  }

  //window.setTimeout(function() {
  // window.location.href = '{{url('/login')}}';
  //}, {{Config::get('session.lifetime')}} * 60000);

	$(function() {
		$('#main-menu').smartmenus({
			subMenusSubOffsetX: 1,
			subMenusSubOffsetY: -8
		});
	});
</script>
 
</head>
<body>
    <div id="page_wrapper">  
        @include('layouts.navtop')
        <div id="sb-site">
        	@yield('content')
        </div>        
        @include('layouts.footer')
    </div>

</body>
</html>
