<?php

class QCLog extends Eloquent {
	protected $fillable = [];
    protected $table = 'qc_log';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = true;
	
	static public function getLogByPatientAndType($patient_id, $case_id, $log_type)
    {
        return $rows = DB::select("select log_decision as Decision, email as \"User name\", log_comment as \"comment\", l.updated_at as \"Time\" from qc_log l, users u where l.patient_id='$patient_id' and l.case_id='$case_id' and l.log_type = '$log_type' and l.user_id=u.id");
    }

}
