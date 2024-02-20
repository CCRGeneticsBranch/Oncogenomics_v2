<?php

namespace App\Models;

use DB,Log;

class VarCNV extends \Illuminate\Database\Eloquent\Model {
	protected $fillable = [];
	protected $table = 'var_cnv';

	static function getCNVByCaseID($patient_id, $case_id) {
		$case_condition = "";
		if ($case_id != "any")
			$case_condition = "and case_id = '$case_id'";
		return DB::select("select distinct chromosome, start_pos, end_pos, cnt, sample_id, allele_a, allele_b from var_cnv where patient_id = '$patient_id' $case_condition order by to_number(decode(substr(chromosome, 4), 'X', '23', 'Y', '24', substr(chromosome, 4))), start_pos asc");
	}

	static function getCNVByCaseName($patient_id, $case_name) {
		$case_condition = "";
		if ($case_name != "any")
			$case_condition = "and exists(select * from sample_cases s where s.sample_id=v.sample_id and s.patient_id=v.patient_id and s.case_name = '$case_name')";
		return DB::select("select distinct chromosome, start_pos, end_pos, cnt, sample_id, allele_a, allele_b from var_cnv v where patient_id = '$patient_id' $case_condition order by to_number(decode(substr(chromosome, 4), 'X', '23', 'Y', '24', substr(chromosome, 4))), start_pos asc");
	}

}


