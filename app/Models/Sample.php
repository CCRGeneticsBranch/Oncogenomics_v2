<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use DB,Log,Config,File,Lang;

class Sample extends Model {
	protected $fillable = [];
	protected $table = 'samples';
	protected $primaryKey = 'sample_id';
	public $timestamps = false;
	public $incrementing = false;

	static public function getAvia_summary() {
		$pubmed_url = Config::get('site.avia_version');
		$sql = "select * from ".$pubmed_url ;
		Log::info("GET AVIA");
		Log::info(Config::get('onco.avia_version'));
		return \DB::select($sql);
		
	}

	static public function getSamplesByPatients($patients) {
		$patient_list = implode("','", $patients);
		$sql = "select * from samples where patient_id in ('$patient_list')";
		return \DB::select($sql);
		
	}
 
	static public function getSamplesByPatientID($patient_id, $case_name = "any", $project_id="any") {
		$case_condition = "";
		$project_condition = "";		
		if ($case_name != "any")
			$case_condition = " and case_name in ('$case_name') ";
		if ($project_id != "any")
			$project_condition = " and exists(select * from project_samples p where s.sample_id=p.sample_id and p.project_id=$project_id)";
		$sql = "select * from sample_cases s where s.patient_id = '$patient_id' $case_condition $project_condition";
		Log::info($sql);
		return \DB::select($sql);
		
	}

	# temp solution for  unmatched case_id/case_name
	static public function getProcessedSamplesByPatient($patient_id, $case_id=null) {
		$sample_ids = array();
		$case_condition = '';
		if ($case_id !="any" && $case_id != null)
			$case_condition = $case_condition." and case_id = '$case_id'";		
		$sql = "select distinct s1.*,s2.type,s2.case_id from samples s1,var_samples s2 where s1.sample_id=s2.sample_id and s1.patient_id = '$patient_id' $case_condition and type <> 'hotspot'";
		Log::info($sql);
		return \DB::select($sql);
		
	}
	
	static public function getVarSamplesByPatient($patient_id, $case_name=null) {
		$sample_ids = array();
		$case_condition = '';
		if ($case_name !="any" && $case_name != null)
			$case_condition = $case_condition." and case_name = '$case_name'";
		$sql = "select distinct patient_id,sample_id,case_name,case_id,path,sample_name,sample_alias,exp_type,tissue_cat from processed_sample_cases where patient_id = '$patient_id' $case_condition and type <> 'hotspot'";
		Log::info($sql);
		return \DB::select($sql);
		
	}

	static public function getMixcr($patient_id, $case_id=null, $type="summary") {
		$table = ($type == "summary")? "mixcr_summary": "mixcr";
		$case_condition = '';
		if ($case_id !="any" && $case_id != null)
			$case_condition = $case_condition." and case_id = '$case_id'";	
		$sql = "select * from $table where patient_id='$patient_id' $case_condition ";
		Log::info($sql);
		$rows = \DB::select($sql);
		for ($i=0;$i<count($rows);$i++) {
			if ($type == "summary") {
				$rows[$i]->mean_frequency = is_numeric($rows[$i]->mean_frequency)? round($rows[$i]->mean_frequency, 4) : 0;
				$rows[$i]->geomean_frequency = is_numeric($rows[$i]->geomean_frequency)? round($rows[$i]->geomean_frequency, 4) : 0;
				$rows[$i]->mean_cdr3nt_length = is_numeric($rows[$i]->mean_cdr3nt_length)? round($rows[$i]->mean_cdr3nt_length, 2) : 0;
				$rows[$i]->mean_insert_size = is_numeric($rows[$i]->mean_insert_size)? round($rows[$i]->mean_insert_size, 2) : 0;
				$rows[$i]->mean_ndn_size = is_numeric($rows[$i]->mean_ndn_size)? round($rows[$i]->mean_ndn_size, 2) : 0;
			} else {
				$rows[$i]->freq = is_numeric($rows[$i]->freq)? round($rows[$i]->freq, 4) : 0;
			}
		}
		
		return $rows;
	}

	static public function getExpressionFile($path, $sample_id, $sample_name, $type, $level, $file_type="txt") {
		$level_str = ($level == "gene")? "Gene" : "Transcript";

		#$suffix = ($type == "refseq")? ".rsem_UCSC.genes.results": ".rsem_ENS.genes.results";
		#$folder = ($type == "refseq")? "RSEM_UCSC" : "RSEM_ENS";
		$suffix = ".rsem_ENS.genes.results";
		$folder = "RSEM_ENS";
		$sample_file = "$path/$sample_name/$folder/$sample_name$suffix";
		Log::info($sample_file);
		
		list($file_path, $sample_folder) = Sample::getFilePath($path, $sample_id, $sample_name, $folder, $suffix);

		//for Manoj
		if ($file_path == "") {
			$folder = "RSEM";
			list($file_path, $sample_folder) = Sample::getFilePath($path, $sample_id, $sample_name, $folder, $suffix);
		}

		if ($file_path != "") {
			Log::info("FOUND RSEM ENS");
			return $file_path;		
		}

		$suffix = ".rsem_UCSC.genes.results";
		$folder = "RSEM_UCSC";
		$sample_file = "$path/$sample_name/$folder/$sample_name$suffix";
		Log::info($sample_file);
		
		list($file_path, $sample_folder) = Sample::getFilePath($path, $sample_id, $sample_name, $folder, $suffix);
		if ($file_path != "") {
			Log::info("FOUND RSEM UCSC");
			return $file_path;		
		}
		
		Log::info("NOT FOUND RSEM");
		Log::info($file_path);
		Log::info("Type: $type");
		Log::info("File type: $file_type");
		$suffix = ".".strtolower($level_str).".TPM.$file_type";
		$folder = ($type == "refseq")? "TPM_UCSC": "TPM_ENS";
		Log::info("$path, $sample_id, $sample_name, $folder, $suffix");

		list($file_path, $sample_folder) = Sample::getFilePath($path, $sample_id, $sample_name, $folder, $suffix);
		if ($file_path == "") {
			$suffix = "_fpkm.$level_str.$file_type";
			$folder = ($type == "refseq")? "TPM_UCSC" : "TPM_ENS";
			list($file_path, $sample_folder) = Sample::getFilePath($path, $sample_id, $sample_name, $folder, $suffix);
		}
		Log::info(".".strtolower($level_str).".TPM.$file_type");
		return $file_path;		
	}

	static public function getFilePath($path, $sample_id, $sample_name, $folder, $suffix) {
		$sample_file = "$path/$sample_id/$folder/$sample_id$suffix";
		//Log::info($sample_file);
		if (file_exists($sample_file))
			return array($sample_file, $sample_id);
		$sample_file = "$path/$sample_id/$folder/$sample_id/$sample_id$suffix";
		if (file_exists($sample_file))
			return array($sample_file, $sample_id);
		$sample_file = "$path/$sample_name/$folder/$sample_name$suffix";
		if (file_exists($sample_file))
			return array($sample_file, $sample_name);	
		$sample_file = "$path/$sample_name/$folder/$sample_name/$sample_name$suffix";
		if (file_exists($sample_file))
			return array($sample_file, $sample_name);	
		$sample_file = "$path/Sample_$sample_id/$folder/Sample_$sample_id$suffix";
		if (file_exists($sample_file))
			return array($sample_file, "Sample_$sample_id");
		$sample_file = "$path/Sample_$sample_id/$folder/Sample_$sample_id/Sample_$sample_id$suffix";
		if (file_exists($sample_file))
			return array($sample_file, "Sample_$sample_id");
		$sample_file = "$path/Sample_$sample_name/$folder/Sample_$sample_name$suffix";
		if (file_exists($sample_file)) 
			return array($sample_file, "Sample_$sample_name");
		$sample_file = "$path/Sample_$sample_name/$folder/Sample_$sample_name/Sample_$sample_name$suffix";
		if (file_exists($sample_file)) 
			return array($sample_file, "Sample_$sample_name");
		$sample_file = "$path/$sample_name/$folder/$sample_name$suffix";
		if (file_exists($sample_file)) 
			return array($sample_file, $sample_name);
		#Log::info("SAMPLE FILE: ".$sample_file);	
		return array("","");
	}
	 
	static public function getExpSamplesFromVarSamples($sample_list) {
		$sql = "select sample_id from samples s1 where material_type='RNA' and exists(select * from samples s2 where s1.patient_id=s2.patient_id and s2.sample_id in ('$sample_list'))";		
		return \DB::select($sql);
	}

	static public function getTranscriptExpression($genes, $samples, $data_type="refseq") {
		$gene_list = strtoupper(implode("','", $genes));
		$sample_list = implode("','", $samples);
		$data_type_clause = ($data_type == "all")? "" : "and data_type = '$data_type'";

		$sql = "select trans, gene, symbol from transcripts where upper(symbol) in ('$gene_list') or upper(gene) in ('$gene_list') $data_type_clause";
		$rows = \DB::select($sql);
		Log::info($sql);

		$gene_data = array();
		foreach ($rows as $row) {
			$genes_data[strtoupper($row->symbol)][] = $row->trans;
			$genes_data[strtoupper($row->gene)][] = $row->trans;
		}
		
		$data_type_clause = ($data_type == "all")? "" : "and target_type = '$data_type'";
		
		$sql = "select * from sample_values where sample_id in ('$sample_list') and upper(symbol) in ('$gene_list') $data_type_clause";
		$rows = \DB::select($sql);
		
		$exps = array();
		
		foreach ($rows as $row) {
			$exps[$row->sample_id][$row->target] = round(log($row->value+1,2),2);
		}
		

		$results = array();
		foreach ($samples as $sample_id) {
			foreach ($genes as $symbol) {
				if (array_key_exists($symbol, $genes_data))
					$trans_data = $genes_data[$symbol];
				else
					return null;

				$exp = (isset($exps[$sample_id][$symbol]))? $exps[$sample_id][$symbol] : 'N/A';
				$results[$sample_id][$symbol]["exp"] = $exp;
				foreach ($trans_data as $t) {
					$exp = (isset($exps[$sample_id][$t]))? $exps[$sample_id][$t] : 'N/A';
					$results[$sample_id][$symbol]["trans"][$t] = $exp;
				}
			}
		}
		return $results;
		
	}

	static public function getExonExpression($genes, $samples, $data_type="refseq") {
		$gene_list = implode("','", $genes);
		$sample_list = implode("','", $samples);		
		
		$data_type_clause = ($data_type == "all")? "" : "and target_type = '$data_type'";
		
		$sql = "select * from exon_expression where sample_id in ('$sample_list') and symbol in ('$gene_list') $data_type_clause";
		Log::info($sql);
		$rows = \DB::select($sql);
		
		$exps = array();
		
		foreach ($rows as $row) {
			//use zero-base coordinate
			$row->start_pos--;
			$exon = $row->chromosome.":".$row->start_pos."-".$row->end_pos;
			$exps[$row->sample_id][$row->symbol][$exon] = round($row->value,2);
		}
		
		return $exps;		
		
	}

	static public function getSampleCasesByPatientList($patient_list, $processed=true) {
		$source = ($processed)? "processed_sample_cases" : "sample_cases";
		$sql = "select * from $source where patient_id in ('$patient_list')";
		Log::info($sql);
		$rows = \DB::select($sql);
		return $rows;
	}

	static public function getCaseID($patient_id, $sample_id) {
		#$sql = "select distinct case_id from sample_cases s, var_cases v where v.patient_id='$patient_id' and sample_id='$sample_id' and s.case_name=v.case_name";
		$sql = "select distinct case_id from processed_sample_cases s where patient_id='$patient_id' and sample_id='$sample_id'";
		$rows = \DB::select($sql);
		if (count($rows) == 0)
			return "";
		return $rows[0]->case_id;
	}

	static public function getCaseName($patient_id, $sample_id) {
		#$sql = "select distinct case_id from sample_cases s, var_cases v where v.patient_id='$patient_id' and sample_id='$sample_id' and s.case_name=v.case_name";
		$sql = "select distinct case_name from processed_sample_cases s where patient_id='$patient_id' and sample_id='$sample_id'";
		$rows = \DB::select($sql);
		if (count($rows) == 0)
			return "";
		return $rows[0]->case_name;
	}

	static public function getNormalSample($sample) {
		$rows = \DB::select("select s2.* from samples s1, samples s2 where (s1.sample_id = '$sample' or s1.sample_name = '$sample') and s2.sample_id=s1.normal_sample");
		if (count($rows) == 0)
			return null;
		return $rows[0];
	}

	static public function getSampleIDByName($sample_name) {
		$samples = Sample::where('sample_name', $sample_name)->get();
		$sample_id = "";
		if (count($samples) > 0)
			$sample_id = $samples[0]->sample_id;
		return $sample_id;
	}

	static public function getSampleNameByID($sample_id) {
		$samples = Sample::where('sample_id', $sample_id)->get();
		$sample_name = "";
		if (count($samples) > 0)
			$sample_name = $samples[0]->sample_name;
		return $sample_name;
	}

	static public function getSample($sample_id_name) {
		$samples = Sample::where('sample_id', $sample_id_name)->orWhere('sample_name', $sample_id_name)->get();
		if (count($samples) > 0)
			return $samples[0];
		return null;
	}

	static public function searchSample($keyword, $include_details=false) {
		$logged_user = User::getCurrentUser();		
		if ($logged_user != null) {
			$field_list = "s.sample_id,s.sample_name,s.patient_id,source_biomaterial_id,material_type,exp_type,library_type,tissue_cat,tissue_type,normal_sample,rnaseq_sample";
			$rows = \DB::select("select LISTAGG(name || ':' || s.project_id, ',') as project,LISTAGG(case_name || ':' || case_id, ',') as case_name,$field_list from project_samples s, sample_case_mapping m, user_projects u where s.sample_id like '%$keyword%' and s.project_id=u.project_id and s.sample_id=m.sample_id and user_id = $logged_user->id group by $field_list");
			if ($include_details) {
				$detail_rows = \DB::select("select d.* from sample_details d,project_samples s, user_projects u where s.sample_id like '%$keyword%' and s.project_id=u.project_id and s.sample_id=d.sample_id and user_id = $logged_user->id");
				$rows = Sample::addSampleDetails($rows, $detail_rows);
			}
			return $rows;
		}
		return array();
	}

	static public function addSampleDetails($rows, $detail_rows) {
		$detail_data = array();
		$attrs = array();
		foreach ($detail_rows as $detail_row) {
			if (strtolower($detail_row->attr_name) == "patient_id")
				continue;
			$attrs[$detail_row->attr_name] = '';
			$detail_data[$detail_row->sample_id][$detail_row->attr_name] = $detail_row->attr_value;
		}
		$attrs = array_keys($attrs);
		foreach ($rows as $row) {
			foreach ($attrs as $attr) {
				$value = '';
				if (array_key_exists($row->sample_id, $detail_data))
					if (array_key_exists($attr, $detail_data[$row->sample_id]))
						$value = $detail_data[$row->sample_id][$attr];
				$row->{$attr} = $value;
			}
		}
		return $rows;

	}

	static public function getSampleDetails($patient_id) {
		$rows = \DB::select("select * from sample_details d where exists(select * from samples s where d.sample_id=s.sample_id and s.patient_id='$patient_id')");
		return $rows;
	}

	static public function getSampleCountByExpType() {
		$logged_user = User::getCurrentUser();
		if ($logged_user != null)
			return \DB::select("select exp_type, count(distinct sample_id) as sample_count from project_samples s, user_projects u where s.project_id=u.project_id and user_id = $logged_user->id group by exp_type");
		return array();
	}

	static public function getSampleCountByTissueCat() {
		$logged_user = User::getCurrentUser();
		if ($logged_user != null)
			return \DB::select("select tissue_cat, count(distinct sample_id) as sample_count from project_samples s, user_projects u where s.project_id=u.project_id and user_id = $logged_user->id group by tissue_cat");
		return array();
	}

	static public function getGenotyping($samples, $type) {
		$sample_ids = array();
		foreach ($samples as $sample) {
			$sample_ids[$sample->sample_id] = '';
			$sample_ids[$sample->sample_name] = '';
		}

		$file = storage_path()."/data/".Config::get('onco.genotyping');

		$fh = fopen($file, "rb");
		$line = fgets($fh);
		$line = trim($line);
		$headers = explode("\t", $line);
		$cols = array(array("title"=>"Sample"));
		$header_idx = array();
		$idx = 0;
		foreach ($headers as $header) {			
			preg_match('/Sample_(.*)/', $header, $matches);
			if (count($matches) > 0)
				$header = $matches[1];
			
			//if ($search_text == 'all' || ($search_text != 'all' && isset($sample_ids[$header]))) {
			if (array_key_exists($header, $sample_ids)) {
				$cols[] = array("title" => $header);
				$header_idx[] = $idx;
			}
			$idx++;
		}

		$data = array();
		$count = 0;
		while (!feof($fh) ) {			
			$line = fgets($fh);
			$line = trim($line);
			if ($line == '') continue;
			$fields = explode("\t", $line);
			preg_match('/Sample_(.*)/', $fields[0], $matches);
			if (count($matches) > 0)
				$fields[0] = $matches[1];
			if ($type == "self" && !array_key_exists($fields[0], $sample_ids)) 
				continue;			
			$data_row = array();
			//if ($search_text == 'all')
			//	$data_row[] = $fields;
			//else {
				
				$data_row[] = $fields[0];
				foreach ($header_idx as $idx) {
					$value = $fields[$idx] * 100;
					$bar_class = Sample::getGenotypingClass($value);
					$html = "<div class='progress text-center' style='margin-bottom:0px'><div class='progress-bar $bar_class progress-bar-striped' role='progressbar' aria-valuenow='$value' aria-valuemin='0' aria-valuemax='100' style='width:$value%'><span>$value%</span></div></div>";
					$data_row[] = $html;
				}
			//}
			$data[] = $data_row;
			Log::info($data);
		}
		fclose($fh);

		return json_encode(array("cols"=>$cols, "data" => $data));
	}

	static function getGenotypingClass($value) {
		$bar_class = "progress-bar-danger";
		if ($value <= 70)
			$bar_class = "progress-bar-success";
		if ($value > 60 && $value <= 80)
			$bar_class = "progress-bar-info";
		if ($value > 80 && $value <= 90)
			$bar_class = "progress-bar-warning";
		return $bar_class;
	}
}


