<?php  namespace LaravelAcl\Authentication\Controllers;

/**
 * Class UserController
 *
 * @author jacopo beschi jacopo@jacopobeschi.com
 */
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use LaravelAcl\Authentication\Exceptions\PermissionException;
use LaravelAcl\Authentication\Exceptions\ProfileNotFoundException;
use LaravelAcl\Authentication\Helpers\DbHelper;
use LaravelAcl\Authentication\Models\UserProfile;
use LaravelAcl\Authentication\Presenters\UserPresenter;
use LaravelAcl\Authentication\Services\UserProfileService;
use LaravelAcl\Authentication\Validators\UserProfileAvatarValidator;
use LaravelAcl\Library\Exceptions\NotFoundException;
use LaravelAcl\Authentication\Helpers\FormHelper;
use LaravelAcl\Authentication\Exceptions\UserNotFoundException;
use LaravelAcl\Authentication\Validators\UserValidator;
use LaravelAcl\Library\Exceptions\JacopoExceptionsInterface;
use LaravelAcl\Authentication\Validators\UserProfileValidator;
use View, Redirect, App, Config, DB, Log;
use LaravelAcl\Authentication\Interfaces\AuthenticateInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use LaravelAcl\Library\Form\FormModel;
use App\Models\User;

class UserController extends Controller {
    /**
     * @var \LaravelAcl\Authentication\Repository\SentryUserRepository
     */
    protected $user_repository;
    protected $user_validator;
    /**
     * @var \LaravelAcl\Authentication\Helpers\FormHelper
     */
    protected $form_helper;
    protected $profile_repository;
    protected $profile_validator;
    /**
     * @var use LaravelAcl\Authentication\Interfaces\AuthenticateInterface;
     */
    protected $auth;
    protected $register_service;
    protected $custom_profile_repository;

    public function __construct(UserValidator $v, FormHelper $fh, UserProfileValidator $vp, AuthenticateInterface $auth)
    {
        $this->user_repository = App::make('user_repository');
        $this->perm_repository = App::make('permission_repository');
        $this->user_validator = $v;
        $this->f = new FormModel($this->user_validator, $this->user_repository);
        $this->form_helper = $fh;
        $this->profile_validator = $vp;
        $this->profile_repository = App::make('profile_repository');
        $this->auth = $auth;
        $this->register_service = App::make('register_service');
        $this->custom_profile_repository = App::make('custom_profile_repository');
    }

    public function getList(Request $request)
    {        

        $users = $this->user_repository->all($request->except(['page']));

        $perms = $this->perm_repository->all();

        $perm_desc = array();

        foreach($perms as $perm) {
            $perm_desc[$perm->permission] = $perm->description;
        }

        foreach($users as $user) {
            $perms = array_keys((array)json_decode($user->permissions));
            $perm_label = array();
            foreach ($perms as $perm) {
                if (array_key_exists($perm, $perm_desc))
                    $perm_label[] = "<span class='badge'>".$perm_desc[$perm]."</span>";
            }
            $user->permissions = implode("", $perm_label);
        }        

        return View::make('laravel-authentication-acl::admin.user.list')->with(["users" => $users]);
    }

    public function editUser(Request $request)
    {
        $projects = array();
        try
        {
            $user = $this->user_repository->find($request->get('id'));
            $logged_user = User::getCurrentUser();            
            if (User::isSuperAdmin())
                $rows = DB::select("select * from projects p where not exists(select * from users_groups u where p.id=u.group_id and u.user_id=$user->id) order by name");
            else
                $rows = DB::select("select * from projects p where exists(select * from project_groups g, project_group_users m where p.project_group=g.project_group and g.project_group=m.project_group and m.user_id=$logged_user->id and m.is_manager='Y') and not exists(select * from users_groups u where p.id=u.group_id and u.user_id=$user->id) order by name");
            $project_group_users = User::getProjectGroups();
            $project_group_user_rows = DB::select("select * from project_group_users where user_id=$user->id and is_manager='N'");
            $project_group_user = array();
            foreach ($project_group_user_rows as $project_group_user_row)
                $project_group_user[$project_group_user_row->project_group] = "";
            foreach ($project_group_users as $project_group) {
                if (array_key_exists($project_group->project_group, $project_group_user))
                    $project_group->checked = "checked";
                else
                    $project_group->checked = "";
            }
            $project_group_managers = User::getProjectGroups();
            $project_group_manager_rows = DB::select("select * from project_group_users where user_id=$user->id and is_manager='Y'");
            $project_group_manager = array();
            foreach ($project_group_manager_rows as $project_group_manager_row)
                $project_group_manager[$project_group_manager_row->project_group] = "";
            foreach ($project_group_managers as $project_group) {
                if (array_key_exists($project_group->project_group, $project_group_manager))
                    $project_group->checked = "checked";
                else
                    $project_group->checked = "";
            }
            foreach ($rows as $row)
                $projects[$row->id] = $row->name;
            $mps = User::getManagedProjects();
            $managed_projects = array();
            foreach ($mps as $mp)
                $managed_projects[$mp->name] = '';

        } catch(JacopoExceptionsInterface $e)
        {
            $user = new User;
        }
        $presenter = new UserPresenter($user);        

        return View::make('laravel-authentication-acl::admin.user.edit')->with(["user" => $user, "presenter" => $presenter, "projects" => $projects, 'project_group_users' => $project_group_users, 'project_group_managers' => $project_group_managers, 'managed_projects' => $managed_projects]);
    }

    public function postEditUser()
    {
        $id = Request::get('id');

        DbHelper::startTransaction();
        try
        {
            $user = $this->f->process(Request::all());
            $this->profile_repository->attachEmptyProfile($user);
        } catch(JacopoExceptionsInterface $e)
        {
            DbHelper::rollback();
            $errors = $this->f->getErrors();
            // passing the id incase fails editing an already existing item
            return Redirect::route("users.edit", $id ? ["id" => $id] : [])->withInput()->withErrors($errors);
        }

        DbHelper::commit();

        return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@editUser', ["id" => $user->id])
                       ->withMessage(Config::get('acl_messages.flash.success.user_edit_success'));
    }

    public function deleteUser(Request $request)
    {
        try
        {
            $this->f->delete($request->all());
        } catch(JacopoExceptionsInterface $e)
        {
            $errors = $this->f->getErrors();
            return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@getList')->withErrors($errors);
        }
        return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@getList')
                       ->withMessage(Config::get('acl_messages.flash.success.user_delete_success'));
    }
    public function sendEmailToUser($id, $user, $fn, $ln) {
        Log::info('in sendEmailToAdmin');
        $name = "$user ($fn $ln)";
        $users = \LaravelAcl\Authentication\Models\User::all();
        $admin_emails = array();
        foreach ($users as $user) {
            foreach ($user->permissions as $permission => $permission_id) {
                if ($permission == "_superadmin")
                //if ($permission == "_tester")
                    $admin_emails[] = $user->email_address;
            }                    
        }

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

    public function addGroup(Request $request, $sendEmail=false)
    {   
        $user_id = $request->get('id');
        $group_id = $request->get('group_id');

        try
        {
            $this->user_repository->addGroup($user_id, $group_id);
            #DB::statement("BEGIN Dbms_Mview.Refresh('USER_PROJECTS','C');END;");
            $this->refreshUserProject();
            $email = DB::select("select email_address from users where id=$user_id");
            if (!preg_match("/test.com/",$email[0]->email_address)){//Do not send to the reviewers
                $project = DB::select("select name from projects where id=$group_id");
                $headers = 'From: oncogenomics@mail.nih.gov'. "\r\n" .
    'BCC: chouh@nih.gov';
                $email =$email[0]->email_address ;
                mail($email,"Project Account Approved", "You have been granted access to the " . $project[0]->name . " on Clinomics Genomics Portal.  Please log in https://clinomics.ncifcrf.gov to view data.",$headers);

            }
        } catch(JacopoExceptionsInterface $e)
        {
            return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@editUser', ["id" => $user_id])
                           ->withErrors(new MessageBag(["name" => Config::get('acl_messages.flash.error.user_group_not_found')]));
        }
        return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@editUser', ["id" => $user_id])
                       ->withMessage(Config::get('acl_messages.flash.success.user_group_add_success'));
    }

    public function deleteGroup(Request $request)
    {
        $user_id = $request->get('id');
        $group_id = $request->get('group_id');

        try
        {
            $this->user_repository->removeGroup($user_id, $group_id);
            #DB::statement("BEGIN Dbms_Mview.Refresh('USER_PROJECTS','C');END;");
            $this->refreshUserProject();
        } catch(JacopoExceptionsInterface $e)
        {
            return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@editUser', ["id" => $user_id])
                           ->withErrors(new MessageBag(["name" => Config::get('acl_messages.flash.error.user_group_not_found')]));
        }
        return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@editUser', ["id" => $user_id])
                       ->withMessage(Config::get('acl_messages.flash.success.user_group_delete_success'));
    }

    public function editPermission(Request $request)
    {
        // prepare input
        $input = $request->all();        
        $operation = $request->get('operation');
        //0: delete, 1: add, 2: edit
        $perm = $request->get('permissions');

        if ($operation < 2)
            $this->form_helper->prepareSentryPermissionInput($input, $operation);        
        
        $id = $request->get('id');
        
        try
        {
            if ($operation == 0)
               DB::delete("delete from users_permissions where user_id=? and perm=?", [$id, $perm]);
            if ($operation == 1)
                DB::insert("insert into users_permissions values(?,?)", [$id, $perm]);
            if ($perm == "_projectmanager" || $perm == "_project-group-user") {
                $project_groups = User::getAllProjectGroups();
                $is_manager = "N";
                $prefix = "user";
                if ($perm == "_projectmanager") {
                    $is_manager = "Y";
                    $prefix = "manager";
                }
                DB::delete("delete from project_group_users where user_id=? and is_manager=?", [$id, $is_manager]);
                foreach($project_groups as $project_group) {
                    Log::info("looking for ".$prefix."_".$project_group->project_group); 
                    if ($request->get($prefix."_".$project_group->project_group) != null) {
                        Log::info("found ".$prefix."_".$project_group->project_group); 
                        DB::insert("insert into project_group_users values(?, ?, ?)", [$id, $project_group->project_group, $is_manager]);
                    }
                }

            }
            if ($operation < 2)
                $obj = $this->user_repository->update($id, $input);
            #DB::statement("BEGIN Dbms_Mview.Refresh('USER_PROJECTS','C');END;");
            $this->refreshUserProject();
        } catch(JacopoExceptionsInterface $e)
        {
            return Redirect::route("users.edit")->withInput()
                           ->withErrors(new MessageBag(["permissions" => Config::get('acl_messages.flash.error.user_permission_not_found')]));
        }
        return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@editUser', ["id" => $id])
                       ->withMessage(Config::get('acl_messages.flash.success.user_permission_add_success'));
    }

    public function refreshUserProject() {
        DB::statement('truncate table user_projects');
        DB::statement("insert into user_projects select distinct * from ((select distinct p.id as project_id, p.name as project_name, g.user_id,p.ispublic from project_group_users g, projects p where p.project_group=g.project_group) union (select distinct p.id as project_id, p.name as project_name, u.id as user_id,p.ispublic from users u, users_groups g, projects p where (u.id=g.user_id and g.group_id=p.id) or p.ispublic=1) union (select  p.id as project_id, p.name as project_name, u.user_id as user_id,p.ispublic from users_permissions u, projects p where u.perm='_superadmin'))");
    }

    public function editProfile()
    {
        $user_id = Request::get('user_id');

        try
        {
            $user_profile = $this->profile_repository->getFromUserId($user_id);
        } catch(UserNotFoundException $e)
        {
            return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@getList')
                           ->withErrors(new MessageBag(['model' => Config::get('acl_messages.flash.error.user_user_not_found')]));
        } catch(ProfileNotFoundException $e)
        {
            $user_profile = new UserProfile(["user_id" => $user_id]);
        }
        $custom_profile_repo = App::make('custom_profile_repository', $user_profile->id);

        return View::make('laravel-authentication-acl::admin.user.profile')->with([
                                                                                          'user_profile'   => $user_profile,
                                                                                          "custom_profile" => $custom_profile_repo
                                                                                  ]);
    }

    public function postEditProfile()
    {
        $input = Request::all();
        $service = new UserProfileService($this->profile_validator);

        try
        {
            $service->processForm($input);
        } catch(JacopoExceptionsInterface $e)
        {
            $errors = $service->getErrors();
            return Redirect::back()
                           ->withInput()
                           ->withErrors($errors);
        }
        return Redirect::back()
                       ->withInput()
                       ->withMessage(Config::get('acl_messages.flash.success.user_profile_edit_success'));
    }

    public function editOwnProfile()
    {
        $logged_user = $this->auth->getLoggedUser();

        $custom_profile_repo = App::make('custom_profile_repository', $logged_user->user_profile()->first()->id);

        return View::make('laravel-authentication-acl::admin.user.self-profile')
                   ->with([
                                  "user_profile"   => $logged_user->user_profile()
                                                                  ->first(),
                                  "custom_profile" => $custom_profile_repo
                          ]);
    }

    public function signup()
    {
        $sql= " select tokenid from reviewer_tokens where exp_date > (select to_date(to_char(SYSDATE,'DD-MON-YY'),'DD-MON-YY') from dual)";
        $rows = DB::select($sql);
        $validTokens = array();
        foreach($rows as $key=>$value){
            array_push($validTokens,$rows[$key]->tokenid);
        }
        
        $enable_captcha = Config::get('laravel-authentication-acl::captcha_signup');
        if($enable_captcha)
        {
            $captcha = App::make('captcha_validator');
            return View::make('laravel-authentication-acl::client.auth.signup')->with('captcha', $captcha);
        }
        return View::make('laravel-authentication-acl::client.auth.signup',['validTokens'=>$validTokens]);
    }

    public function postSignup()
    {
        // $service = App::make('register_service');
        // try
        // {
        //     $service->register(Request::all());
        //     dd('here');
        // } catch(JacopoExceptionsInterface $e)
        // {   
        //     dd("here",$service);
        //     return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@signup')->withErrors($service->getErrors())->withInput();
        // }
        return Redirect::action('\LaravelAcl\Authentication\Controllers\AuthController@postTokenLogin')->withInput();
    }

    public function signupSuccess()
    {
        $email_confirmation_enabled = Config::get('laravel-authentication-acl::email_confirmation');
        return $email_confirmation_enabled ? View::make('laravel-authentication-acl::client.auth.signup-email-confirmation') : View::make('laravel-authentication-acl::client.auth.signup-success');
    }

    public function emailConfirmation()
    {
        $email = Request::get('email');
        $token = Request::get('token');

        try
        {
            $this->register_service->checkUserActivationCode($email, $token);
        } catch(JacopoExceptionsInterface $e)
        {
            return View::make('laravel-authentication-acl::client.auth.email-confirmation')->withErrors($this->register_service->getErrors());
        }
        return View::make('laravel-authentication-acl::client.auth.email-confirmation');
    }

    public function addCustomFieldType()
    {
        $description = Request::get('description');
        $user_id = Request::get('user_id');

        try
        {
            $this->custom_profile_repository->addNewType($description);
        } catch(PermissionException $e)
        {
            return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@postEditProfile', ["user_id" => $user_id])
                           ->withErrors(new MessageBag(["model" => $e->getMessage()]));
        }

        return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@postEditProfile', ["user_id" => $user_id])
                       ->with('message', Config::get('acl_messages.flash.success.custom_field_added'));
    }

    public function deleteCustomFieldType()
    {
        $id = Request::get('id');
        $user_id = Request::get('user_id');

        try
        {
            $this->custom_profile_repository->deleteType($id);
        } catch(ModelNotFoundException $e)
        {
            return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@postEditProfile', ["user_id" => $user_id])
                           ->withErrors(new MessageBag(["model" => Config::get('acl_messages.flash.error.custom_field_not_found')]));
        } catch(PermissionException $e)
        {
            return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@postEditProfile', ["user_id" => $user_id])
                           ->withErrors(new MessageBag(["model" => $e->getMessage()]));
        }

        return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@postEditProfile', ["user_id" => $user_id])
                       ->with('message', Config::get('acl_messages.flash.success.custom_field_removed'));
    }

    public function changeAvatar()
    {
        $user_id = Request::get('user_id');
        $profile_id = Request::get('user_profile_id');

        // validate input
        $validator = new UserProfileAvatarValidator();
        if(!$validator->validate(Request::all()))
        {
            return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@editProfile', ['user_id' => $user_id])
                           ->withInput()->withErrors($validator->getErrors());
        }

        // change picture
        try
        {
            $this->profile_repository->updateAvatar($profile_id);
        } catch(NotFoundException $e)
        {
            return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@editProfile', ['user_id' => $user_id])->withInput()
                           ->withErrors(new MessageBag(['avatar' => Config::get('acl_messages.flash.error.')]));
        }

        return Redirect::action('\LaravelAcl\Authentication\Controllers\UserController@editProfile', ['user_id' => $user_id])
                       ->withMessage(Config::get('acl_messages.flash.success.avatar_edit_success'));
    }

    public function refreshCaptcha()
    {
        return View::make('laravel-authentication-acl::client.auth.captcha-image')
                   ->with(['captcha' => App::make('captcha_validator')]);
    }
} 
