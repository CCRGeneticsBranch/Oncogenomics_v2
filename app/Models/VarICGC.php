<?php

class VarICGC extends Eloquent {
	protected $fillable = [];
        protected $table = 'var_icgc';
	
	static public function findVar($chr, $start_pos, $end_pos, $ref, $alt) {
		$rows = DB::select("select * from var_icgc where chromosome='$chr' and start_pos='$start_pos' and end_pos = '$end_pos' and ref = '$ref' and alt = '$alt'");
		if (count($rows) == 1) {
			return $rows[0];
		}
		if (count($row) > 1) {
			return $rows;
		}
		return null;

	}
	
}
