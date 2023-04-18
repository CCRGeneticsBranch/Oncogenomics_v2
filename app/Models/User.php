<?php

namespace App\Models;
use LaravelAcl\Authentication\Models\User as BaseUser;
use Log,DB;

class User extends BaseUser{	

	static public function getCurrentUser() {
        $auth = app()->make('LaravelAcl\Authentication\Interfaces\AuthenticateInterface');        
        $logged_user = $auth->getLoggedUser();
        return $logged_user;
    }

    static public function accessAll() {
        // return true;##hv added for testing
        //$logged_user = User::getCurrentUser();
        //if ($logged_user != null)
        //    return $logged_user->hasPermission("_khanlab");
        return User::isSuperAdmin();
    }
    
    static public function isSuperAdmin() {
        $logged_user = User::getCurrentUser();
        if ($logged_user != null)
            return $logged_user->hasPermission("_superadmin");
        return false;       
    }

    static public function isPipelineExecutor() {
        $logged_user = User::getCurrentUser();
        if ($logged_user != null)
            return $logged_user->hasPermission("_pipelineexecutor");
        return false;       
    }

    static public function isProjectManager() {
        $logged_user = User::getCurrentUser();
        if ($logged_user != null) {
            $rows = \DB::select("select * from project_groups g where exists(select * from project_group_users u where g.project_group=u.project_group and u.user_id=$logged_user->id and is_manager='Y')");
            return (count($rows) > 0);
        }
        return false;       
    }

    static public function isSignoutManager() {
        $logged_user = User::getCurrentUser();
        if ($logged_user != null)
            return $logged_user->hasPermission("_signout_manager");
        return false;       
    }

    static function getProjectGroups() {        
        if (User::isSuperAdmin())
            return \DB::select("select * from project_groups");
        $logged_user = User::getCurrentUser();
        return \DB::select("select * from project_groups g where exists(select * from project_group_users u where g.project_group=u.project_group and u.user_id=$logged_user->id and is_manager='Y')");

    }

    static function getAllProjectGroups() {        
        return \DB::select("select * from project_groups");        
    }

    static function getManagedProjects() {
        if (User::isSuperAdmin())
            return \DB::select("select * from projects");
        $logged_user = User::getCurrentUser();
        return \DB::select("select * from projects p where exists(select * from project_groups g, project_group_users m where p.project_group=g.project_group and g.project_group=m.project_group and m.user_id=$logged_user->id and m.is_manager='Y')");
    }

	static public function isCurrentUserEditor() {		
		$logged_user = User::getCurrentUser();
		if ($logged_user != null)
			foreach ($logged_user->groups as $group)
				if ($group->name == 'editor')
					return true;
		return false;
	}	

	static public function hasPatient($patient_id) {
		//if (User::isSuperAdmin()) 
		//	return true;
		#Log::info("checking if user can access patient $patient_id");
		$logged_user = User::getCurrentUser();
		$cnt = 0;		
		if ($logged_user != null) {
			$sql = "select count(*) as cnt from project_patients p1, user_projects u where p1.patient_id='$patient_id' and p1.project_id=u.project_id and u.user_id=".$logged_user->id;
			#Log::info($sql);
			$cnt = \DB::select($sql)[0]->cnt;
		}
		return ($cnt > 0);
	}

	static public function hasProject($project_id) {
		//if (User::isSuperAdmin()) 
		//	return true;
		#Log::info("checking if user can access project $project_id");
		$logged_user = User::getCurrentUser();
		$cnt = 0;
		if ($logged_user != null) {
			if ($project_id == "any") return true;
			if (is_numeric($project_id)) {
				$sql = "select count(*) as cnt from user_projects u where project_id=$project_id and u.user_id=".$logged_user->id;			
				$cnt = \DB::select($sql)[0]->cnt;
			} else {
				$sql = "select count(*) as cnt from user_projects u where upper(project_name)='".strtoupper($project_id)."' and u.user_id=".$logged_user->id;
				$cnt = \DB::select($sql)[0]->cnt;
			}
		}
		return ($cnt > 0);
	}
	static public function getCurrentUserProjectsData(){
		$project_json = array();
		$patient_json = array();
		$diagnosis_json = array();
		$patient_id=null;
		$diagnoses = array();
		$projects = array();
		
		$project = array();  //project list for case (one case may belong to many projects)
		
		$default_project = "Clinomics";
		$default_diagnosis = "ANY";
		$project_names=[];
		$path = "";
		$case_list = array();
		$found = false;
		$patient_projects = array();
		$case_name_cnt = array();
		$logged_user = User::getCurrentUser();
		$patients = Patient::getVarPatientList($logged_user->id);
		foreach ($patients as $patient) {
			if (($patient->project_id == $default_project && $patient_id == "null") || (strtolower($default_project) == "any" && $patient_id == "null"))
				$patient_id = $patient->patient_id;						
			if ($patient->patient_id == $patient_id && (strtolower($project_id) == "null" || $patient->project_id == $default_project)) {
				$case_name = $patient->case_name;
				$case_name = str_replace(" ", "_", $case_name);				
				$case_list[$patient->case_id] = $case_name;
				if (!$found) {
					$found = true;
					$diagnosis = $patient->diagnosis;
					$default_project = $patient->project_id;
					$default_diagnosis = $patient->diagnosis;
					$path = $patient->path;
					if ($case_id == 'any')
						$case_id = $patient->case_id;
				}
				$patient_projects[$patient->project_name] = $patient->project_id;
			}
			$project_names[$patient->project_name] = $patient->project_id;
			$project_ids[$patient->project_id] = $patient->project_name;
#			$path=VarCases::getPath($patient->patient_id, $patient->case_id);
			#if($path=="uploads"){
#				Log::info($path);
				$projects[$patient->project_id][$patient->patient_id][$patient->diagnosis][$patient->case_id][$patient->path] = '';
			#}
			$projects["names"]=$project_names;
		}
		return (json_encode($projects));			
	}
	static public function getCurrentUserProjects() {
		$logged_user = User::getCurrentUser();
		if (User::accessAll()) 
			return \DB::select("select * from projects order by name");
		$sql = "select * from projects p where ispublic = 1";


		if ($logged_user != null)
			$sql .= " or exists(select project_id as id, project_name as name from user_projects u where p.id = u.project_id and u.user_id=".$logged_user->id.") order by name";
		return \DB::select($sql);
	}

	static public function getCurrentUserPatients() {
		$logged_user = User::getCurrentUser();
		if ($logged_user != null)
			return \DB::select("select distinct patient_id from user_projects u, project_cases c where u.project_id=c.project_id and u.user_id=$logged_user->id order by patient_id");
		return array();
	}

	static public function getCurrentUserSamples() {
		$logged_user = User::getCurrentUser();
		if ($logged_user != null)
			return \DB::select("select distinct sample_id from user_projects u, project_samples c where u.project_id=c.project_id and u.user_id=$logged_user->id order by sample_id");
		return array();
	}		

	static public function getCurrentUserPermissions() {		
		$logged_user = User::getCurrentUser();
		if ($logged_user != null)
			return $logged_user->permissions;
		return null;
	}

	static public function hasAccessMRN() {
		$logged_user = User::getCurrentUser();
		if ($logged_user == null)
			return false;
		$sql = "select count(*) as cnt from user_projects u, projects p where u.project_id=p.id and u.user_id = $logged_user->id and p.ispublic=9";
		$rows = \DB::select($sql);
		return ($rows[0]->cnt > 0);

	}

	static public function getLatestGene() {
		$logged_user = User::getCurrentUser();
		$sql = "select target from access_log where user_id=".$logged_user->id." and type='gene' order by created_at desc";
		$rows = \DB::select($sql);
		if (count($rows) > 0)
			return $rows[0]->target;
		return "TP53";		
	}

	public function user_profile()
    {
        //return "hello";
        //return $this->hasOne('Jacopo\Authentication\Models\UserProfile');
        $profiles = \DB::table('user_profile')->where('user_id', $this->id)->get();
        if (count($profiles) == 0)
        	return null;
        return $profiles[0];
    }    

}
