<?php

namespace App\Models;

use DB,Log;

class VarACMGGuide extends \Illuminate\Database\Eloquent\Model {
	protected $fillable = [];
    protected $table = 'var_acmg_guide';
    protected $primaryKey = null;
    public $incrementing = false;
	public $timestamps = false;

    static function getAll() {
        $rows = DB::select("select v.*,d.is_public from var_acmg_guide v, var_acmg_guide_details d where v.chromosome=d.chromosome and v.start_pos=d.start_pos and v.end_pos=d.end_pos and v.ref=d.ref and v.alt=d.alt");
        return $rows;
    }
	    
}
