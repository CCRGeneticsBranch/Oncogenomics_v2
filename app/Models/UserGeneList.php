<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

use DB,Log,Config,File,Lang;

class UserGeneList extends Model {
	public $timestamps = false;
	public $incrementing = false;
	protected $fillable = [];
	protected $table = 'user_gene_list';
	protected $primaryKey = null;	

	static public function getGeneList($type, $name="all", $use_hash=true) {

		$time_start = microtime(true);
		$key = "$type.$name";
		$user_filter_list = null;
		try {
			$user = User::getCurrentUser();
			$user_id = $user->id;			
			if ($type == "rnaseq")
				$type = "rnaseq','somatic','germline";
			$sql = "select * from user_gene_list where (user_id=$user_id or ispublic='Y') and type in ('$type','all')";
			if ($name <> "all")
				$sql .= " and list_name = '$name'";			
		} catch (\Exception $e) {
			$user_id = "";
			$sql = "select * from user_gene_list where ispublic='Y' and type in ('$type','all')";
			if ($name <> "all")
				$sql .= " and list_name = '$name'";			
		}
		
		$key .= ".$user_id";
		$gene_list = null;
		$cache_mode = Config::get('onco.cache.list');
		if ($cache_mode) {
			if (Cache::has("gene_list")) {			
				$gene_list = Cache::get("gene_list");
				if (array_key_exists($key, $gene_list)) {
					Log::info("using cache: $key");
					$user_filter_list = $gene_list[$key];
				}
			}
		} 
		if ($user_filter_list == null) {
			$user_filter_list = array();
			$user_gene_list = DB::select($sql);		
			foreach ($user_gene_list as $list) {
				$gene_list_arr = explode(" ", $list->gene_list);
				$gene_list_hash = array();
				if ($use_hash) {
					foreach($gene_list_arr as $gene) {
						$gene_list_hash[$gene] = '';
					}
				}
				else
					$gene_list_hash = $gene_list_arr;
				$user_filter_list[$list->list_name] = $gene_list_hash;
			}
			if ($gene_list == null)
				$gene_list = array($key => $user_filter_list);
			else
				$gene_list[$key] = $user_filter_list;			
			Cache::forever("gene_list", $gene_list);
			
		}
		$time = microtime(true) - $time_start;
		Log::info("execution time (getGeneList): ".round($time,2)." seconds");
		ksort($user_filter_list);
		return $user_filter_list;
	}

	static public function getDescriptions($type) {
		$user_filter_list = array();
		try {
			$user = User::getCurrentUser();
			$user_id = $user->id;
			if ($type == "rnaseq")
				$type = "rnaseq','somatic','germline";
			$user_gene_list = DB::select("select list_name, description from user_gene_list where (user_id=$user_id or ispublic='Y') and type in ('$type','all')");
		} catch (Exception $e) {
			$user_gene_list = DB::select("select list_name, description from user_gene_list where ispublic='Y' and type in ('$type','all')");
		}
		
		foreach ($user_gene_list as $list) {
			$user_filter_list[$list->list_name] = $list->description;
		}
		return $user_filter_list;
	}
	static public function isGeneListPublic($list_name){
		$is_public = DB::select("select ISPUBLIC from user_gene_list where LIST_NAME='".$list_name."'");
		return $is_public;
	}
	static public function geneInList($list_name, $gene) {
		$rows = DB::select("select * from user_gene_list_dtl where list_name = '$list_name' and gene = '$gene'");
		return (count($rows) > 0);
	}
}
