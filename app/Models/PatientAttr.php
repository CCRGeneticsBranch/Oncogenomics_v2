<?php

class PatientAttr extends Eloquent {
	protected $fillable = [];
    protected $table = 'patient_attr';
	
	public function project() {
		return $this->belongsTo('Project', 'project_id');
	}

	static public function getAttr($project_id) {
		if (User::accessAll()) 
			$user_where = "";
		else {
			$logged_user = User::getCurrentUser();
			if ($logged_user != null)
				$user_where = "and exists(select * from projects p2, users_groups u where p1.project_id = p2.id and (p2.ispublic=1 or (p2.id=u.group_id and u.user_id=". $logged_user->id.")))";
			else
				$user_where = "and exists(select * from projects p2 where p1.patient_id = p2.id and p2.ispublic=1)";
		}

		if ($project_id != 'null' && $project_id != 'any') {
			$user_where .= " and p1.project_id=$project_id";	
		}

		$sql = "select * from patient_attr p1 where included = '1' $user_where";
		return DB::select($sql);
	}
	

}
