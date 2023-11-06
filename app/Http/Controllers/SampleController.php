<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Project;
use App\Models\Gene;
use App\Models\Patient;
use App\Models\Sample;
use App\Models\VarCases;
use App\Models\VarAnnotation;
use App\Models\UserGeneList;
use App\Models\Utility;
use App\Models\UserSetting;
use Symfony\Component\Process\Process;
use Config,DB,Log,Lang,View,Cache,Request,Response,stdClass;

class SampleController extends BaseController {


	/**
	 * View all samples.
	 *
	 * @return table view page
	 */
	public function viewSample($id) {
		$cols = $this->getColumnJson("samples", Config::get('onco.sample_column_exclude'));
		$detail_cols = $this->getColumnJson("sample_details", array('sample_id'));		
		return View::make('pages/viewTableMasterDetail', ['cols'=>$cols, 'col_hide'=> Config::get('onco.sample_column_hide'), 'filters'=> Config::get('onco.sample_column_filter'),'title'=>'Samples', 'primary_key'=>$id, 'detail_cols' => $detail_cols, 'detail_url'=>url('/getSampleDetails'), 'detail_pos'=>'east', 'detail_title'=>'Sample Details', 'url'=>url('/getSample/'.$id)]);
	}

	public function viewSearchSample($keyword) {
		return View::make('pages/viewSamples', ['keyword'=>$keyword]);		
	}

	public function searchSample($keyword) {
		$rows = Sample::searchSample($keyword, true);
		$root_url = url("/");
		foreach ($rows as $row) {
			$projects = explode(",", $row->project);
			$projects = array_unique($projects);
			sort($projects);
			$project_strs = array();
			foreach ($projects as $project) {
				$project_pairs = explode(":", $project);
				$project_strs[] = "<a target=_blank href='".$root_url."/viewProjectDetails/".$project_pairs[1]."'>$project_pairs[0]</a>";
			}
			$row->project = implode(",", $project_strs);
			$cases = explode(",", $row->case_name);
			$cases = array_unique($cases);
			$cases_strs = array();
			foreach ($cases as $case) {
				$case_pairs = explode(":", $case);
				if ($case_pairs[1] == "") {
					$cases_strs[] = "<font color='red'>".$case_pairs[0]."</font>";
				} else {
					$cases_strs[] = $case_pairs[0];
				}
			}
			$row->case_name = implode(",", $cases_strs);
			$row->patient_id = "<a target=_blank href='".$root_url."/viewPatient/any/".$row->patient_id."'>".$row->patient_id."</a>";			
		}
		$data = $this->getDataTableJson($rows);
		return json_encode($data);
	}

	/**
	 * View all patients.
	 *
	 * @return table view page
	 */
	public function viewPatients($project_id, $search_text, $include_header, $source) {
		if (strtolower($project_id) == "null")
			$project_id = UserSetting::getSetting('default_project', false);
		$projects = User::getCurrentUserProjects();
		$cols = $this->getColumnJson("patients", Config::get('onco.patient_column_exclude'));
		$link_col = array(array("title"=>"Link"));
		$cols = array_merge($link_col, $cols);
		$sample_cols = $this->getColumnJson("samples", Config::get('onco.sample_column_exclude'));
		$page = ($include_header)? 'pages/viewPatients' : 'pages/viewPatientContent';
		$col_hide = Config::get("site.isPublicSite")? array(Lang::get("messages.project_name"),Lang::get("messages.case_list"), Lang::get("messages.user_id")) : Config::get('onco.patient_column_hide');
		return View::make($page, ['projects' => $projects, 'cols'=>$cols, 'col_hide'=> $col_hide, 'filters'=>Config::get('onco.patient_column_filter'),'title'=>'Patients', 'search_text'=>$search_text, 'detail_cols'=>$sample_cols, 'detail_url'=>url('/getSampleByPatientID'), 'detail_pos'=>'south', 'detail_title'=>'Samples', 'project_id'=>$project_id, 'search_text'=>$search_text, 'include_header' => $include_header, 'source' => $source]);
		
	}

	public function getProejctListForPatient($patient_id) {
		$projects = Patient::getProjectList($patient_id);
		return json_encode($this->getDataTableJson($projects));
	}

	//used in variants page
	public function viewPatient($project_id, $patient_id, $case_name='any') {
		$starttime = microtime(true);
		// dd($project_id);
		try {
			$user_id = User::getCurrentUser()->id;
		} catch (Exception $e) {
			return View::make('pages/error', ['message'=>"Please log in first!"]);
		}
		//$starttime = microtime(true);
		if (strtolower($project_id) == "any")
			$project_id = "null";
		if (strtolower($patient_id) == "any")
			$project_id = "null";
		$patient = Patient::getPatient($patient_id);
		if ($patient == null)
			return View::make('pages/error', ['message'=>"$patient_id not found!"]);
		else
			$patient_id = $patient->patient_id;
		
		$patients = Patient::getVarPatientList($user_id);
		// Log::info("project_id in viewPatient = " . project_id)
		// Log::info($patients);
		$do_log = ($patient_id != "null");
		//$endtime = microtime(true);
		//$timediff = $endtime - $starttime;
		//Log::info("execution time (getVarPatientList): $timediff seconds");
		//$starttime = microtime(true);

		$project_json = array();
		$patient_json = array();
		$diagnosis_json = array();
		
		$diagnoses = array();
		$projects = array();
		
		$project = array();  //project list for case (one case may belong to many projects)
		
		$default_project = "ANY";
		$default_diagnosis = "ANY";
		$project_names["(ANY)"] = "ANY";
		$path = "";
		$case_list = array();
		if (strtolower($project_id) == "null" && strtolower($patient_id) == "null")
			$default_project = UserSetting::getSetting('default_project', false);					
		else
			$default_project = $project_id;
		$found = false;
		$patient_projects = array();
		$case_name_cnt = array();
		foreach ($patients as $patient) {
			if (($patient->project_id == $default_project && $patient_id == "null") || (strtolower($default_project) == "any" && $patient_id == "null"))
				$patient_id = $patient->patient_id;						
			if ($patient->patient_id == $patient_id && (strtolower($project_id) == "null" || $patient->project_id == $default_project) && (strtolower($case_name) == "any" || $patient->case_name == $case_name)) {
				//$case_name = $patient->case_name;
				if (!$found || $default_project == $patient->project_id) {							
					$qcase_name = str_replace(".", "_", $patient->case_name);
					$qcase_name = str_replace(" ", "_", $qcase_name);
					$case_list[$patient->case_name] = $qcase_name;
				}
				Log::info('adding ' . $patient->case_id . " to case_list array");
				if (!$found) {
					$found = true;
					$diagnosis = $patient->diagnosis;
					$default_project = $patient->project_id;
					$default_diagnosis = $patient->diagnosis;
					$path = $patient->path;					
				}				
			}
			if ($patient->patient_id == $patient_id)
				$patient_projects[$patient->project_name] = $patient->project_id;
			$project_names[$patient->project_name] = $patient->project_id;
			$project_ids[$patient->project_id] = $patient->project_name;

			$projects["ANY"]["(ANY)"][$patient->patient_id] = '';
			$projects["ANY"][$patient->diagnosis][$patient->patient_id] = '';
			$projects[$patient->project_id][$patient->diagnosis][$patient->patient_id] = '';
			$projects[$patient->project_id]["(ANY)"][$patient->patient_id] = '';
			
		}		
		ksort($case_list);
		if (count($case_list) == 0) {
			$msg = "Patient $patient_id not found!";
			return View::make('pages/error', ['message'=> $msg]);
		}
		if ($case_name == 'any' || $case_name == null)
			$case_name = array_keys($case_list)[0];	
		if (!array_key_exists($case_name,$case_list)) {
			Log::info('no case for me!');
			Log::info($case_list);
			$msg = ($case_name == "any")? "No patient/case found!" : "Case $case_name not found!";
			return View::make('pages/error', ['message'=> $msg]);
		}
		/*
		foreach ($case_list as $cname => $cid) {
			if (array_key_exists($cname, $case_name_cnt))
				$case_name_cnt[$cname]++;
			else
				$case_name_cnt[$cname] = 1;
		}
		*/
		Log::info($case_list);
			
		if (count($case_list) > 1) {
			$case_list["Merged"] = "any";
			//$case_name_cnt["Merged"] = 1;
		}
		/*
		foreach ($case_list as $cid => $cname) {
			if ($case_name_cnt[$cname] > 1)
				$case_list[$cid] = "$cname-$cid";
		}
		*/
		ksort($patient_projects);
		ksort($project_names);

		//if (strtolower($project_id) != "any") 
		//	$default_project = $project_id;		

		if (!$found) {
			return View::make('pages/error', ['message'=>"$patient_id not found!"]);
		}
		$links = array();
		foreach ($patient_projects as $project_name => $project_id)	{
			if ($project_id == $default_project)
				$project_name = "<font color='red'><b>$project_name</b></font>";
			#$links[] = "<a target=_blank href='".url("/viewPatient/$project_id/$patient_id")."'>$project_name</a>";
			$links[] = "<a target=_blank href='".url("/viewProjectDetails/$project_id")."'>$project_name</a>";
		}
		$project_link = implode(',', $links);
		// Log::info(json_encode($case_list));
		// Log::info($case_id);

		$endtime = microtime(true);
		$timediff = $endtime - $starttime;

		Log::info("execution time (viewPatient): $timediff seconds");

		if ($do_log) {

			$ret = $this->saveAccessLog($patient_id, $default_project, "patient");
			Log::info("saving log. Results: ".json_encode($ret));
		}
		return View::make('pages/viewPatient', ['default_case_name' => $case_name,'case_list' => $case_list, 'project_link' => $project_link, 'project_names' => json_encode($project_names), 'projects' => json_encode($projects), 'project' => implode(',',$project), 'patient_id'=>$patient_id, 'default_project' => $default_project, 'default_diagnosis' => $default_diagnosis, 'patient' => $patient, "patient_projects" => $patient_projects]);
	}

	//used in variants page
	public function viewCase($project_id, $patient_id, $case_name, $with_header=false) {
		if ($case_name == "Merged") {
			$case_name = "any";
			$case_id = "any";
		}
		$casestarttime = microtime(true);
		$patients = Patient::where('patient_id', '=', $patient_id)->get();
		$cases = Project::getProcessedCases($project_id, $patient_id, $case_name);
		if (count($cases) > 0) {
			$case = $cases[0];
			$case_id = $cases[0]->case_id;
			$path = $cases[0]->path;
			$case->case_name = $case_name;
		}
		$summary_json = $this->getpipeline_summary($patient_id,$case_id);
		$summary = json_decode($summary_json);
		$version = $summary->version_data->pipeline_version;

		$patient = null;
		if (count($patients) > 0)
			$patient = $patients[0];
		$exp_samples = VarCases::getExpressionSamples($patient_id, $case_name);
		$mix_samples = VarCases::getMixcrSamples($patient_id, $case_name,"mixcr");
		$mixRNA_samples = VarCases::getMixcrSamples($patient_id, $case_name,"rna");
		$mixTCR_samples = VarCases::getMixcrSamples($patient_id, $case_name,"tcr");
		$has_expression = (count($exp_samples) > 0);
		$mixcr_summary = Sample::getMixcr($patient_id,$case_id,"summary");
		$has_mixcr = (count($mixcr_summary) > 0);
		$mixcr_samples = array();

		foreach (glob(storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/*/mixcr/*.pdf") as $filename) {
			preg_match('/.*\/mixcr\/(.*)\.(.*)\.fancyvj\.wt\.pdf/', $filename, $m );
			$mixcr_samples[$m[1]][] = $m[2];
		}

		Log::info("Expression samples: ".count($exp_samples));
		Log::info("Mixcr");
		Log::info($mix_samples);
		Log::info("Mixcr RNA");
		Log::info($mixRNA_samples);
		Log::info("Mixcr TCR");
		Log::info($mixTCR_samples);	
		/*
		$exp_samples = array();
		$has_expression = $patient->hasExpressionData();
		if ($has_expression) {
			$_exp_samples = Patient::getExpressionSamples($patient_id, $case_id);
			foreach($_exp_samples as $exp_sample)
				$exp_samples[$exp_sample->sample_name] = $exp_sample->sample_id;
		}
		*/
		$var_types = $patient->getVarTypes($case_name);
		$sample_types = $patient->getVarSamples($project_id, $case_name);
		$cnv_samples = array();
		$cnv_genelevel_samples = array();
		$cnvkit_samples = array();
		$cnvtso_samples = array();
		// $methylseq_files = array();
		//$case = VarCases::getCase($patient_id, $case_name);#table_cases
		// dd($case);
		if (!isset($case)) {
			return View::make('pages/error_no_header', ['message'=>"Permission denied!"]);
		}
		$path = $case->path;
		$case->status = "passed";
		//$case_name = $case->case_name;
		$has_germline_somatic = false;
		Log::info("======= Sample types ============");
		Log::info(json_encode($sample_types));
		foreach($sample_types as $type => $samples) {
			if ($type == "germline" || $type == "somatic")
				$has_germline_somatic = true;
			foreach ($samples as $sample) {
				if ($sample->exp_type != 'RNAseq') {
					$file = storage_path()."/ProcessedResults/".$sample->path."/$patient_id/$sample->case_id/$sample->sample_name/sequenza/$sample->sample_name/$sample->sample_name"."_chromosome_view.pdf";
					if (!file_exists($file)) {
						$file = storage_path()."/ProcessedResults/".$sample->path."/$patient_id/$sample->case_id/$sample->sample_id/sequenza/$sample->sample_id/$sample->sample_id"."_chromosome_view.pdf";
						if (!file_exists($file)) {
							$file = storage_path()."/ProcessedResults/".$sample->path."/$patient_id/$sample->case_id/Sample_$sample->sample_id/sequenza/Sample_$sample->sample_id/Sample_$sample->sample_id"."_chromosome_view.pdf";
							if (!file_exists($file))
								Log::info("no squenza!");
							else
								$cnv_samples["Sample_".$sample->sample_id] = $sample->case_id;
						}
						else
							$cnv_samples[$sample->sample_id] = $sample->case_id;
					} else
						$cnv_samples[$sample->sample_name] = $sample->case_id;

					$file = storage_path()."/ProcessedResults/".$sample->path."/$patient_id/$sample->case_id/$sample->sample_name/sequenza/$sample->sample_name"."_genelevel.txt";
					Log::info($file);
					if (file_exists($file)) {
						$cnv_genelevel_samples[$sample->sample_name] = $sample->case_id;
					}



					$file = storage_path()."/ProcessedResults/".$sample->path."/$patient_id/$sample->case_id/$sample->sample_name/cnvkit/$sample->sample_name".".pdf";
					Log::info("====== CNVkit file: $file");
					if (!file_exists($file)) {
						$file = storage_path()."/ProcessedResults/".$sample->path."/$patient_id/$sample->case_id/$sample->sample_id/cnvkit/$sample->sample_id".".pdf";
						if (!file_exists($file)) {
							$file = storage_path()."/ProcessedResults/".$sample->path."/$patient_id/$sample->case_id/Sample_$sample->sample_id/cnvkit/Sample_$sample->sample_id".".pdf";
							if (!file_exists($file))
								Log::info("no CNVkit!");
							else
								$cnvkit_samples["Sample_".$sample->sample_id] = $sample->case_id;
						}
						else
							$cnvkit_samples[$sample->sample_id] = $sample->case_id;
					} else {
						Log::info("CNVkit file exists: $file");
						$cnvkit_samples[$sample->sample_name] = $sample->case_id;
					}
					$file = storage_path()."/ProcessedResults/".$sample->path."/$patient_id/$sample->case_id/$sample->sample_name/cnvTSO/$sample->sample_name".".cns";					
					if (!file_exists($file)) {
						$file = storage_path()."/ProcessedResults/".$sample->path."/$patient_id/$sample->case_id/$sample->sample_id/cnvTSO/$sample->sample_id".".cns";
						if (!file_exists($file)) {
							$file = storage_path()."/ProcessedResults/".$sample->path."/$patient_id/$sample->case_id/Sample_$sample->sample_id/cnvTSO/Sample_$sample->sample_id".".cns";
							if (!file_exists($file))
								continue;
							else
								$cnvtso_samples["Sample_".$sample->sample_id] = $sample->case_id;
						}
						else
							$cnvtso_samples[$sample->sample_id] = $sample->case_id;
					} else
						$cnvtso_samples[$sample->sample_name] = $sample->case_id;
				}
			}
		}
		$sig_samples = array();
		foreach($sample_types as $type => $samples) {
			foreach ($samples as $sample) {
				if ($sample->tissue_cat == 'tumor' && $sample->exp_type != 'RNAseq') {
					$file = VarAnnotation::getSignatureFileName($sample->path, $patient_id, $sample->case_id, $sample->sample_id, $sample->sample_name);
					if ($file != "") {
						$bn = basename($file,".pdf");
						$dn = dirname($file);
						$v3_files = glob("$dn/*_v3*.pdf");
						if (count($v3_files) > 0) {
							$sig_samples[$sample->sample_name]["V2"][$sample->case_id] = [$bn];
							foreach($v3_files as $v3_file) {
								$bn_v3 = basename($v3_file,".pdf");								
								$sig_samples[$sample->sample_name]["V3"][$sample->case_id][] = $bn_v3;
							}
						} else {
							if (substr($version, 0, 2) == "v3")
								$sig_samples[$sample->sample_name]["V2"][$sample->case_id] = [$bn];
							else
								$sig_samples[$sample->sample_name]["V3"][$sample->case_id] = [$bn];
						}
					}

				}
			}
		}

		$hla_samples = array();
		$arriba_samples = array();
		foreach($sample_types as $type => $samples) {
			foreach ($samples as $sample) {
				//if ($sample->tissue_cat == 'normal' && $sample->exp_type != 'RNAseq') {
					$file = VarAnnotation::getHLAFileName($sample->path, $patient_id, $sample->case_id, $sample->sample_id, $sample->sample_name);
					Log::info($file);					
					if ($file != "")
						$hla_samples[$sample->sample_name] = $sample->case_id;
				//}
				$arriba_file = VarAnnotation::getArribaPDFName($sample->path, $patient_id, $sample->case_id, $sample->sample_id, $sample->sample_name);
				if ($arriba_file != "")
					$arriba_samples[$sample->sample_name] = $sample->sample_id;
				Log::info(storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/arriba_out/fusions.pdf");
			}
		}

		$antigen_samples = array();
		foreach($sample_types as $type => $samples) {
			foreach ($samples as $sample) {
				if ($sample->tissue_cat == 'tumor' && $sample->exp_type != 'RNAseq') {
					$file = VarAnnotation::getAntigenFileName($sample->path, $patient_id, $sample->case_id, $sample->sample_id, $sample->sample_name);
					Log::info("file:".$file);					
					if ($file != "")
						$antigen_samples[$sample->sample_name] = $sample->case_id;
				}
			}
		}		

		Log::info("antigen_samples:".json_encode($antigen_samples));

		$total_cnt = 0;		
		
		$cnv_cnt = $patient->getCNVCount();		
		$fusion_cnt = $patient->getFusionCount($case_name);
		$has_splice = $patient->hasSplice($case_name);
		$has_cnvtso = $patient->hasCNVTSO($case_name);

		$tcell_extrect_data = $patient->getTCellExTRECT($case_name);
		$tcell_pdfs = array();
		if (count($tcell_extrect_data) > 0) {
			foreach ($tcell_extrect_data as $tcell_row) {
				$tcell_pdfs[] = [$tcell_row->patient_id, $tcell_row->case_id, $tcell_row->sample_id];
			}
			$tcell_extrect_data = json_encode($this->getDataTableJson($tcell_extrect_data));
		}
		else
			$tcell_extrect_data = NULL;

		$show_circos = ($fusion_cnt > 0) || $has_germline_somatic;
		$methylseq_files = array();//VarAnnotation::getMethylationData("null", $patient_id, $case_id);
		$hasMethylation=0;
		// dd( $methylseq_files);
		#if ($total_cnt == 0 && !$has_expression && $cnv_cnt == 0 && $fusion_cnt == 0 && $hasMethylation == 0)
		#	return View::make('pages/error', ['message'=>"No data in patient $patient_id!"]);
		
		//return $timediff;
		$has_expression_matrix = file_exists(storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/analysis/expression/expression.tpm.coding.tsv");
		$has_qc = file_exists(storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/qc");
		$has_vcf = file_exists(storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/$patient_id.$case_id.vcf.zip");		
		$endtime = microtime(true);
		$timediff = $endtime - $casestarttime;
		//$burden_data = $this->getDataTableJson(VarAnnotation::getMutationBurden("null", $patient_id, $case_id));
		$has_burden = (VarAnnotation::hasMutationBurden("null", $patient_id, $case_name) > 0);
		Log::debug("execution time (viewCase): $timediff seconds $patient_id $case_name");
		// Log::info("methylseq_files:". json_encode($methylseq_files));
		Log::info("cnv_samples:".json_encode($cnv_samples));
		Log::info("cnvkit_samples:".json_encode($cnvkit_samples));
		Log::info("cnvtso_samples:".json_encode($cnvtso_samples));
		Log::info("fusion counts:" . json_encode($fusion_cnt));
		Log::info("sample cases:" . json_encode($sample_types));
		$project = Project::getProject($project_id);
		$report_data = "";
		$summary_rows = VarAnnotation::getQCISummary($patient_id, $case_id);
		foreach ($summary_rows as $summary_row) {
			if ($summary_row->type != "TSO") {
				$report_data = $report_data."<H5>Sample: $summary_row->sample_id</H5><H5>Type: ".ucfirst($summary_row->type)."</H5><br>";
			}			
			$report_data = $report_data.$summary_row->summary."<hr><br>";
		}

		//$report_file = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/report_summary.txt";
		//Log::info("report_file : $report_file");
		//if (file_exists($report_file))
		//	$report_data = $report_data.file_get_contents($report_file);

		// check ChIPseq bw files
		$chip_bws = array();
		$suffix = ".scaled.bw";
		foreach (glob(storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/*/*$suffix") as $filename) {
			$fn = basename($filename);
			$sid = str_replace("Sample_", "", $fn);
			$sid = str_replace($suffix, "", $sid);
			$chip_bws[$sid] = $fn;
		}
    

		return View::make('pages/viewCase', ['with_header' => $with_header, 'summary' => $summary, 'path' => $path, 'cnv_genelevel_samples' => $cnv_genelevel_samples, 'cnv_samples' => $cnv_samples, 'cnvkit_samples' => $cnvkit_samples, 'has_cnvtso' => $has_cnvtso, 'sig_samples' => $sig_samples, 'hla_samples' => $hla_samples, 'antigen_samples' => $antigen_samples, 'sample_types' => $sample_types, 'patient_id'=>$patient_id, 'project_id' => $project_id, 'project' => $project, 'path' => $path, 'merged' => ($case_name == "any"), 'case_name' => $case_name, 'case' => $case, 'var_types' => $var_types, 'fusion_cnt' => $fusion_cnt, 'cnv_cnt'=>$cnv_cnt, 'has_expression' => $has_expression, 'exp_samples' => $exp_samples, 'mix_samples' => $mix_samples,'mixRNA_samples' => $mixRNA_samples,'mixTCR_samples' => $mixTCR_samples, 'has_qc' => $has_qc, 'has_vcf' => $has_vcf, 'show_circos' => $show_circos, 'has_burden' => $has_burden ,'has_Methlyation'=>$hasMethylation, 'methylseq_files'=>$methylseq_files, 'has_splice' => $has_splice, 'arriba_samples' => $arriba_samples, 'report_data' => $report_data, 'tcell_extrect_data' => $tcell_extrect_data, "tcell_pdfs" => $tcell_pdfs, "chip_bws" => $chip_bws, "has_expression_matrix" => $has_expression_matrix, "has_mixcr" => $has_mixcr, "mixcr_samples" => $mixcr_samples ]);
		
	}	

	public function viewChIPseq($patient_id, $case_id) {
		$case = VarCases::getCase($patient_id, $case_id);
		$path = $case->path;
		$chip_bws = array();
		$anno_cols = array("Promoter", "Intergenic", "Exon", "Intron", "3UTR", "5UTR", "TTS");
		$summary_table = array("cols"=>array(["title"=>"Sample"], ["title"=>"Cutoff"], ["title"=>"Number of Peaks"]),"data"=>array());
		$annotations = array();
		$suffix = "_peaks.narrowPeak.nobl.bed.annotation.summary";		
		foreach (glob(storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/*/MACS_Out*/*$suffix") as $filename) {
			$row = array();
			$fn = basename($filename);
			$cutoff = basename(dirname($filename));
			$cutoff = str_replace("MACS_Out_", "", $cutoff);
			$sid = str_replace("Sample_", "", $fn);
			$sid = str_replace($suffix, "", $sid);
			$row[] = $sid;
			$row[] = $cutoff;
			$content = file_get_contents($filename);
			$started_annotation = false;
			$lines = explode("\n", $content);
			foreach ($lines as $line) {
				$line = trim($line);
				$tokens = explode(":", $line);
				if ($tokens[0] == "Total Peaks")
					$row[] = $tokens[1];
				if ($tokens[0] == "Annotating") {
					if ($started_annotation)
						break;
					else
						$started_annotation = true;
				} else {
					if ($started_annotation) {
						$fields = preg_split('/\s+/', $line);
						if (count($fields) == 5 && $fields[0] != "Annotation") {							
							$annotations[$sid][$cutoff][$fields[0]]["count"] = $fields[1];
							$annotations[$sid][$cutoff][$fields[0]]["log2"] = $fields[3];							
						}						
					}
				}

			}
			$summary_table["data"][] = $row;
		}
		foreach ($anno_cols as $anno_key) {
			$summary_table["cols"][] = array("title"=>"$anno_key(Count)");			
		}
		foreach ($anno_cols as $anno_key) {
			$summary_table["cols"][] = array("title"=>"$anno_key(log2Ratio)");
		}
		for ($i=0;$i<count($summary_table["data"]);$i++) {
			$sid = $summary_table["data"][$i][0];
			$cutoff = $summary_table["data"][$i][1];
			foreach ($anno_cols as $anno_key) {
				$count_value = 0;
				$log2_value = 0;
				if (array_key_exists($cutoff, $annotations[$sid]))
					if (array_key_exists($anno_key, $annotations[$sid][$cutoff])) {
						$value = round($annotations[$sid][$cutoff][$anno_key]["count"]);
					}
				$summary_table["data"][$i][] = $value;
			}
		}
		for ($i=0;$i<count($summary_table["data"]);$i++) {
			$sid = $summary_table["data"][$i][0];
			$cutoff = $summary_table["data"][$i][1];
			foreach ($anno_cols as $anno_key) {
				$count_value = 0;
				$log2_value = 0;
				if (array_key_exists($cutoff, $annotations[$sid]))
					if (array_key_exists($anno_key, $annotations[$sid][$cutoff])) {
						$value = $annotations[$sid][$cutoff][$anno_key]["log2"];
					}
				$summary_table["data"][$i][] = '<font color=\"red\">'.$value.'</font>';
			}
		}
		

		$suffix = ".dd.25.scaled.bw";
		foreach (glob(storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/*/*$suffix") as $filename) {
			$fn = basename($filename);
			$sid = str_replace("Sample_", "", $fn);
			$sid = str_replace($suffix, "", $sid);
			$chip_bws[$sid] = $fn;
		}

		$motifs = array();		
		foreach (glob(storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/*/MACS_Out*/motif/knownResults.html") as $filename) {
			$fn = dirname(dirname($filename));
			$cutoff = basename($fn);
			$cutoff = str_replace("MACS_Out_", "", $cutoff);
			$fn = basename(dirname($fn));
			$sid = str_replace("Sample_", "", $fn);
			$motifs[$sid][] = $cutoff;
		}

		return View::make('pages/viewChIPseq', ["patient_id" => $patient_id, "case_id" => $case_id, "path" => $path, "chip_bws" => $chip_bws, "summary_table" => json_encode($summary_table), "motifs" => $motifs]);
	}

	public function viewChIPseqIGV($patient_id, $case_id) {
		$case = VarCases::getCase($patient_id, $case_id);
		$path = $case->path;
		$chip_bws = array();		

		$suffix = ".dd.25.scaled.bw";
		foreach (glob(storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/*/*$suffix") as $filename) {
			$fn = basename($filename);
			$sid = str_replace("Sample_", "", $fn);
			$sid = str_replace($suffix, "", $sid);
			$chip_bws[$sid] = $fn;
		}
		return View::make('pages/viewChIPseqIGV', ["patient_id" => $patient_id, "case_id" => $case_id, "path" => $path, "chip_bws" => $chip_bws]);
	}

	public function viewChIPseqMotif($patient_id, $case_id, $sample_id, $cutoff, $type) {
		$path = VarCases::getPath($patient_id, $case_id);
		$filename = storage_path()."/ProcessedResults/$path/$patient_id/$case_id/Sample_${sample_id}/MACS_Out_$cutoff/motif/".strtolower($type)."Results.html";
		$content = file_get_contents($filename);
		$headers = array('Content-Type' => 'text/html');
		return Response::make($content, 200, $headers);		
	}

	public function viewCases($project_id) {		
		$projects = User::getCurrentUserProjects();		
		return View::make('pages/viewCases', ['project_id' => $project_id, 'projects' => $projects]);
	}

	public function getAnalysisPlot($patient_id, $case_id, $type, $name) {
		$path = VarCases::getPath($patient_id, $case_id);
		$content_type = "application/pdf";
		if (substr($name, -6) == "MA.pdf")
			$name = "DE/$name";
		$pathToFile = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/analysis/$type/$name";
		if (file_exists($pathToFile)) {
			return Response::make(file_get_contents($pathToFile), 200, ['Content-Type' => $content_type,'Content-Disposition' => "inline; filename=$name"]);		
		}
		return "file $name not exists!";
		

	}

	public function getDEResults($patient_id, $case_id, $de_file) {
		$path = VarCases::getPath($patient_id, $case_id);
		$de_results = $this->fileToTable(storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/analysis/expression/DE/${de_file}.txt", "Gene");
		return $de_results;
	}

	public function getGSEAReport($patient_id, $case_id, $geneset, $group, $filename) {
		$path = VarCases::getPath($patient_id, $case_id);
		$htmls = glob(storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/analysis/expression/${group}*${geneset}*gmt/*/$filename");
		$content_type = "text/html";
		if (count($htmls) > 0) {
			return Response::make(file_get_contents($htmls[0]), 200, ['Content-Type' => $content_type]);
		}
		return "file $geneset, $group not exists!";
	}

	public function getGSEASummary($patient_id, $case_id, $gsea_type, $format="json") {
		$path = VarCases::getPath($patient_id, $case_id);
		$f = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/analysis/expression/GSEA_weighted_${gsea_type}_all.txt";
		if (file_exists($f)) {
			$content = file_get_contents($f);
			if ($format == "text")
				return $content;
			$cols = array();
			$data = array();
			$lines = explode("\n", $content);
			foreach ($lines as $line) {
				$fields = explode("\t", $line);
				if (count($cols) == 0) {
					foreach ($fields as $field)
						$cols[] = array("title" => $field);
				} else {
					if (count($cols)==count($fields))	
						$data[] = $fields;
				}
			}
			return json_encode(array("cols" => $cols, "data" => $data));
		}
		return "GSEA file $f not found";
	}

	public function viewExpressionAnalysisByCase($project_id, $patient_id, $case_id) {
		$path = VarCases::getPath($patient_id, $case_id);
		$fs = glob(storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/analysis/expression/*.pdf");
		$files = array();
		foreach ($fs as $f) {
			$files[basename($f, ".pdf")] = basename($f);
		}
		$types = array("NCI","c2","c6","CytoSig");
		$summary_files = glob(storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/analysis/expression/GSEA_weighted_*_all.txt");
		// if no summary files found, generate them
		Log::info("Total GSEA summary files found: ".count($summary_files));
		if (count($summary_files) == 0) {
			$merge_cmd = app_path()."/scripts/backend/mergeGSEA.sh ".storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/analysis/expression";
			popen($merge_cmd . " > /dev/null &", "r");  
			$process = new Process(["whoami"]);
			$process->run();
			Log::info($merge_cmd);
			Log::info($process -> getOutput());
		}

		$des= array();
		$de_summary_file = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/analysis/expression/DE/de_summary.txt";
		$de_summary = $this->fileToTable($de_summary_file);
		$de_fs = glob(storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/analysis/expression/DE/*.txt");
		$de_files = array();
		foreach ($de_fs as $de_f) {
			$de_file = basename($de_f, ".txt");
			if ($de_file != "de_summary")
				$de_files[] = $de_file;
		}

		$gsea_htmls = array();
		foreach ($types as $type) {
			$htmls = glob(storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/analysis/expression/*${type}*gmt/*/index.html");
			$groups = array();
			foreach ($htmls as $html) {
				$dn = basename(dirname(dirname($html)));
				preg_match('/(.*)_weighted_.*/', $dn, $m );
				$group = $m[1];
				$groups[] = $group;
			}
			if (count($groups) > 0)			
				$gsea_htmls[$type] = $groups;
		}		
		$filter_definition = array();
		$filter_lists = UserGeneList::getDescriptions('all');
		foreach ($filter_lists as $list_name => $desc) {
			$filter_definition[$list_name] = $desc;
		}
		$filter_gene_list = UserGeneList::getGeneList("all");				
		foreach ($filter_gene_list as $list_name => $gene_list) {
			$filter_gene_list[$list_name] = $gene_list;
		}		
		return View::make('pages/viewExpressionAnalysisByCase', ['project_id' => $project_id, 'patient_id' => $patient_id, 'case_id' => $case_id, 'filter_definition' => $filter_definition, 'filter_gene_list' => json_encode($filter_gene_list), 'files'=>$files, 'gsea_htmls' => $gsea_htmls, 'de_summary' => $de_summary, 'de_files' => $de_files]);
	}

	public function getCaseExpMatrixFile($patient_id, $case_id) {
		$case = VarCases::getCase($patient_id, $case_id);
		$path = $case->path;
		$matrix_file = storage_path()."/ProcessedResults/$path/$patient_id/$case_id/analysis/expression/expression.tpm.coding.tsv";
		Log::info("matrix_file: $matrix_file");
		return Response::download($matrix_file);
	}

	public function getExpressionMatrix($patient_id, $case_id) {
		$case = VarCases::getCase($patient_id, $case_id);
		$path = $case->path;
		$matrix_file = storage_path()."/ProcessedResults/$path/$patient_id/$case_id/analysis/expression/expression.tpm.coding.tsv";
		Log::info("matrix_file: $matrix_file");
		$cols = array();
		$data = array();
		$user_list_idx = 0;
		$junction_url = url("viewJunction/$patient_id/$case_id");
		if (file_exists($matrix_file)) {
			$content = file_get_contents($matrix_file);
			$lines = explode("\n", $content);			
			$user_filter_list = UserGeneList::getGeneList("all");
			foreach ($lines as $line) {
				$fields = explode("\t", $line);
				if (count($cols) == 0) {
					$fields[0] = "Gene";
					foreach ($fields as $field) {
						$sample_name = Sample::getSampleNameByID($field);
						if ($sample_name == "")
							$sample_name = $field;
						$cols[] = array("title" => $sample_name);
					}
					
					$user_list_idx = count($cols);
					/*
					foreach ($user_filter_list as $list_name => $gene_list)
						$cols[] = array("title" => $list_name);
					*/
				} else {
					$symbol = $fields[0];
					$row_data = array("<a target=_blank href='$junction_url/$symbol'>$symbol</a>");
					#$row_data = array($fields[0]);
					for ($i=1;$i<count($fields);$i++) {
						$value = round(log($fields[$i]+1,2),2);
						$sample_name = $cols[$i]["title"];
						$row_data[] = "<a href='#' onclick=\"showExp(this, '$symbol', '$sample_name')\">$value</a>";
					}
					/*
					foreach ($user_filter_list as $list_name => $gene_list) {
						$has_gene = '';
						if (array_key_exists($fields[0], $gene_list)) {
							$has_gene = 'Y';
						}
						$row_data[] = $has_gene;
					}
					*/
					if (count($cols)==count($row_data))					
						$data[] = $row_data;
				}				
			}

		}
		$json_data = json_encode(array("cols" => $cols, "data" => $data, "user_list_idx" => $user_list_idx ));
		return $json_data;
		
	}

	public function viewExpressionByCase($project_id, $patient_id, $case_id, $sample_id="null") {
		$filter_definition = array();
		$filter_gene_list = array();
		$filter_lists = UserGeneList::getDescriptions('all');
		$filter_gene_list = UserGeneList::getGeneList("all");				
		foreach ($filter_gene_list as $list_name => $gene_list) {
			$filter_gene_list[$list_name] = $gene_list;
		}
		foreach ($filter_lists as $list_name => $desc) {
			$filter_definition[$list_name] = $desc;
		}		
		return View::make('pages/viewCaseExpression', ['project_id' => $project_id, 'patient_id' => $patient_id, 'case_id' => $case_id, 'sample_id' => $sample_id, 'filter_definition' => $filter_definition, 'filter_gene_list' => json_encode($filter_gene_list)]);
	}

	public function getExpressionByCase($project_id, $patient_id, $case_id, $target_type="all", $sample_id="all", $include_link=true) {
		set_time_limit(240);
		ini_set('memory_limit', '2048M');
		/*
		$key = "exp.$patient_id.$case_id.$target_type.$sample_id";
		Cache::forget($key);
		if (Cache::has($key))
			return Cache::get($key);
		*/
		$time_start = microtime(true);
		list($rows, $samples, $target_type,$expression_type,$count_type) = Patient::getExpressionByCase($patient_id, $case_id, $target_type, $sample_id);
		Log::info($expression_type);
		$junction_url = url("viewJunction/$patient_id/$case_id");
		$has_junction = VarAnnotation::hasJunction($patient_id, $case_id);
		$gene_rows = Gene::getGenes(strtolower($expression_type));
		$gene_infos = array();
		foreach($gene_rows as $gene_row) {
			$gene_infos[$gene_row->symbol] = $gene_row;
			$gene_infos[$gene_row->gene] = $gene_row;
		}
		
		$target_types = array();
		$exp_data = array();
		$genes = array();
		foreach ($rows as $row) {
			if (substr($row->symbol, 0, 4)=="ENSG") {
				$gid = substr($row->symbol, 0, strpos($row->symbol, "."));
				#Log::info($gid);
				if (array_key_exists($gid, $gene_infos)) {
					$g = $gene_infos[$gid];
					$row->symbol = $g->symbol;					
				}				
			}
			if (array_key_exists($row->symbol, $gene_infos)) {
				$type = $gene_infos[$row->symbol]->type;
				if ($row->symbol == $row->gene)
					$row->gene = $gene_infos[$row->symbol]->gene;
				if ($type != "protein-coding")
					continue;		
			}
			$target_types[$row->target_type] = '';
			$gene = preg_replace('/(.*)\.(.*)/', '$1', $row->gene);
			$genes[$row->symbol][$row->target_type] = $gene;
			#if (isset($row->sample_id, $exp_data[$row->symbol]))
			#	$exp_data[$row->symbol][$row->sample_id] = array();
			$exp_data[$row->symbol][$row->sample_id][$row->target_type] = $row->value;
			
		}

		$get_gene_time = microtime(true) - $time_start;		
		Log::debug("execution time (getGenes): $get_gene_time seconds");

		$cnv_data = array();
		if ($case_id == "any")
			$cnv_rows = DB::select("select chromosome,case_id,sample_id,sample_name,gene,cnt from var_cnv_genes where patient_id='$patient_id' and gene is not null");
		else
			$cnv_rows = DB::select("select chromosome,case_id,sample_id,sample_name,gene,cnt from var_cnv_genes where patient_id='$patient_id' and case_id='$case_id' and gene is not null");
		foreach ($cnv_rows as $cnv_row) {
			$current_value = 0;
			try {
				if (isset($cnv_data[$cnv_row->sample_id][$cnv_row->chromosome][$cnv_row->gene])) {
					$current_value = $cnv_data[$cnv_row->sample_id][$cnv_row->chromosome][$cnv_row->gene];
					if ($cnv_row->cnt > $current_value)
						$cnv_data[$cnv_row->sample_id][$cnv_row->chromosome][$cnv_row->gene] = $cnv_row->cnt;
				} else
					$cnv_data[$cnv_row->sample_id][$cnv_row->chromosome][$cnv_row->gene] = $cnv_row->cnt;

			} catch (Exception $e) {
				$cnv_data[$cnv_row->sample_id][$cnv_row->chromosome][$cnv_row->gene] = $cnv_row->cnt;
			}
		}
		$sample_mappings = array();
		$sample_rows = Sample::getSamplesByPatientID($patient_id);
		foreach ($sample_rows as $sample_row) {
			if ($sample_row->rnaseq_sample != '' && isset($cnv_data[$sample_row->sample_id]))
				$sample_mappings[$sample_row->rnaseq_sample][$sample_row->sample_id] = $sample_row->sample_name;
		}
		$cols = array();
		$data = array();		
		$target_types = array_keys($target_types);
		arsort($target_types);
		
		$tpm_rank_file = storage_path()."/project_data/$project_id/expression.tpm.coding.rank.tsv";
		Log::info($tpm_rank_file);

		$tpm_ranks = array();
		if (file_exists($tpm_rank_file)) {
			$output = shell_exec("head -n 1 $tpm_rank_file");
			$output = chop($output);
			$rank_header = explode("\t", $output);
			$sample_idx = array_search($sample_id, $rank_header);
			Log::info($sample_idx);
			if ($sample_idx) {
				$cmd = "cut -f1,".($sample_idx+1)." $tpm_rank_file";
				Log::info($cmd);
				$output = shell_exec($cmd);
				$lines = explode("\n", $output);
				$test_count = 0;
				foreach($lines as $line) {
					$fields = explode("\t", $line);
					if (count($fields) == 2) {
						$tpm_ranks[$fields[0]] = $fields[1];
						$test_count++;
						if ($test_count < 10) {
							Log::info("$fields[0] $fields[1]");
							Log::info(count($fields));
						}
					}					

				}
			}
		}

		$has_refseq = false;
		foreach ($target_types as $tt) {
			if (strtolower($tt) == "refseq")
				$has_refseq = true;
		}
		
		if (!$has_refseq) {
			$cols[] = array("title" => "Symbol");
		}


		foreach ($target_types as $tt) {
			$target_type_text = strtoupper($tt);
			$cols[] = array("title" => "Gene-$target_type_text");

		}
		$type_idx = count($cols);
		//$cols[] = array("title" => "Type");		
		foreach ($samples as $sample_id => $sample_name) {
			foreach ($target_types as $target_type) {
				$target_type_text = strtoupper($target_type);				
				$cols[] = array("title" => "$sample_name-$target_type_text");
			}
			if (count($tpm_ranks) > 0)
				$cols[] = array("title" => "$sample_name-TPM-Rank");
			if (isset($sample_mappings[$sample_id])) {
				$dna_samples = $sample_mappings[$sample_id];
				foreach ($dna_samples as $dna_sample_id => $dna_sample_name) 
					$cols[] = array("title" => "CNV-$dna_sample_name");
			}
		}
		$user_list_idx = count($cols);
		Log::info($user_list_idx);
		$add_gene_list_columns = false;
		if ($add_gene_list_columns) {
			$user_filter_list = UserGeneList::getGeneList("all");				
			foreach ($user_filter_list as $list_name => $gene_list)
				$cols[] = array("title" => $list_name);
		}
		foreach ($exp_data as $symbol => $exp) {
			$row_data = array();
			if (!$has_refseq) {
				$row_data[] = ($has_junction && $include_link)? "<a target=_blank href='$junction_url/$symbol'>$symbol</a>" : $symbol;				
			}
			$non_na = true;
			foreach ($target_types as $target_type) {
				$gene_ids = $genes[$symbol];								
				//Log::info($target_type);
				//Log::info(json_encode($gene_ids));
				if (array_key_exists($target_type, $gene_ids)) {
					$gene = $gene_ids[$target_type];					
					$row_data[] = $gene;
				}
				else {
					$non_na = false;
					break;
				}
			}
			if (!$non_na)
				continue;
			$type = "";
			$chr = "";
			if (isset($gene_infos[$symbol])) {
				$type = $gene_infos[$symbol]->type;
				$chr = $gene_infos[$symbol]->chromosome;
				$gene_start = $gene_infos[$symbol]->start_pos;
				$gene_end = $gene_infos[$symbol]->end_pos;
			}
			else
				continue;
			//exclude non-coding to save loading time
			//$row_data[] = $type;
			if ($type != "protein-coding")
				continue;			
			foreach ($samples as $sample_id => $sample_name) {
				foreach ($target_types as $target_type) {
					if (isset($exp_data[$symbol][$sample_id][$target_type])) {
						$value = $exp_data[$symbol][$sample_id][$target_type];
						$gene = $gene_ids[$target_type];
						//$row_data[] = round($value, 2);
						$value_url = round($value, 2);
						if ($include_link && $count_type != "Feature Count")
							//$value_url = round($value, 2);
							$value_url = "<a id='$sample_id$gene' href='#' onclick=\"showExp(this, '$symbol', '$sample_name', '$target_type')\">".round($value, 2)."</a>";
						$row_data[] = $value_url;						
					}
					if (count($tpm_ranks) > 0) {
						$rank = "NA";
						if (array_key_exists($gene, $tpm_ranks))
							$rank = $tpm_ranks[$gene];
						$row_data[] = $rank;
					}
				}
				if (isset($sample_mappings[$sample_id])) {							
					$dna_samples = $sample_mappings[$sample_id];
#					Log::info($cnv_data);
					foreach ($dna_samples as $dna_sample_id => $dna_sample_name) {						
						$cnt = "NA";
						if (array_key_exists($dna_sample_id, $cnv_data))
							if (array_key_exists($chr, $cnv_data[$dna_sample_id]))
								if (array_key_exists($symbol, $cnv_data[$dna_sample_id][$chr]))
									$cnt = $cnv_data[$dna_sample_id][$chr][$symbol];
						$value_url = $cnt;
						if ($include_link)
							$value_url = "<a id='cnv_$sample_id$gene' href='#' onclick=\"showCNV(this, '$symbol', '$dna_sample_name')\">".$cnt."</a>";
						$row_data[] = $value_url;
					}
				}				 
			}
			if ($add_gene_list_columns) {
				foreach ($user_filter_list as $list_name => $gene_list) {
					$has_gene = '';
					if (array_key_exists($symbol, $gene_list)) {
						$has_gene = 'Y';
					}
					$row_data[] = $has_gene;
				}
			}
			if ($non_na)
				$data[] = $row_data;
		}

		$time = microtime(true) - $time_start;		
		Log::debug("execution time (getExpressionByCase): $time seconds");

		$json_data = json_encode(array("cols" => $cols, "data" => $data, "type_idx" => $type_idx, "user_list_idx" => $user_list_idx, "target_type" => $target_type,"expression_type" => $expression_type,"count_type"=>$count_type));
#		$json_file=fopen("../app/tests/getExpressionByCase_Test.json","w");
#		fwrite($json_file,$json_data);	
		//Cache::put($key, $json_data, 24*60);
		return $json_data;
	}

	public function downloadCaseExpression() {
		$patient_id = Request::get('patient_id');
		$project_id = Request::get('project_id');

		if (!User::hasPatient($patient_id)) {
			return View::make('pages/error', ['message' => 'Access denied!']);
		}
		$case_id = Request::get('case_id');
		$sample_id = Request::get('sample_id');
		$gene_list = Request::get('gene_list');
		$genes = explode(',', $gene_list);
		$gene_hash = array();

		Log::info("patient_id: $patient_id");
		Log::info("case_id: $case_id");
		
		foreach ($genes as $gene) {
			$gene_hash[$gene] = '';
		}
		

		$json_data = $this->getExpressionByCase($project_id, $patient_id, $case_id, "all", $sample_id, false);
		
		$data = json_decode($json_data);
		$cols = $data->cols;
		$data = $data->data;

		$headers = array();
		$output = "";
		foreach ($cols as $col) {
			$headers[] = $col->title; 
		}

		$content = implode("\t", $headers)."\n";
		foreach ($data as $row) {
			if (array_key_exists($row[0], $gene_hash))
				$content .= implode("\t", $row)."\n";
		}
		
		$filename = "Expression-$patient_id.$case_id.tsv";

		$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='.$filename);		
		return Response::make($content, 200, $headers);
	}

	public function getCases($project_id, $format="json") {
		$logged_user = User::getCurrentUser();
		if ($logged_user == null) {
			return "Please login first";
		}
		$cases = VarCases::getCases($project_id);
		foreach ($cases as $case) {
			if ($case->case_name==""){
					$case_label="None";
			}
			else
				$case_label=$case->case_name;
			if ($format == "json") {
				$case->case_name = "<a aria-label='".$case_label."' target=_blank href=".url("/viewPatient/$project_id/$case->patient_id/$case->case_name").">".$case->case_name."</a>";
				#$case->patient_id = ;
			}
		}
		$tbl_results = $this->getDataTableJson($cases);
		if ($format == "json")
			return json_encode($tbl_results);
		$project = Project::getProject($project_id);
		$filename = $project->name."_case.tsv";
		$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='.$filename);		
		$content = $this->dataTableToTSV($tbl_results["cols"], $tbl_results["data"]);
		return Response::make($content, 200, $headers);	
		
	}
	
	public function publishCase($patient_id, $case_id){
		$affected = VarCases::publish($patient_id, $case_id);
		if ($affected == 0)
			return "failed";
		return "ok";
	}
	/**
	 * View all patients.
	 *
	 * @return table view page
	 */
	public function viewBiomaterial($id) {
		$cols = $this->getColumnJson("biomaterial", array());
		$sample_cols = $this->getColumnJson("samples", Config::get('onco.sample_column_exclude'));
		return View::make('pages/viewTableMasterDetail', ['cols'=>$cols, 'col_hide'=> array(), 'filters'=> array(),'title'=>'Biomaterial', 'primary_key'=>$id, 'detail_cols' => $sample_cols, 'detail_url'=>url('/getSampleByBiomaterialID'), 'detail_pos'=>'south', 'detail_title'=>'Biomaterial Details', 'url'=>url('/getBiomaterial/'.$id)]);
		
	}

	/**
	 * View all patients.
	 *
	 * @return table view page
	 */
	public function viewSTR($id) {
		$cols = $this->getColumnJson("str", array());
		return View::make('pages/viewTable', ['cols'=>$cols, 'col_hide'=> array(), 'filters'=> array(),'title'=>'Biomaterial', 'primary_key'=>$id, 'url'=>url('/getSTR/'.$id)]);
		
	}

	public function viewGenotyping($search_text, $type="all", $source="all", $has_header = true) {
		return View::make('pages/viewGenotyping', ["search_text" => $search_text, "type" => $type, "source" => $source, "has_header" => $has_header]);
		
	}

	/*
	public function getSample($id) {		
		if ($id == 'all') {
			if (Config::get('onco.cache')) {
				if (Cache::has('samples')) {
					$samples = Cache::get('samples');
				} else {
					$samples = Sample::all();
					Cache::put('samples', $samples, Config::get('onco.cache.mins'));
				}
			}
			else {
				$samples = Sample::all();
			}
		}
		else {
			$samples = Sample::where('sample_id', '=', $id)->get();
		}
		$samples = $this->SamplePostprocessing($samples, 1);
		return $this->getDataTableAjax($samples->toArray(), Config::get('onco.sample_column_exclude'));
	}
	*/

	public function getPatients($project_id, $search_text="any", $patient_id_only = "false", $format="json") {
		$starttime = microtime(true);

		$logged_user = User::getCurrentUser();
		if ($logged_user == null && $format=="json") {
			return "Please login first";
		}
		$include_meta = ($format == "text");
		$patients = Patient::search($project_id, $search_text, ($patient_id_only == "true"), "null", $include_meta);
		$processed_data = array();
		$root_url = url('/');
		foreach ($patients as $patient) {
			//if ($patient->str > 0 )
			//	$patient->str = "<a target=_blank href=".url('/viewSTR/'.$patient->patient_id).">$patient->str</a>";
			if ($format == "json") {
				$patient->samples = "<img width=20 height=20 src='$root_url/images/details_open.png' alt='open sample details for ".$patient->patient_id."'></img>";
				$patient->cases = "<img width=20 height=20 src='$root_url/images/details_open.png' alt='open case details for ".$patient->patient_id."'></img>";
				//$patient->genotyping = "<a target=_blank href=".url('/viewGenotyping/'.$patient->patient_id."><img width=20 height=20 src=".url('images/info.png')."></img>");
				$patient->tree = "<a target=_blank href='$root_url/viewPatientTree/$patient->patient_id'><img width=20 height=20 src='$root_url/images/content-tree.png' alt='open tree for ".$patient->patient_id."'></img>";
				$patient->patient_id = "<a target=_blank href='$root_url/viewPatient/$project_id/$patient->patient_id'>$patient->patient_id</a>";
			}
		}
		$exclude_cols = Config::get('onco.patient_column_exclude');
		$exclude_cols[] = "mrn";
		$exclude_cols[] = "protocol_no";
		if ($format == "json") {			
			$exclude_cols[] = "survival_time";
			$exclude_cols[] = "survival_status";
			//if (!User::isProjectManager()) {
				
			//}
			$tbl_results = $this->getDataTableJson($patients, $exclude_cols);
			$tbl_results["hide_cols"] = Config::get('onco.patient_column_hide');
		} else {
			$exclude_cols[] = "samples";
			$exclude_cols[] = "cases";
			$tbl_results = $this->getDataTableJson($patients,$exclude_cols);
			if ($project_id == "any") {
				$filename = "all_meta.txt";
			} else {
				$project = Project::getProject($project_id);
				$filename = $project->id."_".$project->name.".meta.txt";
			}
			$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='.$filename);
			$content = $this->dataTableToTSV($tbl_results["cols"], $tbl_results["data"]);
			//return $content;
			return Response::make($content, 200, $headers);			

		}
		$time = microtime(true) - $starttime;
		Log::info("execution time (getPatients): $time seconds");	
		return json_encode($tbl_results);
	}

	public function getCaseDetails($project_id, $search_text, $case_id,$patient_id_only = "false") {
		$starttime = microtime(true);

		$logged_user = User::getCurrentUser();
		if ($logged_user == null) {
			return "Please login first";
		}
		$patients = Patient::search($project_id, $search_text, ($patient_id_only == "true"),$case_id);
		$patient_var = Sample::getSamplesByPatientID($search_text, $case_id);
		$processed_data = array();
		$root_url = url("/");
		foreach ($patients as $patient) {
			Log::info("PATIENT ID");
//			Log::info($patient->patient_id);
			//	$patient->str = "<a target=_blank href=".url('/viewSTR/'.$patient->patient_id).">$patient->str</a>";
			//$patient->genotyping = "<a target=_blank href=".url('/viewGenotyping/'.$patient->patient_id."><img width=20 height=20 src=".url('images/info.png')."></img>");

		}
		$exclude_cols = Config::get('onco.patient_column_exclude');
		$exclude_cols[] = "survival_time";
		$exclude_cols[] = "survival_status";
		//if (!User::isProjectManager()) {
			$exclude_cols[] = "mrn";
			$exclude_cols[] = "protocol_no";
		//}
		$tbl_results = $this->getDataTableJson($patients, $exclude_cols);
		$tbl_results["hide_cols"] = Config::get('onco.patient_column_hide');
		$time = microtime(true) - $starttime;
		Log::info("execution time (getPatients): $time seconds");	
		Log::info($tbl_results);
		return json_encode($tbl_results);
	}

	public function getPatientIDs($sid, $search_text) {
		//return Patient::search($sid, $search_text);
		$patients = Patient::search($sid, $search_text);
		$id_list = array();
		foreach ($patients as $patient) {
			$id_list[] = $patient->patient_id;			
		}
		return json_encode($id_list);
	}

	public function viewPatientExpression($patient_id, $case_id) {
		return View::make('pages/viewExpression', ['level'=>'patient','patient_id'=>$patient_id, 'case_id'=>$case_id, 'gene_list' => 'ALK MYCN']);
	}

	public function getPatientsJsonByPost() {
		$data = Request::all();
		$patient_list = $data["id_list"];
		$exp_types = $data["type_list"];
		$use_sample_name = $data["use_sample_name"];
		$case_name = $data["case_name"];		
		$unformatted_json = $this->getPatientsJson($patient_list, $case_name, $exp_types, $use_sample_name, "all", false);
		return $unformatted_json;		
	}

	public function getPatientsJsonByFCID() {
		$data = Request::all();
		$fcid_list = $data["fcid_list"];
		//$fcids = explode("\n", explode(" ", $fcid_list));		
		$use_sample_name = $data["use_sample_name"];
		$case_name = $data["case_name"];
		//$unformatted_json = json_encode($fcids);
		$unformatted_json = $this->getPatientsJson("all", $case_name, "all", $use_sample_name, $fcid_list, false);
		return $unformatted_json;		
	}

	public function getPatientsJsonByProject($project_name, $patient_list="all", $exp_types="all", $use_sample_name="n", $excluded_list="none") {
		$projects = Project::where("name", $project_name)->get();
		if (count($projects) > 0) {
			$json = $this->getPatientsJson($patient_list, "all", $exp_types, $use_sample_name, "all", "true", "all", $excluded_list, $projects[0]->id);
			return $json;
		}
		return "project $project_name not found!";
	}

	public function getCaseByLibrary($sample_name, $FCID) {
		$sample_id = $sample_name."_".$FCID;
		Log::info($sample_id);
		$rows = DB::table('sample_cases')->where('sample_id',$sample_id)->get();
		if (count($rows) == 0)
			return "no case";
		if (count($rows) > 1)
			return "multiple cases";
		$patient_id = $rows[0]->patient_id;
		$case_name = $rows[0]->case_name;
		if ($case_name == "")
			return "no case";
		print "$case_name\n$patient_id\n";
		$rows = DB::table('sample_cases')->where('patient_id',$patient_id)->where('case_name',$case_name)->get();
		foreach ($rows as $row) {
			print $row->sample_id."\n";
		}
	}

	public function getCaseBySampleID($sample_id) {
		$rows = DB::table('sample_cases')->where('sample_id',$sample_id)->get();
		$cases_str = "";
		$cases = array();
		if (count($rows) > 0) {
			foreach ($rows as $row)
				$cases["$row->patient_id"."="."$row->case_name"] = '';
			$cases_str = implode(",", array_keys($cases));
		}
		return $cases_str;
	}

	// if only one case, then all patients use that case, otherwise, patient list must match case list	
	public function getPatientsJson($patient_list, $case_list="all", $exp_types="all", $use_sample_name="n", $fcid_list="all", $do_format="true", $sample_name="all", $excluded_list="none", $project_id="all") {
		$excluded_clause = "";
		if ($excluded_list != "none") {
			$excluded_list = implode("','",explode(',', $excluded_list));
		 	$excluded_clause = " and s1.sample_id not in('$excluded_list')";
		}
		$fcids = preg_split( "/ |\n|,/", $fcid_list );
		$patients = explode(',', $patient_list);
		$custom_list = implode("','", $patients);
		$cases = explode(',', $case_list);
		$patient_cases = array();
		for ($i=0;$i<count($patients);$i++) {
			if (count($cases) == 1)
				$patient_cases[$patients[$i]] = $cases[0];
			else
				$patient_cases[$patients[$i]] = $cases[$i];

		}
		if (strtolower($exp_types) == "all") {
			$exp_type_list = "Exome','Panel','Whole Genome','RNAseq','Methylseq";
		} else
			$exp_type_list = implode("','",explode(',', $exp_types));		
		//$sql = "select s1.*, uuid from samples s1 left join sample_uuid s2 on s1.sample_id=s2.sample_id where patient_id in ('$custom_list')";
		$has_rnaseq = (strtolower($exp_types) == "all" || strrpos($exp_type_list, "RNAseq") !== FALSE);
		$patient_condition = "";		
		if ($patient_list != "all")
			$patient_condition = "s1.patient_id in ('$custom_list') and";
		if ($fcid_list == "all" && $sample_name == "all")
			$sql = "select * from samples s1 where $patient_condition exp_type in ('$exp_type_list') $excluded_clause";
		else {
			$sample_id_clause = "";
			if ($fcid_list=="all") {
				if ($sample_name != "all")
					$sample_id_clause .= "sample_name = '$sample_name'";

			} else {
				for ($i=0;$i<count($fcids);$i++) {
					if (trim($fcids[$i]) == "")
						continue;				
					if ($i != 0)
						$sample_id_clause .= " or ";
					if ($sample_name != "all")
						$sample_id_clause .= "sample_id = '$sample_name"."_".$fcids[$i]."'";
					else
						$sample_id_clause .= "sample_id like '%_$fcids[$i]'";
				}	
			}
				
			$sql = "select * from samples s1 where $patient_condition exp_type in ('$exp_type_list') and $sample_id_clause $excluded_clause";
		}
		if ($project_id != "all") {
			$sql = "select s1.* from samples s1, project_samples s2 where $patient_condition s1.exp_type in ('$exp_type_list') and s2.project_id = $project_id and s2.sample_id=s1.sample_id $excluded_clause";	
		}		
		$sample_names = array();
		if (strtolower($use_sample_name) == "y") {
			$all_samples = Sample::all();
			foreach ($all_samples as $s) {				
				$sample_names[$s->sample_id] = $s->sample_name;
			}
		}
		Log::info($sql);
		$samples = DB::select($sql);

		if ($custom_list == "all") {
			$ps = array();
			foreach($samples as $sample) 
				$ps[] = $sample->patient_id;
			$custom_list = implode("','", $ps);
		}
		$case_samples = array();
		if ($case_list != "all") {
			$rows = Sample::getSampleCasesByPatientList($custom_list, false);
			//Log::info(json_encode($rows));
			foreach ($rows as $row)
				$case_samples[$row->sample_id][$row->case_name] = '';
		}
		
		$subject_json = array();
		$sample_json = array();
		$sample_ref_json = array();
		$sample_RNASeq_json = array();
		$patient_rnaseq = array();
		$rnaseq_sample = array();
		$normal_sample = array();
		$sample_type = array();
		$library = array();
		$sample_captures_json = array();
		$sample_tissue_type_json = array();
		$sample_ref_json_modified = array();
		$tumor_samples = array();
		$normal_samples = array();
		$methylseq_json = array();
		$default_capture = Config::get('onco.sample_capture');
		$exome_capture = Config::get('onco.clinomics_exome_capture');
		$panel_capture = Config::get('onco.clinomics_panel_capture');
		foreach ($samples as $sample) {
			if (strtolower($sample->platform) != 'illumina')
				continue;
			$original_id = $sample->sample_id;
			if (strtolower($use_sample_name) == "y")
				$sample->sample_id = $sample->sample_alias;
			if ($fcid_list == "all") {
				$case_name = "all";
				if (array_key_exists($sample->patient_id, $patient_cases))				
					$case_name = $patient_cases[$sample->patient_id];				
				if ($case_name != "all") {
					if (!isset($case_samples[$original_id][$case_name]))
						continue;
				}
			}				
			$sample->tissue_type = str_replace("'", "", $sample->tissue_type);
			$sample_tissue_type_json[$sample->sample_id] = $sample->tissue_type;
			$sample_type[$sample->sample_id] = ucfirst($sample->tissue_cat);
			$library[$sample->sample_id][] = $original_id;			
			$capture = strtolower($sample->library_type);
			
			if (strtolower($sample->exp_type) == 'exome' || strtolower($sample->exp_type) == 'panel' || strtolower($sample->exp_type) == 'whole genome' || strtolower($sample->exp_type) == 'methylseq') {				
				if (strtolower($sample->exp_type) == 'methylseq')
					$methylseq_json[$sample->patient_id][] = $sample->sample_id;
				else
					$subject_json[$sample->patient_id][] = $sample->sample_id;				
				$sample_json[] = $sample->sample_id;
				if (strtolower($sample->tissue_cat) == 'tumor' || strtolower($sample->tissue_cat) == 'cell line' || strtolower($sample->tissue_cat) == 'xeno') {
					if (strtolower($sample->tissue_cat) != 'xeno')
						$sample_type[$sample->sample_id] = "Tumor";
					if ($sample->normal_sample != null) {
						$normal_sample_id = $sample->normal_sample;
						if ($use_sample_name && array_key_exists($normal_sample_id, $sample_names))
							$normal_sample_id = $sample_names[$normal_sample_id];
						$sample_ref_json[$sample->sample_id] = array($normal_sample_id);
					}
					if ($sample->rnaseq_sample != null && $has_rnaseq) {
						$rnaseq_sample_id = $sample->rnaseq_sample;
						if ($use_sample_name && array_key_exists($rnaseq_sample_id, $sample_names))
							$rnaseq_sample_id = $sample_names[$rnaseq_sample_id];
						$sample_RNASeq_json[$sample->sample_id] = array($rnaseq_sample_id);
					}
				}
				$sample_captures_json[$sample->sample_id] = $capture;
			}
			if (strtolower($sample->exp_type) == 'rnaseq') {
				$patient_rnaseq[$sample->patient_id][] = $sample->sample_id;				
				if (strpos($capture, "ribo") !== false)
					$capture = "ribozero";
				$sample_captures_json[$sample->sample_id] = $capture;
				$sample_type[$sample->sample_id] = "RNAseq";
			}
			

		}		

		$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename="exome.json"');
		$subject_json = (object) $subject_json;
		$sample_ref_json = (object) $sample_ref_json;
		$sample_captures_json = (object) $sample_captures_json;
		$sample_RNASeq_json = (object) $sample_RNASeq_json;
		$patient_rnaseq = (object) $patient_rnaseq;
		$sample_tissue_type_json = (object) $sample_tissue_type_json;
		$final_json = array("subject"=>$subject_json, "library" => $library, "sample_references"=>$sample_ref_json, "sample_type" => $sample_type, "sample_captures"=>$sample_captures_json, "sample_RNASeq"=>$sample_RNASeq_json, "RNASeq"=>$patient_rnaseq, "Diagnosis"=>$sample_tissue_type_json);
		if (count($methylseq_json) > 0)
			$final_json["methylseq"] = $methylseq_json;
		$unformatted_json = json_encode($final_json, JSON_UNESCAPED_SLASHES);
		if (strtolower($do_format) == "true") {
			$cmd = "echo '$unformatted_json' | ".app_path()."/bin/node/bin/node ".app_path()."/bin/json";
			$formatted_json = shell_exec($cmd);
			if ($formatted_json != "")
				return $formatted_json;
		}
		return $unformatted_json;
		//return Response::make($formatted_json, 200, $headers);
	}
public function getPatientsJsonV2($patient_list, $case_list="all", $exp_types="all", $use_sample_name="n", $fcid_list="all", $do_format="true", $sample_name="all", $excluded_list="none", $project_id="all") {
		$excluded_clause = "";
		$sample_id_clause="";
		if ($excluded_list != "none") {
			$excluded_list = implode("','",explode(',', $excluded_list));
		 	$excluded_clause = " and s1.sample_id not in('$excluded_list')";
		}
		$fcids = preg_split( "/ |\n|,/", $fcid_list );
		$samples = preg_split( "/ |\n|,/", $sample_name );
		$patients = explode(',', $patient_list);
		$custom_list = implode("','", $patients);
		$cases = explode(',', $case_list);
		$patient_cases = array();
		for ($i=0;$i<count($patients);$i++) {
			if (count($cases) == 1)
				$patient_cases[$patients[$i]] = $cases[0];
			else
				$patient_cases[$patients[$i]] = $cases[$i];

		}
		if (strtolower($exp_types) == "all") {
			$exp_type_list = "Exome','Panel','Whole Genome','RNAseq','Methylseq";
		} else
			$exp_type_list = implode("','",explode(',', $exp_types));		
		//$sql = "select s1.*, uuid from samples s1 left join sample_uuid s2 on s1.sample_id=s2.sample_id where patient_id in ('$custom_list')";
		$has_rnaseq = (strtolower($exp_types) == "all" || strrpos($exp_type_list, "RNAseq") !== FALSE);
		$patient_condition = "";		
		if ($patient_list != "all")
			$patient_condition = "s1.patient_id in ('$custom_list') and";
		if ($fcid_list == "all" && $sample_name == "all")
			$sql = "select * from samples s1 where $patient_condition exp_type in ('$exp_type_list') $excluded_clause";
		else {
			$sample_id_clause = "";
			if ($fcid_list=="all") {
				if ($sample_name != "all")
					$sample_id_clause .= "sample_name = '$sample_name'";

			} else {
				for ($i=0;$i<count($fcids);$i++) {
					if (trim($fcids[$i]) == "")
						continue;				
					if ($i != 0)
						$sample_id_clause .= " or ";
					if ($sample_name != "all"){
						if(sizeof($samples)>1)
							$sample_id_clause .= "sample_id = '$samples[$i]"."_".$fcids[$i]."'";
						else
							$sample_id_clause .= "sample_id = '$sample_name"."_".$fcids[$i]."'";
					}
					else
						$sample_id_clause .= "sample_id like '%_$fcids[$i]'";
				}	
			}
				
			$sql = "select * from samples s1 where $patient_condition exp_type in ('$exp_type_list') and ($sample_id_clause) $excluded_clause";
			Log::info($sql);
		#	$sql="select * from samples s1 where  exp_type in ('Exome','Panel','Whole Genome','RNAseq','Methylseq') and (sample_id = 'RMS2163_P_H3TJWAFXX' or sample_id = 'RMS2163_T_HKY3VBGX5')";
		}
		if ($project_id != "all") {
			$sql = "select s1.* from samples s1, project_samples s2 where $patient_condition s1.exp_type in ('$exp_type_list') and s2.project_id = $project_id and s2.sample_id=s1.sample_id $excluded_clause";	
		}	
		$sample_names = array();
		if (strtolower($use_sample_name) == "y") {
			$all_samples = Sample::all();
			foreach ($all_samples as $s) {				
				$sample_names[$s->sample_id] = $s->sample_name;
			}
		}
		#Log::info($sql);
		$samples = DB::select($sql);

		if ($custom_list == "all") {
			$ps = array();
			foreach($samples as $sample) 
				$ps[] = $sample->patient_id;
			$custom_list = implode("','", $ps);
		}
		$case_samples = array();
		if ($case_list != "all") {
			$rows = Sample::getSampleCasesByPatientList($custom_list, false);
			//Log::info(json_encode($rows));
			foreach ($rows as $row)
				$case_samples[$row->sample_id][$row->case_name] = '';
		}
		
		$subject_json = array();
		$sample_json = array();
		$sample_ref_json = array();
		$sample_RNASeq_json = array();
		$patient_rnaseq = array();
		$rnaseq_sample = array();
		$normal_sample = array();
		$sample_type = array();
		$library = array();
		$sample_captures_json = array();
		$sample_tissue_type_json = array();
		$sample_ref_json_modified = array();
		$tumor_samples = array();
		$normal_samples = array();
		$methylseq_json = array();
		$default_capture = Config::get('onco.sample_capture');
		$exome_capture = Config::get('onco.clinomics_exome_capture');
		$panel_capture = Config::get('onco.clinomics_panel_capture');
		foreach ($samples as $sample) {
			if (strtolower($sample->platform) != 'illumina')
				continue;
			$original_id = $sample->sample_id;
			if (strtolower($use_sample_name) == "y")
				$sample->sample_id = $sample->alias;
			if ($fcid_list == "all") {
				$case_name = "all";
				if (array_key_exists($sample->patient_id, $patient_cases))				
					$case_name = $patient_cases[$sample->patient_id];				
				if ($case_name != "all") {
					if (!isset($case_samples[$original_id][$case_name]))
						continue;
				}
			}				
			$sample->tissue_type = str_replace("'", "", $sample->tissue_type);
			$sample_tissue_type_json[$sample->sample_id] = $sample->tissue_type;
			$sample_type[$sample->sample_id] = ucfirst($sample->tissue_cat);
			$library[$sample->sample_id][] = $original_id;			
			$capture = strtolower($sample->library_type);
			
			if (strtolower($sample->exp_type) == 'exome' || strtolower($sample->exp_type) == 'panel' || strtolower($sample->exp_type) == 'whole genome' || strtolower($sample->exp_type) == 'methylseq') {				
				if (strtolower($sample->exp_type) == 'methylseq')
					$methylseq_json[$sample->patient_id][] = $sample->sample_id;
				else
					$subject_json[$sample->patient_id][] = $sample->sample_id;				
				$sample_json[] = $sample->sample_id;
				if (strtolower($sample->tissue_cat) == 'tumor' || strtolower($sample->tissue_cat) == 'cell line' ) {
					$sample_type[$sample->sample_id] = "Tumor";
					if ($sample->normal_sample != null) {
						$normal_sample_id = $sample->normal_sample;
						if ($use_sample_name && array_key_exists($normal_sample_id, $sample_names))
							$normal_sample_id = $sample_names[$normal_sample_id];
						$sample_ref_json[$sample->sample_id] = array($normal_sample_id);
					}
					if ($sample->rnaseq_sample != null && $has_rnaseq) {
						$rnaseq_sample_id = $sample->rnaseq_sample;
						if ($use_sample_name && array_key_exists($rnaseq_sample_id, $sample_names))
							$rnaseq_sample_id = $sample_names[$rnaseq_sample_id];
						$sample_RNASeq_json[$sample->sample_id] = array($rnaseq_sample_id);
					}
				}
				$sample_captures_json[$sample->sample_id] = $capture;
			}
			if (strtolower($sample->exp_type) == 'rnaseq') {
				$patient_rnaseq[$sample->patient_id][] = $sample->sample_id;				
				if (strpos($capture, "ribo") !== false)
					$capture = "ribozero";
				$sample_captures_json[$sample->sample_id] = $capture;
				$sample_type[$sample->sample_id] = "RNAseq";
			}
			

		}		

		$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename="exome.json"');
		$subject_json = (object) $subject_json;
		$sample_ref_json = (object) $sample_ref_json;
		$sample_captures_json = (object) $sample_captures_json;
		$sample_RNASeq_json = (object) $sample_RNASeq_json;
		$patient_rnaseq = (object) $patient_rnaseq;
		$sample_tissue_type_json = (object) $sample_tissue_type_json;
		$final_json = array("subject"=>$subject_json, "library" => $library, "sample_references"=>$sample_ref_json, "sample_type" => $sample_type, "sample_captures"=>$sample_captures_json, "sample_RNASeq"=>$sample_RNASeq_json, "RNASeq"=>$patient_rnaseq, "Diagnosis"=>$sample_tissue_type_json);
		if (count($methylseq_json) > 0)
			$final_json["methylseq"] = $methylseq_json;
		$unformatted_json = json_encode($final_json, JSON_UNESCAPED_SLASHES);
		if (strtolower($do_format) == "true") {
			$cmd = "echo '$unformatted_json' | ".app_path()."/bin/node/bin/node ".app_path()."/bin/json";
			$formatted_json = shell_exec($cmd);
			if ($formatted_json != "")
				return $formatted_json;
		}
		return $unformatted_json;
		//return Response::make($formatted_json, 200, $headers);
	}
	/**
	 * View all patients.
	 *
	 * @return table view page
	 */
	public function viewPatientTree($id) {
		$samples = DB::select("select * from samples where patient_id = '$id'");
		$rows = array();
		$cols = array();
		foreach ($samples as $sample) {
			$rows[$sample->source_biomaterial_id][$sample->exp_type][$sample->sample_id]='';
			$cols[$sample->exp_type]='';
		}
		$json_data = array();
		$json_cols = array();
		$json_cols[] = array("title" => "Source Biomaterial ID");
		foreach ($cols as $col_key=>$col_value) {
			$json_cols[] = array("title" => $col_key);
		}
		foreach ($rows as $src_bioid=>$row_value) {
			$json_row = array();
			$json_row[] = "$src_bioid";
			foreach ($cols as $col_key=>$col_value) {
				if (isset($row_value[$col_key])){
					$smp = implode("\t", array_keys($row_value[$col_key]));
					$json_row[] = $smp;
				}
				else
					$json_row[] = '';
			}
			$json_data[] = $json_row;
		}
		return View::make('pages/viewPatientTree', ['data_source'=>url('/getPatientTreeJson/'.$id), 'data'=>$json_data, 'cols' => $json_cols]);
	}

	/**
	 * View all patients.
	 *
	 * @return table view page
	 */
	public function getPatientTreeJson($id) {
		$samples = DB::select("select * from samples where patient_id = '$id'");
		$tree_data = array();
		foreach ($samples as $sample) {
			if ($sample->source_biomaterial_id == null || $sample->source_biomaterial_id == '')
				$sample->source_biomaterial_id == 'NA';
			$tree_data[$id][$sample->source_biomaterial_id][$sample->biomaterial_id][$sample->exp_type][$sample->sample_name] = '';
		}
		return json_encode($this->getSubTree($id, $tree_data[$id]));
	}

	public function getSubTree($node_name, $arr) {
		$node = new stdClass();
		$node->name = "$node_name";
		if (is_array($arr)) {
			$node->children = array();
			foreach ($arr as $key=>$value) {
				$node->children[] = $this->getSubTree($key, $value);
			}
		}
		else
			$node->size = 200;		
		return $node;
	}


	/**
	 * View all samples.
	 *
	 * @return table view page
	 */
	/*
	public function getBiomaterial($id) {
		if ($id == 'all') {
			if (Config::get('onco.cache')) {
				if (Cache::has('biomaterials')) {
					$bio_mas = Cache::get('biomaterials');
				} else {
					$bio_mas = Biomaterial::all();
					Cache::put('biomaterials', $bio_mas, Config::get('onco.cache.mins'));
				}
			}
			else {
				$bio_mas = Biomaterial::all();
			}
		}
		else {
			$bio_mas = Biomaterial::where('biomaterial_id', '=', $id)->get();
		}		
				
		foreach ($bio_mas as $bio_ma){
			$bio_ma->biomaterial_id = "<a href=javascript:getDetails('".$bio_ma->biomaterial_id."');>".$bio_ma->biomaterial_id."</a>";
		}
		return $this->getDataTableAjax($bio_mas->toArray(), array());
	}
	*/
	
	/**
	 * View all samples.
	 *
	 * @return table view page
	 */
	public function getSTR($id) {
		if ($id == 'all') {
			$genos = STR::all();
		}
		else {
			$genos = STR::where('patient_id', '=', $id)->get();
		}		
				
		foreach ($genos as $geno){
			//$geno->patient_id = '<a href='.url('/viewPatients/'.$geno->patient_id).'>'.$geno->patient_id.'</a>';
			$geno->biomaterial_id = '<a href='.url('/viewBiomaterial/'.$geno->biomaterial_id).'>'.$geno->biomaterial_id.'</a>';
		}
		$tbl_results = $this->getDataTableJson($genos);
		return json_encode($tbl_results);
		//return $this->getDataTableAjax($genos->toArray(), array());
	}

	public function getGenotyping_fromDB($id) {
		if ($id == 'all') {
			$rows = Genotyping::all();
		}
		else {
			$rows = Genotyping::where('sample1', 'like', "%$id%")->get();
		}		
		$genos = array();
		$cols = array();
		$sample_ids = array();
		foreach ($rows as $row){
			$genos[$row->sample1][$row->sample2] = $row->percent_match;
			$sample_ids[$row->sample2] = '';

		}
		$cols = array_keys($genos);
		$sample_ids = array_keys($sample_ids);
		$data = array();
		foreach ($sample_ids as $sample_id){
			$data_row = array($sample_id);
			foreach ($cols as $col)
				$data_row[] = $genos[$col][$sample_id];
			$data[] = $data_row;
		}
		return json_encode($data);
	}

	public function getGenotyping($search_text, $type="all", $source="all") {		
		$patient_ids = explode(",", $search_text);
		if ($source == "all")
			$samples = Sample::getSamplesByPatients($patient_ids);
		if ($source == "project")
			$samples = Project::getSamples($search_text);
		return Sample::getGenotyping($samples, $type);		
	}	

	public function getPatientGenotyping($patient_id, $case_id, $project_id="any") {
		if (!User::hasPatient($patient_id)) {
			return View::make('pages/error', ['message' => 'Access denied!']);
		}
		
		$rows = DB::select("select matrix from patient_genotyping where patient_id='$patient_id'");
		if (count($rows) > 0) {
			list($header, $data) = Utility::readStringWithHeader($rows[0]->matrix);
		} else {
			$path = VarCases::getPath($patient_id, $case_id);
			if ($path == null) {
				return View::make('pages/error', ['message' => 'No case found!']);
			}
			$geno_file = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/qc/$patient_id.genotyping.txt";
			if (!file_exists($geno_file))
				return null;
			list($header, $data) = Utility::readFileWithHeader($geno_file);
		}
		
		$cols = array();
		foreach ($header as $col)
			$cols[] = array("title" => $col);
#		$json_file=fopen("../app/tests/getqc_PatientGenotyping_Test.json","w");
#		fwrite($json_file,json_encode(array("cols"=>$cols, "data" => $data)));
		return json_encode(array("cols"=>$cols, "data" => $data));
	}	

	public function getTierCount($project_id,$patient_id,$case_name="any") {
		return json_encode(Patient::getTierCounts2($project_id, $patient_id, $case_name));
	}

	public function getTierCountScott($project_id,$patient_id,$case_name="any") {
		$counts=array();
		$types=array();
		$tiers=array();
		$g_index=0;
		$s_index=0;
		$r_index=0;
		$o_index=0;

		$tier0=array();
		$tier1=array();
		$tier2=array();
		$tier3=array();
		$tier4=array();

		$has_germline=false;
		$has_somatic=false;
		$has_rnaseq=false;
		$has_other=false;

		if($case_name=="any"){
			$cases=array();
			$samples = Patient::getCasesByPatientID($project_id, $patient_id);
			
			foreach ($samples as $sample) {
				array_push($cases,$sample->case_name);

			}
		}
		else
			$cases=array($case_name);
		foreach ($cases as $case_name) {
			$germlines=Patient::getTierCounts($patient_id,$case_name,"germline");
			// echo "$case_id";
			Log::info($germlines);
			$somatics=Patient::getTierCounts($patient_id,$case_name,"somatic");
			$rnaseqs=Patient::getTierCounts($patient_id,$case_name,"rnaseq");
			$other_variants=Patient::getTierCounts($patient_id,$case_name,"variants");
			// echo "<hr />";
			if(count($germlines)>0 &&$has_germline!=true){
				
				array_push($tier0,0);
				array_push($tier1,0);
				array_push($tier2,0);
				array_push($tier3,0);
				array_push($tier4,0);
				
				$gl=array(0,0,0,0,0);
				array_push($types, "germline");
				$has_germline=true;

				$s_index=$s_index+1;
				$r_index=$s_index+1;
			}
			if(count($somatics)>0 &&$has_somatic!=true){
				$sl=array(0,0,0,0,0);
				array_push($tier0,0);
				array_push($tier1,0);
				array_push($tier2,0);
				array_push($tier3,0);
				array_push($tier4,0);
				array_push($types, "somatic");
				$has_somatic=true;

				$r_index=$s_index+1;
			}
			if(count($rnaseqs)>0 &&$has_rnaseq!=true){
				$rl=array(0,0,0,0,0);
				array_push($tier0,0);
				array_push($tier1,0);
				array_push($tier2,0);
				array_push($tier3,0);
				array_push($tier4,0);
				array_push($types, "rnaseq");
				$has_rnaseq=true;
				$o_index=$r_index+1;
			}
			

			foreach ($germlines as $germline){
				if($germline->{'germline_level'}==null){
					$tier=0;
				}
				else{
					$parts=preg_split('/\s+/', $germline->{'germline_level'});
					$tier=floor($parts[1]);

				}
				if ($tier==0)
					$tier0[$g_index]=$tier0[$g_index]+$germline->cnt;
				if ($tier==1)
					$tier1[$g_index]=$tier1[$g_index]+$germline->cnt;
				if ($tier==2){
					Log::info("pushing");
					$tier2[$g_index]=$tier2[$g_index]+$germline->cnt;
				}
				if ($tier==3)
					$tier3[$g_index]=$tier3[$g_index]+$germline->cnt;
				if ($tier==4)
					$tier4[$g_index]=$tier4[$g_index]+$germline->cnt;
				
			}
			foreach ($somatics as $somatic){
				if($somatic->{'somatic_level'}==null){
					$tier=0;
				}
				else{
					$parts=preg_split('/\s+/', $somatic->{'somatic_level'});
					$tier=floor($parts[1]);

				}
				if ($tier==0)
					$tier0[$s_index]=$tier0[$s_index]+$somatic->cnt;
				if ($tier==$s_index)
					$tier1[$s_index]=$tier1[$s_index]+$somatic->cnt;
				if ($tier==2)
					$tier2[$s_index]=$tier2[$s_index]+$somatic->cnt;
				if ($tier==3)
					$tier3[$s_index]=$tier3[$s_index]+$somatic->cnt;
				if ($tier==4)
					$tier4[$s_index]=$tier4[$s_index]+$somatic->cnt;
				
			}
			foreach ($rnaseqs as $rnaseq){
				if($rnaseq->{'somatic_level'}==null){
					$tier=0;
				}
				else{
					$parts=preg_split('/\s+/', $rnaseq->{'somatic_level'});
					$tier=floor($parts[1]);

				}
				if ($tier==0)
					$tier0[$r_index]=$tier0[$r_index]+$rnaseq->cnt;
				if ($tier==1)
					$tier1[$r_index]=$tier1[$r_index]+$rnaseq->cnt;
				if ($tier==2)
					$tier2[$r_index]=$tier2[$r_index]+$rnaseq->cnt;
				if ($tier==3)
					$tier3[$r_index]=$tier3[$r_index]+$rnaseq->cnt;
				if ($tier==4)
					$tier4[$r_index]=$tier4[$r_index]+$rnaseq->cnt;
				
			}
			#Added 20190729 for panel data (RMS)
			Log::info("count of other variants:". count($other_variants));
			if(count($other_variants)>0 && !$has_rnaseq && !$has_somatic && !$has_germline){
				$rl=array(0,0,0,0,0);
				array_push($tier0,0);
				array_push($tier1,0);
				array_push($tier2,0);
				array_push($tier3,0);
				array_push($tier4,0);
				//if ($case_id !== 'any')
				array_push($types, "variants");

				foreach ($other_variants as $other){
					if($other->{'somatic_level'}==null){
						$tier=0;
					}
					else{
						$parts=preg_split('/\s+/', $other->{'somatic_level'});
						$tier=floor($parts[1]);

					}
					if ($tier==0)
						$tier0[$o_index]=$tier0[$o_index]+$other->cnt;
					if ($tier==1)
						$tier1[$o_index]=$tier1[$o_index]+$other->cnt;
					if ($tier==2)
						$tier2[$o_index]=$tier2[$o_index]+$other->cnt;
					if ($tier==3)
						$tier3[$o_index]=$tier3[$o_index]+$other->cnt;
					if ($tier==4)
						$tier4[$o_index]=$tier4[$o_index]+$other->cnt;
					
				}
				
			}
			if (count($types)==0)
				array_push($types, "variants");

			
		}
		#if($tier0[0]==0 && $tier1[0]==0 && $tier2[0]==0 && $tier3[0]==0 && $tier4[0]==0){
		#	unset($tier0[0]);
		#	unset($tier1[0]);
		#	unset($tier2[0]);
		#	unset($tier3[0]);
		#	unset($tier4[0]);
		#}
		if (!$has_rnaseq && !$has_somatic && !$has_germline){#This is only for variants
			Log::info('herernase=' . $has_rnaseq .";has_somatic=$has_somatic;has_germline=$has_germline");
			// Log::info($tier0);
			if (count($tier0)>0){
				array_push($counts,array($tier0[$o_index]));
				array_push($counts,array($tier1[$o_index]));
				array_push($counts,array($tier2[$o_index]));
				array_push($counts,array($tier3[$o_index]));
				array_push($counts,array($tier4[$o_index]));
			}else{
			}
		}else{
			array_push($counts,$tier0);
			array_push($counts,$tier1);
			array_push($counts,$tier2);
			array_push($counts,$tier3);
			array_push($counts,$tier4);
		}


		return json_encode(array("data" => $counts,"variants"=>$types,"names"=>$tiers)); 


	}
	public function getAvia_summary() {
		$avia_versions=Sample::getAvia_summary();
		$cols=array();
		$data=array();
		$cols[] = array("title" => "DB Name");
		$cols[] = array("title" => "Version");
		$cols[] = array("title" => "Last updated");
		$cols[] = array("title" => "Description");
		#Log::info($avia_versions);
		foreach ($avia_versions as $version) {
			$db=$version->dbname;
			$ver=$version->version;
			$lastupdated=$version->lastupdated;
			$description=$version->description;
			$data[]=array($db,$ver,$lastupdated,$description);
		}
		return json_encode(array("cols" => $cols, "data" => $data));

	}
	public function getpipeline_summary($patient_id,$case_id='any') {
		$data=array();
		$cols=array();
		$pipeline_version="NA";
		try{
			$path = VarCases::getCase($patient_id, $case_id);
			// $version = glob(storage_path()."/ProcessedResults/".$path->path."/$patient_id/$case_id/qc/".$patient_id.".config*.txt");
			$key_file= storage_path()."/data/pipleline_version.txt";##careful not to change this; this is how it's spelled on the server!
			$keys = file($key_file, FILE_IGNORE_NEW_LINES);
		#	Log::info($keys);
		#	Log::info("PATHS");
			$cmd = "ls -ltr " . storage_path()."/ProcessedResults/".$path->path."/$patient_id/$case_id/qc/".$patient_id.".config*.txt" ." | tail -1";
			$res = rtrim(`$cmd`);
			if (preg_match("/($patient_id.config.*txt)/",$res,$matches)){
				$latest_version=$matches[0];
			}else{
				$latest_version = "$patient_id.config.txt";
			}
			// $count=0;
			// $version_data=[];
			// foreach($version as $file){
			// 	$myarr[$file] = date ("F d Y H:i:s.", filemtime($file));
			// 	Log::info("$file = " . date ("F d Y H:i:s", filemtime($file)));
			// }
			// krsort($myarr);//Correct for sorting!
			// Log::info(($myarr));
			// $latest_version=key($myarr);
			Log::info('latest_version=' . $latest_version);
		#	$myfile = fopen($version, "r") or die("Unable to open file!");
			$file=	file_get_contents(storage_path()."/ProcessedResults/".$path->path."/$patient_id/$case_id/qc/$latest_version");
			$json_data = json_decode($file,true);
			Log::info("pipeline_version: ".$json_data['pipeline_version']);

			$cols[] = array("title" => "Tool");
			$cols[] = array("title" => "Version");
			foreach ($keys as $key){
		#		$version_data[$key]=$json_data[$key];
				try{
					$data[]=array($key,$json_data[$key]);
				}
				catch (\Exception $e) { 
							
				}
	#			$cols[] = array("title" => $key);

			}
		
			$pipeline_version=$json_data['pipeline_version'];
		}
		catch (\Exception $e) { 
							
			}
		$version_data['pipeline_version']=$pipeline_version;
		return json_encode(array("cols" => $cols, "data" => $data,"version_data"=>$version_data));

	#	fclose($myfile);

	}
	public function getSamplesByCaseName($patient_id,$case_name) {
		$case_condition = "";
		if ($case_name != "any" && $case_name != null)
			$case_condition = "and s2.case_name='$case_name'";
		$samples = DB::select("select * from samples s1 where exists(select * from sample_cases s2 where s1.sample_id=s2.sample_id and s2.patient_id='$patient_id' $case_condition)");
		$sample_details = DB::select("select * from sample_details s1 where exists(select * from sample_cases s2 where s1.sample_id=s2.sample_id and s2.patient_id='$patient_id' $case_condition)");
		$detail_cols = array();
		$detail_data = array();
		foreach ($sample_details as $sample_detail) {
				$detail_cols[$sample_detail->attr_name] = "";
				$detail_data[$sample_detail->sample_id][$sample_detail->attr_name] = $sample_detail->attr_value;
			
		}

		$detail_cols = array_keys($detail_cols);
		foreach($samples as $sample) {
			foreach($detail_cols as $detail_col) {
				if (array_key_exists($detail_col, $detail_data[$sample_detail->sample_id]))
					$sample->{$detail_col} = $detail_data[$sample_detail->sample_id][$detail_col];
				else
					$sample->{$detail_col} = "";

			}
		}

		//$tbl_results = $this->getDataTableJson($samples, Config::get('onco.sample_column_exclude'));
		$tbl_results = $this->getDataTableJson($samples, array());
		$tbl_results["show_cols"] = array(Lang::get("messages.sample_alias"),Lang::get("messages.material_type"),Lang::get("messages.exp_type"),Lang::get("messages.platform"), Lang::get("messages.library_type"), Lang::get("messages.tissue_cat"), Lang::get("messages.tissue_type"), "Test", "Source Biomaterial ID", "Surgical specimen ID", "Tumor Content");
		$tbl_results["type"] = "summary";
		return json_encode($tbl_results);

	}

	public function getSampleByPatientID($project_id, $patient_id,$case_name="any") {
		
		$samples = Sample::getSamplesByPatientID($patient_id,$case_name,$project_id);

		$sample_details = Sample::getSampleDetails($patient_id);

		$detail_cols = array();
		$detail_data = array();
		$status_column = "status";
		$has_status_column = false;
		$root_url = url("/");
		foreach ($sample_details as $sample_detail) {
				if (strtolower($sample_detail->attr_name) == $status_column) {
					$status_column = $sample_detail->attr_name;
					$has_status_column = true;
				}
				$detail_cols[$sample_detail->attr_name] = "";
				$detail_data[$sample_detail->sample_id][$sample_detail->attr_name] = $sample_detail->attr_value;
			
		}
		$filtered_samples = array();
		$detail_cols = array_keys($detail_cols);

		$new_samples = array();
		foreach($samples as $sample) {
			$new_sample = (object)[];
			if ($has_status_column) {				
				if (isset($detail_data[$sample->sample_id][$status_column])) {
					$value = $detail_data[$sample->sample_id][$status_column];
					$image_file = "circle_red.png";
					if ($detail_data[$sample->sample_id][$status_column] == "Completed")
						$image_file = "circle_green.png";
					if ($detail_data[$sample->sample_id][$status_column] == "Others")
						$image_file = "circle_yellow.png";
					$new_sample->{$status_column} = "<img src=$root_url/images/$image_file class='flag_tooltip' title='$value' width=20 height=20 ></img>";
				}
				else
					$new_sample->{$status_column} = "";
			}			

			#Log::info("SAMPLE ".$sample->sample_id);

			//$sample_case_list = $sample->case_name;
			//$sample_cases = explode(',', $sample_case_list);
			
			foreach ($detail_cols as $detail_col) {

#				Log::info("detail_col: $detail_col");
				if ($detail_col == $status_column)
					continue;
				if (isset($detail_data[$sample->sample_id][$detail_col]))
					$sample->{$detail_col} = $detail_data[$sample->sample_id][$detail_col];
				else
					$sample->{$detail_col} = '';
			}

			foreach($sample as $key => $value) {
				$new_sample->{$key} = $value;
			}
			$new_samples[] = $new_sample;
			/*
			$filtered_cases = array();
			if (!User::accessAll() && $project_id == "all") {
				foreach ($sample_cases as $sample_case) {
					if (array_key_exists($sample_case, $case_list))
						$filtered_cases[] = $sample_case;				
				}
				if (count($filtered_cases) > 0 && (isset($sample_list[$sample->sample_id]))) {
					$sample->case_name = implode(',', $filtered_cases);
					$filtered_samples[] = $sample->toArray();
				}
			} else {
				if(isset($sample_list[$sample->sample_id])){
					$filtered_samples[] = $sample->toArray();
				}
			}
			*/			
		}
		$show_cols=array("Sample alias", "DNA/RNA", "Experiment Type", "Library Type","Tissue Category", "Matched normal", "Matched RNA-seq lib" , "FFPE or Fresh Frozen", "Lib prep batch Date", "QPCR Date", "Run Start Date", "Run Finish date","status");
		$show_cols = array(Lang::get("messages.sample_alias"),Lang::get("messages.material_type"),Lang::get("messages.exp_type"),Lang::get("messages.platform"), Lang::get("messages.library_type"), Lang::get("messages.tissue_cat"), Lang::get("messages.tissue_type"), "Test", "Source Biomaterial ID", "Surgical specimen ID", "Tumor Content", "Status");
		
		$tbl_results =$this->getDataTableJson($new_samples, Config::get('onco.sample_column_exclude'));
		$tbl_results["show_cols"] = $show_cols;
		$tbl_results["type"] = "summary";
				
		return json_encode($tbl_results);
		#return $this->getDataTableJson($filtered_samples, Config::get('onco.sample_column_exclude'));			
	}
	

	public function getCasesByPatientID($project_id, $patient_id) {		
		$cases = Patient::getCasesByPatientID($project_id, $patient_id);
		foreach ($cases as $case) {
			$case->case_name = '<a  aria-label="'.$case->case_name.'" target=_blank href='.url('/viewPatient/'.$project_id.'/'.$patient_id.'/'.$case->case_name.'>'.$case->case_name.'</a>');
		}
		return $this->getDataTableJson($cases, array("case_id"));
	}

	/*
	public function getPatientDetails($id) {
		
		$patient_details = DB::select("select distinct d.* from patient_details d where patient_id='$id'");
		$data = array();
		foreach ($patient_details as $patient_detail) {
			$data_row = array();
			if (User::isCurrentUserEditor()) {
				$data_row[] = "<a href=\"javascript:showEditForm('".$patient_detail->patient_id."',1,'".$patient_detail->attr_name."','".$patient_detail->attr_value."');\">Edit</a>";
				$data_row[] = "<a href=\"javascript:deleteDetail('".$patient_detail->patient_id."','".$patient_detail->attr_name."');\">Delete</a>";
			}
			$data_row[] = $patient_detail->attr_name;
			$data_row[] = $patient_detail->attr_value;
			$data[] = $data_row;
		}
		$columns = array();
		if (User::isCurrentUserEditor()) {
			$columns[] = array("title"=>"Edit");
			$columns[] = array("title"=>"Delete");
		}
		$columns[] = array("title"=>"Key");
		$columns[] = array("title"=>"Value");		
		return json_encode(array("column"=>$columns, "data"=>$data));
	}


	public function getSampleByBiomaterialID($id) {
		$samples = Sample::where('biomaterial_id', '=', $id);
		$samples = $this->SamplePostprocessing($samples->get(), 0);
		return $this->getDataTableAjax($samples->toArray(), Config::get('onco.sample_column_exclude'));
	}

	public function getSampleDetails($id) {
		$sample_details = SampleDetail::where('sample_id', '=', $id);
		return $this->getDataTableAjax($sample_details->get()->toArray(), array('sample_id'));
	}

	public function updatePatientDetail($patient_id, $old_key, $key, $value) {
		PatientDetail::updateData($patient_id, $old_key, $key, $value);
	}

	public function addPatientDetail($patient_id, $key, $value) {
		PatientDetail::addData($patient_id, $key, $value);
	}

	public function deletePatientDetail($patient_id, $key) {
		PatientDetail::deleteData($patient_id, $key);
	}
	*/
	public function SamplePostprocessing($samples, $detail_link) {
		foreach ($samples as $sample){
			$sample->sample_id = trim($sample->sample_id);
			$sample->patient_id = '<a target=_blank href='.url('/viewPatients/null/'.$sample->patient_id).'/1>'.$sample->patient_id.'</a>';
			$sample->biomaterial_id = '<a target=_blank href='.url('/viewBiomaterial/'.$sample->biomaterial_id).'>'.$sample->biomaterial_id.'</a>';

		}
		return $samples;
	}
	
	public function getExpSamplesFromVarSamples($sample_list) {
		$samples = Sample::getExpSamplesFromVarSamples($sample_list);
		$sample_ids = array();
		foreach ($samples as $sample) {
			$sample_ids[] = $sample->sample_id;
		}
		return json_encode($sample_ids);
	}

	public function getChIPseqSampleSheet($sample_id) {
		$samples = Sample::where("sample_id", $sample_id)->get();
		if (count($samples) == 0)
			return "no sample found";
		$sample = $samples[0];
		$title = "SampleFiles\tPairedInput\tSampleRef\tLibraryType\tLibrarySize\tReadLength\tPairedExpression\tChIPtarget\tProject\tEnhancePipe\tPeakCalling";
		$sample_folder = "Sample_".$sample_id;
		$patient_id = $sample->patient_id;
		$paired_input = isset($sample->normal_sample)? "Sample_".$sample->normal_sample : "";
		$chip_target = $sample->library_type;
		$projects = explode(',', $sample->source);
		$project = $projects[0];
		$enhance_pipe = $sample->enhancepipe;
		$peakcalling = $sample->peakcalling;		
		$rnaseq = $sample->rnaseq_sample;
		$ref = $sample->reference;
		$case_id = "";
		$path = "";
		if ($ref == "hg19" && $rnaseq != "") {
			$path = Config::get('onco.chipseq_rnaseq_path');
			$content = "$patient_id\n$sample_id\n";
			$case_id = Sample::getCaseID($patient_id, $rnaseq);			
		}
		if ($case_id != "")
			$rnaseq = "$path$patient_id/$case_id/$rnaseq/";
		$headers = array('Content-Type' => 'text/txt');
		$content = "$title\n$sample_folder\t$paired_input\t$ref\t\t\t\t$rnaseq\t$chip_target\t$project\t$enhance_pipe\t$peakcalling";
		return Response::make($content, 200, $headers);		
	}

	public function runPipeline() {
		try {
			$user = User::getCurrentUser();
			if ($user == null) {
				return json_encode(array("code"=>"no_user","desc"=>""));
			}			
			$json_data = Request::all();
			$patient_id = $json_data["patient_id"];
			$data = $json_data["data"];
			$dest = $json_data["dest"];
			$file_name = storage_path()."/jsons/".$json_data["file_name"];
			Log::info($file_name);
			Log::info($patient_id);
			file_put_contents($file_name, json_encode($data));
			$fields = explode(":", $dest);
			$cmd = "scp $file_name  ".$user->email."@$dest"."sampleSheets/";
			Log::info($cmd);
			system($cmd);	
			$cmd = "ssh $user->email@".$fields[0]." ".$fields[1]."runner.sh ".$json_data["file_name"];
			$output = array("ok");
			Log::info($cmd);
			exec($cmd, $output);
			if ($output[0] == "ok")
				return json_encode(array("code"=>"success","desc"=>$output));
			else
				return json_encode(array("code"=>"error","desc"=>$output));
		} catch (\Exception $e) { 
			return json_encode(array("code"=>"error","desc"=>$e->getMessage()));			
		}
	}

	public function saveClinicalData() {
		set_time_limit(240);
		$json_data = Request::all();
		$data = json_decode($json_data["upload_data"]);
		$delete_old = $data->delete_old;		
		try {
			DB::beginTransaction();
			if (property_exists($data, "patients")) {
				$patient_patient_id_col = $data->patients->patient_id_col;
				$patient_survival_time_col = $data->patients->survival_time_col;			
				$patient_survival_status_col = $data->patients->survival_status_col;
				$patient_event_free_survival_time_col = $data->patients->event_free_survival_time_col;			
				$patient_event_free_survival_status_col = $data->patients->event_free_survival_status_col;
				//$patient_fixed_columns = array($patient_patient_id_col, $patient_diagnosis_col, $patient_survival_time_col, $patient_survival_status_col);
				$patient_data = $data->patients->patient_data;
				#$survival_time_col_name = 'Event Free Survival Time in Days';
				#$survival_status_col_name = 'Vital Status';

				foreach ($data->patients->patient_data as $patient) {
					$patient_id = $patient[$patient_patient_id_col];
					$data->patients->meta_data[$patient_patient_id_col]->include = false;					
					if ($delete_old) {
						DB::table('patient_details')->where('patient_id',$patient_id)->delete();
					}
					for ($i=0;$i<count($patient);$i++) {
						if ($data->patients->meta_data[$i]->include) {
							//Log::info("$patient_survival_time_col => ".$data->patients->meta_data[$i]->label);
							$attr_name = $data->patients->meta_data[$i]->label;
							if ($patient_survival_time_col == $i)
								DB::table('patient_details')->insert(array('patient_id' => $patient_id, 'attr_name' => $attr_name, 'attr_value' => $patient[$i], 'type' => 'number', 'class' => 'overall_survival'));
							else if ($patient_survival_status_col == $i)
								DB::table('patient_details')->insert(array('patient_id' => $patient_id, 'attr_name' => $attr_name, 'attr_value' => $patient[$i], 'type' => 'category', 'class' => 'survival_status'));
							else if ($patient_event_free_survival_time_col == $i)
								DB::table('patient_details')->insert(array('patient_id' => $patient_id, 'attr_name' => $attr_name, 'attr_value' => $patient[$i], 'type' => 'number', 'class' => 'event_free_survival'));
							else if ($patient_event_free_survival_status_col == $i)
								DB::table('patient_details')->insert(array('patient_id' => $patient_id, 'attr_name' => $attr_name, 'attr_value' => $patient[$i], 'type' => 'category', 'class' => 'first_event'));
							else
								DB::table('patient_details')->insert(array('patient_id' => $patient_id, 'attr_name' => $attr_name, 'type' => 'category', 'attr_value' => $patient[$i]));
						}
						
						
					}
					//DB::table('patient')->insert(array('project_id' => $this->id, 'patient_id' => $patient_id, 'diagnosis' => $diagnosis, 'survival_time' => $survival_time, 'survival_status' => $survival_status));
				}
			}

			if (property_exists($data, "samples")) {
				$sample_sample_id_col = $data->samples->sample_id_col;			
				$sample_patient_id_col = $data->samples->patient_id_col;
				$sample_data = $data->samples->sample_data;	
				$data->samples->meta_data[$sample_sample_id_col]->include = false;
				$data->samples->meta_data[$sample_patient_id_col]->include = false;
				foreach ($data->samples->sample_data as $sample) {
					$patient_id = $sample[$sample_patient_id_col];
					$sample_id = $sample[$sample_sample_id_col];					
					if ($delete_old)
						DB::table('sample_details')->where('sample_id',$sample_id)->delete();
					for ($i=0;$i<count($sample);$i++) {
						//if (! in_array($i, $sample)) {
						if ($data->samples->meta_data[$i]->include)
							DB::table('sample_details')->insert(array('sample_id' => $sample_id, 'attr_name' => $data->samples->meta_data[$i]->label, 'attr_value' => $sample[$i]));
						//}
					}					
				}
			}						
			DB::commit();
			return "ok";
		} catch (\PDOException $e) { 
			DB::rollBack();
			return $e->getMessage();
		}		
	}	




public function viewGSEA($project_id,$patient_id, $case_id,$token_id) {
		if (!User::hasPatient($patient_id))
			return 'Access denied!';
		$samples=array();
		if($patient_id=="gene"){
			$samples[$case_id]=$case_id;
		}
		if($patient_id=="any"){
			$all_samples=Project::getSamples($project_id);
			$project = Project::getProject($project_id);
			$exp_samples = $project->getExpressionSamples();
			foreach ($all_samples as $sample) {
				if (in_array($sample->sample_id, $exp_samples))
					$samples[$sample->sample_id]=$sample->sample_name;
			}
		}
		else{
			$patient_var = Sample::getSamplesByPatientID($patient_id, $case_id);
			foreach ($patient_var as $var) {
				if($var->exp_type=="RNAseq"){
					$samples[$var->sample_id]=$var->sample_name;
				}
			}
		}	
			if($patient_id!="gene" && $patient_id!="any"){
				foreach($samples as $sample_id=>$sample_name){
					if($patient_id=="any"){
						$sample = Patient::getPatientBySample($sample_id);
						$actual_patient_id=$sample{0}->patient_id;
					}
					else{
						$actual_patient_id=$patient_id;
					}
					if($case_id=="any"){
						$actual_case_id=Sample::getCaseID($actual_patient_id,$sample_id);
	#					Log::info($sample_id);
					}
					else{
						$actual_case_id=$case_id;
					}
					$path='../app/storage/ProcessedResults/'.VarCases::getPath($actual_patient_id, $actual_case_id).'/'.$actual_patient_id.'/'.$actual_case_id;
					$has_expression = Sample::getExpressionFile($path, $sample_id, $sample_name, "refseq", "gene");
#					list($exp_data, $samples) = Patient::getExpressionByCase($actual_patient_id, $actual_case_id, "all", $sample_id);
	#				Log::info(count($exp_data));
					if(!$has_expression)
						unset($samples[$sample_id]);
				

				}

			}


        $first_sample=$samples;
        reset($first_sample);
        $first_id = key($first_sample);
        $first_name = reset($first_sample);
#        Log::info("ID: ".$first_id." NAME:".$first_name);
        $default_sample_name=$first_name;
#		Log::info($case_id);
#        Log::info("END OF VIEW");
		return View::make('pages/viewGSEA', ['project_id' => $project_id,'patient_id' => $patient_id, 'case_id' => $case_id, 'token_id'=>$token_id,'sample_list'=>$samples,'default_sample_name'=>$default_sample_name]);
	}

	

	public function getGSEAInput($token_id){
		$user = User:: getCurrentUser();
		$user_id=$user->id;
		$results=Patient::getGSEArecords($user_id);
		foreach ($results as $result) {
			if($result->token_id==$token_id){
#				$patient_id=$result->patient_id;
#				$case_id=$result->case_id;
#				Log::info('../app/storage/GSEA/files/'.$patient_id.'/'.$case_id.'/input_'.$token_id.'.json');
#				$json=file_get_contents('../app/storage/GSEA/files/'.$patient_id.'/'.$case_id.'/results/input_'.$token_id.'.json');
				$json=$result->json;
				return($json);
			}
		} 
		

	}
	public function viewGSEAResults($project_id,$token_id) {
		Log::info("TOKEN ID ".$token_id);
		return View::make('pages/viewGSEAResults',['project_id'=>$project_id],['token_id'=>$token_id]);
		//return View::make('pages/viewGSEAResults', ['patient_id' => $patient_id, 'case_id' => $case_id, 'token_id'=>$token_id]);

	}
	public function getGSEAResults($project_id,$token_id) {
		$patient_id="";
		$case_id="";
		$host = url("/");
		$results="";
		$output="";
		$user = User:: getCurrentUser();
		$user_id=$user->id;
		$records=Patient::getGSEArecords($user_id);
		foreach ($records as $record) {
			if($record->token_id==$token_id){
				$patient_id=$record->patient_id;
				$case_id=$record->case_id;
				$output=$record->output;
				break;
			}
		}

		if($patient_id=="gene"){
			$dir='../app/storage/GSEA/files/'.$case_id.'/'.$project_id.'/results/'.$output;
		}
		else if($patient_id!="gene"){
			$dir='../app/storage/GSEA/files/'.$patient_id.'/'.$case_id.'/results/'.$output;
		}
		
		if(is_dir($dir)){
			Log::info("DIR ".$dir); 
			$content=$dir."/index.html";
			return Redirect::to($content);	
		}
		
		
		//return View::make('pages/viewGSEAResults', ['patient_id' => $patient_id, 'case_id' => $case_id, 'token_id'=>$token_id]);

	}
	public function removeGSEArecords($token_id) {
		Patient::removeGSEArecords($token_id);
		return "deleted";
	}
	public function getGSEA($project_id,$patient_id, $case_id,$sample_ids) {

		$genes = array();
		$dir='../app/storage/GSEA/Gene_sets/Pre_uploaded';
		$genes = scandir($dir);
		array_shift($genes);
		array_shift($genes);
		$user = User:: getCurrentUser();
		$user_id=$user->id;
		$samples=array();
		if($patient_id=="gene"){
			$samples[$case_id]=$case_id;
		}
		else if($patient_id=="any"){
			$all_samples=Project::getSamples($project_id);
			$project = Project::getProject($project_id);
			$exp_samples = $project->getExpressionSamples();
			foreach ($all_samples as $sample) {
				if (in_array($sample->sample_id, $exp_samples))
					$samples[$sample->sample_id]=$sample->sample_name;
			}			
			ksort($samples);
		}
		else{
			$patient_var = Sample::getSamplesByPatientID($patient_id, $case_id);
			foreach ($patient_var as $var) {
				if($var->exp_type=="RNAseq"){
					$samples[$var->sample_id]=$var->sample_name;
				}
			}
			
		}

		if($patient_id!="gene" && $patient_id!="any"){
			foreach($samples as $sample_id=>$sample_name){
				if($patient_id=="any" ){
					$sample = Patient::getPatientBySample($sample_id);
					$actual_patient_id=$sample{0}->patient_id;
				}
				else
					$actual_patient_id=$patient_id;
				if($case_id=="any"){
					$actual_case_id=Sample::getCaseID($actual_patient_id,$sample_id);
					//Log::info("Case: id".$actual_case_id);
				}
				
				else
					$actual_case_id=$case_id;
				
				$path='../app/storage/ProcessedResults/'.VarCases::getPath($actual_patient_id, $actual_case_id).'/'.$actual_patient_id.'/'.$actual_case_id;
					$has_expression = Sample::getExpressionFile($path, $sample_id, $sample_name, "refseq", "gene");

	#				$has_expression	=Sample::getFilePath($path, $sample_id, $sample_name, "refseq",".gene.fc.RDS");

	#				list($exp_data, $samples) = Patient::getExpressionByCase($actual_patient_id, $actual_case_id, "all", $sample_id);
	#				Log::info("COUNT ".count($exp_data));
					// Log::info("HAS EXPRESSION ".$has_expression);
					if(!$has_expression)
					unset($samples[$sample_id]);
					
			}
		}
		// Log::info($samples);
		$results=Patient::getGSEArecords($user_id);
		$filtered_results=array();
		//Log::info("SAMPLE ID: ".$sample_ids);
		foreach ($results as $result) {
			if($result->project_id==$project_id){	
				if($sample_ids!="any"){
					if($result->sample_id==$sample_ids){
						Log::info("BING ".$result->sample_id." ".$sample_ids);
						Log::info("Project Normal ".$result->project_normal);

						$can_delete=' N ';
						if($result->user_id==$user_id){
							$can_delete=' Y ';
						}
						$project_normal="";
						if(empty($result->project_normal))
							$project_normal="NA";
						else
							$project_normal=$result->project_normal;
						$row=array($result->output,$result->date_created,$result->gene_set,$result->sample_name,$result->rank_by,$project_normal,$result->token_id,$can_delete);
						$filtered_results[]=$row;
					}
				}
				else{
					if(in_array($result->sample_name,$samples)){
						$can_delete=' N ';
						if($result->user_id==$user_id){
							$can_delete=' Y ';
						}
						$project_normal="";
						if(empty($result->project_normal))
							$project_normal="NA";
						else
							$project_normal=$result->project_normal;
						$row=array($result->output,$result->date_created,$result->gene_set,$result->sample_name,$result->rank_by,$project_normal,$can_delete,$result->token_id,$can_delete);
						$filtered_results[]=$row;
					}
				}
			}
		}

		$user_list = array();
		$gene_lists = UserGeneList::getGeneList("rnaseq");
		foreach ($gene_lists as $list_name => $gene_list){
             $user_list[] = $list_name;
		}
		$gene_help= file_get_contents('../app/storage/GSEA/Gene_sets/help.json', true);
		//Log::info($user_list);
		$projects = User::getCurrentUserProjects();
		return json_encode(array("pre_gene_list" => $genes,"user_list" => $user_list,'samples'=>$samples,"results"=>$filtered_results,"gene_help"=>$gene_help,"projects"=>$projects));
	}

	public function GSEAcalc($project_id,$patient_id, $case_id) {
		ini_set('max_execution_time', 900);
		$ranked_rows=array();
		$input = Request::all();
		Log::info($input['gene_set_type']);
		$ispublic='Y';
		if($input['gene_set_type']=="pre")
			$gset_path='../app/storage/GSEA/Gene_sets/Pre_uploaded/'.$input["gmx"];
		else if($input['gene_set_type']=="user"){
			Log::info("SELECTED ".$input["gmx"]);
			$gset_path='../app/storage/GSEA/Gene_sets/Generated/'.$input["gmx"].".gmt";
			$gene_lists = UserGeneList::getGeneList("rnaseq");
				foreach ($gene_lists as $list_name => $gene_list){
	             	if($list_name==$input["gmx"]){
	             		$user_gene_list_file=fopen($gset_path,"w");
	             		foreach($gene_list as $key=>$value){
	             			if($key!="")
    							fwrite($user_gene_list_file,$key."\t");
	             			
    						
    					}
    					$ispublic=UserGeneList::isGeneListPublic($input["gmx"]);
    					$ispublic=$ispublic{0}->ispublic;
	             		fclose($user_gene_list_file);
	             		break;
					}
				}

		}
		Log::info("gset_path: ".$gset_path);
		Log::info($ispublic);
		$user = User:: getCurrentUser();
		$email = $user->email_address;
		$user_id=$user->id;
		$token_id=$input['token_id'];
		$rank_by=$input['rank_by'];
		$sample_id=$input['sample_id'];
		$sample_name=$input['sample_name'];
		$target_type=$input["target_type"];
		$normal_project_name=$input["normal_project_name"];
		Log::info("NORMAL PROJECT NAME ".$normal_project_name);
		$normal_project_id=$input["normal_project_id"];
		if($target_type=="refseq"){
			$target_folder="TPM_UCSC";
			$target_annotation="annotation_UCSC_gene.RDS";
		}
		else if($target_type=="ensembl"){
			$target_folder="TPM_ENS";
			$target_annotation="annotation_ENSEMBL_gene.RDS";
		}
		$url = url("/")."/viewGSEA/".$project_id.'/'.$patient_id.'/'.$case_id."/".$token_id;
	
		if($patient_id=="any"){
			$sample = Patient::getPatientBySample($sample_id);
			$patient_id=$sample->patient_id;
		}

		if($case_id=="any"){
				$case_id=Sample::getCaseID($patient_id,$sample_id);
		}
		$pat_dir= '../app/storage/GSEA/files/'.$patient_id;
		$case_dir='../app/storage/GSEA/files/'.$patient_id.'/'.$case_id;
		$proj_dir='../app/storage/GSEA/files/'.$case_id.'/'.$project_id;
		if(!is_dir($pat_dir)){
	    	//Directory does not exist, so lets create it.
	    	mkdir($pat_dir, 0755, true);
			}
		if($patient_id!="gene"){
			if(!is_dir($case_dir)){
				    		Log::info("MAKING DIR");

	    		//Directory does not exist, so lets create it.
	    		mkdir($case_dir, 0755, true);
	    		mkdir($case_dir.'/data', 0755, true);
	    		mkdir($case_dir.'/results', 0755, true);
			}
		}
		else{			
	    	if(!is_dir($proj_dir)){
				mkdir($proj_dir.'/data', 0755, true);
	    		mkdir($proj_dir.'/results', 0755, true);
	    	}

		}
		//exec(' qsub ../app/scripts/backend/submit_GSEA.pbs',$output);
		//exec(' /usr/bin/python ../app/scripts/GSEA.py '. escapeshellarg(json_encode($params)).' > /dev/null 2>&1 &',$result);
#        $exp_data=Sample::getCoding_expressionBySample($sample_id,$target_type);               

		if($patient_id!="gene"){
			$out_path=$case_dir."/results";
			if($rank_by!="TPM"){
				$path='../app/storage/ProcessedResults/'.VarCases::getPath($patient_id, $case_id).'/'.$patient_id.'/'.$case_id;
				$patient_sample_file=Sample::getFilePath($path, $sample_id, $sample_name, $target_folder,"_counts.Gene.fc.RDS");
				if(in_array('',$patient_sample_file)){
					$patient_sample_file=Sample::getFilePath($path, $sample_id, $sample_name, $target_folder,".gene.fc.RDS");
				}
				$tsv_path="$case_dir/data/".$sample_name.'_'.$token_id.".tsv" or die ("Unable to open file!");
				$rds_patient_string=$sample_id."\t".$sample_name."\t".$patient_sample_file[0]."\n";
				$rds_list_file=fopen($tsv_path,"w");
				if($project_id!=$normal_project_id)
					fwrite($rds_list_file,$rds_patient_string);
				Log::info($rds_patient_string);
	#			$Normalproject = Project::getNormalProject();
				$Normalsamples=Project::getSamples($normal_project_id);
				Log::info("NORMALIZE PROJECT ID ".$normal_project_id);
				Log::info("writing rds matrix");
				foreach($Normalsamples as $sample) {

					$normal_patient_id=$sample->patient_id;
					$normal_sample_id=$sample->sample_id;
					$normal_sample_name=$sample->sample_name;
					$normal_case_id=Sample::getCaseID($normal_patient_id,$normal_sample_id);
					Log::info($normal_case_id);
					if($normal_case_id!=""){
						$path='../app/storage/ProcessedResults/'.VarCases::getPath($normal_patient_id, $normal_case_id).'/'.$normal_patient_id.'/'.$normal_case_id;
						$new_data=false;
						if(!in_array('',Sample::getFilePath($path, $normal_patient_id, $normal_sample_name, $target_folder,"_counts.gene.fc.RDS"))){
							$normal_sample_file=Sample::getFilePath($path, $normal_sample_id, $normal_sample_name, $target_folder,".gene.fc.RDS");
							$rds_normal_string=$normal_sample_id."\t".$normal_sample_name."\t".$normal_sample_file[0]."\n";
							if($rds_normal_string!=$rds_patient_string)
								fwrite($rds_list_file,$rds_normal_string);
							$new_data=true;
						}
						else if(!in_array('',Sample::getFilePath($path, $normal_patient_id, $normal_sample_name, $target_folder,"_counts.Gene.fc.RDS"))&&$new_data==false){
							$normal_sample_file=Sample::getFilePath($path, $normal_patient_id, $normal_sample_name, $target_folder,"_counts.Gene.fc.RDS");
							$rds_normal_string=$normal_sample_id."\t".$normal_sample_name."\t".$normal_sample_file[0]."\n";
							if($rds_normal_string!=$rds_patient_string)
								fwrite($rds_list_file,$rds_normal_string);
						}
					}
				}
				Log::info("finished writing rds matrix");
				fclose($rds_list_file);
				Log::info("Normalizing");
				Log::info('Rscript ../app/scripts/tmmNormalize.r '.$tsv_path.' ../app/storage/data/AnnotationRDS/'.$target_annotation.' gene '.$case_dir.'/data/ '.$sample_id.'_'.$token_id.'_refseq-gene');
				exec('Rscript ../app/scripts/tmmNormalize.r '.$tsv_path.' ../app/storage/data/AnnotationRDS/'.$target_annotation.' gene '.$case_dir.'/data/ '.$sample_id.'_'.$token_id.'_refseq-gene',$output);
				Log::info($output);
				Log::info("processing ".$target_type);
				exec(' Rscript ../app/scripts/preprocessSamples.r '.$case_dir.'/data/'.$sample_id.'_'.$token_id.'_refseq-gene.tsv '.$case_dir.'/data/'.$sample_id.'_'.$token_id.'_processed.tsv' ,$output);
				Log::info($output);
				Log::info("finished processing refseq");
				$fp = fopen($case_dir.'/data/'.$sample_id.'_'.$token_id.'_processed.tsv', 'r');
				$stats=array();
				while ( !feof($fp) )
				{
				    $line = fgets($fp, 2048);
				    $data = str_getcsv($line, "\t");
				    $stats[$data[0]]=($data);

				} 
			}
			$in_path="$case_dir/data/".$sample_name.'_'.$token_id.".rnk" or die ("Unable to open file!");
	    	$in_file=fopen($in_path,"w");
			Log::info("writing RNK file");
			if($patient_id!="gene")
				list($exp_data, $samples) = Patient::getExpressionByCase($patient_id, $case_id, $target_type, $sample_id);
			else{
				$project = Project::getProject($project_id);
				$gene_id=$case_id;
				list($corr_p, $corr_n) = $project->getCorrelation($gene_id, $cutoff, $target_type);
			}
			$genes=file_get_contents($gset_path);
	#		Log::info($exp_data);
			foreach($exp_data as $exp_row) {
				$symbol=$exp_row->symbol;
				$value=$exp_row->value;
				if($rank_by=="Zscore"|| $rank_by=="Median_Centered"||$rank_by=="Median_Zscore"){
	            	if(isset($stats[$symbol]))
	            	{
						$mean = $stats[$symbol][0];
						$std=$stats[$symbol][1];
						$median = $stats[$symbol][2];
						if($rank_by=="Median_Centered"){
							$value=($value-$median);
						}
						else if($rank_by=="Zscore"){
							$value=($value-$mean)/$std;
						}
						else if($rank_by=="Median_Zscore"){
							$value=($value-$median)/$std;
						}
						//unset($stats->{$stat});
					}
				}

				if (strpos($symbol, '/') != true) {
					$pattern='/\b'.$symbol.'\b/';
					if((preg_match($pattern, $genes) && $value ==0) || $value !=0 ) {
	        			$ranked_rows[$symbol]=$value;
		    		}
		    	}
	    	}
	    	arsort($ranked_rows);
	    }
	    else{
	    	$sample_id=$case_id;
	    	$sample_name=$case_id;
	    	$out_path=$proj_dir."/results";
	    	$in_path="$proj_dir/data/".$sample_name.'_'.$token_id.".rnk" or die ("Unable to open file!");
	    	$project = Project::getProject($project_id);
			list($corr_p, $corr_n) = $project->getCorrelation($case_id,0, $target_type);
			arsort($corr_p);
			arsort($corr_n);
			$ranked_rows=array_merge($corr_p,$corr_n);
			$in_file=fopen($in_path,"w");
			Log::info("writing RNK file");
	    }
	    if($patient_id=="gene"){
	    	$row=$case_id."\t"."1.0"."\n";
    		Log::info($row);
    		fwrite($in_file,$row);	
	    }
    	foreach($ranked_rows as $key=>$value){
    		if($patient_id=="gene"){
    			$pieces = explode(",", $key);
    			$key=$pieces[0];
    		}
    		$row=$key."\t".$value."\n";
    		Log::info($row);
    		fwrite($in_file,$row);	
    	}
    	Log::info("DONE WRITING RANKED FILE");
	$directory=getcwd();
 	$params = array("out_path"=>$out_path,"in_path"=>$in_path,"gset_path"=>$gset_path,"email"=>$email,"form_input"=>$input, "patient_id"=>$patient_id,"case_id"=>$case_id, "token_id"=>$token_id,"url"=>$url,"user_id"=>$user_id,"sample_id"=>$sample_id,"sample_name"=>$sample_name,"project_id"=>$project_id,"ispublic"=>$ispublic,"directory"=>$directory,"normal_project_name"=>$normal_project_name);

   		$fp = fopen($out_path.'/input_'.$token_id.'.json', 'w');
		Log::info($out_path.'/input_'.$token_id.'.json');
		fwrite($fp, json_encode($input));
		fclose($fp);

		exec(' /usr/bin/python ../app/scripts/GSEA.py '. escapeshellarg(json_encode($params)),$output);
		//exec(' /usr/bin/python ../app/scripts/GSEA.py '. escapeshellarg(json_encode($params)).' > /dev/null 2>&1 &',$result);
		Log::info($output);
		Log::info("COMPLETE");
  


	}

	public function requestDownloadCase($patient_id, $case_id) {
		$dt = new \DateTime();
		$dt->setTimezone(new \DateTimeZone('EDT'));
		$dtstr = $dt->format('YmdHis');
		$path = storage_path()."/DME_download_request/";
		if (!file_exists($path)) {
    		mkdir($path, 0775, true);
		}
		$fn = "$path/$dtstr.dme";
		$logged_user = User::getCurrentUser();
		if ($logged_user != null) {
			$fp = fopen($fn, 'w');
			$line = "$patient_id\t$case_id\t".$logged_user->email_address;
			fwrite($fp, $line);
			fclose($fp);
			return "Download request is submitted. Please check your email for further actions!";
		} else {
			return "Please login or you do not have permission to download this case!";
		}
		return "Unknown error!";

	}

	public function downloadGSEAResults($project_id,$token_id){
		$user_id = User::getCurrentUser()->id;
		$records=Patient::getGSEArecords($user_id);
		Log::info("TOKEN ID ".$token_id);
		Log::info("PROJECT ID ".$project_id);
		$patient_id="";
		$case_id="";
		$output="";
		foreach ($records as $record) {
			if($record->token_id==$token_id){
				$patient_id=$record->patient_id;
				$case_id=$record->case_id;
				$output=$record->output;
				break;
			}
		}
		
		if($patient_id=="gene"){
			$pathToFilezip='../app/storage/GSEA/files/'.$case_id.'/'.$project_id.'/results/'.$output.".zip";
		}
		else if($patient_id!="gene"){
			$pathToFilezip='../app/storage/GSEA/files/'.$patient_id.'/'.$case_id.'/results/'.$output.".zip";
		}
		return Response::download($pathToFilezip);
		
	}
	
	public function viewMixcr($patient_id, $case_id, $type) {
		return View::make('pages/viewMixcr',['patient_id'=>$patient_id,'case_id'=>$case_id, 'type'=>$type]);
	}

	public function getMixcr($patient_id, $case_id, $type, $format="json") {
		$rows = Sample::getMixcr($patient_id, $case_id, $type);		
		$data = $this->getDataTableJson($rows);
		if ($format == "text") {
			$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='."$patient_id-$case_id-$type.tsv");
			$content = $this->dataTableToTSV($data["cols"], $data["data"]);
			return Response::make($content, 200, $headers);			
		}
		return json_encode($data);
	}

	public function getMixcrFile($patient_id, $sample_name, $case_id, $ext) {
		$path = VarCases::getPath($patient_id, $case_id);
		if ($path != null) {
			$pathToFile = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/$sample_name/mixcr/$sample_name.$ext";			
			if (!file_exists($pathToFile)) {
				$sample_id = Sample::getSampleIDByName($sample_name);
				$pathToFile = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/$sample_id/mixcr/$sample_id.$ext";
				if (!file_exists($pathToFile)) {
					$pathToFile = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/$sample_name/mixcr/$sample_id.$ext";
					if (!file_exists($pathToFile)) {
						return "";
					}
				}
			}
			return $pathToFile;
		}
		return "";
	}

	// from Scott's uncommitted code on clinomics_dev
	public function getmixcrPlot($patient_id, $sample_name, $case_id, $type) {
		$pathToFile = $this->getMixcrFile($patient_id, $sample_name, $case_id, "$type.fancyvj.wt.pdf");
		if ($pathToFile == null) {
			return View::make('pages/error', ['message' => 'File not found!']);
		}
		return Response::make(file_get_contents($pathToFile), 200, ['Content-Type' => 'application/pdf','Content-Disposition' => 'inline; filename="'.$sample_name.'_'."$type.pdf'"]);
	}
	// from Scott's uncommitted code on clinomics_dev
	public function getMixcrTable($patient_id,$sample_name, $case_id, $type){
		Log::info("GET TABLE ");
		$data=array();
		$cols=array();
		$show=array();
		$hide=array();
		$path = VarCases::getCase($patient_id, $case_id);
		
		$pathToFile = $this->getMixcrFile($patient_id, $sample_name, $case_id, "$type.RNA.txt");
		if ($pathToFile == null) {
			$pathToFile = $this->getMixcrFile($patient_id, $sample_name, $case_id, "$type.TCR.txt");
			if ($pathToFile == null) {
				$pathToFile = $this->getMixcrFile($patient_id, $sample_name, $case_id, "$type.ALL.txt");
				if ($pathToFile == null && $type=="clones") {
					$pathToFile = $this->getMixcrFile($patient_id, $sample_name, $case_id, "clonotypes.ALL.txt");
				}
			}				
		}
		if ($pathToFile != "") {
				$file=	file_get_contents($pathToFile);		
				$headers = fgets(fopen($pathToFile, 'r'));
				$parts = preg_split('/\s+/', $headers);
				foreach ($parts as $header){
					$cols[] = array("title" => ucfirst($header));
					$show[]=$header;
								
					}
				$filearray = explode("\n", $file);
				array_shift($filearray);
				foreach ($filearray as $line){
						$parts = explode("\t", $line);
						for ($pi=0;$pi<count($parts);$pi++) {
							$parts[$pi] = str_replace("convert.", "", $parts[$pi]);
							if (is_numeric($parts[$pi]))
								if (is_float($parts[$pi] + 0))
									$parts[$pi] = round($parts[$pi],3);

						}
						$data[]=$parts;			
				}
				array_pop($data);
				array_pop($cols);		
		}
		return json_encode(array("cols" => $cols, "data" => $data,"show_cols" => $show,"hide_cols" => $hide,"type" => 'mixcr'));
	}

	public function getRNAseqSample($sample_id) {
		$rnaseq_samples = array();
		$rows = Sample::where('sample_id', $sample_id)->get();
		if (count($rows) > 0) {
			$rnaseq_rows = Sample::where('sample_id', $rows[0]->rnaseq_sample)->get();
			if (count($rnaseq_rows) > 0) {
				$rnaseq_samples[] = $rnaseq_rows[0]->sample_name;
			}
		}
		return json_encode($rnaseq_samples);
	}

	public function getDataIntegrityReportTable($file, $target="Khanlab", $format="json") {
		$content = file_get_contents($file);
		$lines = explode("\n", $content);
		$cols = array();
		$data = array();
		$path_idx = -1;
		$headers = explode("\t", $lines[0]);
		if ($format == "text") {
			$data[] = $lines[0];
		}
		for ($i=0;$i<count($headers);$i++) {
			$cols[] = array("title"=>$headers[$i]);
			if ($headers[$i] == "Path")
				$path_idx = $i;
		}
		for ($i=1;$i<count($lines);$i++) {
			$fields = explode("\t", $lines[$i]);
			if (count($fields) <= 1)
				continue;
			if ($path_idx > 0 ) {
				if ($target == "COMPASS") {
					if (strpos($fields[$path_idx], "compass") === false)
						continue;
				} else {
					if (strpos($fields[$path_idx], "compass") !== false)
						continue;
				}
			}
			if ($format == "json")
				$data[] = $fields;
			else
				$data[] = $lines[$i];			
		}
		if ($format == "json")
			return json_encode(array("cols"=>$cols,"data"=>$data));
		return implode("\n", $data);

	}

	public function viewDataIntegrityReport($target="Khanlab") {
		$data_list = ($target == "Khanlab")? array("case_content_inconsistency", "case_name_inconsistency"):array();
		$data_list = array_merge($data_list,array("cases_on_Biowulf_only", "cases_on_Frederick_only", "missing_bams", "missing_rsems", "sample_inconsistency","no_successful_cases", "unloaded_cases","unprocessed_cases", "unused_cases"));
		$root = storage_path()."/data_integrity_report";
		$summary = $this->getDataIntegrityReportTable("$root/summary_${target}.txt", $target);
		$detail_tables = array();
		foreach ($data_list as $name) {
			$detail_tables[$name] = $this->getDataIntegrityReportTable("$root/${name}.txt", $target);
		}
		//return $summary;
		return View::make('pages/viewDataIntegrityReport',['target'=>$target, 'summary'=>$summary, 'detail_tables' => $detail_tables]);

	}

	public function downloadDataIntegrityReport($report_name, $target="Khanlab") {
		$root = storage_path()."/data_integrity_report";
		
		$content = $this->getDataIntegrityReportTable("$root/${report_name}.txt", $target, "text");		
		$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='."${report_name}.txt");
		return Response::make($content, 200, $headers);

	}	
}
