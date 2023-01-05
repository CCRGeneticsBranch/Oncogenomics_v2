<?php

namespace App\Models;

use DB,Log;

class VarSignout extends \Illuminate\Database\Eloquent\Model {
	protected $fillable = [];
	protected $table = 'var_signout';
	protected $primaryKey = null;
    public $incrementing = false;
	
	static public function getSignoutHistory($patient_id, $sample_id, $case_id, $type) {
		$sql = "select status, v.updated_at as signout_time, p.first_name || ' ' || p.last_name as user_name, var_num as total_variants, var_list from var_signout v, user_profile p where patient_id='$patient_id' and sample_id='$sample_id' and case_id='$case_id' and type='$type' and v.user_id=p.user_id";
		$rows = DB::select($sql);
		return $rows;
	}
	
}
