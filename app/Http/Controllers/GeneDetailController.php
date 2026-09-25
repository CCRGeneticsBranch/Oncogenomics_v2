<?php

namespace App\Http\Controllers;
use App\Models\VarAnnotation;
use Config,View,Log,Response;
use App\Models\Project;
use App\Models\CancerType;
use App\Models\User;
use App\Models\PCA;
use App\Models\Gene;


class GeneDetailController extends BaseController {

   #==========View Gene Details=========================        

#$start = microtime(true);
#$time_elapsed_secs = microtime(true) - $start;
#echo "Query time: $time_elapsed_secs <BR>";

	private $annotations =array('GO - Cellular Component'=>['ID', 'http://amigo.geneontology.org/amigo/medial_search?q='], 'GO - Biological Process'=>['ID', 'http://amigo.geneontology.org/amigo/medial_search?q='], 'GO - Molecular Function'=>['ID', 'http://amigo.geneontology.org/amigo/medial_search?q='], 'CTD Disease Info'=>['ID', 'http://www.nlm.nih.gov/cgi/mesh/2015/MB_cgi?field=uid&term='], 'GAD Disease Info'=>[], 'KEGG Pathway Info'=>['ID','http://www.genome.jp/kegg-bin/show_pathway?'], 'Reactome Pathway Name'=>[], 'General'=>['Ensembl Gene Info'=>'http://www.ensembl.org/Homo_sapiens/Gene/Summary?g=','Gene Symbol'=>'https://genome.ucsc.edu/cgi-bin/hgTracks?clade=mammal&org=Human&db=hg38&position=']);

	public function getExpGeneSummary($gene_id, $category,$tissue, $target_type="ensembl", $lib_type="all") {
		return json_encode(Gene::getExpGeneSummary($gene_id, $category,$tissue, $target_type, $lib_type));
	}

	public function viewProjectGeneDetail($project_id, $gene_id, $selected_tab_id=0) {
		$gene_id = strtoupper($gene_id);
		$project = Project::getProject($project_id);
		$genome_versions = $project->getExpressionGenomeVersion();
		$gene = Gene::getGene($gene_id);
		if ($gene == null) {
			return View::make('pages/error', ['message'=>"gene $gene_id not found!"]);
		}
		$gene_id = $gene->getSymbol();
		$ensembl_id = $gene->getEnsemblID();
		$symbol = $gene->getSymbol();
		if ($symbol == null) {
			return View::make('pages/error', ['message'=>"gene $gene_id not found!"]);
		}
		$display_id = $gene_id;
		if ($ensembl_id != null) {			
			$anno_info = $gene->getAnnotationInfo();
			$anno_header = array();
			$anno_data = array();
			$general_header = array(array("title" => "Name"), array("title" => "Value"));
			$general_data = array();
			foreach ($anno_info as $anno_key => $anno_value) {
				if (count($anno_value) > 1 || isset($this->annotations[$anno_key])) {
					for ($i=0;$i<count($anno_value);$i++) 
						if (isset($this->annotations[$anno_key]) && count($this->annotations[$anno_key]) > 0) {
							$val = $anno_value[$i];
							$key = $this->annotations[$anno_key][0];
							$anno_info[$anno_key][$i][$key] = "<a target='_blank' href='".$this->annotations[$anno_key][1].str_replace('MESH:','',$val[$key])."'>".$val[$key]."</a>";
					}
					list($headers, $table_data) = $this->hash2table($anno_info[$anno_key]);
					$anno_header[$anno_key] = $this->get_json_columns($headers);
					$anno_data[$anno_key] = $this->array2jsontable($table_data);
				} 
				else {
					if (isset($this->annotations['General'][$anno_key]))
						$anno_value[0]['ID'] = "<a target='_blank' href='".$this->annotations['General'][$anno_key].$anno_value[0]['ID']."'>".$anno_value[0]['ID']."</a>";
					$general_data[] = array(array($anno_key), array($anno_value[0]['ID']));
				} 
				              
			}
	    }
	    //survival diagnosis. used for drop-down menu
	    $diags = $project->getSurvivalDiagnosis();
	    /*
	    $tumor_project_data = $project->getGeneExpression(array($gene->getSymbol()));
	    $target_type_list = array();
	    if (count($tumor_project_data) > 0) {
			$target_type_list = array_keys($tumor_project_data["exp_data"][$gene_id]);
			rsort($target_type_list);
		}		
		*/
		$ret = $this->saveAccessLog($gene_id, $project_id, "gene");
		Log::info("saving log. Results: ".json_encode($ret));	
	    return View::make('pages/viewProjectGeneDetail', ['cohort_type' => 'Project', 'cohort'=>$project, 'survival_diagnosis'=>$diags, 'display_id'=>$display_id,'gene'=>$gene, 'genome_versions' => $genome_versions, 'anno_header' => $anno_header, 'anno_data' => $anno_data, 'general_header' => $general_header, 'general_data' => $general_data, "selected_tab_id"=>$selected_tab_id, 'include_public' => '']);
	}

	
	public function viewCancerTypeGeneDetail($cancer_type_id, $gene_id, $selected_tab_id=0, $include_public="N") {
		$gene_id = strtoupper($gene_id);
		$cancer_type = CancerType::find($cancer_type_id);
		$genome_versions = explode(",", $cancer_type->getGenomeVersion($include_public));
		$gene = Gene::getGene($gene_id);
		if ($gene == null) {
			return View::make('pages/error', ['message'=>"gene $gene_id not found!"]);
		}
		$gene_id = $gene->getSymbol();
		$ensembl_id = $gene->getEnsemblID();
		$symbol = $gene->getSymbol();
		if ($symbol == null) {
			return View::make('pages/error', ['message'=>"gene $gene_id not found!"]);
		}
		$display_id = $gene_id;
		if ($ensembl_id != null) {			
			$anno_info = $gene->getAnnotationInfo();
			$anno_header = array();
			$anno_data = array();
			$general_header = array(array("title" => "Name"), array("title" => "Value"));
			$general_data = array();
			foreach ($anno_info as $anno_key => $anno_value) {
				if (count($anno_value) > 1 || isset($this->annotations[$anno_key])) {
					for ($i=0;$i<count($anno_value);$i++) 
						if (isset($this->annotations[$anno_key]) && count($this->annotations[$anno_key]) > 0) {
							$val = $anno_value[$i];
							$key = $this->annotations[$anno_key][0];
							$anno_info[$anno_key][$i][$key] = "<a target='_blank' href='".$this->annotations[$anno_key][1].str_replace('MESH:','',$val[$key])."'>".$val[$key]."</a>";
					}
					list($headers, $table_data) = $this->hash2table($anno_info[$anno_key]);
					$anno_header[$anno_key] = $this->get_json_columns($headers);
					$anno_data[$anno_key] = $this->array2jsontable($table_data);
				} 
				else {
					if (isset($this->annotations['General'][$anno_key]))
						$anno_value[0]['ID'] = "<a target='_blank' href='".$this->annotations['General'][$anno_key].$anno_value[0]['ID']."'>".$anno_value[0]['ID']."</a>";
					$general_data[] = array(array($anno_key), array($anno_value[0]['ID']));
				} 
				              
			}
	    }
	    //survival diagnosis. used for drop-down menu
	    $diags = $cancer_type->getSurvivalDiagnosis();
	    
		$target_type_list = $cancer_type->getTargetTypes();	
		$ret = $this->saveAccessLog($gene_id, "any", "gene");
		Log::info("saving log. Results: ".json_encode($ret));	
	    return View::make('pages/viewProjectGeneDetail', ['cohort_type' => 'CancerType', 'cohort'=>$cancer_type, 'survival_diagnosis'=>$diags, 'display_id'=>$display_id,'gene'=>$gene, 'anno_header' => $anno_header, 'genome_versions' => $genome_versions, 'anno_data' => $anno_data, 'general_header' => $general_header, 'general_data' => $general_data, "selected_tab_id"=>$selected_tab_id, "target_type_list" => $target_type_list, 'include_public' => $include_public]);
	}

	public function viewGeneDetail($gene_id) {
		$gene = Gene::getGene($gene_id);
		if ($gene == null) {
			return View::make('pages/error', ['message'=>"gene $gene_id not found!"]);
		}
		$gene_id = $gene->getSymbol();
		$ensembl_id = $gene->getEnsemblID();
		$symbol = $gene->getSymbol();
		if ($symbol == null) {
			return View::make('pages/error', ['message'=>"gene $gene_id not found!"]);
		}
		$display_id = $gene_id;
		if ($ensembl_id != null) {			
			$anno_info = $gene->getAnnotationInfo();
			$anno_header = array();
			$anno_data = array();
			$general_header = array(array("title" => "Name"), array("title" => "Value"));
			$general_data = array();
			foreach ($anno_info as $anno_key => $anno_value) {
				if (count($anno_value) > 1 || isset($this->annotations[$anno_key])) {
					for ($i=0;$i<count($anno_value);$i++) 
						if (isset($this->annotations[$anno_key]) && count($this->annotations[$anno_key]) > 0) {
							$val = $anno_value[$i];
							$key = $this->annotations[$anno_key][0];
							$anno_info[$anno_key][$i][$key] = "<a target='_blank' href='".$this->annotations[$anno_key][1].str_replace('MESH:','',$val[$key])."'>".$val[$key]."</a>";
					}
					list($headers, $table_data) = $this->hash2table($anno_info[$anno_key]);
					$anno_header[$anno_key] = $this->get_json_columns($headers);
					$anno_data[$anno_key] = $this->array2jsontable($table_data);
				} 
				else {
					if (isset($this->annotations['General'][$anno_key]))
						$anno_value[0]['ID'] = "<a target='_blank' href='".$this->annotations['General'][$anno_key].$anno_value[0]['ID']."'>".$anno_value[0]['ID']."</a>";
					$general_data[] = array(array($anno_key), array($anno_value[0]['ID']));
				} 
				              
			}
	    }

	    $var_types = VarAnnotation::getVarTypeByGene($gene_id);
	    $ret = $this->saveAccessLog($gene_id, "any", "gene");
		return View::make('pages/viewGeneDetail', ['var_types' => $var_types, 'gene_id' => $gene_id, 'has_expression' => true, 'has_var' => true, 'anno_header' => $anno_header, 'anno_data' => $anno_data, 'general_header' => $general_header, 'general_data' => $general_data]);
	}

	



	

	public function getSurvivalData($sid, $target_id, $level, $data_type="UCSC") {
		$study = Study::getStudy($sid, $data_type);
		$surv_file = $study->getSurvivalFile($target_id, $level);
		if ($surv_file != null) {
			$pvalue_file = public_path()."/expression/$sid/survival_pvalue.$target_id.$data_type.tsv";
			$cmd = "Rscript ".app_path()."/scripts/survival_pvalues.r $surv_file $pvalue_file";			
			//return $cmd;
			$ret = shell_exec($cmd);
			if ($ret == "only one group")
				return $ret;
			list($median, $median_pvalue, $min_cutoff, $min_pvalue) = preg_split('/\s+/', $ret);
			$median_plot_url = $this->plotSurvival($sid, $target_id, $level, $median, $median_pvalue, $data_type);
			$min_plot_url = $this->plotSurvival($sid, $target_id, $level, $min_cutoff, $min_pvalue, $data_type);
			//return;
			$handle = fopen($pvalue_file, "r");
			$i = 1;
			if ($handle) {
				while (($line = fgets($handle)) !== false) {
					$line = trim($line);
					$fields = preg_split('/\s+/', $line);		
					$data[] = array($fields[0], $fields[1]);
				}
				foreach ($data as $d) {
					$vars[] = $i."/".count($data);
					$i++;
				}
				$json = array("data"=>array("z"=>array("",""),"y"=>array("vars"=>$vars, "smps"=>['Exp-cutoff', 'P-value'], "data" => $data)), "median"=>$median, "median_pvalue"=>$median_pvalue, "min_cutoff"=>$min_cutoff, "min_pvalue"=>$min_pvalue, 'median_plot_url'=>$median_plot_url, 'min_plot_url'=>$min_plot_url);
				return json_encode($json);
			}
		}
		
		
	}

	public function plotSurvival($sid, $target_id, $level, $cutoff, $pvalue, $data_type="UCSC") {
		$study = Study::getStudy($sid, $data_type);
		$surv_file = $study->getSurvivalFile($target_id, $level);
		$plot_file = "/expression/$sid/survival_pvalue$cutoff.$target_id.$data_type.svg";
		$cmd = "Rscript ".app_path()."/scripts/survival_fit.r $surv_file ".public_path()."$plot_file $cutoff $pvalue";
		//echo $cmd."<br>";
		$ret = shell_exec($cmd);
		return url($plot_file);
	}

	
	


	public function getExpressionByGene($sid, $gid) {
      		$sql = "select s.tissue_type, s.tissue_cat, s.sample_id, exp_value from study_samples s, expr e where s.study_id=$sid and gene='$gid' and s.sample_id=e.sample_id";
      		$gene_exprs = DB::select($sql);
		return $gene_exprs;
	}


	public function formatScientific($someFloat) {
		$power = ($someFloat % 10) - 1;
		return ($someFloat / pow(10, $power)) . "e" . $power;
	}


	public function getCorrelationHeatmapJson($corr, $sid, $gid, $data_type) {
		if ($corr == null) 
			return array(null, 0, 0);
		$study = Study::getStudy($sid, $data_type);
		$genes = array_keys($corr);
		list($raw_data, $groups) = $study->getCorrelationExp($genes);
		$samples = array_keys($raw_data);
		$levels = array_keys($corr);
		$data_values = array();
		$group_json = array();
		foreach ($samples as $sample) {
			$data_row = array();
			foreach ($levels as $level) {
				$data_row[] = $raw_data[$sample][$level];
			}
			$data_values[] = $data_row;
			$group_json[] = $groups[$sample];
		} 

		$header = 150;
		$max_x_label_len = max(array_map('strlen', $samples));
		$max_y_label_len = max(array_map('strlen', $levels));
		$width = $header * 2 + count(array_unique($samples)) * 20 + $max_y_label_len * 4;
		$height = $header * 2 + count(array_unique($levels)) * 20 + $max_x_label_len * 10;
		$plot_json = array("z" => array('Group'=> $group_json), "x"=>array('Correlation'=>array_values($corr)), "y"=>array('vars'=>$samples, 'smps'=>$levels, 'data'=>$data_values), "m"=>array("Name"=>'Transcript level expression'));
		return array("data"=>$plot_json, "width"=>$width, "height"=>$height);
	}



	public function getTwoGenesDotplotData($sid, $g1, $g2, $data_type) {
		$study = Study::getStudy($sid, $data_type);
		list($exprs, $samples) = $study->getTwoGenesExp($g1, $g2);
		
		$data = array();
		$tissue_type = array();
		$sample_ids = array_keys($samples);
		$exp1 = array();
		$exp2 = array();
		foreach ($sample_ids as $sample_id) {
			$data[] = array($exprs[$sample_id][$g1], $exprs[$sample_id][$g2]);
			$exp1[] = $exprs[$sample_id][$g1];
			$exp2[] = $exprs[$sample_id][$g2];
			$tissue_type[] = $samples[$sample_id];
		}
		//calculate the p-value
		$exp1_list = implode(',', $exp1);
		$exp2_list = implode(',', $exp2);
		$cmd = "Rscript ".app_path()."/scripts/corr_test.r $exp1_list $exp2_list";
		//return $exp1_list."<BR><BR>".$exp2_list;
		$ret = shell_exec($cmd);		
		$fields = preg_split('/\s+/', $ret);
		$json = array("data"=>array("y"=>array("smps"=>[$g1,$g2], "vars"=> $sample_ids, "data" => $data), "z"=> array("Tissue" => $tissue_type)), "p_two"=>$fields[0], "p_great"=>$fields[1], "p_less"=>$fields[2]);
		
		return json_encode($json);
   	}


	
	#convert array of hash to array of array (table)
	function hash2table($input) {
		$header_idx = [];
		$headers = [];
		$idx = 0;  
		foreach ($input as $row) {
			foreach ($row as $key => $value) {
				if (!isset($header_idx[$key])) {
					$headers[$idx] = $key;
					$header_idx[$key] = $idx++;
				}
			}
		}
  
		for ($i=0;$i<count($input);$i++) {
			for ($j=0;$j<count($header_idx);$j++) {
				$output[$i][$j] = "";
			}
		}

		$i=0;
		foreach ($input as $row) {
			foreach ($row as $key => $value) {
				$j = $header_idx[$key];
				$output[$i][$j] = $value;               
			}
			$i++;  
		} 
		return array($headers, $output);
	}

	public function get_json_columns ($input) {

		$j_col ='[';
		foreach($input as $header) {
			$j_col .= '{"title":"'.$header.'"},';
		}
		$j_col .=']';
		return $j_col;
	}
   
	function array2jsontable($input) {
		$output ='[';
		foreach ($input as $row) {
			$output .='[';
			foreach ($row as $value) {
				$output .='["'.$value.'"],';
			}
			$output .='],';   
		}
		$output .=']';
		return $output;
	}

}
