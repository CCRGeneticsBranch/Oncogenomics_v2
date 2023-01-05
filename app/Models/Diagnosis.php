<?php

/**
 *
 * OR mapping class for table 'diagnosis'. This table stores the cancer type information from Oncotree.
 *
 * @copyright 2018 Javed Khan's group
 * @author Hsien-chao Chou, Scott Goldweber
 * @package models
 */
namespace App\Models;

use DB,Log;

class Diagnosis extends \Illuminate\Database\Eloquent\Model {
	protected $fillable = [];
	protected $table = 'diagnosis';
	public $timestamps = false;
	public $incrementing = false;
	
	static public function getDiagnosisByCode($code) {
		$rows = DB::select("select * from diagnosis where secondary_code='$code' or tertiary_code='$code' or quaternary_code='$code' or quinternary_code='$code'");
		if (count($rows) > 0)
			return $rows[0];		
		return null;
	}

}


