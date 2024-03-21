<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB,Log,Config,File,Lang,Cache;

class VarAnnotation {

	private $gene_id = 'null';
	private $sid = 'null';
	private $patient_id = 'null';
	private $sample_id = 'null';
	private $chr = 'null';
	private $start_pos = 'null';
	private $end_pos = 'null';
	private $mutPlotData = null;
	private $data;
	private $columns;
	private $study;
	private $actionables = null;
	private $root_url;	

	function __construct() {
		$this->root_url = url("/");		
	}

	static public function getVarAnnotationByPatient($project_id, $patient_id, $case_id, $type, $use_table=false) {		
		$var = new VarAnnotation();
		$var->init_patient($project_id,$patient_id, $case_id, $type, $use_table);
		return $var;
	}
	static public function getCaseidsByType($patient_id, $type) {		
		$sql = " select case_id from var_cases where type='$type' and patient_id ='$patient_id'";
				Log::info("getCaseidsByType ".$sql);

		$rows = DB::select($sql);
		return $rows;
	}
	static public function insertVariant($chr,$start,$end,$ref,$alt) {
		$user = User:: getCurrentUser();
		$user_id=$user->email;
		$user_id=$user->id;
		$patient_id='pat_'.$user_id;
		$project_name='anno_'.$user_id;
		$sample_id='Sample_'.$user_id.'_XYXYX';
		$sample_name='Sample_'.$user_id.'_NA';
		$project_description="inserted by user";
		$sql="select patient_id from patients where patient_id='$patient_id'";
		$rows=DB::select($sql);
		$length =count($rows);
		Log::info("LENGTH of patients ".$length);
		if ($length==0){
			$sql="insert into patients (patient_id,diagnosis,project_name,is_cellline,case_list,user_id) values ('$patient_id','NA','NA','N','Research',$user_id)";
			DB::insert($sql);
		}
		$sql="select * from var_samples where chromosome='$chr' and start_pos=$start and end_pos=$end and ref='$ref' and alt='$alt'";
		$rows=DB::select($sql);
		$length =count($rows);
		Log::info("LENGTH of samples ".$length);
		if ($length==0){
			$sql="insert into var_samples (chromosome,start_pos,end_pos,ref,alt,case_id,patient_id,sample_id,sample_name,type) values ('$chr',$start,$end,'$ref','$alt','NA','$patient_id','$sample_id','$sample_name','germline')";
#			DB::insert($sql);
			$sql="insert into var_samples (chromosome,start_pos,end_pos,ref,alt,case_id,patient_id,sample_id,sample_name,type) values ('$chr',$start,$end,'$ref','$alt','NA','$patient_id','$sample_id','$sample_name','somatic')";
#			DB::insert($sql);
		}
		$sql="select * from projects where name='$project_name'";
		$rows=DB::select($sql);
		$length =count($rows);
		Log::info("LENGTH of project $project_name ".$length);
		if ($length==0){
			$sql="insert into projects (name,ispublic,description,user_id,created_at,updated_at,isstudy,status,version) VALUES ('$project_name','0','$project_description',$user_id,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,'1','0','19')";
			DB::insert($sql);
		}
		$sql="select * from project_samples where sample_id='$sample_id'";
		$rows=DB::select($sql);
		$length =count($rows);
		Log::info("LENGTH of sample in project_samples $sample_id ".$length);
		if ($length==0){
			$sql="select id from projects where name='$project_name'";
			$rows=DB::select($sql);
			foreach ($rows as $row) {
				$project_id=$row->id;
			}
			$sql="insert into project_samples(project_id,patient_id,sample_id,sample_name) VALUES ($project_id,'$patient_id','$sample_id','$sample_name')";
			DB::insert($sql);
			Log::info($sql);
		}
		$sql="select * from project_patients where patient_id='$patient_id'";
		$rows=DB::select($sql);
		$length =count($rows);
		Log::info("LENGTH of sample in project_patients $patient_id ".$length);
		if ($length==0){
			$sql="select id from projects where name='$project_name'";
			$rows=DB::select($sql);
			foreach ($rows as $row) {
				$project_id=$row->id;
			}
			$sql="insert into project_patients(project_id,patient_id,case_name) VALUES ($project_id,'$patient_id','$case_id')";
		}
	}
	static public function getVarAnnotationByVariant($chr,$start,$end,$ref,$alt) {
		$vars = new VarAnnotation();
		$data=array();
		$samples=array();
		$columns=array();
		$sql="select distinct v.patient_id,v.case_id,p.project_id,v.sample_id,v.type from var_samples v, project_samples p where p.patient_id=v.patient_id and v.chromosome='$chr' and v.start_pos=$start and v.end_pos=$end and ref='$ref' and alt='$alt'";


		Log::info($sql);
		$sample_rows = DB::select($sql);
		Log::info(count($sample_rows));
		foreach ($sample_rows as $sample) {
			$sample_id=$sample->sample_id;
			$case_id=$sample->case_id;
			$patient_id=$sample->patient_id;
			$project_id=$sample->project_id;
			$type=$sample->type;
			$chr_indx=0;
			$start_indx=0;
			$end_indx=0;
			$ref_indx=0;
			$alt_indx=0;
			if (User::hasPatient($patient_id) && !in_array($sample_id, $samples)) {
				Log::info("getVarAnnotationByVariant: ".$sample_id." type ".$type);
				$vars->init_sample($project_id,$patient_id, $sample_id, $case_id, $type);
				Log::info("VARS");
				foreach (array_values($vars->columns) as $i => $value) {
					if($value['title']=='Chr')
						$chr_indx=$i;					
					if($value['title']=='Ref')
						$ref_indx=$i;
					if($value['title']=='Alt')
						$alt_indx=$i;
					if($value['title']=='Start')
						$start_indx=$i;
					if($value['title']=='End')
						$end_indx=$i;
				}
				foreach($vars->data as $var){
					$chr_current=$var[$chr_indx];
					$start_current=$var[$start_indx];
					$end_current=$var[$end_indx];
					$ref_current=$var[$ref_indx];
					$alt_current=$var[$alt_indx];
					if($chr_current==$chr && $start_current==$start && $end_current==$end &&$ref_current==$ref &&$alt_current==$alt && ($type=='somatic' || $type=='germline') ){
						Log::info("FOUND VARIANT");
						$columns=$vars->columns;
						Log::info($type);
						$data[] = $var;


					}
				}

			}
			$samples[]=$sample->sample_id;
		}
		$vars->data=$data;
		$vars->columns=$columns;
		return $vars;
	}
	#	list($this->data, $this->columns) = $this->postProcessVarData($rows, $project_id, $type, $found_hotspots);

	static public function getVarAnnotationBySample($project_id, $patient_id, $sample_id, $case_id, $type) {
		$var = new VarAnnotation();
		$var->init_sample($project_id,$patient_id, $sample_id, $case_id, $type);
		return $var;
	}

	static public function getVarAnnotationByGene($project_id, $gene_id, $type, $use_table=false) {
		$var = new VarAnnotation();
		$var->init_gene($project_id, $gene_id, $type, $use_table);		
		$var->getRefMutations($gene_id);
		return $var;
	}

	static public function getAnnotationBySampleGene($sample_id, $gene_id) {
		$var = new VarAnnotation();
		$var->initBySampleGene($sample_id, $gene_id);
		return $var;
	}

	static public function getAnnotationByLocus($chr, $start_pos, $end_pos) {
		$var = new VarAnnotation();
		$var->initByLocus($chr, $start_pos, $end_pos);
		return $var;
	}

	static public function getAllPatients() {
		$tbl_name = "var_samples";
		$sql = "select distinct diagnosis, p1.patient_id, count(p1.patient_id) as cnt from patients p1, $tbl_name p2 where p1.patient_id=p2.patient_id group by diagnosis, p1.patient_id";
		$diag_rows = DB::select($sql);
		$patients = array();
		foreach ($diag_rows as $diag) {
			$patients[$diag->diagnosis][$diag->patient_id] = $diag->cnt;
		}
			
		return $patients;

	}	

	static public function getCNVByPatient($patient_id) {
		$sql = "select * from var_cnv where patient_id = '$patient_id'";
		$rows = DB::select($sql);		
		return $rows;
	}

	static public function getVarSamples($chr, $start_pos, $end_pos, $ref_base, $alt_base, $patient_id, $case_id, $type) {
		$case_condition = "";
		if ($case_id != "any")
			$case_condition = "and case_id='$case_id' ";
		if ($case_id == "null")
			$case_condition = "";
		//to do: library table should be shown as case name:
		//$case_condition = "and exists(select * from sample_cases s where s.patient_id=v.patient_id and v.case_id=s.case_id and s.case_name='$case_name' and v.sample_id=s.sample_id)";
		$sql = "select sample_name, exp_type, tissue_cat, caller, qual, fisher_score, total_cov, var_cov, '' as VAF, vaf_ratio, relation from var_samples where 
			chromosome='$chr' and start_pos='$start_pos' and end_pos = '$end_pos' and ref = '$ref_base' and alt = '$alt_base' and
			patient_id= '$patient_id' $case_condition and type = '$type'";
		/*$sql = "select alias as sample, s1.exp_type, s1.tissue_cat, caller, qual, fisher_score, total_cov, variant_cov, '' as VAF, vaf_ratio, s1.relation from var_samples s1, samples s2 where 
			chromosome='$chr' and start_pos='$start_pos' and end_pos = '$end_pos' and ref = '$ref_base' and alt = '$alt_base' and 			
			s1.patient_id= '$patient_id' and case_id='$case_id' and type = '$type' and s1.sample_id=s2.sample_id";*/
		$rows = DB::select($sql);
		Log::info($sql);
		$data = array();
		$sample_hide_cols = array("chromosome", "start_pos", "end_pos", "ref", "alt", "patient_id", "type");
		if ($type != "germline")
			$sample_hide_cols[] = "vaf_ratio";
		$first_row = 1;
		foreach ($rows as $row) {
			if ($row->total_cov == 0)
				$row->vaf = 0.000;
			else
				$row->vaf = round($row->var_cov/$row->total_cov,3);
			$row->vaf_ratio = round($row->vaf_ratio,2);
			$row->caller = VarAnnotation::parseCallers($row->caller);
			$row->qual = VarAnnotation::parseCallers($row->qual);
			$row->fisher_score = VarAnnotation::parseCallers($row->fisher_score);
			$array = json_decode(json_encode($row), true);
			$row_value = array();
			foreach ($array as $key=>$value) {
				if (!in_array($key, $sample_hide_cols)) {
					if ($first_row) {
						$key_label = VarAnnotation::getKeyLabel($key);
						$columns[] = array("title"=>$key_label);
					}
					$row_value[] = $value;
				}
			}
			$first_row = 0;
			$data[] = $row_value;
		}
		return array("data" => $data, "columns" => $columns);
	}

	static public function parseCallers($value) {
		$callers = explode(";", $value);
		$results = "";
		foreach ($callers as $caller) {
			if ($caller == '.')
				$results.=	$caller;
			else
				$results.="<span class='badge'>".$caller."</span>";
		}
		return $results;
	}

	static public function getVarStjudeDetails($chr, $start_pos, $end_pos, $ref_base, $alt_base) {
		$sql = "select * from stjude where chromosome='$chr' and start_pos='$start_pos' and end_pos = '$end_pos' and ref = '$ref_base' and alt = '$alt_base'";
		$row = DB::select($sql)[0];
		$data = array();
		$columns = array(array("title"=>"Key"), array("title"=>"Value"));

		$data[] = array("Gene", $row->gene);
		if ($row->pmid != '-')
			$data[] = array("PMID", "<a target='_blanck' href=http://www.ncbi.nlm.nih.gov/pubmed/".$row->pmid.">".$row->pmid."</a>");
		if ($row->panel_decision != '-')
			$data[] = array("Panel decision", VarAnnotation::getKeyLabel($row->panel_decision));
		if ($row->category != '-')
			$data[] = array("Category", VarAnnotation::getKeyLabel($row->category));
		$data[] = array("Count", $row->count);
		return array("data" => $data, "columns" => $columns);
	}


	static public function getAnnotator() {
		$sql = "select * from var_avia_annotator a, var_avia_cols_oc_all o where a.annotator=o.annotator";
		Log::info($sql);
		$rows = DB::select($sql);
		$data = array();
		foreach ($rows as $row) {
			$data[strtolower($row->table_column_name)] = $row;
		}
		return $data;
	}

	static public function JSONToHTMLTable($data_str, $link, $headers=null) {
		$data = json_decode($data_str);
		if (!is_array($data)) {
			if ($link != "") {
				$data_str = "<a target=_blank href='$link/$data_str'>$data_str</a>";
			} else {
				if (substr($data_str, 0, 4) == "http" || substr($data_str, 0, 4) == "www.")
					$data_str = "<a target=_blank href='$data_str'>$data_str</a>";
			}
			return $data_str;
		}
		Log::info($data);
		$html="<table border=1>";
		if ($headers != null)
			foreach($headers as $header)
				$html.="<th>$header</th>";
			
		foreach($data as $row) {
			$html.="<tr>";
			if (is_array($row)) {
				foreach($row as $cell) 
					$html.="<td>$cell</td>";
			} else
				$html.="<td>$row</td>";
			$html.="</tr>";
		}
		$html.="</table>";
		return $html;

	}

	static public function sepToHTMLTable($str, $sep, $link) {
		if ($sep == null)
			return $str;		
		$html="<table border=1>";
		$data = [];
		if (strlen($sep) == 1) {
			$data = explode($sep, $str);
			foreach ($data as $value) { 
				if ($link != "") {
					$value = "<a target=_blank href='$link/$value'>$value</a>";
				}
				$html.="<tr><td>$value</td></tr>";
			}
		}
		if (strlen($sep) == 2) {
			$html.="<th style=color:white>Key</th><th style=color:white>Value</th>";
			$sep1 = substr($sep, 0, 1);
			$sep2 = substr($sep, 1, 2);
			$data = VarAnnotation::parseString($str, $sep1, $sep2);
			foreach ($data as $key => $value) {
				$html.="<tr><td>$key</td><td>$value</td></tr>";			
			}
		}
		
		$html.="</table>";
		return $html;

	}

	static public function getVariant($patient_id, $case_id, $sample_id, $type, $chr, $start_pos, $end_pos, $ref, $alt) {
		$avia_table = Config::get('site.avia_table');
		$sql = "select * from $avia_table where patient_id='$patient_id' and case_id='$case_id' and sample_id='$sample_id' and type='$type' and chromosome='$chr' and start_pos=$start_pos and end_pos=$end_pos and ref='$ref'and alt='$alt'";
		Log::info($sql);
		$rows = DB::select($sql);
		$row = (array)$rows[0];
		$annotator_rows = VarAnnotation::getAnnotator();
		$data = array();
		foreach ($row as $col_name => $val) {
			if ($val == "")
				continue;
			if (array_key_exists($col_name, $annotator_rows)) {
				$annotator_row = $annotator_rows[$col_name];				
				if ($annotator_row->annotator_type == "Reported") {
					list($total_count, $detail_data) = VarAnnotation::parseReported((object)$row, [(object)["column_name" => $col_name]]);
					if (count($detail_data) > 0)
						$val = "<div style='text-align:left'>Total count:".$detail_data[0][1]."</div>".$detail_data[0][2];
				}			

				if ($annotator_row->sep != null) {
					$val = VarAnnotation::sepToHTMLTable($val, $annotator_row->sep, $annotator_row->link);
				} else {
					if ($col_name == "interpro__domain")
						$val = str_replace("'", "\"", $val);	

					$val = VarAnnotation::JSONToHTMLTable($val, $annotator_row->link);
				}

				$values = [$col_name, $annotator_row->column_name, $annotator_row->column_description, $val];
				if ($annotator_row->annotator_name == null || $annotator_row->annotator_name == "")
					$annotator_row->annotator_name = $annotator_row->annotator;
				$type = $annotator_row->annotator_type; 
				
				if (!array_key_exists($type, $data))
					$data[$type] = array();
				$key = $annotator_row->annotator_name;
				$key = strtolower(str_replace(" ", "_", $key));
				$key_label = Lang::get("messages.$key");
				if ($key_label == "messages.$key") {
					$key_label = ucfirst($key);
				}
				if (array_key_exists($key_label, $data[$type]))
					$data[$type][$key_label][2][] = $values;
				else 					
					$data[$type][$key_label] = [$annotator_row->annotator_name, $annotator_row->annotator_description, [$values]];
				
			} else {
				//$data[$col_name] = [$col_name, $col_name, [[$col_name, $col_name, $col_name, $val]]];
			}
		}
		return $data;
	}
		
	static public function getVarDetails($type, $patient_id, $case_id, $sample_id, $chr, $start_pos, $end_pos, $ref_base, $alt_base, $gene_id) {
		$avia_mode = VarAnnotation::is_avia();
		$pubmed_url = Config::get('onco.pubmed_url');
		if ($avia_mode) {
			if ($type == "acmg") {
				$rows = DB::select("select * from acmg where gene_refgene = '$gene_id'");
				Log::info("select * from acmg where gene_refgene = '$gene_id'");
				$columns = array(array("title" => "Feature"), array("title" => "Value"));
				$detail_data = array();
				foreach ($rows as $row) {
					$row_arr = (array)$row;							
					foreach ($row_arr as $key => $value) {
						if ($key == "lsdb" && substr($value, 0, 4) == "http")
							$value = "<a target=_blank href='$value'>$value</a>";
						if ($key == "pubmedid" && $value != "NA" && $value != "none") {
							$pubmeds = explode(",", $value);
							$new_values = array();
							foreach ($pubmeds as $pubmed) {
								$pubmed = trim($pubmed);
								$new_values[] = "<a target='_blank' href='$pubmed_url$pubmed'>$pubmed</a>";
							}
							$value = implode("\n", $new_values);
						}
						$detail_data[] = array(VarAnnotation::getKeyLabel($key), "<pre>$value</pre>");
					}
				}
				return array("data" => $detail_data, "columns" => $columns);
			}
			Log::info("IN DETAILS");

			$chromosome = substr($chr, 3);
			$avia_table = Config::get('site.avia_table');
			#$avia_rows = DB::table($avia_table)->where("chr", $chromosome)->where("query_start", $start_pos)->where("query_end", $end_pos)->where("allele1", 		
			$avia_rows = DB::select("select * from $avia_table where patient_id='$patient_id' and case_id = '$case_id' and sample_id='$sample_id' and chromosome='$chr' and start_pos=$start_pos and end_pos=$end_pos and ref='$ref_base' and alt='$alt_base'");
			Log::info("select * from $avia_table where chromosome='$chr' and start_pos=$start_pos and end_pos=$end_pos and ref='$ref_base' and alt='$alt_base'");
			//$avia_rows = DB::table($avia_table)->where("chromosome", $chr)->where("start_pos", $start_pos)->where("end_pos", $end_pos)->where("ref", 			$ref_base)->where("alt", $alt_base)->get();
			Log::info("total: ".$avia_table."=>".count($avia_rows));
			Log::info("type: ".$type);
			if (count($avia_rows) > 0) {
				$avia_row = $avia_rows[0];			
				//$avia_col_cat = VarAnnotation::getAVIACols();
				$avia_col_cat = VarAnnotation::getAVIAColsDesc();
				if (array_key_exists($type, $avia_col_cat)) {
					$cols = $avia_col_cat[$type];
					if ($type == "dbsnp_af") {
						list($maf, $detail_data) = VarAnnotation::parseDBSNP_af($avia_row, $cols);
						$columns = array(array("title"=>"Population"), array("title"=>"Sub-population"), array("title"=>"Frequency"));
						return array("data" => $detail_data, "columns" => $columns);
					}
					if ($type == "freq") {						
						list($maf, $detail_data) = VarAnnotation::parseFrequency($avia_row, $cols);
						$columns = array(array("title"=>"Population"), array("title"=>"Sub-population"), array("title"=>"Frequency"), array("title"=>"Allele Count"), array("title"=>"Total Count"));
						return array("data" => $detail_data, "columns" => $columns);
					}

					if ($type == "gene") {
						$detail_data = VarAnnotation::parseGene($avia_row, $cols);
						$detail_data[] = array("GeneCards", "<a target=_blank href='http://www.genecards.org/cgi-bin/carddisp.pl?gene=$gene_id'>$gene_id</a>");
						$detail_data[] = array("NCBI", "<a target=_blank href='https://www.ncbi.nlm.nih.gov/gene/?term=$gene_id'>$gene_id</a>");			
						$columns = array(array("title"=>"Feature"), array("title"=>"Value"));
						return array("data" => $detail_data, "columns" => $columns);
					}

					### AVIA OC
					/*
					if ($type == "clinvar") {
						$col = $cols[0]->column_name;
						$value = $avia_row->{strtolower($col)};
						if ($value != '' && $value != '-') {
							list($clisig, $columns, $data) = VarAnnotation::parseClinvar($value);
							return array("data" => $data, "columns" => $columns);
						}
					}

					
					if ($type == "civic") {
						$col = $cols[0]->column_name;
						$value = $avia_row->{strtolower($col)};
						Log::info("Parsing CIViC");
						Log::info("col: $col");
						Log::info("value: $value");
						if ($value != '' && $value != '-') {
							list($columns, $data) = VarAnnotation::parseCivic($value);
							return array("data" => $data, "columns" => $columns);
						}
					}
					*/

					if ($type == "hgmd") {
						$col = $cols[0]->column_name;
						$value = $avia_row->{strtolower($col)};
						if ($value != '' && $value != '-') {
							list($columns, $data) = VarAnnotation::parseHGMD($value);
							return array("data" => $data, "columns" => $columns);			
						}
					}

					if ($type == "cosmicx") {						
						$col = $cols[0]->column_name;
						$value = $avia_row->{strtolower($col)};
						if ($value != '' && $value != '-') {
							list($columns, $data) = VarAnnotation::parseCosmic($value);
							return array("data" => $data, "columns" => $columns);			
						}
					}

					if ($type == "actionable") {
						$columns = array(array("title"=>"Source"), array("title"=>"Feature"), array("title"=>"Value"));
						$data = array();
						$values = array();
						foreach ($cols as $c) {
							$col = $c->column_name;
							$value_str = $avia_row->{strtolower($col)};
							if ($value_str == "" || $value_str == "-")
								continue;
							if (strtolower($col) == "candl__candl" || strtolower($col) == "civic" || strtolower($col) == "docm__docm") {
								$values = VarAnnotation::parseString($value_str, ";", "=", ",");
							}
							else if (strtolower($col) == "mcg__mcg") {
								//this part is kinda messy. Find union of My cancer genome links
								$values = explode(",", $value_str);
								$links = array();
								foreach ($values as $value) {
									$pair = explode("=", $value);
									if (count($pair) == 2) {
										$key = $pair[0];
										$value = $pair[1];
										if (strtolower($key) == "mycg_link") {
											$value = explode(";", $value);
											foreach ($value as $v) {
												$link = "<a target='_blank' href='http://$v'>$v</a>";
												$links[$link] = '';
											}										
										}
									}
								}
								$link_str = "<PRE>".implode("\n", array_keys($links))."</PRE>";
								$data[] = array("My Cancer Genome", "URL", $link_str);
								continue;
							}
							else if (strtolower($col) == "matchtrial") {
								$arr = explode("=", $value_str);
								if (count($arr) == 2)
									$values = array($arr[0] => explode(",", $arr[1]));
							}
							else if (strtolower($col) == "targetedcancercare") {
								$arr = explode(":", $value_str);
								if (count($arr) == 2) {
									$gene_id = $arr[0];
									$values = array("URL" => array("<a target='_blank' href='https://targetedcancercare.massgeneral.org/My-Trial-Guide/Genes/$gene_id.aspx'>$gene_id</a>"));
								}
							}
							
							foreach ($values as $key => $value) {
								$row_data = array(VarAnnotation::getKeyLabel($col));
								$row_data[] = VarAnnotation::getKeyLabel($key);
								$new_values = array();
								foreach($value as $dtl) {
									if (strtolower($key) == "pubmed_id" || strtolower($key) == "pmid" || strtolower($key) == "pmids") {					
										$new_values[] = "<a target='_blank' href='$pubmed_url$dtl'>$dtl</a>";										
									}
									else
										$new_values[] = $dtl;
								}
								$row_data[] = "<PRE>".implode("\n", $new_values)."</PRE>";
								$data[] = $row_data;
							}							
						}
						return array("data" => $data, "columns" => $columns);
					}

					if ($type == "reported") {
						list($maf, $detail_data) = VarAnnotation::parseReported($avia_row, $cols);
						$columns = array(array("title"=>"Source"), array("title"=>"Count"), array("title"=>"Details"));
						return array("data" => $detail_data, "columns" => $columns);
					}

					if ($type == "genie") {
						list($maf, $detail_data) = VarAnnotation::parseGenie($avia_row, $cols);						
						$columns = array(array("title"=>"Tissue"), array("title"=>"Code"), array("title"=>"Main Type"), array("title"=>"NCI Thesaurus"), array("title"=>"UMLS"), array("title"=>"Count"));
						return array("data" => $detail_data, "columns" => $columns);
					}					

					if ($type == "oncokb_actionable" || $type == "oncokb_api") {
						Log::info("getting detail info, type: $type");
						Log::info("column name: ".$avia_row->{$type});
						$sep = ($type == "oncokb_actionable")? ":" : ";";
						#$sep = ";";
						$values = VarAnnotation::parseString($avia_row->{$type}, $sep, "=");						
						$detail_data = array();
						foreach ($values as $key => $value) {
							$detail_data[] = array($key, $value);							
						}
						$columns = array(array("title"=>"Feature"), array("title"=>"Value"));
						return array("data" => $detail_data, "columns" => $columns);
					}
					Log::info("type: $type");
					if ($type == "prediction" || $type == "actionable" || $type == "civic" || $type=="clinvar" || $type == "cosmic" || $type == "spliceai") {
						$columns = array(array("title"=>"Feature"), array("title"=>"Description"), array("title"=>"Value"));
						$data = array();
						foreach ($cols as $col) {
							//Log::info($col->column_name);
							$value = $avia_row->{strtolower($col->column_name)};
							if (strtolower($col->column_name) == "cosmic__variant_count_tissue") {
								$vs = json_decode($value);
								$varr = [];
								foreach ($vs as $d)
									$varr[] = $d[0].":".VarAnnotation::formatLabelStatic($d[1]);
								$value = implode("<BR>", $varr);

							}
							if ($value != '' && $value != '-') {
								$column_name = $col->cname;
								if ($col->cname == '')
									$column_name = $col->column_name;
								#$data[] = array($column_name, "<div class=\"desc\">".$col->column_description."</div>", "<PRE>$value</PRE>");
								$data[] = array($column_name, $col->column_description, "<PRE>$value</PRE>");
							}
						}						
						return array("data" => $data, "columns" => $columns);
					}
				}
			}
		}

		return array("data" => [], "columns" => []);

		if ($type == "gene")
			$type = "refgene";
		$sql = "select * from var_annotation_details where chromosome='$chr' and start_pos='$start_pos' and end_pos = '$end_pos' and ref = '$ref_base' and alt = '$alt_base' and type = '$type'";
		$rows = DB::select($sql);
		Log::info($sql);
		$data = array();
		if ($type == "reported")
			$columns = array(array("title"=>"Source"), array("title"=>"Count"));
		elseif ($type == "freq")
			$columns = array(array("title"=>"Population"), array("title"=>"Sub-population"), array("title"=>"Frequency"));
		elseif ($type == "actionable")
			$columns = array(array("title"=>"Source"), array("title"=>"Feature"), array("title"=>"Value"));
		elseif ($type == "clinvar")
			$columns = array(array("title"=>"Accession"), array("title"=>"CliSig"), array("title"=>"Disease"), array("title"=>"Disease DB"), array("title"=>"Disease DB ID"));
		elseif ($type == "sample")
			$columns = array();
		else
			$columns = array(array("title"=>"Key"), array("title"=>"Value"));
		
		
		$clinvar_url = Config::get('onco.clinvar_url');
		$hgmd_url = Config::get('onco.hgmd_url');
		$hgmd_gene_url = Config::get('onco.hgmd_gene_url');
		$clinvar_data = array();
		foreach ($rows as $row) {	
			$original_value = $row->attr_value;
			$row->attr_value = "<PRE>".str_replace(",", "\n", $row->attr_value)."</PRE>";
			if ($type == "acmg") {
				if ($row->attr_name == "ACMG_LSDB")
					$row->attr_value = "<a target='_blank' href='".$original_value."'>$original_value</a>";
			}
			if ($type == "actionable" || $type == "freq") {
				if ($row->attr_name == "cg69") {
					$data[] = array("Complete genomics", $row->attr_name, $row->attr_value);
					continue;
				}
				if ($row->attr_name == "nci60") {
					$data[] = array("Cellline", $row->attr_name, $row->attr_value);
					continue;
				}
				$tcga_suffix = "";
				if (stripos($row->attr_name,"nontcga") !== FALSE) {
					$tcga_suffix = "_nonTCGA";
				}
 				$attr_type = explode("_", $row->attr_name);
				$row->attr_value = str_replace(",", "\n", $original_value);
				$row->attr_value = str_replace(";", "\n", $row->attr_value);
				if ($attr_type[1] != 'Link')
					$row->attr_value = str_replace("/", "\n", $row->attr_value);
					
				$row->attr_value = str_replace(" ", "", $row->attr_value);
				if (strtolower($attr_type[1]) == "pmid" || strtolower($attr_type[1]) == "pmids") {					
					$ids = explode("\n", $row->attr_value);
					$new_values = array();
					foreach ($ids as $id)
						$new_values[] = "<a target='_blank' href='$pubmed_url$id'>$id</a>";
					$row->attr_value = implode("\n", $new_values);
				}
				$row->attr_value = "<PRE>".$row->attr_value."</PRE>";
				if ($attr_type[1] == 'Link')
					$row->attr_value = "<a target='_blank' href='http://".$original_value."'>Link</a>";
				if (strtolower($attr_type[0]) == 'targeted')
					if ($original_value == $gene_id)
						$row->attr_value = "<a target='_blank' href='https://targetedcancercare.massgeneral.org/My-Trial-Guide/Genes/$gene_id.aspx'>$gene_id</a>";
				$attr_type[0] = VarAnnotation::getKeyLabel($attr_type[0].$tcga_suffix);
				$attr_type[1] = VarAnnotation::getKeyLabel($attr_type[1]);
				if ($type == "freq")
					$attr_type[1] = strtoupper($attr_type[1]);
				$data[] = array($attr_type[0], $attr_type[1], $row->attr_value);
			} elseif ($type == "reported") {
				if (substr($row->attr_name, 0, 4) == "ICGC") {
					$var_icgc = VarICGC::findVar($chr, $start_pos, $end_pos, $ref_base, $alt_base);
					if ($var_icgc != null) {
						$var_icgc->icgc_id = ltrim($var_icgc->icgc_id, "MU");
						$value = "MU_".$var_icgc->icgc_id;
						$row->attr_name = $value;						
					}
				}
				$row->attr_name = VarAnnotation::getReportedSampleLink($row->attr_name);
				$data[] = array($row->attr_name, $row->attr_value);
			} elseif ($type == "genie") {				
				$row->attr_name = VarAnnotation::getReportedSampleLink($row->attr_name);
				$data[] = array($row->attr_name, $row->attr_value);			
			} elseif ($type == "cosmic") {
				if ($row->attr_name == "ID") {
					$ids = explode(",", $original_value);
					$row->attr_value = "<PRE>";
					foreach ($ids as $id) {
						$id = ltrim($id, "COSM");
						$row->attr_value .= "<a target='_blank' href='http://grch37-cancer.sanger.ac.uk/cosmic/mutation/overview?id=".$id."'>$id</a>\n";
					}
					$row->attr_value = rtrim($row->attr_value, "\n");
					$row->attr_value .= "</PRE>";
				}
				$data[] = array($row->attr_name, $row->attr_value);	
			} elseif ($type == "clinvar") {
				$value_list = explode('|', $original_value);
				//$value_list = explode(',', $original_value);
				$values = array();
				foreach ($value_list as $value) {
					if (strtolower($row->attr_name) == "clinvar_accession")
						$values[] = "<a target='_blank' href='$clinvar_url$value'>$value</a>";
					else
						$values[] = $value;					
				}
				$clinvar_data[str_replace("clinvar_", "", $row->attr_name)] = $values;
				//$data[] = array(str_replace("clinvar_", "", $row->attr_name), "<PRE>".implode("\n", $values)."</PRE>");
				
			} elseif ($type == "hgmd") {
				if (strtolower($row->attr_name) == 'hgmd_genename') {
					$row->attr_value = "<PRE><span class='hgmdTip' title='".Config::get('onco.hgmd_hint')."' href='#'><a target='_blank' href='$hgmd_gene_url$gene_id'>$original_value</a></span></PRE>";
					$data[] = array('Gene', $row->attr_value);
				}elseif (strtolower($row->attr_name) == 'hgmd_accno') {
					$row->attr_value = "<PRE><span class='hgmdTip' title='".Config::get('onco.hgmd_hint')."' href='#'><a target='_blank' href='$hgmd_url$original_value'>$original_value</a>";
					$data[] = array('Accession', $row->attr_value);
					$refs = DB::table('hgmd_ref')->where('hgmd_accno', trim($original_value))->get();
					$url_strs = [];
					foreach ($refs as $ref) {
						$lists = explode(',', $ref->ref_list);
						foreach ($lists as $list) {
							$url_strs[] = "<a target='_blank' href='$pubmed_url$list'>$list</a>";
						}						
					}
					$data[] = array('Reference', "<PRE>".implode("\n", $url_strs)."</PRE>");
				}
				else
					$data[] = array(str_replace("hgmd_", "", $row->attr_name), $row->attr_value);				
			} elseif ($type == "refgene") {
				if (strtolower($row->attr_name) == "aachange") {
					$row->attr_value = str_replace("$gene_id:", "", $row->attr_value);
					$row->attr_value = str_replace(":", " ", $row->attr_value);
				}
				$row->attr_name = VarAnnotation::getKeyLabel($row->attr_name);
				$data[] = array($row->attr_name, $row->attr_value);
			}
			else {
				$row->attr_name = VarAnnotation::getKeyLabel($row->attr_name);
				$data[] = array($row->attr_name, $row->attr_value);			
			}
			/*
			if ($type == "freq") {
				if ($row->attr_value == '-1')
					$row->attr_value = 'NA';
				if ($row->attr_value != '' && $row->attr_value != 'NA')
					$row->attr_value = round($row->attr_value*100,2)."%";
				$row->attr_name = str_ireplace($row->type."_", "", $row->attr_name);
			}
			$array = json_decode(json_encode($row), true);			
			if ($type == "reported" || $type == "freq" || $type == "actionable") {				
				$row_value = array();
				$i = 0;
				foreach ($array as $key=>$value) {
					if (! in_array($key, array("chromosome","start_pos","end_pos","ref","alt"))) {
						if (strtoupper($key) == "STUDY") {
							if (substr($value, 0, 4) == "ICGC") {
								$var_icgc = VarICGC::findVar($chr, $start_pos, $end_pos, $ref_base, $alt_base);
								if ($var_icgc != null) {
									$var_icgc->icgc_id = ltrim($var_icgc->icgc_id, "MU");
									$value = "MU_".$var_icgc->icgc_id;	
								}

							}
							$value = VarAnnotation::getReportedSampleLink($value);					
						}
						if ($i == count($array) - 1)
							$row_value[] = "<PRE>$value</PRE>";
						else
							$row_value[] = $value;
						
					}
					$i++;
				}
				$data[] = $row_value;
			}			
			else {
				foreach ($array as $key=>$value) 
					if (! in_array($key, array("chromosome","start_pos","end_pos","ref","alt"))) {
						if ($type == "clinvar" || $type == "cosmic")
							$data = VarAnnotation::parseDetail($value);
						else {
							if ($type == "sample") {
								if ($key == "patient_id")
								continue;
							}						
							$data[] = array(VarAnnotation::getKeyLabel($key), "<PRE>$value</PRE>");			
						}
					}
			}
			*/
		} // end of foreach
		if ($type == "refgene") {
			$data[] = array("cBioPortal","<a target=_blank href='http://www.cbioportal.org/cross_cancer.do?cancer_study_list=&cancer_study_id=all&data_priority=0&case_ids=&patient_case_select=sample&gene_set_choice=user-defined-list&gene_list=$gene_id&clinical_param_selection=null&tab_index=tab_visualize&Action=Submit&Action=Submit#crosscancer/overview/0/$gene_id/sarc_mskcc%2Csarc_tcga%2Cthyroid_mskcc_2016%2Cacc_tcga%2Cchol_jhu_2013%2Cchol_nccs_2013%2Cchol_nus_2012%2Cchol_tcga%2Cgbc_shanghai_2014%2Cblca_bgi%2Cblca_dfarber_mskcc_2014%2Cblca_mskcc_solit_2012%2Cblca_mskcc_solit_2014%2Cblca_tcga%2Cblca_tcga_pub%2Cmm_broad%2Ces_dfarber_broad_2014%2Ces_iocurie_2014%2Ccoadread_genentech%2Ccoadread_mskcc%2Ccoadread_tcga%2Ccoadread_tcga_pub%2Cbrca_bccrc%2Cbrca_bccrc_xenograft_2014%2Cbrca_broad%2Cbrca_sanger%2Cbrca_tcga%2Cbrca_tcga_pub%2Cbrca_tcga_pub2015%2Ccesc_tcga%2Clgg_tcga%2Clgg_ucsf_2014%2Clgggbm_tcga_pub%2Cpcpg_tcga%2Cescc_icgc%2Cescc_ucla_2014%2Cegc_tmucih_2015%2Chnsc_broad%2Chnsc_jhu%2Chnsc_tcga%2Chnsc_tcga_pub%2Cnpc_nusingapore%2Clihc_amc_prv%2Clihc_riken%2Clihc_tcga%2Cpaac_jhu_2014%2Cpaad_icgc%2Cpaad_tcga%2Cpaad_utsw_2015%2Cpanet_jhu_2011%2Cmeso_tcga%2Cnepc_wcm%2Cprad_broad%2Cprad_broad_2013%2Cprad_mich%2Cprad_mskcc%2Cprad_mskcc_2014%2Cprad_mskcc_cheny1_organoids_2014%2Cprad_su2c_2015%2Cprad_tcga%2Cprad_tcga_pub%2Ccscc_dfarber_2015%2Crms_nih_2014%2Ctgct_tcga%2Ctet_nci_2014%2Cthca_tcga%2Cthca_tcga_pub%2Cucec_tcga%2Cucec_tcga_pub%2Call_stjude_2015%2Claml_tcga%2Claml_tcga_pub%2Cnbl_amc_2012%2Cmbl_broad_2012%2Cmbl_icgc%2Cmbl_pcgp%2Cesca_broad%2Cesca_tcga%2Cstad_pfizer_uhongkong%2Cstad_tcga%2Cstad_tcga_pub%2Cstad_uhongkong%2Cstad_utokyo%2Cuvm_tcga%2Cacyc_mskcc%2Cccrcc_irc_2014%2Cccrcc_utokyo_2013%2Ckirc_bgi%2Ckirc_tcga%2Ckirc_tcga_pub%2Cnccrcc_genentech_2014%2Csclc_clcgp%2Csclc_jhu%2Csclc_ucologne_2015%2Cluad_broad%2Cluad_mskcc_2015%2Cluad_tcga%2Cluad_tcga_pub%2Cluad_tsp%2Clusc_tcga%2Clusc_tcga_pub%2Ccellline_ccle_broad%2Ccellline_nci60%2Cscco_mskcc%2Cmpnst_mskcc%2Cskcm_broad%2Cskcm_broad_dfarber%2Cskcm_tcga%2Cskcm_yale%2Cdesm_broad_2015%2Cthym_tcga%2Cucs_jhu_2014%2Cucs_tcga%2Cgbm_tcga%2Cgbm_tcga_pub%2Cgbm_tcga_pub2013%2Ckich_tcga%2Ckich_tcga_pub%2Ckirp_tcga%2Cdlbc_tcga%2Cpcnsl_mayo_2015%2Cov_tcga%2Cov_tcga_pub'>$gene_id</a>");
			$data[] = array("GeneCards", "<a target=_blank href='http://www.genecards.org/cgi-bin/carddisp.pl?gene=$gene_id'>$gene_id</a>");
			$data[] = array("NCBI", "<a target=_blank href='https://www.ncbi.nlm.nih.gov/gene/?term=$gene_id'>$gene_id</a>");
			
		}
		if ($type == "clinvar") {
			for ($i=0; $i<count($clinvar_data["Accession"]);$i++) {
				$data[] = array($clinvar_data["Accession"][$i], $clinvar_data["CliSig"][$i], $clinvar_data["VarDisease"][$i], $clinvar_data["VarDiseaseDB"][$i], $clinvar_data["VarDiseaseDBID"][$i]);
			}
		}
		return array("data" => $data, "columns" => $columns);
	}


	public static function parseDetail($value) {
		$details = explode(";", $value);
		$data = array();
		foreach ($details as $detail) {
			$pair = explode("=", $detail);
			$pair[1] = "<PRE>".str_replace(",", "\n", $pair[1])."</PRE>";
			//$pair[1] = str_replace(",", "\n", $pair[1]);
			$data[] = array(VarAnnotation::getKeyLabel($pair[0]), VarAnnotation::getKeyLabel($pair[1]));
		}
		return $data;
	}	

	public static function getReportedSampleLink($value) {
		preg_match("/(.*?)_(.*)/", $value, $matches);
		if (count($matches) == 3) {
			$type = $matches[1];
			$id = $matches[2];
		}
		if ($id == "TOTAL")
			return $value;
		if ($type == "FMI")
			return $value;
		if ($type == "FoundMed")
			return $value;
		if ($type == "TCGA")
			return $value;
		if ($type == "MU") {			
			return "<a target='_blanck' href=https://dcc.icgc.org/mutations/MU$id>ICGC_$id</a>";
		}
		if ($type == "UVM")
			return "<a target='_blanck' href=https://tcga-data.nci.nih.gov/tcga/tcgaCancerDetails.jsp?diseaseType=UVM&diseaseName=Uveal%20Melanoma/$value'>$value</a>";
		return "<a target='_blanck' href=http://www.ncbi.nlm.nih.gov/pubmed/$id>$value</a>";

	}

	public function getCanonicalTrans() {
		$canonical_file = storage_path()."/data/".Config::get('onco.refseq_canonical');
		$content = file_get_contents($canonical_file);
		$list = explode("\n", $content);
		$canonical_list = array();
		foreach ($list as $item) {
			$canonical_list[$item] = '';
		}
		return $canonical_list;
	}

	
	public function processAVIAPatientData($project_id=null, $patient_id, $case_id, $type=null, $sample_id=null, $gene_id=null, $include_details=false, $include_cohort=true, $avia_table_name=null, $diagnosis=null) {
		$use_view = true;
		$var_table = VarAnnotation::getTableName();
		if ($avia_table_name != null)
			$var_table = $avia_table_name;
		if ($patient_id != null) {
			$case_condition = "";
			if ($case_id != null)
				$case_condition =  "and case_id='$case_id'";
			$sql_check_view = "select count(*) as cnt from $var_table where patient_id='$patient_id' $case_condition";
			Log::info($sql_check_view);
			$cnt = DB::select($sql_check_view)[0]->cnt;
			if ($cnt == 0)
				$use_view = false;
		}
		//$use_view = false;
		$avia_col_cat = VarAnnotation::getAVIACols();
		$avia_col_list = array();
		
		if ($use_view) {
			$avia_table_alias = "v";
			$adj_table_alias = "v";
			//$var_table = "var_sample_avia";
			if ($avia_table_name != null)
				$var_table = $avia_table_name;
		} else {
			$avia_table_alias = "a";
			$adj_table_alias = "c";
			$var_table = "var_samples";
			##### need to know genome version
			$avia_table = Config::get("site.hg19_annot_table");
		}

		foreach ($avia_col_cat as $key => $values) {
			foreach ($values as $value)
				if ($value->select_list == 'Y' || $include_details)
					$avia_col_list[] = strtolower("$avia_table_alias.".$value->column_name);
		}

		$aa_cohort_table="var_aa_cohort_oc";

		if ($type == null) {
			$type = "any";
			$project_condition = "";
		}
		$project_condition = "";
		$c1_project_condition = "";
		$c2_project_condition = "";
		$project_field = "v2.project_id as project,";
		$project_group_field = "";
		$project_table = "project_samples p2,";
		
		$c1_project_condition = "c1.project_id = p2.project_id and ";
		$c2_project_condition = "c2.project_id = p2.project_id and ";

		$project_field = "p2.project_id as project,";
		$project_group_field = "p2.project_id,";		
		
		$cohort_list = "0 as cohort, 0 as site_cohort";
		$cohort_join = "";			

		if ($include_cohort && $project_id != "any" && $project_id != "null" && $project_id != null) {
			$project_condition = "and p2.project_id = $project_id and v.patient_id = p2.patient_id and v.sample_id=p2.sample_id";
			$c1_project_condition = "c1.project_id = $project_id and ";
			$c2_project_condition = "c2.project_id = $project_id and ";
			$project_field = "$project_id as project,";	
			$project_group_field = "";	
			//for new avia_oc		
			$cohort_join = "left join var_gene_cohort c1 on $c1_project_condition $avia_table_alias.gene=c1.gene and c1.type='$type'
						left join $aa_cohort_table c2 on $c2_project_condition $avia_table_alias.gene=c2.gene and c2.aa_site=$avia_table_alias.canonicalprotpos and c2.type='$type'";
			$cohort_list = "c1.cnt as cohort, c2.cnt as site_cohort";	
			#$cohort_join = "";
			#$cohort_list = "0 as cohort, 0 as site_cohort";

		}

		
		if (!$include_cohort) {
			$project_field = "";
			$project_table = "";
			$project_group_field = "";
		}
		
		$avia_col_list = implode(",", $avia_col_list);				
		#$sample_col_list = "v.patient_id, v.case_id,chromosome, start_pos, end_pos, ref, alt,vaf, total_cov, var_cov, vaf_ratio, matched_var_cov, matched_total_cov";
		#$var_col_list = "v.sample_id,v.patient_id, v.case_id, $project_field v.chromosome, v.start_pos, v.end_pos, v.ref,v.caller, v.alt,vaf, total_cov, var_cov, vaf_ratio, matched_var_cov, matched_total_cov, normal_vaf, germline_count, somatic_count";		
		$var_col_list = "v.sample_id, v.patient_id, v.case_id,$project_field v.chromosome, v.start_pos, v.end_pos, v.ref, v.alt, v.vaf, v.total_cov, v.var_cov, v.vaf_ratio, v.matched_var_cov, v.matched_total_cov, v.caller, v.fisher_score , v.normal_total_cov, v.normal_vaf, v.exp_type";
		$case_condition = "and v.case_id='$case_id'";
		$sample_condition = "";
		if ($type == "germline")
			$sample_condition = "and v.tissue_cat='normal' and v.exp_type <> 'RNAseq'";
		if ($type == "somatic")
			$sample_condition = "and v.tissue_cat='tumor' and v.exp_type <> 'RNAseq'";
		if ($case_id == "any")
			$case_condition = "";			
		

		$exome_join = "";
		$exome_field = ", 'Y' as in_exome";		
		$type_condition = " and v.type='$type'";
		if ($type == "any") {			
			$type_condition = "";
		}
		if ($sample_id != null) {
			$samples = Sample::where("sample_id", $sample_id)->get();
			if (count($samples) > 0) {
				$exp_type = $samples[0]->exp_type;
				/*
				if (strtolower($exp_type) == "panel") {
					$exome_join = " left join var_samples v2 on v.patient_id=v2.patient_id and v.case_id=v2.case_id and v2.exp_type='Exome' and v.chromosome=v2.chromosome and v.start_pos=v2.start_pos and v.end_pos=v2.end_pos and v.ref=v2.ref and v.alt=v2.alt and v.type=v2.type";
					$exome_field = ",decode(v2.patient_id,'$patient_id', 'Y', '' ) as in_exome";
				}				
				if (strtolower($exp_type) == "rnaseq") {
					$exome_join = " left join var_samples v2 on v.patient_id=v2.patient_id and v.case_id=v2.case_id and v2.exp_type='Exome' and v.chromosome=v2.chromosome and v.start_pos=v2.start_pos and v.end_pos=v2.end_pos and v.ref=v2.ref and v.alt=v2.alt";
					$exome_field = ",decode(v2.patient_id,'$patient_id', 'Y', '' ) as in_exome";
				}
				*/
			}
			### AVIA OC
			if ($include_cohort)
				$cohort_list = "c1.cnt as cohort, c2.cnt as site_cohort";
			###
			$group_by = "";			
			$sample_condition = "and v.sample_id='$sample_id'";
			$var_col_list = "$var_col_list $exome_field";
		}
		
		if ($use_view)
			#remove distinct if CLOB included
			$sql_avia = "select distinct $var_col_list,$avia_col_list,maf,$cohort_list 					
						from $project_table $var_table v 
							$cohort_join
							$exome_join
						where 							
							v.patient_id='$patient_id'
							$project_condition
							$sample_condition
							$case_condition
							$type_condition";
		else {
			#need to know the genome version			
			$sql_avia = "select distinct $var_col_list,$avia_col_list,maf,$cohort_list 					
						from $project_table $var_table v 
							$exome_join, 
							$avia_table a
							$cohort_join
						where 
							v.chromosome = $avia_table_alias.chr and
							v.start_pos=query_start and
							v.end_pos=query_end and
							v.ref=allele1 and
							v.alt=allele2 and 
							v.patient_id='$patient_id'
							$project_condition
							$sample_condition
							$case_condition
							$type_condition";
		}
		
		if ($gene_id != null) {
			$var_col_list = "$var_col_list";			
			if ($use_view)
				$sql_avia = "select distinct $var_col_list,$avia_col_list,$cohort_list,maf 					
							from project_samples p2, $var_table v 
								$cohort_join
						where														
							p2.patient_id = v.patient_id and
							v.gene='$gene_id'
							$project_condition
							$sample_condition	
							$type_condition";
			else
				$sql_avia = "select distinct $var_col_list,$avia_col_list,$cohort_list,maf 					
							from project_cases p2, $var_table v, $avia_table a $cohort_join
						where														
							p2.patient_id = v.patient_id and							
							$avia_table_alias.gene='$gene_id' and					
							substr(v.chromosome, 4) = $avia_table_alias.chr and
							v.start_pos=query_start and
							v.end_pos=query_end and
							v.ref=allele1 and
							v.alt=allele2
							$project_condition
							$sample_condition
							$type_condition";
		} 

		$time_start = microtime(true);
		Log::info ("SQL AVIA");
		Log::info($sql_avia);
		$rows_avia = DB::select($sql_avia);
		$time = microtime(true) - $time_start;
		Log::info("Total rows: ".count($rows_avia));
		Log::info("execution time (avia): $time seconds");
		
		$time_start = microtime(true);
		$avia_data = array();
		$canonical_trans = VarAnnotation::getCanonicalTrans();
		$time = microtime(true) - $time_start;
		//Log::info("execution time (getCanonicalTrans): $time seconds");
		$time_start = microtime(true);
		$root_url = $this->root_url;
		Log::info("PARSING ROW");
		$avia_mode = VarAnnotation::is_avia();
		$qci_data = null;
		if ($gene_id != null) {
			$project = Project::getProject($project_id);
			if ($project != null) {
				$meta_list = $project->getProperty("survival_meta_list");		
				$patient_meta = $project->getPatientMetaData(true, false, false, $meta_list);
				if ($project->hasQCI())
					$qci_data = VarAnnotation::getQCIByGene($project_id, $gene_id, $type);
			}
			// get all patient's diagnosis 
			else {
				$patient_meta = Patient::getDiagnosisMeta();
				Log::info("==========================================================");
				Log::info(json_encode($patient_meta));

			}
		} else {
			$qci_data = VarAnnotation::getQCI($patient_id, $case_id, "any", $type);
			if (count($qci_data) == 0)
				$qci_data = null;
		}

		foreach ($rows_avia as $row) {
			$time_start = microtime(true);
			$so = $row->so;
			//if ($row->maf != "" && $row->maf > 0.01)
			//	continue;
			if ($row->so == "intron_variant")
				continue;
			if ($row->so == "splice_site_variant" && $type == "rnaseq")
				continue;			

			$is_canonical = true;
			$canonical_trans = $row->transcript;
			
			#### AVIA OC			
			//clinvar
			$clinvar_col = $avia_col_cat["clinvar"][0];
			//$clinsig = '';
			//$clinvar_cols = array();

			#list($clinsig, $clinvar_cols, $data) = VarAnnotation::parseClinvar($row->{strtolower($clinvar_col->column_name)}, $this->root_url);
			$clinsig = $row->clinvar__sig;	

			$clinvar_badged = "";
			if (isset($avia_col_cat["clinvar_badged"])) {
				$clinvar_badged_col = $avia_col_cat["clinvar_badged"][0];
				$clinvars = VarAnnotation::parseString($row->{strtolower($clinvar_badged_col->column_name)}, ";", "=");
				if (isset($clinvars["BADGED_LEVEL"]))
					$clinvar_badged = trim(str_replace("\"]", "", $clinvars["BADGED_LEVEL"]));
			}
			
			//cosmic
			$cosmic_col = $avia_col_cat["cosmic"][0];	
			//hgmd
			$hgmd_col = $avia_col_cat["hgmd"][0];
			$hgmd_cat = VarAnnotation::getHGMDCategory($row->{strtolower($hgmd_col->column_name)});
			//actionable hotspot
			$actionable_cols = $avia_col_cat["actionable"];
			$actionable = '';
			foreach ($actionable_cols as $actionable_col) {
				if ($row->{strtolower($actionable_col->column_name)} != '' && $row->{strtolower($actionable_col->column_name)} != '-') {
					$actionable = 'Y';
					break;
				}
			}

			$time = microtime(true) - $time_start;
			//Log::info("execution time (p1): $time seconds");
			$time_start = microtime(true);

			//prediciton
			$prediction_cols = $avia_col_cat["prediction"];
			$prediction = '';
			$prediction_details = array();
			foreach ($prediction_cols as $prediction_col) {
				if (!property_exists($row, strtolower($prediction_col->column_name)))
					continue;
				if ($row->{strtolower($prediction_col->column_name)} != '' && $row->{strtolower($prediction_col->column_name)} != '-') {
					$prediction = 'Y';
					$prediction_details[] = $prediction_col->column_name.":".$row->{strtolower($prediction_col->column_name)};
					if (!$include_details)
						break;
				}
			}
			#### AVIA OC
			//splicAI prediciton
			$spliceai_cols = $avia_col_cat["spliceai"];
			$spliceai = '';
			$spliceai_details = array();
			foreach ($spliceai_cols as $spliceai_col) {
				if (!property_exists($row, strtolower($spliceai_col->column_name)))
					continue;
				if ($row->{strtolower($spliceai_col->column_name)} != '' && $row->{strtolower($spliceai_col->column_name)} != '-') {
					$spliceai = 'Y';
					$spliceai_details[] = $spliceai_col->column_name.":".$row->{strtolower($spliceai_col->column_name)};
					if (!$include_details)
						break;
				}
			}
			//Frequency
			if ($include_details) {
				$freq_cols = $avia_col_cat["freq"];
				$freq_details = array();
				foreach ($freq_cols as $freq_col) {
					if ($row->{strtolower($freq_col->column_name)} != '' && $row->{strtolower($freq_col->column_name)} != '-')
						$freq_details[] = $freq_col->column_name.":".$row->{strtolower($freq_col->column_name)};					
				}
			}

			#### AVIA_OC no dbsnp_af
			/*
			$dbsnp_cols = $avia_col_cat["dbsnp_af"];
			list($dbsnf_maf, $detail_data) = VarAnnotation::parseDBSNP_af($row, $dbsnp_cols);						
			if ($dbsnf_maf == 0)
				$dbsnf_maf = "";
			*/
			$dbsnf_maf = "";
			#####

			//reported
			$reported_cols = $avia_col_cat["reported"];
			$total_reported = VarAnnotation::parseReported($row, $reported_cols, false);
			
			if ($total_reported == 0)
				$total_reported = "";
			//genie
			#$genie_cols = $avia_col_cat["genie"];
			#$total_genie = VarAnnotation::parseGenie($row, $genie_cols, false);
			#if ($total_genie == 0)
				$total_genie = "";

			$time = microtime(true) - $time_start;
			//Log::info("execution time (p2): $time seconds");
			$time_start = microtime(true);
			$var = (object)array("flag" => "");
			if ($sample_id != null && $type != "rnaseq")
				$var->{'signout'} = '';
			if ($type == "germline")
				$var->{'acmg_guide'} = '';
			$var->{'libraries'} = '';
			$var->{'view_igv'} = '';
			$var->{'open_cravat'} = "<a target=_blank  href='$root_url/viewVariant/$row->patient_id/$row->case_id/$row->sample_id/$type/$row->chromosome/$row->start_pos/$row->end_pos/$row->ref/$row->alt/$row->gene'><img width=15 height=15 src='$root_url/images/OpenCRAVAT_Logo-0-sm.png'></img></a>";
			$var->{'cohort'} = $row->cohort;
			$var->{'site_cohort'} = $row->site_cohort;			
			//if ($sample_id != null) {
				$var->{'sample_id'} = $row->sample_id;
				$var->{'caller'} = $row->caller;
			//}
			$var->{'patient_id'} = $row->patient_id;
			// $var->{'sample_id'} = $row->sample_id;
			
			/*
			if ($case_id == "any") {
				$case_list = explode(",", $row->case_id);
				$cases = array();
				foreach ($case_list as $c) {
					$cases[$c] = '';
				}
				$var->{'case_id'} = implode(",", array_keys($cases));
			} else
			*/
			$var->{'case_id'} = $row->case_id;
			$var->{'project'} = ($project_id == null)? "null" : $row->project;
			//$var->{'type'} = $row->type;
			$var->{'chromosome'} = $row->chromosome;
			$var->{'start_pos'} = $row->start_pos;
			$var->{'end_pos'} = $row->end_pos;
			$var->{'ref'} = $row->ref;
			$var->{'alt'} = $row->alt;
			#### AVIA OC
			//$var->{'func'} = $row->so;
			####
			$var->{'gene'} = $row->gene;
			
			#### AVIA OC
			# new column name			
			#$var->{'exonicfunc'} = $aa_info->exonicfunc;
			 $var->{'exonicfunc'} = $row->so;
			
			#if ($aa_info->exonicfunc == "-")
			#	$var->{'exonicfunc'} = "(".$row->annovar_annot.")";
			#if ($var->{'exonicfunc'} == "synonymous SNV")
			#	continue;
			if ($var->{'exonicfunc'} == "synonymous_variant")
				continue;			

			#$var->{'aachange'} = $aa_info->aachange;
			$var->{'canonicalprotpos'} = $row->canonicalprotpos;
			$var->{'aachange'} = $row->achange;
			####
			$var->{'pecan'} = "<a target=_blank  href='https://pecan.stjude.cloud/variants/proteinpaint/?genome=hg19&gene=$row->gene'>".$this->formatLabel("Y")."</a>";
			$var->{'civic'} = ($row->{strtolower($avia_col_cat["civic"][0]->column_name)} == '' || $row->{strtolower($avia_col_cat["civic"][0]->column_name)} == '-')? '' : 'Y';
			$var->{'actionable'} = $actionable;
			#### AVIA OC
			#$var->{'dbsnp'} = $row->dbsnp;
			$var->{'dbsnp'} = $row->dbsnp__rsid;
			#$var->{'dbsnp_af'} = $dbsnf_maf;
			$var->{'dbsnp_af'} = "";
			####
			$var->{'frequency'} = $row->maf;
			if ($row->maf > 0.001)
				$var->{'frequency'} = round($row->maf, 4);
			else {
				$var->{'frequency'} = ($row->maf == "" || $row->maf == 0)? $row->maf : sprintf('%.2e',$row->maf);
			}
			if ($var->{'frequency'} == 0)
				$var->{'frequency'} = '';
			if ($include_details)
				$var->{'freq_details'} = implode(",", $freq_details);
			$var->{'pop_max_gnomad2'} = $row->pop_max_gnomad2;
			$var->{'pop_max_source_gnomad2'} = $row->pop_max_source_gnomad2;
			$var->{'pop_max_gnomad3'} = $row->pop_max_gnomad3;
			$var->{'pop_max_source_gnomad3'} = $row->pop_max_source_gnomad3;
			$var->{'prediction'} = $prediction;
			if ($include_details)
				$var->{'prediction_details'} = implode(",", $prediction_details);

			#### AVIA OC
			$var->{'spliceai'} = $spliceai;
			if ($include_details)
				$var->{'spliceai_details'} = implode(",", $spliceai_details);

			#### AVIA OC
			//$var->{'clinvar'} = (count($clinvar_cols) > 0)? 'Y': '';
			$var->{'clinvar'} = ($clinsig != "")? 'Y': '';
			if ($include_details)
				$var->{'clinvar_details'} = $row->{strtolower($clinvar_col->column_name)};
			$var->{'clinvar_badged'} = $clinvar_badged;
			$var->{'cosmic'} = ($row->{strtolower($cosmic_col->column_name)} == '' || $row->{strtolower($cosmic_col->column_name)} == '-')? '' : 'Y';
			$var->{'hgmd'} = ($row->{strtolower($hgmd_col->column_name)} == '' || $row->{strtolower($hgmd_col->column_name)} == '-')? '' : 'Y';
			if (Config::get('site.isPublicSite'))
				$var->{'hgmd'} = '';
			$var->{'reported_mutations'} = $total_reported;

			#QCI annotation for TSO500
			if ($qci_data != null) {
				$qci_key = implode(":", array($var->patient_id, $var->case_id,$var->chromosome, $var->start_pos));
				$var->qci_assessment = '';
				$var->{'qci_assessment'} = '';
				$var->{'qci_actionability'} = '';				
				$var->{'qci_nooactionability'} = '';
				if (array_key_exists($qci_key, $qci_data)) {
					$qci_row = $qci_data[$qci_key];
					$var->{'qci_assessment'} = $qci_row[0];
					$var->{'qci_actionability'} = $qci_row[1];					
					$var->{'qci_nooactionability'} = $qci_row[2];
				}
			}

			//$var->{'genie'} = $total_genie;
			$var->{'germline'} = '';
			$var->{'clinvar_clisig'} = $clinsig;
			$var->{'hgmd_cat'} = ($hgmd_cat == 'Disease causing mutation')? 'Y' : '';
			#### AVIA OC
			$var->{'intervar'} = $row->intervar__significance;
			$var->{'intervar_evidence'} = $row->intervar__evidences;
			#$var->{'intervar_evidence'} = "";
			####
			$var->{'acmg'} = '';
			#### AVIA OC
			#$var->{'transcript'} = $aa_info->transcript;
			#$var->{'exon_num'} = $aa_info->exon_num;
			#$var->{'aaref'} = $aa_info->aaref;
			#$var->{'aaalt'} = $aa_info->aaalt;
			#$var->{'aapos'} = $aa_info->aapos;
			$var->{'transcript'} = $row->transcript;
			$var->{'aaref'} = "";
			$var->{'aaalt'} = "";
			$var->{'aapos'} = 0;
			if ($var->canonicalprotpos != "")
				$var->{'aapos'} = substr($var->canonicalprotpos, 3, 3);
			####

			####
			$var->{'hotspot_gene'} = '';
			$var->{'actionable_hotspots'} = '';
			$var->{'prediction_hotspots'} = '';
			$onco_kb_str = "";
			if (property_exists($row, "oncokb_actionable")) {
				if ($row->oncokb_actionable != "" && $row->oncokb_actionable != "-")
					$onco_kb_str .= $this->getDetailLink($var, 'oncokb_actionable', "<img class='flag_tooltip' title='OncoKB Actionable' width=18 height=18 src='$root_url/images/flame.png'></img></a>&nbsp;", false);
					#$onco_kb_str .= "<a href=javascript:getDetails('oncokb_actionable','$row->chromosome','$row->start_pos','$row->end_pos','$row->ref','$row->alt','$row->patient_id','$row->gene');><img class='flag_tooltip' title='OncoKB Actionable' width=18 height=18 src='$root_url/images/flame.png'></img></a>&nbsp;";
				if ($row->oncokb_api != "" && $row->oncokb_api != "-")
					$onco_kb_str .= "<a href=javascript:getDetails('oncokb_api','$row->chromosome','$row->start_pos','$row->end_pos','$row->ref','$row->alt','$row->patient_id','$row->gene');><img class='flag_tooltip' title='OncoKB All' width=18 height=18 src='$root_url/images/info2.png'></img></a>&nbsp;<a target=_blank href='https://oncokb.org/gene/$row->gene/$aa_info->aachange'><img class='flag_tooltip' title='OncoKB' width=38 height=18 src='$root_url/images/oncokb.png'></img></a>";
				$var->{'oncokb'} = $onco_kb_str;
			}
			$var->{'loss_func'} = '';
			if ($type == "somatic") {
				$var->{'germline_vaf'} = $this->formatLabel(round($row->normal_vaf, 3));
				$var->{'vaf'} = round($row->vaf, 3);
			} else
				$var->{'vaf'} = round($row->vaf, 3);
			$var->{'total_cov'} = $row->total_cov;
			$var->{'var_cov'} = $row->var_cov;
			$var->{'vaf_ratio'} = $row->vaf_ratio;
			$var->{'matched_var_cov'} = $row->matched_var_cov;
			$var->{'matched_total_cov'} = $row->matched_total_cov;
			#$var->{'germline_count'} = $row->germline_count;
			#$var->{'somatic_count'} = $row->somatic_count;
			#$var->{'adj_germline_count'} = $row->adj_germline_count;
			#$var->{'adj_somatic_count'} = $row->adj_somatic_count;
			$var->{'somatic_level'} = '';
			$var->{'germline_level'} = '';
			$var->{'dna_mutation'} = 'Y';
			if ($gene_id != null) {
				$meta_list = (isset($patient_meta))? $patient_meta["attr_list"] : [];
				for ($idx=0;$idx<count($meta_list);$idx++) {
					$meta = $meta_list[$idx];
					$var->{$meta} = $patient_meta["data"][$row->patient_id][$idx];
				}				
			} else {
				$var->{'caller'} = $row->caller;
				if (property_exists($row, "fisher_score"))
					$var->{'fisher_score'} = $row->fisher_score;
				$var->{'normal_total_cov'} = $row->normal_total_cov;
				$var->{'exp_type'} = $row->exp_type;
				if (property_exists($row, "in_exome"))
					$var->{'in_exome'} = $row->in_exome;
			}			
			$avia_data[] = $var;
			$time = microtime(true) - $time_start;
			//Log::info("execution time (p3): $time seconds");
			$time_start = microtime(true);
		}
		$time = microtime(true) - $time_start;
		//Log::info("execution time (loop): $time seconds");
		Log::info("Total :". count($avia_data));
		return $avia_data;
	}

	public static function getAVIACols() {
		$avia_cols = DB::table('var_avia_cols_oc')->get();
		$avia_col_cat = array();
		foreach ($avia_cols as $col) {
			if ($col->type != '')
				$avia_col_cat[$col->type][] = $col;
		}
		return $avia_col_cat;
	}

	public static function getAVIAColsDesc() {
		$sql = "select o.*,a.column_name as cname, annotator,column_description from var_avia_cols_oc o left join var_avia_cols_oc_all a on lower(o.column_name)=a.table_column_name";
		Log::info($sql);
		$avia_cols = DB::select($sql);
		$avia_col_cat = array();
		foreach ($avia_cols as $col) {
			if ($col->type != '')
				$avia_col_cat[$col->type][] = $col;
		}
		return $avia_col_cat;
	}
	
	public static function parseString($str, $sep1, $sep2, $sep3 = null) {		
		$arr = array();
		$rows = explode($sep1, $str);
		foreach ($rows as $row) {
			if ($row == '')
				continue;
			$values = explode($sep2, $row);
			if (count($values) != 2)
				continue;
			$name = $values[0];
			if ($name == "")
				continue;
			$value = $values[1];
			if ($sep3 != null) {
				$detail_values = explode($sep3, $value);
				$arr[$name] = $detail_values;
			} else
				$arr[$name] = $value;
		}	
		return $arr;		
	}

	public static function parseCosmic($cosmic_str) {
		$cols = array();
		$data = array();
		$ids = array();
		if ($cosmic_str != '-') {
			$cosmic_id = "ID";
			//$cosmics = VarAnnotation::parseString($cosmic_str, "|", ";", "=");
			$rows = explode("|", $cosmic_str);
			foreach ($rows as $row) {
				$cosmics = VarAnnotation::parseString($row, ";", "=");
				if (count($cols) == 0) {
					foreach ($cosmics as $col_name => $value)
						$cols[] = array("title" => $col_name);
				}
				$values = array();
				$dup = false;
				foreach ($cosmics as $col_name => $value) {
					if ($col_name == $cosmic_id) {
						$id = str_replace("COSM", "", $value);
						if (array_key_exists($id, $ids)) {
							$dup = true;
							$ids[$id] = '';
							break;							
						}
						$value = "<a target=_blank href='http://grch37-cancer.sanger.ac.uk/cosmic/mutation/overview?id=$id'>$value</a>";
					}
					$values[] = "<PRE style='text-align:left'>".str_replace (',', "\n", $value)."</PRE>";
				}
				if (!$dup)
					$data[] = $values;
			}						
			/*
			$col_names = array_keys($cosmics);
			foreach ($col_names as $col_name)
				$cols[] = array("title" => $col_name);	
			
			foreach ($cosmics as $name => $value_str) {
				$row_data = array();
				$values = explode(',', $value_str);
				$cosmic_value = "";
				$new_values = array();
				foreach ($values as $value) {
					if ($name == $cosmic_id) {
						$id = str_replace("COSM", "", $value);
						$value = "<a target=_blank href='http://grch37-cancer.sanger.ac.uk/cosmic/mutation/overview?id=$id'>$value</a>";
					}
					$new_values[] = $value;
				}				
				$data[] = array($name, "<PRE>".implode("\n", $new_values)."</PRE>");
			}
			*/
		}
		return array($cols, $data);		

	}

	public static function getHGMDCategory($hgmd_str) {
		if ($hgmd_str != '-' && $hgmd_str != '') {			
			$hgmd = VarAnnotation::parseString($hgmd_str, ";", "=");
			foreach ($hgmd as $key => $value) {
				if (strtolower($key) == 'hgmd_category')
					return $value;
			}
		}
		return "";
	}

	public static function parseHGMD($hgmd_str) {
		$cols = array(array("title" => "Key"), array("title" => "Value"));
		$data = array();
		$hgmd_url = Config::get('onco.hgmd_url');
		$hgmd_gene_url = Config::get('onco.hgmd_gene_url');
		$pubmed_url = Config::get('onco.pubmed_url');
		$hgmd_hint = Config::get('onco.hgmd_hint');
		#Log::info($hgmd_gene_url);
		#Log::info($hgmd_url);
		if ($hgmd_str != '-' && $hgmd_str != '') {
			$hgmd = VarAnnotation::parseString($hgmd_str, ";", "=");
			foreach ($hgmd as $key => $value) {					
				if (strtolower($key) == 'hgmd_genename') {
					$value_str = "<PRE><span class='hgmdTip' title='$hgmd_hint' href='#'><a target='_blank' href='$hgmd_gene_url$value'>$value</a></span></PRE>";
					$data[] = array('Gene', $value_str);
				} elseif (strtolower($key) == 'hgmd_accno') {
					$value_str = "<PRE><span class='hgmdTip' title='$hgmd_hint' href='#'><a target='_blank' href='$hgmd_url$value'>$value</a>";
					$data[] = array('Accession', $value_str);
					$refs = DB::table('hgmd_ref')->where('hgmd_accno', trim($value))->get();
					$url_strs = [];
					foreach ($refs as $ref) {
						$lists = explode(',', $ref->ref_list);
						foreach ($lists as $list) {
							$url_strs[] = "<a target='_blank' href='$pubmed_url$list'>$list</a>";
						}						
					}
					$data[] = array('Reference', "<PRE>".implode("\n", $url_strs)."</PRE>");
				}
				else
					$data[] = array(str_replace("hgmd_", "", $key), $value);

			}
		}
		return array($cols, $data);	
	}

	public static function parseCivic($civic_str) {
		$cols = array(array("title" => "Key"), array("title" => "Value"));
		$data = array();
		if ($civic_str != '-' && $civic_str != '') {
			$civic = VarAnnotation::parseString($civic_str, ";", "=");
			foreach ($civic as $key => $value) {
				if (substr($key, -4) == "_url") {
					$urls = explode("|", $value);
					$new_values = array();
					foreach ($urls as $url) {
						$new_values[] = "<a target=blank href='$url'>$url</a>";
					}
					$value = implode(" | ", $new_values);
				}
				$key = ucfirst($key);				
				$data[] = array(str_replace("hgmd_", "", $key), $value);
			}
		}
		return array($cols, $data);	
	}
	/*
	public static function parseCivic($civic_str) {
		$cols = array(array("title" => "Key"), array("title" => "Value"));
		$data = array();
		if ($civic_str != '-' && $civic_str != '') {
			$civic = VarAnnotation::parseString($civic_str, ";", "=");
			foreach ($civic as $key => $value) {
				if (substr($key, -4) == "_url") {
					$urls = explode("|", $value);
					$new_values = array();
					foreach ($urls as $url) {
						$new_values[] = "<a target=blank href='$url'>$url</a>";
					}
					$value = implode(" | ", $new_values);
				}
				$key = ucfirst($key);				
				$data[] = array(str_replace("hgmd_", "", $key), $value);
			}
		}
		return array($cols, $data);	
	}
	*/

	
	public static function parseClinvar($clinvar_str, $root_url = null) {
		if ($root_url == null)
			$root_url = url("/");
		$pathogenic = "";
		//Datatable format detail data
		$cols = array();
		$data = array();
		if ($clinvar_str != '-' && $clinvar_str != '') {
			Log::info($clinvar_str);
			$clinvars = VarAnnotation::parseString($clinvar_str, ";", "=", "|");
			### AVIA OC
			#$clinsig_name = "CLNSIG";
			#$acc_name = "CLNACC";
			#$review_status = "CLNREVSTAT";

			$clinsig_name = "clinvar_sig";
			$acc_name = "clinvar_id";
			$review_status = "CLNREVSTAT";

			if (isset($clinvars[$clinsig_name])) {
				$total_row = count($clinvars[$clinsig_name]);
				$col_names = array_keys($clinvars);
				foreach ($col_names as $col_name)
					$cols[] = array("title" => VarAnnotation::getKeyLabel($col_name));
				for ($i=0;$i<$total_row;$i++) {
					$row_data = array();
					foreach ($clinvars as $key => $values) {
						if (!isset($values[$i]))
							continue;
						$value = $values[$i];
						if (strtolower($key) == strtolower($clinsig_name)) {
							if (strtolower($value) == "pathogenic" || strtolower($value) == "likely pathogenic" || strtolower($value) == "drug response")
								$pathogenic = "Y";
						}
						if (strtolower($key) == strtolower($acc_name)) {
							$accs = explode(',', $value);
							$value = '';
							foreach ($accs as $acc) {
								$value .= "<a target=_blank href='http://www.ncbi.nlm.nih.gov/clinvar/$acc'>$acc</a>&nbsp;";
							}
						}
						if (strtolower($key) == strtolower($review_status)) {
							$stars = $value;
							$value = "<h4>";
							for ($j=0;$j<5;$j++) {
								if ($j < $stars)
									$value .= "<img width=18 height=18 src='$root_url/images/star_yellow.png'></img>";
								else
									$value .= "<img width=18 height=18 src='$root_url/images/star_empty.png'></img>";
							}
							$value .= "</h4>";
						}
						$row_data[] = $value;
					}
					$data[] = $row_data;
				}
				for ($i=0;$i<count($data);$i++) {
					if (count($data[$i]) != count($cols)) {
						for ($j=0;$j<(count($cols)-count($data[$i]));$j++)
							$data[$i][] = '';
					}
				}
			} else {
				Log::info("not found: $clinvar_str");
			}
		}
		return array($pathogenic, $cols, $data);
	}

	public static function parseGene($avia_row, $cols) {
		$detail_data = array();
		foreach ($cols as $col) {
			$col_name = strtolower($col->column_name);
			$cat_name = ($col->class == '')? $col->column_name : $col->class;
			
			$value_str = $avia_row->{$col_name};			
			$value_arr = array();				
			if ($value_str != "" && $value_str != "-") {
				$values = explode(",", $value_str);
				foreach ($values as $value) {					
					if ($value != "")
						$value_arr[] = implode("\n", explode(";",$value));
				}				
				$value = "<PRE>".implode("\n", $value_arr)."</PRE>";
				$detail_data[] = array(VarAnnotation::getKeyLabel($col_name), $value);
			}			
		}
		return $detail_data;
	}

	public static function parseDBSNP_af($avia_row, $freq_cols) {
		$maf = 0;
		$detail_data = array();		
		foreach ($freq_cols as $freq_col) {
			$col_name = strtolower($freq_col->column_name);
			$cat_name = ($freq_col->class == '')? $freq_col->column_name : $freq_col->class;
			$sep = ";";
			$col_values = $avia_row->{$col_name};
			$parsed = VarAnnotation::parseString($col_values, $sep, "=");					
			foreach ($parsed as $parsed_key => $parsed_value) {
				#Log::info("$parsed_key => $parsed_value");
				if ($parsed_value == "." || $parsed_value == "0")
					continue;
						#$exac_name = substr($exac_name, $prefix_len);
				$detail_data[] = array($cat_name, $parsed_key, "<PRE>".$parsed_value."</PRE>");
				if ($parsed_value > $maf)
					$maf = $parsed_value;
			}

		}
		return array($maf, $detail_data);

	}

	public static function parseFrequency($avia_row, $freq_cols) {
		$maf = 0;
		$detail_data = array();
		$cols = array();
		foreach ($freq_cols as $freq_col) {
			$col_name = strtolower($freq_col->column_name);
			$cols[$col_name] = $freq_col;
		}
		foreach ($cols as $col_name => $freq_col) {
			$cat_name = ($freq_col->class == '')? $freq_col->column_name : $freq_col->class;
			
			if ($col_name == "exac_af" || $col_name == "exacnontcga_af" || $col_name == "gnomad_exome" || $col_name == "gnomad_genome" || $col_name == "dbsnp_af") {
			#s	Log::info($col_name);
				
				$freq_str = $avia_row->{$col_name};	

				if ($freq_str != "-" && $freq_str != "" && $freq_str != "0") {
					#$sep = ($col_name == "gnomad_exome")? ";" : ";";
					$sep = ";";
					$freqs = VarAnnotation::parseString($freq_str, $sep, "=");
					$prefix_len = ($col_name == "exac_af")? 5 : 13;
					if ($col_name == "gnomad_exome" || $col_name == "gnomad_genome")
						$prefix_len = strlen($col_name) + 1;
					if ($col_name == "dnsnp_af") $prefix_len=0;
					//check if there is count info					
					$count_col_name = $col_name."counts";
					if (array_key_exists($count_col_name, $cols)) {
						$count_str = $avia_row->{$count_col_name};	
						$counts = VarAnnotation::parseString($count_str, $sep, "=");
					} else
						$counts = array();
					foreach ($freqs as $freq_name => $freq_value) {
						$ac = "NA";
						$an = "NA";
						if ($freq_value == "." || $freq_value == "0")
							continue;
						$sub_group = substr($freq_name, $prefix_len);
						$ac_name = "AC_".$sub_group;
						$an_name = "AN_".$sub_group;
						if (array_key_exists($ac_name, $counts))
							$ac = $counts[$ac_name];
						if (array_key_exists($an_name, $counts))
							$an = $counts[$an_name];
					
						#$exac_name = substr($exac_name, $prefix_len);
						$detail_data[] = array(str_replace("_AF","",strtoupper($col_name)), $sub_group, "<PRE>".$freq_value."</PRE>","<PRE>".$ac."</PRE>", "<PRE>".$an."</PRE>");
						
						if ($col_name == "exacnontcga_af" && $freq_value > $maf)
							$maf = $freq_value;
						
					}
				}
				continue;
			}
			
			$freq = $avia_row->{$col_name};
			if (!is_numeric($freq))
				continue;
			if ($freq == 0)
				continue;

			if ($freq_col->additional_info == "Y") {
				if ($freq > $maf)
					$maf = $freq;				
			}
						
			$detail_data[] = array(strtoupper($cat_name), strtoupper($col_name), "<PRE>".$freq."</PRE>", "<PRE>NA</PRE>","<PRE>NA</PRE>");
		}
#		Log::info($detail_data);
		return array($maf, $detail_data);

	}
//	public static getVariantsByQuery($start,$end,$ref,$alt){

//	}
	

	public static function parseGenie($avia_row, $cols, $details=true) {
		$total_count = 0;
		$detail_data = array();
		foreach ($cols as $col) {
			if (strtolower($col->column_name) != 'genie_counts')
				continue;
			$values = (array)json_decode($avia_row->{strtolower($col->column_name)});
			ksort($values);				
			foreach ($values as $key => $value) {
				$cancer_type = strtoupper($key);
				$total_count += $value;
				if ($details) {
					$diag = new \stdClass();
					$diag->primary_name = '';
					$diag->metamaintype = '';
					$diag->metanci = '';
					$diag->metaumls = '';
					$d = Diagnosis::getDiagnosisByCode($cancer_type);
					if ($d != null)
						$diag = $d;
					$detail_data[] = array($diag->primary_name, $cancer_type, $diag->metamaintype, "<a target=_blank href=https://ncit.nci.nih.gov/ncitbrowser/ConceptReport.jsp?dictionary=NCI_Thesaurus&code=$diag->metanci>$diag->metanci</a>", "<a target=_blank href=https://ncim.nci.nih.gov/ncimbrowser/ConceptReport.jsp?code=$diag->metaumls>$diag->metaumls</a>", $value);
				}
			}
		}
		if ($details)
			return array($total_count, $detail_data);
		else
			return $total_count;
	}

	public static function parseReported($avia_row, $cols, $details=true) {
		$total_count = 0;
		$detail_data = array();
		$icgc_url = "https://dcc.icgc.org/";
		
		foreach ($cols as $col) {			
			$col_name = strtolower($col->column_name);
			$col_text = VarAnnotation::getKeyLabel($col_name);
			if (!property_exists($avia_row, $col_name))
				continue;
			$value_str = $avia_row->{$col_name};
			if ($value_str == "-" || $value_str == "")
				continue;
			if ($col_name == "icgc__icgc") {
				$tbl_html = ""; 
				$header_html = ""; 
				$icgc_donors = 0;
				$total_projects = 0;
				
				$values = VarAnnotation::parseString($value_str, ";", "=");
				foreach ($values as $key => $value) {
					if (strtolower($key) == "id")
						$header_html = "<div align='left'>ID: <a target=_blank href='$icgc_url/mutations/$value'>$value</a></div>";
					if (strtolower($key) == "occurrence") {
						$tbl_html = "<table class='var_dtl_info' width='100%' border=1><thead><tr color='#FFFFFF'><th>Project</th><th>Donors</th><th>Total Donors</th><th>Frequency</th></tr></thead><tbody>";
						$icgc_rows = explode(",", $value);
						$total_projects = count($icgc_rows);
						foreach ($icgc_rows as $row) {
							$icgc_cells = explode("|", $row);
							$tbl_html .= "<tr>";								
							for ($i = 0; $i< count($icgc_cells); $i++) {
								$cell = $icgc_cells[$i];
								if ($i == 0)
									$cell = "<a target=_blank href='$icgc_url/projects/$cell'>$cell</a>";
								if ($i == 1) {
									$total_count += $cell;
									$icgc_donors += $cell;
								}
								$tbl_html .= "<td>$cell";
							}
							$tbl_html .= "</tr>";
						}
						//$header_html .= "$icgc_donors DISTINCT DONORS ACROSS $total_projects CANCER PROJECTS</H3> ";
						$tbl_html .= "</tbody></table>";
						$detail_data[] = array("<pre>$col_text</pre>", "<pre>$icgc_donors</pre>", "$header_html"." ".$tbl_html);
						#Log::info("<pre>$col_text</pre>");
					}	

				}
				
			}			
			if ($col_name == "pcg__pcg") {
				$pcg_count = 0;
				if ($details)
					$pcg_list = Config::get('onco.pcg_list');				
				$values = explode(":", $value_str);
				$tbl_html = "<table class='var_dtl_info' width='100%' border=1><thead><tr><tr color='#FFFFFF'><th>Cancer</th><th>URL</th><th>Count</th></tr></thead><tbody>";
				
				for ($i=2;$i<count($values)-2;$i++) {
					if ($values[$i] > 0) {
						$pcg_count += $values[$i];
						$total_count += $values[$i];
						if ($details) {
							$pcg_id = $pcg_list[$i-2];
							$pcg_id_arr = explode("_", $pcg_id);
							$type = $pcg_id_arr[0];
							$pubid = $pcg_id_arr[1];
							array_shift($pcg_id_arr);
							$url = "";
							$value = implode("_", $pcg_id_arr);
							if ($type == "UVM")
								$url = "<a target='_blank' href=https://tcga-data.nci.nih.gov/tcga/tcgaCancerDetails.jsp?diseaseType=UVM&diseaseName=Uveal%20Melanoma/$value'>$value</a>";
							else if ($type == "FMI" || $type == "FoundMed")
								$url = $pcg_id;
							else
								$url = "<a target='_blank' href=http://www.ncbi.nlm.nih.gov/pubmed/$pubid>$pcg_id</a>";
							$tbl_html .= "<tr><td>$type</td><td>$url</td><td>".$values[$i]."</td></tr>";
						}
					}
				}
				$tbl_html .= "</tbody></table>";
				if ($pcg_count > 0)
					$detail_data[] = array("<pre>$col_text</pre>", "<pre>$pcg_count</pre>", $tbl_html);
			}
			//if ($col_name == "tcga_counts" || $col_name == "genie_counts") {
			if ($col_name == "tcga_counts" || $col_name == "genie__genie_counts" || $col_name == "cbio__cbio_counts") {				
				$tcga_count = 0;
				if ($value_str == "-" || $value_str == "")
					continue;
				#### AVIA OC json should be double quote
				$value_str = str_replace("'", "\"",$value_str);
				####
				$values = (array)json_decode($value_str);
				$count_header = "Count";				
				$dtl_values = "";
				if ($col_name == "tcga_counts")
					$dtl_values = $avia_row->tcga;
				if ($col_name == "cbio__cbio_counts" && $details)
					$dtl_values = $avia_row->cbio__cbio;
				### use genie__genie_counts because genie__genie might be truncated
				#if ($col_name == "genie__genie_counts" & $details)
				#	$dtl_values = $avia_row->genie__genie;
				if ($dtl_values != "") {
					#### AVIA OC json should be double quote
					$dtl_values = str_replace("'", "\"",$dtl_values);
					####
					$values = (array)json_decode($dtl_values);
					$count_header = "Sample";											
				}
				$tbl_html = "<table class='var_dtl_info' width='100%' border=1><thead><tr color='#FFFFFF'><th>Tissue</th><th>Code</th><th>Main Type</th><th>NCI Thesaurus</th><th>UMLS</th><th>${count_header}</th></tr></thead><tbody>";
				
				foreach ($values as $cancer_type => $cancer_count) {
					$samples = array();
					
					if ($dtl_values != "") {
						$samples = $cancer_count;
						$cancer_count = count($samples);
					}
					$tcga_count += $cancer_count;
					$total_count += $cancer_count;
					//Log::info("cancer_count: $cancer_count");
					if ($details) {
						$diag = new \stdClass();
						$diag->primary_name = '';
						$diag->metamaintype = strtoupper($cancer_type);
						$diag->metanci = '';
						$diag->metaumls = '';
						$d = Diagnosis::getDiagnosisByCode(strtoupper($cancer_type));
						if ($d != null)
							$diag = $d;
						if ($dtl_values != "") {
							foreach($samples as $sample) {
								$sample_id = $sample[0];
								$tbl_html .= "<tr><td>$diag->primary_name</td>";
								$tbl_html .= "<td>".strtoupper($cancer_type)."</td>";
								$tbl_html .= "<td>$diag->metamaintype</td>";
								$tbl_html .= "<td><a target=_blank href=https://ncit.nci.nih.gov/ncitbrowser/ConceptReport.jsp?dictionary=NCI_Thesaurus&code=$diag->metanci>$diag->metanci</a></td>";
								$tbl_html .= "<td><a target=_blank href=https://ncim.nci.nih.gov/ncimbrowser/ConceptReport.jsp?code=$diag->metaumls>$diag->metaumls</a></td>";
								$tbl_html .= "<td>$sample_id</td></tr>";
							}
						} else {
							$tbl_html .= "<tr><td>$diag->primary_name</td>";
							$tbl_html .= "<td>".strtoupper($cancer_type)."</td>";
							$tbl_html .= "<td>$diag->metamaintype</td>";
							$tbl_html .= "<td><a target=_blank href=https://ncit.nci.nih.gov/ncitbrowser/ConceptReport.jsp?dictionary=NCI_Thesaurus&code=$diag->metanci>$diag->metanci</a></td>";
							$tbl_html .= "<td><a target=_blank href=https://ncim.nci.nih.gov/ncimbrowser/ConceptReport.jsp?code=$diag->metaumls>$diag->metaumls</a></td>";
							$tbl_html .= "<td>$cancer_count</td></tr>";
						}
					}
				}
				$tbl_html .= "</tbody></table>";
				#Log::info("tcga_count: $tcga_count");
				if ($tcga_count > 0) {
					$detail_data[] = array("<pre>$col_text</pre>", "<pre>$tcga_count</pre>", $tbl_html);
					#Log::info("--------------<pre>$col_text</pre>");
				}
			}
			
						
		}
		//Log::info("total count: $total_count");
		if ($details)
			return array($total_count, $detail_data);
		else
			return $total_count;

	}

	private function init_patient($project_id, $patient_id, $case_id, $type, $use_table=false) {
		Log::info("init_patient");
		//return $this->init_patient_avia($project_id, $patient_id, $case_id, $type, $use_table);
		$id = "$patient_id-$project_id-$case_id-$type";
				$anno_table = "var_annotation";
		$var_table = "var_samples";
		$acmg_field = ($type == "germline")? "'' as acmg_guide, " : "";
		$signout_field = ($type != "rnaseq")? "'' as signout, " : "";
		$patients = Patient::where('patient_id', $patient_id)->get();
		if (count($patients) == 0)
			return;
		$diagnosis = $patients[0]->diagnosis;
		$diagnosis = str_replace ("'", "''", $diagnosis);
		$germline_vaf = "";
		if ($type == "somatic") {
			$germline_vaf = "v.normal_vaf as germline_vaf,"; 
		}
		$exome_join = "";
		$exome_field = ", 'Y' as in_exome";
		$rows = [];
		$cache_mode = Config::get('onco.cache.var');
		if ($cache_mode) {
			$var_cache = OncoCache::find($id);
			if ($var_cache) {
				$rows = json_decode($var_cache->data);
				list($this->data, $this->columns) = $this->postProcessVarData($rows, $project_id, $type);
				return;
			}
		}
		
	
		$time_start = microtime(true);
		//$rows = DB::select($sql);
		$avia_mode = VarAnnotation::is_avia();
		if ($avia_mode)
			$rows = $this->processAVIAPatientData($project_id, $patient_id, $case_id, $type);
		else {
			$rows = $this->processKhanlabPatientData($project_id, $patient_id, $case_id, $type);
			//processKhanlabPatientData($project_id=null, $patient_id, $case_id, $type=null, $sample_id=null, $gene_id=null, $include_details=false, $include_cohort=true) 
			//$rows = DB::select($sql);
		}
		$time = microtime(true) - $time_start;
		Log::info("execution time (SQL query): $time seconds");
		if ($cache_mode) {
			$var_cache = new OncoCache;
			$var_cache->id = $id;
			$var_cache->data = json_encode($rows);
			$var_cache->save();
			//$var_cache = User::create(array('id' => $id, data => '$data'));
		}
		$found_hotspots = null;
		if ($type == "hotspot") {
			//Log::debug($sql);
			$time_start = microtime(true);
			$var_table = "var_samples";
		//	$sql = "select v1.chromosome,v1.start_pos,v1.end_pos,v1.ref,v1.alt from $var_table v1 where v1.patient_id = '$patient_id' and v.case_id ='$case_id' (v1.type = 'germline' or v1.type = 'somatic') and 
		//			exists (select * from $var_table v2 where v2.patient_id = '$patient_id' and v.case_id ='$case_id' v2.type='hotspot' and
		//				v1.chromosome=v2.chromosome and
		//				v1.start_pos=v2.start_pos and
		//				v1.end_pos=v2.end_pos and
		//				v1.ref=v2.ref and
		//				v1.alt=v2.alt)";
		$sql="select v1.chromosome,v1.start_pos,v1.end_pos,v1.ref,v1.alt from $var_table v1 where v1.patient_id = '$patient_id' and v1.case_id = '$case_id' and (v1.type = 'germline' or v1.type = 'somatic') and 
					exists (select * from $var_table v2 where v2.patient_id = '$patient_id' and v2.case_id = '$case_id' and v2.type='hotspot' and
						v1.chromosome=v2.chromosome and
						v1.start_pos=v2.start_pos and
						v1.end_pos=v2.end_pos and
						v1.ref=v2.ref and
						v1.alt=v2.alt)";
		Log::info($sql);
			$hotspot_rows = DB::select($sql);
			Log::info(count($hotspot_rows));
			if(count($hotspot_rows)>0){ 
				$found_hotspots = array();
				foreach ($hotspot_rows as $hotspot_row) {
					$var_pk = $hotspot_row->chromosome."_".$hotspot_row->start_pos."_".$hotspot_row->end_pos."_".$hotspot_row->ref."_".$hotspot_row->alt;
					$found_hotspots[$var_pk] = '';
				}
			}
			$time = microtime(true) - $time_start;
			Log::debug("execution time (found_hotspots): $time seconds");
		}		

		list($this->data, $this->columns) = $this->postProcessVarData($rows, $project_id, $type, $found_hotspots);		
	}

	// khanlab annotation is not supported anymore
	static public function is_avia() {
		return true;
		#Log::info("setting:".UserSetting::getSetting("default_project", false));
		#return (UserSetting::getSetting("default_annotation", false) == "avia");
	}
	private function init_sample($project_id, $patient_id, $sample_id, $case_id, $type) {
		$anno_table = "var_annotation";
		$var_table = "var_samples";
		$acmg_field = ($type == "germline")? "'' as acmg_guide, " : "";
		$signout_field = ($type != "rnaseq")? "'' as signout, " : "";
		$patients = Patient::where('patient_id', $patient_id)->get();
		if (count($patients) == 0)
			return;
		$diagnosis = $patients[0]->diagnosis;
		$diagnosis = str_replace ("'", "''", $diagnosis);
		$germline_vaf = "";
		if ($type == "somatic") {
			$germline_vaf = "v.normal_vaf as germline_vaf,"; 
		}
		$exome_join = "";
		$exome_field = ", 'Y' as in_exome";
		
		$sample_condition = "";
		if ($type == "germline")
			$sample_condition = "v.tissue_cat='normal' and v.exp_type <> 'RNAseq' and";
		if ($type == "somatic")
			$sample_condition = "v.tissue_cat='tumor' and v.exp_type <> 'RNAseq' and";

		$samples = Sample::where("sample_id", $sample_id)->get();
		if (count($samples) > 0) {
			$exp_type = $samples[0]->exp_type;
			if (strtolower($exp_type) == "panel") {
				$exome_join = " left join var_samples v2 on v.patient_id=v2.patient_id and v.case_id=v2.case_id and v2.exp_type='Exome' and v.chromosome=v2.chromosome and v.start_pos=v2.start_pos and v.end_pos=v2.end_pos and v.ref=v2.ref and v.alt=v2.alt and v.type=v2.type";
				$exome_field = ",decode(v2.patient_id,'$patient_id', 'Y', '' ) as in_exome";
			}
		}


		

		//print $sql;
		//return;
		$time_start = microtime(true);
		$avia_mode = VarAnnotation::is_avia();
		if ($avia_mode)
			$rows = $this->processAVIAPatientData($project_id, $patient_id, $case_id, $type, $sample_id);
		else
			$rows = $this->processKhanlabPatientData($project_id, $patient_id, $case_id, $type, $sample_id);
		$time = microtime(true) - $time_start;
		Log::info("execution time: $time seconds");
		
		//if hotspot then find them in germline and somatic
		$found_hotspots = null;
		if ($type == "hotspot") {
			$time_start = microtime(true);
			$sql = "select v1.chromosome,v1.start_pos,v1.end_pos,v1.ref,v1.alt from $var_table v1 where v1.patient_id = '$patient_id' and v1.case_id = '$case_id' and v1.sample_id='$sample_id' and (v1.type = 'germline' or v1.type = 'somatic') and 
					exists (select * from $var_table v2 where v2.patient_id = '$patient_id' and v2.case_id = '$case_id' and v2.sample_id='$sample_id' and v2.type='hotspot' and
						v1.chromosome=v2.chromosome and
						v1.start_pos=v2.start_pos and
						v1.end_pos=v2.end_pos and
						v1.ref=v2.ref and
						v1.alt=v2.alt)";
			$hotspot_rows = DB::select($sql);

			$found_hotspots = array();
			foreach ($hotspot_rows as $hotspot_row) {
				$var_pk = $hotspot_row->chromosome."_".$hotspot_row->start_pos."_".$hotspot_row->end_pos."_".$hotspot_row->ref."_".$hotspot_row->alt;
				$found_hotspots[$var_pk] = '';
			}
			$time = microtime(true) - $time_start;
			Log::info("execution time (found_hotspots): $time seconds");
		}
		
		list($this->data, $this->columns) = $this->postProcessVarData($rows, $project_id, $type, $found_hotspots);
	}

	private function init_gene($project_id, $gene_id, $type, $use_table=false) {
		
		$time_start = microtime(true);
		$rows = $this->processAVIAPatientData($project_id, null, null, $type, null, $gene_id);
		
		$time = microtime(true) - $time_start;
		Log::info("execution time: $time seconds");

		//data structure for lollipop plot data
		$this->mutPlotData = new \stdClass();
		$this->mutPlotData->sample = new \stdClass();
		$this->mutPlotData->ref = new \stdClass();
		$this->mutPlotData->sample->data = array();
		$this->mutPlotData->sample->range = array("start_pos" => 99999, "end_pos" => 0);
		$this->mutPlotData->ref->data = array();
		$this->mutPlotData->ref->range = array("start_pos" => 99999, "end_pos" => 0);

		list($this->data, $this->columns) = $this->postProcessVarData($rows, $project_id, $type);
	}

	

	function getActionable($patient_id, $type) {
		$vars = DB::table('var_actionable_site')->where('patient_id', $patient_id)->where('type', $type)->get();
		$sites = array();
		foreach ($vars as $var) {
			$key = $var->chromosome.$var->start_pos.$var->end_pos.$var->ref.$var->alt;
			$sites[$key]['source'] = $var->var_source;
			$sites[$key]['level'] = $var->var_level;
		}
		return $sites;


	}

	function getFlags($type) {
		$flags = VarFlag::getAll($type);
		$flag_list = array();
		foreach($flags as $flag) {			
			$key = implode('_', array($flag->chromosome, $flag->start_pos, $flag->end_pos, $flag->ref, $flag->alt));
			//$patient_id = (is_numeric($flag->patient_id))? "p".$flag->patient_id : $flag->patient_id;
			$patient_id = $flag->patient_id;
			$flag_list[$key][$patient_id][$flag->is_public] = $flag->status;
		}
		return $flag_list;
	}

	function getACMGGuides() {
		$acmg_guides = VarACMGGuide::getAll();
		$acmg_list = array();
		foreach($acmg_guides as $acmg_guide) {			
			$key = implode('_', array($acmg_guide->chromosome, $acmg_guide->start_pos, $acmg_guide->end_pos, $acmg_guide->ref, $acmg_guide->alt));
			//$patient_id = (is_numeric($flag->patient_id))? "p".$flag->patient_id : $flag->patient_id;
			$patient_id = $acmg_guide->patient_id;
			$acmg_list[$key][$patient_id][$acmg_guide->is_public] = $acmg_guide->class;
		}
		return $acmg_list;
	}

	function getFlagURL($var_id, $gene, $type, $status) {
		$root_url = $this->root_url;
		$flag_id = "flag_$var_id";
		$image_file = "$root_url/images/circle_green.png";
		$hint = "No comments about this variant in this patient";
		if ($status == 1) {
			$hint = "This variant has been commented";
			$image_file = "$root_url/images/circle_yellow.png";
		}
		if ($status == 2) {
			$hint = "The comments of this variant has been closed";
			$image_file = "$root_url/images/circle_red.png";
		}
		return "<a id='$flag_id' class='flag_tooltip' title='$hint' href=\"javascript:showFlagDetails('$var_id', '$gene', '$type', '$status')\"><img id='img_$var_id' width=18 height=18 src='".$image_file."'></img></a>";
	}

	function getACMGGuideURL($var_id, $var, $class) {

		//$hint = "No interpreation about this variant in this patient";
		$acmg_id = "acmg_$var_id";
		#		Log::info("<a id='$acmg_id' class='flag_tooltip' href=\"javascript:showACMGGuide('$var_id', '$var->gene', '$var->patient_id', '$var->frequency', '$var->exonicfunc', '$var->loss_func', '$var->clinvar', '$var->hgmd', '$var->acmg', '$var->clinvar_clisig', '$var->hgmd_cat', '$var->reported_mutations', '$var->actionable_hotspots')\">".$class."</a>");
		return "<a id='$acmg_id' class='flag_tooltip' href=\"javascript:showACMGGuide('$var_id', '$var->gene', '$var->patient_id', '$var->frequency', '$var->exonicfunc', '$var->loss_func', '$var->clinvar', '$var->hgmd', '$var->acmg', '$var->clinvar_clisig', '$var->hgmd_cat', '$var->reported_mutations', '$var->actionable_hotspots')\">".$class."</a>";
	}
	
	
	static function getFlagStatus($chromosome, $start_pos, $end_pos, $ref, $alt, $type, $patient_id) {
		$sql = "select status from var_flag where chromosome='$chromosome' and start_pos='$start_pos' and end_pos='$end_pos' and ref='$ref' and alt='$alt' and patient_id='$patient_id'";
		$rows = DB::select($sql);
		if (count($rows) == 0)
			return 0;
		else
			return $rows[0]->status;
	}

	static function getFlagHistory($chromosome, $start_pos, $end_pos, $ref, $alt, $type, $patient_id) {		
		$sql = "select distinct v.patient_id, v.type, v.var_comment, v.is_public, v.updated_at, p.first_name || ' ' || p.last_name as user_name from var_flag_details v, user_profile p where chromosome='$chromosome' and start_pos='$start_pos' and end_pos='$end_pos' and ref='$ref' and alt='$alt' and  (patient_id='$patient_id' or is_public = 'Y') and v.user_id=p.user_id and v.status <> '-1'";
		$rows = DB::select($sql);
		return $rows;
	}

	static function getACMGGuideClass($chromosome, $start_pos, $end_pos, $ref, $alt, $patient_id) {
		$sql = "select class, checked_list from var_acmg_guide where chromosome='$chromosome' and start_pos='$start_pos' and end_pos='$end_pos' and ref='$ref' and alt='$alt' and patient_id='$patient_id'";
		$rows = DB::select($sql);
		if (count($rows) == 0)
			return array("classification" => "None", "checked_list" => "");
		else
			return array("classification" => $rows[0]->class, "checked_list" => $rows[0]->checked_list);
	}

	static function updateACMGGuideClass($chromosome, $start_pos, $end_pos, $ref, $alt, $patient_id) {
		$sql = "select class, checked_list, updated_at from var_acmg_guide_details where chromosome='$chromosome' and start_pos='$start_pos' and end_pos='$end_pos' and ref='$ref' and alt='$alt' and patient_id='$patient_id' and status <> '-1' order by updated_at desc";
		$rows = DB::select($sql);
		$classification = "None";
		$checked_list = "";
		if (count($rows) > 0)
			VarACMGGuide::where('chromosome', $chromosome)->where('start_pos', $start_pos)->where('end_pos', $end_pos)->where('ref', $ref)->where('alt', $alt)->where('patient_id', $patient_id)->update(array('class' => $rows[0]->class, "checked_list" => $rows[0]->checked_list));			
	}

	static function getACMGGuideHistory($chromosome, $start_pos, $end_pos, $ref, $alt, $patient_id) {
		$sql = "select distinct v.patient_id, v.class, upper(v.checked_list) as checked, v.is_public, v.updated_at, p.first_name || ' ' || p.last_name as user_name from var_acmg_guide_details v, user_profile p where chromosome='$chromosome' and start_pos='$start_pos' and end_pos='$end_pos' and ref='$ref' and alt='$alt' and  (patient_id='$patient_id' or is_public = 'Y') and v.user_id=p.user_id and status <> '-1'";
		$rows = DB::select($sql);
		return $rows;
	}

	static function getQCI($patient_id, $case_id, $ref="any", $type="somatic") {
		$ref_clause = "";
		if ($ref != "any")
			$ref_clause = " and ref='$ref'";
		if ($ref == "fusion")
			$ref_clause = " and (ref='fusion' or ref='rearrangement')";
		$sql = "select * from var_qci_annotation where patient_id = '$patient_id' and case_id = '$case_id' $ref_clause";
		$rows = DB::select($sql);
		Log::info($sql);
		$qci_data = array();
		foreach ($rows as $row) {
			if ($type == "germline" && $row->type != "germline")
				continue;				
			if ($type != "germline" && $row->type == "germline")
				continue;				
			$key = implode(":", array($row->patient_id, $row->case_id, $row->chromosome, $row->position));
			$qci_data[$key] = array($row->qci_assessment, $row->qci_actionability, $row->qci_nooactionability,$row->type);
		}
		return $qci_data;

	}

	static function getQCIByGene($project_id, $gene, $type="somatic") {
		$table_name = VarAnnotation::getTableName();
		$ref = "";
		$ref_clause = "";
		if ($ref != "any")
			$ref_clause = " and ref='$ref'";
		if ($ref == "fusion")
			$ref_clause = " and (ref='fusion' or ref='rearrangement')";
		$sql = "select q.* from var_qci_annotation q,$table_name a,project_cases p where q.patient_id=p.patient_id and q.case_id=p.case_id and 
p.project_id=$project_id and q.patient_id=a.patient_id and q.type='$type' and a.gene='$gene' and q.case_id=a.case_id and q.chromosome=a.chromosome and q.position=a.start_pos and q.ref not in ('fusion','rearrangement','gene_rearrangement') ";
		$rows = DB::select($sql);
		Log::info($sql);
		$qci_data = array();
		foreach ($rows as $row) {
			$key = implode(":", array($row->patient_id, $row->case_id, $row->chromosome, $row->position));
			$qci_data[$key] = array($row->qci_assessment, $row->qci_actionability, $row->qci_nooactionability,$row->type);
		}
		return $qci_data;

	}

	function postProcessVarData($variances, $project_id, $type, $found_hotspots=null) {
		$time_start = microtime(true);
		$project = Project::getProject($project_id);
		$data = array();
		$columns = null;
		$mut_ref_cnt = array();
		$mut_samples_cnt = array();
		$mut_samples = array();
		$mut_cat = array();
		//list($hotspot_gene_list, $hotspot_gene_desc) = VarAnnotation::getHotspotGenes(storage_path()."/data/".Config::get('onco.hotspot.actionable'));
		$hotspot_actionable_list = array();
		//read the hotspot genes
		list($hotspot_actionable_list, $hotspot_actionable_desc) = VarAnnotation::getHotspots(storage_path()."/data/".Config::get('onco.hotspot.actionable'));
		list($hotspot_predicted_list, $hotspot_predicted_desc) = VarAnnotation::getHotspots(storage_path()."/data/".Config::get('onco.hotspot.predicted'));
		$hotspot_exons = VarAnnotation::getHotspotExons(storage_path()."/data/".Config::get('onco.hotspot.exons'));

		//Log::info("Total count is : ".count($hotspot_exons));
		$project_folders = array();
		#$totalPatients = Project::totalPatients($project_id);
		$patient_counts = array();
		$pat_cnts = DB::table('project_patient_summary')->get();
		foreach ($pat_cnts as $cnt)
			$patient_counts["p".$cnt->project_id] = array($cnt->patients, $cnt->name);

		//read the disease genes
		//$inherited_diseases_genes = VarAnnotation::readFile(storage_path()."/data/".Config::get('onco.inherited_diseases'));
		
		
		$patient_samples = array();
		$acmg_list_name = Config::get('onco.acmg_list_name');
		$flag_list = $this->getFlags($type);
		if ($type == "germline")
			$acmg_list = $this->getACMGGuides();
		$root_url = $this->root_url;

		$tissue_cat = '';
		if ($type == 'germline')
			$tissue_cat = 'normal';
		if ($type == 'somatic')
			$tissue_cat = 'tumor';
		$fp_list = array();
		if ($tissue_cat != '') {
			if (Cache::has('clinomics_fp'))
				$fp_list = Cache::get('clinomics_fp');
			else {
				Log::info("Retrieving clinomics_fp");
				$fps = DB::table('clinomics_fp')->where('type', $type)->where('tissue_cat', $tissue_cat)->get();
				foreach ($fps as $fp) {
					$fp_list[$fp->chromosome."_".$fp->start_pos."_".$fp->end_pos."_".$fp->ref."_".$fp->alt] = $fp->cnt;
				}
    			Cache::forever('clinomics_fp', $fp_list);
			};
		}
		if ($type == 'rnaseq') {
			$fp_list = Cache::rememberForever('rnaseq_fp', function() {
				Log::info("Retrieving rnaseq_fp");
				$fps = DB::table('rnaseq_fp')->get();
				$_fp_list = array();
				foreach ($fps as $fp) {
					$_fp_list[$fp->chromosome."_".$fp->start_pos."_".$fp->end_pos."_".$fp->ref."_".$fp->alt] = $fp->cnt;
				}
				return $_fp_list;
			});
		}

		$igv_url = Config::get('onco.igv_url');	
		$rnaseq_samples = array();		

		//read the user defined genes
		$user_filter_list = UserGeneList::getGeneList($type);
		
		foreach ($variances as $var) {
			$patient_id = $var->patient_id;			
			$cases = explode(",", $var->case_id);
			$cases = array_unique($cases);
			$var->case_id = implode(",",$cases);			
			$case_id = $var->case_id;
			if (count($cases) > 1)
				$case_id = "any";

			$sample_id = isset($var->sample_id)? $var->sample_id : "null";

			//if ($var->func != 'exonic' && $var->func != 'splicing')
			//	continue;
			//$var->details = "<img width=18 height=18 src='$root_url/images/details_open.png'></img>";
			$var->libraries = $this->getDetailLink($var, 'samples',  "<img width=18 height=18 src='$root_url/images/details_open.png'></img>", false);
			$var_pk = $var->chromosome."_".$var->start_pos."_".$var->end_pos."_".$var->ref."_".$var->alt;
			$var_id = $var->patient_id.":".$var->case_id.":".$var->chromosome.":".$var->start_pos.":".$var->end_pos.":".$var->ref.":".$var->alt;
			//set IGV url
			if (!array_key_exists($var->patient_id.$case_id, $project_folders)) {	
				$time_getpath = microtime(true);			
				$path = VarCases::getPath($patient_id, $case_id);
				$project_folders[$var->patient_id.$case_id] = $path;
				$time = microtime(true) - $time_getpath;
				//Log::info("execution time (getpath): $time seconds");				
			}
			$project_folder = $project_folders[$var->patient_id.$case_id];
			if ($project != null && $project->showFeature('igv')) {
				$var->view_igv = "<a target=_blank href='$root_url/viewIGV/$var->patient_id/$sample_id/$case_id/$type/".($var->start_pos)."/$var->chromosome".":".($var->start_pos - 50)."-".($var->end_pos + 50)."'><img width=20 hight=20 src='$root_url/images/igv.jpg'/></a>";
			}
			if (isset($hotspot_actionable_list[$var->gene]))
				$var->hotspot_gene = "Y";
			if (isset($hotspot_actionable_list[$var->gene][$var->chromosome][$var->start_pos][$var->end_pos]))
				$var->actionable_hotspots = "Y";
			if (isset($hotspot_predicted_list[$var->gene][$var->chromosome][$var->start_pos][$var->end_pos]))					
				$var->prediction_hotspots = "Y";
			//user defined filters
			$cosmic_cancer_gene = 'N';
			foreach ($user_filter_list as $list_name => $gene_list) {				
				if (strtolower($list_name) == $acmg_list_name) {				
					$var->acmg = isset($gene_list[$var->gene])? "Y":"";
					continue;
				}				
				$has_gene = isset($gene_list[$var->gene])? $this->formatLabel("Y"):"";
				if (strtolower($list_name) == "cosmic_cancer_genes" && $has_gene != "")
					$cosmic_cancer_gene = "Y";
				//if (strtolower($list_name) == "clinomics_gene_list" || strtolower($list_name) == "cgcensus_hereditary" || strtolower($list_name) == "tumor_suppressor_genes" || strtolower($list_name) == "loss_of_function_genes")
					$var->{strtolower($list_name)} = $has_gene;
			}

			//add flag info
			$flag_id = $var_pk."_".$patient_id;
			$status = 0;
			$acmg_status = 0;
			if (isset($flag_list[$var_pk])) {
				$flags = $flag_list[$var_pk];
				if (isset($flags[$patient_id])) {
					if (isset($flags[$patient_id]["Y"]))
						$status = $flags[$patient_id]["Y"];
					if (isset($flags[$patient_id]["N"]))
						$status = $flags[$patient_id]["N"];
					$var->flag = $this->getFlagURL($var_id, $var->gene, $type, $status);
				} else 
					$var->flag = $this->getFlagURL($var_id, $var->gene, $type, $status);
				//if comments in other patients					
				foreach ($flags as $pid => $flag) {
					if (isset($flag["Y"]) && $pid != $patient_id) {
						$status = 1;
						$var->flag .= "<img class='flag_tooltip' title='Flagged in other patients' width=18 height=18 src='$root_url/images/info2.png'></img>";
						break;
					}
				}
			}
			else
				$var->flag = $this->getFlagURL($var_id, $var->gene, $type, $status);	

			$fp = 'N';
			if (array_key_exists($var_pk, $fp_list)) {
				$cnt = $fp_list[$var_pk];
				$var->flag .= "<img class='flag_tooltip' title='$cnt patients have this mutation' width=18 height=18 src='$root_url/images/circle_red.png'></img>";
				$fp = 'Y';
			}


			#add ACMG guide link
			if ($type == "germline") {
				if (isset($acmg_list[$var_pk])) {
					$acmg_guides = $acmg_list[$var_pk];
					if (isset($acmg_guides[$patient_id])) {
						if (isset($acmg_guides[$patient_id]["Y"]))
							$classification = $acmg_guides[$patient_id]["Y"];
						if (isset($acmg_guides[$patient_id]["N"]))
							$classification = $acmg_guides[$patient_id]["N"];
						$var->acmg_guide = $this->getACMGGuideURL($var_id, $var, $classification);
						$acmg_status = 1;
					} else
						$var->acmg_guide = $this->getACMGGuideURL($var_id, $var, "None");
					//if comments in other patients					
					foreach ($acmg_guides as $pid => $acmg_guide) {
						if (isset($acmg_guide["Y"]) && $pid != $patient_id) {
							$acmg_status = 1;
							$var->acmg_guide .= "<img class='flag_tooltip' title='Flagged in other patients' width=18 height=18 src='$root_url/images/info.png'></img>";
							break;
						}
					}
				} else
					$var->acmg_guide = $this->getACMGGuideURL($var_id, $var, "None");
			}

			if (property_exists($var, 'signout')) {
				$var->signout = "<input type='checkbox' id='signout_$var_id'></input>";
			}

			if ($found_hotspots != null) {
				$var->{"in_germline_somatic"} = (array_key_exists($var_pk, $found_hotspots)) ? 'Y' : 'N';
			}

			$var->{"fp"} = $fp;
			$var->{"acmg_status"} = $acmg_status;
			$var->{"flag_status"} = $status;
			$var->{"id"} = $var_id;

			//if ($var->func == "splicing" || $var->exonicfunc == "stopgain" || strpos($var->exonicfunc, "frameshift") !== FALSE)
			//if ($var->func == "splicing" || $var->exonicfunc == "stopgain" || substr($var->exonicfunc, 0, 10) == "frameshift" || $var->exonicfunc == "nonframeshift insertion" || $var->exonicfunc == "nonframeshift deletion" || ($var->exonicfunc == "nonframeshift substitution" && strlen($var->ref) <= 3))
			### AVIA OC
			//$var->loss_func = VarAnnotation::isLOF($var->func, $var->exonicfunc);
			$var->loss_func = VarAnnotation::isLOF($var->exonicfunc);
			####
			
			//get level
			$var->somatic_level = ($type != "germline")? $this->formatLabel(VarAnnotation::getLevel('somatic', $var, $type)) : "";
			$var->germline_level = ($type != "somatic")? $this->formatLabel(VarAnnotation::getLevel('germline', $var, $type)) : "";

			$aa_site = '';

			$aapos = $var->aapos;


			if ($this->mutPlotData != null) {
				try {
					$coord = (int)$aapos;
					$this->updateRange($coord, $this->mutPlotData->sample);
					$coord = "A".$coord;
					Log::info("coord: $coord");
					$mut_cat[$coord] = $var->exonicfunc;
					//if (isset($mut_samples_cnt[$coord]))
						$mut_samples_cnt[$coord][$var->patient_id] = '';
					//else
					//	$mut_samples_cnt[$coord] = 1;
					if (!isset($mut_samples[$coord]))
						if (array_key_exists($var->patient_id, $rnaseq_samples))
							$mut_samples[$coord] = $rnaseq_samples[$var->patient_id];
					else
						if (array_key_exists($var->patient_id, $rnaseq_samples))
							$mut_samples[$coord] = array_merge($mut_samples[$coord], $rnaseq_samples[$var->patient_id]);
					if ($var->reported_mutations > 0)
						$mut_ref_cnt[$coord] = $var->reported_mutations;

				} catch (Exception $e) {
					Log::info("aapos : $aapos");
				}				
			}			

			if ($var->aachange == "-1") {
				$var->aachange = "";
				$var->gene = "";
			}

			//$var->aachange = rtrim($var->aachange, ",");
			$total_patients = 0;
			$project_name = "";
			if (array_key_exists("p".$var->project, $patient_counts)) {
				$total_patients = $patient_counts["p".$var->project][0];
				$project_name = $patient_counts["p".$var->project][1];
				$var->project = $project_name;
			}
			
			$hint = "$var->cohort out of $total_patients patients have mutation in gene ".$var->gene;
			if ($total_patients > 0) {
				$cohort_value = round($var->cohort/$total_patients * 100, 2);			
				$bar_class = VarAnnotation::getCohortClass($cohort_value);				
				$var->cohort = "<span class='mytooltip' title='$hint'><div class='progress text-center'><div class='progress-bar $bar_class progress-bar-striped' role='progressbar' aria-valuenow='$cohort_value' aria-valuemin='0' aria-valuemax='100' style='width:$cohort_value%'><span>$cohort_value%</span></div></div></span>";
				$var->cohort = $this->getDetailLink($var, 'cohort',  $var->cohort, false);
				if ($var->site_cohort > 0) {
					$cohort_value = round($var->site_cohort/$total_patients * 100, 2);
					$bar_class = VarAnnotation::getCohortClass($cohort_value);
					$hint = "$var->site_cohort out of $total_patients patients have mutation at $aa_site in gene ".$var->gene;
					$var->site_cohort = "<span class='mytooltip' title='$hint'><div class='progress text-center'><div class='progress-bar $bar_class progress-bar-striped' role='progressbar' aria-valuenow='$cohort_value' aria-valuemin='0' aria-valuemax='100' style='width:$cohort_value%'><span>$cohort_value%</span></div></div></span>";
					$var->site_cohort = $this->getDetailLink($var, 'cohort',  $var->site_cohort, false);
				} else
					$var->site_cohort = "";
			}

			$warning_url = "";

			if ($var->transcript != "*" && substr($var->transcript, 0 ,1) == "*")
				$warning_url = "<img class='flag_tooltip' title='Not canonical transcript' width=18 height=18 src='$root_url/images/warning.ico'></img>";

			#### AVIA OC
			//if ($var->aachange == '' and $var->func == 'splicing' )
			//	$var->aachange = "(Splicing variants)";
			if ($var->aachange == '' and $var->exonicfunc == 'splice_site_variant' )
				$var->aachange = "(Splicing variants)";
			else {				
				$var->aachange = "$warning_url<a href=javascript:showMutalyzer('$var->chromosome','$var->start_pos','$var->end_pos','$var->ref','$var->alt','$var->gene','$var->transcript')>$var->aachange</a>";
			}
			//if ($var->exonicfunc == "UTR3" || $var->exonicfunc == "UTR5")			
			if ($var->exonicfunc == "3_prime_UTR_variant" || $var->exonicfunc == "5_prime_UTR_variant")
				$var->aachange = "";
			####
			$dbsnps = explode(",",$var->dbsnp);
			$dbsnp_str = "";
			foreach ($dbsnps as $dbsnp) {
				preg_match('/rs(.*)/', $dbsnp, $matches);
				if (count($matches) > 0)
					$dbsnp_str .= "<a target=_blank href='http://www.ncbi.nlm.nih.gov/SNP/snp_ref.cgi?rs=".$matches[1]."'>$dbsnp</a> ";
			}
			$var->dbsnp = $dbsnp_str;
			#format label
			//$var->hotspot_gene = $this->formatLabel($var->hotspot_gene);
			$var->actionable_hotspots = $this->formatLabel($var->actionable_hotspots);
			$var->prediction_hotspots = $this->formatLabel($var->prediction_hotspots);

			$var->loss_func = $this->formatLabel($var->loss_func);
			$var->clinvar_clisig = $this->formatLabel($var->clinvar_clisig);
			$var->hgmd_cat = $this->formatLabel($var->hgmd_cat);
			//$var->acmg = $this->formatLabel($var->acmg);
			$var->acmg = $this->getDetailLink($var, 'acmg',  $var->acmg);
			$var->actionable = $this->getDetailLink($var, 'actionable',  $var->actionable);
			$var->clinvar = $this->getDetailLink($var, 'clinvar',  $var->clinvar);
			$var->civic = $this->getDetailLink($var, 'civic',  $var->civic);

			$badgeds = explode(",",$var->clinvar_badged);
			$clinvar_badged = "";
			foreach ($badgeds as $badged) {
				if ($badged != "") {
					$desc = "";
					if ($badged == "green") $desc = ">95% from past 5 years";
					if ($badged == "purple") $desc = "Discrepancy resolution";
					if ($badged == "gold") $desc = "Consenting mechanism";
					$clinvar_badged = $clinvar_badged."<img class='flag_tooltip' title='$desc' width=18 height=18 src='$root_url/images/clinvar_badged_$badged.png'></img>";
				}
			}
			$var->clinvar_badged = $clinvar_badged;
			$var->cosmic = $this->getDetailLink($var, 'cosmic',  $var->cosmic);
			if ($cosmic_cancer_gene == 'Y')
				$var->cosmic = $var->cosmic."&nbsp;<a target=_blank href='https://cancer.sanger.ac.uk/cosmic/census-page/$var->gene'>".$this->formatLabel("Census")."</a>";
			$var->hgmd = $this->getDetailLink($var, 'hgmd',  $var->hgmd);
			$var->prediction = $this->getDetailLink($var, 'prediction',  $var->prediction);
			$var->spliceai = $this->getDetailLink($var, 'spliceai',  $var->spliceai);
			$var->germline = $this->getDetailLink($var, 'stjude',  $var->germline);

			$var->hotspot_gene = $this->formatLabel($var->hotspot_gene);
			#$var->germline_count = $this->formatLabel($var->germline_count);
			#$var->somatic_count = $this->formatLabel($var->somatic_count);
			#$var->adj_germline_count = $this->formatLabel($var->adj_germline_count);
			#$var->adj_somatic_count = $this->formatLabel($var->adj_somatic_count);

			if (property_exists($var, "qci_assessment"))
				$var->qci_assessment = $this->formatLabel($var->qci_assessment);
			if (property_exists($var, "qci_actionability"))
				$var->qci_actionability = $this->formatLabel($var->qci_actionability);

			if (property_exists($var, "intervar"))
				$var->intervar = $this->formatLabel($var->intervar);

			if ($var->reported_mutations > 0) 
				$var->reported_mutations = $this->getDetailLink($var, 'reported', $var->reported_mutations, true);
				//$var->reported_mutations = "<div style='text-align:center'><a href=javascript:getDetails('reported','$var->chromosome','$var->start_pos','$var->end_pos','$var->ref','$var->alt','$var->patient_id','$var->gene');>".$this->formatLabel($var->reported_mutations)."</a></div>";
			//if ($var->genie > 0) 
			//	$var->genie = "<div style='text-align:center'><a href=javascript:getDetails('genie','$var->chromosome','$var->start_pos','$var->end_pos','$var->ref','$var->alt','$var->patient_id','$var->gene');>".$this->formatLabel($var->genie)."</a></div>";
			
			$key = $var->chromosome.$var->start_pos.$var->end_pos.$var->ref.$var->alt;
			
			if ($var->dbsnp_af != null)
				$var->dbsnp_af = $this->getDetailLink($var, 'dbsnp_af', $var->dbsnp_af, true);
				//$var->dbsnp_af = "<div style='text-align:center'><a href=javascript:getDetails('dbsnp_af','$var->chromosome','$var->start_pos','$var->end_pos','$var->ref','$var->alt','$var->patient_id','$var->gene');>".$this->formatLabel($var->dbsnp_af)."</a></div>";

			if ($var->frequency != null)
				$var->frequency = $this->getDetailLink($var, 'freq', $var->frequency, true);
				//$var->frequency = "<div style='text-align:center'><a href=javascript:getDetails('freq','$var->chromosome','$var->start_pos','$var->end_pos','$var->ref','$var->alt','$var->patient_id','$var->gene');>".$this->formatLabel($var->frequency)."</a></div>";

			$var->pop_max_gnomad2 = $this->formatLabel($var->pop_max_gnomad2);
			$var->pop_max_source_gnomad2 = $this->formatLabel($var->pop_max_source_gnomad2);
			$var->pop_max_gnomad3 = $this->formatLabel($var->pop_max_gnomad3);
			$var->pop_max_source_gnomad3 = $this->formatLabel($var->pop_max_source_gnomad3);
			
			$bracket_pos = strpos($var->gene, '(');
			$gene_id = $var->gene;
			if ($bracket_pos !== false) {
				$var->gene = "<div title='".$var->gene."'>".substr($var->gene, 0, $bracket_pos)."...</div>";
			} else {
				//$var->gene = "<a target=_blank href=".url('/viewVarAnnotation/'.$this->sid."/null/".$var->gene).">".$var->gene."</a>";
				$var->gene = $this->getDetailLink($var, 'gene', $var->gene, false);
				//$var->gene = "<a href=javascript:getDetails('gene','$var->chromosome','$var->start_pos','$var->end_pos','$var->ref','$var->alt','$var->patient_id','$var->gene');>$var->gene</a>";
			}
			
			$var->gene .= "&nbsp;&nbsp;<a target=_blank href=$root_url/viewVarAnnotationByGene/$project_id/$gene_id/$type/1/null/null/any/$var->patient_id><img class='flag_tooltip' title='Detail page' width=18 height=18 src='$root_url/images/info2.png'></img></a><a target=_blank href=https://maayanlab.cloud/archs4/gene/$gene_id><img class='flag_tooltip' title='ARCHS4' width=15 height=15 src='https://maayanlab.cloud/archs4/images/archs-icon.png?v=2'/></a>";
			$var->patient_id = "<a target=_blank href='$root_url/viewPatient/$project_id/$var->patient_id'>".$var->patient_id."</a>";
			

			$var->vaf = $this->formatLabel(round($var->vaf, 3));
			//$var->vaf = "<span class='badge'>".round($var->vaf, 3)."</span>";
			$var->total_cov = $this->formatLabel($var->total_cov);
			$var->var_cov = $this->formatLabel($var->var_cov);
			$var->vaf_ratio = $this->formatLabel(round($var->vaf_ratio, 2));

			#preg_match('/(.*)\/(.*)/', $var->exp_cov, $matches);
			#if (count($matches) > 0) {
			$var->matched_var_cov = $this->formatLabel($var->matched_var_cov);
			$var->matched_total_cov = $this->formatLabel($var->matched_total_cov);
			

			if (strlen($var->ref) > 3) {
				$var->ref = "<div title='".$var->ref."'>".substr($var->ref, 0, 3)."...</div>";
			}

			if (strlen($var->alt) > 3) {
				$var->alt = "<div title='".$var->alt."'>".substr($var->alt, 0, 3)."...</div>";
			}

			
			$var_values = array_values((array)$var);



			$data[] = array_values((array)$var);

			if ($columns == null) {
				$columns = array();
				$var_keys = array_keys((array)$var);				
				foreach ($var_keys as $var_key) {					
					$key_label = Lang::get("messages.$var_key");					
					if ($key_label == "messages.$var_key")
						//$key_label = $var_key;
						$key_label = ucfirst(str_replace("_", " ", $var_key));
					$help_label = Lang::get("messages.$var_key.help");
					if ($help_label != "messages.$var_key.help")
						$key_label = "<span title='$help_label'>$key_label</span>";					
					$columns[] = array("title"=>$key_label);
				}				
			}	

		}

		Log::info("mut_samples");
		Log::info(json_encode($mut_samples));
		foreach ($mut_samples_cnt as $coord => $patients) {
				$cnt = count(array_keys($patients));
				$mutation = array();				
				$mutation["coord"] = ltrim($coord, 'A');
				if (isset($mut_samples["$coord"]))					
					$mutation["sample"] = $mut_samples["$coord"];
				else
					$mutation["sample"] = [];
				$mutation["category"] = $mut_cat["$coord"];
				$mutation["value"] = $cnt;
				$this->mutPlotData->sample->data[] = $mutation;
		}
		
		//Log::info("execution time (p1): $p1 seconds");
		//Log::info("execution time (p2): $p2 seconds");
		//Log::info("execution time (p3): $p3 seconds");
		//Log::info("execution time (p4): $p4 seconds");
		$time = microtime(true) - $time_start;
		Log::info("execution time (postProcessVarData): $time seconds");

		/*
		if ($this->gene_id != 'null') {
			$this->getRefMutations($this->gene_id);
		}
		else {
			foreach ($mut_ref_cnt as $coord => $cnt) {
					$mutation = array();				
					$mutation["coord"] = ltrim($coord, 'A');
					$mutation["category"] = $mut_cat[$coord];
					$mutation["value"] = $cnt;
					$this->mutPlotData->ref->data[] = $mutation;
			}
		}
		*/
		return array($data, $columns);
	}	

	
	### AVIA OC
	static function isLOF($exonicfunc) {
		if ($exonicfunc == "splice_site_variant" || strpos($exonicfunc,"stop_gained") !== FALSE || substr($exonicfunc, 0, 10) == "frameshift" || $exonicfunc == "inframe_insertion" || $exonicfunc == "inframe_deletion" || $exonicfunc == "stop_lost")
			return 'Y';
		return '';
	}
	###
	/*
	static function isLOF($func, $exonicfunc) {
		if ($func == "splicing" || strpos($exonicfunc,"stopgain") !== FALSE || substr($exonicfunc, 0, 10) == "frameshift" || $exonicfunc == "nonframeshift insertion" || $exonicfunc == "nonframeshift deletion" || $exonicfunc == "stoploss")
			return 'Y';
		return '';
	}
	*/

	static function getQCISummary($patient_id, $case_id="any") {
		$case_condition = "";
		if ($case_id != "any")
			$case_condition = " and case_id='$case_id'";
		$rows = DB::select("select * from var_qci_summary where patient_id='$patient_id' $case_condition");
		return $rows;
	}

	static function getCohortClass($cohort_value) {
		$bar_class = "progress-bar-danger";
		if ($cohort_value <= 10)
			$bar_class = "progress-bar-success";
		if ($cohort_value > 10 && $cohort_value <= 20)
			$bar_class = "progress-bar-info";
		if ($cohort_value > 20 && $cohort_value <= 30)
			$bar_class = "progress-bar-warning";
		return $bar_class;
	}

	static function findBAMfile($path, $patient_id, $case_id, $sample_id, $alias, $file_type) {
		$ext_squeeze_name = "$file_type.final.squeeze.bam";
		$ext_squeeze_cram_name = "$file_type.final.squeeze.cram";
		$ext_name = "$file_type.final.bam";
		if ($file_type == "") {
			$ext_squeeze_name = "squeeze.bam";
			$ext_name = "bam";
		}

		if ($file_type == "fusion") {
			$ext_squeeze_name = "star.fusions.bam";
			$ext_name = "bam";
		}

		if ($file_type == "final") {
			$ext_squeeze_name = "final.squeeze.bam";
			$ext_name = "bam";
		}

		$cases = explode(',', $case_id);
		foreach ($cases as $case_id) {
			//check squeeze bam
			#$sample_file = "$path/$patient_id/$case_id/$sample_id/$sample_id.$ext_squeeze_cram_name";
			#if (file_exists(storage_path()."/ProcessedResults/".$sample_file))
			#	return $sample_file;
			#$sample_file = "$path/$patient_id/$case_id/$alias/$alias.$ext_squeeze_cram_name";
			//Log::info("checking sample file: $sample_file");
			#if (file_exists(storage_path()."/ProcessedResults/".$sample_file))
			#	return $sample_file;
			$sample_file = "$path/$patient_id/$case_id/Sample_$sample_id/Sample_$sample_id.$ext_squeeze_name";
			//Log::info("checking sample file: $sample_file");
			if (file_exists(storage_path()."/bams/".$sample_file))
				return $sample_file;
			$sample_file = "$path/$patient_id/$case_id/$sample_id/$sample_id.$ext_squeeze_name";
			if (file_exists(storage_path()."/bams/".$sample_file))
				return $sample_file;
			$sample_file = "$path/$patient_id/$case_id/Sample_$alias/Sample_$alias.$ext_squeeze_name";
			if (file_exists(storage_path()."/bams/".$sample_file))
				return $sample_file;
			$sample_file = "$path/$patient_id/$case_id/$alias/$alias.$ext_squeeze_name";
			Log::info($sample_file);
			if (file_exists(storage_path()."/bams/".$sample_file))
				return $sample_file;
			//check original bam
			$sample_file = "$path/$patient_id/$case_id/Sample_$sample_id/Sample_$sample_id.$ext_name";
			if (file_exists(storage_path()."/bams/".$sample_file))
				return $sample_file;
			$sample_file = "$path/$patient_id/$case_id/$sample_id/$sample_id.$ext_name";
			if (file_exists(storage_path()."/bams/".$sample_file))
				return $sample_file;
			$sample_file = "$path/$patient_id/$case_id/Sample_$alias/Sample_$alias.$ext_name";
			if (file_exists(storage_path()."/bams/".$sample_file))
				return $sample_file;
			$sample_file = "$path/$patient_id/$case_id/$alias/$alias.$ext_name";
			if (file_exists(storage_path()."/bams/".$sample_file))
				return $sample_file;
		}
		return "";

	}

	static function formatLabelStatic($text) {
		if ($text != "")
			return "<span class='badge badge-pill badge-dark'>".$text."</span>";
		else
			return "";
	}

	function formatLabel($text) {
		return VarAnnotation::formatLabelStatic($text);		
	}

	static public function hasJunction($patient_id, $case_id) {		
		$case = VarCases::getCase($patient_id, $case_id);
		$path = $case->path;
		$suffix="SJ.out.bed.gz";
		$junctions = array();
		foreach (glob(storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/*/*$suffix") as $filename) {
			if (file_exists($filename))
				return true;
		}	
		return false;
	}

	static function getLevel($type, $var, $source) {
		if ($var->frequency > 0.05)
			return "";
	#	if ($type == "germline" && $var->vaf < 0.25)
	#		return "";
		if ($type == "germline") {
			if ($var->actionable_hotspots != ""){
			#	Log::info('Tier 1.0');
				return "Tier 1.0";
			}
			if (($var->acmg != '' && (substr($var->clinvar_clisig,0,10) == "Pathogenic" || substr($var->clinvar_clisig,0,17) == "Likely pathogenic")) || ($var->clinomics_gene_list != "" && (substr($var->clinvar_clisig,0,10) == "Pathogenic" || substr($var->clinvar_clisig,0,17) == "Likely pathogenic"))) {
			#	Log::info('Tier 1.1');
				return "Tier 1.1";
			}
			if ($var->acmg != '' && $var->loss_func != ""){
			#	Log::info('Tier 1.2');
				return "Tier 1.2";
			}
			if ($var->clinomics_gene_list != "" && $var->cgcensus_hereditary != "" && $var->loss_func != ""){
			#	Log::info('Tier 1.2');
				return "Tier 1.2";
			}
			if (($var->acmg != '' && $var->hgmd_cat != "") || ($var->clinomics_gene_list != "" && $var->hgmd_cat != "")){
			#	Log::info('Tier 1.3');
			#	Log::info($var->hgmd_cat);
				return "Tier 1.3";
			}
			if ($var->clinomics_gene_list != "" && $var->loss_func != "" && $var->tumor_suppressor_genes != ""){
				Log::info('Tier 1.3');
			#	Log::info($var->loss_func);
				return "Tier 1.3";
			}
			if ($var->clinomics_gene_list != "" && $var->loss_func != "" && $var->loss_of_function_genes != "")
				return "Tier 1.4";
			if ($var->acmg != '' || ($var->clinomics_gene_list != "" && $var->loss_func != "") || $var->cgcensus_hereditary != "")
			{
				#Log::info($var->chromosome);
				#Log::info($var->clinvar_clisig);
				return "Tier 2";
			}			
			if (($var->hgmd_cat != "" && (substr($var->clinvar_clisig,0,10) == "Pathogenic" || substr($var->clinvar_clisig,0,17) == "Likely pathogenic")) || ($var->clinomics_gene_list != "" && $var->vaf_ratio >= 1.2 && $source == "germline")){
			#	Log::info('Tier 3');
			#	Log::info($var->hgmd_cat);
				return "Tier 3";								
			}
			if ($var->hgmd_cat != "" || $var->clinomics_gene_list != ""){
			#	Log::info('Tier 4');
			#	Log::info($var->hgmd_cat);
				return "Tier 4";
			}
			return "";
		}
		if ($type == "somatic") {
			$is_nonfs = (substr($var->exonicfunc, 0, 13) == "nonframeshift");
			$is_nonsyn = (substr($var->exonicfunc, 0, 13) == "nonsynonymous");
			if (($var->actionable_hotspots != "") || 
				($is_nonfs && $var->gene == "EGFR" && ($var->exon_num == "exon19" || $var->exon_num == "exon20")) ||
				($is_nonfs && $var->gene == "ERBB2" && $var->exon_num == "exon20") ||
				(($is_nonfs || $is_nonsyn) && $var->gene == "KIT" && ($var->exon_num == "exon8" || $var->exon_num == "exon9" || $var->exon_num == "exon11" || $var->exon_num == "exon13" || $var->exon_num == "exon14" || $var->exon_num == "exon17")) ||
				(($is_nonfs || $is_nonsyn) && $var->gene == "PDGFRA" && ($var->exon_num == "exon12" || $var->exon_num == "exon14" || $var->exon_num == "exon18")) || 
				($is_nonfs && $var->gene == "FLT3" && ($var->exon_num == "exon14" || $var->exon_num == "exon15")))
				return "Tier 1.1";
			if ($var->clinomics_gene_list != "" && $var->reported_mutations >= 5)
				return "Tier 1.2";
			if ($var->clinomics_gene_list != "" && $var->loss_func != "" && $var->tumor_suppressor_genes)
				return "Tier 1.3";
			if ($var->clinomics_gene_list != "" && $var->loss_func != "" && $var->loss_of_function_genes)
				return "Tier 1.4";
			if ($var->clinomics_gene_list != "" && $var->loss_func != "")
				return "Tier 2";
			/*
			if ($var->total_cov >= 10 && $var->var_cov >= 3 && $var->vaf >= 0.15 && (($exonic_func == "nonframeshift" && $var->gene == "ERBB2" && $var->exon_num == "exon20") || 
				($exonic_func == "nonframeshift" && $var->gene == "KIT" && $var->exon_num == "exon11") || ($var->clinomics_gene_list != "" && ($var->loss_func != "" || $exonic_func == "nonframeshift"))))
				return "Tier 2";
			*/
			if ($var->clinomics_gene_list != "")
				return "Tier 3";			
			return "Tier 4";
		}
		return "";
	}

	static function getReserachLevel($type, $var, $source) {
		if ($var->frequency > 0.05)
			return "";
		if ($type == "germline" && $var->vaf < 0.25)
			return "";
		if ($type == "germline") {
				if (($var->acmg != "" && $var->clinvar_clisig != "") || ($var->cgcensus_hereditary != "" && $var->clinvar_clisig != "")
					|| ($var->actionable_hotspots != "") 
					|| ($var->hgmd_cat != "" && $var->clinvar_clisig != "")
					|| ($var->cancer_gene_list != "" && $var->clinvar_clisig != "") 
					|| ($var->cancer_gene_list != "" && $var->loss_func != ""))
					return "Tier 1";
				if ($var->cancer_gene_list != "" && $var->vaf_ratio >= 1.2 && $source == "germline")
					return "Tier 2";
				if ($var->acmg != "" || ($var->inherited_diseases != "" && $var->loss_func != "") || ($var->inherited_diseases != "" && $var->vaf >= 0.75) || $var->cancer_gene_list != "")
					return "Tier 3";								
				if ($var->hgmd_cat != "" || $var->inherited_diseases != "")
					return "Tier 4";
				
			
		}
		if ($type == "somatic") {
				if ($var->actionable_hotspots != "")
					return "Tier 1";
				if ($var->cancer_gene_list != "")
					if ($var->loss_func != "")
						return "Tier 2";
					else
						return "Tier 3";
				return "Tier 4";
		}
		return "";
	}

	static function parseAnnovarFeat($feat, $canonical_trans, $is_avia=true) {
		$infos = preg_split("/(,|;)/", $feat);
		$first = true;
		$annovar_feat = (object)array("exonicfunc" => '-', "transcript" => '', "exon_num" => '', "aachange" => '', "aaref" => '', "aapos" => '', "aaalt" => '', "hgvs_string" => '');
		foreach ($infos as $info) {
			$aa_info = explode(':', $info);
			if ($first || $is_avia) {#For regular ANNOVAR this is true, but this should be done every time for
				//$annovar_feat->exonicfunc = array_shift($aa_info);
				$first = false;
			}
			if (count($aa_info) < 4)
				continue;		
			$trans_id_complete = $aa_info[2];
			//Log::info($trans_id_complete);
			//preg_match('/(.*)\..*/', $trans_id_complete, $matches);
			//if (count($matches) > 0)
			//	$trans_id = $matches[1];
			//Log::info(json_encode($matches));
			//if (!isset($trans_id))
			$trans_id = $trans_id_complete;
			if ($trans_id == $canonical_trans) {
			#if ($trans_id == $canonical_trans) {
				$annovar_feat->exonicfunc = $aa_info[0];
				if ($annovar_feat->exonicfunc == "nonsynonymous SNV")
					$annovar_feat->exonicfunc = "SNV";
				#$annovar_feat->exonicfunc = str_replace("frameshift", "FS", $annovar_feat->exonicfunc);
				$annovar_feat->transcript = $trans_id;
				$annovar_feat->exon_num = $aa_info[3];
				//if indel
				
				//if SNP
				if (count($aa_info) >= 6) {
					preg_match('/p\.([0-9]*)_([0-9]*).*/', $aa_info[5], $matches);
					if (count($matches) > 0)
						$annovar_feat->aapos = $matches[1];											
					/*
					preg_match('/c\.([A-Z])([0-9]*)([A-Z])/', $aa_info[3], $matches);
					if (count($matches) > 0) {
						$annovar_feat->aaref = $matches[1];
						$annovar_feat->aapos = $matches[2];
						$annovar_feat->aaalt = $matches[3];
						$annovar_feat->aachange = $annovar_feat->aaref.$annovar_feat->aapos.$annovar_feat->aaalt;
					}
					*/
					$aachange = $aa_info[5];
					preg_match('/p\.([A-Z]*)([0-9]*)(.+)/', $aachange, $matches);
					if (count($matches) > 0) {
						$annovar_feat->aaref = $matches[1];
						$annovar_feat->aapos = $matches[2];
						$annovar_feat->aaalt = $matches[3];
						$annovar_feat->aachange = $annovar_feat->aaref.$annovar_feat->aapos.$annovar_feat->aaalt;
					}
				}
				else
					$annovar_feat->aachange = $aa_info[count($aa_info)-1];
			}
		}		
		return $annovar_feat;
	}

	function getRefMutations($gene_id) {
		$avia_mode = VarAnnotation::is_avia();
		$mut_ref_cnt = array();
		$mut_cat = array();		
		if ($avia_mode) {
			$avia_col_cat = VarAnnotation::getAVIACols();
			$avia_col_list = array();
			foreach ($avia_col_cat as $key => $values) {
				foreach ($values as $value)
				if ($value->type == 'reported')
					$avia_col_list[] = strtolower($value->column_name);				
			}
			$avia_col_list = implode(",", $avia_col_list);		
			#$avia_table = Config::get('site.avia_table');
			$avia_table = Config::get("site.hg19_annot_table");
			### AVIA OC
			$sql_avia = "select so,hgvs,canonicalprotpos,$avia_col_list from $avia_table a where gene = '$gene_id'";
			Log::info($sql_avia);
			$time_start = microtime(true);
			$rows_avia = DB::select($sql_avia);
			$time = microtime(true) - $time_start;
			Log::info("execution time (getRefMutations-avia): $time seconds");
			//$canonical_trans = VarAnnotation::getCanonicalTrans();
			$canonical_trans = '';
			foreach ($rows_avia as $row) {
				$hgvs_avia = explode(":", $row->hgvs);
				if (count($hgvs_avia) > 1) {
					$canonical_trans = $hgvs_avia[0];
				}

				//$aa_info = VarAnnotation::parseAnnovarFeat($annovar_feat, $canonical_trans, $avia_mode);
				$reported_cols = $avia_col_cat["reported"];
				$total_reported = VarAnnotation::parseReported($row, $reported_cols, false);
				//Log::info("total_reported: $total_reported");
				//Log::info("AA info: ".json_encode($aa_info));
				if ($total_reported == 0)
					continue;
				//if ($aa_info->aapos != '') {
				$aapos = substr($row->canonicalprotpos, 3, 3);
				if (isset($mut_ref_cnt["C$aapos"][$row->so])) {
					$current_cnt = $mut_ref_cnt["C$aapos"][$row->so];
					$mut_ref_cnt["C$aapos"][$row->so] = $current_cnt + $total_reported;
				}
				else {
					$mut_ref_cnt["C$aapos"][$row->so] = $total_reported;
				}
						
				//}
			}			
		}		
		
		foreach ($mut_ref_cnt as $coord_str => $cats) {
			$coord = substr($coord_str, 1);
			foreach ($cats as $cat => $cnt) {				
				$this->updateRange($coord, $this->mutPlotData->ref);
				$mutation["coord"] = $coord;
				$mutation["category"] = $cat;
				$mutation["value"] = $cnt;
				$this->mutPlotData->ref->data[] = $mutation;
			}
		}
		//Log::info(json_encode($this->mutPlotData->ref->data));
		
	}

	

	function getDetailLink($var, $type, $value, $style=true) {
		$var_pk = $var->chromosome."_".$var->start_pos."_".$var->end_pos."_".$var->ref."_".$var->alt;
		if ($value != '') {			
			//return "<div style='text-align:center'><a id='$var_pk' href='#' class='detailInfo' data-toggle='popover' data-trigger='focus' data-poload=".url("/getVarDetails/$type/$var->chromosome/$var->start_pos/$var->end_pos/$var->ref/$var->alt/$var->gene").";><span class='badge'>$value</span></a></div>";
			$value_str = ($style)? $this->formatLabel($value) : $value;
			return "<span style='text-align:center'><a id='$var_pk' href=javascript:getDetails('$type','$var->patient_id','$var->case_id','$var->sample_id','$var->chromosome','$var->start_pos','$var->end_pos','$var->ref','$var->alt','$var->gene');>".$value_str."</a></span>";
		}

//return "<a href=javascript:getDetails('sample','$var->chromosome','$var->start_pos','$var->end_pos','$var->ref','$var->alt','$var->sample_id');><img width=25 height=15 src='".url('images/info2.png')."'></img></a>";
		else
			return $value;
	}

	function initByLocus($chr, $start_pos, $end_pos) {
		$this->chr = $chr;
		$this->start_pos = $start_pos;
		$this->end_pos = $end_pos;
		$sql = "select gene,symbol,strand,chrom from gene_ensembl where gene ='".$gene_id."' or symbol ='".$gene_id."' and species = 'Hs'";
		$rows = DB::select($sql);
		$ensembl_id = null;
		if (count($rows) > 0) {
			$this->ensembl_id = $rows[0]->gene;
			$this->symbol = $rows[0]->symbol;
			$this->chr = $rows[0]->chrom;
			$this->strand = $rows[0]->strand;
		}		
	}
	

	function updateRange($coord, $obj) {
		if ($obj->range["start_pos"] > $coord) 
			$obj->range["start_pos"] = $coord;
		if ($obj->range["end_pos"] < $coord) 
			$obj->range["end_pos"] = $coord;		
	}
	
	static function getCancerGenes($type) {
		if ($type == "rnaseq")
			$type = "germline";
		if ($type == "all") {
			$germline_genes = VarAnnotation::readFile(storage_path()."/data/".Config::get('onco.cancer.germline'));
			$somatic_genes = VarAnnotation::readFile(storage_path()."/data/".Config::get('onco.cancer.somatic'));			
			$predis_genes = array_merge($germline_genes,$somatic_genes);
		}
		else
			$predis_genes = VarAnnotation::readFile(storage_path()."/data/".Config::get('onco.cancer.'.$type));
		return $predis_genes;
	}
	static function getKeyLabel($key) {
		$key = strtolower($key);
		$key_label = Lang::get("messages.$key");
		if ($key_label == "messages.$key") {
			$key_label = ucfirst(str_replace("_", " ", $key));
		}
		return $key_label;
	}
	
	function getMutationPlotData() {
			return $this->mutPlotData;			
	}

	function getDataTable() {
		return array($this->data, $this->columns);
	}

	static public function getHotspotGenes($file) {		
		$hotspots = VarAnnotation::readFile($file);
		$hotspot_list = array();
		$hotspot_desc = "The hot spot genes include: ";
		foreach ($hotspots as $hotspot) {			
			$hotspot_list[$hotspot] = '';
			$hotspot_desc .= $hotspot.", ";
		}
		$hotspot_desc = rtrim($hotspot_desc, ", ");
		return array($hotspot_list, $hotspot_desc);
	}

	static public function getHotspots($file) {
		$hotspots = VarAnnotation::readFile($file);
		$hotspot_list = array();
		$hotspot_desc = "The hot spots include: ";
		foreach ($hotspots as $hotspot) {
			$fields = preg_split('/\s+/', $hotspot);
			if (count($fields) < 6)
				continue;
			/*
			preg_match('/c\.(.*)/', $fields[4], $matches);
			if (count($matches) > 0)
				continue;
			preg_match('/p\.([A-Z][0-9]*)[a-zA-Z]+/', $fields[4], $matches);
			if (count($matches) > 0)
				$fields[4] = $matches[1];			
			$hotspot_list{$fields[3]}{$fields[4]} = $fields[5];
			*/
			$hotspot_list[$fields[3]][$fields[0]][$fields[1]][$fields[2]] = $fields[5];
			$hotspot_desc .= $fields[3]."(".$fields[4]."), ";
		}
		$hotspot_desc = rtrim($hotspot_desc, ", ");
		return array($hotspot_list, $hotspot_desc);
	}

	static public function getHotspotExons($file) {
		$hotspots = VarAnnotation::readFile($file);
		$hotspot_list = array();
		$hotspot_desc = "The hot spots include: ";
		foreach ($hotspots as $hotspot) {			
			$fields = preg_split('/\s+/', $hotspot);
			if (count($fields) < 2)
				continue;
			$hotspot_list[$fields[0]]["exon".$fields[1]][$fields[2]] = '';
		}		
		return $hotspot_list;
	}	

	static public function readFile($file) {		
		$fh = fopen($file, "rb");
		$lines = array();
		while (!feof($fh) ) {
			$line = fgets($fh);
			$line = trim($line);
			if ($line == '') continue;
			$lines[] = $line;
		}
		fclose($fh);
		return $lines;
	}

	static public function getVarActionable($patient_id, $sample_id, $case_name, $type, $flag=false, $var_hash=[]) {
		if ($type == "fusion") {
			$fusion = VarAnnotation::getFusionByPatient($patient_id, $case_name);
			$fusion_array = $fusion->toArray();
			$cols = null;
			$content = "";
			foreach ($fusion_array as $row) {
				if ($cols == null) {
					$cols = array_keys($row);
					$cols = implode("\t", $cols);
					$content = $cols."\n";
				}
				$content .= implode("\t", array_values($row))."\n";
			}
			return $content;
		}		
		return VarSamples::getAnnotation($patient_id, $sample_id, $case_id, $type, $flag, $var_hash);		
	}

	static public function getFusionByPatient($patient_id, $case_name=null) {
		$col_list="case_id,PATIENT_ID,left_gene,right_gene,left_chr,left_position,right_chr,right_position,sample_id,tool,type,var_level,left_region,right_region,left_trans,right_trans,left_sanger,right_sanger,left_cancer_gene,right_cancer_gene";
		if ($case_name == "any" || $case_name == null)
			$rows = DB::select("select $col_list from var_fusion v where v.patient_id='$patient_id'");
		else
			$rows = DB::select("select $col_list from var_fusion v where v.patient_id='$patient_id' and exists(select * from sample_cases s where v.sample_id=s.sample_id and s.case_name='$case_name' and v.case_id=s.case_id)");
			//$rows = VarFusion::where('case_id', '=', $case_id)->where('patient_id', '=', $patient_id)->get();
		//return $rows;
		$cases = Patient::getCasesByPatientID(null, $patient_id, $case_name);
		$case = null;
		$case_id = "any";
		$qci_data = null;
		if (count($cases) > 0) {
			$case = $cases[0];
			$case_id = $case->case_id;
			$qci_data = VarAnnotation::getQCI($patient_id, $case_id, "fusion");
			Log::info("Total QCI:".count($qci_data));
			if (count($qci_data) == 0)
				$qci_data = null;
		}

		return VarAnnotation::postProcessFusion($rows, $qci_data);
	}

	static public function getSplice($project_id, $patient_id, $case_name=null) {
		$sql = "select distinct v.* from project_cases p, var_splicing v where p.project_id=$project_id and p.patient_id = v.patient_id and p.case_id=v.case_id and v.patient_id='$patient_id'";
		$case_condition = " and p.case_name = '$case_name'";
		if ($case_name == "any" || $case_name == null)
			$case_condition = "";
		$sql = "$sql $case_condition";
		$rows = DB::select($sql);
		Log::info($sql);
		return $rows;
	}

	static public function getCNVTSO($project_id, $patient_id, $case_name=null) {
		$sql = "select distinct v.*,q.qci_actionability,q.qci_assessment,q.qci_nooactionability,q.ref from project_cases p, var_cnvtso v left join var_qci q on v.patient_id=q.patient_id and v.case_id=q.case_id and v.chromosome=q.chromosome and v.start_pos = q.position where p.project_id=$project_id and p.patient_id = v.patient_id and p.case_id=v.case_id and v.patient_id='$patient_id'";
		$case_condition = " and p.case_name = '$case_name'";
		if ($case_name == "any" || $case_name == null)
			$case_condition = "";
		$sql = "$sql $case_condition";
		Log::info("SQL : $sql");
		$rows = DB::select($sql);
		return $rows;
	}

	static public function getFusionDetailDataV2($left_gene, $right_gene, $left_chr, $right_chr, $left_position, $right_position, $sample_id) {
		#$sql = "select * from var_fusion_details where left_gene = '$left_gene' and right_gene = '$right_gene' and left_chr = '$left_chr' and right_chr = '$right_chr' and left_position = '$left_position' and right_position = '$right_position'";
		$col_list = "left_gene,right_gene,left_chr,left_position,right_chr,right_position,fusion_proteins,left_info,right_info";
		$sql = "select $col_list from var_fusion where left_gene = '$left_gene' and right_gene = '$right_gene' and left_chr = '$left_chr' and right_chr = '$right_chr' and left_position = '$left_position' and right_position = '$right_position' and sample_id='$sample_id'";
		$rows = DB::select($sql);
		return $rows;
	}

	static public function getFusionDetailData($left_gene, $right_gene, $left_chr, $right_chr, $left_position, $right_position, $include_domain=false) {
		$domain_stmt = "";
		if ($include_domain)
			$domain_stmt = ",domain";
		$sql = "select pep_length,type,trans_list$domain_stmt from var_fusion_dtl where left_gene = '$left_gene' and right_gene = '$right_gene' and left_chr = '$left_chr' and right_chr = '$right_chr' and left_position = '$left_position' and right_position = '$right_position' order by pep_length desc";
		//$sql = "select 500 as pep_length,type, trans_list, '' as domain from var_fusion_dtl where left_gene = '$left_gene' and right_gene = '$right_gene' and left_chr = '$left_chr' and right_chr = '$right_chr' and left_position = '$left_position' and right_position = '$right_position'";
		Log::info($sql);
		$rows = DB::select($sql);
		return $rows;
	}

	static public function getFusionDomain($left_gene, $right_gene, $left_chr, $right_chr, $left_position, $right_position, $left_trans, $right_trans) {
		$rows = VarAnnotation::getFusionDetailData($left_gene, $right_gene, $left_chr, $right_chr, $left_position, $right_position, true);
		foreach ($rows as $row) {
			$trans_list = json_decode($row->trans_list);
			if (!isset($trans_list))
    			continue;
			foreach ($trans_list as $trans_pair) {
				if ($left_trans == $trans_pair[0] && $right_trans == $trans_pair[1])
					return $row->domain;
			}
		}
		return null;
		
	}

	static public function postProcessFusion($rows, $qci_data=null) {
		$user_filter_list = UserGeneList::getGeneList("fusion");
		/*
		$fusion_gene_list = array();
		foreach ($user_filter_list as $list_name => $gene_list) {
			if (strtolower($list_name) == "clinomics_gene_list") {
				$fusion_gene_list = $gene_list;
			}
		}
		*/				
		foreach ($rows as $row) {
			//user defined filters
			if ($qci_data != null) {
				#Log::info("has qci data");
				$key = "$row->patient_id:$row->case_id:$row->left_chr/$row->right_chr:$row->left_position/$row->right_position";
				$key_tso = "$row->patient_id:$row->case_id:$row->left_chr:$row->left_position";
				if (array_key_exists($key, $qci_data)) {
					$qci_row = $qci_data[$key];
					$row->{"qci_assessment"} = $qci_row[0];
					$row->{"qci_actionability"} = $qci_row[1];
					$row->{"qci_nooactionability"} = $qci_row[2];
				} elseif (array_key_exists($key_tso, $qci_data)) {
					$qci_row = $qci_data[$key_tso];
					$row->{"qci_assessment"} = $qci_row[0];
					$row->{"qci_actionability"} = $qci_row[1];
					$row->{"qci_nooactionability"} = $qci_row[2];
				} else {	
					$row->{"qci_assessment"} = "";
					$row->{"qci_actionability"} = "";
					$row->{"qci_nooactionability"} = "";				
				}				
			}
			foreach ($user_filter_list as $list_name => $gene_list) {
				$has_gene = (array_key_exists($row->left_gene, $gene_list) || array_key_exists($row->right_gene, $gene_list))? "Y" : "";				
				$row->{$list_name} = $has_gene;
			}

			/*
			$left_cancer_gene_exists = array_key_exists($row->left_gene, $fusion_gene_list);
			$right_cancer_gene_exists = array_key_exists($row->right_gene, $fusion_gene_list);
			$row->var_level = '';
			if ($row->type == "in-frame" || $row->type == "right gene intact") {
				$row->var_level = "Tier 4";
				if ($left_cancer_gene_exists || $right_cancer_gene_exists)
					$row->var_level = "Tier 2";
				if ($left_cancer_gene_exists && $right_cancer_gene_exists)
					$row->var_level = "Tier 1";				
			} else {
				if ($left_cancer_gene_exists || $right_cancer_gene_exists)
					$row->var_level = "Tier 3";
			}
			*/			
		}
		
		return $rows;
	}

	static public function getDiagnosisAACohorts($project_id, $gene, $type) {
		$sql = "select * from var_diagnosis_aa_cohort where project_id = $project_id and gene = '$gene' and type = '$type'";
		return DB::select($sql);
	}

	static public function getDiagnosisGeneCohorts($project_id, $gene, $type) {
		$sql = "select * from var_diagnosis_gene_cohort where project_id = $project_id and gene = '$gene' and type = '$type'";
		return DB::select($sql);
	}

	static public function getGeneCohorts($project_id, $gene, $type) {
		$sql = "select * from var_gene_cohort where project_id = $project_id and gene = '$gene' and type = '$type'";
		return DB::select($sql);
	}

	static public function getTableName() {
		return Config::get("site.avia_table");
		#return "var_sample_avia_oc";
	}
	static function getAAChangeHGVSFormat($chr, $start_pos, $end_pos, $ref, $alt, $gene, $transcript) {
		$table_name = VarAnnotation::getTableName();
		// $sql = "select * from var_annotation_details where chromosome='$chr' and start_pos='$start_pos' and end_pos = '$end_pos' and ref = '$ref' and alt = '$alt' and type = 'refgene' and attr_name in ('AAChange', 'ExonicFunc')"; deprecated 

		// $rows = DB::select($sql);
		if (1==1){##HV added to accomodate those variants not already loaded into the var_annotation_details table through the DNASeq pipelines, e.g through hotspots or RNASeq
			#$sql="SELECT distinct annovar_feat,regexp_replace(regexp_replace(REGEXP_SUBSTR(annovar_feat,'$transcript" . "\.\d+[^,;]+'),':exon\\d+',''),':p.[^,]*','') hgvs FROM var_sample_avia where chromosome='$chr' and start_pos=$start_pos and end_pos=	$end_pos	and ref='$ref' and alt='$alt'";
			$sql="SELECT distinct annovar_feat,hgvs_any as hgvs FROM $table_name where chromosome='$chr' and start_pos=$start_pos and end_pos=$end_pos	and ref='$ref' and alt='$alt'";
			Log::info($sql);
			
  			$rows= DB::select($sql);
  			if (preg_match("/frameshift substitution/i",$rows[0]->annovar_feat)){
  				if (preg_match("/^($transcript\.\d+):c\.([0-9]+_[0-9]+)([A-Z]+)$/i", $rows[0]->hgvs, $matches)){
					if (count($matches) > 0)
						return $matches[1].":".$matches[2]."delins".$matches[3];
  				}
  			}
  			return $rows[0]->hgvs;
  			
		}

		$hgvs_string = "";
		$exonicfunc = "";
		$aachange_string = "";
		foreach ($rows as $row) {
			if (strtolower($row->attr_name) == "exonicfunc" )
				$exonicfunc = $row->attr_value;
			if (strtolower($row->attr_name) == "aachange" )
				$aachange_string = $row->attr_value;
		}
		$aachanges = explode(",", $aachange_string);
		foreach ($aachanges as $aachange) {
			$aachange_items = explode(":", $aachange);
			$items_cnt = count($aachange_items);
			if ($items_cnt > 0) {
				if ($transcript == $aachange_items[1]) {
					if (preg_match("/frameshift substitution/", $exonicfunc)) {#$exonicfunc == "nonframeshift substitution"
						preg_match('/^c\.([0-9]+_[0-9]+)([A-Z]+)$/i', $aachange_items[3], $matches);
						if (count($matches) > 0)
							return $aachange_items[1].":".$matches[1]."delins".$matches[2];
					}
					return $aachange_items[1].":".$aachange_items[3];
				}
			}
		}
		return "";
	}

	static function getTCellExTRECTPlot($path, $patient_id, $case_id, $sample_id, $sample_name) {
		$file_name = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/$sample_name/TCellExTRECT/${sample_name}_TCellExTRECT_with_tumor.pdf";
		if (file_exists($file_name))
			return $file_name;
		$file_name = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/$sample_name/TCellExTRECT/${sample_name}_TCellExTRECT_naive.pdf";
		if (file_exists($file_name))
			return $file_name;
		$file_name = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/$sample_id/TCellExTRECT/${sample_id}_TCellExTRECT_with_tumor.pdf";
		if (file_exists($file_name))
			return $file_name;
		$file_name = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/$sample_id/TCellExTRECT/${sample_id}_TCellExTRECT_naive.pdf";
		if (file_exists($file_name))
			return $file_name;
		return "";
	}

	static function getSignatureFileName($path, $patient_id, $case_id, $sample_id, $sample_name, $file="mutationalSignature") {
		$file_name = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/Actionable/$sample_name".".$file.pdf";
		if (file_exists($file_name))
			return $file_name;		
		if ($sample_id == "") {
			$samples = Sample::where("sample_name", $sample_name)->get();
			if (count($samples) > 0) {
				$sample_id = $samples[0]->sample_id;
			}
		}
		$file_name = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/Actionable/$sample_id".".$file.pdf";
		if (file_exists($file_name))
			return $file_name;
		$file_name = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/Actionable/$file.pdf";
		if (file_exists($file_name))
			return $file_name;
		return "";
	}

	static function getAntigenFileName($path, $patient_id, $case_id, $sample_id, $sample_name) {		
		$file_name = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/$sample_name/NeoAntigen/$sample_name.final.txt";
		if (file_exists($file_name))
			return $file_name;				
		$file_name = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/$sample_id/NeoAntigen/$sample_id.final.txt";
		if (file_exists($file_name))
			return $file_name;
		$file_name = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/Sample_$sample_id/NeoAntigen/Sample_$sample_id.final.txt";
		if (file_exists($file_name))
			return $file_name;
		return "";
	}

	static function getArribaPDFName($path, $patient_id, $case_id, $sample_id, $sample_name) {
		$file_name = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/$sample_name/arriba_out/fusions.pdf";
		if (file_exists($file_name))
			return $file_name;					
		$file_name = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/$sample_id/arriba_out/fusions.pdf";
		if (file_exists($file_name))
			return $file_name;
		return "";
	}

	static function getHLAFileName($path, $patient_id, $case_id, $sample_id, $sample_name) {
		Log::info("getHLAFileName: $path, $patient_id, $case_id, $sample_id, $sample_name");
		$file_name = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/$sample_name/HLA/$sample_name.Calls.txt";
		Log::info("file_name: $file_name");
		if (file_exists($file_name))
			return $file_name;					
		$file_name = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/$sample_id/HLA/$sample_id.Calls.txt";
		if (file_exists($file_name))
			return $file_name;
		$file_name = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/Sample_$sample_id/HLA/Sample_$sample_id.Calls.txt";		
		if (file_exists($file_name))
			return $file_name;
		return "";
	}

	static function getNewAIVAVariantCount($patient_id, $case_id, $type) {
		$table_name = VarAnnotation::getTableName();		
		$cnt_avia = DB::select("select count(*) as cnt from $table_name where patient_id='$patient_id' and case_id='$case_id' and type='$type'")[0]->cnt;
		if ($cnt_avia == 0) {
			$avia_table = Config::get("site.hg19_annot_table");
			$cnt_smp = DB::select("select count(*) as cnt from var_samples s where patient_id='$patient_id' and case_id='$case_id' and type='$type' and not exists(select * from $avia_table a where s.chromosome=a.chr and s.start_pos=a.query_start and s.end_pos=a.query_end and s.ref=a.allele1 and s.alt=a.allele2)")[0]->cnt;
			return 	$cnt_smp;

		}
		
		return 0;
	}

	static function getAntigen($patient_id, $case_id, $sample_id) {
		$case_condition = "";
		if ($case_id != "any")
			$case_condition = "and n.case_id = '$case_id'";
		$sample_condition = "";
		if ($sample_id != "any")
			$sample_condition = "and n.sample_id = '$sample_id'"; 
		$time_start = microtime(true);		
		#$sql = "select v.*, g.gene from var_cnv v, gene g where patient_id = '$patient_id' $case_condition $sample_condition and v.chromosome=g.chromosome and v.end_pos >= g.start_pos and v.start_pos <= g.end_pos and g.target_type='refseq' order by v.chromosome, v.start_pos, v.end_pos, v.cnt, v.allele_a, v.allele_b";
		$sql = "select distinct n.*,var_cov,total_cov,normal_var_cov,normal_total_cov,v.matched_var_cov,v.matched_total_cov from neo_antigen n left join var_samples v on 
					n.patient_id = v.patient_id and
					n.case_id = v.case_id and
					n.sample_id = v.sample_id and
					n.chromosome = v.chromosome and
					n.start_pos = v.start_pos and
					n.end_pos = v.end_pos and
					n.ref = v.ref and
					n.alt = v.alt and v.type <> 'hotspot'
				where n.patient_id = '$patient_id' $case_condition $sample_condition";
		Log::info("getAntigen: ".$sql);
		$time = microtime(true) - $time_start;
		Log::info("execution time (getAntigen): $time seconds");
		$rows = DB::select($sql);
		return $rows;
	}

	static function getCNVGeneLevelSamples($patient_id, $case_id, $sample_id, $source="sequenza") {
		$case_condition = "";
		if ($case_id != "any")
			$case_condition = "and v.case_id = '$case_id'";
		
		$cnv_table = ($source == "sequenza")? "var_cnv_gene_level" : "var_cnvkit_gene_level";
		
		$sample_condition = "";
		if ($sample_id != "any")
			$sample_condition = "and v.sample_id = '$sample_id'"; 
		$time_start = microtime(true);
		$sql = "select distinct sample_id from $cnv_table v where v.patient_id = '$patient_id' $case_condition $sample_condition";
		Log::info("getCNVGeneLevelSamples: ".$sql);
		$time = microtime(true) - $time_start;
		Log::info("execution time (getCNVGeneLevelSamples): $time seconds");
		$rows = DB::select($sql);
		return $rows;
	}

	static function getCNV($patient_id, $case_id, $sample_id, $source="sequenza", $project_id="any") {
		$case_condition = "";
		$project_condition = "";
		if ($case_id != "any")
			$case_condition = "and v.case_id = '$case_id'";
		else {
			if ($project_id != "any")
				$case_condition = "and exists(select * from project_cases p where v.patient_id=p.patient_id and v.case_id=p.case_id and p.project_id=$project_id)";
		}
		$sample_condition = "";
		if ($sample_id != "any")
			$sample_condition = "and v.sample_id = '$sample_id'"; 
		$time_start = microtime(true);
		#$sql = "select v.*, g.gene from var_cnv v, gene g where patient_id = '$patient_id' $case_condition $sample_condition and v.chromosome=g.chromosome and v.end_pos >= g.start_pos and v.start_pos <= g.end_pos and g.target_type='refseq' order by v.chromosome, v.start_pos, v.end_pos, v.cnt, v.allele_a, v.allele_b";
		if ($source == "sequenza"){
			#$sql = "select v.*, a.diagnosis from var_cnv_genes v,patients a  where  v.patient_id = '$patient_id' and  v.patient_id=a.patient_id  $case_condition $sample_condition order by chromosome, start_pos, end_pos, cnt, allele_a, allele_b";
			$sql = "select v.*, a.diagnosis from var_cnv_segment v,patients a where  v.patient_id = '$patient_id' and  v.patient_id=a.patient_id  $case_condition $sample_condition order by chromosome, start_pos, end_pos, cnt, allele_a, allele_b";
			Log::info($sql);
		}
		else
			#$sql = "select v.*, c.case_name, s.sample_name,a.diagnosis from var_cnvkit_genes v,patients a,samples s,cases c where v.patient_id = '$patient_id'  and  v.patient_id=a.patient_id and v.patient_id=c.patient_id and v.case_id=c.case_id and v.sample_id=s.sample_id $case_condition $sample_condition order by chromosome, start_pos, end_pos, log2";
			$sql = "select v.*, s.sample_name,a.diagnosis from var_cnvkit_segment v,patients a,samples s where v.patient_id = '$patient_id'  and  v.patient_id=a.patient_id and v.sample_id=s.sample_id $case_condition $sample_condition order by chromosome, start_pos, end_pos, log2";
		Log::info("getCNV: ".$sql);
		$time = microtime(true) - $time_start;		
		$rows = DB::select($sql);
		Log::info("execution time (getCNV): $time seconds");
		return $rows;
	}

	static function getCNVByGene($project_id, $gene) {
		//$sql = "select v.*, g.gene from var_cnv v, gene g, project_patients p where v.patient_id=p.patient_id and p.project_id=$project_id and g.symbol='$gene' and v.chromosome=g.chromosome and v.end_pos >= g.start_pos and v.start_pos <= g.end_pos and g.target_type='refseq' order by v.sample_id, v.chromosome, v.start_pos";
		
		#$sql = "select v.* from var_cnv_genes v, project_patients p where v.patient_id=p.patient_id and project_id=$project_id and gene = '$gene' order by chromosome, start_pos, end_pos, cnt, allele_a, allele_b";
		$sql="select v.*, a.diagnosis from var_cnv_gene_level v, project_patients p, patients a where v.patient_id=p.patient_id and v.patient_id=a.patient_id and project_id=$project_id and gene = '$gene' order by chromosome, start_pos, end_pos, cnt, allele_a, allele_b";		
		if ($project_id == "any")
			$sql = "select v.*,a.diagnosis from var_cnv_gene_level v,patients a where gene = '$gene' and v.patient_id=a.patient_id order by chromosome, start_pos, end_pos, cnt, allele_a, allele_b";
		
		$rows = DB::select($sql);
		return $rows;
	}

	static function getTopVarGenesWithUser($topn=20) {
		$logged_user = User::getCurrentUser();
		if ($logged_user == null)
			return null;

		#$sql = "select v1.type, v1.diagnosis, v1.gene, v1.cnt as patient_count from (select * from (select sum(cnt) as patient_count, gene, type from var_diagnosis_gene_cohort p, user_projects u where p.project_id=u.project_id and u.user_id=$logged_user->id and type='germline' group by gene, type order by patient_count desc) where rownum <= 20 union select * from (select sum(cnt) as patient_count, gene, type from var_diagnosis_gene_cohort p, user_projects u where p.project_id=u.project_id and u.user_id=$logged_user->id and type='somatic' group by gene, type order by patient_count desc) where rownum <= 20 union select * from (select sum(cnt) as patient_count, gene, type from var_diagnosis_gene_cohort p, user_projects u where p.project_id=u.project_id and u.user_id=$logged_user->id and type='rnaseq' group by gene, type order by patient_count desc) where rownum <= 20 union select * from (select sum(cnt) as patient_count, gene, type from var_diagnosis_gene_cohort p, user_projects u where p.project_id=u.project_id and u.user_id=$logged_user->id and type='variants' group by gene, type order by patient_count desc) where rownum <= 20) v2, var_diagnosis_gene_cohort v1,user_projects u where v1.gene=v2.gene and v1.type=v2.type and v1.project_id=u.project_id and u.user_id=$logged_user->id";
		$sql = "select v1.type, v1.diagnosis, v1.gene, v1.cnt as patient_count from (select * from (select sum(cnt) as patient_count, gene, type from var_diagnosis_gene_cohort p, user_projects u where p.project_id=u.project_id and u.user_id=$logged_user->id and type='germline' group by gene, type order by patient_count desc) where rownum <= 20 union select * from (select sum(cnt) as patient_count, gene, type from var_diagnosis_gene_cohort p, user_projects u where p.project_id=u.project_id and u.user_id=$logged_user->id and type='somatic' group by gene, type order by patient_count desc) where rownum <= 20) v2, var_diagnosis_gene_cohort v1,user_projects u where v1.gene=v2.gene and v1.type=v2.type and v1.project_id=u.project_id and u.user_id=$logged_user->id";
		Log::info($sql);
		$rows = DB::select($sql);
		$data = array();
   		$gene_count = array();
   		foreach ($rows as $row) {
   			$data[$row->type][$row->diagnosis][$row->gene] = (int)$row->patient_count;
   			$gene_count[$row->type][$row->gene] = (int)$row->patient_count + (isset($gene_count[$row->type][$row->gene])? $gene_count[$row->type][$row->gene] : 0);
   		}
   		$results = array();
   		foreach ($data as $type => $diag_data) {
   			$genes = $gene_count[$type];
   			Log::info($type);
   			Log::info(json_encode($genes));
   			arsort($genes);
   			Log::info(json_encode($genes));
   			$series = array();
   			$diagnoses = array_keys($data[$type]);
   			$category = array_keys($genes);   			
   			foreach ($diagnoses as $diag) {
   				$diag_values = array();
   				foreach ($category as $gene)
   					$diag_values[] = isset($data[$type][$diag][$gene]) ? $data[$type][$diag][$gene] : 0;
   				$series[] = array("name" => $diag, "data" => $diag_values);
   			}
   			$results[$type] = array("category" => $category, "series" => $series);
   		}
		return $results;
	}

	static function getTopVarGenes() {
		$sql = "select v1.gene, p.diagnosis, v1.type, count(distinct v1.patient_id) as patient_count from var_genes v1, patients p, var_top20 v2 where v1.patient_id=p.patient_id and v1.gene=v2.gene and v1.type=v2.type group by v1.gene, p.diagnosis, v1.type";
		Log::info($sql);
		$rows = DB::select($sql);
		$data = array();
   		$gene_count = array();
   		foreach ($rows as $row) {
   			$data[$row->type][$row->diagnosis][$row->gene] = (int)$row->patient_count;
   			$gene_count[$row->type][$row->gene] = (int)$row->patient_count + (isset($gene_count[$row->type][$row->gene])? $gene_count[$row->type][$row->gene] : 0);
   		}
   		$results = array();
   		foreach ($data as $type => $diag_data) {
   			$genes = $gene_count[$type];
   			arsort($genes);
   			$series = array();
   			$diagnoses = array_keys($data[$type]);
   			$category = array_keys($genes);   			
   			foreach ($diagnoses as $diag) {
   				$diag_values = array();
   				foreach ($category as $gene)
   					$diag_values[] = isset($data[$type][$diag][$gene]) ? $data[$type][$diag][$gene] : 0;
   				$series[] = array("name" => $diag, "data" => $diag_values);
   			}
   			$results[$type] = array("category" => $category, "series" => $series);
   		}
		return $results;	
	}

	static function getVarGeneSummary($gene_id, $value_type, $category, $min_pat, $tier_str) {
		$sql = "select id, category, type, germline_level, somatic_level, count(distinct patient_id) as patient_count from (select distinct p2.id, v.patient_id, p2.name as category, type, min(substr(germline_level, 6,1)) as germline_level, min(substr(somatic_level, 6,1)) as somatic_level from var_gene_tier v, project_patients p1, projects p2 where gene='$gene_id' and (type='germline' or type='somatic') and p1.project_id=p2.id and v.patient_id=p1.patient_id 
group by p2.id,v.patient_id,p2.name,type) group by id, category, type, germline_level, somatic_level order by type, germline_level, somatic_level";
		if ($category == "diagnosis")
			$sql = "select '' as id, category, type, germline_level, somatic_level, count(distinct patient_id) as patient_count from (select distinct v.patient_id, diagnosis as category, type, min(substr(germline_level, 6,1)) as germline_level, min(substr(somatic_level, 6,1)) as somatic_level 
from var_gene_tier v, patients p where gene='$gene_id' and (type='germline' or type='somatic') and v.patient_id=p.patient_id 
group by v.patient_id,diagnosis,type) group by category, type, germline_level, somatic_level order by type, germline_level, somatic_level";
		$rows = DB::select($sql);
		//Log::info($sql);
		$data = array();
   		$count_data = array();

   		$total_counts = array();
   		if ($value_type == "frequency") {
   			if ($category == "project") {
	   			Log::info("get total count");
	   			$pat_cnts = DB::table('project_patient_summary')->get();
	   			foreach ($pat_cnts as $pat_cnt) {
	   				$total_counts["p$pat_cnt->project_id"] = $pat_cnt->patients;
	   			}
	   		} else {
		   		$pat_cnts = Patient::getDiagnosisCount();
	   			foreach ($pat_cnts as $pat_cnt) {
	   				$total_counts[$pat_cnt->diagnosis] = $pat_cnt->patient_count;
	   			}	
	   		}
   		}
   		$query_tiers = explode(",", $tier_str);
   		foreach ($rows as $row) {
			if ($row->patient_count < (int)$min_pat)
				continue;
			$level = ($row->type == "germline")? $row->germline_level : $row->somatic_level;
			$level = ($level == "")? "No Tier" : "Tier $level";
			if (array_search($level, $query_tiers) === FALSE)
				continue;
			$cat_id = ($category == "project")? "p$row->id" : $row->category;
			$data[$row->type][$level][$row->category]["count"] = (int)$row->patient_count;
			if ($value_type == "frequency") {
				$data[$row->type][$level][$row->category]["frequency"] = round($row->patient_count / $total_counts[$cat_id] * 100, 2);
			}
			$count_data[$row->type][$row->category] = (float)$data[$row->type][$level][$row->category][$value_type] + (isset($count_data[$row->type][$row->category])? $count_data[$row->type][$row->category] : 0);
		}
		$results = array();
   		foreach ($data as $type => $tier_data) {
   			$prjs = $count_data[$type];
   			arsort($prjs);
   			$series = array();
   			$tiers = array_keys($data[$type]);
   			$categories = array_keys($prjs);   			
   			foreach ($tiers as $tier) {
   				$tier_values = array();
   				foreach ($categories as $cat) {
   					if ($value_type == "frequency")
   						$tier_values[] = array("y" => isset($data[$type][$tier][$cat]["frequency"]) ? $data[$type][$tier][$cat]["frequency"] : 0, "raw" => "(".(isset($data[$type][$tier][$cat]["count"]) ? $data[$type][$tier][$cat]["count"] : 0).")");
   					else
   						$tier_values[] = array("y" => isset($data[$type][$tier][$cat]["count"]) ? $data[$type][$tier][$cat]["count"] : 0, "raw" => "");
   				}
   				$series[] = array("name" => $tier, "data" => $tier_values);
   			}
   			$results[$type] = array("category" => $categories, "series" => $series);
   		}
		return $results;
	}

	static function getCNVGeneSummary($gene_id, $value_type, $category, $min_pat, $min_amplified, $max_deleted) {
		$sql = "select id, name as category, count(distinct v.patient_id) as patient_count, (case when cnt >= $min_amplified then 'Amplified' when cnt <= $max_deleted then 'Deleted' end) as type 
				from var_cnv_genes v, project_patients p1, projects p2 
				where 
				p1.project_id=p2.id and
				v.patient_id=p1.patient_id and
				gene='$gene_id' and (cnt >= $min_amplified or cnt <= $max_deleted) group by id,name, (case when cnt >= $min_amplified then 'Amplified' when cnt <= $max_deleted then 'Deleted' end)";
				
		if ($category == "diagnosis")
			$sql = "select diagnosis as category, count(distinct v.patient_id) as patient_count, (case when cnt >= $min_amplified then 'Amplified' when cnt <= $max_deleted then 'Deleted' end) as type 
				from var_cnv_genes v, patients p 
				where 
				v.patient_id=p.patient_id and
				gene='$gene_id' and (cnt >= $min_amplified or cnt <= $max_deleted) group by diagnosis, (case when cnt >= $min_amplified then 'Amplified' when cnt <= $max_deleted then 'Deleted' end)";
		$rows = DB::select($sql);
		//Log::info($sql);
		$data = array();
   		$count_data = array();

   		$total_counts = array();
   		if ($value_type == "frequency") {
   			if ($category == "project") {
	   			Log::info("get total count");
	   			$pat_cnts = DB::table('project_patient_summary')->get();
	   			foreach ($pat_cnts as $pat_cnt) {
	   				$total_counts["p$pat_cnt->project_id"] = $pat_cnt->patients;
	   			}
	   		} else {
		   		$pat_cnts = Patient::getDiagnosisCount();
	   			foreach ($pat_cnts as $pat_cnt) {
	   				$total_counts[$pat_cnt->diagnosis] = $pat_cnt->patient_count;
	   			}	
	   		}
   		}
   		$categories = array();
   		$types = array();
   		foreach ($rows as $row) {
			if ($row->patient_count < (int)$min_pat)
				continue;
			$cat_id = ($category == "project")? "p$row->id" : $row->category;
			$data[$row->type][$row->category]["count"] = (int)$row->patient_count;
			if ($value_type == "frequency") {
				$data[$row->type][$row->category]["frequency"] = round($row->patient_count / $total_counts[$cat_id] * 100, 2);
			}			
			$count_data[$row->type][$row->category] = (float)$data[$row->type][$row->category][$value_type] + (isset($count_data[$row->type][$row->category][$value_type])? $count_data[$row->type][$row->category][$value_type] : 0);
			$categories[$row->category] = isset($categories[$row->category])? $categories[$row->category] + $data[$row->type][$row->category][$value_type] : $data[$row->type][$row->category][$value_type];
			$types[$row->type] = '';
		}
		arsort($categories);
		$categories = array_keys($categories);
		$types = array_keys($types);
		$results = array();

		$series = array();
   		foreach ($types as $type) {
   			$type_values = array();
   			foreach ($categories as $cat) {
   				if ($value_type == "frequency")
   					$type_values[] = array("y" => isset($data[$type][$cat]["frequency"]) ? $data[$type][$cat]["frequency"] : 0, "raw" => "(".(isset($data[$type][$cat]["count"]) ? $data[$type][$cat]["count"] : 0).")");
   				else
   					$type_values[] = array("y" => isset($data[$type][$cat]["count"]) ? $data[$type][$cat]["count"] : 0, "raw" => "");
   			}
   			$series[] = array("name" => $type, "data" => $type_values);   			
   		}
   		if (count($series) == 0)
   			return array();
		return array("category" => $categories, "series" => $series);
	}

	static function getFusionGeneSummary($gene_id, $value_type, $category, $min_pat, $fusion_type, $tier_str) {		
		$type_condition = ($fusion_type == "All")? "" : "and type = '$fusion_type'";
		$sql = "select p2.id, p2.name as category, count(distinct v.patient_id) as patient_count, substr(var_level,1,1) as var_level 
				from var_fusion v, project_patients p1, projects p2 
				where 
				p1.project_id=p2.id and
				v.patient_id=p1.patient_id and
				(left_gene='$gene_id' or right_gene='$gene_id') $type_condition group by p2.id,p2.name, substr(var_level,1,1)";
				
		if ($category == "diagnosis")
			$sql = "select diagnosis as category, count(distinct v.patient_id) as patient_count, substr(var_level,1,1) as var_level
				from var_fusion v, patients p 
				where 
				v.patient_id=p.patient_id and
				(left_gene='$gene_id' or right_gene='$gene_id') $type_condition group by diagnosis, substr(var_level,1,1)";
		$rows = DB::select($sql);
		Log::info($sql);
		$data = array();
   		$count_data = array();

   		$total_counts = array();

   		if ($value_type == "frequency") {
   			if ($category == "project") {
	   			Log::info("get total count");
	   			$pat_cnts = DB::table('project_patient_summary')->get();
	   			foreach ($pat_cnts as $pat_cnt) {
	   				$total_counts["p$pat_cnt->project_id"] = $pat_cnt->patients;
	   			}
	   		} else {
		   		$pat_cnts = Patient::getDiagnosisCount();
	   			foreach ($pat_cnts as $pat_cnt) {
	   				$total_counts[$pat_cnt->diagnosis] = $pat_cnt->patient_count;
	   			}	
	   		}
   		}
   		$categories = array();
   		$types = array();
   		$query_tiers = explode(",", $tier_str);
   		foreach ($rows as $row) {
			#if ($row->patient_count < (int)$min_pat)
			#	continue;
			$level = ($row->var_level == "No Tier")? $row->var_level : "Tier".substr($row->var_level, 0, 1);
			if (array_search($level, $query_tiers) === FALSE)
				continue;
			$cat_id = ($category == "project")? "p$row->id" : $row->category;
			$value = ($value_type == "frequency")? round($row->patient_count / $total_counts[$cat_id] * 100, 2) : (int)$row->patient_count;
			$data[$level][$row->category]["count"] = isset($data[$level][$row->category]["count"])? $data[$level][$row->category]["count"] + (int)$row->patient_count : (int)$row->patient_count;
			if ($value_type == "frequency") {
				$data[$level][$row->category]["frequency"] = isset($data[$level][$row->category]["frequency"])? $data[$level][$row->category]["frequency"] + $value : $value;
			}			
			$count_data[$level][$row->category] = (float)$data[$level][$row->category][$value_type] + (isset($count_data[$level][$row->category][$value_type])? $count_data[$level][$row->category][$value_type] : 0);
			$categories[$row->category] = isset($categories[$row->category])? $categories[$row->category] + $value : $value;
			$types[$level] = '';
		}
		arsort($categories);
		Log::info("Categories:");
		Log::info(json_encode($categories));
		$categories = array_keys($categories);
		Log::info(json_encode($categories));
		Log::info(json_encode($types));
		$types = array_keys($types);
		$results = array();

		$series = array();
   		foreach ($types as $type) {
   			$type_values = array();
   			foreach ($categories as $cat) {
   				if ($value_type == "frequency")
   					$type_values[] = array("y" => isset($data[$type][$cat]["frequency"]) ? $data[$type][$cat]["frequency"] : 0, "raw" => "(".(isset($data[$type][$cat]["count"]) ? $data[$type][$cat]["count"] : 0).")");
   				else
   					$type_values[] = array("y" => isset($data[$type][$cat]["count"]) ? $data[$type][$cat]["count"] : 0, "raw" => "");
   			}
   			$series[] = array("name" => $type, "data" => $type_values);   			
   		}
   		if (count($series) == 0)
   			return array();
		return array("category" => $categories, "series" => $series);
	}

	static function getFusionGenePairSummary($gene_id, $category, $min_pat, $fusion_type, $tier_str) {		
		$query_tiers = explode(",", $tier_str);
		$tier_condition = "and (";
		for ($i=0;$i<count($query_tiers);$i++) {
			$tier = $query_tiers[$i];
			$tier = substr($tier, 4);
			$or_op = ($i == 0)? "" : "or";
			//if ($tier == "1" || $tier == "2")
				$tier_condition .= " $or_op (var_level like '$tier%')";
			//else
				//$tier_condition .= " $or_op (var_level = '$tier')";
		}
		$tier_condition .= ")"; 
		$type_condition = ($fusion_type == "All")? "" : "and type = '$fusion_type'";
		$sql = "select p2.id, p2.name as category, count(distinct v.patient_id) as patient_count, left_gene, right_gene 
				from var_fusion v, project_patients p1, projects p2 
				where 
				p1.project_id=p2.id and
				v.patient_id=p1.patient_id and
				(left_gene='$gene_id' or right_gene='$gene_id') $type_condition $tier_condition group by p2.id,p2.name, left_gene, right_gene";
				
		if ($category == "diagnosis")
			$sql = "select diagnosis as category, count(distinct v.patient_id) as patient_count, left_gene, right_gene
				from var_fusion v, patients p 
				where 
				v.patient_id=p.patient_id and
				(left_gene='$gene_id' or right_gene='$gene_id') $type_condition $tier_condition group by diagnosis, left_gene, right_gene";
		$rows = DB::select($sql);
		Log::info($sql);
		$data = array();
   		$count_data = array();

   		$total_counts = array();
   		
   		$categories = array();
   		$types = array();
   		
   		foreach ($rows as $row) {
			if ($row->patient_count < (int)$min_pat)
				continue;
			$cat_id = ($category == "project")? "p$row->id" : $row->category;
			$type = $row->category;
			$gene_pair = $row->left_gene."->".$row->right_gene;
			$data[$type][$gene_pair]["count"] = (int)$row->patient_count;
			$count_data[$type][$gene_pair] = (float)$data[$type][$gene_pair]["count"] + (isset($count_data[$type][$gene_pair]["count"])? $count_data[$type][$gene_pair]["count"] : 0);
			$categories[$gene_pair] = isset($categories[$gene_pair])? $categories[$gene_pair] + $data[$type][$gene_pair]["count"] : $data[$type][$gene_pair]["count"];
			$types[$type] = '';
		}
		arsort($categories);
		$categories = array_keys($categories);
		$types = array_keys($types);
		$results = array();

		$series = array();
   		foreach ($types as $type) {
   			$type_values = array();
   			foreach ($categories as $cat) {
   				$type_values[] = array("y" => isset($data[$type][$cat]["count"]) ? $data[$type][$cat]["count"] : 0, "raw" => "");
   			}
   			$series[] = array("name" => $type, "data" => $type_values);   			
   		}
   		if (count($series) == 0)
   			return array();
		return array("category" => $categories, "series" => $series);
	}	

	static function getVarTypeByGene($gene_id) {
		$rows = DB::select("select distinct type from var_gene_cohort where gene='$gene_id'");
		$results = array();
		foreach ($rows as $row) {
			$results[] = $row->type;
		}
		return $results;
	}

	static function getPatientsByVarGene($gene_id, $type, $cat_type, $category, $tier_str) {
		$query_tiers = explode(",", $tier_str);
		$tier_field = ($type == "germline")? "germline_level" : "somatic_level";
		$type_condition = "and type = '$type' ";
		$tier_condition = "and (";
		for ($i=0;$i<count($query_tiers);$i++) {
			$tier = $query_tiers[$i];
			$or_op = ($i == 0)? "" : "or";
			if ($tier == "Tier 1")
				$tier_condition .= " $or_op ($tier_field like '$tier%')";
			else if ($tier == "No Tier")
				$tier_condition .= " $or_op ($tier_field is null)";
			else
				$tier_condition .= " $or_op ($tier_field = '$tier')";
		}
		$tier_condition .= ")";
		$sql = "select distinct p1.patient_id,p1.diagnosis,p1.project_name from patients p1, var_gene_tier v
					where p1.diagnosis='$category' and v.patient_id=p1.patient_id and v.gene='$gene_id' $type_condition $tier_condition";
		if ($cat_type == "project")
			$sql = "select distinct p1.patient_id,p1.diagnosis,p1.project_name from patients p1, project_patients p2, projects p3, var_gene_tier v
					where p1.patient_id=p2.patient_id and p2.project_id=p3.id and p3.name='$category' and v.patient_id=p1.patient_id and v.gene='$gene_id' $type_condition $tier_condition";
		Log::info($sql);
		$rows = DB::select($sql);
		$data = array();
		foreach ($rows as $row) {
			$data[] = array($row->patient_id, $row->diagnosis,$row->project_name);
		}
		return $data;
	}

	static function getPatientsByCNVGene($gene_id, $cat_type, $category, $min_amplified, $max_deleted) {
		$sql = "select distinct p1.patient_id,p1.diagnosis,p1.project_name, (case when cnt >= $min_amplified then 'Amplified' when cnt <= $max_deleted then 'Deleted' end) as type from patients p1, project_patients p2, projects p3, var_cnv_genes v
					where p1.patient_id=p2.patient_id and p2.project_id=p3.id and p3.name='$category' and v.patient_id=p1.patient_id and v.gene='$gene_id' and (cnt >= $min_amplified or cnt <= $max_deleted)";
			
		if ($cat_type == "diagnosis")
			$sql = "select distinct p1.patient_id,p1.diagnosis,p1.project_name, (case when cnt >= $min_amplified then 'Amplified' when cnt <= $max_deleted then 'Deleted' end) as type from patients p1, var_cnv_genes v
					where p1.diagnosis='$category' and v.patient_id=p1.patient_id and v.gene='$gene_id' and (cnt >= $min_amplified or cnt <= $max_deleted)";
		$rows = DB::select($sql);
		$data = array();
		foreach ($rows as $row) {
			$data[] = array($row->patient_id, $row->diagnosis,$row->type, $row->project_name);
		}
		return $data;

	}

	static function getPatientsByFusionGene($gene_id, $cat_type, $category, $fusion_type = 'All', $tier_str = 'Tier 1') {
		$query_tiers = explode(",", $tier_str);
		$tier_condition = "and (";
		for ($i=0;$i<count($query_tiers);$i++) {
			$tier = $query_tiers[$i];
			$tier = str_replace("Tier", "", $tier);
			$or_op = ($i == 0)? "" : "or";
			#if ($tier == "Tier 1"|| $tier == "Tier 2")
				$tier_condition .= " $or_op (var_level like '$tier%')";
			#else
			#	$tier_condition .= " $or_op (var_level = '$tier')";
		}
		$tier_condition .= ")"; 
		$type_condition = ($fusion_type == "All")? "" : "and type = '$fusion_type'";
		$sql = "select distinct p1.patient_id, p1.diagnosis, p2.project_name from var_fusion v, patients p1, project_patients p2, projects p3
				where v.patient_id=p1.patient_id and p1.patient_id=p2.patient_id and p2.project_id=p3.id and p3.name='$category' and (left_gene='$gene_id' or right_gene= '$gene_id') $type_condition $tier_condition";
		if ($cat_type == "diagnosis")
			$sql = "select distinct p1.patient_id, diagnosis, project_name from var_fusion v, patients p1
				where v.patient_id=p1.patient_id and p1.diagnosis='$category' and (left_gene='$gene_id' or right_gene= '$gene_id') $type_condition $tier_condition";
		Log::info($sql);
		$rows = DB::select($sql);
		$data = array();
		foreach ($rows as $row) {
			$data[] = array($row->patient_id, $row->diagnosis, $row->project_name);
		}
		return $data;
	}

	static function getPatientsByFusionPair($left_gene, $right_gene, $fusion_type = 'All', $tier_str = 'Tier 1') {
		$query_tiers = explode(",", $tier_str);
		$tier_condition = "and (";
		for ($i=0;$i<count($query_tiers);$i++) {
			$tier = $query_tiers[$i];
			$tier = str_replace("Tier", "", $tier);
			$or_op = ($i == 0)? "" : "or";
			#if ($tier == "Tier 1"|| $tier == "Tier 2")
				$tier_condition .= " $or_op (var_level like '$tier%')";
			#else
			#	$tier_condition .= " $or_op (var_level = '$tier')";
		}
		$tier_condition .= ")"; 
		$type_condition = ($fusion_type == "All")? "" : "and type = '$fusion_type'";
		$sql = "select distinct p.patient_id, diagnosis, project_name from var_fusion v, patients p 
				where v.patient_id=p.patient_id and left_gene='$left_gene' and right_gene= '$right_gene' $type_condition $tier_condition";
		Log::info($sql);
		$rows = DB::select($sql);
		$data = array();
		foreach ($rows as $row) {
			$data[] = array($row->patient_id, $row->diagnosis,$row->project_name);
		}
		return $data;
	}

	static public function hasMutationBurden($project_id, $patient_id, $case_name=null) {
		$case_condition = "";
		if ($project_id == "null") {			
			if ($case_name != "any" && $case_name != null)
				$case_condition = "and s.case_name='$case_name' ";
			$sql = "select count(*) as cnt from project_processed_cases p, sample_cases s, mutation_burden m where p.patient_id='$patient_id' and p.case_name='$case_name' and p.patient_id=s.patient_id and p.case_name=s.case_name and s.patient_id=m.patient_id $case_condition and s.case_id=m.case_id and m.sample_id=s.sample_id";
		} else {
			$sql = "select count(*) as cnt from sample_cases s, mutation_burden m where m.patient_id='$patient_id' $case_condition and m.sample_id=s.sample_id";		
		}		
		Log::info($sql);
		return DB::select($sql)[0]->cnt;
	}

	static public function getMutationBurden($project_id, $patient_id, $case_id) {
		$case_condition = "";
		if ($case_id != "any")
			$case_condition = "and m.case_id='$case_id' ";
		if ($project_id == "null") {			
			$sql = "select m.patient_id, m.case_id, p.diagnosis, sample_name, exp_type, caller, burden, total_bases, round(burden/total_bases*1000000,2) as burden_per_mb from samples s, patients p, mutation_burden m where m.patient_id='$patient_id' and m.patient_id=p.patient_id $case_condition and m.sample_id=s.sample_id";
			$rows = DB::select($sql);
		} else {
			$sql = "select m.patient_id, m.case_id, p.diagnosis, sample_name, exp_type, caller, burden, total_bases, round(burden/total_bases*1000000,2) as burden_per_mb from project_samples s, patients p, mutation_burden m where s.project_id=$project_id and m.patient_id=p.patient_id and m.patient_id='$patient_id' and m.sample_id=s.sample_id $case_condition";
			$rows = DB::select($sql);
		}
		Log::info($sql);		
		return $rows;
	}
}
