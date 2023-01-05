<?php 
/**
 * @desc This class manages user authenication (Site Minder or LDAP)
 *
 * @author The Advanced Biomedical Computing Center (ABCC) 
 * @version 1.0
 * @package abcc_webcommon
 * @subpackage webcommon_classes 
 *
 * @param bool $debug Turn on/off the debug feature
 * @param string $auth_type Type of authenication. LDAP (ldap) or SM (Site Minder)
 * @param mixed $logout_url Define the url for logging out
 * @param mixed $sm_login_url Define the login url for Site Minder
 * @param mixed $sm_logout_page Define the logout url for SiteMinder
 * @param array $access_exceptions Define an array of fields or attributes to be ignored
 * @param mixed $access_start_page Define the "home" page after successfully logged in
 * @param string $session_key Define the session identifier for application
 * @param string $cookie_key Define the cookie key to use in helping identifying cookies
 * @param string $siteMinder_cookie_key Define the cookie key for Site Minder to use in helping identifying cookies
 * @param string $isp_redirect_key Define the redirect key for Information System Program
 * @param mixed $ldap_host Define the LDAP host address
 * @param int $ldap_port Define the LDAP port number
 * @param mixed $user_basedn Define the base DN for user (people) information
 * @param mixed $group_basedn Define the base DN for group information
 * @param mixed $ldap_groups_key Define the key to identify group
 * @param mixed $ldap_login_url Define the url for the LDAP login
 * @param mixed $ldap_logout_page Define the url for the LDAP logout
 * @param mixed $ad_host Define the active directory host address
 * @param int $ad_port Define the active directory port number
 * @param mixed $ad_basedn Define the active directory base DN
 * @param array $ldapVars List of all LDAP variables
 * @param array $siteMinderVars List of all vars sent from SiteMinder
 *
 * Methods:
 * 1. siteMinder_login - Site Minder login process.  Connect to database for site minder access log and insert site minder login data to database.  Sessions are created.
 * 2. abcc_siteminder_log_connect - connect to database for site minder
 * 3. abcc_siteminder_log_disconnect - disconnect from database for site minder info
 * 4. verifyAuthentication - verify the type of authenication and redirect to appropriate "home" page
 * 5. logout - logout process: kill all sessions and cookies and redirect user to login page
 * 6. ldap_authenticate - LDAP authentication for user informationd/data
 * 7. ldap_group_authenticate - LDAP Group Authentication - get groups that user belong to
 * 8. ldap_login - login process: create sessions/cookies
 * 9. createSession - create user session information
 */
 

class UserAuthentication
{
	
	/**
	* @desc Turn on/off the debug feature
	* @var bool
	*/
	public $debug = true;
	
	/**
	* @desc
	* @var 
	*/	
	public $auth_type = 'ad'; // = 'ldap'; // to be defined :: ad,ldap or SM

	/**
	* @desc Define the url for logging out
	* @var mixedw
	*/	
	public $logout_url = '/apps/logout';

	/**
	* @desc Define the amount of failed login attempts before locking user out
	* @var INT
	**/
	private $login_attempt_allowed_failures = 3;

	
	/**
	* @desc Define the length of time to lock out user in minutes
	* @var INT
	**/
	private $login_attempt_lock_time = 30;


	/**
	* @desc Define the login url for Site Minder
	* @var mixed
	*/
	public $sm_login_url = '/admin/';
	/**
	* @desc Define the logout url for SiteMinder
	* @var mixed
	*/	
	public $sm_logout_page = '/logout/index.php';

	/**
	* @desc Define an array of fields or attributes to be ignored
	* @var array
	*/	
	public $access_exceptions = array();
	
	/**
	* @desc Define the "home" page after successfully logged in
	* @var mixed
	*/
	public $access_start_page; // = '/apps/site/start';
	
	/**
	* @desc Define the session identifier for application
	* @var mixed
	*/
	public $session_key = 'abcc_user';

	/**
	* @desc Define the cookie key to use in helping identifying cookies
	* @var mixed
	*/	
	public $cookie_key = 'ISPAUTH';
	public $cookie_secure_mode = false;
	public $httponly_mode = false;
	
	
	/**
	* @desc Define the redirect key for Information System Program
	* @var mixed
	*/	
	public $isp_redirect_key = 'ISP_REDIRECT';
	
	
	/** LDAP SETTINGS **/
	/**
	* @desc Define the LDAP host address
	* @var mixed
	*/	
	private $ldap_host = 'plaid.ncifcrf.gov';
	
	/**
	* @desc Define the LDAP port number
	* @var int
	*/	
	private $ldap_port = 389;
	
	/**
	* @desc Define the base DN for user (people) information
	* @var mixed
	*/		
	private $user_basedn = 'ou=People,dc=ncifcrf,dc=gov';
	
	/**
	* @desc Define the base DN for group information
	* @var mixed
	*/		
	private $group_basedn = 'ou=Group,dc=ncifcrf,dc=gov';
	
	/**
	* @desc Define the key to identify group
	* @var mixed
	*/		
	public $ldap_groups_key = 'groups_name';
	
	/**
	* @desc Define the url for the LDAP login
	* @var mixed
	*/		
	public $ldap_login_url = '/auth/';
	
	/**
	* @desc Define the url for the LDAP logout
	* @var mixed
	*/		
	public $ldap_logout_page = '/auth/'; //'/logout/index.php';

	/** AD SETTINGS **/	
	/**
	* @desc Define the active directory host address
	* @var mixed
	*/		
	private $ad_host = 'nihrdcnsfred.ncifcrf.gov'; //'ldapad.nih.gov';
	
	/**
	* @desc Define the active directory port number
	* @var int
	*/		
	private $ad_port = 389;
	
	/**
	* @desc Define the active directory base DN
	* @var array
	*/		
	private $ad_basedn = array
		(
			'OU=Users,OU=NCI,OU=NIH,OU=AD,DC=nih,DC=gov',
			'OU=Users,OU=NCI-Frederick,OU=NIH,OU=AD,DC=nih,DC=gov',
			'ou=users,ou=Fred,ou=nci,ou=nih,ou=ad,dc=nih,dc=gov',
			'OU=Users,OU=Fred,OU=NCI,OU=NIH,OU=AD,DC=nih,DC=gov',
			'OU=Users,OU=NIAID,OU=NIH,OU=AD,DC=nih,DC=gov',
			'OU=Users-dir,OU=NIAID,OU=NIH,OU=AD,DC=nih,DC=gov',
			'OU=Users,OU=NIA-ERP,OU=NIA,OU=NIH,OU=AD,DC=nih,DC=gov',
			'OU=Users,OU=NHGRI,OU=NIH,OU=AD,DC=nih,DC=gov',
			'OU=Users,OU=NHLBI,OU=NIH,OU=AD,DC=nih,DC=gov',
			'OU=Users,OU=NIAMS,OU=NIH,OU=AD,DC=nih,DC=gov',
			'OU=Users,OU=OD,OU=NIH,OU=AD,DC=nih,DC=gov',
			'OU=Users,OU=NCATS,OU=NIH,OU=AD,DC=nih,DC=gov',
			'OU=Users,OU=CC,OU=NIH,OU=AD,DC=nih,DC=gov',
			'OU=Users-dir,OU=NIAID,OU=NIH,OU=AD,DC=nih,DC=gov',
			'OU=Extended Leave,OU=NIH,OU=AD,DC=nih,DC=gov',
			'OU=Users,OU=Fred,OU=NCI,OU=NIH,OU=AD,DC=nih,DC=gov'
		);
	/*
	private $ad_basedn = array
		(
			'OU=Users,OU=NCI,OU=NIH,OU=AD,DC=nih,DC=gov',
			'OU=Users,OU=NCI-Frederick,OU=NIH,OU=AD,DC=nih,DC=gov',
			'ou=users,ou=Fred,ou=nci,ou=nih,ou=ad,dc=nih,dc=gov',
			'OU=Users,OU=NIAID,OU=NIH,OU=AD,DC=nih,DC=gov',
			'OU=Users-dir,OU=NIAID,OU=NIH,OU=AD,DC=nih,DC=gov',
			'OU=Users,OU=NIA-ERP,OU=NIA,OU=NIH,OU=AD,DC=nih,DC=gov',
			'OU=Users,OU=NHGRI,OU=NIH,OU=AD,DC=nih,DC=gov',
			'OU=Users,OU=NHLBI,OU=NIH,OU=AD,DC=nih,DC=gov',
			'OU=Users,OU=NIAMS,OU=NIH,OU=AD,DC=nih,DC=gov',
			'OU=Users,OU=OD,OU=NIH,OU=AD,DC=nih,DC=gov',
			'OU=Users,OU=NCATS,OU=NIH,OU=AD,DC=nih,DC=gov',
			'OU=Users,OU=CC,OU=NIH,OU=AD,DC=nih,DC=gov'
		);
	*/
	
	private $ad_groupdn = 'ou=Groups,ou=nci,ou=nih,ou=ad,dc=nih,dc=gov';
	public $ad_login_url = '/apps/authenticate/login';// = '/admin/';
	public $ad_logout_page = '/apps/authenticate/logout';// = '/admin/';
	public $ad_locked_out_page = '/admin/locked.php';
	public $ad_groups_key = 'ad_groups_name';
	
	
	
	/*
	*
	*
	**/
	private $adVars = array
		(
			//'objectclass',
			'cn',
			'sn',
			'c',
			'l',
			'st',
			'postalcode',
			'physicaldeliveryofficename',
			'telephonenumber',
			'facsimiletelephonenumber',
			'givenname',
			'initials',
			'distinguishedname',
			'instancetype',
			'whencreated',
			'whenchanged',
			'displayname',
			'usncreated',
			'info',
			'memberof',
			'usnchanged',
			'co',
			'department',
			'company',
			'homemta',
			'proxyaddresses',
			'homemdb',
			'streetaddress',
			'mdbusedefaults',
			'extensionattribute9',
			'extensionattribute10',
			'msexchassistantname',
			'mailnickname',
			'protocolsettings',
			'employeenumber',
			'employeetype',
			'personaltitle',
			'name',
			'objectguid',
			'useraccountcontrol',
			'badpwdcount',
			'codepage',
			'countrycode',
			'employeeid',
			'homedirectory',
			'badpasswordtime',
			'lastlogon',
			'scriptpath',
			'pwdlastset',
			'primarygroupid',
			'objectsid',
			'accountexpires',
			'logoncount',
			'samaccountname',
			'samaccounttype',
			'showinaddressbook',
			'legacyexchangedn',
			'userprincipalname',
			'lockouttime',
			'objectcategory',
			'dscorepropagationdata',
			'lastlogontimestamp',
			'textencodedoraddress',
			'mail',
			'roomnumber',
			'pssregistered',
			'adnihsac',
			'adnihadacctreq',
			'adnihadmailboxreq',
			'adnihbuilding',
			'msexchhomeservername',
			'msexchalobjectversion',
			'msexchmailboxsecuritydescriptor',
			'msexchuseraccountcontrol',
			'msexchmailboxguid',
			'msrtcsip_primaryuseraddress',
			'msrtcsip_userenabled',
			'msrtcsip_primaryhomeserver',
			'msrtcsip_federationenabled',
			'msrtcsip_internetaccessenabled',
			'msrtcsip_archivingenabled',
			'msrtcsip_optionflags',
			'msexchpoliciesexcluded',
			'msexchrecipientdisplaytype',
			'msexchuserculture',
			'msexchversion',
			'msexchrecipienttypedetails',
			'msexchmobilemailboxflags',
			'msexchomaadminwirelessenable',
			'adnihprimarysmtp',
			'count',
			'dn'
		);
	
	
	
	/**
	* @desc List of all LDAP variables
	* @var array
	*/		
	private $ldapVars = array
		(
			'uid',
			'cn',
			'sn',
			'krbprincipalname',
			'uidnumber',
			'gidnumber',
			'gecos',
			'givenname',
			'homedirectory',
			'objectclass',
			'host',
			'sambasid',
			'sambapasswordhistory',
			'sambapwdmustchange',
			'sambapwdcanchange',
			'sambalmpassword',
			'sambantpassword',
			'sambapwdlastset',
			'sambaacctflags',
			'mail',
			'shadowwarning',
			'shadowmax',
			'shadowinactive',
			'loginshell',
			'count',
			'dn'
		);

	/**
	* @desc List of all vars sent from SiteMinder
	* @var array
	* SiteMinderVars: 
	* $_SERVER vars are created by site mider when successful authentication is completed. These vars will be bound and passed through the user session.
	*/

	public $siteMinderVars = array
		(			
			'HTTP_SM_TRANSACTIONID',
			'HTTP_SM_SDOMAIN',
			'HTTP_SM_REALM',
			'HTTP_SM_REALMOID',
			'HTTP_SM_AUTHTYPE',
			'HTTP_SM_AUTHREASON',
			'HTTP_SM_AUTHDIROID',
			'HTTP_SM_AUTHDIRNAME',
			'HTTP_SM_AUTHDIRSERVER',
			'HTTP_SM_AUTHDIRNAMESPACE',
			'HTTP_SM_USER',
			'HTTP_SM_USERDN',
			'HTTP_SM_SERVERSESSIONID',
			'HTTP_SM_SERVERSESSIONSPEC',
			'HTTP_SM_TIMETOEXPIRE',
			'HTTP_SM_SERVERIDENTITYSPEC',
			'HTTP_SM_USER_AUTH_LEVEL',
			'HTTP_UI_DISPLAYNAME',
			'HTTP_UI_DEPARTMENT',
			'HTTP_UI_EMPLOYEEID',
			'HTTP_UI_MAILNICKNAME',
			'HTTP_UI_SAMACCOUNTNAME',
			'HTTP_UI_SN',
			'HTTP_UI_GIVENNAME',
			'HTTP_UI_MSEXCHHOMESERVERNAME',
			'HTTP_UI_DISTINGUISHEDNAME',
			'HTTP_UI_MEMBEROF',
			'HTTP_UI_NAME',
			'HTTP_UI_COMPANY',
			'HTTP_UI_DESCRIPTION',
			'HTTP_UI_TELEPHONENUMBER',
			'HTTP_UI_MAIL',
			'HTTP_UI_CN',
		);	

	/**
	 * @desc Contructor sets up
	 */
	public function __construct($ad_login_url = null,$ad_logout_page = null,$auth_type = false)
	{
		
		global $ad_login_url;
		global $ad_logout_page;
		global $session_key;
		
		$this->db = new QueryBuilder;
		$this->auth_type = ($auth_type) ? $auth_type : $this->auth_type;

			
			
			if(!empty($ad_login_url))
			{
				$this->ad_login_url = $ad_login_url;
			}
		  
	
			if(!empty($ad_logout_page))
			{
				$this->ad_logout_page = $ad_logout_page;
			}
                    
	}



	/**
	* @desc Login process only passes site minder data to db and creates necessary sessions
	*
	**/	
	/*
	public function siteMinder_login()
	{
		
		if($this->auth_type != 'SM')
		{
			header("Location: ".$this->ldap_login_url);
			exit;	
		}
		
		if($this->debug)
		{
			dump($_SERVER);
		}
		
		
		// create session				
		foreach($this->siteMinderVars as $field) $_SESSION[$this->session_key][$field] = $_SERVER[$field];
		
		// log user access
		$access_log['access_log'] = $_SESSION[$this->session_key];
		
		$meta_data = array();
		foreach($_SERVER as $key => $value) $meta_data[] = strtoupper($key).': '.$value;
		
		$access_log['access_log']['META_DATA'] = implode("\n",$meta_data);
		$access_log['access_log']['HOST'] = $_SERVER['HTTP_HOST'];
				
		$this->abcc_siteminder_log_connect();
		$this->db->query('access_log',$access_log);
		$this->abcc_siteminder_log_disconnect();
		
		// write session cookie
		setcookie($this->cookie_key,$_SESSION[$this->session_key]['HTTP_SM_TRANSACTIONID'],time()+60*30,"/",".ncifcrf.gov");
		

		// create user session
		$this->createSession();

		// redirect
		$this->access_start_page = ($_COOKIE[$this->isp_redirect_key]) ? $_COOKIE[$this->isp_redirect_key] : $this->access_start_page;
		

		header("Location: ".$this->access_start_page);
		exit;		
	} //end siteMinder_login method
*/



	
	/**
	 * @desc This method connect to database for the site minder log
	 * 
	 * @use /webcommon/private/classes/database_connect.class.php
	 */

	public function db_disconnect()
	{
		$this->db_connect->db_close();	
	}
	
	// alias
	public function isp_log_disconnect()
	{
		$this->db_disconnect();	
	}


	/**
	 * @desc This method close the connection to the database for the site minder log
	 */
	private function abcc_siteminder_log_disconnect()
	{
		$this->db_connect->db_close();	
	}



	/**
	 * @desc This method verify the type of authentication.  If Site Minder, set cookies for the url and redirect to the site minder login.<br />
	 * If LDAP, redirect to LDAP login.
	 */
	public function verifyAuthentication()
	{			

		// remember this url & set a cookie
		if(!$_SESSION[$this->session_key]['cn'])
		{
			setrawcookie($this->isp_redirect_key,$_SERVER['REQUEST_URI'],time()+3600,"/");
			$_SESSION['error'] = 'Authorization Required';
			
			// create session for xml_header_message
			$_SESSION['header_message'] = '<h4 class="error">Authorization Required</h4>';
		}
	
		

		if($this->auth_type == 'ldap') // end SM authentication
		{
			
			if(!$_SESSION[$this->session_key])
			{
				header("location: ".$this->ldap_login_url);
				exit;
			}
		}
		elseif($this->auth_type == 'ad')
		{
			if(!$_SESSION[$this->session_key]['cn'])
			{				
				unset($_SESSION);
				session_write_close();
				header("location: ".$this->ad_login_url);
				exit;
			}
		}
		else
		{
			die("Authentication type not defined: ".$this->auth_type);
		}
	}
	
	

	/**
	* Logout
	* @desc Kills all sessions and cookies. Redirects user to login form.
	* @return NULL Redirects to login page
	*/
	public function logout($redirect_login = false)
	{		
		
		setcookie($this->cookie_key,NULL,time()-3600,'/');
		
		if($redirect_login)
		{
			unset($_SESSION[$this->isp_redirect_key]);
			setrawcookie($this->isp_redirect_key,$redirect_login,time()+3600,"/");
		}
		else
		{
			$_SESSION[$this->isp_redirect_key] = $redirect_login;
			setrawcookie($this->isp_redirect_key,"",time()-3600,"/");
		}
		
		unset($_SESSION[$this->session_key]);
		session_write_close();
		
		$this->logout = ($this->auth_type == 'ldap') ? $this->ldap_logout_page : $this->sm_logout_page;
		$this->logout = ($this->auth_type == 'ad') ? $this->ad_logout_page : $this->logout;
				
		header("Location: ".$this->logout);
		exit;

	}


	/** 
	 * @desc LDAP authentication for user information/data
	 * 
	 * @param mixed $username The username that was used to login
	 * @param mixed $password The password that was used to login
	 * @return array Return ldap information regarding user
	 */
	public function ldap_authenticate($username,$password)
	{
		
		$result = false;
				
		if ( !empty($username) && !empty($password) )
		{
			$connect = @ldap_connect($this->ldap_host,$this->ldap_port);				
			
			$result_search = @ldap_search($connect,$this->user_basedn,'uid='.$username);

print_r($result_search);
		
			if ($result_search)
			{
				$search = @ldap_get_entries($connect,$result_search);
				
				$dn = $search[0]['dn'];
						
				if($dn)
				{
				   @ldap_set_option($connect, LDAP_OPT_PROTOCOL_VERSION, 3);
				   
				   $ldap_bind = @ldap_bind($connect,$dn,$password);
				   
					if($ldap_bind)
					{
						$result = $search[0];
					}
					else
					{
						$result = false;
					}
				}
			}
		}

		return $result;
	} //end ldap_authenticate method


	/**
	 * @desc LDAP Group Authentication - get groups that user belong to
	 * 
	 * @param mixed $username The username that was used to login
	 * @return array Return the list of groups that user belong to
	 */	
	public function ldap_group_authenticate($username)
	{
		// return false;
		
		$group_result = false;
		
		if (!empty($username))
		{
			//connect to ldap server
			$connect = @ldap_connect($this->ldap_host,$this->ldap_port);
			
			//set ldap option
			@ldap_set_option($connect, LDAP_OPT_PROTOCOL_VERSION, 3);
			@ldap_set_option($connect, LDAP_OPT_REFERRALS, 0);
			
			//search ldap for all groups by user
			$group_search=ldap_search($connect,"ou=Group,dc=ncifcrf,dc=gov","memberUid=".$username);
			
			if($group_search)
			{
				//get all group entries
				$group_entries = ldap_get_entries($connect, $group_search);
				
				//get total number of groups
				$total_groups = ldap_count_entries($connect,$group_search);
				
				$group_result = array();
				
				for($i=0; $i<$total_groups; $i++)
				{
					//isolate the group from the rest
					$group_parts = explode(",",$group_entries[$i]['dn']);
					$cn_parts = explode("=",$group_parts[0]);
					
					$group_result[] = $cn_parts[1];
				}
			}			
		}
		return $group_result;		
	}	//end ldap_group_authenticate method
	

	/**
	 * @desc Login Process<br />
	 * Create necessary sessions/cookies<br />
	 * Connect to site minder database table to insert the access log, then close.
	 */
	public function ldap_login($authenticated_data,$groups_data)
	{
		
		// create session				
		$this_user_data = false;
		
		// clean data...grab the 1st entry for each
		foreach($this->ldapVars as $field)
		{
			if( !is_array($authenticated_data[$field]) )
			{
				$this_user_data[$field] = $authenticated_data[$field];
			}
			else
			{
				$this_user_data[$field] = $authenticated_data[$field][0];
			}		
		}
	
		$_SESSION[$this->session_key] = $this_user_data;

		//dump($_SERVER['REMOTE_ADDR'],0);

		// log user access
		$access_log['ldap_access_log'] = $_SESSION[$this->session_key];
		
		$meta_data = array();
		foreach($_SERVER as $key => $value) $meta_data[] = strtoupper($key).': '.$value;
		
		$access_log['ldap_access_log']['meta_data'] = implode("\n",$meta_data);
		$access_log['ldap_access_log']['domain'] = $_SERVER['HTTP_HOST'];
		
		$this->abcc_siteminder_log_connect();
		$this->db->query('ldap_access_log',$access_log);
		$this->abcc_siteminder_log_disconnect();
		
		$_SESSION[$this->session_key][$this->ldap_groups_key] = $groups_data;
		$_SESSION[$this->session_key]['email'] = $_SESSION[$this->session_key]['mail'];	
		$_SESSION[$this->session_key]['username'] = isset($_SESSION[$this->session_key]['uid']) ? $_SESSION[$this->session_key]['uid'] : false;
		
				
		// write session
		session_write_close();

		$this->access_start_page = ($_SESSION[$this->isp_redirect_key]) ? $_SESSION[$this->isp_redirect_key] : $this->access_start_page;

		header("Location: ".$this->access_start_page);
		exit;
		
	} //end ldap_login method



	/** @desc  AD LOGIN **/
	public function ad_authenticate($username,$password)
	{
		//$this->verifyLockout($username);
		
		$result = false;
		
		
		
		if( !empty($username) && !empty($password) )
		{
			
			$ad_connect = ldap_connect($this->ad_host,$this->ad_port) or die("Unable to connect to server.");		
			ldap_set_option($ad_connect, LDAP_OPT_PROTOCOL_VERSION, 3);
			$ldap_bind = @ldap_bind($ad_connect,$username.'@nih.gov',$password);			
			
			$search = array();			
			foreach($this->ad_basedn as $base_key => $ad_basedn)
			{			
				$result_search = @ldap_search($ad_connect,$ad_basedn,"sAMAccountName=".$username);
				$search = @ldap_get_entries($ad_connect,$result_search);
				
				// did we get results?
				if( !empty($search['count']))
				{
					$ad_basedn = $this->ad_basedn[$base_key];
					break;
				}
			}
			
		
		
			
			if($search)
			{
				//$search = ldap_get_entries($ad_connect,$result_search);
				$dn = $search[0]['dn'];

				$search[0]['memberof'] = is_array($search[0]['memberof']) ? $search[0]['memberof'] : array();
				
				$this_member_group = array();				
				foreach($search[0]['memberof'] as $member_key => $member_value)
				{
					$this_group = explode(",",$member_value);
					if(!strpos($this_group[0],'VPN'))
					{
						$this_member_group[] = $this_group[0];
					}
				}
				
				$ad_groups = array();
				foreach($this_member_group as $group_key => $group_value)
				{
					$this_ad_member_group = explode("CN=",$group_value);
					if( isset($this_ad_member_group[1]) ) $ad_groups[] = $this_ad_member_group[1];
				}
				
				// get groups
				$group_search = @ldap_search($ad_connect,$this->ad_groupdn,"(&(objectClass=group)(member=".$dn."))",array("cn"));
				$group_result = ldap_get_entries($ad_connect,$group_search);
				$total_groups = ldap_count_entries($ad_connect,$group_search);

				
				
				for($i=0; $i<$total_groups; $i++)
				{
					$group_parts = explode(",",$group_result[$i]['cn'][0]);
					$ad_groups[] = $group_parts[0];
				}
				
				sort($ad_groups);
				$ad_groups = array_unique($ad_groups);	
				
				$this_users_data = $this->extractAdLdapData($search[0]);
						
				// kill crappy & unwanted data
				unset
					(
					 	$this_users_data['objectguid'],
						$this_users_data['objectsid'],
						$this_users_data['msexchmailboxsecuritydescriptor'],
						$this_users_data['msexchmailboxguid']
					);
				
				foreach($this_users_data as $field => $value)
				{
					if(!in_array($field,$this->adVars) )
					{
						unset($this_users_data[$field]);
					}				
				}
				
				// restack data for logs insert
				// create session
				$_SESSION[$this->session_key] = false;
				foreach($this->adVars as $data_field)
				{
				 	if(isset($this_users_data[$data_field])) $_SESSION[$this->session_key][$data_field] = $this_users_data[$data_field];
				}
				
				// check for user data
				$this->isp_log_connect();
				$sql = array("key" => "cn","value" => $_SESSION[$this->session_key]['cn']);
				$restrict = array("users_data" => array("users_data_id"));
				$this->db->restrict($restrict);
				$user = $this->db->select("users_data",$sql);
								
				$this_user['users_data'] = $_SESSION[$this->session_key];
				
				$_SESSION['create_ad_local_account'] = false;
				
				if($user) // update the user record
				{
					$this->db->set("users_data", array("users_data_id" => $user['users_data_id']));
				}
				else // look in ad_users_library for a record and get the ad_users_library_id
				{
					
					$sql = array("key" => "cn","value" => $_SESSION[$this->session_key]['cn']);
					$restrict = array("ad_users_library" => array("ad_users_library_id"));
					$this->db->restrict($restrict);
					$ad_library_user = $this->db->select("ad_users_library",$sql);
					
					if($ad_library_user) $this_user['users_data']['ad_users_library_id'] = $ad_library_user['ad_users_library_id'];

				}
				
				$user = $this->db->query("users_data",$this_user);
				
				// set users_data_id
				$_SESSION[$this->session_key]['users_data_id'] = $user['users_data_id'];
				$_SESSION[$this->session_key]['ad_users_library_id'] = $user['ad_users_library_id'];
				// set groups
				$_SESSION[$this->session_key][$this->ad_groups_key] = $ad_groups;
			
				$_SESSION[$this->session_key]['email'] = $_SESSION[$this->session_key]['mail'];	
				$_SESSION[$this->session_key]['username'] = $_SESSION[$this->session_key]['cn'];

				// set the cookie
				
				$cookie_data = array();
				$cookie_data[] = $user['users_data_id'];				
				$cookie_data[] = $user['cn'];
				$cookie_data[] = session_id();
				$cookie_data = implode('|',$cookie_data);
				
				//setcookie($this->cookie_key,$cookie_data,time()+3600,'/');
				setcookie($this->cookie_key,$cookie_data,0,'/',$_SERVER['SERVER_NAME'],$this->cookie_secure_mode,$this->httponly_mode);
				
				// add log
				$log['users_access_logs']['users_data_id'] = $user['users_data_id'];
				$log['users_access_logs']['metadata'] = json_encode($_SERVER);
				$log['users_access_logs']['access_url'] = $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
				

				$this->db->query("users_access_logs",$log);
				//dump($log,1,'129.43.2.199');

				if($search[0])
				{
					$attempts['users_access_attempts']['users_data_id'] = $user['users_data_id'];
					$attempts['users_access_attempts']['ip_address'] = $_SERVER['REMOTE_ADDR'];
					$attempts['users_access_attempts']['cn'] = $username;
					$attempts['users_access_attempts']['result'] = 'success';
					$this->db->query("users_access_attempts",$attempts);
					
					$result = true;
				}
				else
				{
					$result = false;
				}
			}
			else
			{
				// log the failure
				// does the cn exist?
				$this->isp_log_connect();
				$sql = array("key" => "cn","value" => $username);
				$restrict = array("users_data" => array("users_data_id"));
				$this->db->restrict($restrict);
				$user = $this->db->select("users_data",$sql);
				
				$fail['users_access_attempts']['users_data_id'] = $user['users_data_id'];
				$fail['users_access_attempts']['ip_address'] = $_SERVER['REMOTE_ADDR'];
				$fail['users_access_attempts']['cn'] = $username;
				$fail['users_access_attempts']['result'] = 'fail';
				
				$this->db->query("users_access_attempts",$fail);
			}
		}

		return $result;
	
	}




	/**
	 * @desc This method creates user session
	 */
	private function createSession()
	{
		
		return;
		
		$_SESSION[$this->session_key] = array();		
		
		//dump($this->auth_type);
		
		$sql = array
			(
				"key" => "HTTP_SM_TRANSACTIONID",
				"value" => $_COOKIE[$this->cookie_key]
			);
		$this->abcc_siteminder_log_connect();
		
		$restrict = array
			(
				"access_log" => array
					(
						"HTTP_UI_MEMBEROF",
						"HTTP_UI_MAIL",
						"HTTP_SM_TRANSACTIONID",
						"HTTP_UI_DISPLAYNAME"
					)				
			);
			
			
		$this->db->restrict($restrict);
		$session = $this->db->select('access_log',$sql);
		
		
		$this->abcc_siteminder_log_disconnect();
		
		$_SESSION[$this->session_key] = $session; 
		
		$_SESSION[$this->session_key]['email'] = $_SESSION[$this->session_key]['HTTP_UI_MAIL'];
		$_SESSION[$this->session_key]['mail'] = $_SESSION[$this->session_key]['HTTP_UI_MAIL'];
		$_SESSION[$this->session_key]['username'] = explode('@',$_SESSION[$this->session_key]['HTTP_UI_MAIL']);
		$_SESSION[$this->session_key]['username'] = $_SESSION[$this->session_key]['username'][0];
	
		
		// extract groups
		$member_of = explode(",",$_SESSION[$this->session_key]['HTTP_UI_MEMBEROF']);
		foreach($member_of as $data)
		{
			if( strpos($data,"^CN=") )
			{
				$this_group = explode("=",$data);
				$_SESSION[$this->session_key]['AD_GROUPS'][] = $this_group[2];
			}
		}
	} //end createSession method
	
	
	private function extractAdLdapData($data)
	{
		$field = array();
		foreach($data as $field => $values)
		{
			$db_fields = !is_numeric($field) ? $field : false;
			if($db_fields) $field_list[str_replace('-','_',$db_fields)] = chop($values[0]);
		}

		return $field_list;	
	}
	
	
	private function verifyLockout($username)
	{
		
		
		$sql = array
			(
				"key" => "cn",
				"value" => $username,
				"AND" => array
					(
						"result",
						"=",
						"fail"
					)
			);
			
		$this->isp_log_connect();
		$restrict = array("users_access_attempts" => array("users_access_attempts_id","isp_log_date"));
		$this->db->restrict($restrict);
		$sort = array("isp_log_date","DESC");
		
		$attempts = $this->db->select("users_access_attempts",$sql,true,$sort,$limit);
			
		if( count($attempts) >= $this->login_attempt_allowed_failures)
		{
			$last_attempt = $attempts[0]['isp_log_date'];
			
			// was the last attempt less than 30 minutes ago? if so lock them out.
			//$buffer_time = date(time(),mktime(date("H"), date("i")-30,date("s"), date("m")  , date("d"), date("Y")));
			$buffer_time = (time()-30);
			$result_in_seconds = $buffer_time - strtotime($attempts[0]['isp_log_date']);
			$minutes_since_last_attempt = floor($result_in_seconds/60);
			
			if($minutes_since_last_attempt <= $this->login_attempt_lock_time)
			{
				unset($_SESSION[$this->session_key]);
				Globals::redirect($this->ad_locked_out_page,"error","Account access is denied, <a href=\"{$this->ad_login_url}\">try again</a> in 30 minutes");
			}
		}
	}
	
	
	
} //end class
?>
