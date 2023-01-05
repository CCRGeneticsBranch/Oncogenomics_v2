<?php

class GeneSurf extends Eloquent {
	protected $fillable = [];
        protected $table = 'gene_surf';
	protected $primaryKey = 'gene';

	static public function getAll() {
		$key = 'gene_surf';
		if (!Cache::has($key)) {
			$sql = "select s.*,e.gene as ensembl_id from gene_surf s, gene_ensembl e where s.gene=e.symbol and e.species='Hs'";
			//$rows = GeneSurf::all();
			$rows = DB::select($sql);
			$gene_surf = array();
			foreach ($rows as $row) {
				$gene_surf[$row->gene] = $row;
				$gene_surf[$row->ensembl_id] = $row;
				$row->membranous_protein = ($row->membranous_protein == 1)? 'Y': 'N';
			}
			Cache::forever('gene_surf', $gene_surf);
		}
		return Cache::get($key);
	}	
}
