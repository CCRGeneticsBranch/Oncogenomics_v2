<?php

namespace App\Models;

use DB,Log;

class VarFlag extends \Illuminate\Database\Eloquent\Model {
	protected $fillable = [];
    protected $table = 'var_flag';
    protected $primaryKey = null;
    public $incrementing = false;
	public $timestamps = false;
	
    static function getAll($type) {
    	//$rows = DB::select("select v.*,d.is_public from var_flag v, var_flag_details d where v.type='$type' and v.chromosome=d.chromosome and v.start_pos=d.start_pos and v.end_pos=d.end_pos and v.ref=d.ref and v.alt=d.alt and v.type=d.type");
        $rows = DB::select("select v.*,d.is_public, d.updated_at from var_flag v, var_flag_details d where v.chromosome=d.chromosome and v.start_pos=d.start_pos and v.end_pos=d.end_pos and v.ref=d.ref and v.alt=d.alt and v.status <> '-1'");
    	return $rows;
    }

    static function deleteFlag($chromosome, $start_pos, $end_pos, $ref, $alt, $type, $patient_id, $updated_at) {
        
    }
}
