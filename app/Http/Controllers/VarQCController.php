<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Project;
use App\Models\Patient;
use App\Models\Sample;
use App\Models\VarCases;
use App\Models\VarQC;
use Response,Config,DB,Log,Lang,View;

class VarQCController extends BaseController {

	public function viewProjectQC($project_id) {
		$cases = Project::getCases($project_id);
		$plot_types = array('circos', 'coveragePlot', 'transcriptCoverage', 'hotspot');
		$hasRNAseq = Project::hasRNAseq($project_id);
		if (!$hasRNAseq)
			$plot_types = array('circos', 'coveragePlot', 'hotspot');
		//$plot_types = array();		
		$gt_file = storage_path()."/project_data/$project_id/gt.txt";
		$genotyping_url = "";
		//if (file_exists($gt_file))
		//	$genotyping_url = url("/getProjectGenotyping/$project_id");
		$genotyping_patients = Project::getGenoTypingPatients($project_id);
		return View::make('pages/viewProjectQC', ['cohort_type' => 'Project', 'cohort_id' => $project_id, 'cases' => $cases, 'plot_types' => $plot_types, 'genotyping_url' => $genotyping_url, 'genotyping_patients' => $genotyping_patients, 'hasRNAseq' => $hasRNAseq]);
	}

	public function viewCancerTypeQC($cancer_type_id) {
		return View::make('pages/viewProjectQC', ['cohort_type' => 'CancerType', 'cohort_id' => $cancer_type_id, 'cases' => null, 'plot_types' => [], 'genotyping_url' => null, 'genotyping_patients' => [], 'hasRNAseq' => true]);
	}

	public function viewVarQC($project_id, $patient_id, $case_name) {
		$patients = Patient::where('patient_id', '=', $patient_id)->get();
		$patient = null;
		if (count($patients) > 0)
			$patient = $patients[0];
		$cases = Patient::getCasesByPatientID(null, $patient_id, $case_name);
		$case = null;
		if (count($cases) > 0)
			$case = $cases[0];
		if ($case == null || $patient == null){
			return View::make('pages/error_no_header', ['message' => 'Patient or case not found']);
		}
		$path = $case->path;
		$case_id = $case->case_id;
		Log::info("Path: $path");
		$sample_types = $patient->getVarSamples($project_id, $case_name);
		$cnv_samples = array();
		$conpair_samples = array();
		$fastqc_samples = array();		

		$circos_file = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/qc/$patient_id.circos.png";
		$has_circos = (file_exists($circos_file));
		$geno_file = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/qc/$patient_id.genotyping.txt";
		$multiqc_file = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/qc/multiqc_report.html";
		$has_geno = (file_exists($geno_file));
		$has_multiqc = (file_exists($multiqc_file));

		foreach($sample_types as $type => $samples) {
			foreach ($samples as $sample) {
				$fastqc_files = glob(storage_path()."/ProcessedResults/$path/$patient_id/$case_id/$sample->sample_id/qc/fastqc/*fastqc.html");
				if (count($fastqc_files) == 0) {
					$fastqc_files = glob(storage_path()."/ProcessedResults/$path/$patient_id/$case_id/$sample->sample_name/qc/fastqc/*fastqc.html");
				}
				foreach ($fastqc_files as $fastqc_file) {
					$file_path = str_replace(storage_path()."/ProcessedResults/$path/$patient_id/$case_id/", "", $fastqc_file);			
					preg_match('/\/qc\/fastqc\/(.*)_fastqc.html/', $file_path, $matches);
					if (count($matches) > 1) {
						$file_path = str_replace("/", "@", $file_path);
						$fastqc_samples[$matches[1]] = $file_path;
					}
				}
				Log::info("Sample name: $sample->sample_name");			
				if ($sample->tissue_cat == 'tumor' && $sample->exp_type != 'RNAseq') {
					list($file, $sample_folder) = Sample::getFilePath(storage_path()."/ProcessedResults/$path/$patient_id/$sample->case_id", $sample->sample_id, $sample->sample_name, "sequenza", "_CP_contours.pdf");
					if ($file != "")
						$cnv_samples[$sample_folder] = $sample->case_id;
					list($conta_file, $sample_folder) = Sample::getFilePath(storage_path()."/ProcessedResults/$path/$patient_id/$sample->case_id", $sample->sample_id, $sample->sample_name, "qc", ".conta.txt");
					$conpair_content = "";
					if ($conta_file != "")
						$conpair_content = file_get_contents($conta_file);
					list($concod_file, $sample_folder) = Sample::getFilePath(storage_path()."/ProcessedResults/$path/$patient_id/$sample->case_id", $sample->sample_id, $sample->sample_name, "qc", ".concod.txt");
					if ($concod_file != "")
						$conpair_content = $conpair_content."\n".file_get_contents($concod_file);
					if ($conpair_content != "")
						$conpair_samples[$sample->sample_name] = $conpair_content;


					/*
					$file = storage_path()."/ProcessedResults/".$path."/$patient_id/$sample->case_id/$sample->sample_name/sequenza/$sample->sample_name/$sample->sample_name"."_CP_contours.pdf";
					if (!file_exists($file)) {
						$file = storage_path()."/ProcessedResults/".$path."/$patient_id/$sample->case_id/$sample->sample_id/sequenza/$sample->sample_id/$sample->sample_id"."_CP_contours.pdf";
						if (!file_exists($file)) {
							$file = storage_path()."/ProcessedResults/".$path."/$patient_id/$sample->case_id/Sample_$sample->sample_id/sequenza/Sample_$sample->sample_id/Sample_$sample->sample_id"."_CP_contours.pdf";
							if (!file_exists($file))
								continue;
							else
								$cnv_samples["Sample_".$sample->sample_id] = $sample->case_id;
						}
						else
							$cnv_samples[$sample->sample_id] = $sample->case_id;
					} else
						$cnv_samples[$sample->sample_name] = $sample->case_id;
					*/
				}

			}

		}
		ksort($fastqc_samples);
		ksort($cnv_samples);
		Log::info('samples:'.json_encode($cnv_samples));
		$qc_cnt = $patient->getQCCount($case_id);
		$rnaqc_samples = array();
			foreach (glob(storage_path()."/ProcessedResults/$path/$patient_id/$case_id/*/qc/*rnaseqc/*report.html") as $rnaqc_file) {
				$file_path = str_replace(storage_path()."/ProcessedResults/$path/$patient_id/$case_id/", "", $rnaqc_file);			
				preg_match('/\/qc\/.*rnaseqc\/(.*)report.html/', $file_path, $matches);
				if (count($matches) > 1) {
					if (strpos($file_path, 'report') !== false) {
						$file_path = str_replace("/", "@", $file_path);
						$sample = explode("@", $file_path)[0];
						$rnaqc_samples[$sample] = $file_path;

					}
					
					#Log::info($matches);
				}
			}
		ksort($rnaqc_samples);

		//TSO metrics file
		$matrics_file = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/qc/MetricsOutput.tsv";		
		$metrics_tables = array();
		if (file_exists($matrics_file)) {
			$content = file_get_contents($matrics_file);
			$lines = explode("\n", $content);
			$tbl_name = "";
			$tbl_content = array();
			$max_col = 0;
			foreach ($lines as $line) {
				$line = trim($line);
				if (substr($line, 0 , 1) == "[" && substr($line, -1) == "]") {
					if ($tbl_name != "") {
						$metrics_tables[] = array("name"=>$tbl_name, "max_col" => $max_col, "data"=> $tbl_content);
					}
					$tbl_name = substr($line, 1, -1);
					$tbl_content = array();
					$max_col = 0;
				} else {
					if ($tbl_name != "") {
						$row = array();
						$arr = explode("\t", $line);
						foreach($arr as $item) {
							$item = trim($item);
							if ($item != "")
								$row[] = $item;
						}
						if (count($row) > $max_col)
							$max_col = count($row);
						$tbl_content[] = $row;
					}
				}
			}
			$metrics_tables[] = array("name"=>$tbl_name, "data"=> $tbl_content, "max_col" => $max_col);
		}
		//Log::info(json_encode($metrics_tables));

		return View::make('pages/viewVarQC', ['qc_cnt' => $qc_cnt, 'project_id' => $project_id,'patient_id' => $patient_id, 'case_id' => $case_id, 'case_name' => $case_name, 'has_circos' => $has_circos, 'has_geno' => $has_geno, 'has_multiqc' => $has_multiqc, 'cnv_samples' => $cnv_samples, 'conpair_samples' => $conpair_samples, 'fastqc_samples' => $fastqc_samples,'rnaqc_samples' => $rnaqc_samples, 'metrics_tables' => $metrics_tables] );
	}

	public function getCoveragePlotData($project_id, $patient_id, $case_name, $samples) {
		$dirs=array();
		$case_ids=array();
		$coverage_data = array();
		$sample_list=array();
		$samples=Sample::getSamplesByPatientID($patient_id,$case_name,$project_id);
		// Log::info("select * from samples where patient_id = '$patient_id'");
		// Log::info($samples);
		##hue added this to help with the Coverage Plots where multiple samples were in different cases 6/21/2019
		##this sample to case id is associated in your Master File!!!
		if ($samples==null)
			$samples=Sample::getSamplesByPatientID($patient_id);
		#end Hue
		foreach($samples as $sample){
			$case_id = $sample->case_id;
			$path = $sample->path;
			//$dirs = scandir(storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/");
				// Log::info($dirs);
			//foreach ($dirs as $folder) {
					$data=array();
					#if(strpos($folder, 'Sample') === 0 ||strpos($folder, 'NCI') === 0 ||strpos($folder, 'CL') === 0 ||strpos($folder, 'RH') === 0 ||strpos($folder, 'Rh') === 0){
					// if($folder!==$patient_id && $folder !== "Actionable" && $folder !== "qc" && strpos($folder, 'zip') !== 0 && strpos($folder, 'txt') !== 0){#as per lab meeting with Wei, Xinyu, Javed and Hue on 5/10/2019, determined that this code ($folder!==$patient_id) would only omit information from the website (coverage plots on summary page) instead of helping it.
					// Log::info("working on $patient_id/$case_id_of_Sample/".$folder."/qc/".$folder);
					//if($folder !== "Actionable" && $folder !== "qc" && strpos($folder, 'zip') !== 0 && strpos($folder, 'txt') !== 0){
					$path_to_file = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/".$sample->sample_id."/qc/".$sample->sample_id.".bwa.coverage.txt";
					$folder = $sample->sample_id;
					if (!file_exists($path_to_file)) {
						$path_to_file = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/".$sample->sample_name."/qc/".$sample->sample_name.".bwa.coverage.txt";
						$folder = $sample->sample_name;
						if (!file_exists($path_to_file))
							$path_to_file = null;
					}
					if( $path_to_file != null ){
							$pieces = explode(".", $folder);
							array_push($sample_list,$pieces[0]);
							Log::info("pushed". $pieces[0]);
							$pathToFile = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/".$folder."/qc/".$folder.".bwa.coverage.txt";
							$file  = fopen($pathToFile, 'r');
							$maxLines = 1000;
							$counter=1;
							$top=1;
							 while(!feof($file)) {
							 	$line = fgets($file);
								$parts = preg_split('/\s+/', $line);
								//$point=$top-$parts[4];
								if($parts[0]!=''){
									$point=$top-$parts[4];
									$coordinate=array($counter,$top-$parts[4]);
									$top=$point;					
									array_push($data,$coordinate);
									if($counter==$maxLines)
										break;
									else
										$counter++;
								}
							}
							array_push($coverage_data,$data);

					}
					else {
						$path_to_file = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/".$sample->sample_id."/qc/".$sample->sample_id.".star.coverage.txt";
						$folder = $sample->sample_id;
						if (!file_exists($path_to_file)) {
							$path_to_file = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/".$sample->sample_name."/qc/".$sample->sample_name.".star.coverage.txt";
							$folder = $sample->sample_name;
							if (!file_exists($path_to_file))
								$path_to_file = null;
						}
						if( $path_to_file != null ){
							$pieces = explode(".", $folder);
							array_push($sample_list,$pieces[0]);
							Log::info("pushed". $pieces[0]);
							$pathToFile = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/".$folder."/qc/".$folder.".star.coverage.txt";
							$file  = fopen($pathToFile, 'r');
							$maxLines = 1000;
							$counter=1;
							$top=1;
							 while(!feof($file)) {
							 	$line = fgets($file);
								$parts = preg_split('/\s+/', $line);
								//$point=$top-$parts[4];
								$point=$top-$parts[4];
								$coordinate=array($counter,$top-$parts[4]);
								$top=$point;
								array_push($data,$coordinate);
								if($counter==$maxLines)
									break;
								else
									$counter++;
							}

							array_push($coverage_data,$data);
						}						
					}			
		}
		return json_encode(array("coverage_data" => $coverage_data,"samples"=>$sample_list)); 
	}

	public function viewQC($patient_id, $case_id, $qc_path) {
		if (!User::hasPatient($patient_id)) {
			return View::make('pages/error', ['message' => 'Access denied!']);
		}
		$path = VarCases::getPath($patient_id, $case_id);
		$filename = storage_path()."/ProcessedResults/$path/$patient_id/$case_id/".str_replace("@","/", $qc_path);
		if (strpos($filename, 'html') !== false) {
			$content = file_get_contents($filename);
			$headers = array('Content-Type' => 'text/html');
			if (strpos($filename, 'report') !== false) {
				$content=VarQCController::FormatRNAqc($patient_id, $case_id,$content,$qc_path);
			}
			return Response::make($content, 200, $headers);
		}
	}


	public function FormatRNAqc($patient_id, $case_id,$content,$qc_path){
		$imgs=['meanCoverageNorm_low.png','meanCoverageNorm_medium.png','meanCoverageNorm_high.png','meanCoverage_low.png','meanCoverage_medium.png','meanCoverage_high.png'];
		
				$to_replace="<p>
<h2>Files</h2>
<table><tr><th>File</th><th>Description</th></tr>
<tr><td><a target='_new' href='metrics.tsv'>Metrics Tab Separated Value File</a></td><td>Text file containing all the metrics of the report in a single tab delimited file.</td></tr>
<tr><td><a target='_new' href='meanCoverageNorm_low.txt'>Mean Coverage Plot Data - Low Expr</a></td><td>Text file containing the data for mean coverage plot by position for low expression coverage</td></tr>
<tr><td><a target='_new' href='meanCoverageNorm_medium.txt'>Mean Coverage Plot Data - Medium Expr</a></td><td>Text file containing the data for mean coverage plot by position for medium expression coverage</td></tr>
<tr><td><a target='_new' href='meanCoverageNorm_high.txt'>Mean Coverage Plot Data - High Expr</a></td><td>Text file containing the data for mean coverage plot by position for high expression coverage</td></tr>
<tr><td><a target='_new' href='meanCoverage_low.txt'>Mean Coverage Plot Data - Low Expr</a></td><td>Text file containing the data for mean coverage plot by distance from 3' end for low expression coverage</td></tr>
<tr><td><a target='_new' href='meanCoverage_medium.txt'>Mean Coverage Plot Data - Medium Expr</a></td><td>Text file containing the data for mean coverage plot by distance from 3' end for medium expression coverage</td></tr>
<tr><td><a target='_new' href='meanCoverage_high.txt'>Mean Coverage Plot Data - High Expr</a></td><td>Text file containing the data for mean coverage plot by distance from 3' end for high expression coverage</td></tr>
<tr><td><a target='_new' href='gapLengthHist_low.txt'>Mean Coverage Plot Data - Low Expr</a></td><td>Text file containing the data for gap length counts for low expression coverage</td></tr>
<tr><td><a target='_new' href='gapLengthHist_medium.txt'>Mean Coverage Plot Data - Medium Expr</a></td><td>Text file containing the data for gap length counts for medium expression coverage</td></tr>
<tr><td><a target='_new' href='gapLengthHist_high.txt'>Mean Coverage Plot Data - High Expr</a></td><td>Text file containing the data for gap length counts for high expression coverage</td></tr>

</table>
</p>";
				$content = str_replace($to_replace, "", $content);
				$sample = explode("@", $qc_path)[0];
				$host = url("/");
				foreach ($imgs as &$value) {
    				$content = str_replace($value,$host.'/getContent/'.$patient_id.'/'.$case_id.'/'.$sample.'@qc@rnaseqc@'.$value.'/img/png',$content);
				}

				return $content;

	}
	public function getContent($patient_id,$case_id,$file_path,$type,$subtype="NA"){
		Log::info($file_path);
		Log::info("GET CONTENT");
		$path = VarCases::getPath($patient_id, $case_id);
		$filename = storage_path()."/ProcessedResults/$path/$patient_id/$case_id/".str_replace("@","/", $file_path);
		Log::info($filename);
		if($type=="text"){
			$content = file_get_contents($filename);
			$headers = array('Content-Type' => 'text/html');
		}
		if($type="img"){
			$content = file_get_contents($filename);
			$headers = array('Content-Type' => 'image/'.$subtype);
		}
		return Response::make($content, 200, $headers);
	}
	public function saveQCLog() {		
		try {
			$user_id = Sentry::getUser()->id;
		} catch (Exception $e) {
			return "NoUserID";
		}
		$data = Input::all();	
		try {
			$qclog = new QCLog;	
			$qclog->patient_id = $data["patient_id"];
			$qclog->log_type = $data["log_type"];			
			$qclog->log_decision = $data["log_decision"];
			$qclog->log_comment = $data["log_comment"];
			$qclog->user_id = $user_id;
			$qclog->save();
			return "Success";
		} catch (\Exception $e) { 
			return $e->getMessage();			
		}	

	}

	public function getQCLogs($patient_id, $case_id, $log_type) {
		$logs = QCLog::getLogByPatientAndType($patient_id, $case_id, $log_type);
		return json_encode($this->getDataTableJson($logs));
	}

	public function getQC($patient_id, $case_id, $type) {
		return json_encode(VarQC::getQCByPatientID($patient_id, $case_id, $type));
	}

	public function getQCPlot($patient_id, $case_id, $type) {
		if (!User::hasPatient($patient_id)) {
			return View::make('pages/error', ['message' => 'Access denied!']);
		}
		$path = VarCases::getPath($patient_id, $case_id);
		if ($path == null) {
			return View::make('pages/error', ['message' => 'No case found!']);
		}
		$pathToFile = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/qc/$patient_id.$type.png";
		if (!file_exists($pathToFile))
			$pathToFile = public_path()."/images/file_not_found.jpg";			

		//$headers = array('Content-Type: application/png');

		$headers = array('Content-Type' => 'image/png');
		$content = file_get_contents($pathToFile);
		return Response::make($content, 200, $headers);
		//return Response::download($pathToFile);

	}

	public function getProjectHotspotCoverage($project_id) {
		$samples = Project::getSampleCases($project_id);
		return VarQC::getHotspotCoverage($samples);
	}

	public function getHotspotCoverage($patient_id, $case_id, $project_id="any") {
		$samples = VarCases::getSamples($patient_id, $case_id, $project_id);
		Log::info(json_encode($samples));
		return VarQC::getHotspotCoverage($samples);
	}
}
