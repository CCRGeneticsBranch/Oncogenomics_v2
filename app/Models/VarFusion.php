<?php

class VarFusion extends Eloquent {
	protected $fillable = [];
        protected $table = 'var_fusion';
	

	static public function getfusionGenes($chr,$start_pos,$end_pos,$strand) {
		$tbl_name = "gene";
		$sql = "select symbol,end_pos from gene where CHROMOSOME='$chr' and START_POS=$start_pos and END_POS=$end_pos and STRAND='$strand'";
		Log::info($sql);
		$row = DB::select($sql);
		return $row;

	}
}


