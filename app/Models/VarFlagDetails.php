<?php

namespace App\Models;

use DB,Log;

class VarFlagDetails extends \Illuminate\Database\Eloquent\Model {
	protected $fillable = [];
    protected $table = 'var_flag_details';
    protected $primaryKey = null;
    public $incrementing = false;    
}
