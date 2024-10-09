<?php

namespace App\Models;

use DB,Log,Config;

class VarCNV extends \Illuminate\Database\Eloquent\Model {
	protected $fillable = [];
	protected $table = 'var_cnv';

	static function getCNVByCaseID($patient_id, $case_id) {
		$db_type = Config::get("site.db_connection");
		$case_condition = "";
		if ($case_id != "any")
			$case_condition = "and case_id = '$case_id'";
		$order_by = "to_number(decode(substr(chromosome, 4), 'X', '23', 'Y', '24', substr(chromosome, 4))), start_pos asc";
		if ($db_type == "mysql")
			$order_by = "case substring(chromosome,4) when 'X' then 23 when 'Y' then 24 else cast(substring(chromosome,4) as signed) end, start_pos asc";
		return DB::select("select distinct chromosome, start_pos, end_pos, cnt, sample_id, allele_a, allele_b from var_cnv_segment where patient_id = '$patient_id' $case_condition order by $order_by");
	}

	static function getCNVByCaseName($patient_id, $case_name) {
		$db_type = Config::get("site.db_connection");
		$case_condition = "";
		if ($case_name != "any")
			$case_condition = "and exists(select * from sample_cases s where s.sample_id=v.sample_id and s.patient_id=v.patient_id and s.case_name = '$case_name')";
		$order_by = "to_number(decode(substr(chromosome, 4), 'X', '23', 'Y', '24', substr(chromosome, 4))), start_pos asc";
		if ($db_type == "mysql")
			$order_by = "case substring(chromosome,4) when 'X' then 23 when 'Y' then 24 else cast(substring(chromosome,4) as signed) end, start_pos asc";
		return DB::select("select distinct chromosome, start_pos, end_pos, cnt, sample_id, allele_a, allele_b from var_cnv_segment v where patient_id = '$patient_id' $case_condition order by $order_by");
	}

}


