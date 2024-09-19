<?php namespace LaravelAcl\Authentication\Controllers;

use LaravelAcl\Authentication\Models;
use Sentry, Redirect, App, Config, DB, Log, View, Request, Session, Mail;
use Illuminate\Support\MessageBag;
use LaravelAcl\Authentication\Validators\ReminderValidator;
use LaravelAcl\Library\Exceptions\JacopoExceptionsInterface;
use LaravelAcl\Authentication\Services\ReminderService;


class AuthController extends Controller {

    protected $authenticator;
    protected $reminder;
    protected $reminder_validator;

    public function __construct(ReminderService $reminder, ReminderValidator $reminder_validator)
    {
        $this->authenticator = App::make('authenticator');
        $this->reminder = $reminder;
        $this->reminder_validator = $reminder_validator;
    }

    public function getClientLogin()
    {
        return view('laravel-authentication-acl::client.auth.login');
    }

    public function getAdminLogin()
    {
        Log::info('in getAdminLogin');
        return View::make('laravel-authentication-acl::admin.auth.login');
    }

    public function postAdminLogin()
    {   
        Log::info('in postAdminLogin');
        list($email, $password, $remember, $nih_login) = $this->getLoginInput();
        if ($email==-1){
            return Redirect::action('postTokenLogin')->withInput()->withErrors($errors);
        }
        try
        {
            $this->authenticator->authenticate(array(
                                                "email" => $email,
                                                "password" => $password
                                             ), $remember);
        }
        catch(JacopoExceptionsInterface $e)
        {
            $errors = $this->authenticator->getErrors();
            return Redirect::action('LaravelAcl\Authentication\Controllers\AuthController@getAdminLogin')->withInput()->withErrors($errors);
        }

        return Redirect::to('/admin/users/dashboard');
    }

    public function sendEmailToAdmin($id, $user, $fn, $ln) {
        Log::info('in sendEmailToAdmin');
        $name = "$user ($fn $ln)";
        $users = \Jacopo\Authentication\Models\User::all();
        $admin_emails = array();
        foreach ($users as $user) {
            foreach ($user->permissions as $permission => $permission_id) {
                if ($permission == "_superadmin")
                //if ($permission == "_tester")
                    $admin_emails[] = $user->email_address;
            }                    
        }

        #mail($admin_emails, "User $name has logged in OncogenomicsPub", $message, 'From: Oncogenomics@mail.nih.gov');

        Mail::send(
           'emails.auth.registerNotify',
           array(
              'name'=>$name,
              'id'=>$id
           ), 
           function($message) use ($admin_emails, $name, $id) {
                $message->to($admin_emails)->subject("User $name has logged in OncogenomicsPub");              
           }
        );
    }

    public function notifyAdmin() { 
        Log::info('in notifyAdmin');
        $user_id = Request::input('user_id');   
        $name    = Request::input('name');
        $email = Request::input('email');
        $department = Request::input('department');
        $tel = Request::input('tel');
        $project = Request::input('project');
        $reason = Request::input('reason');
        $users = \LaravelAcl\Authentication\Models\User::all();
        $admin_emails = array();
        $id = \LaravelAcl\Authentication\Models\User::where('email', $user_id)->get()[0]->id;
        foreach ($users as $user) {
            foreach ($user->permissions as $permission => $permission_id) {
                if ($permission == "_superadmin" || $permission == "_projectmanager") {
                    Log::info("admin email: ".$user->email_address);
                    if ($user->email_address != "" && str_contains($user->email_address, "@"))
                        $admin_emails[] = $user->email_address;
                }
            }                    
        }

        if (Mail::send(
           'emails.auth.registerNotify',
           array(
                'id' => $id,
                'name'=>$name,
                'user_id'=>$user_id,
                'email'=>$email,
                'department'=>$department,
                'tel'=>$tel,
                'project'=>$project,
                'reason'=>$reason
           ), 
           function($message) use ($admin_emails, $name, $user_id) {
                $message->to($admin_emails)->subject("User $name has logged in OncogenomicsPub");              
           }
        )) {
           Log::info("New User mail sent to admins!! user=$email\n");

        } else {
            Log::info('New User email not sent!' . $email. "\n");
        }        
        return View::make("pages/error",['message' => 'Your request has been sent to Oncogenomics administrators, thank you!']);

    }
    public function postTokenLogin(){
        list($email, $password, $remember, $token) = $this->getTokenInput();
        $email = strtolower($email);
        $first_time = false;
        // if ($nih_login) {
            $app_path = app_path();
            if ($email=='failed'){
                $errors = new MessageBag();
                $errors->add('password', 'Passwords do not match' );
                return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@signup')->withInput()->withErrors($errors);
            }
            if (strpos($app_path, "clinomics_dev2") !== false) {
                if ( !preg_match("/^(khanjav|weij|vuonghm|chouh|hue|hvr|failed|hsien|tyagi|xil2|mraff|pehrsson)/",$email )){#!= "khanjav" && $email != "weij" && $email != "vuonghm" && $email != "chouh" && $email!=) {
                    $errors = new MessageBag();
                    $errors->add('login', 'Only developers can access this site! email=' . $email );
                    return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@signup')->withInput()->withErrors($errors);
                }
            }
            
            if ($remember=='other') {
                if ($token){
                    // fields usually retrieved by login authentication process
                    $fn = $email;
                    $ln = '';
                    $tel = 'XXX-XXX-XXXX';
                    $department = "Reviewers";
                    $nih_email="$email@test.com";
                    $first_time=true;
                }else{
                    $nih_email="$email@test.com";
                    $remember='NIH login';
                    $fn=$email;
                    $ln = "";
                }
            }
            Log::info("[postToken] user first=" . $fn);
            Log::info("[postToken] user last=". $ln);
            
            if ($nih_email == null) {
                $errors = new MessageBag();
                $errors->add('login', 'Incorrect NIH username/password!');
                Log::info("adding Incorrect NIH user name to log in page");
                return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@signup')->withInput()->withErrors($errors);
            }elseif($nih_email == '-1'){
                $errors = new MessageBag();
                $errors->add('login','Could not authenticate your user.');
                Log::info("service account login?? " . $email);
                return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@signup')->withInput()->withErrors($errors);
            } elseif ($token) {
                $sql= " select tokenid from reviewer_tokens where exp_date > (select to_date(to_char(SYSDATE,'DD-MON-YY'),'DD-MON-YY') from dual)";
                $rows = DB::select($sql);
                $found = false;
                foreach ($rows as $row) {
                    if ($row->tokenid == $token) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $errors = new MessageBag();
                    $errors->add('tokenpass','Invalid or Expired Token.');
                    return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@signup')->withInput()->withErrors($errors);
                }

                $sentry = App::make('sentry');
                try {
                    $user = $sentry->findUserByLogin($email);
                    $errors = new MessageBag();
                    $errors->add('username','User account already exists.');
                    return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@signup')->withInput()->withErrors($errors);

                }
                catch (\Exception $e) {
                    $user = $sentry->register(array("email" => $email,"password" => $password, "email_address" => $nih_email), true);
                    $profile_repo = App::make('profile_repository');
                    #$profile_repo->attachProfile($user, $fn, $ln);
                    $profile_repo->attachEmptyProfile($user, $fn, $ln);
                    $first_time = true;
                }


                
            }
            try
            {
                $this->authenticator->authenticate(array(
                            "email" => $email,
                            "password" => $password
                       ), $remember);
                    //Session::forget('auth_type');
                    //Session::forget('user');
                    //Session::put('auth_type', 'site');
                    //Session::put('user', Sentry::getUser());
            }
            catch(JacopoExceptionsInterface $e)
            {
                $errors = $this->authenticator->getErrors();
                if($token){
                    // dd('error');
                    $errors = new MessageBag();
                    $errors->add('username','User account already exists.');
                    return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@signup')->withInput()->withErrors($errors);
                }
                return Redirect::action('\LaravelAcl\Authentication\Controllers\AuthController@getClientLogin')->withInput()->withErrors($errors);
            }
        //Hack for token login
        $url = url("/");
        /*
        $url = 'https://'. $_SERVER['SERVER_NAME'];
        if (preg_match("/(.*\/public\/)/",$_SERVER['REQUEST_URI'],$matches)){
            $url.=$matches[0];
        }else{
            $url='https://clinomics.ccr.cancer.gov/clinomics/public';
        }
        */
        if ($first_time){
            Log::info("first time login and setting setProjectByToken/$email/$token");
            Log::info("curl: $url/setProjectByToken/$email/$token");
            $res = rtrim(`curl $url/setProjectByToken/$email/$token 2>/dev/null`);
            Log::info("/viewProjectDetails/$res");
            return Redirect::intended("/viewProjectDetails/$res");
        }
        else{
             Log::info("Returning user $email"); 
             return Redirect::intended('/viewProjects/');
             $res = rtrim(`curl $url/getProjectByUser/$email 2>/dev/null`);
             return Redirect::intended('/viewProjectDetails/' . $res);
        }

    }
    
    public function postClientLogin()
    {   
        Log::info("postClientLogin");
        list($email, $password, $remember, $nih_login) = $this->getLoginInput();
        Log::info($password);
        $email = strtolower($email);
        $first_time = false;

    	// if ($nih_login) {
            $app_path = app_path();
            if (strpos($app_path, "clinomics_dev") !== false) {
                if ( 1==2 && !preg_match("/^(khanjav|weij|chea|chouh|che|hsien|tyagi|manoj|xil2|mraff|pehrsson)/",$email )){#!= "khanjav" && $email != "weij" && $email != "vuonghm" && $email != "chouh" && $email!=) {
                    $errors = new MessageBag();
                    $errors->add('login', 'Only developers can access this site!email=' . $email );
                    return Redirect::action('\LaravelAcl\Authentication\Controllers\AuthController@getClientLogin')->withInput()->withErrors($errors);
                }
            }
            if ($nih_login) {
        		list($nih_email, $fn, $ln, $tel, $department) = $this->nih_login($email, $password);
                Log::info("user first=" . $fn);
                Log::info("user last=". $ln);
            }else{
                $arr = explode('||',$password);
                $fn = $arr[0];
                $ln= $arr[1];
                $department= $arr[2];
                $tel = 'XXX-XXX-XXXX';
                if (preg_match("/^([^@]*)@/",$email,$matches)){
                    $nih_email = $email;
                    // $email = $matches[1];
                }
            }
            $password = "uHip&x.kQz!e";
            
    		if ($nih_email == null) {
    			$errors = new MessageBag();
    			$errors->add('login', 'Incorrect NIH username/password!');
                Log::info("adding Incorrect NIH user name to log in page");
    			return Redirect::action('\LaravelAcl\Authentication\Controllers\AuthController@getClientLogin')->withInput()->withErrors($errors);
    		}elseif($nih_email == '-1'){
                $errors = new MessageBag();
                $errors->add('login','Could not authenticate your user.');
                Log::info("service account login?? " . $email);
                return Redirect::action('\LaravelAcl\Authentication\Controllers\AuthController@getClientLogin')->withInput()->withErrors($errors);
            } else {		
    			$sentry = App::make('sentry');
    			try {
    				$user = $sentry->findUserByLogin($email);
    			}
    			catch (\Exception $e) {
                    Log::info("Registering $email: email_address: $email");
    				$user = $sentry->register(array("email" => $email,"password" => $password, "email_address" => $email), true);
                    $profile_repo = App::make('profile_repository');
                    #$profile_repo->attachProfile($user, $fn, $ln);
                    $profile_repo->attachEmptyProfile($user, $fn, $ln);
                    $first_time = true;
                    if (preg_match("/reviewer/",$email)){
                        //This is the default passwords for the reviewers.  Comment out next three lines if you want to create a new "reviewer" user
                        $this->auth = App::make('authenticator');
                        $token = $this->auth->getToken($email);
                        return Redirect::action('\LaravelAcl\Authentication\Controllers\AuthController@getChangePassword')->withInput(["email" => $email, "token" => $token, "login"=>true]);
                    }
    			}
    			
    		}
            Log::info("The email is " . $email . " and nih_login=" . $nih_login . ".  Is this the first time?($first_time)");
            try
            {
                $this->authenticator->authenticate(array(
                            "email" => $email,
                            "password" => $password
                       ), $remember);
                    //Session::forget('auth_type');
                    //Session::forget('user');
                    //Session::put('auth_type', 'site');
                    //Session::put('user', Sentry::getUser());
            }
            catch(JacopoExceptionsInterface $e)
            {
                $errors = $this->authenticator->getErrors();
                return Redirect::action('\LaravelAcl\Authentication\Controllers\AuthController@getClientLogin')->withInput()->withErrors($errors);
            }

    	// }else{
     //        dd($email,$password,$remember,$nih_login);
     //    }
    	
        if ($first_time){
            Log::info("going to first_login page!!!");
            $to      = 'hsien-chao.chou@nih.gov,khanjav@mail.nih.gov,weij@mail.nih.gov';
            $subject = 'First time user login';
            $message = "user $email is about to login in for the first time....look out for registration email!";
            $headers = 'From: Oncogenomics@mail.nih.gov' ;
            UserController::doRefreshUserProject();
            #DB::statement("BEGIN Dbms_Mview.Refresh('USER_PROJECTS','C');END;");

            mail($to, $subject, $message, $headers);
            return View::make('laravel-authentication-acl::client.auth.first-login', ['user_id' => $email, 'name' => "$fn $ln", 'department' => $department, 'tel' => $tel, 'email' => $nih_email]);
        }
        return Redirect::intended('/home');
            #return Redirect::to(Config::get('laravel-authentication-acl::config.user_login_redirect_url'));
            #$previous_url = Session::get('previous_url');
            #return Redirect::to($previous_url);

    }

    /**
     * Logout utente
     * 
     * @return string
     */
    public function getLogout()
    {
	Session::forget('auth_type');
	Session::forget('user');
	$this->authenticator->logout();

        return Redirect::to('/');
    }

    /**
     * Recupero password
     */
    public function getReminder()
    {
        return View::make("laravel-authentication-acl::client.auth.reminder");
    }
    /**
     * Recupero password
     */
    public function getContacts()
    {
        return View::make("pages/viewContact");
    }

    /**
     * Invio token per set nuova password via mail
     *
     * @return mixed
     */
    public function postReminder()
    {
        $email = Request::input('email');

        try
        {
            $this->reminder->send($email);
            return Redirect::to("/user/reminder-success");
        }
        catch(JacopoExceptionsInterface $e)
        {
            $errors = $this->reminder->getErrors();
            return Redirect::action("Jacopo\\Authentication\\Controllers\\AuthController@getReminder")->withErrors($errors);
        }
    }

    public function getChangePassword()
    {
        $email = Request::input('email');
        $token = Request::input('token');
         $login = Request::input('login');
        return View::make("laravel-authentication-acl::client.auth.changepassword", array("email" => $email, "token" => $token,"login"=>$login) );
    }

    public function postChangePassword()
    {
        $email = Request::input('email');
        $token = Request::input('token');
        $password = Request::input('password');
        $password2 = Request::input('confirmpass');
        $login= Request::input('login');
        if (! $this->reminder_validator->validate(Input::all()) )
        {
          return Redirect::action("Jacopo\\Authentication\\Controllers\\AuthController@getChangePassword")->withErrors($this->reminder_validator->getErrors())->withInput();
        }
        if ($password!=$password2){
            $errors = new MessageBag();
            $errors->add('login', 'Passwords did not match.' );
            return Redirect::action('\LaravelAcl\Authentication\Controllers\AuthController@getChangePassword')->withInput()->withErrors($errors);
        }
        try
        {
            $this->reminder->reset($email, $token, $password);
        }
        catch(JacopoExceptionsInterface $e)
        {
            $errors = $this->reminder->getErrors();
            return Redirect::action("Jacopo\\Authentication\\Controllers\\AuthController@getChangePassword")->withErrors($errors);
        }

        if ($login==true){
            $remember='National Institutes of Health';
            try
            {
                $this->authenticator->authenticate(array(
                            "email" => $email,
                            "password" => $password
                       ), $remember);
            }
            catch(JacopoExceptionsInterface $e)
            {
                $errors = $this->authenticator->getErrors();
                return Redirect::action('\LaravelAcl\Authentication\Controllers\AuthController@getClientLogin')->withInput()->withErrors($errors);
            }
        
            return Redirect::intended('/');
        }else{
            return Redirect::to("user/change-password-success");
        }

    }
    /**
     * @return array
     */
    private function getLoginInput()
    {        
        $loginID    = Request::input('loginID');
        $password = Request::input('password');
        $idp = Request::input('idp');
        if ($idp == "National Institutes of Health"){
             $nih_login = true;
        }
        if ($idp == 'other'){
            return array(-1,-1,-1,-1);
        }
        $login_type = Request::input('login_type');
        $nih_login = ($login_type == 'nih_login');

        return array($loginID, $password, $idp, $nih_login);
    }
     private function getTokenInput()
    {
        $loginID    = Request::input('username');
        $password = Request::input('password');
        $idp = Request::input('idp');
        if ($idp == "National Institutes of Health"){
             $nih_login = true;
        }
        $token = Request::input('tokenpass');
        $login_type = Request::input('login_type');
        $confirmpass = Request::input('password2');
        if ($confirmpass!=$password && $token){
            return array('FAILED',$password,$idp,$token);
        }
        $nih_login = ($login_type == 'nih_login');
        return array($loginID, $password, $idp, $token);
    }

    private function nih_login($cn, $ldappass) {
        //$cn = substr($ldaprdn, 0, strpos($ldaprdn, '@'));
        $ldaprdn = $cn."@nih.gov";
    	$filter = "cn=$cn";
        //$filter = "cn=$cn";
        if (preg_match("/irtappscan/",$cn)){
            return array($cn.'@mail.nih.gov','Service Account','','XXX-XXX-XXXX','NIH');
        }
    	$attributes = array('givenname', 'sn', 'mail', 'department', 'organization', 'telephonenumber','physicaldeliveryofficename','roomnumber', 'streetaddress','l','st','postalcode'); 
    	$ldapconn = ldap_connect("ldaps://ldapad.nih.gov") or die("Could not connect to LDAP server.");
    	 
    	ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
    	
    	if ($ldapconn) {
    	
    		// binding 
    		try {
    			$ldapbind = ldap_bind($ldapconn, $ldaprdn, $ldappass);
    		}
    		catch (\ErrorException $e) {
                $ldapbind=null;
    		}
    	
    		if ($ldapbind) {
    			
    			$msg = "LDAP bind anonymous successful...";
    			$results = ldap_search($ldapconn, "OU=NIH,OU=AD,DC=nih,DC=gov", $filter, $attributes);
    			// Dump records into array
    			$attrs = ldap_get_entries($ldapconn, $results);
                if (count($attrs[0])<10){#authenticated
                   if (preg_match("/ncif-www-.*-svc/i",$attrs[0]['dn']) && preg_match("/ServiceAccounts/",$attrs[0]['dn'])){
                        return array($cn.'@mail.nih.gov','Service Account','','XXX-XXX-XXXX','NIH');
                    }else{
                        return array('-1','Invalid','User','XXX-XXX-XXXX','Unknown');
                    }
                }else{
                    return array($attrs[0]['mail'][0], $attrs[0]['givenname'][0], $attrs[0]['sn'][0], $attrs[0]['telephonenumber'][0], $attrs[0]['physicaldeliveryofficename'][0]);
                }
    			//return $attrs[0]['givenname'][0];
    			
    		}
    		else
    			return null;
    	}
    	return null;
	
    }




}
