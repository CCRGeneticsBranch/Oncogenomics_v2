<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB,Log,Config,File,Lang;


putenv ("R_LIBS=/opt/nasapps/applibs/r-3.5.0_libs/");
class Project extends Model {
	protected $fillable = [];
    protected $table = 'projects';
	private $expression_cnt;
	private $target_type;
	private $var_gene_count = array();
	private $cnv_gene_data  = array();
	private $fusion_gene_data  = array();
	private $has_mutation;
	private $has_burden;
	private $dx= array();

	public function patient_attrs() {
		return $this->hasMany('PatientAttr', 'project_id');
	}
	
	public function sampleSummary() {
		return $this->hasMany('App\Models\ProjectSampleSummary', 'project_id');
	}

	public function getSampleSummary($exp_type) {
		$sample_summary = $this->sampleSummary;
		foreach ($sample_summary as $summary) {
			if ($summary->exp_type == $exp_type){
				return $summary->samples;
			}
		}
		return 0;
	}	

	public function getUserList() {
		$sql = "select distinct first_name,last_name,u.email_address,to_char(u.permissions) as roles,(select count(*) from access_log a where a.user_id=u.id and a.project_id=to_char(p.project_id)) as access_frequency,(select max(created_at) from access_log a where a.user_id=u.id and a.project_id=to_char(p.project_id)) as last_access_project,u.last_login from user_projects p, users u, user_profile f where project_id='$this->id' and p.user_id=u.id and u.id=f.user_id order by last_name";
		return DB::select($sql);
	}

	public function getExpressionCount() {
		if (!isset($this->expression_cnt)) {
			$sql = "select count(*) as cnt from project_values where project_id=$this->id";
			$this->expression_cnt = DB::select($sql)[0]->cnt;
		}

		return $this->expression_cnt;
	}

	public function getExpressionProcessingTime() {
		$res = "NA";
		$f = storage_path()."/project_data/".$this->id."/expression.tpm.tsv";		
		if (file_exists($f)) {
			$res = date ("F d Y H:i:s.", filemtime($f));
		}		
		return $res;
	}

	public function getTargetType($value_type="tmm-rpkm") {
		return "ensembl";
		if (!isset($this->target_type)) {
			$this->target_type = "";
			//Log::info(storage_path()."/project_data/$this->id/refseq-gene.tsv");
			if (file_exists(storage_path()."/project_data/$this->id/refseq-gene.$value_type.tsv"))
				$this->target_type = "refseq";
			if (file_exists(storage_path()."/project_data/$this->id/ensembl-gene.$value_type.tsv"))
				$this->target_type = "ensembl";			
		}
		return $this->target_type;
	}

	public function showFeature($feature) {
		$key = 'site.projects.'.$this->name.'.'.$feature;
		if (Config::has($key))
			return Config::get($key);
		else
			return true;
	}

	public function getProperty($prop) {
		$key = 'site.projects.'.$this->name.'.'.$prop;
		if (Config::has($key))
			return Config::get($key);
		else
			return null;
	}

	public function getTargetTypes($value_type="tmm-rpkm") {
		return array("ensembl");
		$types = array();
		if (file_exists(storage_path()."/project_data/$this->id/ensembl-gene.coding.tmm-rpkm.tsv"))
			$types[] = "ensembl";		
		if (file_exists(storage_path()."/project_data/$this->id/refseq-gene.coding.tmm-rpkm.tsv"))
			$types[] = "refseq";		
		return $types;
	}

	public function getSampleMetaData($exp_type, $key="sample_alias", $tissue_cat="all" ,$tissue_type="all", $include_details=false) {
		$tissue_cat_condition = "";
		$query="select s1.project_id,s2.patient_id,s2.sample_id,s2.sample_name,s2.sample_alias,s2.tissue_cat,s2.library_type,s2.platform,s2.material_type,s2.exp_type,regexp_substr(s2.tissue_type,'[^/(,]+') as tissue_type" ;
		if ($tissue_cat != "all")
			$tissue_cat_condition = " and s2.tissue_cat = '$tissue_cat'";
		if ($tissue_type != "all")
			$tissue_cat_condition .= " and regexp_like(s2.tissue_type,'^(" .preg_replace("/,/","|",$tissue_type). ")')";
			// $tissue_cat_condition .= " and tissue_type in ('" .preg_replace("/,/","','",$tissue_type). "')";
		$sql = "$query from project_samples s1, samples s2 where project_id=$this->id and s1.patient_id=s2.patient_id and s1.sample_id=s2.sample_id and s2.exp_type = '$exp_type' $tissue_cat_condition";
		Log::info($sql);
		$samples = DB::select($sql);
		$sample_details_attrs = array();
		$sample_details_values = array();
		if ($include_details) {
			$sql_details = "select * from project_samples s1, sample_details s2 where project_id=$this->id and s1.sample_id=s2.sample_id";
			$sample_details = DB::select($sql_details);			
			foreach ($sample_details as $sample_detail) {
				$sample_details_attrs[$sample_detail->attr_name] = "";
				$sample_details_values[$sample_detail->sample_id][$sample_detail->attr_name] = $sample_detail->attr_value;
			}
			$sample_details_attrs = array_keys($sample_details_attrs);
		}
		
		// dd("$query from project_samples where project_id=$this->id and exp_type = '$exp_type' $tissue_cat_condition")
		//$patient_attrs = PatientAttr::getAttr($this->id);
		$sample_meta = array();
		$attr_list = array(Lang::get("messages.tissue_cat"),Lang::get("messages.tissue_type"),Lang::get("messages.library_type"));
		$attr_values = array();
		$patients = array();
		//add patient meta data to sample annotation
		$patient_meta = $this->getPatientMetaData(false, true);
		$patient_data = $patient_meta["data"];
		$patient_attr_list = $patient_meta["attr_list"];
		$empty_array = array();
		foreach ($patient_attr_list as $dummy) 
			$empty_array[] = "NA";
		$sample_names = array();
		foreach($samples as $sample) {			
			if (isset($sample_names[$sample->sample_name]))
				$sample->sample_name .= "_2";
			$sample_id = $sample->sample_id;
			if ($key == "sample_name")
				$sample_id = $sample->sample_name;
			if ($key == "sample_alias")
				$sample_id = $sample->sample_alias;
			#$sample_id = ($use_id)? $sample->sample_id : $sample->sample_name;
			$sample->tissue_type = trim($sample->tissue_type);
			if (isset($patient_data[$sample->patient_id])){
				$sample_meta[$sample_id] = array_merge(array($sample->tissue_cat, $sample->tissue_type, $sample->library_type), $patient_data[$sample->patient_id]);
			}
			else
				$sample_meta[$sample_id] = array_merge(array($sample->tissue_cat, $sample->tissue_type, $sample->library_type), $empty_array);
			if ($include_details) {
				$sample_details_value_arr = array();
				foreach ($sample_details_attrs as $sample_details_attr) {
					$v = "NA";
					if (isset($sample_details_values[$sample->sample_id][$sample_details_attr]))
						$v = $sample_details_values[$sample->sample_id][$sample_details_attr];
					$sample_details_value_arr[] = $v;
				}
				$sample_meta[$sample_id] = array_merge($sample_meta[$sample_id], $sample_details_value_arr);
			}
			$patients[$sample_id] = $sample->patient_id;
			$sample_names[] = $sample->sample_name;
		}
		#Log::info(json_encode($sample_meta));
		$attr_list_arr = array_merge($attr_list, $patient_attr_list);
		if ($include_details)
			$attr_list_arr = array_merge($attr_list_arr, $sample_details_attrs);
		return array("data" => $sample_meta, "attr_list" => $attr_list_arr, "patients" => $patients);
	}

	public function getPatientMetaData($include_diagnosis = true , $includeOnlyRNAseq = false, $include_numeric=true, $meta_list=null) {
		$pat_q = "select p1.* from patient_details p1, project_samples p2 where p2.project_id=$this->id and p1.patient_id=p2.patient_id";#hv changed this from project_patients to project_samples on 20190913; also added $includeOnlyRNAseq flag and next block
		if ($includeOnlyRNAseq)
			$pat_q.=" and p2.exp_type='RNAseq'";
		// End Hue new
		$patient_data = DB::select($pat_q);
		$patients = DB::select("select patient_id,diagnosis from project_patients where project_id=$this->id");
		//$patient_attrs = PatientAttr::getAttr($this->id);

		$diag_data = array();
		$data_list = array();
		$attr_list = array();

		$arr = array();
		$diag_arr = array();
		foreach ($patients as $patient) {
			$diag_data[$patient->patient_id] = $patient->diagnosis;
			$diag_arr[$patient->diagnosis] = '';
			//$arr["Diagnosis"][$patient->diagnosis] = '';
		}
		//foreach ($patient_attrs as $patient_attr)
			//$attr_list[$patient_attr->display_name] = $patient_attr->attr_id;
		
		foreach ($patient_data as $data) {
			$data->attr_name = trim($data->attr_name);
			$data->attr_value = trim($data->attr_value);			
			$data_list[$data->patient_id][$data->attr_name] = $data->attr_value;
			$attr_list[$data->attr_name] = '';
			$arr[$data->attr_name][$data->attr_value] = '';
		}
		
		$meta = array();

		if ($meta_list != null) {
			foreach ($meta_list as $key) {
				if ($key == "Diagnosis")
					$value = $diag_arr;
				else
					$value = $arr[$key];
				$vals = array_keys($value);
				sort($vals);
				$meta[$key] = $vals;
			}
		} else {			
			foreach ($arr as $key => $value) {
				$vals = array_keys($value);
				if (!$include_numeric) {
					$is_num = true;
					foreach ($vals as $v) {
						if (!is_numeric($v) && $v != "NoValue" && $v != "NA" && $v != "" && $v != "unknown") {
							$is_num = false;
							break;
						}
					}
					sort($vals);
					if (!$is_num)
						$meta[$key] = $vals;
				} else {
					$meta[$key] = $vals;
				}
			}
			if ($include_diagnosis) {
				$meta_list = array_merge(array("Diagnosis"), array_keys($meta));
			}
			else
				$meta_list = array_keys($meta);
		}

		$patient_list = array_keys($diag_data);
		$isFirst=0;		

		$values = array();

		/*
		if we only show the following meta:
		$meta_list = $this->getProperty("metadata_list");
		
		if ($meta_list != null) {
			//remove meta_data if not in list
			$meta_found_list = array();
			foreach ($meta_list as $meta) {
				if (in_array($meta, $attr_name_list))
					$meta_found_list[] = $meta;
			}
			//$attr_name_list = $meta_found_list;
		}
		*/
		foreach($patient_list as $patient_id) {
			$value_list = array();
			
			foreach($meta_list as $attr_name) {
				if ($attr_name == "Diagnosis")
					$value_list[] = $diag_data[$patient_id];
				else {
					if (isset($data_list[$patient_id][$attr_name]))				
						$value_list[] = $data_list[$patient_id][$attr_name];
					else
						$value_list[] = "NA";
				}
			}
			$values[$patient_id] = $value_list;
		}
		if ($include_diagnosis) {
			$meta["Diagnosis"] = array_keys($diag_arr);		
		}		
		return array("meta" => $meta, "data" => $values, "attr_list" => $meta_list);
	}
	public function getPatientDx($include_diagnosis = true) {
		$patients = DB::select("select distinct regexp_substr(p1.diagnosis,'[^/(,]+\w') as diagnosis from patients p1, project_patients p2 where p2.project_id=$this->id and p1.patient_id=p2.patient_id");

		$diag_data = array();

		foreach ($patients as $patient){
			if (!in_array($patient->diagnosis,$diag_data)){
				if (preg_match("/^([A-Z0-9\-\s]+\w)/i",$patient->diagnosis,$matches)){
					array_push($diag_data,$matches[0]);
				}
			}
		}
		sort($diag_data);
		return $diag_data;
	}

	static public function getNormalProject() {
		$prjs = Project::where('name','Normal')->get();
		if (count($prjs) == 0)
			return null;
		return $prjs[0];
	}

	static public function getFusionDiagnosisCount($project_id) {
		return DB::select("select diagnosis, count(distinct p1.patient_id) as patient_count from project_patients p1,var_fusion v where p1.project_id = $project_id and p1.patient_id=v.patient_id group by diagnosis order by diagnosis");
	}

	static public function getDiagnosisCount($project_id) {
		return DB::select("select diagnosis, count(distinct p1.patient_id) as patient_count from project_patients p1 where p1.project_id = $project_id group by diagnosis order by diagnosis");
	}

	public function getMutatedRNAseqSamples($gene_id) {
		$vars = array();
		$types = array();
		$rows = DB::select("select v.* from project_samples s, var_genes v where s.sample_id=v.rnaseq_sample and s.exp_type='RNAseq' and project_id=$this->id and v.gene='$gene_id'");

		foreach($rows as $row) {
			$vars[$row->rnaseq_sample][$row->type] = '';
			$types[$row->type] = '';
		}
		return array($vars, $types);
	}
	
	public function getGeneExpression($genes, $target_type = 'ensembl', $library_type = 'all', $target_level = 'gene', $include_meta = true, $tissue_cat = 'all', $value_type='tpm', $use_alias = true) {
		$library_where = "";
		$tissue_cat_condition = "";
		if ($tissue_cat != "all")
			$tissue_cat_condition = " and s2.tissue_cat = '$tissue_cat'";
		$sample_ids = array();
		$sql = "select distinct s2.* from project_samples s1, samples s2 where project_id=$this->id and s2.exp_type = 'RNAseq' and s1.patient_id=s2.patient_id and s1.sample_id=s2.sample_id $tissue_cat_condition $library_where";
		Log::info($sql);
		$samples = DB::select($sql);
		//$sample_id_mapping = array();
		$sample_names = array();
		$patients = array();
		foreach ($samples as $sample) {
			if ($use_alias) {
				$sample->sample_name = $sample->sample_alias;
			}
			$sample_id_mapping[$sample->sample_id] = $sample->sample_name;
			$patients[$sample->sample_name] = $sample->patient_id;
			//$sample_ids[] = $sample->sample_id;
			if (isset($sample_names[$sample->sample_name]))
				$sample->sample_name = $sample->sample_name."_2";
			$sample_names[] = $sample->sample_name;
			if ($sample->library_type != "polyA")
				$sample->library_type = "Ribozero or Access";
			$lib_types[$sample->library_type] = '';
		}		

		$gene_list = "'".implode("','", $genes)."'";
		$where_target = "";
		if (count($genes) > 1 || $target_level == 'gene')
			$where_target = " and target_level = 'gene'";
		$where_type = "";
		if ($target_type != "all")
			$where_type = " and target_type = '$target_type'";
		$sql = "select * from project_values where project_id=$this->id and value_type='$value_type' and (symbol in ('_list',$gene_list) or target in ('_list',$gene_list)) $where_target $where_type order by target_level";
		Log::info("$sql");
		$rows = DB::select($sql);
		$exp_data = array();
		$value_exp_data = array();
		$target_types = array();
		$lib_types = array();
		$target_list = array();
		$value_sample_ids = array();
		
		if (count($rows) == 0)
			return array();
		foreach ($rows as $row) {
			if ($row->symbol == "_list") {
				$value_sample_ids = explode(',',$row->value_list);
				foreach ($value_sample_ids as $sample_id) {
					if (array_key_exists($sample_id, $sample_id_mapping))
						$sample_ids[] = $sample_id;
				}
				break;
			}
		}

		foreach ($rows as $row) {
			if ($row->symbol != "_list") {
				$target_types[$row->target_type] = '';
				$target = $row->symbol;
				if ($row->target_level == 'trans')
					$target = $row->target;
				if ($target_type == 'all' || $target_type == $row->target_type) {
					$value_list = explode(',',$row->value_list);
					$filtered_value_list = array();
					for ($i=0; $i<count($value_list); $i++) {
						$sample_id = $value_sample_ids[$i];
						if (array_key_exists($sample_id, $sample_id_mapping))
							$filtered_value_list[] = round($value_list[$i], 2);
					}
					$exp_data[$target][$row->target_type] = $filtered_value_list;
				}
				$target_list[$row->target_type][] = array("id" => $target, "level" => $row->target_level);
			}
		}
		$sample_meta = array();
		if ($include_meta) {
			$sample_meta = $this->getSampleMetaData("RNAseq", "sample_alias", $tissue_cat);
			//$sample_meta = $this->getSampleMetaData("RNAseq", false);
			if (count($genes) == 1) {
				list($vars, $types) = $this->getMutatedRNAseqSamples($genes[0]);			
				foreach($types as $type => $dummy) {
					//print $type;					
					$sample_meta["attr_list"][] = ucfirst($type)." Mutation";
					foreach ($sample_ids as $sample_id) {
						$has_mut = (isset($vars[$sample_id][$type]))? 'Y' : 'N';
						$sample_name = (isset($sample_id_mapping[$sample_id]))? $sample_id_mapping[$sample_id]: '$sample_id';
						$sample_meta["data"][$sample_name][] = $has_mut;
					}
				}
			}
		}
		$sample_names = array();
		foreach ($sample_ids as $sample_id) {			
			if (isset($sample_id_mapping[$sample_id])) {
				$sample_name = $sample_id_mapping[$sample_id];				
				$sample_names[] = $sample_name;
			}
			else
				$sample_names[] = $sample_id;
		}		
		ksort($target_list);
		return array("patients" => $patients, "samples" => $sample_names, "sample_ids" => $sample_ids, "meta_data" => $sample_meta, "exp_data" => $exp_data, "target_list" => $target_list, "library_type" => array_keys($lib_types), "target_type" => array_keys($target_types));

	}

	public function getCNV($genes) {
		$gene_list = "'".implode("','", $genes)."'";
		if (!array_key_exists($gene_list, $this->cnv_gene_data)) {
			$sql = "select distinct p.patient_id, c.sample_id,c.sample_name,c.cnt,c.gene from project_cases p, var_cnv_gene_level c where project_id=$this->id and p.patient_id=c.patient_id and p.case_id=c.case_id and c.gene in ($gene_list)";
			Log::info($sql);
			$rows = DB::select($sql);
			$data = array();
			
			$patients = array();
			$patients = array();
			if (count($rows) == 0)
				return array();
			foreach ($rows as $row) {
				$patients[$row->sample_name] = $row->patient_id;
				$current_value = 0;
				if (array_key_exists($row->sample_name, $data) && array_key_exists($row->gene, $data[$row->sample_name])) {
					$current_value = $data[$row->sample_name][$row->gene];					
					if ($current_value < $row->cnt)
						$data[$row->sample_name][$row->gene] = $row->cnt;					
				}
				else
					$data[$row->sample_name][$row->gene] = $row->cnt;				
			}
			
			$sample_names = array_keys($patients);			
			$cnv_data = array();
			foreach ($genes as $gene) {
				$cnvs = array();
				foreach ($sample_names as $sample_name) {
					if (isset($data[$sample_name][$gene]))
						$cnvs[] = $data[$sample_name][$gene];
					else
						$cnvs[] = 'NA';					
				}
				$cnv_data[$gene] = $cnvs;	
			}
			$this->cnv_gene_data[$gene_list] = array("patients" => $patients, "samples" => $sample_names, "cnv_data" => $cnv_data );
		}						
		return $this->cnv_gene_data[$gene_list];

	}	

	static public function hasRNAseq($project_id) {
		$sql = "select count(*) as cnt from project_samples where project_id=$project_id and exp_type='RNAseq'";
		$rows = DB::select($sql);
		return ($rows[0]->cnt > 0);

	}

	static public function getGenoTypingPatients($project_id) {
		#$sql="select distinct patient_id,diagnosis from project_samples p where exists(select * from genotyping g where p.sample_id=g.sample1 or p.sample_id=g.sample2 or p.sample_name=g.sample1 or p.sample_name=g.sample2) and project_id=$project_id order by patient_id";
		$sql="select distinct patient_id,diagnosis from project_samples p,genotyping g where (p.sample_id=g.sample1 or p.sample_id=g.sample2 or p.sample_name=g.sample1 or p.sample_name=g.sample2) and project_id=$project_id order by patient_id";
		Log::info($sql);
		return DB::select($sql);

	}

	public function GenotypingByPatient($patient_id) {
		$sql="select s1.patient_id as Patient1,g.sample1, s1.tissue_type as Tissue1,s1.exp_type as exp_type1, s2.patient_id as Patient2,g.sample2, s2.tissue_type as Tissue2,s2.exp_type as exp_type2,percent_match from genotyping g,project_samples s1,project_samples s2 where (g.sample1=s1.sample_id or g.sample1=s1.sample_name) and (g.sample2=s2.sample_id or g.sample2=s2.sample_name) and s1.project_id=$this->id and s2.project_id=$this->id and (s1.patient_id='$patient_id' or s2.patient_id='$patient_id')";
		return DB::select($sql);

	}

	public function getMatchedGenotyping($cutoff=0.75) {
		$sql="select s1.patient_id as Patient1,g.sample1, s1.tissue_type as Tissue1,s1.exp_type as exp_type1, s2.patient_id as Patient2,g.sample2, s2.tissue_type as Tissue2,s2.exp_type as exp_type2,percent_match  from genotyping g,project_samples p,samples s1,samples s2 where p.project_id=$this->id and g.percent_match >= $cutoff and (p.sample_id=g.sample1 or p.sample_id=g.sample2) and s1.sample_id=g.sample1 and s2.sample_id=g.sample2";
		$sql="select s1.patient_id as Patient1,g.sample1, s1.tissue_type as Tissue1,s1.exp_type as exp_type1, s2.patient_id as Patient2,g.sample2, s2.tissue_type as Tissue2,s2.exp_type as exp_type2,percent_match from genotyping g,project_samples s1,project_samples s2 where (g.sample1=s1.sample_id or g.sample1=s1.sample_name) and (g.sample2=s2.sample_id or g.sample2=s2.sample_name) and s1.project_id=$this->id and s2.project_id=$this->id and g.percent_match >= $cutoff";
		return DB::select($sql);

	}

	static public function getCount() {	
		$logged_user = User::getCurrentUser();
		if ($logged_user != null)
			return DB::select("select count(distinct project_id) as cnt from user_projects where user_id = $logged_user->id")[0]->cnt;
		return 0;
	}
	static public function get_project_sampleBy_Paient($patient_id){
		$sql="select distinct sample_id,sample_name,tissue_cat,tissue_type,library_type,platform,material_type,exp_type from project_samples where patient_id='$patient_id'";
		return DB::select($sql);
	}
	static public function getAll($include_details=true) {
		if ($include_details)
			/*
			$sql = "select distinct * from 
				(select p1.id, p1.name, p1.description, p1.ispublic, 
				  (select count(distinct patient_id) from project_patients s where p1.id=s.project_id) as patients,
				  (select count(distinct patient_id) from project_processed_cases s where p1.id=s.project_id) as processed_patients,
				  (select count(distinct c1.patient_id) from project_patients c1, patient_details c2 where p1.id=c1.project_id and c1.patient_id=c2.patient_id and class='overall_survival') as Survival,
				  (select count(distinct sample_id) from processed_sample_cases c1 where exists(select * from project_processed_cases c2 where c1.patient_id=c2.patient_id and c1.case_name=c2.case_name and c2.project_id=p1.id) and c1.exp_type='Exome') as Exome,
				  (select count(distinct sample_id) from processed_sample_cases c1 where exists(select * from project_processed_cases c2 where c1.patient_id=c2.patient_id and c1.case_name=c2.case_name and c2.project_id=p1.id) and c1.exp_type='Panel') as Panel,
				  (select count(distinct sample_id) from project_samples c1 where c1.project_id=p1.id and c1.exp_type='RNAseq') as RNAseq,
				  (select count(distinct sample_id) from processed_sample_cases c1 where exists(select * from project_processed_cases c2 where c1.patient_id=c2.patient_id and c1.case_name=c2.case_name and c2.project_id=p1.id) and c1.exp_type='Whole Genome') as Whole_Genome,	  
				  status, p1.user_id, u.email as created_by, to_char(p1.updated_at, 'YYYY/MM/DD') as Updated_at
					 from projects p1 left join users u on p1.user_id=u.id,project_samples p2 where p1.id=p2.project_id and p1.isstudy=1) projects where (RNAseq is not null or Whole_Genome is not null or Exome is not null or Panel is not null or Panel is not null or Whole_Genome is not null)";
			*/
			$sql = "select * from project_mview p where (1=1)";
		else
			$sql = "select * from projects p where (1=1)";
		//if (User::accessAll()) 
		$user_where = "";
		//else {
		if (!User::isSuperAdmin()) {
			$logged_user = User::getCurrentUser();
			if ($logged_user != null)
				$user_where = " and exists (select * from user_projects u where u.project_id=p.id and u.user_id=". $logged_user->id.")";
			else
				$user_where = " and p.ispublic='1'";
		}
		//}
		$sql .= $user_where;
		\Log::info($sql);
		return \DB::select($sql);
	}

	static public function getProject($project_id) {
		if ($project_id == "any")
			return null;			
		return Project::find($project_id);
	}

	static public function getProjectInfo($project_id) {
		if ($project_id == "any")
			return null;
		$sql = "select * from project_mview where id=$project_id";
		Log::info($sql);			
		$projects = DB::select($sql);
		Log::info("Done");
		if (count($projects) == 0)
			return null;
		return $projects[0];
	}

	static public function getProjectByName($name) {
		$projects = DB::select("select * from projects where upper(name)='".strtoupper($name)."'");
		if (count($projects) == 0)
			return null;
		return Project::getProject($projects[0]->id);
	}

	static public function getSamples($project_id) {
		return DB::select("select distinct * from project_samples where project_id=$project_id");
	}

	static public function getCases($project_id) {
		return DB::select("select distinct c.* from cases c, project_patients p where c.patient_id=p.patient_id and project_id=$project_id order by c.patient_id, c.case_name");
	}

	static public function getProcessedCases($project_id, $patient_id, $case_name=null) {
		$case_condition = '';
		if ($case_name != null && $case_name != "any")
			$case_condition = "and case_name='$case_name'";
		return DB::select("select distinct * from project_processed_cases where project_id=$project_id and patient_id='$patient_id' $case_condition");		
	}

	static public function getSampleCases($project_id)
    {
        return DB::select("select distinct p.*,c.case_id,c.path from project_samples p, cases c where project_id=$project_id and p.patient_id=c.patient_id");
    }

	static public function getPatients($project_id) {
		return DB::select("select distinct p1.* from patients p1, project_patients p2 where p1.patient_id=p2.patient_id and project_id=$project_id order by p1.patient_id");
	}
		
	static public function totalPatients($project_id) {
		$pateint_cnts = DB::table("project_patient_summary")->where('project_id', $project_id)->get();
		//$pateint_cnts = DB::select("select * from project_patient_summary where project_id = $project_id"); 
		if (count($pateint_cnts) > 0)
			return $pateint_cnts[0]->patients;
		return 0;
	}

	static public function totalPatientsGroupByDiagnosis($project_id) {
		return DB::select("select project_id, diagnosis, count(*) as patient_count from (select distinct project_id, diagnosis, p1.patient_id from project_patients p1, var_cases c where p1.patient_id=c.patient_id) group by project_id, diagnosis having project_id=$project_id");
	}

	static public function getFusionPatientCount($project_id ) {
		$time_start = microtime(true);		
		$sql = "select left_chr, left_gene, right_chr, right_gene, count(distinct p.patient_id) as cnt from var_fusion v, project_cases p where p.patient_id=v.patient_id and p.case_id=v.case_id and project_id=$project_id group by left_chr, left_gene, right_chr, right_gene";
		log::info("getFusionPatientCount: " . $sql);
		$rows = DB::select($sql);
		$time = microtime(true) - $time_start;
		Log::info("execution time (getFusionPatientCount)): $time seconds");
		return $rows;
	}

	static public function getFusionProjectDetailByDiagnosis($project_id, $fusion_table="var_fusion", $diagnosis=null, $cutoff=2) {
		$table = ($diagnosis == null || $diagnosis == "null")? "project_fusion" : "project_diagnosis_fusion";
		$diag_condition = ($diagnosis == null || $diagnosis == "null")? "" : "and diagnosis='$diagnosis'";
		$cutoff_condition = "and count >= $cutoff";		
		#$sql = "select v.patient_id, left_chr, left_gene, right_chr, right_gene, var_level, type from $fusion_table v, project_patients p where v.patient_id=p.patient_id and p.project_id=$project_id $diag_condition";
		$sql = "select * from $table where project_id=$project_id $diag_condition $cutoff_condition";
		log::info("getFusgetFusionProjectDetailByDiagnosisionProjectDetail: " . $sql);
		return DB::select($sql);
	}

	static public function getFusionProjectDetail($project_id, $group_field, $value = null, $include_patient_list = false, $fusion_table="var_fusion") {
		$value_condition = ($value == null)? "" : "and var_level='$value'";
		$patient_list_field = "";
		$db_type = Config::get("site.db_connection");
		Log::info("DB type: $db_type");
		if ($include_patient_list) {
			if ($db_type == "oracle")
				$patient_list_field = ", listagg(patient_id,',') within group( order by patient_id ) as patient_list";
			if ($db_type == "mysql")
				$patient_list_field = ",group_concat(patient_id, ',') as patient_list";		
		}		
		$sql = "select left_chr, left_gene, right_chr, right_gene, count(*) as cnt, $group_field $patient_list_field from (select distinct v.patient_id, left_chr, left_gene, right_chr, right_gene, $group_field from $fusion_table v, project_cases p where p.patient_id=v.patient_id and p.case_id=v.case_id and p.project_id=$project_id $value_condition) f group by left_chr, left_gene, right_chr, right_gene, $group_field";
		log::info("getFusionProjectDetail: " . $sql);
		return DB::select($sql);
	}

	static public function getFusionGenes($project_id, $left_gene, $right_gene = null, $type = null, $value = null) {
		$type_condition = "";
		$project_condition = "";
		if ($type != null) {
			$type_condition = " and $type = '$value'";
			if (strtolower($value) == 'no tier')			
				$type_condition = " and $type is null";
		}
		if ($project_id != "all" && $project_id != "any") {
			$project_condition = " and p1.project_id = $project_id";
		} else {
			$logged_user = User::getCurrentUser();
			$project_condition = " and exists(select * from user_projects up where p1.project_id=up.project_id and up.user_id=$logged_user->id)";
		}
		$fusion_field_list = "f.case_id,f.patient_id,left_gene,right_gene,left_chr,left_position,right_chr,right_position,f.sample_id,tool,type,var_level,left_region,right_region,left_trans,right_trans,left_sanger,right_sanger,left_cancer_gene,right_cancer_gene";
		if ($right_gene == null)
			$sql = "select '' as plot, '' as igv, diagnosis, $fusion_field_list from var_fusion f,patients p where exists(select * from project_cases p1 where f.patient_id = p1.patient_id and f.case_id=p1.case_id and p.patient_id=p1.patient_id $project_condition) and f.patient_id=p.patient_id and (left_gene = '$left_gene' or right_gene = '$left_gene') $type_condition";		
		else
			$sql = "select '' as plot, '' as igv, diagnosis, $fusion_field_list from var_fusion f,patients p where exists(select * from project_cases p1 where f.patient_id = p1.patient_id and f.case_id=p1.case_id and p.patient_id=p1.patient_id $project_condition) and f.patient_id=p.patient_id and left_gene = '$left_gene' and right_gene = '$right_gene' $type_condition";
		Log::info($sql);
		return DB::select($sql);
	}

	function getExpressionSamples($target_type="ensembl", $value_type ="tpm") {
		$sample_list = array();
		$sql = "select value_list from project_values where project_id=$this->id and symbol = '_list' and value_type='$value_type' and target_type='$target_type'";
		$rows = DB::select($sql);
		if (count($rows) > 0) {
			$value_list = $rows[0]->value_list;
			$sample_list = explode(",", $value_list);			
		}
		return $sample_list;
	}

	public function getSurvivalDiagnosis() {
		$diags = array();
		$rows = DB::select("select distinct s.diagnosis from patient_details p, project_samples s where s.project_id=$this->id and s.patient_id=p.patient_id and class='survival_status' order by s.diagnosis desc");
		foreach ($rows as $row)
			$diags[] = $row->diagnosis;
		return $diags;
	}

	public function hasSurvivalSample() {
		$surv_col_name = 'overall_survival';
		//$surv_col_name = 'event_free_survival';		
		$surv_status_col_name = 'survival_status';
		$sql_survival = "select distinct s.sample_id,p.attr_value,p.class from patient_details p, project_samples s where s.project_id=$this->id and s.patient_id=p.patient_id and (p.class='$surv_col_name' or p.class='$surv_status_col_name')";		
		$surv_rows = DB::select($sql_survival);
		return count($surv_rows) > 0;
	}

	static public function getMutationGeneList($project_id, $tier="Tier 1") {
		$sql = "select distinct v.gene from var_tier_avia v,project_patients p where v.patient_id=p.patient_id and p.project_id=$project_id and (v.somatic_level like '${tier}%' or v.germline_level like '${tier}%') order by v.gene";
		$genes = array();
		$rows = DB::select($sql);
		foreach ($rows as $row)
			$genes[] = $row->gene;
		return $genes;
	}

	static public function getFusionGenesList($project_id, $tier="1") {
		$sql = "select distinct left_gene,right_gene from var_fusion v, project_samples p where p.project_id=27343 and p.sample_id=v.sample_id and var_level like '${tier}%' order by left_gene,right_gene";
		$genes = array();
		$rows = DB::select($sql);
		foreach ($rows as $row)
			$genes[] = "$row->left_gene - $row->right_gene";
		return $genes;
	}

	public function getSurvivalFile($data_type, $filter_attr_name1, $filter_attr_value1, $filter_attr_name2, $filter_attr_value2, $group_by1, $group_by2="not_used", $group_by_values=null) {
		$surv_col_name = $data_type."_survival";
		$surv_status_col_name = 'survival_status';
		if ($data_type == "event_free") {		
			$surv_status_col_name = 'first_event';
		}
		$project_dir = storage_path()."/project_data/".$this->id;
		if(!is_dir($project_dir))
			mkdir($project_dir);
		$surv_dir = "$project_dir/survival";
		if(!is_dir($surv_dir))
			mkdir($surv_dir);

		$surv_file = "survival_data.$data_type.$filter_attr_name1.$filter_attr_value1.$filter_attr_name2.$filter_attr_value2.$group_by1.$group_by2.tsv";		
		if ($group_by1 == "mutation")
			$surv_file = "survival_data.$data_type.$filter_attr_name1.$filter_attr_value1.$filter_attr_name2.$filter_attr_value2.$group_by1.$group_by_values.tsv";
		$surv_file = str_replace(" ", "", $surv_file);
		$surv_file = str_replace("(", "", $surv_file);
		$surv_file = str_replace(")", "", $surv_file);
		$surv_file = str_replace("/", "", $surv_file);
		$surv_file = "$surv_dir/$surv_file";

		Log::info($surv_file);
		//if (file_exists($surv_file))
		//	return $surv_file;
		$subquery1 = "";	
		$filter_attr_value1	= str_replace("'", "''", $filter_attr_value1);
		$filter_attr_value2	= str_replace("'", "''", $filter_attr_value2);
		if ($filter_attr_name1 == "any")
			$subquery1 = "select * from project_patients p2 where p1.patient_id=p2.patient_id and p2.project_id = $this->id";
		else if (strtolower($filter_attr_name1) == "diagnosis")
			$subquery1 = "select * from project_patients p2,patients p3 where p1.patient_id=p2.patient_id and p2.project_id = $this->id and p1.patient_id=p3.patient_id and p3.diagnosis='$filter_attr_value1'";
		else {
			$subquery1 = "select * from project_patients p2,patient_details p3 where p1.patient_id=p2.patient_id and p2.project_id = $this->id and p1.patient_id=p3.patient_id and p3.attr_name='$filter_attr_name1' and p3.attr_value='$filter_attr_value1'";
		}
		$subquery2 = "";
		if ($filter_attr_name2 == "any")
			$subquery2 = "select * from project_patients p5 where p1.patient_id=p5.patient_id and p5.project_id = $this->id";
		else if (strtolower($filter_attr_name2) == "diagnosis")
			$subquery1 = "select * from project_patients p5,patients p6 where p1.patient_id=p5.patient_id and p5.project_id = $this->id and p1.patient_id=p6.patient_id and p3.diagnosis='$filter_attr_value2'";
		else {
			$subquery2 = "select * from project_patients p5,patient_details p6 where p1.patient_id=p5.patient_id and p5.project_id = $this->id and p1.patient_id=p6.patient_id and p6.attr_name='$filter_attr_name2' and p6.attr_value='$filter_attr_value2'";
		}

		$values = array();
		if ($group_by1 == "mutation") {
			$arr = explode(":",$group_by_values);
			$tier = $arr[0];
			$tier_type = $arr[1];
			$gene1 = strtoupper($arr[2]);
			$relation = $arr[3];
			$gene2 = strtoupper($arr[4]);
			if ($gene2 == "ANY")
				$gene2 = "";
			$gene_condition = "v.gene='$gene1'";
			if ($gene2 != "" && $gene2 != "any")
				$gene_condition = "(v.gene='$gene1' or v.gene='$gene2')";
			$tier_condition = "";
			if ($tier != "all_tier") {
				$tier_string = "like 'Tier 1%'";
				$tier_op = "or";
				if ($tier == "other_tier") {
					$tier_string = "not like 'Tier 1%'";
					$tier_op = "and";
				}
				if ($tier_type == "germline")
					$tier_condition = "and v.germline_level $tier_string";
				if ($tier_type == "somatic")
					$tier_condition = "and v.somatic_level $tier_string";
				if ($tier_type == "germline_somatic")
					$tier_condition = "and (v.germline_level $tier_string $tier_op v.somatic_level $tier_string)";
			}
			
			//"(v.somatic_level like 'Tier 1%' or v.germline_level like 'Tier 1%')"
			$sql_value = "select distinct v.patient_id,v.gene from var_tier_avia v,project_patients p where v.patient_id=p.patient_id and p.project_id=$this->id $tier_condition and $gene_condition";
			Log::info($sql_value);
			$value_rows = DB::select($sql_value);
			$patient_genes = array();
			foreach ($value_rows as $row)	
				$patient_genes[$row->patient_id][$row->gene] = "";
			
			$patient_ids = array_keys($patient_genes);

			foreach ($patient_ids as $patient_id) {
				$has_gene1 = (isset($patient_genes[$patient_id][$gene1])? 1: 0);
				$has_gene2 = (isset($patient_genes[$patient_id][$gene2])? 1: 0);
				if ($gene2 == "")
					$values[$patient_id] = (($has_gene1)? 'Y': 'N');
				else if ($relation == "and")
					$values[$patient_id] = (($has_gene1 && $has_gene2)? 'Y': 'N');
				else if ($relation == "or")
					$values[$patient_id] = (($has_gene1 || $has_gene2)? 'Y': 'N');
				else if ($relation == "andNot")
					$values[$patient_id] = (($has_gene1 && !$has_gene2)? 'Y': 'N');				
			}
		} elseif ($group_by1 == "fusion") {
			$arr = explode(" - ",$group_by_values);
			$left_gene = $arr[0];
			$right_gene = $arr[1];
			$tier = "1";
			$sql_value = "select distinct v.patient_id,left_gene,right_gene,var_level from project_samples p, var_fusion v where p.project_id=$this->id and p.sample_id=v.sample_id";
			Log::info($sql_value);
			$value_rows = DB::select($sql_value);
			$patient_genes = array();
			foreach ($value_rows as $row) {
				$current_tier = 100;
				$t = (float)substr($row->var_level, 0, 1);
				if (isset($patient_genes[$row->patient_id][$row->left_gene][$row->right_gene]))
					$current_tier = $patient_genes[$row->patient_id][$row->left_gene][$row->right_gene];
				if ($current_tier && $current_tier <= $t)
					continue;
				$patient_genes[$row->patient_id][$row->left_gene][$row->right_gene] = $t;
			}
			$patient_ids = array_keys($patient_genes);

			foreach ($patient_ids as $patient_id) {
				$values[$patient_id] = (isset($patient_genes[$patient_id][$left_gene][$right_gene])? 'Y' : 'N');
			}

		} else {			
			$groups = array($group_by1, $group_by2);
			foreach ($groups as $group) {
				if ($group == "not_used")
					continue;
				if (strtolower($group) == "diagnosis") {
					$sql_value = "select distinct patient_id, diagnosis from project_patients p where project_id=$this->id";
					$value_rows = DB::select($sql_value);
					foreach ($value_rows as $row) {
						if (isset($values[$row->patient_id]))
							$values[$row->patient_id] = $values[$row->patient_id]."_".$row->diagnosis;
						else
							$values[$row->patient_id] = $row->diagnosis;
					}
				} else {
					$sql_value = "select distinct d.patient_id,attr_value from patient_details d,project_patients p where d.patient_id=p.patient_id and p.project_id=$this->id and d.attr_name='$group'";
					Log::info($sql_value);
					$value_rows = DB::select($sql_value);
					foreach ($value_rows as $row) { 	
						//$values[$row->patient_id] = $row->attr_value;
						$v = (trim($row->attr_value) == "")? "N/A" : trim($row->attr_value);
						if (isset($values[$row->patient_id]))
							$values[$row->patient_id] = $values[$row->patient_id]."_".$v;
						else
							$values[$row->patient_id] = $v;
					}
				}
			}
		}

		$sql_survival = "select distinct p1.patient_id, p1.attr_name,p1.attr_value,p1.class from patient_details p1 where exists(".$subquery1.") and exists(".$subquery2.") and (p1.class='$surv_col_name' or p1.class='$surv_status_col_name')";
		Log::info($sql_survival);
		$surv_rows = DB::select($sql_survival);
		
		$survival = array();
				
		foreach ($surv_rows as $row) {
			$value = $row->attr_value;
			if ($row->class == $surv_status_col_name) {
				$status = (strtolower($value) == "dead" || strtolower($value) == "event" || $value == "1")? 1 : 0;
				if ($data_type == "event_free")
					$status = (strtolower($value) == "censored" || strtolower($value) == "none" || $value == "0")? 0 : 1;
				$value = $status;
			}
			if ($row->class == $surv_col_name && $value == "0")
				continue;
			$survival[$row->patient_id][$row->class] = $value;
		}
		$patient_ids = array_keys($survival);
		sort($patient_ids);
		$surv_data = "Patient ID\tTime\tStatus\tValue";
		foreach ($patient_ids as $patient_id) {
			if (!isset($survival[$patient_id][$surv_col_name]) || !isset($survival[$patient_id][$surv_status_col_name]))
				continue;
			$survival_time = $survival[$patient_id][$surv_col_name];

			$survival_status = $survival[$patient_id][$surv_status_col_name];
			$value = "N/A";
			if (!isset($values[$patient_id])) {
				if ($group_by1 == "mutation")
					$value = "N";
				else
					$value = "N/A";
			}
			else
				$value = $values[$patient_id];
			$surv_data.="\n$patient_id\t$survival_time\t$survival_status\t$value";
		}

		$bytes_written = File::put($surv_file, $surv_data);
		return $surv_file;
	}
	

	public function getExpSurvivalFile($target, $target_type, $target_level="gene", $data_type="overall", $value_type="tmm-rpkm", $diagnosis="any") {		 
		$surv_col_name = $data_type."_survival";
		$surv_status_col_name = 'survival_status';
		if ($data_type == "event_free") {		
			$surv_status_col_name = 'first_event';
		}

		$project_dir = storage_path()."/project_data/".$this->id;
		if(!is_dir($project_dir))
			mkdir($project_dir);
		$surv_dir = "$project_dir/survival";
		if(!is_dir($surv_dir))
			mkdir($surv_dir);

		$surv_file = "survival_data.$target.$target_type.$data_type.$value_type.$diagnosis.tsv";
		$surv_file = str_replace(" ", "", $surv_file);
		$surv_file = str_replace("(", "", $surv_file);
		$surv_file = str_replace(")", "", $surv_file);
		$surv_file = str_replace("/", "", $surv_file);
		$surv_file = "$surv_dir/$surv_file";
		//if (file_exists($surv_file))
		//	return $surv_file;
		$sql_exp = "select * from project_values where (symbol='$target' or symbol='_list') and value_type='$value_type' and target_type='$target_type' and target_level='$target_level' and project_id= ".$this->id;
		$diag_condition = "";
		if ($diagnosis != "any")
			$diag_condition = " and s.diagnosis='$diagnosis'";
		$sql_survival = "select distinct p.patient_id, s.sample_id,p.attr_value,p.class from patient_details p, project_samples s where s.project_id=$this->id and s.patient_id=p.patient_id $diag_condition and (p.class='$surv_col_name' or p.class='$surv_status_col_name') and attr_value is not null";
		Log::info($sql_exp);
		Log::info($sql_survival);
		$surv_rows = DB::select($sql_survival);
		$survival = array();
		
		$patient_ids = array();
		foreach ($surv_rows as $row) {
			$survival[$row->sample_id][$row->class] = $row->attr_value;
			$patient_ids[$row->sample_id] = $row->patient_id;
		}
		$exp_rows = DB::select($sql_exp);
		$exp_data = array();
		$sample_ids = array();
		foreach ($exp_rows as $row) {
			if ($row->symbol == "_list")
				$sample_ids = explode(',',$row->value_list);
			else
				$exp_data = explode(',',$row->value_list);				
		}
		
		if (count($sample_ids) < 10)
			return null;
		$surv_data = "Sample ID \tPatient ID\tTime\tStatus\tExp";
		//calculate median
		/*
		$arr = array();
		for ($i=0;$i<count($sample_ids);$i++) {
			$sample_id = $sample_ids[$i];
			$exp_value = 0;			
			if (isset($exp_value))
				$exp_value = $exp_data[$i];
			$exp_value = round(log($exp_value + 1, 2), 4);
			if (isset($survival[$sample_id][$surv_status_col_name])) {
				$arr[] = $exp_value;
			}
		}
		$median = Utility::getMedian($arr);
		Log::info(json_encode($arr));
		Log::info(json_encode($median));
		*/
		for ($i=0;$i<count($sample_ids);$i++) {
			$sample_id = $sample_ids[$i];
			$exp_value = 0;			
			if (isset($exp_data[$i]))
				$exp_value = $exp_data[$i];
			$exp_value = round(log($exp_value + 1, 2), 4);
			if (isset($survival[$sample_id][$surv_status_col_name]) && isset($survival[$sample_id][$surv_col_name])) {
				//$time = isset($survival[$sample_id][$surv_col_name])? $survival[$sample_id][$surv_col_name] : "Inf";
				$time = $survival[$sample_id][$surv_col_name];
				$patient_id = $patient_ids[$sample_id];
				$status = (strtolower($survival[$sample_id][$surv_status_col_name]) == "dead" || $survival[$sample_id][$surv_status_col_name] == "1")? 1 : 0;
				if ($data_type == "event_free")
					$status = (strtolower($survival[$sample_id][$surv_status_col_name]) == "censored" || strtolower($survival[$sample_id][$surv_status_col_name]) == "none" || $survival[$sample_id][$surv_status_col_name] == "0")? 0 : 1;
				if ($time > 0)
					$surv_data.="\n$sample_id\t$patient_id\t$time\t$status\t".$exp_value;
			}

			
		}
		$bytes_written = File::put($surv_file, $surv_data);
		return $surv_file;
	}

	public function getSurvivalPvalueFile($type="overall") {
		$files = glob(storage_path()."/project_data/$this->id/survival/${type}_pvalues.*.tsv");
		return $files;
	}

	public function getProjectStat($target_type = "refseq", $value_type="tmm-rpkm") {
		$sql = "select p.*,g.symbol from project_stat p, gene g where project_id = $this->id and p.target_type='$target_type' and p.target=g.gene and g.type='protein-coding' and p.target_level='gene' and p.value_type='$value_type'";
		Log::info("getProjectStat SQL: $sql");
		return DB::select($sql);
	}

	public function getAllExpression($target_level="gene", $target_type="refseq") {
		return DB::select("select symbol, value_list from project_values p where project_id = $this->id and p.target_level='$target_level' and p.target_type='$target_type'");
	}

	private function getExprFileName($target_level="gene", $target_type="refseq") {
		return storage_path()."/project_data/".$this->id."/$target_type-$target_level-coding.tsv";
	}

	public function getCorrelation($symbol, $cutoff, $target_type="refseq", $method="pearson", $value_type="tmm-rpkm") {
		$starttime = microtime(true);
		#$file_type = ($value_type == "tmm-rpkm")? "" : ".$value_type";
		$project_dir = storage_path()."/project_data/$this->id";
		if (!file_exists($project_dir))
			system("mkdir $project_dir");
		$cor_dir = "$project_dir/cor";
		if (!file_exists($cor_dir))
			system("mkdir $cor_dir");
		$gene = Gene::getGene($symbol);
		$gene_id = $gene->getEnsemblID();
		$cor_file = "$cor_dir/expression.coding.$method.$value_type.$gene_id.tsv";
		$rds_file = "$project_dir/expression.coding.$value_type.RDS";
		#$rds_file = "$project_dir/$target_type-gene-coding$file_type.tsv";
		if (!file_exists($cor_file)) {
			$cmd = "Rscript ".app_path()."/scripts/calculateCorr.r $rds_file $gene_id $cor_file $method";
			Log::info("======== calculating correlation ========");
			Log::info($cmd);
			Log::info("=========================================");
			system($cmd);
		}
		if (!file_exists($cor_file)) {
			$cmd = "Rscript ".app_path()."/scripts/calculateCorr.r $rds_file $symbol $cor_file $method";
			Log::info("======== calculating correlation using Symbol========");
			Log::info($cmd);
			Log::info("=========================================");
			system($cmd);
		}
		$content = file_get_contents($cor_file);
		$lines = explode("\n", $content);
		$corr_n = array();
		$corr_p = array();
		foreach ($lines as $line) {
			$fields = explode("\t", $line);
			if (count($fields) == 2) {
				$symbol = $fields[0];
				//Log::info($symbol);
				$value = $fields[1];
				if ($symbol == $gene_id || $symbol == "NA")
					continue;
				if ($value > 0)
					$corr_p[$symbol] = number_format((float)$value, 3);
				else
					$corr_n[$symbol] = number_format((float)$value, 3);
			}
		}
		$endtime = microtime(true);
		$timediff = $endtime - $starttime;
		Log::info("execution time (calculateCorrelation): $timediff seconds");
		return array($corr_p, $corr_n);
	}

	public function getCorrelationOld($gene_id, $cutoff, $target_type="refseq") {
		$starttime = microtime(true);
		$gene_stat = $this->getProjectStat($target_type);
		$exps = array();
		$std = array();
		$mean = array();
		$median = array();
		$symbols = array();
		$target = $gene_id;
		foreach ($gene_stat as $row) {
			if ($row->symbol == $gene_id)
				$target = $row->target;
			$symbols[$row->target] = $row->symbol;
			$mean[$row->target] = $row->mean - $row->median;
			$std[$row->target] = $row->std;
			$median[$row->target] = $row->median;
		}

		$endtime = microtime(true);		
		$timediff = $endtime - $starttime;
		Log::info("execution time (getProjectStat): $timediff seconds");
		$starttime = microtime(true);

		//read expression from file
		$exp_file = $this->getExprFileName('gene', $target_type);
		//$exp_file = "/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics_dev/app/storage/data/ensembl-gene-coding.tsv";
		if (!file_exists($exp_file)) {
			return null;
		}
		$handle = fopen($exp_file, "r");
		if ($handle) {
			$header = fgets($handle);
			$samples = explode("\t", $header); 
			while (($line = fgets($handle)) != false) {
			//while ($line = stream_get_line($handle,4048,"\n")) {
				$fields = explode("\t", $line);
				$gene = $fields[0];		
				if (!isset($median[$gene]))
					continue;
				/*$expressed = 0;
				for ($i=1;$i<count($fields);$i++) {
					if ($fields[$i] > 0) {
						$expressed = 1;
						break;
					}
				}
				*/
				$expressed = 1;
				if ($expressed || $gene == $target) {
					for ($i=1;$i<count($fields);$i++) {
						#$exps[$gene][] = $fields[$i];

						$exp_value = round(log($fields[$i]+1, 2), 2);
						#$exp_value = $fields[$i];
						$exps[$gene][] = $exp_value - $median[$gene];
					}
				}
			}
			fclose($handle);
		} else {
			return null;
		}
 	
		if (count($mean) == 0) {
			return null;
		}		

/*
		$project_values = $this->getAllExpression("gene", $target_type);
		return;
		foreach ($project_values as $project_value) {
			if (!isset($median[$project_value->symbol]))
				continue;
			$values = explode(',', $project_value->value_list);
			$exps[$project_value->symbol] = array();
			$median_value = $median[$project_value->symbol];
			foreach ($values as $value)
				$exps[$project_value->symbol][] = $value - $median_value;			
		}
*/

		$target_gene_mean = $mean[$target];
		$target_gene_std = $std[$target];
		$target_gene_exp = $exps[$target];

		$genes = array_keys($exps); 
		$corr_n = array();
		$corr_p = array();

		$endtime = microtime(true);
		$timediff = $endtime - $starttime;
		Log::info("execution time (getExprFileName): $timediff seconds");
		$starttime = microtime(true);

		foreach ($genes as $gene) {
			$symbol = $symbols[$gene];
			if ($gene_id == $symbol) continue;
			if ($std[$gene] == 0) continue;
			$corr_coef = $this->calculateCorrelation($target_gene_exp, $exps[$gene], $target_gene_mean, $mean[$gene], $std[$gene], $target_gene_std);
			$symbol = $symbols[$gene];
			if ($corr_coef > 1)
				$corr_coef = 0;
			if ($corr_coef >= $cutoff)
				$corr_p["$gene,$symbol"] = number_format($corr_coef,3);
			if ($corr_coef <= $cutoff*(-1))
				$corr_n["$gene,$symbol"] = number_format($corr_coef,3);

		}

		$endtime = microtime(true);
		$timediff = $endtime - $starttime;
		Log::info("execution time (calculateCorrelation): $timediff seconds");
		return array($corr_p, $corr_n);
	}

	public function calculateCorrelation($exp1, $exp2, $mean1, $mean2, $std1, $std2) {        
		$correlation = 0;
      
		$sum = 0;
		$n = count($exp1);
		for ($i=0;$i<$n;$i++) {
			$sum += $exp1[$i] * $exp2[$i];
		}
		$correlation = ($sum - $mean1*$mean2*$n)/(($n-1)*$std1*$std2);
		return $correlation;
	}

	public function getTwoGenesExp($g1, $g2) {
		$table_name = $this->getGeneExprTableName();
		$sql = "select gene, e.sample_id, exp_value, s.tissue_type from $table_name e, study_samples s where s.study_id=".$this->id." and e.gene in ('$g1','$g2') and s.sample_id=e.sample_id";
      		$rows = DB::select($sql);
		
		$exprs = array();
		$samples = array();

		foreach ($rows as $row) {
			$exprs[$row->sample_id][$row->gene] = $row->exp_value;
			$samples[$row->sample_id] = $row->tissue_type;
		}
		return array($exprs, $samples);
   	}	

	public function hasVCF() {
		$pathToFile = storage_path()."/project_data/$this->id/$this->id.vcf.zip";
		Log::info($pathToFile);
		return file_exists($pathToFile);
	}

	public function hasVariantFile($type) {
		$pathToFile = storage_path()."/project_data/$this->id/variants/$this->id.$type.zip";		
		return file_exists($pathToFile);
	}

   	public function getVarCount() {
   		#$sql = "select count(*) as cnt,type from var_samples v, project_samples p where project_id=$this->id and p.patient_id=v.patient_id and v.sample_id=p.sample_id group by type order by type";
   		$sql = "select * from project_var_count where project_id = $this->id";
   		Log::info("getVarCount(sql): $sql");
   		$rows = DB::select($sql);
   		$var_count = array("germline" => 0, "somatic" => 0, "rnaseq" => 0, "variants" => 0);
   		if ($this->hasQCI())
   			$var_count = array("QCI"=>1, "germline" => 0, "somatic" => 0, "rnaseq" => 0, "variants" => 0);
   		foreach ($rows as $row) {
   			$var_count[$row->type] = $row->cnt;
   		}
   		Log::info("getVarCount(end)");
   		return $var_count;
   	}

   	public function hasBurden() {
   		if (!isset($this->has_burden))
   			$this->has_burden = (VarAnnotation::hasMutationBurden($this->id, "null", "null") > 0);
   		return $this->has_burden;
   	}

   	public function hasCNV() {
   		$rows = DB::select("select count(*) as cnt from project_processed_cases p where exists(select * from var_cnv v where p.patient_id=v.patient_id and p.case_id=v.case_id) and p.project_id=$this->id");
   		return ($rows[0]->cnt > 0);
   	}


   	public function hasQCI() {
   		$sql="select count(*) as cnt from var_qci_annotation q,project_cases p where q.patient_id=p.patient_id and q.case_id=p.case_id and p.project_id=$this->id";
		Log::info($sql);
		$rows = DB::select($sql);
		return ($rows[0]->cnt > 0);

   	}

   	public function getQCITypes() {
   		$sql="select distinct type from var_qci_annotation q,project_cases p where q.patient_id=p.patient_id and q.case_id=p.case_id and p.project_id=$this->id and type <> 'fusions'";
		Log::info($sql);
		$rows = DB::select($sql);
		$qci_types = array();
		foreach($rows as $row)
			$qci_types[] = $row->type;
		return $qci_types;

   	}

   	public function isCOMPASS() {
   		return ($this->ispublic == 9);
   	}
   	
   	public function getQCI($type) {
   		$table_name = VarAnnotation::getTableName();
   		$sql="select gene,q.qci_assessment,q.qci_actionability,count(distinct q.patient_id) as patient_count from var_qci_annotation q,$table_name a,project_cases p where q.patient_id=p.patient_id and q.case_id=p.case_id and p.project_id=$this->id and q.patient_id=a.patient_id and q.type='$type' and q.case_id=a.case_id and q.chromosome=a.chromosome and q.position=a.start_pos and q.ref not in ('fusion','rearrangement','gene_rearrangement') group by gene,qci_assessment,q.qci_actionability order by gene";
   		$sql="select * from var_qci_cohort where project_id=$this->id and type='$type' order by gene";
		Log::info($sql);
		return DB::select($sql);

   	}

	public function hasMixcr() {
		$sql = "select count(*) as cnt from project_cases p, mixcr_summary m where p.project_id=$this->id and p.patient_id=m.patient_id and p.case_id=m.case_id and m.count >=3 and m.chain like 'TR%'";

		Log::info($sql);
		$rows = DB::select($sql);
		return ($rows[0]->cnt > 0);
	}

	public function getMixcrFiles() {
		$fs = glob(storage_path()."/project_data/$this->id/mixcr/*.{zip,txt}", GLOB_BRACE);
		$files = array();
		foreach ($fs as $f) {
			$files[] = basename($f);
		}
		return $files;

	}

   	public function getMixcr($type="summary") {
		$table = ($type == "summary")? "mixcr_summary": "mixcr";
		$sql = "select m.* from project_cases p, $table m where p.project_id=$this->id and p.patient_id=m.patient_id and p.case_id=m.case_id and m.count >=3 and m.chain like 'TR%'";
		Log::info($sql);
		$rows = DB::select($sql);
		for ($i=0;$i<count($rows);$i++) {
			if ($type == "summary") {
				$rows[$i]->mean_frequency = round($rows[$i]->mean_frequency, 4);
				$rows[$i]->geomean_frequency = round($rows[$i]->geomean_frequency, 4);
				$rows[$i]->nc_frequency = round($rows[$i]->nc_frequency, 4);
				$rows[$i]->mean_cdr3nt_length = round($rows[$i]->mean_cdr3nt_length, 2);
				$rows[$i]->mean_insert_size = round($rows[$i]->mean_insert_size, 2);
				$rows[$i]->mean_ndn_size = round($rows[$i]->mean_ndn_size, 2);
			} else {
				$rows[$i]->freq = round($rows[$i]->freq, 4);
			}
		}
		
		return $rows;
	}

   	public function getVarGeneTier($type, $meta_type = "any", $meta_value="any", $annotation="AVIA", $maf=1, $min_total_cov=0, $vaf=0, $tier_table="var_tier_avia") {

   		$table_name = VarAnnotation::getTableName();

		$meta_from = "";
		$meta_condition = "";
		if (strtolower($meta_type) != "any") {
			if (strtolower($meta_type) == "diagnosis") {
				$meta_from = ",patients p2";
				$meta_condition = "p.patient_id=p2.patient_id and p2.diagnosis='$meta_value' and";
			} else {
				$meta_from = ",patient_details p2";
				$meta_condition = "p.patient_id=p2.patient_id and p2.attr_name='$meta_type' and p2.attr_value='$meta_value' and";
			}			
		}
		$fp_condition = "";
		$no_fp = true;
		if ($no_fp) {
			$fp_table = ($type == "rnaseq")? "rnaseq_fp" : "clinomics_fp";
			$type_condition = ($type == "rnaseq")? "" : " and f.type='$type'";
			$fp_condition = "not exists(select * from $fp_table f where t.chromosome=f.chromosome and t.start_pos=f.start_pos and t.end_pos=f.end_pos and t.ref=f.ref and t.alt=f.alt $type_condition) and";
		}
		//$tier_table = "var_tier_avia";		
		//$tier_table = ($annotation == "AVIA")? "var_tier_avia" : "var_tier";
		//$sample_alias = ($annotation == "AVIA")? "a" : "a";
		$sample_alias = "a";
		$tissue_cat_condition = '';
		/*
		if ($type=="germline")
			$tissue_cat_condition = "$sample_alias.tissue_cat='normal' and ";
		if ($type=="somatic")
			$tissue_cat_condition = "$sample_alias.tissue_cat='tumor' and ";
		*/
		$sqls = array();
		$types = array("germline", "somatic");
		$start_pos = 0;
		if (Config::get("site.db_connection") == "mysql")
			$start_pos = 1;
		foreach ($types as $t) {
				$sql = "select '$t' as tier_type, '$type' as type, gene, substr(${t}_level, $start_pos, 6) as tier, count(distinct t.patient_id) as cnt 
					from project_samples p, $tier_table t $meta_from where p.project_id=$this->id and p.patient_id=t.patient_id and p.sample_id=t.sample_id and t.type='$type' and
					$meta_condition
					$fp_condition
					$tissue_cat_condition
					(t.maf <= $maf or t.maf is null) and
					t.total_cov >= $min_total_cov and
					t.vaf >= $vaf					
					group by gene, substr(${t}_level, $start_pos, 6)";
				$sqls[$t] = $sql;
		}
		if ($annotation == "AVIA") {
			$germline_sql = "select 'germline' as tier_type, '$type' as type, gene, substr(germline_level, $start_pos, 6) as tier, count(distinct a.patient_id) as cnt 
					from $table_name a, project_samples p, $tier_table t $meta_from where p.project_id=$this->id and p.patient_id=a.patient_id and p.sample_id=a.sample_id and a.type='$type' and
					t.chromosome=a.chromosome and
					t.start_pos=a.start_pos and
					t.end_pos=a.end_pos and
					t.ref=a.ref and				
					t.alt=a.alt and 
					$meta_condition
					$fp_condition
					$tissue_cat_condition
					($sample_alias.maf <= $maf or $sample_alias.maf is null) and
					$sample_alias.total_cov >= $min_total_cov and
					$sample_alias.vaf >= $vaf and
					t.type='$type' and t.patient_id=a.patient_id and t.case_id=a.case_id 
					group by gene, substr(germline_level, $start_pos, 6)";			
			$somatic_sql = "select 'somatic' as tier_type, '$type' as type, gene, substr(somatic_level, $start_pos, 6) as tier, count(distinct a.patient_id) as cnt 
					from $table_name a, project_samples p, $tier_table t $meta_from where p.project_id=$this->id and p.patient_id=a.patient_id and p.sample_id=a.sample_id and a.type='$type' and
					t.chromosome=a.chromosome and
					t.start_pos=a.start_pos and
					t.end_pos=a.end_pos and
					t.ref=a.ref and
					t.alt=a.alt and 
					$meta_condition
					$fp_condition
					$tissue_cat_condition
					($sample_alias.maf <= $maf or $sample_alias.maf is null) and
					$sample_alias.total_cov >= $min_total_cov and
					$sample_alias.vaf >= $vaf and
					t.type='$type' and t.patient_id=a.patient_id and t.case_id=a.case_id
					group by gene, substr(somatic_level, $start_pos, 6)";
		} else {
			$germline_sql = "select 'germline' as tier_type, '$type' as type, gene, substr(germline_level, $start_pos, 6) as tier, count(distinct a.patient_id) as cnt 
					from var_sample_khanlab a, project_patients p, $tier_table t $meta_from where 
					p.project_id=$this->id and p.patient_id=a.patient_id and a.type='$type' and
					t.chromosome=a.chromosome and
					t.start_pos=a.start_pos and
					t.end_pos=a.end_pos and
					t.ref=a.ref and				
					t.alt=a.alt and 
					$meta_condition
					$fp_condition
					$tissue_cat_condition
					(a.frequency <= $maf or a.frequency is null) and
					$sample_alias.total_cov >= $min_total_cov and
					$sample_alias.vaf >= $vaf and
					t.type='$type' and t.patient_id=a.patient_id and t.case_id=a.case_id
					group by gene, substr(germline_level, $start_pos, 6)";
			$somatic_sql = "select 'somatic' as tier_type, '$type' as type, gene, substr(somatic_level, $start_pos, 6) as tier, count(distinct a.patient_id) as cnt 
					from var_sample_khanlab a, project_patients p, $tier_table t $meta_from where 
					p.project_id=$this->id and p.patient_id=a.patient_id and a.type='$type' and
					t.chromosome=a.chromosome and
					t.start_pos=a.start_pos and
					t.end_pos=a.end_pos and
					t.ref=a.ref and				
					t.alt=a.alt and  
					$meta_condition
					$fp_condition
					$tissue_cat_condition
					(a.frequency <= $maf or a.frequency is null) and
					$sample_alias.total_cov >= $min_total_cov and
					$sample_alias.vaf >= $vaf and
					t.type='$type' and t.patient_id=a.patient_id and t.case_id=a.case_id
					group by gene, substr(somatic_level, $start_pos, 6)";

		}
		if ($type == "germline")
			$sql = $sqls['germline'];
		else if ($type == "somatic")
			$sql = $sqls['somatic'];
		else
			$sql = $sqls['germline']." union ".$sqls['somatic'];
		Log::info($sql);
		return DB::select($sql);		
   	}
   	
   	public function getVarCountByGene($gene_id) {
   		if (!array_key_exists($gene_id, $this->var_gene_count)) {
   			$rows = DB::select("select count(*) as cnt, type from (select distinct p.project_id, v.gene, v.type, p.patient_id from var_gene_tier v, project_patients p
  where p.project_id=$this->id and p.patient_id=v.patient_id and gene='$gene_id') sq group by type order by type");

   		//$rows = DB::select("select count(*) as cnt,type from var_gene_cohort v where project_id=$this->id and v.gene = '$gene_id' group by type order by type");
   		 	$this->var_gene_count[$gene_id] = array("germline" => 0, "somatic" => 0, "rnaseq" => 0, "variants" => 0);
   			foreach ($rows as $row) {
   				$this->var_gene_count[$gene_id][$row->type] = $row->cnt;
   			}
   		}
   		return $this->var_gene_count[$gene_id];
   	}

   	public function hasGeneMutation($gene_id) {
   		$var_count = $this->getVarCountByGene($gene_id);
   		foreach ($var_count as $type => $cnt) {
   			if ($cnt > 0)
   				return true;
   		}
   		return false;
   	}

   	public function hasMutation() {
   		if (!isset($this->has_mutation)) {
   			$rows = DB::select("select count(*) as cnt from var_cases c, project_patients p where p.project_id=$this->id and c.patient_id=p.patient_id and type in ('germline','somatic','rnaseq','variants')");   			
   			$this->has_mutation = ($rows[0]->cnt > 0);
   		}
   		return $this->has_mutation;
   	}

	function hasTCellExTRECT() {
		$sql = "select count(*) as cnt from project_cases c, tcell_extrect v,patients p where c.project_id=$this->id and c.patient_id = v.patient_id and c.case_id=v.case_id and c.patient_id=p.patient_id";
		$rows = DB::select($sql);
		return ($rows[0]->cnt > 0);
	}

	function getTCellExTRECT() {
		$sql = "select distinct p.diagnosis,v.* from project_cases c, tcell_extrect v,patients p where c.project_id=$this->id and c.patient_id = v.patient_id and c.case_id=v.case_id and c.patient_id=p.patient_id";
		$rows = DB::select($sql);
		return $rows;
	}

	public function getProjectSamples($include_details=true, $exp_type="all") {
		$exp_condition = "";
		if ($exp_type != "all")
			$exp_condition = " and exp_type='$exp_type'";		
		$sql = "select * from project_samples where project_id=$this->id $exp_condition";
		Log::info($sql);
		$samples = DB::select($sql);
		$sample_details_attrs = array();
		$sample_details_values = array();
		if ($include_details) {
			$sql_details = "select * from project_samples s1, sample_details s2 where project_id=$this->id and s1.sample_id=s2.sample_id";
			$sample_details = DB::select($sql_details);			
			foreach ($sample_details as $sample_detail) {
				$sample_details_attrs[$sample_detail->attr_name] = "";
				$sample_details_values[$sample_detail->sample_id][$sample_detail->attr_name] = $sample_detail->attr_value;
			}
			$sample_details_attrs = array_keys($sample_details_attrs);
		}
		$rows = DB::select($sql);
		$new_rows = array();
		foreach($rows as $row) {
			$new_row = $row;
			foreach($sample_details_attrs as $attr) {
				$new_row->{$attr} = "";
				if (array_key_exists($row->sample_id, $sample_details_values))
					if (array_key_exists($attr, $sample_details_values[$row->sample_id]))
						$new_row->{$attr} = $sample_details_values[$row->sample_id][$attr];
			}
			$new_rows[] = $new_row;

		}
		return $new_rows;

	}

	public function saveClinicalDataType($data) {
		try {
			DB::beginTransaction();
			DB::table('patient_attr')->where('project_id', '=', $this->id)->delete();
			DB::table('sample_attr')->where('project_id', '=', $this->id)->delete();
			DB::table('patient')->where('project_id', '=', $this->id)->delete();
			DB::table('sample')->where('project_id', '=', $this->id)->delete();
			DB::table('clinical_data')->where('project_id', '=', $this->id)->delete();

			$patient_patient_id_col = $data->patients->patient_id_col;
			$patient_diagnosis_col = $data->patients->diagnosis_col;			
			$patient_survival_time_col = $data->patients->survival_time_col;			
			$patient_survival_status_col = $data->patients->survival_status_col;
			$patient_fixed_columns = array($patient_patient_id_col, $patient_diagnosis_col, $patient_survival_time_col, $patient_survival_status_col);
			$patient_data = $data->patients->patient_data;

			$sample_sample_id_col = $data->samples->sample_id_col;			
			$sample_patient_id_col = $data->samples->patient_id_col;
			$sample_tissue_type_col = $data->samples->tissue_type_col;
			$sample_fixed_columns = array($sample_sample_id_col, $sample_patient_id_col, $sample_tissue_type_col);
			$sample_data = $data->samples->sample_data;						

			for ($i=0;$i<count($data->patients->meta_data);$i++) {
				$id = $data->patients->meta_data[$i]->id;
				$label = $data->patients->meta_data[$i]->label;
				$type = $data->patients->meta_data[$i]->type;
				$include = $data->patients->meta_data[$i]->include;
				$values = $data->patients->meta_data[$i]->values;
				DB::table('patient_attr')->insert(array('project_id' => $this->id, 'attr_id' => $id, 'display_name' => $label, 'type' => $type, 'included' => $include, 'value_list' => $values));
			}

			for ($i=0;$i<count($data->samples->meta_data);$i++) {
				$id = $data->samples->meta_data[$i]->id;
				$label = $data->samples->meta_data[$i]->label;
				$type = $data->samples->meta_data[$i]->type;
				$include = $data->samples->meta_data[$i]->include;
				$values = $data->samples->meta_data[$i]->values;
				DB::table('sample_attr')->insert(array('project_id' => $this->id, 'attr_id' => $id, 'display_name' => $label, 'type' => $type, 'included' => $include, 'value_list' => $values));
			}

			foreach ($data->patients->patient_data as $patient) {
				$patient_id = $patient[$patient_patient_id_col];
				$diagnosis = $patient[$patient_diagnosis_col];
				if ($patient_survival_time_col != "None")
					$survival_time = $patient[$patient_survival_time_col];
				if ($patient_survival_status_col != "None")
					$survival_status = $patient[$patient_survival_status_col];
				for ($i=0;$i<count($patient);$i++) {
					if (! in_array($i, $patient)) {
						DB::table('clinical_data')->insert(array('project_id' => $this->id, 'id' => $patient_id, 'type' => "patient", 'attr_id' => $data->patients->meta_data[$i]->id, 'attr_value' => $patient[$i]));
					}
				}
				DB::table('patient')->insert(array('project_id' => $this->id, 'patient_id' => $patient_id, 'diagnosis' => $diagnosis, 'survival_time' => $survival_time, 'survival_status' => $survival_status));
			}
			foreach ($data->samples->sample_data as $sample) {
				$patient_id = $sample[$sample_patient_id_col];
				$sample_id = $sample[$sample_sample_id_col];
				$tissue_type = $sample[$sample_tissue_type_col];
				for ($i=0;$i<count($sample);$i++) {
					if (! in_array($i, $sample)) {
						DB::table('clinical_data')->insert(array('project_id' => $this->id, 'id' => $sample_id, 'type' => "sample", 'attr_id' => $data->samples->meta_data[$i]->id, 'attr_value' => $sample[$i]));
						//DB::table('sample_data')->insert(array('project_id' => $this->id, 'sample_id' => $sample_id, 'attr_name' => $data->samples->meta_data[$i]->id, 'attr_value' => $sample[$i]));
					}
				}
				DB::table('sample')->insert(array('project_id' => $this->id, 'patient_id' => $patient_id, 'sample_id' => $sample_id, 'tissue_type' => $tissue_type));
			}			
			DB::commit();
			return "ok";
		} catch (\PDOException $e) { 
			DB::rollBack();
			return $e->getMessage();
		}
	}

	public function getAdditionalLinks() {
		return DB::select("select * from project_additional_links where project_id=$this->id");
	}

	public function getAdditionalTabs() {
		return DB::select("select * from project_additional_tabs where project_id=$this->id");
	}
}
