<?php

namespace App\Http\Controllers;
use App\Models\VarAnnotation;
use Config,View,Log,Response,DB,Redirect;
use App\Models\Project;
use App\Models\User;
use App\Models\PCA;
use App\Models\Gene;
use App\Models\UserSetting;
use App\Models\UserGeneList;
use App\Models\VarQC;
use App\Models\Oncotree;

putenv("R_LIBS=".Config::get("site.R_LIBS"));
putenv("PATH=".Config::get("site.R_PATH"));
putenv("LD_LIBRARY_PATH=".Config::get("site.LD_LIBRARY_PATH"));

class ProjectController extends BaseController {

	public function viewProjects() {
		return View::make('pages/viewProjects'); 		
	}

	public function viewProjectDetails($project_id) {
		$project = null;
		if (is_numeric($project_id))
			$project = Project::getProject($project_id);
		/*
		if ($project == null) {
			$project = Project::getProjectByName($project_id);
			if ($project == null)
				return View::make('pages/error', ['message' => "Project $project_id not found!"]);
			$project_id = $project->id;
		}
		*/
		$project_info = Project::getProjectInfo($project_id);
		if ($project_info == null)
			return View::make('pages/error', ['message' => "Project $project_id not found!"]);
		$ret = $this->saveAccessLog($project_id, $project_id, "project");
		$survival_diags = $project->getSurvivalDiagnosis();
		Log::info("Survival diagnosis: ".json_encode($survival_diags));
		$has_survival = count($survival_diags);
		$tier1_genes = array();
		$survival_meta_list = null;
		$has_survival_pvalues = false;
		$fusion_genes = array();
		if ($has_survival) {
			$tier1_genes = Project::getMutationGeneList($project_id);
			$fusion_genes = Project::getFusionGenesList($project_id);
			$survival_meta_list = $project->getProperty("survival_meta_list");
			$overall_files = $project->getSurvivalPvalueFile("overall");
			$event_free_files = $project->getSurvivalPvalueFile("event_free");
			$has_survival_pvalues = (count($overall_files) > 1 || count($event_free_files) > 1);

		}
		$cnv_files = array();
		$has_cnv_summary=false;
		if (file_exists(storage_path()."/project_data/$project_id/cnv/$project_id.sequenza.summary.tsv")) {
			$cnv_files["Sequenza Summary"] = "sequenza.summary.tsv";
			$has_cnv_summary=true;
		}
		$has_tcell_extrect_data = $project->hasTCellExTRECT();
		
		if (file_exists(storage_path()."/project_data/$project_id/cnv/$project_id.sequenza.zip"))
			$cnv_files["Sequenza Files (zipped)"] = "sequenza.zip";
		if (file_exists(storage_path()."/project_data/$project_id/cnv/$project_id.sequenza.matrix.tsv"))
			$cnv_files["Sequenza Matrix File (CN)"] = "sequenza.matrix.tsv";
		if (file_exists(storage_path()."/project_data/$project_id/cnv/$project_id.cnvkit.matrix.tsv"))
			$cnv_files["CNVkit Matrix File (log2)"] = "cnvkit.matrix.tsv";		
		Log::info("saving log. Results: ".json_encode($ret));
		$additional_tabs = $project->getAdditionalTabs();
		$additional_links = $project->getAdditionalLinks();
		$gsva_files = array();
		$gsva_dir = storage_path()."/project_data/$project_id/gsva";
		if (is_dir($gsva_dir))
			$gsva_files = scandir($gsva_dir);
		$genesets = array();
		$methods = array();
		$nsmps = 0;
		foreach ($gsva_files as $gsva_file) {
			$tokens = explode(".", $gsva_file);
			if (count($tokens) > 2) {
				$cmd = "/usr/bin/awk -F'\\t' '{print NF;exit}' $gsva_dir/$gsva_file";
				$ret = shell_exec($cmd);
				$ret = (int)trim($ret);
				if ($ret > $nsmps)
					$nsmps = $ret;
				$method = $tokens[count($tokens)-2];
				if ($method != "") {
					$methods[$method] = "";
					$genesets[str_replace(".".$method.".txt", "", $gsva_file)] = "";
				}
			}
		}
		Log::info("GSVA has $nsmps samples");	
		
		return View::make('pages/viewProjectDetails', ['project' =>$project, 'has_survival'=>$has_survival, 'has_survival_pvalues' => $has_survival_pvalues, 'has_cnv_summary' => $has_cnv_summary, 'cnv_files' =>$cnv_files, 'survival_diags' => json_encode($survival_diags), 'tier1_genes' => $tier1_genes, 'fusion_genes' => $fusion_genes, 'survival_meta_list' => json_encode($survival_meta_list), 'has_tcell_extrect_data' => $has_tcell_extrect_data, 'project_info'=>$project_info, 'additional_links' => $additional_links, 'additional_tabs' => $additional_tabs, 'genesets' => array_keys($genesets), 'gsva_methods' => array_keys($methods), 'gsva_nsmps' => $nsmps]);
		
	} 

	public function getProjectsByPost()	{				
		$projects = Project::All();
		return json_encode($projects);

	}
	public function getProjects() {
		$projects = Project::getAll();
		foreach ($projects as $project) {
			$project->name = "<a target=_blank href=".url("/viewProjectDetails/".$project->id).">".$project->name."</a>";
			$project->ispublic = ($project->ispublic == "1")? "Y" : "";
			$project->ispublic = $this->formatLabel($project->ispublic);
			$project->patients = $this->formatLabel($project->patients);
			$project->cases = $this->formatLabel($project->cases);
			$project->samples = $this->formatLabel($project->samples);
			$project->version = $this->formatLabel("hg".$project->version);
			$project->processed_patients = $this->formatLabel($project->processed_patients);
			$project->processed_cases = $this->formatLabel($project->processed_cases);
			$project->survival = $this->formatLabel($project->survival);
			$project->exome = $this->formatLabel($project->exome);
			$project->panel = $this->formatLabel($project->panel);
			$project->rnaseq = $this->formatLabel($project->rnaseq);
			$project->whole_genome = $this->formatLabel($project->whole_genome);
			if ($project->created_by == "" || $project->created_by == "admin@admin.com")
				$project->created_by = "System";
			#$project->status = ($project->status == 1)? "<font color='red'>Processing</font>" : "Ready";
			$project->status = "Ready";
			$user = User::getCurrentUser();
			$project->{'action'} = '';
			if ($user != null) {
				if ($user->id == $project->user_id) {
					$project->action = '<a target=_blank href="'.url("/viewEditProject/$project->id").'" class="btn btn-success btn-sm" ><span class="glyphicon glyphicon-edit"></span><span id="addText">&nbsp;Edit</span></a>&nbsp;';
					$project->action .=  '<a target=_blank href="javascript:deleteProject('.$project->id.')" class="btn btn-warning btn-sm" ><span class="glyphicon glyphicon-trash"></span><span id="addText">&nbsp;Delete</span></a>';
				}
			}
		}
		$tbl_results = $this->getDataTableJson($projects, Config::get('onco.project_column_exclude'));
		return json_encode($tbl_results);
	}
	
	public function getPatientMetaData($pid, $format="json", $includeOnlyRNAseq='N', $include_diagnosis='Y', $include_numeric='Y', $meta_list_only='Y') {		
		$project = Project::getProject($pid);
		$meta_list = null;
		if ($meta_list_only == "Y") {
			$meta_list = $project->getProperty("survival_meta_list");
		}
		$patient_meta = $project->getPatientMetaData(($include_diagnosis=='Y'), ($includeOnlyRNAseq=='Y'), ($include_numeric=='Y'), $meta_list);		
		if ($format == 'json')
			return json_encode($patient_meta);
		if ($format == 'table') {
			$out_string = "PatientID\t".implode("\t", $patient_meta["attr_list"])."\n";
			foreach ( $patient_meta["data"] as $patient_id => $values) {
				$out_string = $out_string.$patient_id."\t".implode("\t", $values)."\n";

			}
			$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='.$project->name."_meta_data.txt");
			return Response::make($out_string, 200, $headers);
		}
		return "format unknown";
	}

	public function getProjectSummary($project_id) {
		$project = Project::getProject($project_id);
		$patient_meta = $project->getPatientMetaData();
		$fusion_table = $project->getProperty("var_fusion_table");	
		if ($fusion_table == null)	
			$fusion_table = "var_fusion";
		$tier1_fusions = Project::getFusionProjectDetail($project_id, "var_level", "1.1", true, $fusion_table);
		$fusions = array();
		foreach ($tier1_fusions as $tier1_fusion) {
			$fusions[] = array("genes" => $tier1_fusion->left_gene."-".$tier1_fusion->right_gene, "count" => $tier1_fusion->cnt, "patient_list" => explode(",",$tier1_fusion->patient_list));
		}
		
		usort($fusions, "\App\Http\Controllers\ProjectController::sortByCount");
		//$fusion_json = array();
		//foreach ($fusions as $key => $value)
		//	$fusion_json[] = array($key, $value);		

		return json_encode(array("fusion" => $fusions, "patient_meta" => $patient_meta));
	}

	static public function sortByCount($a, $b) {
		$cnt1 = (int)$a["count"];
		$cnt2 = (int)$b["count"];
		if ($cnt1 == $cnt2)
			return 0;
		return ($cnt1 > $cnt2)? -1:1;
	}

	public function viewPatient($project_id) {
		$projects = User::getCurrentUserProjects();
		if (count($projects) == 0) {
			return View::make('pages/error', ["message" => "No project information found!"]);
		}
		return View::make('pages/viewProjectPatient', ["message" => "No project information found!"]);
	}

	public function getProject($project_id) {
		$project = Project::getProject($project_id);
		return json_encode($project);
	}

	public function getUserList($project_id) {
		$project = Project::getProject($project_id);
		$user_list = $project->getUserList();
		foreach ($user_list as $user) {
			$permission_obj = json_decode($user->roles);
			if (isset($permission_obj)) {
				$permissions = array_keys((array)$permission_obj);
				$permission_arr = array();
				foreach ($permissions as $permission) {
					$permission = ucfirst(str_replace("_", "", $permission));
					$permission_arr[] = $permission;
				}
				$user->roles = implode(",", $permission_arr);
			}
			else
				$user->roles = "Regular user";
		}
		return $this->getDataTableJson($user_list);
	}

	public function getPatientProjects($patient_id) {
		$projects = Patient::getProjects($patient_id);
		return json_encode($projects);
	}

	public function getCNVSummary($project_id) {
		$summary_file = storage_path()."/project_data/$project_id/cnv/$project_id.sequenza.summary.tsv";
		$content = file_get_contents($summary_file);
		$lines = explode("\n", $content);
		$cols = null;
		$data = array();
		$url = url("/viewPatient");
		foreach ($lines as $line) {
			if ($line == "")
				continue;
			if ($cols == null) {
				$cols = array();
				$col_arr = explode("\t", $line);
				foreach ($col_arr as $col)
					$cols[] = array("title" => $col);
			} else {
				$row_data = explode("\t", $line);
				$row_data[0] = "<a href = '$url/$project_id/".$row_data[0]."'>$row_data[0]</a>";
				$data[] = $row_data; 
			}
		}		
		return array("cols" => $cols, "data" => $data);	
	}

	public function viewExpression($project_id, $patient_id="null", $case_id="null", $meta_type="null", $setting="null") {
		$attr_name = "page.expression";
		if ($setting == "null")
			$setting = UserSetting::getSetting($attr_name);
		else {
			$setting = json_decode($setting);
			UserSetting::saveSetting($attr_name, $setting);
		}		
		$project = Project::getProject($project_id);
		$target_types = $project->getTargetTypes();

		if (!property_exists($setting, 'norm_type'))
			$setting->norm_type = 'tmm-rpkm';
		if (!property_exists($setting, 'target_type'))
			$setting->target_type = 'ensembl';

		return View::make('pages/viewExpression',['project_id' => $project_id, 'patient_id' => $patient_id, 'case_id' => $case_id, 'setting' => $setting, 'gene_id' => '', 'meta_type' => $meta_type, 'target_types' => $target_types]);
	}

	public function viewExpressionByGene($project_id, $gene_id) {
		$attr_name = "page.expression";
		$setting = UserSetting::getSetting($attr_name);		
		$setting->gene_list = $gene_id;
		UserSetting::saveSetting($attr_name, $setting);
		return $this->viewExpression($project_id);
		#$project = Project::getProject($project_id);
		#$target_type = $project->getTargetType();
		#return View::make('pages/viewExpression',['project_id' => $project_id, 'patient_id' => 'null', 'case_id' => 'null', 'meta_type' => 'null', 'setting' => $setting, 'gene_id' => $gene_id]);
	}

	public function getExpression($project_id, $gene_list, $target_type = 'all', $library_type = 'all') {
		if ($project_id == "all" || $project_id == "any")
			return json_encode(Gene::getExpression($gene_list, $target_type, $library_type));
		$gs = explode(' ', $gene_list);
		$genes = array();
		foreach ($gs as $g) {
			if (rtrim($g) != '')
				$genes[] = $g;
		}
		$project = Project::getProject($project_id);
		$project_data = $project->getGeneExpression($genes, $target_type, $library_type, 'gene', false);
		return json_encode($project_data);
	}

	public function getCNV($project_id, $gene_list) {
		$gs = explode(' ', $gene_list);
		$genes = array();
		foreach ($gs as $g) {
			if (rtrim($g) != '')
				$genes[] = $g;
		}
		$project = Project::getProject($project_id);
		$project_data = $project->getCNV($genes);
		return json_encode($project_data);
	}

	public function getExpressionByGeneList($project_id, $patient_id, $case_id, $gene_list, $target_type = 'all', $library_type = 'all', $value_type="tmm-rpkm") {
		if ($target_type == 'null')
			$target_type = "ensembl";
		$gs = explode(' ', $gene_list);
		$genes = array();
		foreach ($gs as $g) {
			if (rtrim($g) != '')
				$genes[] = $g;
		}
		$hight_light_samples = array();
		if ($patient_id != "null") {
			$samples = Patient::where('patient_id', '=', $patient_id)->get()[0]->samples;
			foreach ($samples as $sample) {
				if ($sample->exp_type == "RNAseq") {
					if ($case_id != "null" && $sample->case_id = $case_id)
						$hight_light_samples[] = $sample->sample_name;
				}
			}
		}

		$project = Project::getProject($project_id);
		$gene_meta = Gene::getSurfaceInfo($genes);
		$tumor_project_data = $project->getGeneExpression($genes, $target_type, $library_type, 'gene', true, 'all', $value_type);
		//$tumor_project_data['patient_meta'] = $project->getPatientMetaData();
		$normal_project = Project::getNormalProject();
		$normal_project_data = null;
		if ($normal_project != null)		
			$normal_project_data = $normal_project->getGeneExpression($genes, $target_type, $library_type, 'gene', true, 'normal', $value_type);
		//$normal_project_data['patient_meta'] = $normal_project->getPatientMetaData();
		return json_encode(array("hight_light_samples" => $hight_light_samples, "tumor_project_data"=> $tumor_project_data, "normal_project_data" => $normal_project_data, "gene_meta" => $gene_meta));		
	}

	public function getExpressionByLocus($project_id, $patient_id, $case_id, $chr, $start_pos, $end_pos, $target_type, $library_type) {		
		$genes = Gene::getGeneListByLocus($chr, $start_pos, $end_pos, $target_type);
		$gene_list = implode(' ', $genes);
		return $this->getExpressionByGeneList($project_id, $patient_id, $case_id, $gene_list, $target_type, $library_type);		
	}

	public function getPCAData($project_id, $target_type = "ensembl", $value_type="all") {
		ini_set('memory_limit', '1024M');
		$project = Project::getProject($project_id);
		$value_type = ($value_type == "zscore")? ".zscore" : "";
		$loading_file = storage_path()."/project_data/$project_id/pca-loading$value_type.tsv";
		$coord_file = storage_path()."/project_data/$project_id/pca-coord$value_type.tsv";
		$std_file = storage_path()."/project_data/$project_id/pca-std$value_type.tsv";		
		$groups = [];
		Log::info($loading_file);
		if (!file_exists($loading_file)) {
			return json_encode(array("status"=>"no data"));
		}
		$sample_meta = $project->getSampleMetaData("RNAseq", "sample_id", "all" ,"all", true);
		//return json_encode($sample_meta);
		$pca_json = $this->getPCAPlotjson($loading_file, $coord_file, $std_file, $sample_meta);
		$pca_json["status"] = "ok";
		return json_encode($pca_json);
	}

	public function getPCAPlotjson($loading_file, $coord_file, $std_file, $sample_meta_old) {
		//replace '-' to '.' because R will change sample name this waystorage_path
		$sample_meta = array();	
		$patients = $sample_meta_old["patients"];
		foreach ($sample_meta_old["data"] as $sample => $attrs) {
			#$sample = str_replace("-", ".", $sample);
			$sample_meta["data"][$sample] = $attrs;
		}
		$sample_meta["attr_list"] = $sample_meta_old["attr_list"];
		$pca = new PCA($loading_file, $coord_file, $std_file);
		list($loadings, $coord, $std) = $pca->getPCAResult();		
		$samples = array_keys($coord);
		$genes = array_keys($loadings);
		$var_sum = 0;
		$variances = array();
		$variance_prop = array();
		$num_pc = 20;
		foreach ($std as $pc=>$std_value) {
			$var = $std_value[0] * $std_value[0];
			$var_sum = $var_sum + $var;
			$variances[] = $var;
		}
		$gene_infos = Gene::getGenesInfo();
		$variances = array_splice($variances, 0, $num_pc);
		$pca_seq = array();
		$i = 1;
		for ($i=0;$i<count($variances);$i++) {
			$variance_prop[] = round($variances[$i] / $var_sum * 100, 1);
			$pca_seq[] = $i+1;
		}
		$loading = array();
		foreach ($loadings as $key=>$values) {
			if (array_key_exists($key, $gene_infos)) {
				$gene_info = $gene_infos[$key];
				$key = $gene_info->symbol;
			}
			for ($i=0;$i<count($values);$i++)
				$loading[$i][$key] = round($values[$i],4);
		}
		$top_ploading = array();
		$top_nloading = array();		
		for ($i=0;$i<count($loading);$i++) {
			arsort($loading[$i]);
			$ploading = array_splice($loading[$i], 0, $num_pc);
			asort($loading[$i]);
			$nloading = array_splice($loading[$i], 0, $num_pc);
			$top_ploading["PC".($i+1)] = array(array_keys($ploading), array_values($ploading));
			$top_nloading["PC".($i+1)] = array(array_keys($nloading), array_values($nloading));
		}
		$pca_data = array('sample_meta' => $sample_meta, 'samples'=>$samples, 'patients'=>$patients, 'data'=>$coord, 'variance_prop' => array($variance_prop[0], $variance_prop[1], $variance_prop[2]), 'pca_variance'=>$variances, 'pca_loading'=>array("p"=>$top_ploading, "n"=>$top_nloading));		
		return $pca_data;

	}

	public function viewSurvivalByExpression($project_id, $symbol, $show_search="N", $include_header="N", $type="overall", $selected_diagnosis="any") {
		$gene = Gene::getGene($symbol);
		if ($gene != null) {
			$symbol = $gene->getSymbol();
			$ret = $this->saveAccessLog($symbol, $project_id, "gene");
		}
		$project = Project::getProject($project_id);
		$survival_diags = $project->getSurvivalDiagnosis();	
		$view_name = "viewSurvivalByExpression";
		if ($include_header == "Y")
			$view_name = "viewSurvivalByExpressionHeader";

		return View::make("pages/$view_name",['project' => $project, 'symbol'=>$symbol, 'survival_diagnosis' => $survival_diags, 'show_search' => $show_search, 'include_header' => $include_header, 'type'=>$type, 'selected_diagnosis' => $selected_diagnosis]);	
	}

	public function viewTIL($project_id) {		
		return View::make('pages/viewTIL',['project_id' => $project_id]);
	}

	public function getTIL($project_id) {
		$project = Project::getProject($project_id);		
		return json_encode($this->getDataTableJson($project->getTCellExTRECT()));
	}


	public function getMutationGenes($project_id, $type="germline", $meta_type = "any", $meta_value="any", $maf=1, $min_total_cov=0, $vaf=0) {

		ini_set('memory_limit', '1024M');
		$time_start = microtime(true);
		$total_patients = Project::totalPatients($project_id);
		//$rows = DB::table('var_gene_tier')->where('project_id', $project_id)->where('type',$type)->get();
		$project = Project::find($project_id);
		$time = microtime(true) - $time_start;
		Log::info("execution time (totalPatients): $time seconds");
		$time_start = microtime(true);

		$annotation = (VarAnnotation::is_avia())? "AVIA" : "Khanlab";

		$tier_table = $project->getProperty("var_tier_table");
		if ($tier_table == null)
			$rows = $project->getVarGeneTier($type, $meta_type, $meta_value, $annotation, $maf, $min_total_cov, $vaf);
		else
			$rows = $project->getVarGeneTier($type, $meta_type, $meta_value, $annotation, $maf, $min_total_cov, $vaf, $tier_table);

		$time = microtime(true) - $time_start;
		Log::info("execution time (getVarGeneTier): $time seconds");
		$time_start = microtime(true);
		$germline_levels = array();
		$somatic_levels = array();
		$tiers = array("Tier 1", "Tier 2", "Tier 3", "Tier 4", "No Tier");
		//$tiers = array("Tier 1");
		foreach ($rows as $row) {
			$germline_level = "";
			if ($row->tier_type == "germline") {
				$germline_level = substr($row->tier, 0, 6);
				if (isset($germline_levels[$row->gene][$germline_level]))
					$germline_levels[$row->gene][$germline_level] += $row->cnt;
				else
					$germline_levels[$row->gene][$germline_level] = $row->cnt;
			}
			$somatic_level = "";			
			if ($row->tier_type == "somatic"){
				$somatic_level = substr($row->tier, 0, 6);
				//Log::info($somatic_level);
				if (isset($somatic_level, $somatic_levels[$row->gene][$somatic_level]))
					$somatic_levels[$row->gene][$somatic_level] += $row->cnt;
				else
					$somatic_levels[$row->gene][$somatic_level] = $row->cnt;
			}
		}
		$user_filter_list = UserGeneList::getGeneList($type);


		//return json_encode($germline_levels);
		$cols = array();
		$data = array();
		if ($type == "rnaseq" || $type == "variants")
			$cols = array(array("title" => "Gene"), array("title" => 'Germline - Tier 1'), array("title" => 'Germline - Tier 2'), array("title" => 'Germline - Tier 3'), array("title" => 'Germline - Tier 4'), array("title" => 'Germline - No Tier'), array("title" => 'Somatic - Tier 1'), array("title" => 'Somatic - Tier 2'), array("title" => 'Somatic - Tier 3'), array("title" => 'Somatic - Tier 4'), array("title" => 'Somatic - No Tier'));
		else
			$cols = array(array("title" => "Gene"), array("title" => 'Tier 1'), array("title" => 'Tier 2'), array("title" => 'Tier 3'), array("title" => 'Tier 4'), array("title" => 'No Tier'));
		foreach ($user_filter_list as $list_name => $gene_list)
			$cols[] = array("title" => ucfirst(str_replace("_", " ", $list_name)));

		$root_url = url("/");
		
		$levels = ($type == "somatic")? $somatic_levels : $germline_levels;
		$no_fp = ($type == "rnaseq")? "true" : "false";
		$param_str = "/$meta_type/$meta_value/null/true/$maf/$min_total_cov/$vaf";

		foreach ($levels as $gene => $tier_data) {
			$row_value = array();
			$url = "$root_url/viewProjectGeneDetail/$project_id/$gene/0";
			$row_value[] = "<a target=_blank href='$url'>$gene</a>";
			if ($type != "somatic") {
				foreach ($tiers as $tier) {					
					$value = isset($germline_levels[$gene][$tier])? $germline_levels[$gene][$tier] : 0;
					//$value = 0;
					$hint = "$value out of $total_patients patients have $tier mutations in gene ".$gene;
					$tier_str = strtolower(str_replace(" ", "", $tier));
					$tier_str = ($tier_str == "notier")? "no_tier" : $tier_str;
					//$row_value[] = "<a target=blank_ href='".url("/viewVarAnnotationByGene/$project_id/$gene/$type/1/germline/$tier_str")."'><span class='mytooltip' title='$hint'>".$this->formatLabel($value )."</span></a>";
					
					$row_value[] = "<a target=blank_ href='$root_url/viewVarAnnotationByGene/$project_id/$gene/$type/1/germline/$tier_str$param_str'><span class='mytooltip' title='$hint'>".$this->formatLabel($value )."</span></a>";

				}
			}
			if ($type != "germline") {
				foreach ($tiers as $tier) {
					$value = isset($somatic_levels[$gene][$tier])? $somatic_levels[$gene][$tier] : 0;
					$hint = "$value out of $total_patients patients have $tier mutations in gene ".$gene;
					$tier_str = strtolower(str_replace(" ", "", $tier));
					$tier_str = ($tier_str == "notier")? "no_tier" : $tier_str;
					$row_value[] = "<a target=blank_ href='$root_url/viewVarAnnotationByGene/$project_id/$gene/$type/1/somatic/$tier_str$param_str'><span class='mytooltip' title='$hint'>".$this->formatLabel($value )."</span></a>";
					//$row_value[] = "<span class='mytooltip' title='$hint'>".$this->formatLabel($value )."</span>";
				}
			}
			//user defined filters
			foreach ($user_filter_list as $list_name => $gene_list) {
				$has_gene = isset($gene_list[$gene])? $this->formatLabel("Y"):"";
				$row_value[] = $has_gene;
			}			
			$data[] = $row_value;
		}

		$time = microtime(true) - $time_start;
		Log::info("execution time (getMutationGenes): $time seconds");

		return json_encode(array('cols' => $cols, 'data' => $data));
	}

	public function getFusionProjectDetail($project_id, $diagnosis=null, $cutoff=null) {
		ini_set('memory_limit', '1024M');
		set_time_limit(240);
		$project = Project::find($project_id);
		if ($cutoff == null)
			$cutoff = Config::Get('onco.minPatients');
		$total_patients = Project::totalPatients($project_id);
		$time_start = microtime(true);
		$fusion_table = $project->getProperty("var_fusion_table");
		if ($fusion_table == null)
			$fusion_table = "var_fusion";
		else
			$cutoff = 0;
		$root_url = url("/");
		$user_filter_list = UserGeneList::getGeneList("fusion", "all", false);
		$rows = Project::getFusionProjectDetailByDiagnosis($project_id, $fusion_table, $diagnosis, $cutoff);
		$cols = array(array("title" => "Left chr"), array("title" => "Left gene"), array("title" => "Right chr"), array("title" => "Right gene"), array("title" => "Tier"), array("title" => "Patient Count"));
		$data = [];
		//foreach ($user_filter_list as $list_name => $gene_list)
		//	$cols[] = array("title" => ucfirst(str_replace("_", " ", $list_name)));
		foreach ($rows as $row) {
			$count = "<a target=_blank href='$root_url/viewFusionGenes/$project_id/$row->left_gene/$row->right_gene/tier/tier$row->var_level/$diagnosis' class='mytooltip'>".$this->formatLabel($row->count)."</a>";
			$row_value = [$row->left_chr,$row->left_gene,$row->right_chr,$row->right_gene,$row->var_level,$count];
			/*
			foreach ($user_filter_list as $list_name => $gene_list) {
				$has_gene = (isset($gene_list[$row->left_gene]) || isset($gene_list[$row->right_gene]))? $this->formatLabel("Y"):"";
				$row_value[] = $has_gene;
			}
			*/
			$data[] = $row_value;

		}
		$time = microtime(true) - $time_start;
		Log::info("execution time (getFusionProjectDetailByDiagnosis)): $time seconds");
		return json_encode(array('gene_list'=>$user_filter_list, 'cols' => $cols, 'data' => $data)); 


		return $this->getDataTableJson($rows);
		$time = microtime(true) - $time_start;
		Log::info("execution time (getFusionProjectDetailByDiagnosis)): $time seconds");		
		$fusion_counts = array();
		$tiers = array("Tier 1", "Tier 2", "Tier 3", "Tier 4");
		$types = array("in-frame", "right gene intact", "out-of-frame", "no protein");
		
		$patients = array();
		foreach ($rows as $row) {
			$key = "$row->left_chr:$row->left_gene:$row->right_chr:$row->right_gene";
			$level = "Tier ".substr($row->var_level, 0, 1);
			if (! array_key_exists($key, $fusion_counts)) {
				$fusion_counts[$key] = [];
				$fusion_counts[$key]["count"] = [];				
			}
			if (! array_key_exists($level, $fusion_counts[$key]))
				$fusion_counts[$key][$level] = 1;
			else
				$fusion_counts[$key][$level]++;
			if (! array_key_exists($row->type, $fusion_counts[$key]))
				$fusion_counts[$key][$row->type] = 1;
			else
				$fusion_counts[$key][$row->type]++;			
			$fusion_counts[$key]["count"][$row->patient_id] = '';
			$patients[$row->patient_id] = '';
		}

		$user_filter_list = UserGeneList::getGeneList("fusion");		
				
		$data = array();
		$cols = array(array("title" => "Left chr"), array("title" => "Left gene"), array("title" => "Right chr"), array("title" => "Right gene"), array("title" => "Patients"));

		foreach ($tiers as $tier)
			$cols[] = array("title" => $tier);
		foreach ($types as $type)
			$cols[] = array("title" => ucfirst($type));
		foreach ($user_filter_list as $list_name => $gene_list)
			$cols[] = array("title" => ucfirst(str_replace("_", " ", $list_name)));
		$total_patients = count($patients);

		
		foreach ($fusion_counts as $key => $count_data) {
			$total_count = count($count_data["count"]);
			if ($total_count < $cutoff)
				continue; 
			$row_value = array();
			list($left_chr, $left_gene, $right_chr, $right_gene) = explode(":", $key);
			$left_url = "$root_url/viewProjectGeneDetail/$project_id/$left_gene/0";
			$right_url = "$root_url/viewProjectGeneDetail/$project_id/$right_gene/0";
			$row_value[] = $left_chr;
			$row_value[] = $left_gene;
			#$row_value[] = "<a target=_blank href='$left_url'>$left_gene</a>";
			$row_value[] = $right_chr;
			$row_value[] = $right_gene;
			#$row_value[] = "<a target=_blank href='$right_url'>$right_gene</a>";
			$hint = "$total_count out of $total_patients patients have fusion event(s) in $left_gene and $right_gene";
			$row_value[] = "<a target=_blank href='$root_url/viewFusionGenes/$project_id/$left_gene/$right_gene/null/null/$diagnosis' class='mytooltip' title='$hint'>".$this->formatLabel($total_count)."</a>";
			foreach ($tiers as $tier) {				
				$value = isset($count_data[$tier])? $count_data[$tier] : 0;
				$hint = "$value $tier fusion event(s) in $left_gene and $right_gene";
				$tier_str = strtolower(str_replace(" ", "", $tier));
				$tier_str = ($tier_str == "notier")? "no_tier" : $tier_str;
				$row_value[] = $value;
				#$row_value[] = "<a target=_blank href='$root_url/viewFusionGenes/$project_id/$left_gene/$right_gene/tier/$tier_str/$diagnosis' class='mytooltip' title='$hint'>".$this->formatLabel($value)."</a>";
			}
			
			foreach ($types as $type) {
				$value = isset($count_data[$type])? $count_data[$type] : 0;
				$hint = "$value $type fusion event(s) in $left_gene and $right_gene";
				$row_value[] = $value;
				#$row_value[] = "<a target=_blank href='$root_url/viewFusionGenes/$project_id/$left_gene/$right_gene/type/$type/$diagnosis' class='mytooltip' title='$hint'>".$this->formatLabel($value)."</a>";
			}
			//user defined filters
			
			foreach ($user_filter_list as $list_name => $gene_list) {
				$has_gene = (isset($gene_list[$left_gene]) || isset($gene_list[$right_gene]))? $this->formatLabel("Y"):"";
				$row_value[] = $has_gene;
			}
			
			$data[] = $row_value;
		}
		$time = microtime(true) - $time_start;		
		Log::info("execution time (getFusionProjectDetail()): $time seconds");
		
		return json_encode(array('cols' => $cols, 'data' => $data));
	}

	

	public function viewFusionGenes($project_id, $left_gene, $right_gene = "null", $type = "null", $value = "null", $diagnosis="null") {
		$filter_definition = array();
		$filter_lists = UserGeneList::getDescriptions('fusion');
		foreach ($filter_lists as $list_name => $desc) {
			$filter_definition[$list_name] = $desc;
		}
		
        $setting = UserSetting::getSetting("page.fusion_all");
        
        $setting->filters = "[]";
			
        if ($type == "tier") {
        	$setting->tier1 = "false";
			$setting->tier2 = "false";
			$setting->tier3 = "false";
			$setting->tier4 = "false";
			$setting->no_tier = "false";
			if ($type == "tier")			
				$setting->{$value} = "true";
		}
        else
        	$setting->{$type} = $value;
        
		$url = url("/getFusionGenes/$project_id/$left_gene");
		$view = 'pages/viewFusion';
		if ($right_gene != "null") {
			$url .= "/$right_gene";
			$view = 'pages/viewFusionHeader';
		}

		return View::make($view, ['title' => 'Fusion', 'url' => $url, 'project_id' => $project_id, 'patient_id' => 'null', 'case_name' => 'any', 'filter_definition' => $filter_definition, 'setting' => $setting, 'has_qci' => false, 'diagnosis' => $diagnosis]);
	}

	public function getFusionGenes($project_id, $left_gene, $right_gene = null, $type = null, $value = null) {
		$rows = Project::getFusionGenes($project_id, $left_gene, $right_gene, $type, $value);
		$root_url = url("/");
		foreach ($rows as $row) {
			//$row->patient_id = "<a target=_blank href='$root_url/viewFusion/$project_id/$row->patient_id/$row->case_id/1'>$row->patient_id</a>";
			$row->igv = "<a target=_blank href='".$root_url."/viewFusionIGV/$row->patient_id/$row->sample_id/$row->case_id/$row->left_chr/$row->left_position/$row->right_chr/$row->right_position'><img width=15 hight=15 src='$root_url/images/igv.jpg'/></a>";
			$row->patient_id = "<a target=_blank href='$root_url/viewPatient/$project_id/$row->patient_id'>$row->patient_id</a>";
			if ($row->type != "no-info")
				$row->plot = "<img width=20 height=20 src='".url('images/details_open.png')."'></img>";
			//add fusion gene icons
			if ($row->left_sanger == "Y")
				$row->left_gene = $row->left_gene."<img title='Sanger curated and Mitelman fusion gene' width=15 height=15 src='".url('images/flame.png')."'></img>";
			if ($row->left_cancer_gene == "Y")
				$row->left_gene = $row->left_gene."<img title='Cancer gene' width=15 height=15 src='".url('images/circle_red.png')."'></img>";
			if ($row->right_sanger == "Y")
				$row->right_gene = $row->right_gene."<img title='Sanger curated and Mitelman fusion gene' width=15 height=15 src='".url('images/flame.png')."'></img>";
			if ($row->right_cancer_gene == "Y")
				$row->right_gene = $row->right_gene."<img title='Cancer gene' width=15 height=15 src='".url('images/circle_red.png')."'></img>";
			//tools formatting
			$tools = json_decode($row->tool);
			$tools_str_arr = array();
			foreach ($tools as $tool) {
				foreach ($tool as $key => $value) {
					$tools_str_arr[] = "<font color='red'>$key</font>:<b>$value</b>";
				}
			}			
			$row->tool = implode(", ", $tools_str_arr);
			$row->type = $this->formatLabel($row->type);
			$row->var_level = $this->formatLabel($row->var_level);
			$row->left_region = $this->formatLabel($row->left_region);
			$row->right_region = $this->formatLabel($row->right_region);

		}
		return  $this->getDataTableJson(VarAnnotation::postProcessFusion($rows));
	}

	public function getSurvivalData($project_id, $filter_attr_name1, $filter_attr_value1, $filter_attr_name2, $filter_attr_value2, $group_by1, $group_by2="not_used", $group_by_values=null) {
		$filter_attr_name1 = urldecode($filter_attr_name1);
		$filter_attr_value1 = urldecode($filter_attr_value1);
		$filter_attr_name2 = urldecode($filter_attr_name2);
		$filter_attr_value2 = urldecode($filter_attr_value2);
		$group_by1 = urldecode($group_by1);
		$group_by2 = urldecode($group_by2);	
		if ($group_by_values == "null")
			$group_by_values = null;
		$project = Project::getProject($project_id);
		$data_types =array("overall","event_free");
		$json = array();
		foreach ($data_types as $data_type) {
			$surv_file = $project->getSurvivalFile($data_type, $filter_attr_name1, $filter_attr_value1, $filter_attr_name2, $filter_attr_value2, $group_by1, $group_by2, $group_by_values);
			$surv_content = file_get_contents($surv_file);
			$surv_lines = explode("\n", $surv_content);
			//make patient_surv_time hash so we can get the patient_id from the survival time
			$patient_surv_time = array();
			foreach ($surv_lines as $line) {
				$line = trim($line);
				$fields = preg_split('/\t/', $line);
				$time = $fields[1];
				$status = $fields[2];
				$patient_id = $fields[0];
				//Log::info("patient_id: $patient_id");
				if ($patient_id == "Patient ID")
					continue;
				$patient_surv_time["T$time"][] = array($patient_id, $status);
			}
			$total_patients = count($patient_surv_time);
			Log::info("patient count: $total_patients");
			if ( $total_patients == 0) 
				continue;
			$surv_fit_file = "${surv_file}.out.tsv";
			$surv_summary_file = "${surv_file}.summary.tsv";
			#if (!file_exists($surv_fit_file) || !file_exists($surv_summary_file)) {
				$cmd = "Rscript ".app_path()."/scripts/survival_fit.r $surv_file $surv_fit_file $surv_summary_file";
				Log::info($cmd);
				#$ret = shell_exec($cmd);
				exec($cmd, $exec_out, $ret);
				Log::info($exec_out);
				Log::info($ret);
			#}
			
			//get summary info (e.g pvalue and the number of patients of each strata)
			if (!file_exists($surv_summary_file))
				return "no data";
			$summary_content = file_get_contents($surv_summary_file);
			$summary_lines = explode("\n", $summary_content);
			//make patient_surv_time hash so we can get the patient_id from the survival time
			$patient_count = array();
			$plot_data = array(); //KM series data. initial coordinate is (0,1)
			$pvalue = "NA";

			foreach ($summary_lines as $line) {
				$line = trim($line);
				$fields = preg_split('/\t/', $line);
				if (count($fields) == 2) {
					$key = $fields[0];					
					$value = $fields[1];
					if ($key == "pvalue")
						$pvalue = $value;
					else {
						$patient_count[$key] = $value;
						$plot_data[$key] = array(1);
					}
				}				
			}
			$data = $this->getExpSurvivalFileContent($surv_fit_file, $patient_surv_time, null);
			$json[$data_type] = array("data" => $data, "pvalue" => $pvalue, "patient_count" => $patient_count, "plot_data" => $plot_data);
		}
		return json_encode($json);
	}

	public function getMutationGeneList($project_id, $tier="Tier 1") {
		return json_encode(Project::getMutationGeneList($project_id, $tier));
	}
	
	public function viewSurvivalListByExpression($project_id) {
		$project = Project::getProject($project_id);
		$exp_types = array("overall", "event_free");
		$types = array();
		foreach ($exp_types as $type) {
			foreach (glob(storage_path()."/project_data/$project_id/survival/${type}_pvalues.*.tsv") as $file) {
				preg_match('/pvalues\.(.*)\.tsv/', $file, $m );
				$diagnosis = $m[1];
				$type_label = ucfirst(str_replace("_", " ", $type));
				$types["$type_label - $diagnosis"] = array($type, $diagnosis);				
			}
		}
		return View::make('pages/viewSurvivalListByExpression', ['project_id' => $project_id, 'types' => $types]);
	}

	public function getSurvivalListByExpression($project_id, $type, $diagnosis) {
		set_time_limit(240);
		$time_start = microtime(true);
		$genes = array();
		$exp_types = array("overall", "event_free");
		$types = array();
		# check if event free and overvall survival data exist
		$cols = array(['title'=>'Gene'],["title"=>"Median"],["title"=>"Median Pvalue"],["title"=>"Minimum Cutoff"],["title"=>"Minimum Pvalue"],["title"=>"FDR"]);
		
		$file = storage_path()."/project_data/$project_id/survival/${type}_pvalues.{$diagnosis}.tsv";			
		$content = file_get_contents($file);
		$lines = explode("\n", $content);
		$data = array();
		foreach ($lines as $line) {
			$fields = explode("\t", $line);
			$gene = $fields[0];
			#if empty then it is the title
			if ($fields[0] != "")
				$data[] = $fields;
		}		
		Log::info("getSurvivalListByExpression time: ". (microtime(true)-$time_start));
		return json_encode(array("cols"=>$cols, "data"=>$data));
	}

	public function getExpSurvivalData($project_id, $target_id, $level, $cutoff=null, $target_type="refseq", $data_type="overall", $value_type="tmm-rpkm", $diag="any") {
		if ($cutoff == "null")
			$cutoff = null;
		$project = Project::getProject($project_id);
		$surv_file = $project->getExpSurvivalFile($target_id, $target_type, $level, $data_type, $value_type, $diag);
		if ($surv_file == null)
        	return "no data";
		$surv_content = file_get_contents($surv_file);
		$surv_lines = explode("\n", $surv_content);
		$patient_surv_time = array();
		foreach ($surv_lines as $line) {
			$line = trim($line);
			$fields = preg_split('/\s+/', $line);
			$time = $fields[2];
			$status = $fields[3];
			$patient_id = $fields[1];
			$patient_surv_time["T$time"][] = array($patient_id, $status);
		}
		$pvalue_data = array();
		if ($surv_file != null) {
			if ($cutoff == null) {
				system("mkdir -p ".storage_path()."/project_data/$project_id/survival");

				//$pvalue_file = storage_path()."/project_data/$project_id/survival/survival_pvalue.$target_id.$target_type.$data_type.$value_type.$diag.tsv";
				$pvalue_file = $surv_file.".pvalue.tsv";
				$pvalue_summary_file = $surv_file.".summary.tsv";
				//$pvalue_summary_file = storage_path()."/project_data/$project_id/survival/survival_pvalue.$target_id.$target_type.$data_type.$value_type.$diag.summary.tsv";
				//if (!file_exists($pvalue_file) && !file_exists($pvalue_summary_file)) {
					$cmd = "Rscript ".app_path()."/scripts/survival_pvalues.r $surv_file $pvalue_file $pvalue_summary_file";	
					Log::info("cmd: $cmd");		
					//return $cmd;
					$ret = shell_exec($cmd);
				//}
				if (!file_exists($pvalue_summary_file))
					return "no data";
				$pvalue_summary_content = file_get_contents($pvalue_summary_file);
				if ($pvalue_summary_content == "only one group")
					return $pvalue_summary_content;
				if (!file_exists($pvalue_file))
					return "no data";
				$pvalue_file_content = file_get_contents($pvalue_file);
				
				

				list($median, $median_pvalue, $min_cutoff, $min_pvalue) = preg_split('/\s+/', $pvalue_summary_content);
				//echo "$median, $median_pvalue, $min_cutoff, $min_pvalue<BR>";
				list($median_survival_file, $median_high_num, $median_low_num) = $this->calculateExpSurvival($project_id, $target_id, $level, $median, $target_type, $data_type, $value_type, $diag);
				$user_cutoff = $min_cutoff;
				$user_pvalue = $min_pvalue;
				$pvalue_file_content = file_get_contents($pvalue_file);
				$pvalue_file_lines = explode("\n", $pvalue_file_content);				
				foreach ($pvalue_file_lines as $line) {
					$line = trim($line);
					$fields = preg_split('/\s+/', $line);
					if (count($fields) == 2)
						$pvalue_data[] = array($fields[0], round($fields[1], 3));
				}
				$median_data = $this->getExpSurvivalFileContent($median_survival_file, $patient_surv_time);

			} else {
				$user_cutoff = $cutoff;
			}

			list($user_survival_file, $user_high_num, $user_low_num) = $this->calculateExpSurvival($project_id, $target_id, $level, $user_cutoff, $target_type, $data_type, $value_type, $diag);			
			$user_survival_data = $this->getExpSurvivalFileContent($user_survival_file, $patient_surv_time);

			if ($cutoff == null) 
				$json = array("pvalue_data" => $pvalue_data, "median_data" => array("cutoff" => $median, "high_num" => $median_high_num, "low_num" => $median_low_num, "pvalue" => $median_pvalue, "data" => $median_data), "user_data" => array("cutoff" => $user_cutoff, "high_num" => $user_high_num, "low_num" => $user_low_num, "pvalue" => $user_pvalue, "data" => $user_survival_data));
			else
				$json = array("user_data" => array("cutoff" => $user_cutoff, "high_num" => $user_high_num, "low_num" => $user_low_num, "data" => $user_survival_data));
			return json_encode($json);
		}
	}

	public function getExpSurvivalFileContent($survival_file, $patient_surv_time, $strata_map=array(2 => "Low", 1 => "High")) {
		$file_content = file_get_contents($survival_file);
		//log::info($survival_file);
		//return array();
		$lines = explode("\n", $file_content);
		$data = array();
		foreach ($lines as $line) {
			$line = trim($line);
			$fields = preg_split('/\t/', $line);
			if (count($fields) > 2) {				
				$cat = $fields[2];
				if ($strata_map != null)
					$cat = $strata_map[$fields[2]];
				$events = (int)$fields[3];
				if (array_key_exists("T".$fields[0], $patient_surv_time))
					$patient_surv = $patient_surv_time["T".$fields[0]];
				else {
					Log::info($line);
					continue;
				}
				$data[] = array((int)$fields[0], round($fields[1],3), $cat, $events, $patient_surv);
			}
		}
		return $data;
	}

	public function calculateExpSurvival($project_id, $target_id, $level, $cutoff, $target_type="refseq", $data_type="overall", $value_type="tmm-rpkm", $diag="any") {
		$project = Project::getProject($project_id);
		$surv_file = $project->getExpSurvivalFile($target_id, $target_type, $level, $data_type, $value_type, $diag);
		$text_file = $surv_file."$cutoff.text";
		//$plot_file = storage_path()."/survival/$project_id"."_survival_pvalue$cutoff.$target_id.$target_type.svg";
		//$text_file = storage_path()."/project_data/$project_id/survival/survival_pvalue$cutoff.$target_id.$target_type.$data_type.$diag.tsv";
		$cmd = "Rscript ".app_path()."/scripts/survival_fit_exp.r $surv_file $text_file $cutoff";
		Log::info($cmd);
		$ret = shell_exec($cmd);
		list($high_num, $low_num) = preg_split('/\s+/', $ret);
		return array($text_file, $high_num, $low_num);
	}

	public function viewCorrelation($project_id, $gid) {
		return View::make('pages/geneCorrelation', ['sid'=>$project_id, 'gid' => $gid]);      
	}	

	public function getTTestHeatmapData($project_id, $gid, $data_type="UCSC") {
		$project = Project::getProject($project_id, $data_type);
		list($tscore, $pvalue) = $project->getTTestResults($gid);
		$samples = $project->getStudySamples();
		$tissue_cats = array();
		foreach ($samples as $sample) {
			$tissue_cats[$sample->tissue_type] = $sample->tissue_cat;
		}
		$tissues = array_keys($tscore);
		$data_tscores = array();
		$data_pvalues = array();
		$group_json = array();
		foreach ($tissues as $tissue1) {
			$data_tscore = array();
			$data_pvalue = array();
			foreach ($tissues as $tissue2) {
				$data_tscore[] = number_format($tscore[$tissue1][$tissue2],2);
				$pvalue[$tissue1][$tissue2] = number_format($pvalue[$tissue1][$tissue2], 3);
				$data_pvalue[] = $pvalue[$tissue1][$tissue2];
			}
			$data_tscores[] = $data_tscore;
			$data_pvalues[] = $data_pvalue;
			$group_json[] = $tissue_cats[$tissue1];
		} 

		$header = 150;
		$max_label_len = max(array_map('strlen', $tissues));
		$width = $header * 2 + count(array_unique($tissues)) * 20 + $max_label_len * 3;
		$height = $header * 2 + count(array_unique($tissues)) * 20 + $max_label_len * 10;
		$plot_json = array("z" => array('Group'=> $group_json), "x" => array('Group'=> $group_json), "y"=>array('vars'=>$tissues, 'smps'=>$tissues, 'data'=>$data_tscores), "m"=>array("Name"=>'T-Test Results'));
		$json = array("data"=>$plot_json, "width"=>$width, "height"=>$height, "tscore"=>$data_tscores, "pvalue"=>$data_pvalues);
		return json_encode($json);
	}	

	public function getExpressionByGene($project_id, $gid) {
      		$sql = "select s.tissue_type, s.tissue_cat, s.sample_id, exp_value from study_samples s, expr e where s.study_id=$project_id and gene='$gid' and s.sample_id=e.sample_id";
      		$gene_exprs = DB::select($sql);
		return $gene_exprs;
	}


	public function formatScientific($someFloat) {
		$power = ($someFloat % 10) - 1;
		return ($someFloat / pow(10, $power)) . "e" . $power;
	}


	public function getCorrelationHeatmapJson($corr, $project_id, $gid, $data_type) {
		if ($corr == null) 
			return array(null, 0, 0);
		$project = Project::getProject($project_id);
		$genes = array_keys($corr);
		list($raw_data, $groups) = $project->getCorrelationExp($genes);
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

	public function getCorrelationData($project_id, $gene_id, $cutoff, $target_type="refseq", $method="pearson", $value_type="tmm-rpkm") {
		set_time_limit(240);
		ini_set('memory_limit', '1024M');
		$project = Project::getProject($project_id);
		list($corr_p, $corr_n) = $project->getCorrelation($gene_id, $cutoff, $target_type, $method, $value_type);
		arsort($corr_p, SORT_NUMERIC);
		//$corr_p_topn = array_slice($corr_p, 0, $top_n);
		asort($corr_n, SORT_NUMERIC);
		//$corr_n_topn = array_slice($corr_n, 0, $top_n);		
		//if ($target_type=="ensembl")
		//	$cols = array(array("title"=>"Gene"), array("title"=>"Symbol"), array("title"=>"Pearson"), array("title"=>"Positive/negative"));
		//else
			$cols = array(array("title"=>"Symbol"), array("title"=>"Gene"), array("title"=>"Coefficient"), array("title"=>"Positive/negative"));
		$data = array();
		$gene_infos = Gene::getGenesInfo();
		foreach ($corr_p as $gene=>$value) {
			$symbol = $gene;
			if (array_key_exists($gene, $gene_infos)) {
				$gene_info = $gene_infos[$gene];
				$symbol = $gene_info->symbol;
			}
			$data[] = array($symbol, $gene, $value, "Positive");

		}
		foreach ($corr_n as $gene=>$value) {
			$symbol = $gene;
			if (array_key_exists($gene, $gene_infos)) {
				$gene_info = $gene_infos[$gene];
				$symbol = $gene_info->symbol;
			}
			$data[] = array($symbol, $gene, $value, "Negative");
		}
		$table_data = array("cols" => $cols, "data" => $data);
		//$json_p = $this->getCorrelationHeatmapJson($corr_p_topn, $project_id, $gene_id, $target_type);
		//$json_n = $this->getCorrelationHeatmapJson($corr_n_topn, $project_id, $gene_id, $target_type);
		//$best_gene = array_keys($corr_p_topn)[0];
		//list($best_gene, $best_symbol) = explode(',', $best_gene);
		//$json = array("p"=>$json_p, "n"=>$json_n, "table_data" => $table_data);
		return json_encode($table_data);
   	}


	public function getTwoGenesDotplotData($project_id, $g1, $g2, $target_type) {
		Log::info("g1 - g2: $g1 - $g2");
		$project = Project::getProject($project_id);
		$exp_data = $project->getGeneExpression(array($g1, $g2), $target_type, "all");

		list($vars1, $types1) = $project->getMutatedRNAseqSamples($g1);
		list($vars2, $types2) = $project->getMutatedRNAseqSamples($g2);
		foreach($types1 as $type => $dummy) {
			$exp_data["meta_data"]["attr_list"][] = "$type Mutation";
			for ($i=0; $i<count($exp_data["sample_ids"]);$i++) {
				$sample_id = $exp_data["sample_ids"][$i];
				$sample_name = $exp_data["samples"][$i];
				$has_mut1 = isset($vars1[$sample_id][$type]);
				$has_mut2 = isset($vars2[$sample_id][$type]);
				$label = 'Both';
				if ($has_mut1 && !$has_mut2)
					$label = "$g1 only";
				if (!$has_mut1 && $has_mut2)
					$label = "$g2 only";
				if (!$has_mut1 && !$has_mut2)
					$label = "Neither";
				$exp_data["meta_data"]["data"][$sample_name][] = $label;
			}
		}

		//return json_encode($exp_data);
		$data = array();
		$tissue_type = array();
		$samples = $exp_data["samples"];
		$exp1 = array();
		$exp2 = array();
		for ($i=0;$i<count($samples);$i++) {
			$sample = $samples[$i];
			$exp_value1 = $exp_data["exp_data"][$g1][$target_type][$i];
			$exp_value2 = $exp_data["exp_data"][$g2][$target_type][$i];
			$exp_value1 = log($exp_value1 + 1, 2);
			$exp_value2 = log($exp_value2 + 1, 2);
			$data[] = array($exp_value1, $exp_value2);
			$exp1[] = $exp_value1;
			$exp2[] = $exp_value2;
			$tissue_type[] = "NA";
		}
		//return json_encode($data);
		//calculate the p-value
		$exp1_list = implode(',', $exp1);
		$exp2_list = implode(',', $exp2);
		$cmd = "Rscript ".app_path()."/scripts/corr_test.r $exp1_list $exp2_list";
		//return $exp1_list."<BR><BR>".$exp2_list;
		$ret = shell_exec($cmd);
		Log::info("ret: $ret");
		$fields = preg_split('/\s+/', $ret);
		Log::info(count($fields));
		return json_encode(array("data" => $exp_data, "pvalue" => array("p_two"=>$fields[0], "p_great"=>$fields[1], "p_less"=>$fields[2])));
		//$json = array("data"=>array("y"=>array("smps"=>[$g1,$g2], "vars"=> $samples, "data" => $data), "z"=> array("Tissue" => $tissue_type)), "p_two"=>$fields[0], "p_great"=>$fields[1], "p_less"=>$fields[2]);
		
		return json_encode($json);
   	}


	public function getTranscriptExpressionData($gene_list, $sample_id) {		
		$genes = explode(',', $gene_list);
		$genes = Sample::getTranscriptExpression($genes, $sample_id);
		
		return json_encode($genes);
	}	

	public function downloadCNVFiles($project_id, $type="sequenza.summary.tsv") {
		$pathToFile = storage_path()."/project_data/$project_id/cnv/$project_id.$type";
		return response()->download($pathToFile);
	}

	public function getExpMatrixFile($project_id, $target_type, $data_type) {
		//$pathToFile = storage_path()."/project_data/$project_id/$target_type-gene.$lib_type.$value_type.tsv";
		$pathToFile = storage_path()."/project_data/$project_id/expression.${data_type}.tsv";
		return response()->download($pathToFile);
	}

	public function viewFusionProjectDetail($project_id) {
		$project = Project::getProject($project_id);
		$filter_definition = array();
		$filter_lists = UserGeneList::getDescriptions('fusion');
		foreach ($filter_lists as $list_name => $desc) {
			$filter_definition[$list_name] = $desc;
		}
		$setting = UserSetting::getSetting("page.fusion");
		$rows = Project::getFusionDiagnosisCount($project_id);
		$diags = array();
		foreach ($rows as $row)
			$diags[$row->diagnosis] = $row->patient_count;
		return View::make('pages/viewFusionProjectDetail', ['project_name' => $project->name, 'project_id' =>$project_id, 'setting' => $setting, 'filter_definition' => $filter_definition, 'diags' => $diags]);
	}

	public function getProjectQCI($project_id, $type, $format="json") {
		$project = Project::getProject($project_id);
		$qci_data = $project->getQCI($type);
		$data = $this->getDataTableJson($qci_data);
		if ($format == "text") {
			$filename = $project->name."-QCI-$type.txt";
			$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='.$filename);		
			$content = $this->dataTableToTSV($data["cols"], $data["data"]);
			return Response::make($content, 200, $headers);	
		}
		return $data;
	}

	public function viewQCITypeProjectDetail($project_id, $type) {
		$filter_definition = array();
		$filter_lists = UserGeneList::getDescriptions($type);
		foreach ($filter_lists as $list_name => $desc) {
			$filter_definition[$list_name] = $desc;
		}
		$project = Project::getProject($project_id);

		return View::make('pages/viewQCITypeProjectDetail', ['project_id' => $project_id, 'type' => $type, 'filter_definition' => $filter_definition]);
	}

	public function viewVarProjectDetail($project_id, $type, $diagnosis = "Any") {
		$filter_definition = array();
		$filter_lists = UserGeneList::getDescriptions($type);
		foreach ($filter_lists as $list_name => $desc) {
			$filter_definition[$list_name] = $desc;
		}
		$project = Project::getProject($project_id);

		$setting = UserSetting::getSetting("page.$type");
		if ($type == "QCI") {
			$types = $project->getQCITypes();			
			return View::make('pages/viewQCIProjectDetail', ['project' => $project, 'types' => $types, 'filter_definition' => $filter_definition]);
		}
		$diag_counts = Project::getDiagnosisCount($project_id);
		$total_patients = 0;
		foreach ($diag_counts as $diag_count) {
			$total_patients += $diag_count->patient_count;
		}
		$diag_counts = array_merge(array((object) array('diagnosis' => 'Any', 'patient_count' => $total_patients)), $diag_counts);

		

		//$meta = $project->getMetaData();
		$meta_list = $project->getProperty("survival_meta_list");		
		$patient_meta = $project->getPatientMetaData(true,false,false,$meta_list);
		$meta = $patient_meta["meta"];
		$annotation = UserSetting::getSetting("default_annotation", false);
		return View::make('pages/viewVarProjectDetail', ['project_id' => $project_id, 'type' => $type, 'setting' => $setting, 'filter_definition' => $filter_definition, 'diag_counts' => $diag_counts, 'diagnosis' => $diagnosis, 'annotation' => $annotation, 'meta' => $meta, 'has_variant_file' => $project->hasVariantFile($type)]);
	}
	
	public function viewCreateProject() {
		return View::make('pages/viewCreateProject', ["project_id" => "", "project_name" => "", "project_desc" => "", "project_ispublic" => "0", "patients" => "[]"]);
	}

	public function viewEditProject($project_id) {
		$project = Project::find($project_id);
		$patients = Project::getPatients($project_id);
		$patient_ids = array();
		foreach ($patients as $patient)
			$patient_ids[] = $patient->patient_id;
		return View::make('pages/viewCreateProject', ["project_id" => $project->id, "project_name" => $project->name, "project_desc" => $project->description, "project_ispublic" => $project->ispublic, "patients" => json_encode($patient_ids)]);
	}

	public function getPatientTree() {
		return Oncotree::getPatientTree();
	}

	public function getOncoTree() {
		return Oncotree::getOncoTree();
	}

	public function deleteProject($project_id) {
		$user = User::getCurrentUser();
		if ($user == null) {
			return json_encode(array("code"=>"no_user","desc"=>""));
		}
		try {				
			DB::beginTransaction();
			$project = Project::find($project_id);
			$project->delete();
			DB::table('project_patients')->where('project_id', '=', $project_id)->delete();
			DB::commit();
			return json_encode(array("code"=>"success","desc"=>$project_id));			
		} catch (\PDOException $e) { 
			DB::rollBack();
			return json_encode(array("code"=>"error","desc"=>$e->getMessage()));			
		}
	}

	public function getProjectSamples($project_id, $format="json", $exp_type="all") {
		$project = Project::getProject($project_id);
		$rows = $project->getProjectSamples(true, $exp_type);
		if ($format == "json") {
			$data = $this->getDataTableJson($rows, ["sample_alias","run_id","biomaterial_id", "relation", "platform", "project_id", "name", "diagnosis"]);
			return json_encode($data);
		}
		$filename = $project->name."_samples.tsv";
		$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='.$filename);		
		$data = $this->getDataTableJson($rows);
		$content = $this->dataTableToTSV($data["cols"], $data["data"]);
		return Response::make($content, 200, $headers);		
		
	}

	public function getProjectGenotypingByPatient($project_id, $patient_id) {
		$project = Project::getProject($project_id);
		$rows = $project->GenotypingByPatient($patient_id);
		$data = $this->getDataTableJson($rows);
		return json_encode($data);
	}

	public function getMatchedGenotyping($project_id, $cutoff=0.75) {
		$project = Project::getProject($project_id);
		$rows = $project->getMatchedGenotyping($cutoff);
		$data = $this->getDataTableJson($rows);
		return json_encode($data);
	}

	public function getProjectGenotyping($project_id, $type="json") {
		$geno_file = storage_path()."/project_data/$project_id/gt.txt";
		if (!file_exists($geno_file))
			return null;
		if ($type == "text") {
			$content = file_get_contents($geno_file);
			$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='.$project_id."_genotyping.txt");
			return Response::make($content, 200, $headers);
		}
		list($header, $data) = Utility::readFileWithHeader($geno_file);		
		
		$cols = array();
		foreach ($header as $col)
			$cols[] = array("title" => $col);
		return json_encode(array("cols"=>$cols, "data" => $data));
	}

	public function viewProjectMixcr($project_id, $type) {
		return View::make('pages/viewMixcr',['project_id'=>$project_id,'type'=>$type]);
	}

	public function getProjectMixcr($project_id, $type, $format="json") {
		$project = Project::getProject($project_id);
		$rows = $project->getMixcr($type);
		$data = $this->getDataTableJson($rows);
		if ($format == "text") {
			$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='."$project->name-$type.tsv");
			$content = $this->dataTableToTSV($data["cols"], $data["data"]);
			return Response::make($content, 200, $headers);			
		}
		return json_encode($data);
	}

	public function saveProject() {
		$user = User::getCurrentUser();
		if ($user == null) {
			return json_encode(array("code"=>"no_user","desc"=>""));
		}
		$user_id = $user->id;		
		$data = Input::all();
		$project_id = $data["id"];
		$project_name = $data["name"];
		$project_desc = $data["desc"];
		$project_ispublic = $data["ispublic"];
		$patients = $data["patients"];
		try {				
			DB::beginTransaction();
			if ($project_id == "")
				$project = new Project;
			else
				$project = Project::find($project_id);
			if ($project == null) {
				DB::rollBack();
				return json_encode(array("code"=>"error","desc"=>"project not exists!"));
			}
			$project->name = $project_name;
			$project->description = $project_desc;
			$project->ispublic = ($project_ispublic)? '1' : '0';
			$project->isstudy = '1';
			$project->status = '0';
			$project->user_id = $user_id;
			$project->version = "19";
			$project->save();
			$project_id = $project->id;
			$cases = VarCases::getCaseNames();
			foreach ($patients as $patient) {
				Log::info($patient);
				$patient_cases = $cases[$patient];
				$samples=Project::get_project_sampleBy_Paient($patient);
				foreach ($samples as $sample){
					$sample_id=$sample->sample_id;
					$sample_name=$sample->sample_name;
					$tissue_cat=$sample->tissue_cat;
					$tissue_type=$sample->tissue_type;
					$library_type=$sample->library_type;
					$platform=$sample->platform;
					$material_type=$sample->material_type;
					$exp_type=$sample->exp_type;
					DB::table('project_samples')->insert(["project_id" => $project_id, "patient_id" => $patient, "sample_id" => $sample_id, "sample_name" => $sample_name, "tissue_cat" => $tissue_cat, "tissue_type" => $tissue_type, "library_type" => $library_type, "platform" => $platform, "material_type" => $material_type, "exp_type" => $exp_type]);



				}
				#Log::info($patient_cases);
				foreach ($patient_cases as $patient_case) {
					DB::table('project_patients')->insert(["project_id" => $project_id, "patient_id" => $patient, "case_name" => $patient_case]);
				}
			}
			DB::commit();			
		} catch (\PDOException $e) { 
			DB::rollBack();
			return json_encode(array("code"=>"error","desc"=>$e->getMessage()));
			
		}
		//DB::statement("BEGIN Dbms_Mview.Refresh('PROJECT_PATIENT_SUMMARY','C');END;");
		//DB::statement("BEGIN Dbms_Mview.Refresh('PROJECT_SAMPLE_SUMMARY','C');END;");
		//DB::statement("BEGIN Dbms_Mview.Refresh('PROJECT_SAMPLES','C');END;");
		//DB::statement("BEGIN Dbms_Mview.Refresh('VAR_GENE_TIER','C');END;");
		//DB::statement("BEGIN Dbms_Mview.Refresh('VAR_GENE_COHORT','C');END;");
		$email = $user->email_address;
		$url = url("/");
		//$cmd = app_path()."/scripts/preprocessProjectMaster.pl -p $project_id -e $email -u $url > ".storage_path()."/project_data/$project_id/run.log 2>&1&";
		$cmd = "perl ".app_path()."/scripts/preprocessProjectMaster.pl -p $project_id -e $email -o ".app_path()."/storage/project_data -u $url 2>&1&";
		//$output = "";
		$email = $user->email_address;
		//exec($cmd, $output);
		Log::info("commmand: $cmd");
		//Log::info("commmand: ".json_encode($output));
		$handle = popen($cmd, "r");
		$read = fread($handle, 2096);
		Log::info($read);
		pclose($handle);
		return json_encode(array("code"=>"success","desc"=>$project_id));
	}

	public function downloadProjectVariants($project_id, $type) {
		if (!User::hasProject($project_id)) {
			return View::make('pages/error', ['message' => 'Access denied!']);
		}		
		$pathToFile = storage_path()."/project_data/$project_id/variants/$project_id.$type.zip";
		return response()->download($pathToFile);
	}	

	public function downloadProjectVCFs($project_id) {
		if (!User::hasProject($project_id)) {
			return View::make('pages/error', ['message' => 'Access denied!']);
		}		
		$pathToFile = storage_path()."/project_data/$project_id/$project_id.vcf.zip";
		return response()->download($pathToFile);
	}

	public function downloadMixcrFile($project_id, $file) {
		$pathToFile = storage_path()."/project_data/$project_id/mixcr/$file";
		if (file_exists($pathToFile))
			return response()->download($pathToFile);
		return "File $file not found";
	}

	public function getQC($project_id, $type, $format="json") {
		$data = VarQC::getQCByProjectID($project_id, $type, $format); 
		if ($format == "json")
			return json_encode($data);
		$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='."$project_id-$type-QC.tsv");
		$content = $this->dataTableToTSV($data["qc_data"]["cols"], $data["qc_data"]["data"]);
			return Response::make($content, 200, $headers);		
	}
	public function getProjectByUser($user){
		$rows = DB::select("select a.project_id from reviewer_tokens a, reviewer_users b, users k where
k.id=b.userid and b.tokenid=a.tokenid and k.email='$user'");
		if (count($rows)==0){
			return Redirect::intended('/');
		}
		echo  $rows[0]->project_id;
	}
	public function setProjectByToken($user,$token){
		Log::info("setting token by user $user");
		$ADMIN= 'chouh@nih.gov';
		$rows = DB::select("select id from users where email='$user'");
		if (count($rows)<=0){
			mail("$ADMIN","[FAILED] New Reviewer Login", "Could not find user in the user table user=$user");
			Log::info("In setProjectByToken ...User does not exist $user")  ;
			return;
		}

		$userid = $rows[0]->id;
		$rows = DB::table('reviewer_tokens')->where('tokenid',$token)->get();
		if (count($rows)<=0){
			mail("$ADMIN","[FAILED] New Reviewer Login", "Token not exists or expired? $token user=$user($userid)");
			Log::info( "In setProjectByToken ...User $user tried to login with a token $token (token invalid)");
			return Redirect::intended('/');
		}
		$project_id = $rows[0]->project_id;
		$rows = DB::table('reviewer_users')->where('userid',$userid)->get();
		if (count($rows)<=0){
			DB::table('reviewer_users')->insert(array('userid' => $userid, 'tokenid' => $token));
		}
		$rows = DB::select("select count(*) as count from users_groups where user_id='$userid' and group_id='$project_id'");
		if ($rows[0]->count==0){
			DB::table('users_groups')->insert(array('user_id' => $userid, 'group_id' => $project_id));
			DB::statement("BEGIN Dbms_Mview.Refresh('USER_PROJECTS','C');END;");
		}else{
			mail("$ADMIN","[FAILED] New Reviewer Login", "USER_GROUPS not updated user=$user($userid) ");
			return Redirect::intended('/');
		}

		//echo "$project_id";
		return $project_id;
		
		$status = mail("$ADMIN","New Reviewer Login", "User $user($userid) has signed in for the first time for project $project_id using token $token");
		Log::info("sent email in setProjectByToken?yes=" . $status);
		return Redirect::intended('/viewProjectDetails/' . $project_id);
		
	}

	public function syncPublicProject($project_name) {
		if (!User::isSuperAdmin())
			return "Unauthorized!";
		$logged_user = User::getCurrentUser();
		$script_dir = Config::get("site.mount_public")."/app/scripts/backend";
		$stamp = date("Ymd");		
		$cmd = "source ~/.bash_profile;/bin/sbatch -o ${script_dir}/../../storage/logs/slurm/sync_${project_name}.$stamp.o -e ${script_dir}/../../storage/logs/slurm/sync_${project_name}.$stamp.e --chdir=${script_dir} ${script_dir}/submitSyncPublic.sh $project_name $logged_user->email 2>&1";
		//$cmd='/bin/whoami';
		#exec('/bin/whoami', $out, $retval);
		$out = shell_exec($cmd);
		#$out = json_encode($out);
		if ($out != NULL)
			return "ok";
		
		return "$out";
	}

	public function getGSVAData($project_id, $geneset, $method, $format="json") {
		$file = storage_path()."/project_data/$project_id/gsva/${geneset}.${method}.txt";
		if (!file_exists($file)) {
			return json_encode(array("status"=>"no data"));
		}
		if ($format=="text") {
			$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='."$project_id-$geneset-$method.tsv");
			$content = file_get_contents($file);
			return Response::make($content, 200, $headers);
		}
		$json_data = $this->fileToTable($file);
		#$json_data["status"] = "ok";
		return $json_data;
	}
	
}
