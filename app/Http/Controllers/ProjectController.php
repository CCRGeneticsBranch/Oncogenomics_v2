<?php

namespace App\Http\Controllers;
use App\Models\VarAnnotation;
use Config,View,Log,Response,DB,Redirect;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Models\Project;
use App\Models\User;
use App\Models\PCA;
use App\Models\Gene;
use App\Models\UserSetting;
use App\Models\UserGeneList;
use App\Models\VarQC;
use App\Models\Oncotree;

if (getenv("AWS") == false) {
	putenv("R_LIBS=".Config::get("site.R_LIBS"));
	putenv("PATH=".Config::get("site.R_PATH"));
	putenv("LD_LIBRARY_PATH=".Config::get("site.LD_LIBRARY_PATH"));
}
class ProjectController extends BaseController {
	private const GEMINI_COOLDOWN_SECONDS = 0;
	private const OPENAI_COMPAT_COOLDOWN_SECONDS = 0;

	protected $chatbotLlmTrace = [
		'provider' => null,
		'model' => null,
	];

	protected $chatbotLlmLastError = [
		'provider' => null,
		'code' => null,
		'status' => null,
		'message' => null,
	];

	private $expressionPlotLastError = null;

	protected function resetChatbotLlmDiagnostics() {
		$this->chatbotLlmTrace = ['provider' => null, 'model' => null];
		$this->chatbotLlmLastError = ['provider' => null, 'code' => null, 'status' => null, 'message' => null];
		$this->expressionPlotLastError = null;
	}

	private function noteExpressionPlotError($code, $message, $details = []) {
		$this->expressionPlotLastError = array_merge([
			'code' => (string)$code,
			'message' => (string)$message,
			'provider' => $this->chatbotLlmTrace['provider'] ?? null,
			'model' => $this->chatbotLlmTrace['model'] ?? null,
			'finish_reason' => $this->chatbotLlmTrace['finish_reason'] ?? null,
			'output_tokens' => $this->chatbotLlmTrace['output_tokens'] ?? null,
		], is_array($details) ? $details : []);
		Log::warning('Expression plot generation diagnostic.', $this->expressionPlotLastError);
	}

	private function noteChatbotLlmError($provider, $code = null, $status = null, $message = null, $overwrite = false) {
		if (!$overwrite && ($this->chatbotLlmLastError['provider'] ?? null) != null) {
			return;
		}
		$this->chatbotLlmLastError = [
			'provider' => $provider != null ? (string)$provider : null,
			'code' => $code != null ? (string)$code : null,
			'status' => $status != null ? (string)$status : null,
			'message' => $message != null ? (string)$message : null,
		];
	}

	private function clearChatbotLlmError() {
		$this->chatbotLlmLastError = ['provider' => null, 'code' => null, 'status' => null, 'message' => null];
	}

	private function appendLastLlmErrorToTrace($trace) {
		if (!is_array($trace)) {
			$trace = [];
		}
		if (($this->chatbotLlmLastError['provider'] ?? null) != null) {
			$trace['llm_error_provider'] = $this->chatbotLlmLastError['provider'];
		}
		if (($this->chatbotLlmLastError['code'] ?? null) != null) {
			$trace['llm_error_code'] = $this->chatbotLlmLastError['code'];
		}
		if (($this->chatbotLlmLastError['status'] ?? null) != null) {
			$trace['llm_error_status'] = $this->chatbotLlmLastError['status'];
		}
		return $trace;
	}

	protected function buildChatbotTrace($mode, $provider = null, $model = null) {
		$trace = [
			'mode' => (string)$mode,
		];
		if ($provider != null && trim((string)$provider) !== '') {
			$trace['provider'] = (string)$provider;
		}
		if ($model != null && trim((string)$model) !== '') {
			$trace['model'] = (string)$model;
		}
		return $trace;
	}

	protected function appendChatbotTraceToUrl($url, $trace) {
		if (!is_string($url) || trim($url) === '' || !is_array($trace)) {
			return $url;
		}

		$query = [];
		if (isset($trace['mode']) && trim((string)$trace['mode']) !== '') {
			$query['trace_mode'] = (string)$trace['mode'];
		}
		if (isset($trace['provider']) && trim((string)$trace['provider']) !== '') {
			$query['trace_provider'] = (string)$trace['provider'];
		}
		if (isset($trace['model']) && trim((string)$trace['model']) !== '') {
			$query['trace_model'] = (string)$trace['model'];
		}
		if (isset($trace['llm_error_provider']) && trim((string)$trace['llm_error_provider']) !== '') {
			$query['trace_llm_error_provider'] = (string)$trace['llm_error_provider'];
		}
		if (isset($trace['llm_error_code']) && trim((string)$trace['llm_error_code']) !== '') {
			$query['trace_llm_error_code'] = (string)$trace['llm_error_code'];
		}
		if (isset($trace['llm_error_status']) && trim((string)$trace['llm_error_status']) !== '') {
			$query['trace_llm_error_status'] = (string)$trace['llm_error_status'];
		}

		if (empty($query)) {
			return $url;
		}

		$separator = (strpos($url, '?') === false) ? '?' : '&';
		return $url . $separator . http_build_query($query);
	}

	public function viewProjects() {
		return View::make('pages/viewProjects', ['type' => 'Projects']); 		
	}

	public function viewProjectDetails($project_id) {
		$project = null;
		$genome_versions = array();
		if (is_numeric($project_id)) {
			$project = Project::getProject($project_id);
			$genome_versions = explode(",", $project->getGenomeVersion());
		}
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
			$has_survival_pvalues = (count($overall_files) > 0 || count($event_free_files) > 0);

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
		$var_count = $project->getVarCount();
		Log::info("GSVA has $nsmps samples");
		$has_isoforms = file_exists(storage_path()."/project_data/$project_id/isoforms.zip");
		$has_mutation = $project->hasMutation();
		$has_hla = $project->hasHLA();
		$has_str = $project->hasSTR();
		$has_fusion = $project->hasFusion();
		$has_chipseq = $project->hasChIPseq();

		$genes = Gene::getAllSymbols();
		$gene_data = array();
		foreach ($genes as $g)
			$gene_data[] = "$g->symbol";
		
		return View::make('pages/viewProjectDetails', ['cohort' =>$project, 'cohort_type' => 'Project', 'has_mutation' => $has_mutation, 'has_survival'=>$has_survival, 'has_survival_pvalues' => $has_survival_pvalues, 'has_cnv_summary' => $has_cnv_summary, 'cnv_files' =>$cnv_files, 'survival_diags' => json_encode($survival_diags), 'tier1_genes' => $tier1_genes, 'fusion_genes' => $fusion_genes, 'survival_meta_list' => json_encode($survival_meta_list), 'has_tcell_extrect_data' => $has_tcell_extrect_data, 'cohort_info'=>$project_info, 'additional_links' => $additional_links, 'additional_tabs' => $additional_tabs, 'genesets' => array_keys($genesets), 'gsva_methods' => array_keys($methods), 'gsva_nsmps' => $nsmps, 'var_count' => $var_count, 'has_isoforms' => $has_isoforms, 'has_hla' => $has_hla, 'has_str'=>$has_str, 'has_chipseq' => $has_chipseq, 'include_public' => '', 'genome_versions' => $genome_versions, 'gene_data' => json_encode($gene_data)]);
		
	} 

	public function getProjectsByPost()	{				
		$projects = Project::All();
		return json_encode($projects);

	}
	public function getProjects() {
		$projects = Project::getAll();
		foreach ($projects as $project) {
			$project->name = "<a class='link-underline-light' target=_blank href=".url("/viewProjectDetails/".$project->id).">".$project->name."</a>";
			$project->ispublic = ($project->ispublic == "1")? "Y" : "";
			$project->ispublic = $this->formatLabel($project->ispublic);
			$project->patients = $this->formatLabel($project->patients);
			$project->cases = $this->formatLabel($project->cases);
			$project->samples = $this->formatLabel($project->samples);
			$project->version = $this->formatLabel($project->version);
			$project->processed_patients = $this->formatLabel($project->processed_patients);
			$project->processed_cases = $this->formatLabel($project->processed_cases);
			$project->survival = $this->formatLabel($project->survival);
			$project->exome = $this->formatLabel($project->exome);
			$project->panel = $this->formatLabel($project->panel);
			$project->rnaseq = $this->formatLabel($project->rnaseq);
			$project->whole_genome = $this->formatLabel($project->whole_genome);
			$project->chipseq = $this->formatLabel($project->chipseq);
			$project->hic = $this->formatLabel($project->hic);
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
		$genome_versions = $project->getExpressionGenomeVersion();

		if (!property_exists($setting, 'norm_type'))
			$setting->norm_type = 'tmm-rpkm';
		if (!property_exists($setting, 'genome_version'))
			$setting->genome_version = 'ensembl';

		return View::make('pages/viewExpression',['cohort_type' => 'Project', 'cohort_id' => $project_id, 'patient_id' => $patient_id, 'case_id' => $case_id, 'setting' => $setting, 'gene_id' => '', 'meta_type' => $meta_type, 'genome_versions' => $genome_versions, 'include_public' => '']);
	}

	public function viewExpressionByGene($project_id, $gene_id) {
		$attr_name = "page.expression";
		$setting = UserSetting::getSetting($attr_name);		
		$setting->gene_list = $gene_id;
		UserSetting::saveSetting($attr_name, $setting);
		return $this->viewExpression($project_id);
		#$project = Project::getProject($project_id);
		#$genome_version = $project->getTargetType();
		#return View::make('pages/viewExpression',['project_id' => $project_id, 'patient_id' => 'null', 'case_id' => 'null', 'meta_type' => 'null', 'setting' => $setting, 'gene_id' => $gene_id]);
	}

	protected function normalizeChatbotScope($scope) {
		$scope = strtolower(trim((string)$scope));
		$scope = str_replace(['-', ' '], '_', $scope);
		if ($scope === 'cancertype') {
			$scope = 'cancer_type';
		}

		return in_array($scope, ['global', 'project', 'cancer_type'], true) ? $scope : null;
	}

	protected function resolveChatbotContext($scope, $cohortId) {
		if ($scope === 'global') {
			return ['status' => 'success', 'scope' => 'global', 'id' => 'all', 'name' => 'Clinomics'];
		}

		if ($scope === 'project') {
			$projectId = filter_var($cohortId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
			if ($projectId === false) {
				return ['status' => 'error', 'message' => 'A valid numeric project ID is required.'];
			}
			foreach (Project::getAll(false) as $availableProject) {
				if ((int)$availableProject->id === (int)$projectId) {
					return [
						'status' => 'success', 'scope' => 'project',
						'id' => (int)$projectId, 'name' => trim((string)$availableProject->name),
					];
				}
			}

			return ['status' => 'error', 'message' => "Project {$projectId} was not found or is not accessible."];
		}

		$cancerTypeId = trim((string)$cohortId);
		foreach (User::getCurrentUserCancerTypes() as $cancerType) {
			if ((string)$cancerType->id === $cancerTypeId) {
				return [
					'status' => 'success', 'scope' => 'cancer_type',
					'id' => $cancerTypeId, 'name' => $cancerTypeId,
				];
			}
		}

		return ['status' => 'error', 'message' => "Cancer type {$cancerTypeId} was not found or is not accessible."];
	}

	public function runProjectChatbot($project_id, $query) {
		$context = $this->resolveChatbotContext('project', $project_id);
		if (($context['status'] ?? null) !== 'success') {
			return View::make('pages/error_no_header', [
				'message' => $context['message'] ?? 'Project not found or inaccessible.',
			]);
		}
		$project_id = $context['id'];
		$project = Project::getProject($project_id);
		if ($project == null) {
			return View::make('pages/error', ['message' => "Project $project_id not found!"]);
		}

		$this->resetChatbotLlmDiagnostics();

		$query = trim(urldecode($query));
		if ($query == '') {
			return View::make('pages/error', ['message' => 'Please enter a query.']);
		}

		// Safety path: for survival queries, trust query gene token over LLM tool arguments.
		if ($this->isSurvivalLikeQuery($query)) {
			$rawSurvivalGene = $this->extractRawGeneTokenFromQuery($query);
			if ($rawSurvivalGene != null) {
				$survivalGene = $this->resolveGeneSymbol($rawSurvivalGene);
				if ($survivalGene == null) {
					$survivalGene = strtoupper($rawSurvivalGene);
				}
				$trace = $this->buildChatbotTrace('regex');
				Log::info('Chatbot survival short-circuit resolved from query token.', [
					'project_id' => (int)$project_id,
					'query' => $query,
					'gene' => $survivalGene,
				]);
				$url = url('/viewSurvivalByExpression/' . $project_id . '/' . $survivalGene . '/Y');
				return Redirect::to($this->appendChatbotTraceToUrl($url, $trace));
			}
		}

		// Queries spanning several data types skip the single-intent rules and go straight
		// to the multi-tool LLM+MCP path so their results can be merged.
		if ($this->isMultiAspectQuery($query)) {
			$multiResult = $this->runMcpWithLlmToolSelection($project_id, $query);
			$multiView = $this->renderChatbotMcpResult($project_id, $query, $multiResult, $this->buildChatbotTrace(
				'llm',
				$this->chatbotLlmTrace['provider'] ?? null,
				$this->chatbotLlmTrace['model'] ?? null
			));
			if ($multiView !== null) {
				Log::info('Chatbot resolved by multi-aspect LLM+MCP path.', [
					'project_id' => (int)$project_id,
					'query' => $query,
					'action' => is_array($multiResult) ? ($multiResult['action'] ?? null) : null,
				]);
				return $multiView;
			}
		}

		// Primary path: deterministic rule-based intent extraction
		$intent = $this->extractIntentByRules($query);
		if ($intent != null) {
			$trace = $this->buildChatbotTrace('regex');
			Log::info('Chatbot resolved by rule-based intent.', [
				'project_id' => (int)$project_id,
				'query' => $query,
				'action' => $intent['action'] ?? null,
			]);
			$mcpArguments = ['project_id' => (int)$project_id];
			if (($intent['action'] ?? null) === 'get_pathogeic_mutations') {
				$mcpArguments['diagnosis'] = $intent['diagnosis'] ?? null;
				$mcpArguments['gene_id'] = $intent['gene_id'] ?? null;
			} else {
				$mcpArguments['gene'] = $intent['gene'];
			}
			if (isset($intent['type'])) {
				$mcpArguments['type'] = $intent['type'];
			}
			if (isset($intent['plot_type'])) {
				$mcpArguments['plot_type'] = $intent['plot_type'];
			}
			if (isset($intent['group_by'])) {
				$mcpArguments['group_by'] = $intent['group_by'];
			}
			if (isset($intent['dataset_scope'])) {
				$mcpArguments['dataset_scope'] = $intent['dataset_scope'];
			}
			if (isset($intent['transform'])) {
				$mcpArguments['transform'] = $intent['transform'];
			}
			if (isset($intent['group_order'])) {
				$mcpArguments['group_order'] = $intent['group_order'];
			}
			$mcpResult = $this->callOncoMcpTool($intent['action'], $mcpArguments);
			if (is_array($mcpResult)) {
				if (($mcpResult['status'] ?? null) === 'error') {
					return View::make('pages/error_no_header', ['message' => $mcpResult['message'] ?? 'MCP tool execution failed.']);
				}
				if (isset($mcpResult['display_type']) && $mcpResult['display_type'] === 'correlation_table') {
					return $this->displayCorrelationResult($project_id, $mcpResult, $trace);
				}
				if ($this->isGenericTableResult($mcpResult)) {
					return $this->displayTableResult($project_id, $mcpResult, $trace);
				}
				if (isset($mcpResult['display_type']) && $mcpResult['display_type'] === 'expression_data_json') {
					return $this->displayExpressionResult($project_id, $query, $mcpResult, $trace);
				}
				if (isset($mcpResult['redirect_url'])) {
					return Redirect::to($this->appendChatbotTraceToUrl($mcpResult['redirect_url'], $trace));
				}
			}
			Log::warning('MCP tool call failed on rule-based intent; using deterministic page fallback.', [
				'project_id' => $project_id,
				'action' => $intent['action'],
				'gene' => $intent['gene'] ?? ($intent['gene_id'] ?? null),
			]);

			// Secondary recovery path: attempt LLM+MCP tool selection before final deterministic fallback.
			$llmMcpResult = $this->runMcpWithLlmToolSelection($project_id, $query);
			if (is_array($llmMcpResult)) {
				$llmTrace = $this->buildChatbotTrace(
					'llm',
					$this->chatbotLlmTrace['provider'] ?? null,
					$this->chatbotLlmTrace['model'] ?? null
				);
				Log::info('Chatbot recovered via LLM+MCP after rule-based MCP failure.', [
					'project_id' => (int)$project_id,
					'query' => $query,
					'action' => $llmMcpResult['action'] ?? null,
				]);
				if (($llmMcpResult['status'] ?? null) === 'error') {
					return View::make('pages/error_no_header', ['message' => $llmMcpResult['message'] ?? 'MCP tool execution failed.']);
				}
				if (isset($llmMcpResult['display_type']) && $llmMcpResult['display_type'] === 'correlation_table') {
					return $this->displayCorrelationResult($project_id, $llmMcpResult, $llmTrace);
				}
				if ($this->isGenericTableResult($llmMcpResult)) {
					return $this->displayTableResult($project_id, $llmMcpResult, $llmTrace);
				}
				if (isset($llmMcpResult['display_type']) && $llmMcpResult['display_type'] === 'expression_data_json') {
					return $this->displayExpressionResult($project_id, $query, $llmMcpResult, $llmTrace);
				}
				if (isset($llmMcpResult['redirect_url'])) {
					return Redirect::to($this->appendChatbotTraceToUrl($llmMcpResult['redirect_url'], $llmTrace));
				}
			}
			$trace = $this->appendLastLlmErrorToTrace($trace);
			return $this->runProjectChatbotFallback($project_id, $intent, $trace);
		}

		// Secondary path: MCP initialize -> tools/list -> LLM selects tool -> tools/call
		$mcpResult = $this->runMcpWithLlmToolSelection($project_id, $query);
		if (is_array($mcpResult)) {
			$trace = $this->buildChatbotTrace(
				'llm',
				$this->chatbotLlmTrace['provider'] ?? null,
				$this->chatbotLlmTrace['model'] ?? null
			);
			Log::info('Chatbot resolved by LLM+MCP fallback path.', [
				'project_id' => (int)$project_id,
				'query' => $query,
				'action' => $mcpResult['action'] ?? null,
			]);
			if (($mcpResult['status'] ?? null) === 'error') {
				return View::make('pages/error_no_header', ['message' => $mcpResult['message'] ?? 'MCP tool execution failed.']);
			}
			if (isset($mcpResult['display_type']) && $mcpResult['display_type'] === 'correlation_table') {
				return $this->displayCorrelationResult($project_id, $mcpResult, $trace);
			}
			if ($this->isGenericTableResult($mcpResult)) {
				return $this->displayTableResult($project_id, $mcpResult, $trace);
			}
			if (isset($mcpResult['display_type']) && $mcpResult['display_type'] === 'expression_data_json') {
				return $this->displayExpressionResult($project_id, $query, $mcpResult, $trace);
			}
			if (isset($mcpResult['redirect_url'])) {
				return Redirect::to($this->appendChatbotTraceToUrl($mcpResult['redirect_url'], $trace));
			}
		}

		// Deterministic safety net: if the LLM+MCP path produced nothing renderable but the
		// query is clearly asking for pathogenic mutations, render the generic table directly
		// (display_type => 'table') without depending on the MCP round trip or OPcache freshness.
		$pathogenicIntent = $this->extractPathogenicMutationIntentFromQuery($query);
		if ($pathogenicIntent !== null) {
			try {
				$topGeneOnly = $pathogenicIntent['top_gene'] ?? false;
				$project = Project::getProject($project_id);
				$table = $this->getPathogeicMutations(
					$project_id,
					$pathogenicIntent['diagnosis'] ?? 'null',
					$pathogenicIntent['gene_id'] ?? 'null',
					$topGeneOnly
				);
				$pathogenicTrace = $this->buildChatbotTrace('regex');
				Log::info('Chatbot rendered pathogenic mutations via deterministic safety net.', [
					'project_id' => (int)$project_id,
					'query' => $query,
					'diagnosis' => $pathogenicIntent['diagnosis'] ?? null,
					'gene_id' => $pathogenicIntent['gene_id'] ?? null,
					'top_gene' => $topGeneOnly,
				]);
				return $this->displayTableResult($project_id, [
					'status' => 'success',
					'action' => 'get_pathogeic_mutations',
					'data_type' => 'table',
					'display_type' => 'table',
					'table_json' => json_encode($table, JSON_UNESCAPED_SLASHES),
					'order' => $topGeneOnly ? [[2, 'desc']] : null,
					'title' => $topGeneOnly ? 'Top Gene by Pathogenic Mutations' : 'Pathogenic Mutations',
					'project_name' => $project === null ? '' : $project->name,
					'summary' => $topGeneOnly
						? 'Gene with the most pathogenic mutations for the requested diagnosis.'
						: 'Pathogenic mutations matching the requested diagnosis and gene ID.',
				], $pathogenicTrace);
			} catch (\Throwable $e) {
				Log::error('Pathogenic mutation safety-net fallback failed.', [
					'project_id' => (int)$project_id,
					'diagnosis' => $pathogenicIntent['diagnosis'] ?? null,
					'gene_id' => $pathogenicIntent['gene_id'] ?? null,
					'message' => $e->getMessage(),
				]);
				return View::make('pages/error_no_header', [
					'message' => 'Pathogenic mutation query failed: ' . $e->getMessage(),
				]);
			}
		}

		$llmErrorSuffix = '';
		if (($this->chatbotLlmLastError['provider'] ?? null) != null) {
			$llmErrorSuffix = ' | LLM failure: ' . $this->chatbotLlmLastError['provider'];
			if (($this->chatbotLlmLastError['code'] ?? null) != null) {
				$llmErrorSuffix .= ' code=' . $this->chatbotLlmLastError['code'];
			}
			if (($this->chatbotLlmLastError['status'] ?? null) != null) {
				$llmErrorSuffix .= ' status=' . $this->chatbotLlmLastError['status'];
			}
		}

		// BUILD MARKER: pathogenic-fix-v3. If you see this marker in the error message,
		// the running instance IS executing the current edited code and we have a real
		// logic bug. If you do NOT see this marker, the running instance is stale
		// (deployment/OPcache) and the edits are not being executed.
		Log::warning('Chatbot reached FINAL fallback (pathogenic-fix-v3).', [
			'project_id' => (int)$project_id,
			'query' => $query,
		]);

		return View::make('pages/error_no_header', ['message' => '[pathogenic-fix-v3] Unable to determine intent from query. Try: please show me the expression of FGFR4' . $llmErrorSuffix]);
	}

	private function runProjectChatbotFallback($project_id, $intent, $trace = []) {
		if ($intent['action'] == 'get_pathogeic_mutations') {
			try {
				$project = Project::getProject($project_id);
				$table = $this->getPathogeicMutations(
					$project_id,
					$intent['diagnosis'] ?? 'null',
					$intent['gene_id'] ?? 'null'
				);
				return $this->displayTableResult($project_id, [
					'status' => 'success',
					'action' => 'get_pathogeic_mutations',
					'data_type' => 'table',
					'table_json' => json_encode($table, JSON_UNESCAPED_SLASHES),
					'title' => 'Pathogenic Mutations',
					'project_name' => $project === null ? '' : $project->name,
					'summary' => 'Pathogenic mutations matching the requested diagnosis and gene ID.',
				], $trace);
			} catch (\Throwable $e) {
				Log::error('Pathogenic mutation fallback failed.', [
					'project_id' => (int)$project_id,
					'diagnosis' => $intent['diagnosis'] ?? null,
					'gene_id' => $intent['gene_id'] ?? null,
					'message' => $e->getMessage(),
				]);
				return View::make('pages/error_no_header', [
					'message' => 'Pathogenic mutation query failed: ' . $e->getMessage(),
				]);
			}
		}

		if ($intent['action'] == 'survival_by_expression') {
			$url = url('/viewSurvivalByExpression/' . $project_id . '/' . $intent['gene'] . '/Y');
			return Redirect::to($this->appendChatbotTraceToUrl($url, $trace));
		}

		if ($intent['action'] == 'expression_by_gene') {
			$url = url('/viewProjectExpressionByGene/' . $project_id . '/' . $intent['gene']);
			return Redirect::to($this->appendChatbotTraceToUrl($url, $trace));
		}

		if ($intent['action'] == 'mutation_by_gene') {
			$url = url('/viewVarAnnotationByGene/' . $project_id . '/' . $intent['gene'] . '/' . $intent['type'] . '/0');
			return Redirect::to($this->appendChatbotTraceToUrl($url, $trace));
		}

		if ($intent['action'] == 'fusion_by_gene') {
			$url = url('/viewFusionGenes/' . $project_id . '/' . $intent['gene']);
			return Redirect::to($this->appendChatbotTraceToUrl($url, $trace));
		}

		if ($intent['action'] == 'cnv_by_gene') {
			$url = url('/viewCNVByGene/' . $project_id . '/' . $intent['gene'] . '/Project');
			return Redirect::to($this->appendChatbotTraceToUrl($url, $trace));
		}

		if ($intent['action'] == 'correlation_by_gene') {
			// For correlation, we need to get the data and display it
			$project = Project::getProject($project_id);
			if ($project != null) {
				$gene = strtoupper(trim($intent['gene']));
				$geneObj = Gene::getGene($gene);
				if ($geneObj != null) {
					$geneSymbol = $geneObj->getSymbol();
					list($corrPositive, $corrNegative) = $project->getCorrelation($geneSymbol, 0.2, 'refseq', 'pearson', 'tpm') ?? [[], []];
					$geneInfos = Gene::getGenesInfo();
					
					// Format correlation data
					$allCorrelations = [];
					foreach ($corrPositive as $geneId => $coeff) {
						$symbol = $geneId;
						if (array_key_exists($geneId, $geneInfos) && isset($geneInfos[$geneId]->symbol) && $geneInfos[$geneId]->symbol != '') {
							$symbol = $geneInfos[$geneId]->symbol;
						}
						$allCorrelations[] = [$symbol, $geneId, $coeff, 'Positive'];
					}
					foreach ($corrNegative as $geneId => $coeff) {
						$symbol = $geneId;
						if (array_key_exists($geneId, $geneInfos) && isset($geneInfos[$geneId]->symbol) && $geneInfos[$geneId]->symbol != '') {
							$symbol = $geneInfos[$geneId]->symbol;
						}
						$allCorrelations[] = [$symbol, $geneId, $coeff, 'Negative'];
					}
					
					if (count($allCorrelations) > 0) {
						$mcpResult = [
							'status' => 'success',
							'action' => 'correlation_by_gene',
							'project_id' => $project_id,
							'gene' => $geneSymbol,
							'project_name' => $project->name,
							'method' => 'pearson',
							'value_type' => 'tpm',
							'cutoff' => 0.2,
							'correlation_data' => $allCorrelations,
							'display_type' => 'correlation_table',
							'summary' => "Found " . count($allCorrelations) . " genes correlated with $geneSymbol",
						];
						return $this->displayCorrelationResult($project_id, $mcpResult, $trace);
					}
				}
			}
		}

		return View::make('pages/error_no_header', ['message' => 'Query type is not supported yet.']);
	}

	/**
	 * True when a query asks for more than one kind of genomic data at once, for example
	 * "show me the structure variation of TP53 and pathogenic mutation and its expression
	 * level". Such queries need several MCP tools, so the single-intent rules are skipped.
	 */
	private function isMultiAspectQuery($query) {
		$lower = strtolower((string)$query);
		if (preg_match('/\balterations?\b/', $lower) === 1) {
			return true;
		}

		$aspects = [
			'/\bexpressions?\b|\bexpressed\b/',
			'/\bmutations?\b|\bvariants?\b|\bpathogenic\b/',
			'/\bfusions?\b|\bstructur\w*\s+variations?\b|\bstructural\s+variants?\b/',
			'/\bcnv\b|\bcopy\s+number\b|\bamplifications?\b|\bdeletions?\b/',
		];
		$hits = 0;
		foreach ($aspects as $pattern) {
			if (preg_match($pattern, $lower) === 1) {
				$hits++;
			}
		}

		return $hits >= 2;
	}

	/**
	 * Render whatever an MCP result represents, or null when it is not renderable.
	 */
	private function renderChatbotMcpResult($project_id, $query, $mcpResult, $trace = []) {
		if (!is_array($mcpResult) || ($mcpResult['status'] ?? null) === 'error') {
			return null;
		}
		if (($mcpResult['display_type'] ?? null) === 'correlation_table') {
			return $this->displayCorrelationResult($project_id, $mcpResult, $trace);
		}
		if ($this->isGenericTableResult($mcpResult)) {
			return $this->displayTableResult($project_id, $mcpResult, $trace);
		}
		if (($mcpResult['display_type'] ?? null) === 'expression_data_json') {
			return $this->displayExpressionResult($project_id, $query, $mcpResult, $trace);
		}
		if (isset($mcpResult['redirect_url'])) {
			return Redirect::to($this->appendChatbotTraceToUrl($mcpResult['redirect_url'], $trace));
		}

		return null;
	}

	protected function isGenericTableResult($mcpResult) {
		if (!is_array($mcpResult)) {
			return false;
		}

		if (($mcpResult['data_type'] ?? ($mcpResult['display_type'] ?? null)) === 'table') {
			return true;
		}

		return array_key_exists('table_json', $mcpResult) || array_key_exists('table', $mcpResult);
	}

	private function displayCorrelationResult($project_id, $mcpResult, $trace = []) {
		$project = Project::getProject($project_id);
		if ($project === null) {
			return View::make('pages/error_no_header', ['message' => 'Project not found.']);
		}

		if ($mcpResult['status'] !== 'success' && $mcpResult['status'] !== 'no_data') {
			return View::make('pages/error_no_header', ['message' => $mcpResult['message'] ?? 'Unknown error occurred.']);
		}

		if ($mcpResult['status'] === 'no_data') {
			return View::make('pages/error_no_header', ['message' => $mcpResult['message'] ?? 'No correlation data available.']);
		}

		$correlationData = $mcpResult['correlation_data'] ?? [];
		$gene = $mcpResult['gene'];
		$method = $mcpResult['method'] ?? 'pearson';
		$valueType = $mcpResult['value_type'] ?? 'tpm';
		$cutoff = $mcpResult['cutoff'] ?? 0.2;

		// Get available genome versions from project
		$genomeVersionsRaw = $project->getGenomeVersion();
		$genomeVersions = ['hg19']; // default fallback
		
		if (!empty($genomeVersionsRaw)) {
			// Convert string to array if needed
			if (is_string($genomeVersionsRaw)) {
				// Handle comma-separated or space-separated string
				$genomeVersions = array_filter(array_map('trim', preg_split('/[,\s]+/', $genomeVersionsRaw)));
			} elseif (is_array($genomeVersionsRaw)) {
				$genomeVersions = $genomeVersionsRaw;
			}
		}

		return View::make('pages/chatbotCorrelationResult', [
			'project' => $project,
			'gene' => $gene,
			'method' => $method,
			'value_type' => $valueType,
			'cutoff' => $cutoff,
			'correlation_data' => $correlationData,
			'summary' => $mcpResult['summary'] ?? '',
			'genome_versions' => $genomeVersions,
			'trace_mode' => $trace['mode'] ?? null,
			'trace_provider' => $trace['provider'] ?? null,
			'trace_model' => $trace['model'] ?? null,
		]);
	}

	private function displayTableResult($project_id, $mcpResult, $trace = []) {
		$project = Project::getProject($project_id);
		if ($project === null) {
			return View::make('pages/error_no_header', ['message' => 'Project not found.']);
		}

		$status = (string)($mcpResult['status'] ?? 'success');
		if ($status !== 'success') {
			return View::make('pages/error_no_header', [
				'message' => $mcpResult['message'] ?? 'Table data is unavailable.',
			]);
		}

		$tableJson = $mcpResult['table_json'] ?? ($mcpResult['table'] ?? null);
		if (is_array($tableJson) || is_object($tableJson)) {
			$tableJson = json_encode($tableJson, JSON_UNESCAPED_SLASHES);
		}
		if (!is_string($tableJson) || trim($tableJson) === '') {
			return View::make('pages/error_no_header', ['message' => 'The MCP tool returned no table JSON.']);
		}

		return View::make('pages/chatbotTableResult', [
			'title' => $mcpResult['title'] ?? 'Results',
			'summary' => $mcpResult['summary'] ?? '',
			'project_name' => $mcpResult['project_name'] ?? $project->name,
			'table_json' => $tableJson,
			'table_order' => $mcpResult['order'] ?? null,
			'trace_mode' => $trace['mode'] ?? null,
			'trace_provider' => $trace['provider'] ?? null,
			'trace_model' => $trace['model'] ?? null,
		]);
	}

	protected function displayScopedTableResult($context, $mcpResult, $trace = []) {
		$status = (string)($mcpResult['status'] ?? 'success');
		if ($status !== 'success') {
			return View::make('pages/error_no_header', [
				'message' => $mcpResult['message'] ?? 'Table data is unavailable.',
			]);
		}

		$tableJson = $mcpResult['table_json'] ?? ($mcpResult['table'] ?? null);
		if (is_array($tableJson) || is_object($tableJson)) {
			$tableJson = json_encode($tableJson, JSON_UNESCAPED_SLASHES);
		}
		if (!is_string($tableJson) || trim($tableJson) === '') {
			return View::make('pages/error_no_header', ['message' => 'The MCP tool returned no table JSON.']);
		}

		return View::make('pages/chatbotTableResult', [
			'title' => $mcpResult['title'] ?? 'Results',
			'summary' => $mcpResult['summary'] ?? '',
			'project_name' => $mcpResult['project_name']
				?? $mcpResult['cancer_type_id']
				?? ($context['name'] ?? 'Clinomics'),
			'table_json' => $tableJson,
			'table_order' => $mcpResult['order'] ?? null,
			'trace_mode' => $trace['mode'] ?? null,
			'trace_provider' => $trace['provider'] ?? null,
			'trace_model' => $trace['model'] ?? null,
		]);
	}

	private function displayExpressionResult($project_id, $query, $mcpResult, $trace = []) {
		$project = Project::getProject($project_id);
		if ($project === null) {
			return View::make('pages/error_no_header', ['message' => 'Project not found.']);
		}
		if (($mcpResult['status'] ?? null) !== 'success') {
			return View::make('pages/error_no_header', ['message' => $mcpResult['message'] ?? 'Expression data is unavailable.']);
		}

		// Keep transform behavior deterministic from raw user wording. Compacting the query
		// makes zscore, z-score, z_score and z score equivalent.
		$compactQuery = strtolower((string)preg_replace('/[^A-Za-z0-9]+/', '', (string)$query));
		if (strpos($compactQuery, 'zscore') !== false || strpos($compactQuery, 'standardscore') !== false || strpos($compactQuery, 'standarddeviation') !== false) {
			$mcpResult['transform'] = 'zscore';
		} elseif (preg_match('/\blog\s*2\b/i', $query) === 1) {
			$mcpResult['transform'] = 'log2p1';
		}

		$requestedPlotType = strtolower(trim((string)($mcpResult['plot_type'] ?? 'violin')));
		if (in_array($requestedPlotType, ['heatmap', 'heat map', 'heat_map'], true)) {
			$gene = trim((string)($mcpResult['gene'] ?? ''));
			if ($gene === '') {
				return View::make('pages/error_no_header', ['message' => 'Heatmap request is missing gene name.']);
			}
			// Use the existing/stable heatmap implementation in viewExpression.
			return $this->viewExpressionByGene($project_id, $gene);
		}

		$metadataSelection = $this->selectExpressionMetadataByLlm($query, $mcpResult);
		$metadataPrompt = $metadataSelection['prompt'] ?? 'Not sent: metadata was resolved deterministically from meta_data.attr_list.';
		if (!empty($mcpResult['group_by']) && $metadataSelection === null) {
			return View::make('pages/error_no_header', [
				'message' => 'Unable to match "' . $mcpResult['group_by'] . '" to a valid metadata attribute. No fallback grouping was used.',
			]);
		}
		if ($metadataSelection !== null) {
			$selectedRows = $this->buildExpressionRowsFromMetadataSelection($mcpResult, $metadataSelection['selections']);
			if (!empty($selectedRows)) {
				$mcpResult['plot_rows'] = $selectedRows;
				$mcpResult['metadata_fields'] = array_map(function ($selection) {
					return $selection['label'];
				}, $metadataSelection['selections']);
				$mcpResult['metadata_selection_reason'] = $metadataSelection['reason'];
			}
		}

		$plotRows = $mcpResult['plot_rows'] ?? [];
		if (!is_array($plotRows) || empty($plotRows)) {
			return View::make('pages/error_no_header', ['message' => 'No expression values were returned for the requested gene.']);
		}

		$plotSpec = $this->buildExpressionPlotlySpec($query, $mcpResult, $plotRows);
		if ($plotSpec === null) {
			$message = 'The expression JSON was returned, but a plot specification could not be built.';
			$errorCode = $this->expressionPlotLastError['code'] ?? 'unknown';
			$isConnectionIssue = in_array($errorCode, ['connection_error', 'empty_response', 'no_provider', 'cooldown', 'exception', 'rate_limit_exceeded'], true);
			if (($this->expressionPlotLastError['message'] ?? null) !== null) {
				$message .= ' ' . $this->expressionPlotLastError['message'];
			}
			return View::make('pages/error_no_header', [
				'message' => $message,
				'error_code' => $errorCode,
				'is_connection_issue' => $isConnectionIssue,
				'tried_providers' => $this->expressionPlotLastError['tried_providers'] ?? [],
				'console_error' => $this->expressionPlotLastError,
			]);
		}

		$llmTrace = $this->buildChatbotTrace(
			'server',
			'deterministic',
			'plotly'
		);

		return View::make('pages/chatbotExpressionResult', [
			'project' => $project,
			'gene' => $mcpResult['gene'] ?? '',
			'plot_spec' => $plotSpec,
			'plot_type' => $plotSpec['plot_type'] ?? ($mcpResult['plot_type'] ?? 'violin'),
			'group_by' => $mcpResult['group_by'] ?? null,
			'metadata_fields' => $mcpResult['metadata_fields'] ?? [],
			'dataset_scope' => $mcpResult['dataset_scope'] ?? 'all',
			'transform' => $plotSpec['data_transform'] ?? 'none',
			'group_order' => $plotSpec['group_order'] ?? 'none',
			'raw_expression_json' => $mcpResult['expression_data_json'] ?? '{}',
			'trace_mode' => $llmTrace['mode'] ?? null,
			'trace_provider' => $llmTrace['provider'] ?? null,
			'trace_model' => $llmTrace['model'] ?? null,
			'plot_row_count' => count($plotRows),
			'llm_prompts' => [
				'metadata_selection' => $metadataPrompt,
				'plot_presentation' => 'Not sent: the plot is computed deterministically in PHP and rendered by Plotly (native violin/box). The LLM is only used to resolve grouping metadata.',
			],
			'llm_decision_summary' => sprintf(
				'The chart was built deterministically from %d raw expression values using transform "%s" and order "%s". Grouping metadata: %s. %s',
				count($plotRows),
				$plotSpec['data_transform'] ?? 'none',
				$plotSpec['group_order'] ?? 'none',
				implode(', ', array_filter($mcpResult['metadata_fields'] ?? [])) ?: 'none',
				$mcpResult['metadata_selection_reason'] ?? 'The LLM selected an exact metadata label from attr_list.'
			),
		]);
	}

	private function selectExpressionMetadataByLlm($query, $mcpResult) {
		$options = $mcpResult['metadata_options'] ?? [];
		$requestedGroup = trim((string)($mcpResult['group_by'] ?? ''));
		// If the intent parser returned a vague/default field, recover a stronger grouping
		// hint from the raw user query (e.g. "sex", "diagnosis").
		$queryLower = strtolower((string)$query);
		if (strpos($queryLower, 'sex') !== false || strpos($queryLower, 'gender') !== false) {
			$requestedGroup = 'sex';
		} elseif (strpos($queryLower, 'diagnosis') !== false || strpos($queryLower, 'disease') !== false || strpos($queryLower, 'histology') !== false) {
			$requestedGroup = 'diagnosis';
		}
		if ($requestedGroup === '' || !is_array($options) || empty($options)) {
			return null;
		}
		$exactSelections = $this->selectExactExpressionMetadata($requestedGroup, $options);
		if ($exactSelections !== null) {
			return [
				'selections' => $exactSelections,
				'reason' => 'Matched the explicitly requested grouping to an exact metadata label.',
				'prompt' => 'Not sent: the requested grouping matched an exact meta_data.attr_list label.',
			];
		}

		$prompt = "Select one metadata field for the requested grouping.\n" .
			"Requested grouping: {$requestedGroup}\n" .
			"Available attr_list values by dataset: " . json_encode($options, JSON_UNESCAPED_SLASHES) . "\n" .
			"For each dataset, return the zero-based index and exact label from that dataset's list. " .
			"If no field matches, return null for both index and label. Do not default to the first item.\n" .
			'Return JSON only: {"selections":{"dataset":{"index":0,"label":"exact label"}},"reason":"brief reason"}';

		try {
			$text = $this->dispatchLlmTextRequest($prompt, Config::get('services.llm', []));
			$parsed = $this->parseIntentJsonFromText($text);
			if (!is_array($parsed) || !is_array($parsed['selections'] ?? null)) {
				return null;
			}

			$selections = [];
			foreach ($options as $dataset => $labels) {
				$choice = $parsed['selections'][$dataset] ?? null;
				if (!is_array($choice)) {
					return null;
				}
				if (($choice['index'] ?? null) === null && ($choice['label'] ?? null) === null) {
					$selections[$dataset] = ['index' => null, 'label' => 'Not available'];
					continue;
				}
				if (!is_numeric($choice['index'] ?? null)) {
					return null;
				}
				$index = (int)$choice['index'];
				if (!array_key_exists($index, $labels) || (string)($choice['label'] ?? '') !== (string)$labels[$index]) {
					return null;
				}
				$selections[$dataset] = ['index' => $index, 'label' => (string)$labels[$index]];
			}
			Log::info('LLM selected expression metadata attributes.', [
				'requested_group' => $requestedGroup,
				'selections' => $selections,
			]);

			return [
				'selections' => $selections,
				'reason' => trim((string)($parsed['reason'] ?? 'The LLM selected the closest matching metadata label.')),
				'prompt' => $prompt,
			];
		} catch (\Throwable $e) {
			Log::warning('LLM metadata selection failed.', ['message' => $e->getMessage()]);
			return null;
		}
	}

	private function selectExactExpressionMetadata($requestedGroup, $options) {
		$requested = strtolower((string)preg_replace('/[^A-Za-z0-9]+/', '', $requestedGroup));
		$aliasMap = [
			'diagnosis' => ['diagnosis', 'diag', 'disease', 'histology', 'tumortype', 'cancertype', 'oncotree'],
			'sex' => ['sex', 'gender'],
			'tissue' => ['tissue', 'site', 'origin'],
			'stage' => ['stage', 'tumorstage', 'clinicalstage'],
		];
		$requestedAliases = [$requested];
		foreach ($aliasMap as $canonical => $aliases) {
			if ($requested === $canonical || in_array($requested, $aliases, true)) {
				$requestedAliases = array_values(array_unique(array_merge([$canonical], $aliases)));
				break;
			}
		}
		$selections = [];
		$hasExactMatch = false;
		foreach ($options as $dataset => $labels) {
			$matched = null;
			foreach ($labels as $index => $label) {
				$segments = preg_split('/[|\/]+/', (string)$label);
				$normalizedSegments = array_map(function ($segment) {
					return strtolower((string)preg_replace('/[^A-Za-z0-9]+/', '', (string)$segment));
				}, $segments);
				$normalizedLabel = strtolower((string)preg_replace('/[^A-Za-z0-9]+/', '', (string)$label));
				$labelMatchesAlias = false;
				foreach ($requestedAliases as $alias) {
					if ($alias !== '' && (
						$normalizedLabel === $alias
						|| strpos($normalizedLabel, $alias) !== false
						|| in_array($alias, $normalizedSegments, true)
					)) {
						$labelMatchesAlias = true;
						break;
					}
				}
				if ($labelMatchesAlias) {
					$matched = ['index' => (int)$index, 'label' => (string)$label];
					$hasExactMatch = true;
					break;
				}
			}
			if ($matched === null) {
				$matched = ['index' => null, 'label' => 'Not available'];
			}
			$selections[$dataset] = $matched;
		}

		return $hasExactMatch ? $selections : null;
	}

	private function buildExpressionRowsFromMetadataSelection($mcpResult, $selections) {
		$payload = json_decode((string)($mcpResult['expression_data_json'] ?? ''), true);
		if (!is_array($payload)) {
			return [];
		}

		$gene = (string)($mcpResult['gene'] ?? '');
		$genomeVersion = (string)($mcpResult['genome_version'] ?? 'hg19');
		$scope = (string)($mcpResult['dataset_scope'] ?? 'all');
		$rows = [];
		foreach ($selections as $dataset => $selection) {
			if (($scope === 'tumor' && $dataset !== 'tumor') || ($scope === 'normal' && $dataset !== 'normal')) {
				continue;
			}
			$projectData = $payload[$dataset . '_project_data'] ?? [];
			$samples = $projectData['samples'] ?? [];
			$patients = $projectData['patients'] ?? [];
			$expressionByGenome = $projectData['exp_data'][$gene] ?? [];
			$expressionValues = $expressionByGenome[$genomeVersion] ?? reset($expressionByGenome);
			if (!is_array($expressionValues)) {
				continue;
			}
			$metadata = $projectData['meta_data'] ?? [];
			$metadataIndex = $selection['index'] === null ? null : (int)$selection['index'];
			foreach ($samples as $index => $sample) {
				if (!isset($expressionValues[$index]) || !is_numeric($expressionValues[$index])) {
					continue;
				}
				$value = $metadataIndex === null
					? 'N/A'
					: trim((string)($metadata['data'][$sample][$metadataIndex] ?? 'N/A'));
				$value = $value !== '' ? $value : 'N/A';
				$rawExpression = (float)$expressionValues[$index];
				$rows[] = [
					'sample' => (string)$sample,
					'patient_id' => (string)($patients[$sample] ?? ''),
					'expression' => $rawExpression,
					'raw_expression' => $rawExpression,
					'dataset' => $dataset,
					'metadata_field' => (string)$selection['label'],
					'metadata_value' => $value,
					// Do not prefix with Tumor/Normal; group strictly by requested metadata value.
					'group' => $value,
				];
			}
		}

		return $rows;
	}

	/**
	 * Build the expression plot deterministically in PHP and hand raw values to Plotly.
	 * Plotly's native violin/box traces compute the kernel-density / quartiles themselves,
	 * so there is no need to ask an LLM to hand-compute polygon coordinates (the source of
	 * the previous "contract violation" failures). The grouping intent (transform, plot
	 * type, group order, grouping field) is already resolved upstream into $mcpResult.
	 */
	private function buildExpressionPlotlySpec($query, $mcpResult, $plotRows) {
		$this->expressionPlotLastError = null;

		$requestedTransform = strtolower(trim((string)($mcpResult['transform'] ?? 'none')));
		$queryLower = strtolower((string)$query);
		$normalizedRequestedTransform = strtolower((string)preg_replace('/[^A-Za-z0-9]+/', '', $requestedTransform));
		$effectiveTransform = $normalizedRequestedTransform === 'zscore' ? 'zscore' : $requestedTransform;
		$compactQuery = strtolower((string)preg_replace('/[^A-Za-z0-9]+/', '', (string)$query));
		// Treat explicit query wording as authoritative so transform cannot be lost
		// if MCP arguments were normalized incorrectly upstream.
		if (strpos($compactQuery, 'zscore') !== false || strpos($compactQuery, 'standardscore') !== false || strpos($compactQuery, 'standarddeviation') !== false) {
			$effectiveTransform = 'zscore';
		} elseif (preg_match('/\blog\s*2\b/i', $query) === 1) {
			$effectiveTransform = 'log2p1';
		} elseif (!in_array($effectiveTransform, ['none', 'log2p1', 'zscore'], true)) {
			$effectiveTransform = 'none';
		}
		$requestedPlotType = strtolower(trim((string)($mcpResult['plot_type'] ?? 'violin')));
		$requestedGroupOrder = strtolower(trim((string)($mcpResult['group_order'] ?? 'none')));
		$effectiveGroupOrder = 'none';
		if (in_array($requestedGroupOrder, ['median_asc', 'asc', 'ascending'], true)) {
			$effectiveGroupOrder = 'median_asc';
		} elseif (in_array($requestedGroupOrder, ['median_desc', 'desc', 'descending'], true)) {
			$effectiveGroupOrder = 'median_desc';
		} elseif (strpos($queryLower, 'descending') !== false || strpos($queryLower, 'desc') !== false) {
			$effectiveGroupOrder = 'median_desc';
		} elseif (strpos($queryLower, 'ascending') !== false || strpos($queryLower, 'asc') !== false) {
			$effectiveGroupOrder = 'median_asc';
		}
		$gene = trim((string)($mcpResult['gene'] ?? ''));

		if (in_array($requestedPlotType, ['boxplot', 'box', 'box-plot'], true)) {
			$plotType = 'box';
		} elseif (in_array($requestedPlotType, ['barplot', 'bar', 'bar-plot'], true)) {
			$plotType = 'bar';
		} elseif (in_array($requestedPlotType, ['column', 'columnplot', 'column-plot'], true)) {
			$plotType = 'column';
		} else {
			$plotType = 'violin';
		}

		// Group raw values by their group label.
		$rawGroups = [];
		foreach ($plotRows as $row) {
			$name = trim((string)($row['group'] ?? 'N/A'));
			if ($name === '') {
				$name = 'N/A';
			}
			$value = $row['raw_expression'] ?? ($row['expression'] ?? null);
			if ($value === null || !is_numeric($value)) {
				continue;
			}
			$rawGroups[$name][] = (float)$value;
		}
		if (empty($rawGroups)) {
			$this->noteExpressionPlotError('no_data', 'No numeric expression values were available to plot.');
			return null;
		}

		// Apply the requested transform to every value (log2(x + 1) or identity).
		$groups = [];
		$allRawValues = [];
		foreach ($rawGroups as $values) {
			foreach ($values as $v) {
				$allRawValues[] = (float)$v;
			}
		}
		$globalMean = 0.0;
		$globalStdDev = 0.0;
		if (!empty($allRawValues)) {
			$globalMean = array_sum($allRawValues) / count($allRawValues);
			$varianceSum = 0.0;
			foreach ($allRawValues as $v) {
				$delta = $v - $globalMean;
				$varianceSum += ($delta * $delta);
			}
			$globalStdDev = sqrt($varianceSum / count($allRawValues));
		}
		foreach ($rawGroups as $name => $values) {
			if ($effectiveTransform === 'log2p1') {
				$groups[$name] = array_map(function ($v) {
					return log($v + 1, 2);
				}, $values);
			} elseif ($effectiveTransform === 'zscore') {
				if ($globalStdDev > 0.0) {
					$groups[$name] = array_map(function ($v) use ($globalMean, $globalStdDev) {
						return ($v - $globalMean) / $globalStdDev;
					}, $values);
				} else {
					$groups[$name] = array_fill(0, count($values), 0.0);
				}
			} else {
				$groups[$name] = $values;
			}
		}
		$flatTransformedValues = [];
		foreach ($groups as $vals) {
			foreach ($vals as $v) {
				$flatTransformedValues[] = (float)$v;
			}
		}
		$transformedMin = !empty($flatTransformedValues) ? min($flatTransformedValues) : 0.0;
		$transformedMax = !empty($flatTransformedValues) ? max($flatTransformedValues) : 0.0;

		// Compute the median of each group (used for ordering).
		$median = function (array $vals) {
			if (empty($vals)) {
				return 0.0;
			}
			sort($vals);
			$n = count($vals);
			$mid = intdiv($n, 2);
			return ($n % 2 === 0) ? (($vals[$mid - 1] + $vals[$mid]) / 2.0) : $vals[$mid];
		};
		$medians = [];
		foreach ($groups as $name => $vals) {
			$medians[$name] = $median($vals);
		}

		// Order the groups.
		$orderedNames = array_keys($groups);
		if ($effectiveGroupOrder === 'median_asc') {
			usort($orderedNames, function ($a, $b) use ($medians) {
				$cmp = $medians[$a] <=> $medians[$b];
				if ($cmp !== 0) {
					return $cmp;
				}
				return strcasecmp((string)$a, (string)$b);
			});
		} elseif ($effectiveGroupOrder === 'median_desc') {
			usort($orderedNames, function ($a, $b) use ($medians) {
				$cmp = $medians[$b] <=> $medians[$a];
				if ($cmp !== 0) {
					return $cmp;
				}
				return strcasecmp((string)$a, (string)$b);
			});
		}

		$traces = [];
		$plottedMin = $transformedMin;
		$plottedMax = $transformedMax;
		if (in_array($plotType, ['bar', 'column'], true)) {
			$barColors = [];
			foreach ($orderedNames as $name) {
				$hash = sprintf('%u', crc32((string)$name));
				$hue = (int)($hash % 360);
				$barColors[] = sprintf('hsl(%d, 72%%, 52%%)', $hue);
			}
			$barValues = array_map(function ($name) use ($medians) {
				return $medians[$name];
			}, $orderedNames);
			// Bar/column traces display group medians, not individual sample values.
			// Their numeric axis must therefore be based on these plotted values.
			$plottedMin = !empty($barValues) ? min($barValues) : 0.0;
			$plottedMax = !empty($barValues) ? max($barValues) : 0.0;
			$traces[] = $plotType === 'bar'
				? [
					'type' => 'bar',
					'orientation' => 'h',
					'x' => $barValues,
					'y' => $orderedNames,
					'marker' => ['color' => $barColors],
					'hovertemplate' => '%{y}: %{x:.4f}<extra></extra>',
				]
				: [
					'type' => 'bar',
					'x' => $orderedNames,
					'y' => $barValues,
					'marker' => ['color' => $barColors],
					'hovertemplate' => '%{x}: %{y:.4f}<extra></extra>',
				];
		}
		foreach (in_array($plotType, ['bar', 'column'], true) ? [] : $orderedNames as $name) {
			$vals = array_values($groups[$name]);
			$xVals = array_fill(0, count($vals), $name);
			$hash = sprintf('%u', crc32((string)$name));
			$hue = (int)($hash % 360);
			$lineColor = sprintf('hsl(%d, 72%%, 38%%)', $hue);
			$fillColor = sprintf('hsla(%d, 72%%, 52%%, 0.45)', $hue);
			if ($plotType === 'box') {
				$traces[] = [
					'type' => 'box',
					'x' => $xVals,
					'y' => $vals,
					'name' => $name,
					'boxpoints' => 'outliers',
					'boxmean' => true,
					'marker' => ['color' => $fillColor],
					'line' => ['color' => $lineColor],
				];
			} else {
				$traces[] = [
					'type' => 'violin',
					'x' => $xVals,
					'y' => $vals,
					'name' => $name,
					'points' => false,
					// 'hard' clamps the density to the actual data range so the smoothed
					// tail cannot extend below the minimum value (e.g. below 0 for log2p1).
					'spanmode' => 'hard',
					'scalemode' => 'width',
					'width' => 0.82,
					'box' => ['visible' => true],
					'meanline' => ['visible' => true],
					'line' => ['color' => $lineColor],
					'fillcolor' => $fillColor,
				];
			}
		}

		$groupCount = count($orderedNames);
		$yTitle = 'Expression';
		if ($effectiveTransform === 'log2p1') {
			$yTitle = 'log2(expression + 1)';
		} elseif ($effectiveTransform === 'zscore') {
			$yTitle = 'z-score expression';
		}
		$groupByLabel = trim((string)($mcpResult['group_by'] ?? ''));
		$xTitle = $groupByLabel !== '' ? ucfirst($groupByLabel) : 'Group';
		$titleText = ($gene !== '' ? $gene : 'Gene') . ' expression by ' . strtolower($xTitle);

		$layout = [
			'title' => ['text' => $titleText],
			'xaxis' => [
				'title' => ['text' => $xTitle],
				'type' => 'category',
				'categoryorder' => 'array',
				'categoryarray' => $orderedNames,
				'automargin' => true,
				'tickangle' => $groupCount > 6 ? -45 : 0,
			],
			'yaxis' => [
				'title' => ['text' => $yTitle],
				'zeroline' => false,
				'automargin' => true,
			],
			'showlegend' => false,
			'height' => 680,
			'margin' => ['t' => 60, 'r' => 20, 'b' => 100, 'l' => 70],
			'hovermode' => 'closest',
		];
		if ($plotType === 'bar') {
			$layout['xaxis']['title']['text'] = 'Median ' . $yTitle;
			$layout['xaxis']['type'] = 'linear';
			unset($layout['xaxis']['categoryorder'], $layout['xaxis']['categoryarray'], $layout['xaxis']['tickangle']);
			$layout['yaxis']['title']['text'] = $xTitle;
			$layout['yaxis']['type'] = 'category';
			$layout['yaxis']['categoryorder'] = 'array';
			$layout['yaxis']['categoryarray'] = array_reverse($orderedNames);
		} elseif ($plotType === 'column') {
			$layout['yaxis']['title']['text'] = 'Median ' . $yTitle;
		}
		if ($plotType === 'violin') {
			$layout['violingap'] = 0.1;
		} else {
			$layout['boxgap'] = 0.2;
		}
		$valueAxis = $plotType === 'bar' ? 'xaxis' : 'yaxis';
		if ($effectiveTransform === 'log2p1') {
			$layout[$valueAxis]['rangemode'] = 'nonnegative';
		} elseif ($effectiveTransform === 'zscore') {
			$layout[$valueAxis]['rangemode'] = 'normal';
			// Keep z-score axes symmetric around zero: [-M, M], where
			// M is the largest absolute plotted value.
			$symmetricMax = max(abs($plottedMin), abs($plottedMax), 0.1);
			$layout[$valueAxis]['range'] = [-$symmetricMax, $symmetricMax];
		}

		// For many groups, fix the width and let the container scroll horizontally so each
		// violin keeps a readable width instead of being squeezed.
		$perGroupWidth = 45;
		$chartMargins = 160;
		$computedWidth = ($groupCount * $perGroupWidth) + $chartMargins;
		$useFixedWidth = $groupCount > 8 && $computedWidth > 1000;
		if ($useFixedWidth) {
			$layout['width'] = min($computedWidth, 20000);
		}

		$config = [
			'responsive' => !$useFixedWidth,
			'displaylogo' => false,
			'modeBarButtonsToRemove' => ['lasso2d', 'select2d'],
		];

		$summary = sprintf(
			'Rendered a %s plot for %s across %d group%s from %d expression value%s (transform: %s, order: %s). Kernel density / quartiles are computed by Plotly directly from the data.',
			$plotType,
			$gene !== '' ? $gene : 'the gene',
			$groupCount,
			$groupCount === 1 ? '' : 's',
			count($plotRows),
			count($plotRows) === 1 ? '' : 's',
			$effectiveTransform,
			$effectiveGroupOrder
		);
		if ($effectiveTransform === 'zscore') {
			$summary .= sprintf(
				' z-score diagnostics: mean(raw)=%.6f, std(raw)=%.6f, sample range=[%.6f, %.6f], plotted %s range=[%.6f, %.6f].',
				$globalMean,
				$globalStdDev,
				$transformedMin,
				$transformedMax,
				in_array($plotType, ['bar', 'column'], true) ? 'median' : 'sample',
				$plottedMin,
				$plottedMax
			);
		}

		return [
			'title' => $titleText,
			'summary' => $summary,
			'plot_type' => $plotType,
			'data_transform' => $effectiveTransform,
			'group_order' => $effectiveGroupOrder,
			'plotly' => [
				'data' => $traces,
				'layout' => $layout,
				'config' => $config,
			],
		];
	}

	private function generateExpressionPlotSpecByLlm($query, $mcpResult, $plotRows) {
		$this->expressionPlotLastError = null;
		$llmConfig = Config::get('services.llm', []);
		$requestedTransform = strtolower(trim((string)($mcpResult['transform'] ?? 'none')));
		$requestedPlotType = strtolower(trim((string)($mcpResult['plot_type'] ?? '')));
		$requestedGroupOrder = strtolower(trim((string)($mcpResult['group_order'] ?? 'none')));
		$rawGroups = [];
		foreach ($plotRows as $row) {
			$name = trim((string)($row['group'] ?? 'N/A'));
			$rawGroups[$name][] = (float)($row['raw_expression'] ?? $row['expression']);
		}
		$expectedGroupNames = array_keys($rawGroups);
		$expectedGroupCount = count($expectedGroupNames);
		
		// Create a transformation instruction based on requested transform
		$transformInstruction = '';
		if ($requestedTransform === 'log2p1') {
			$transformInstruction = "STEP 1 - TRANSFORM EVERY RAW VALUE:\n" .
				"Transform each raw value in each group using: log2(x + 1) where ln/log means natural log.\n" .
				"For example, if group 'Diagnosis A' has raw values [0, 1, 3, 7, 15]:\n" .
				"  - 0 transforms to log2(0+1) = log2(1) = 0\n" .
				"  - 1 transforms to log2(1+1) = log2(2) = 1\n" .
				"  - 3 transforms to log2(3+1) = log2(4) = 2\n" .
				"  - 7 transforms to log2(7+1) = log2(8) = 3\n" .
				"  - 15 transforms to log2(15+1) = log2(16) = 4\n" .
				"THEN USE THESE TRANSFORMED VALUES [0, 1, 2, 3, 4] FOR ALL CALCULATIONS (density, median, sorting, axis labels).\n";
		} else {
			$transformInstruction = "STEP 1 - USE RAW VALUES AS-IS:\n" .
				"Do NOT transform the values. Use raw expression values directly for all calculations.\n";
		}
		
		$prompt = "Generate the complete Highcharts JSON configuration requested by the user.\n" .
			"User request: {$query}\n" .
			"Gene: " . ($mcpResult['gene'] ?? '') . "\n" .
			"Grouping metadata selected from attr_list: " . json_encode($mcpResult['metadata_fields'] ?? [], JSON_UNESCAPED_SLASHES) . "\n" .
			"Requested plot type (authoritative): {$requestedPlotType}\n" .
			"Requested transform (authoritative): {$requestedTransform}\n" .
			"Requested group order (authoritative): {$requestedGroupOrder}\n" .
			"Grouped RAW expression values (do NOT use these directly - apply transform first): " . json_encode($rawGroups, JSON_UNESCAPED_SLASHES) . "\n\n" .
			$transformInstruction .
			"STEP 2 - ORDER GROUPS (if applicable):\n" .
			"If group_order is median_asc or median_desc: compute each group's MEDIAN FROM TRANSFORMED VALUES, then arrange groups by that median (ascending or descending).\n" .
			"Update xAxis.categories with groups in this new order.\n\n" .
			"STEP 3 - CREATE SERIES:\n" .
			"Build Highcharts series using ONLY transformed values (never raw values in any series.data array).\n" .
			"series.data must contain only the transformed values as your data source.\n" .
			"Only use these installed series types: line, spline, area, areaspline, column, bar, pie, scatter, bubble, packedbubble, polygon, boxplot, errorbar, waterfall, gauge, arearange, areasplinerange, and columnrange.\n" .
			"For a violin plot (type polygon): return polygon series (one per group with sufficient data), each named after its group label, with 3-40 points each forming a smooth mirrored kernel-density shape from transformed values. Groups with very few samples (<3) may be omitted.\n" .
			"For a boxplot (type boxplot): return boxplot series (one per group with sufficient data), each named after its group label, using transformed values to calculate quartiles.\n" .
			"Set xAxis.type to 'category', include all group labels (in order) in xAxis.categories, and set plotArea.width to '100%'.\n\n" .
			"STEP 4 - RETURN JSON:\n" .
			"Set data_transform to the exact transform name: 'log2p1' or 'none'.\n" .
			"Set group_order to the exact order type: 'median_asc', 'median_desc', or 'none'.\n" .
			"Set yAxis.title.text appropriately: for log2p1 use 'log2(expression + 1)', for none use 'Expression'.\n" .
			"Return ONLY JSON in this format (no prose):\n" .
			'{"summary":"brief description","data_transform":"log2p1 or none","group_order":"median_asc or median_desc or none","highcharts_options":{"chart":{"type":"column"},"title":{"text":"Gene expression"},"xAxis":{"type":"category","categories":[...]},"yAxis":{"title":{"text":"..."}},"plotArea":{"width":"100%"},"series":[...]}}';

		try {
			$providers = $this->availablePlotLlmProviders($llmConfig);
			if (empty($providers)) {
				$this->noteExpressionPlotError('no_provider', 'No LLM provider is configured with an API key.');
				return null;
			}

			$spec = null;
			$options = null;
			$succeeded = false;
			$lastFailure = null;
			$triedProviders = [];

			foreach ($providers as $provider) {
				$triedProviders[] = $provider;
				$requestPrompt = $prompt;
				$providerSucceeded = false;

				for ($attempt = 0; $attempt < 2; $attempt++) {
					$text = $this->dispatchLlmTextRequest($requestPrompt, $llmConfig, $provider);
					if (!is_string($text) || trim($text) === '') {
						// Empty response usually means a connection/transport problem for this provider.
						// Only trust the recorded error if it actually belongs to THIS provider,
						// otherwise a stale code from an earlier provider gets mis-attributed.
						$providerError = null;
						if (($this->chatbotLlmLastError['provider'] ?? null) === $provider) {
							$providerError = $this->chatbotLlmLastError;
						}
						$connError = $providerError['code'] ?? null;
						$isRateLimit = $connError !== null && stripos((string)$connError, 'rate_limit') !== false;
						if ($isRateLimit) {
							$message = "Provider '{$provider}' hit its API rate limit (rate_limit_exceeded). "
								. "This is a per-minute token limit, not a daily quota — a large request (many groups/points) can trigger it even on the first attempt. Wait a minute and retry, or reduce the number of groups.";
						} else {
							$message = "Provider '{$provider}' returned no response (likely a connection or API error" . ($connError ? ": {$connError}" : '') . ").";
						}
						$lastFailure = [
							'code' => $isRateLimit ? 'rate_limit_exceeded' : 'connection_error',
							'message' => $message,
							'details' => ['provider' => $provider, 'provider_error' => $providerError],
						];
						break; // move on to the next provider
					}
					$spec = $this->parseIntentJsonFromText($text);
					if (!is_array($spec)) {
						$details = [
							'provider' => $provider,
							'response_characters' => strlen($text),
							'json_error' => json_last_error_msg(),
							'response_tail' => mb_substr(trim($text), -400),
						];
						if ($attempt === 0) {
							$requestPrompt = $prompt . "\n\nYour previous response was invalid or truncated JSON. Regenerate it as compact JSON, keep each violin boundary within 40 points per side, omit scatter points, and return no prose.";
							continue;
						}
						$lastFailure = [
							'code' => 'invalid_json',
							'message' => "Provider '{$provider}' returned invalid or truncated JSON after one compact retry.",
							'details' => $details,
						];
						break; // move on to the next provider
					}

					$options = $this->sanitizeLlmHighchartsOptions($spec['highcharts_options'] ?? null);
					if (!is_array($options) || !isset($options['series']) || !is_array($options['series']) || empty($options['series'])) {
						Log::warning('LLM Highcharts response contained no usable series.', ['provider' => $provider]);
						if ($attempt === 0) {
							$requestPrompt = $prompt . "\n\nYour previous JSON contained no usable series. Regenerate the complete compact JSON with non-empty Highcharts polygon series.";
							continue;
						}
						$lastFailure = [
							'code' => 'missing_series',
							'message' => "Provider '{$provider}' returned JSON without a usable Highcharts series array.",
							'details' => ['provider' => $provider],
						];
						break; // move on to the next provider
					}

					$violations = $this->expressionPlotSpecViolations(
						$options,
						$spec,
						$requestedPlotType,
						$requestedTransform,
						$requestedGroupOrder,
						$expectedGroupNames
					);
					if (empty($violations)) {
						$providerSucceeded = true;
						break;
					}
					Log::warning('LLM Highcharts response violated the requested plot contract.', ['provider' => $provider, 'violations' => $violations]);
					if ($attempt === 1) {
						$lastFailure = [
							'code' => 'contract_violation',
							'message' => "Provider '{$provider}' failed chart validation after one corrective retry.",
							'details' => ['provider' => $provider, 'violations' => $violations],
						];
						break; // move on to the next provider
					}
					$requestPrompt = $prompt . "\n\nYour previous response violated these requirements: " . implode('; ', $violations) .
						'. Previous series summary: ' . json_encode($this->summarizeExpressionSeries($options), JSON_UNESCAPED_SLASHES) .
						'. Regenerate the complete JSON and satisfy every requirement.';
				}

				if ($providerSucceeded) {
					$succeeded = true;
					$this->chatbotLlmTrace['provider'] = $provider;
					Log::info('Expression plot generated successfully.', ['provider' => $provider]);
					break;
				}

				Log::warning('Expression plot provider failed; falling back to next provider if available.', [
					'provider' => $provider,
					'failure_code' => $lastFailure['code'] ?? null,
				]);
			}

			if (!$succeeded) {
				$code = $lastFailure['code'] ?? 'generation_failed';
				$message = ($lastFailure['message'] ?? 'All configured LLM providers failed to generate a valid plot.')
					. ' Providers tried: ' . implode(', ', $triedProviders) . '.';
				$this->noteExpressionPlotError($code, $message, array_merge(
					['tried_providers' => $triedProviders],
					is_array($lastFailure['details'] ?? null) ? $lastFailure['details'] : []
				));
				return null;
			}

			$this->expressionPlotLastError = null;
			$options['credits'] = ['enabled' => false];
			
			// Ensure chart config exists
			if (!isset($options['chart'])) {
				$options['chart'] = [];
			}
			$options['chart']['spacingTop'] = 20;
			$options['chart']['spacingRight'] = 20;
			$options['chart']['spacingBottom'] = 20;
			$options['chart']['spacingLeft'] = 60;

			// Give each group a fixed width so violins stay readable; the container scrolls horizontally
			// when the total width exceeds the viewport (handled by CSS overflow-x on #expression_plot).
			$groupCount = count($expectedGroupNames);
			$perGroupWidth = 90; // px per group
			$chartMargins = 140; // left/right spacing + y-axis labels
			$computedWidth = ($groupCount * $perGroupWidth) + $chartMargins;
			if ($groupCount > 1 && $computedWidth > 900) {
				$options['chart']['width'] = min($computedWidth, 20000);
				// Remove any LLM-provided plotArea width; a fixed chart width drives group sizing instead.
				unset($options['plotArea']);
				unset($options['responsive']);
			} else {
				// Few groups: let the chart fill the container responsively.
				if (!isset($options['plotArea'])) {
					$options['plotArea'] = [];
				}
				$options['plotArea']['width'] = '100%';
			}
			
			// Ensure xAxis is properly configured for category grouping
			if (count($expectedGroupNames) > 1) {
				if (!isset($options['xAxis'])) {
					$options['xAxis'] = [];
				}
				$options['xAxis']['type'] = 'category';
				$options['xAxis']['categories'] = $expectedGroupNames;
			}

			// Disable the legend when there are many groups - x-axis categories already label each group.
			// A legend with 100+ series entries consumes most of the plot area.
			if (count($expectedGroupNames) > 8) {
				if (!isset($options['legend'])) {
					$options['legend'] = [];
				}
				$options['legend']['enabled'] = false;
			}

			return [
				'title' => (string)data_get($options, 'title.text', ($mcpResult['gene'] ?? '') . ' expression'),
				'summary' => (string)($spec['summary'] ?? ''),
				'data_transform' => (string)($spec['data_transform'] ?? 'none'),
				'group_order' => (string)($spec['group_order'] ?? 'none'),
				'highcharts_options' => $options,
				'_llm_prompt' => $prompt,
			];
		} catch (\Throwable $e) {
			Log::warning('LLM expression plot generation failed.', ['message' => $e->getMessage()]);
			$this->noteExpressionPlotError('exception', 'Plot generation raised an exception.', ['exception' => $e->getMessage()]);
			return null;
		}
	}

	private function expressionPlotSpecViolations($options, $spec, $requestedPlotType, $requestedTransform, $requestedGroupOrder, $expectedGroupNames) {
		$violations = [];
		$expectedGroupCount = count($expectedGroupNames);
		$unsupportedTypes = $this->unsupportedExpressionSeriesTypes($options);
		if (!empty($unsupportedTypes)) {
			$violations[] = 'unsupported series type(s): ' . implode(', ', $unsupportedTypes);
		}
		if ($requestedPlotType === 'violin') {
			$hasPolygon = false;
			$polygonCount = 0;
			$invalidPolygonNames = [];
			$zeroAreaPolygonNames = [];
			$polygonNames = [];
			$categories = data_get($options, 'xAxis.categories', []);
			$categories = is_array($categories) ? array_values(array_map('strval', $categories)) : [];
			$defaultType = strtolower((string)data_get($options, 'chart.type', 'line'));
			foreach ($options['series'] as $series) {
				$type = is_array($series) ? strtolower((string)($series['type'] ?? $defaultType)) : $defaultType;
				if ($type === 'polygon') {
					$hasPolygon = true;
					$polygonCount++;
					$seriesName = (string)($series['name'] ?? 'unnamed polygon');
					$polygonNames[] = $seriesName;
					$points = is_array($series['data'] ?? null) ? $series['data'] : [];
					$pointCount = count($points);
					// Allow 3+ points for rare diagnoses (violin density may not have 6+ with low sample count)
					if ($pointCount < 3 || $pointCount > 80) {
						$invalidPolygonNames[] = $seriesName;
					}
					$xValues = [];
					$yValues = [];
					foreach ($points as $point) {
						if (!is_array($point) || count($point) < 2 || !is_numeric($point[0]) || !is_numeric($point[1])) {
							continue;
						}
						$xValues[] = (float)$point[0];
						$yValues[] = (float)$point[1];
					}
					// For polygons with 3+ points, check they actually form a shape (not degenerate)
					$hasArea = count($xValues) >= 3
						&& count($xValues) === $pointCount
						&& $pointCount > 0
						&& (max($xValues) - min($xValues)) > 0.01
						&& (max($yValues) - min($yValues)) > 0.0001;
					if (!$hasArea && $pointCount >= 3) {
						$zeroAreaPolygonNames[] = $seriesName;
					}
				}
			}
			if (!$hasPolygon) {
				$violations[] = 'requested violin plot must include polygon series forming violin density shapes';
			}
			if ($polygonCount < ceil(0.70 * $expectedGroupCount)) {
				$violations[] = "violin plot must contain at least " . ceil(0.70 * $expectedGroupCount) . " polygon series; received {$polygonCount}";
			}
			if (!empty($invalidPolygonNames)) {
				$violations[] = 'every violin polygon must contain 3 to 80 density boundary points; invalid series: ' . implode(', ', array_slice($invalidPolygonNames, 0, 10));
			}
			if (!empty($zeroAreaPolygonNames)) {
				$violations[] = 'every violin polygon must contain finite [x,y] points with nonzero x width and y height; zero-area series: ' . implode(', ', array_slice($zeroAreaPolygonNames, 0, 10));
			}

		}
		if ($requestedTransform !== 'none' && strtolower(trim((string)($spec['data_transform'] ?? ''))) !== $requestedTransform) {
			$violations[] = 'data_transform must be exactly ' . $requestedTransform;
		}
		if ($requestedGroupOrder !== 'none' && strtolower(trim((string)($spec['group_order'] ?? ''))) !== $requestedGroupOrder) {
			$violations[] = 'group_order must be exactly ' . $requestedGroupOrder;
		}

		return $violations;
	}

	private function summarizeExpressionSeries($options) {
		$summary = [];
		foreach (array_slice($options['series'] ?? [], 0, 10) as $series) {
			$data = is_array($series['data'] ?? null) ? $series['data'] : [];
			$summary[] = [
				'name' => (string)($series['name'] ?? ''),
				'type' => (string)($series['type'] ?? data_get($options, 'chart.type', 'line')),
				'point_count' => count($data),
				'first_points' => array_slice($data, 0, 3),
			];
		}

		return $summary;
	}

	private function unsupportedExpressionSeriesTypes($options) {
		$supportedTypes = [
			'line', 'spline', 'area', 'areaspline', 'column', 'bar', 'pie', 'scatter',
			'bubble', 'packedbubble', 'polygon', 'boxplot', 'errorbar', 'waterfall', 'gauge',
			'arearange', 'areasplinerange', 'columnrange',
		];
		$defaultType = strtolower((string)data_get($options, 'chart.type', 'line'));
		$unsupportedTypes = [];
		foreach ($options['series'] as $series) {
			$type = is_array($series) ? strtolower((string)($series['type'] ?? $defaultType)) : $defaultType;
			if (!in_array($type, $supportedTypes, true)) {
				$unsupportedTypes[] = $type !== '' ? $type : '(empty)';
			}
		}
		return array_values(array_unique($unsupportedTypes));
	}

	private function sanitizeLlmHighchartsOptions($value, $key = '') {
		$blockedKeys = ['__proto__', 'prototype', 'constructor', 'events', 'formatter', 'pointformatter', 'labelformatter', 'usehtml'];
		if (in_array(strtolower((string)$key), $blockedKeys, true)) {
			return null;
		}
		if (is_array($value)) {
			$sanitized = [];
			foreach ($value as $childKey => $childValue) {
				$cleanValue = $this->sanitizeLlmHighchartsOptions($childValue, (string)$childKey);
				if ($cleanValue !== null) {
					$sanitized[$childKey] = $cleanValue;
				}
			}
			if ($key === 'chart') {
				foreach (['height', 'width'] as $dimension) {
					if (isset($sanitized[$dimension]) && is_numeric($sanitized[$dimension])) {
						$sanitized[$dimension] = max(200, min(2000, (int)$sanitized[$dimension]));
					}
				}
			}
			return $sanitized;
		}
		if (is_string($value)) {
			if (preg_match('/<|>|javascript:|data:|https?:|\/\//i', $value)) {
				return null;
			}
			return mb_substr($value, 0, 1000);
		}
		if (is_float($value) && !is_finite($value)) {
			return null;
		}
		return is_scalar($value) || $value === null ? $value : null;
	}


	private function mcpAuthorizedRequest($timeout) {
		$request = Http::timeout($timeout)->acceptJson()->asJson();
		$internalToken = (string) config('mcp_auth.internal_token', '');
		if ($internalToken !== '') {
			$request = $request->withToken($internalToken);
		}
		return $request;
	}

	private function mcpInitialize($mcpUrl) {
		$payload = [
			'jsonrpc' => '2.0',
			'id' => 'init_' . uniqid(),
			'method' => 'initialize',
			'params' => [
				'protocolVersion' => '2025-11-25',
				'capabilities' => (object)[],
				'clientInfo' => [
					'name' => 'clinomics-project-chatbot',
					'version' => '1.0.0',
				],
			],
		];
		$response = $this->mcpAuthorizedRequest(15)->post($mcpUrl, $payload);
		if (!$response->ok()) {
			Log::warning('MCP initialize request failed.', ['status' => $response->status()]);
			return null;
		}
		return $response->header('Mcp-Session-Id');
	}

	private function callMcpToolWithSession($mcpUrl, $sessionId, $toolName, $arguments) {
		$payload = [
			'jsonrpc' => '2.0',
			'id' => 'tool_' . uniqid(),
			'method' => 'tools/call',
			'params' => [
				'name' => $toolName,
				'arguments' => $arguments,
			],
		];
		$request = $this->mcpAuthorizedRequest(20);
		if ($sessionId != null && $sessionId != '') {
			$request = $request->withHeaders(['Mcp-Session-Id' => $sessionId]);
		}
		$response = $request->post($mcpUrl, $payload);
		if (!$response->ok()) {
			Log::warning('MCP tool call failed.', ['status' => $response->status(), 'tool' => $toolName]);
			return null;
		}
		$body = $response->json();
		if (!is_array($body)) return null;
		if (isset($body['result']['isError']) && $body['result']['isError']) {
			Log::warning('MCP tool returned an error.', ['tool' => $toolName, 'body' => $body]);
			return null;
		}
		$structured = data_get($body, 'result.structuredContent');
		if (is_array($structured)) return $structured;
		$text = data_get($body, 'result.content.0.text', '');
		if (is_string($text) && trim($text) != '') {
			$decoded = json_decode($text, true);
			if (is_array($decoded)) return $decoded;
		}
		return null;
	}

	private function callMcpToolsList($mcpUrl, $sessionId) {
		$payload = [
			'jsonrpc' => '2.0',
			'id' => 'list_' . uniqid(),
			'method' => 'tools/list',
			'params' => (object)[],
		];
		$request = $this->mcpAuthorizedRequest(15);
		if ($sessionId != null && $sessionId != '') {
			$request = $request->withHeaders(['Mcp-Session-Id' => $sessionId]);
		}
		$response = $request->post($mcpUrl, $payload);
		if (!$response->ok()) {
			Log::warning('MCP tools/list request failed.', ['status' => $response->status()]);
			return [];
		}
		return (array)data_get($response->json(), 'result.tools', []);
	}

	private function callOncoMcpTool($toolName, $arguments) {
		$mcpUrl = url('/mcp/onco');
		try {
			$sessionId = $this->mcpInitialize($mcpUrl);
			if ($sessionId === null) return null;
			return $this->callMcpToolWithSession($mcpUrl, $sessionId, $toolName, $arguments);
		} catch (\Exception $e) {
			Log::warning('MCP tool invocation exception.', ['tool' => $toolName, 'message' => $e->getMessage()]);
			return null;
		}
	}

	protected function runMcpWithLlmToolSelection($cohort_id, $query, $scope = 'project') {
		$scope = $this->normalizeChatbotScope($scope) ?? 'project';
		$mcpUrl = url('/mcp/onco');
		try {
			// Step 1: initialize MCP session
			$sessionId = $this->mcpInitialize($mcpUrl);
			if ($sessionId === null) {
				Log::warning('MCP initialize failed during LLM tool selection flow.');
				return null;
			}

			// Step 2: fetch live tool catalog from MCP server
			$tools = $this->callMcpToolsList($mcpUrl, $sessionId);
			$tools = $this->filterMcpToolsForChatbotScope($tools, $scope);
			if (empty($tools)) {
				Log::warning('No MCP tools are allowed for chatbot scope.', ['scope' => $scope]);
				return null;
			}

			// Step 3: LLM selects one or more tools and builds their arguments
			$selections = $this->selectToolsByLlm($query, $tools, $cohort_id, $scope);
			if (empty($selections)) {
				Log::warning('LLM did not select a valid tool.', ['query' => $query]);
				return null;
			}

			// Step 4: execute every selected tool on the same session
			$results = [];
			foreach ($selections as $selection) {
				$result = $this->executeLlmToolSelection($mcpUrl, $sessionId, $cohort_id, $query, $selection, $scope);
				if (is_array($result)) {
					$results[] = $result;
				}
			}

			if (empty($results)) {
				return null;
			}
			if (count($results) === 1) {
				return $results[0];
			}

			// Step 5: several tools ran, so merge their tables into one summary table
			if ($scope !== 'project') {
				return $results[0];
			}
			$merged = $this->mergeToolResultsIntoTable($cohort_id, $query, $results);
			return $merged !== null ? $merged : $results[0];
		} catch (\Exception $e) {
			Log::warning('runMcpWithLlmToolSelection exception.', ['message' => $e->getMessage()]);
			return null;
		}
	}

	private function filterMcpToolsForChatbotScope($tools, $scope) {
		$allowed = (array)Config::get('chatbot.scope_tools.'.$scope, []);
		$allowedLookup = array_fill_keys(array_map('strtolower', $allowed), true);

		return array_values(array_filter((array)$tools, static function ($tool) use ($allowedLookup) {
			$name = strtolower(trim((string)($tool['name'] ?? '')));

			return $name !== '' && isset($allowedLookup[$name]);
		}));
	}

	private function applyChatbotScopeArguments($toolName, $arguments, $scope, $cohortId) {
		$toolKey = strtolower(trim((string)$toolName));
		$cohortTools = [
			'getcohortsamples',
			'getcohortchipseq',
			'getcohortmutationgenes',
		];

		if ($scope === 'global') {
			unset($arguments['project_id'], $arguments['cohort_id'], $arguments['cohort_type']);

			return $arguments;
		}

		if (in_array($toolKey, $cohortTools, true)) {
			unset($arguments['project_id'], $arguments['cancer_type_id'], $arguments['cancer_type']);
			$arguments['cohort_type'] = $scope;
			$arguments['cohort_id'] = $scope === 'project' ? (int)$cohortId : (string)$cohortId;

			return $arguments;
		}

		if ($scope === 'project') {
			unset($arguments['cohort_id'], $arguments['cohort_type'], $arguments['cancer_type_id']);
			$arguments['project_id'] = (int)$cohortId;

			return $arguments;
		}

		unset($arguments['project_id'], $arguments['cohort_id'], $arguments['cohort_type']);
		if ($toolKey === 'getfusioncancertypedetail') {
			$arguments['cancer_type_id'] = (string)$cohortId;
		}

		return $arguments;
	}

	private function executeLlmToolSelection($mcpUrl, $sessionId, $cohort_id, $query, $selection, $scope = 'project') {
		try {
			$selectedToolName = trim((string)($selection['tool_name'] ?? ''));
			if ($selectedToolName === '') {
				return null;
			}
			$selectedToolKey = strtolower($selectedToolName);
			$allowedTools = array_map('strtolower', (array)Config::get('chatbot.scope_tools.'.$scope, []));
			if (!in_array($selectedToolKey, $allowedTools, true)) {
				Log::warning('Blocked MCP tool outside chatbot scope.', [
					'scope' => $scope,
					'tool' => $selectedToolName,
				]);
				return null;
			}
			$arguments = (array)($selection['arguments'] ?? []);
			$arguments = $this->normalizeLlmToolArguments($arguments);
			$arguments = $this->applyChatbotScopeArguments(
				$selectedToolName,
				$arguments,
				$scope,
				$cohort_id
			);
			if ($selectedToolKey === 'get_pathogeic_mutations') {
				if (!isset($arguments['diagnosis'])) {
					foreach (['cancer_type', 'cancerType', 'disease'] as $diagnosisAlias) {
						if (isset($arguments[$diagnosisAlias])) {
							$arguments['diagnosis'] = $arguments[$diagnosisAlias];
							unset($arguments[$diagnosisAlias]);
							break;
						}
					}
				}
				if (!isset($arguments['gene_id']) && isset($arguments['gene'])) {
					$arguments['gene_id'] = $arguments['gene'];
					unset($arguments['gene']);
				}
			}

			// Guardrail: for gene-based tools, prefer exact gene symbols present in the user query.
			$geneBasedTools = [
				'expression_by_gene',
				'mutation_by_gene',
				'fusion_by_gene',
				'cnv_by_gene',
				'correlation_by_gene',
				'survival_by_expression',
				'get_project_cnv',
				'get_fusion_genes',
			];
			if ($selectedToolKey === 'get_fusion_genes' && !isset($arguments['gene']) && isset($arguments['left_gene'])) {
				$arguments['gene'] = $arguments['left_gene'];
			}
			if (in_array($selectedToolKey, $geneBasedTools, true)) {
				$geneBeforeGuardrail = isset($arguments['gene']) ? (string)$arguments['gene'] : null;
				$queryGene = $this->extractExactGeneSymbolFromQuery($query);
				$rawQueryGene = $this->extractRawGeneTokenFromQuery($query);
				$guardrailSource = null;

				// Prefer user-query gene over any LLM-proposed gene to avoid hallucinated substitutions.
				if ($queryGene != null) {
					$arguments['gene'] = $queryGene;
					$guardrailSource = 'query_exact_symbol';
				} elseif ($rawQueryGene != null) {
					// Keep the query token as-is (uppercased) instead of fuzzy remapping to a different gene.
					$arguments['gene'] = strtoupper((string)$rawQueryGene);
					$guardrailSource = 'query_raw_token';
				} elseif (isset($arguments['gene_symbol']) && trim((string)$arguments['gene_symbol']) !== '') {
					$resolvedGeneSymbol = $this->resolveGeneSymbol((string)$arguments['gene_symbol']);
					$arguments['gene'] = $resolvedGeneSymbol != null ? $resolvedGeneSymbol : strtoupper((string)$arguments['gene_symbol']);
					$guardrailSource = 'llm_gene_symbol';
				} elseif (isset($arguments['gene'])) {
					$resolved = $this->resolveGeneSymbol((string)$arguments['gene']);
					if ($resolved != null) {
						$arguments['gene'] = $resolved;
						$guardrailSource = 'llm_gene_resolved';
					}
				}

				if (isset($arguments['gene'])) {
					unset($arguments['gene_symbol']);
					unset($arguments['geneSymbol']);
					unset($arguments['symbol']);
				}

				if ($geneBeforeGuardrail !== ($arguments['gene'] ?? null)) {
					Log::info('Gene guardrail adjusted gene argument.', [
						'cohort_id' => $cohort_id,
						'scope' => $scope,
						'query' => $query,
						'tool' => $selectedToolName,
						'source' => $guardrailSource,
						'gene_before' => $geneBeforeGuardrail,
						'gene_after' => $arguments['gene'] ?? null,
					]);
				}
			}
			if ($selectedToolKey === 'get_fusion_genes' && isset($arguments['gene'])) {
				$arguments['left_gene'] = $arguments['gene'];
				unset($arguments['gene']);
			}
			if ($selectedToolKey === 'expression_by_gene') {
				$arguments['dataset_scope'] = $this->extractExpressionDatasetScopeFromQuery($query);
				$arguments['transform'] = $this->extractExpressionTransformFromQuery($query);
				$valueType = $this->extractExpressionValueTypeFromQuery($query);
				if ($valueType !== null) {
					$arguments['value_type'] = $valueType;
				}
				$plotType = $this->extractExpressionPlotTypeFromQuery($query);
				if ($plotType !== null) {
					$arguments['plot_type'] = $plotType;
				}
				$groupBy = $this->extractExpressionGroupByFromQuery($query);
				if ($groupBy !== null) {
					$arguments['group_by'] = $groupBy;
				}
				$arguments['group_order'] = $this->extractExpressionGroupOrderFromQuery($query);
			}

			Log::info('LLM tool selection resolved arguments.', [
				'cohort_id' => $cohort_id,
				'scope' => $scope,
				'query' => $query,
				'tool' => $selectedToolName,
				'arguments' => $arguments,
			]);

			// Execute the selected tool using the existing session
			$result = $this->callMcpToolWithSession($mcpUrl, $sessionId, $selectedToolName, $arguments);
			if ($selectedToolKey === 'get_pathogeic_mutations') {
				$topGeneOnly = $this->pathogenicTopGeneRequested($query);
				// Rebuild directly when the MCP result is not a usable table, or when the query
				// asks for the single top gene (an aggregation the raw tool does not perform).
				if ($topGeneOnly || !$this->isGenericTableResult($result)) {
					try {
						$project = Project::getProject((int)$cohort_id);
						$table = $this->getPathogeicMutations(
							(int)$cohort_id,
							$arguments['diagnosis'] ?? 'null',
							$arguments['gene_id'] ?? 'null',
							$topGeneOnly
						);
						return [
							'status' => 'success',
							'action' => 'get_pathogeic_mutations',
							'project_id' => (int)$cohort_id,
							'project_name' => $project == null ? '' : $project->name,
							'diagnosis' => $arguments['diagnosis'] ?? null,
							'gene_id' => $arguments['gene_id'] ?? null,
							'data_type' => 'table',
							'display_type' => 'table',
							'table_json' => json_encode($table, JSON_UNESCAPED_SLASHES),
							'order' => $topGeneOnly ? [[2, 'desc']] : null,
							'title' => $topGeneOnly ? 'Top Gene by Pathogenic Mutations' : 'Pathogenic Mutations',
							'summary' => $topGeneOnly
								? 'Gene with the most pathogenic mutations for the requested diagnosis.'
								: 'Pathogenic mutations matching the requested diagnosis and gene ID.',
						];
					} catch (\Throwable $e) {
						return [
							'status' => 'error',
							'action' => 'get_pathogeic_mutations',
							'message' => $e->getMessage(),
						];
					}
				}
			}
			return $result;
		} catch (\Exception $e) {
			Log::warning('executeLlmToolSelection exception.', ['message' => $e->getMessage()]);
			return null;
		}
	}

	/**
	 * Combine the tables produced by several MCP tools into a single summary table.
	 * Genomic alterations (copy number, fusion, pathogenic mutation) are joined first on
	 * patient_id + case_id, then the RNA-seq expression of each altered sample is appended.
	 */
	private function mergeToolResultsIntoTable($project_id, $query, $results) {
		$tables = [];
		$expressionResults = [];
		foreach ($results as $result) {
			if (($result['status'] ?? 'success') !== 'success') {
				continue;
			}
			if (($result['display_type'] ?? null) === 'expression_data_json') {
				$expressionResults[] = $result;
				continue;
			}
			if (!$this->isGenericTableResult($result)) {
				continue;
			}
			$table = $this->decodeResultTable($result);
			if ($table === null || empty($table['data'])) {
				continue;
			}
			$table['label'] = (string)($result['title'] ?? ($result['action'] ?? 'Result'));
			$tables[] = $table;
		}
		if (count($tables) + count($expressionResults) < 2) {
			return null;
		}

		// The project sample metadata links an alteration's DNA sample to its RNA-seq sample,
		// which is how the expression values are attached to the alteration rows.
		$sampleLinks = $this->loadProjectSampleLinks($project_id);

		$mergedBy = 'alteration_join';
		$merged = $this->joinAlterationTables($tables);
		if ($merged !== null && !empty($expressionResults)) {
			$merged = $this->appendExpressionColumns($merged, $expressionResults, $sampleLinks);
			$mergedBy = 'alteration_join_with_expression';
		}
		if ($merged === null) {
			foreach ($expressionResults as $expressionResult) {
				$table = $this->expressionResultToTable($expressionResult);
				if ($table !== null && !empty($table['data'])) {
					$table['label'] = (string)($expressionResult['title'] ?? ($expressionResult['action'] ?? 'Expression'));
					$tables[] = $table;
				}
			}
			if (count($tables) < 2) {
				return null;
			}
			$mergedBy = 'llm';
			$merged = $this->mergeTablesByLlm($query, $tables, $sampleLinks);
		}
		if ($merged === null) {
			$mergedBy = 'stacked';
			$merged = $this->stackTables($tables);
		}
		if ($merged === null || empty($merged['data'])) {
			return null;
		}
		unset($merged['line_samples']);

		$labels = array_column($tables, 'label');
		if ($mergedBy === 'alteration_join_with_expression') {
			foreach ($expressionResults as $expressionResult) {
				$labels[] = (string)($expressionResult['title'] ?? ($expressionResult['action'] ?? 'Expression'));
			}
		}
		$project = Project::getProject($project_id);
		Log::info('Merged multiple MCP tool results.', [
			'project_id' => (int)$project_id,
			'query' => $query,
			'sources' => $labels,
			'merged_by' => $mergedBy,
		]);

		return [
			'status' => 'success',
			'action' => 'multi_tool_summary',
			'project_id' => (int)$project_id,
			'project_name' => $project === null ? '' : $project->name,
			'data_type' => 'table',
			'display_type' => 'table',
			'table_json' => json_encode($merged, JSON_UNESCAPED_SLASHES),
			'title' => 'Combined Results',
			'merged_by' => $mergedBy,
			'summary' => 'Combined summary of ' . implode(' and ', $labels) . '. ' . $this->describeMergeMethod($mergedBy),
		];
	}

	private function describeMergeMethod($mergedBy) {
		switch ($mergedBy) {
			case 'alteration_join':
				return 'Joined by the application on patient_id and case_id.';
			case 'alteration_join_with_expression':
				return 'Joined by the application on patient_id and case_id, with expression attached through the RNAseq sample metadata.';
			case 'llm':
				return 'Joined by the language model.';
			case 'stacked':
				return 'Not joined: the source tables are stacked because they share no key.';
		}

		return '';
	}

	private function decodeResultTable($result) {
		$table = $result['table_json'] ?? ($result['table'] ?? null);
		if (is_string($table)) {
			$table = json_decode($table, true);
		}
		if (!is_array($table) || !isset($table['cols']) || !isset($table['data'])) {
			return null;
		}
		$cols = [];
		foreach ((array)$table['cols'] as $col) {
			$cols[] = is_array($col) ? (string)($col['title'] ?? '') : (string)$col;
		}
		if (empty($cols)) {
			return null;
		}

		$rows = [];
		foreach ((array)$table['data'] as $row) {
			if (!is_array($row)) {
				continue;
			}
			$rows[] = array_map(function ($cell) {
				return is_scalar($cell) ? (string)$cell : '';
			}, array_values($row));
		}

		return ['cols' => $cols, 'data' => $rows];
	}

	/**
	 * Flatten an expression_by_gene result into a table so it can take part in a merge.
	 */
	private function expressionResultToTable($result) {
		if (($result['display_type'] ?? null) !== 'expression_data_json') {
			return null;
		}
		$plotRows = $result['plot_rows'] ?? [];
		if (!is_array($plotRows) || empty($plotRows)) {
			return null;
		}

		$data = [];
		foreach ($plotRows as $plotRow) {
			if (!is_array($plotRow)) {
				continue;
			}
			$data[] = [
				(string)($plotRow['patient_id'] ?? ''),
				(string)($plotRow['sample'] ?? ''),
				(string)($plotRow['dataset'] ?? ''),
				(string)($plotRow['metadata_value'] ?? ''),
				(string)($plotRow['raw_expression'] ?? ($plotRow['expression'] ?? '')),
			];
		}
		if (empty($data)) {
			return null;
		}

		return [
			'cols' => ['Patient ID', 'Sample', 'Dataset', 'Group', 'Expression'],
			'data' => $data,
		];
	}

	/**
	 * Join the genomic alteration tables (copy number, fusion, pathogenic mutation) on
	 * patient_id + case_id. Returns null when a table has no patient column; a single
	 * alteration table is returned as-is so expression can still be appended to it.
	 */
	private function joinAlterationTables($tables) {
		if (empty($tables)) {
			return null;
		}

		$indexes = [];
		foreach ($tables as $position => $table) {
			$patientIndex = $this->findPatientColumnIndex($table['cols']);
			if ($patientIndex === null) {
				return null;
			}
			$indexes[$position] = [
				'patient' => $patientIndex,
				'case' => $this->findColumnIndex($table['cols'], ['caseid', 'case']),
				'sample' => $this->findSampleColumnIndex($table['cols']),
			];
		}

		if (count($tables) === 1) {
			$cols = [];
			foreach ($tables[0]['cols'] as $title) {
				$cols[] = ['title' => $title];
			}

			return [
				'cols' => $cols,
				'data' => $tables[0]['data'],
				'line_samples' => $this->collectRowSamples($tables[0]['data'], $indexes[0]['sample']),
			];
		}

		$grouped = [];
		$byPatient = [];
		$keyOrder = [];
		$keyLabels = [];
		$casedPatients = [];
		foreach ($tables as $position => $table) {
			foreach ($table['data'] as $row) {
				$patientLabel = $this->plainCellValue($row[$indexes[$position]['patient']] ?? '');
				if ($patientLabel === '') {
					continue;
				}
				$caseLabel = $indexes[$position]['case'] === null
					? ''
					: $this->plainCellValue($row[$indexes[$position]['case']] ?? '');

				$patient = strtolower($patientLabel);
				$key = $patient . '||' . strtolower($caseLabel);
				if (!isset($keyLabels[$key])) {
					$keyLabels[$key] = ['patient' => $patientLabel, 'case' => $caseLabel];
					$keyOrder[] = $key;
				}
				if ($caseLabel !== '') {
					$casedPatients[$patient] = true;
				}
				$grouped[$key][$position][] = $row;
				$byPatient[$patient][$position][] = $row;
			}
		}
		if (empty($keyOrder)) {
			return null;
		}

		// Rows coming from a table without a case column land in the case-less bucket of their
		// patient; drop that bucket once the patient has real cases to attach to.
		$keyOrder = array_values(array_filter($keyOrder, function ($key) use ($keyLabels, $casedPatients) {
			if ($keyLabels[$key]['case'] !== '') {
				return true;
			}
			return !isset($casedPatients[strtolower($keyLabels[$key]['patient'])]);
		}));
		if (empty($keyOrder)) {
			return null;
		}

		$hasCaseColumn = false;
		foreach ($indexes as $index) {
			if ($index['case'] !== null) {
				$hasCaseColumn = true;
				break;
			}
		}

		$titles = $hasCaseColumn ? ['Patient ID', 'Case ID'] : ['Patient ID'];
		$columnMap = [];
		foreach ($tables as $position => $table) {
			foreach ($table['cols'] as $columnIndex => $title) {
				if ($columnIndex === $indexes[$position]['patient'] || $columnIndex === $indexes[$position]['case']) {
					continue;
				}
				$columnMap[] = ['table' => $position, 'column' => $columnIndex];
				$titles[] = $table['label'] . ': ' . $title;
			}
		}

		$data = [];
		$lineSamples = [];
		foreach ($keyOrder as $key) {
			$patient = strtolower($keyLabels[$key]['patient']);
			$perTable = [];
			foreach ($tables as $position => $table) {
				$perTable[$position] = $indexes[$position]['case'] === null
					? ($byPatient[$patient][$position] ?? [])
					: ($grouped[$key][$position] ?? []);
			}
			$height = 1;
			foreach ($perTable as $rows) {
				$height = max($height, count($rows));
			}
			for ($line = 0; $line < $height; $line++) {
				// Repeated on every line so DataTables can still sort and filter by patient/case.
				$newRow = [$keyLabels[$key]['patient']];
				if ($hasCaseColumn) {
					$newRow[] = $keyLabels[$key]['case'];
				}
				$samples = [];
				foreach ($columnMap as $column) {
					$row = $perTable[$column['table']][$line] ?? null;
					$newRow[] = ($row !== null && isset($row[$column['column']])) ? $row[$column['column']] : '';
				}
				foreach ($tables as $position => $table) {
					$row = $perTable[$position][$line] ?? null;
					if ($row === null || $indexes[$position]['sample'] === null) {
						continue;
					}
					$sample = $this->plainCellValue($row[$indexes[$position]['sample']] ?? '');
					if ($sample !== '') {
						$samples[] = $sample;
					}
				}
				$data[] = $newRow;
				$lineSamples[] = $samples;
			}
		}
		if (empty($data)) {
			return null;
		}

		$cols = [];
		foreach ($titles as $title) {
			$cols[] = ['title' => $title];
		}

		return ['cols' => $cols, 'data' => $data, 'line_samples' => $lineSamples];
	}

	/**
	 * Append the RNA-seq expression of each altered sample to the joined alteration table,
	 * so an expression value is only shown where an alteration exists.
	 */
	private function appendExpressionColumns($merged, $expressionResults, $links) {
		$expressionColumns = [];
		foreach ($expressionResults as $result) {
			$values = [];
			foreach ((array)($result['plot_rows'] ?? []) as $plotRow) {
				if (!is_array($plotRow) || ($plotRow['dataset'] ?? '') === 'normal') {
					continue;
				}
				$sampleName = strtolower(trim((string)($plotRow['sample'] ?? '')));
				if ($sampleName === '') {
					continue;
				}
				$values[$sampleName] = (string)($plotRow['raw_expression'] ?? ($plotRow['expression'] ?? ''));
			}
			if (empty($values)) {
				continue;
			}
			$gene = trim((string)($result['gene'] ?? ''));
			$expressionColumns[] = [
				'title' => ($gene === '' ? '' : $gene . ' ') . 'Expression (' . (string)($result['value_type'] ?? 'expression') . ')',
				'values' => $values,
			];
		}
		if (empty($expressionColumns)) {
			return $merged;
		}

		$lineSamples = $merged['line_samples'] ?? [];
		$cols = $merged['cols'];
		$cols[] = ['title' => 'RNAseq Sample'];
		foreach ($expressionColumns as $expressionColumn) {
			$cols[] = ['title' => $expressionColumn['title']];
		}

		$data = [];
		foreach ($merged['data'] as $line => $row) {
			$rnaSample = '';
			foreach ((array)($lineSamples[$line] ?? []) as $sample) {
				$resolved = $this->resolveExpressionSampleName($sample, $links);
				if ($resolved !== '') {
					$rnaSample = $resolved;
					break;
				}
			}
			$row[] = $rnaSample;
			foreach ($expressionColumns as $expressionColumn) {
				$row[] = $rnaSample === '' ? '' : (string)($expressionColumn['values'][strtolower($rnaSample)] ?? '');
			}
			$data[] = $row;
		}

		return ['cols' => $cols, 'data' => $data];
	}

	/**
	 * Translate an alteration sample id into the sample name that keys the expression data:
	 * sample_id -> its "RNAseq sample" attribute -> that RNA-seq sample's sample_name.
	 */
	private function resolveExpressionSampleName($sample, $links) {
		$sample = strtolower(trim((string)$sample));
		if ($sample === '') {
			return '';
		}
		$rna = (string)($links['rna'][$sample] ?? '');
		if ($rna === '') {
			return '';
		}

		return (string)($links['name'][$rna] ?? $rna);
	}

	private function collectRowSamples($rows, $sampleIndex) {
		$samples = [];
		foreach ($rows as $row) {
			if ($sampleIndex === null) {
				$samples[] = [];
				continue;
			}
			$sample = $this->plainCellValue($row[$sampleIndex] ?? '');
			$samples[] = $sample === '' ? [] : [$sample];
		}

		return $samples;
	}

	private function plainCellValue($cell) {
		return trim(html_entity_decode(strip_tags((string)$cell), ENT_QUOTES | ENT_HTML5));
	}

	/**
	 * Map every project sample to its "RNAseq sample" attribute, and every sample id to its
	 * sample name. RNA-seq samples map to themselves.
	 */
	private function loadProjectSampleLinks($project_id) {
		$links = ['rna' => [], 'name' => [], 'patient' => []];
		try {
			$project = Project::getProject($project_id);
			if ($project === null) {
				return $links;
			}
			$samples = $project->getProjectSamples(true, 'all');
		} catch (\Throwable $e) {
			Log::warning('Could not load project sample links.', ['project_id' => (int)$project_id, 'message' => $e->getMessage()]);
			return $links;
		}

		foreach ((array)$samples as $sample) {
			$attributes = (array)$sample;
			$rawSampleName = trim((string)($attributes['sample_name'] ?? ''));
			$sampleId = strtolower(trim((string)($attributes['sample_id'] ?? '')));
			$sampleName = strtolower($rawSampleName);
			$patient = strtolower(trim((string)($attributes['patient_id'] ?? '')));
			$expType = strtolower(trim((string)($attributes['exp_type'] ?? '')));

			$rna = '';
			foreach ($attributes as $attributeName => $attributeValue) {
				$normalized = strtolower((string)preg_replace('/[^A-Za-z0-9]+/', '', (string)$attributeName));
				if ($normalized === 'rnaseqsample' || $normalized === 'rnaseqsampleid' || $normalized === 'rnasample') {
					$rna = strtolower(trim((string)$attributeValue));
					break;
				}
			}
			if ($rna === '' && strpos($expType, 'rna') !== false) {
				$rna = $sampleId !== '' ? $sampleId : $sampleName;
			}

			foreach ([$sampleId, $sampleName] as $alias) {
				if ($alias === '') {
					continue;
				}
				if ($rna !== '') {
					$links['rna'][$alias] = $rna;
				}
				if ($rawSampleName !== '') {
					$links['name'][$alias] = $rawSampleName;
				}
				if ($patient !== '') {
					$links['patient'][$alias] = $patient;
				}
			}
		}

		return $links;
	}

	private function findPatientColumnIndex($cols) {
		foreach ($cols as $index => $title) {
			$normalized = strtolower((string)preg_replace('/[^A-Za-z0-9]+/', '', (string)$title));
			if ($normalized === 'patientid' || $normalized === 'patient') {
				return $index;
			}
		}

		return null;
	}

	private function findSampleColumnIndex($cols) {
		return $this->findColumnIndex($cols, ['sampleid', 'sample', 'samplename', 'rnaseqsample']);
	}

	private function findColumnIndex($cols, $accepted) {
		foreach ($cols as $index => $title) {
			$normalized = strtolower((string)preg_replace('/[^A-Za-z0-9]+/', '', (string)$title));
			if (in_array($normalized, $accepted, true)) {
				return $index;
			}
		}

		return null;
	}

	private function mergeTablesByLlm($query, $tables, $links = null) {
		$llmConfig = Config::get('services.llm', []);
		$sampleMap = [];
		$payload = [];
		foreach ($tables as $table) {
			$sampleIndex = $this->findSampleColumnIndex($table['cols']);
			if ($sampleIndex !== null && is_array($links)) {
				foreach ($table['data'] as $row) {
					$sample = $this->plainCellValue($row[$sampleIndex] ?? '');
					$rna = $this->resolveExpressionSampleName($sample, $links);
					if ($sample !== '' && $rna !== '') {
						$sampleMap[$sample] = $rna;
					}
				}
			}
			$rows = [];
			// Cap rows and cell length so the merge prompt stays inside the model context window.
			foreach (array_slice($table['data'], 0, 50) as $row) {
				$rows[] = array_map(function ($cell) {
					$text = trim(html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5));
					return mb_substr($text, 0, 120);
				}, $row);
			}
			$payload[] = [
				'source' => $table['label'],
				'cols' => $table['cols'],
				'rows' => $rows,
			];
		}

		$mappingSection = '';
		if (!empty($sampleMap)) {
			$mappingSection = "Sample metadata mapping (alteration sample id => the sample name of its RNA-seq sample). " .
				"Copy number and mutation rows come from DNA samples; expression values are keyed by the RNA-seq sample name. " .
				"Translate every alteration sample through this map before matching it with an expression row, and only report an expression value on a row that has an alteration:\n" .
				json_encode(array_slice($sampleMap, 0, 200), JSON_PRETTY_PRINT) . "\n\n";
		}

		$prompt = "You merge genomics result tables into one summary table.\n\n" .
			"User query: $query\n\n" .
			"Source tables (every row array follows the cols array of its own table):\n" .
			json_encode($payload, JSON_PRETTY_PRINT) . "\n\n" .
			$mappingSection .
			"Build one summary table that answers the query. Keep a column that identifies which source each row came from. " .
			"Join the alteration tables on their patient identifier and case identifier columns so each patient/case appears once, and leave a cell empty when a source has nothing for it. " .
			"When sample columns are present, use the sample metadata mapping above to attach the expression value of the matching RNA-seq sample. " .
			"Use only values that appear in the source tables and never invent data. Every row must contain exactly as many cells as there are columns.\n" .
			"Return strict JSON only: {\"cols\": [\"<title>\"], \"data\": [[\"<cell>\"]]}";

		try {
			$text = $this->dispatchLlmTextRequest($prompt, $llmConfig);
			if (!is_string($text) || trim($text) == '') return null;

			$parsed = $this->parseIntentJsonFromText($text);
			if (!is_array($parsed) || !isset($parsed['cols']) || !isset($parsed['data'])) return null;

			$cols = [];
			foreach ((array)$parsed['cols'] as $col) {
				$title = is_array($col) ? (string)($col['title'] ?? '') : (string)$col;
				if (trim($title) !== '') {
					$cols[] = ['title' => htmlspecialchars($title, ENT_QUOTES)];
				}
			}
			if (empty($cols)) return null;

			$data = [];
			foreach ((array)$parsed['data'] as $row) {
				if (!is_array($row)) continue;
				// Model output is rendered by DataTables as HTML, so escape it.
				$cells = array_map(function ($cell) {
					return is_scalar($cell) ? htmlspecialchars((string)$cell, ENT_QUOTES) : '';
				}, array_values($row));
				$data[] = array_slice(array_pad($cells, count($cols), ''), 0, count($cols));
			}
			if (empty($data)) return null;

			return ['cols' => $cols, 'data' => $data];
		} catch (\Exception $e) {
			Log::warning('LLM table merge failed.', ['message' => $e->getMessage()]);
			return null;
		}
	}

	private function stackTables($tables) {
		$titles = ['Source'];
		foreach ($tables as $table) {
			foreach ($table['cols'] as $col) {
				if (!in_array($col, $titles, true)) {
					$titles[] = $col;
				}
			}
		}

		$data = [];
		foreach ($tables as $table) {
			$position = array_flip($table['cols']);
			foreach ($table['data'] as $row) {
				$newRow = [];
				foreach ($titles as $title) {
					if ($title === 'Source') {
						$newRow[] = $table['label'];
						continue;
					}
					$index = $position[$title] ?? null;
					$newRow[] = ($index !== null && isset($row[$index])) ? $row[$index] : '';
				}
				$data[] = $newRow;
			}
		}
		if (empty($data)) {
			return null;
		}

		$cols = [];
		foreach ($titles as $title) {
			$cols[] = ['title' => $title];
		}

		return ['cols' => $cols, 'data' => $data];
	}

	private function extractIntentByRules($query) {
		$survivalIntent = $this->extractSurvivalByExpressionIntentFromQuery($query);
		if ($survivalIntent != null) {
			return $survivalIntent;
		}

		$expressionGene = $this->extractExpressionGeneFromQuery($query);
		if ($expressionGene != null) {
			$intent = ['action' => 'expression_by_gene', 'gene' => $expressionGene];
			$plotType = $this->extractExpressionPlotTypeFromQuery($query);
			$groupBy = $this->extractExpressionGroupByFromQuery($query);
			if ($plotType != null) {
				$intent['plot_type'] = $plotType;
			}
			if ($groupBy != null) {
				$intent['group_by'] = $groupBy;
			}
			$intent['dataset_scope'] = $this->extractExpressionDatasetScopeFromQuery($query);
			$intent['transform'] = $this->extractExpressionTransformFromQuery($query);
			$intent['group_order'] = $this->extractExpressionGroupOrderFromQuery($query);
			$valueType = $this->extractExpressionValueTypeFromQuery($query);
			if ($valueType !== null) {
				$intent['value_type'] = $valueType;
			}
			return $intent;
		}

		$mutationIntent = $this->extractMutationIntentFromQuery($query);
		if ($mutationIntent != null) {
			return $mutationIntent;
		}

		$fusionIntent = $this->extractFusionIntentFromQuery($query);
		if ($fusionIntent != null) {
			return $fusionIntent;
		}

		$cnvIntent = $this->extractCnvIntentFromQuery($query);
		if ($cnvIntent != null) {
			return $cnvIntent;
		}

		$correlationIntent = $this->extractCorrelationIntentFromQuery($query);
		if ($correlationIntent != null) {
			return $correlationIntent;
		}

		return null;
	}

	/**
	 * Deterministic parser for pathogenic-mutation requests. Used only as a last-resort
	 * safety net after the LLM+MCP tool-selection path, so the generic table still renders
	 * for queries like "show me the pathogenic mutations in NSCLC". Returns null when the
	 * query is not asking for pathogenic mutations.
	 */
	private function extractPathogenicMutationIntentFromQuery($query) {
		$lower = strtolower((string)$query);
		if (strpos($lower, 'pathogenic') === false && strpos($lower, 'pathogeic') === false) {
			return null;
		}

		$diagnosis = null;
		// Capture the phrase after in/for/of/within/with as the diagnosis / cancer type.
		if (preg_match('/\b(?:in|for|of|within|with)\s+([A-Za-z0-9][A-Za-z0-9 \-\/\+]{0,60})/i', (string)$query, $m)) {
			$candidate = trim($m[1]);
			// Drop trailing filler words that are not part of the diagnosis.
			$candidate = preg_replace('/\b(patients?|samples?|cases?|cohort|tumou?rs?|mutations?|variants?)\b.*$/i', '', $candidate);
			$candidate = trim($candidate);
			if ($candidate !== '') {
				$diagnosis = $candidate;
			}
		}

		$geneId = null;
		if (preg_match('/\bgene(?:\s+id)?\s+([A-Za-z0-9\-\.]+)/i', (string)$query, $gm)) {
			$geneId = trim($gm[1]);
		}

		return [
			'action' => 'get_pathogeic_mutations',
			'diagnosis' => $diagnosis ?? 'null',
			'gene_id' => $geneId ?? 'null',
			'top_gene' => $this->pathogenicTopGeneRequested($query),
		];
	}

	/**
	 * True when a pathogenic-mutation query asks for the single gene with the most
	 * (highest / top / greatest) pathogenic mutations, e.g. "which genes have the most
	 * pathogenic mutations in pancreatic acinar cell carcinoma".
	 */
	private function pathogenicTopGeneRequested($query) {
		$lower = strtolower((string)$query);
		if (strpos($lower, 'pathogenic') === false && strpos($lower, 'pathogeic') === false) {
			return false;
		}
		if (preg_match('/\b(most|highest|greatest|top|maximum|max|largest)\b/i', $lower) !== 1) {
			return false;
		}
		return strpos($lower, 'gene') !== false;
	}

	private function normalizeLlmToolArguments($arguments) {
		if (!is_array($arguments)) {
			return [];
		}

		if (!isset($arguments['gene']) || trim((string)$arguments['gene']) === '') {
			if (isset($arguments['gene_symbol']) && trim((string)$arguments['gene_symbol']) !== '') {
				$arguments['gene'] = $arguments['gene_symbol'];
			} elseif (isset($arguments['geneSymbol']) && trim((string)$arguments['geneSymbol']) !== '') {
				$arguments['gene'] = $arguments['geneSymbol'];
			} elseif (isset($arguments['symbol']) && trim((string)$arguments['symbol']) !== '') {
				$arguments['gene'] = $arguments['symbol'];
			}
		}

		return $arguments;
	}

	private function extractSurvivalByExpressionIntentFromQuery($query) {
		$lowerQuery = strtolower($query);
		if (!$this->isSurvivalLikeQuery($query)) {
			return null;
		}

		// Keep this intent specific to gene-based survival requests.
		if (strpos($lowerQuery, 'expression') === false && strpos($lowerQuery, 'gene') === false && strpos($lowerQuery, ' of ') === false && strpos($lowerQuery, ' for ') === false) {
			return null;
		}

		$gene = $this->extractGeneFromSurvivalQuery($query);
		if ($gene == null) {
			return null;
		}

		return ['action' => 'survival_by_expression', 'gene' => $gene];
	}

	private function extractGeneFromSurvivalQuery($query) {
		$patterns = [
			// survival ... of/for/based on/by ... <gene>
			'/survival(?:\s+analysis)?(?:\s+based\s+on|\s+by)?\s+(?:expression\s+of\s+|gene\s+)?([A-Za-z0-9\-\.]+)/i',
			'/survival(?:\s+analysis)?\s+(?:of|for|by|based\s+on)\s+(?:expression\s+of\s+|gene\s+)?([A-Za-z0-9\-\.]+)/i',
			// kaplan-meier / kaplen-meier / km phrasing
			'/\bkapl(?:an|en)\s*[- ]\s*meier(?:\s+analysis)?\s+(?:of|for|by|based\s+on)\s+(?:expression\s+of\s+|gene\s+)?([A-Za-z0-9\-\.]+)/i',
			'/\bkm(?:\s+analysis)?\s+(?:of|for|by|based\s+on)\s+(?:expression\s+of\s+|gene\s+)?([A-Za-z0-9\-\.]+)/i',
			'/\b(?:survival|kapl(?:an|en)\s*[- ]\s*meier|km)(?:\s+analysis)?\s+based\s+on\s+([A-Za-z0-9\-\.]+)\s+expression\b/i',
			// ... <gene> expression ... survival ...
			'/([A-Za-z0-9\-\.]+)\s+expression.*survival/i',
			'/survival.*([A-Za-z0-9\-\.]+)\s+expression/i',
			'/expression\s+of\s+([A-Za-z0-9\-\.]+).*survival/i',
		];

		foreach ($patterns as $pattern) {
			if (!preg_match($pattern, $query, $matches)) {
				continue;
			}
			$gene = $this->resolveGeneSymbol((string)$matches[1]);
			if ($gene != null) {
				return $gene;
			}
		}

		// Last-resort fallback: pick the first exact gene token near survival/expression wording.
		$tokens = preg_split('/[^A-Za-z0-9\-\.]+/', $query, -1, PREG_SPLIT_NO_EMPTY);
		$stopWords = [
			'survival', 'analysis', 'based', 'on', 'by', 'of', 'for', 'show', 'me', 'the', 'expression', 'gene', 'and', 'or', 'please', 'kaplan', 'kaplen', 'meier', 'km'
		];

		foreach ($tokens as $token) {
			$tokenLower = strtolower($token);
			if (in_array($tokenLower, $stopWords, true)) {
				continue;
			}
			$gene = $this->resolveGeneSymbol($token);
			if ($gene != null && strtoupper($token) === $gene) {
				return $gene;
			}
		}

		return null;
	}

	private function extractExpressionGeneFromQuery($query) {
		$patterns = [
			'/\b(?:make|create|generate|show|draw|plot)\s+(?:an?\s+)?(?:heat\s*map|heatmap|box\s*plot|boxplot|violin(?:e)?\s*plot|bar\s*plot|barplot|column(?:\s*plot)?)\s+(?:for|of)\s+([A-Za-z0-9\-\.]+)\s+expression\b/i',
			'/\b(?:make|create|generate|show|draw|plot)(?:\s+me)?\s+(?:an?\s+|the\s+)?(?:log\s*2\s+)?(?:heat\s*map|heatmap|box\s*plot|boxplot|violin(?:e)?(?:\s*plot)?|bar\s*plot|barplot|column(?:\s*plot)?)\s+(?:for|of)\s+([A-Za-z0-9\-\.]+)\b/i',
			'/\b([A-Za-z0-9\-\.]+)\s+expression\b.*\b(?:heat\s*map|heatmap|box\s*plot|boxplot|violin(?:e)?\s*plot|bar\s*plot|barplot|column(?:\s*plot)?)\b/i',
			'/\bexpression\s+of\s+([A-Za-z0-9\-\.]+)\b/i',
			'/show\s+me\s+(?:the\s+)?(?:rnaseq|rna\s*seq)\s+of\s+(.+?)\s+expression\s*$/i',
			'/(?:rnaseq|rna\s*seq)\s+of\s+(.+?)\s+expression\s*$/i',
			'/(?:rnaseq|rna\s*seq)\s+expression\s+of\s+(.+)$/i',
			'/expression\s+of\s+(.+)$/i',
			'/show\s+me\s+the\s+expression\s+of\s+(.+)$/i',
			'/show\s+expression\s+of\s+(.+)$/i'
		];
		
		$geneString = null;
		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $query, $matches)) {
				$geneString = trim($matches[1]);
				break;
			}
		}
		
		if ($geneString == null) {
			return null;
		}
		
		// Split by "and", comma, or multiple spaces
		$geneTokens = preg_split('/\s+and\s+|,\s*|\s{2,}/', $geneString, -1, PREG_SPLIT_NO_EMPTY);
		$resolvedGenes = [];
		
		foreach ($geneTokens as $geneToken) {
			$geneToken = trim($geneToken);
			if (empty($geneToken)) continue;
			
			// Resolve each gene
			$resolved = $this->resolveGeneSymbol($geneToken);
			if ($resolved != null) {
				$resolvedGenes[] = $resolved;
			}
		}
		
		// Return space-separated gene list if we found any genes
		if (!empty($resolvedGenes)) {
			return implode(' ', $resolvedGenes);
		}
		
		return null;
	}

	private function extractExpressionPlotTypeFromQuery($query) {
		if (preg_match('/\bviolin(?:e)?(?:\s*plot)?\b/i', $query)) {
			return 'violin';
		}
		if (preg_match('/\bbox\s*plot\b/i', $query)) {
			return 'boxplot';
		}
		if (preg_match('/\bheat\s*map\b/i', $query)) {
			return 'heatmap';
		}
		if (preg_match('/\bbar(?:\s*(?:plot|chart))\b/i', $query)) {
			return 'barplot';
		}
		if (preg_match('/\bcolumn(?:\s*(?:plot|chart))?\b/i', $query)) {
			return 'column';
		}

		return null;
	}

	private function extractExpressionGroupByFromQuery($query) {
		$patterns = [
			'/\bgroup(?:ed)?\s+by\s+([A-Za-z][A-Za-z0-9_\-]*(?:\s+[A-Za-z][A-Za-z0-9_\-]*)*?)(?=\s+order(?:ed)?\s+by\s+|\s+(?:for|using|from|with|in)\s+|\s+(?:tumou?r|normal|healthy|control)\b|[,.?]|$)/i',
			'/\b(?:heat\s*map|heatmap|box\s*plot|boxplot|violin(?:e)?(?:\s*plot)?|bar(?:\s*(?:plot|chart))|column(?:\s*(?:plot|chart))?)\s+by\s+([A-Za-z][A-Za-z0-9_\-]*(?:\s+[A-Za-z][A-Za-z0-9_\-]*)*?)(?=\s+order(?:ed)?\s+by\s+|\s+(?:for|using|from|with|in)\s+|\s+(?:tumou?r|normal|healthy|control)\b|[,.?]|$)/i',
		];
		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $query, $matches)) {
				return strtolower(trim((string)$matches[1]));
			}
		}

		return null;
	}

	private function extractExpressionDatasetScopeFromQuery($query) {
		$mentionsTumor = preg_match('/\b(?:tumou?r|cancer(?:ous)?)\b/i', $query) === 1;
		$mentionsNormal = preg_match('/\b(?:normal|healthy|control)\b/i', $query) === 1;

		if ($mentionsTumor && !$mentionsNormal) {
			return 'tumor';
		}
		if ($mentionsNormal && !$mentionsTumor) {
			return 'normal';
		}

		return 'all';
	}

	private function extractExpressionTransformFromQuery($query) {
		$compactQuery = strtolower((string)preg_replace('/[^A-Za-z0-9]+/', '', (string)$query));
		if (strpos($compactQuery, 'zscore') !== false || strpos($compactQuery, 'standardscore') !== false || strpos($compactQuery, 'standarddeviation') !== false) {
			return 'zscore';
		}
		return preg_match('/\blog\s*2\b/i', $query) === 1 ? 'log2p1' : 'none';
	}

	private function extractExpressionValueTypeFromQuery($query) {
		if (preg_match('/\bTPM\b/i', $query)) {
			return 'tpm';
		}
		if (preg_match('/\bTMM[\s_-]*RPKM\b/i', $query)) {
			return 'tmm-rpkm';
		}

		return null;
	}

	private function extractExpressionGroupOrderFromQuery($query) {
		if (preg_match('/\border(?:ed)?\s+by\s+(?:the\s+)?median(?:\s+value)?(?:\s+(descending|desc|highest|ascending|asc|lowest))?/i', $query, $matches)) {
			$direction = strtolower((string)($matches[1] ?? ''));
			return in_array($direction, ['descending', 'desc', 'highest'], true) ? 'median_desc' : 'median_asc';
		}

		return 'none';
	}

	private function extractMutationIntentFromQuery($query) {
		$lowerQuery = strtolower($query);
		if (strpos($lowerQuery, 'mutation') === false && strpos($lowerQuery, 'mutations') === false) {
			return null;
		}

		$type = $this->normalizeMutationTypeFromQuery($query);
		if ($type == null) {
			$type = 'somatic';
		}

		$geneCandidate = null;
		$patterns = [
			'/mutation(?:s)?\s+of\s+([A-Za-z0-9\-\.]+)/i',
			'/show\s+me\s+the\s+mutation(?:s)?\s+of\s+([A-Za-z0-9\-\.]+)/i',
			'/show\s+the\s+mutation(?:s)?\s+of\s+([A-Za-z0-9\-\.]+)/i',
			'/([A-Za-z0-9\-\.]+)\s+(?:somatic|somtaic|germline|rnaseq|variant|variants)\s+mutation(?:s)?/i'
		];

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $query, $matches)) {
				$geneCandidate = strtoupper(trim($matches[1]));
				break;
			}
		}

		if ($geneCandidate == null) {
			return null;
		}

		$gene = $this->resolveGeneSymbol($geneCandidate);
		if ($gene == null) {
			return null;
		}

		return ['action' => 'mutation_by_gene', 'gene' => $gene, 'type' => $type];
	}

	private function normalizeMutationTypeFromQuery($query) {
		$tokens = preg_split('/[^A-Za-z]+/', strtolower($query));
		$typeMap = [
			'somatic' => 'somatic',
			'germline' => 'germline',
			'rnaseq' => 'rnaseq',
			'variant' => 'variants',
			'variants' => 'variants'
		];

		foreach ($tokens as $token) {
			if ($token == '') {
				continue;
			}
			foreach ($typeMap as $expected => $normalized) {
				if ($token === $expected || levenshtein($token, $expected) <= 2) {
					return $normalized;
				}
			}
		}

		return null;
	}

	private function resolveGeneSymbol($geneCandidate) {
		$geneCandidate = strtoupper(trim($geneCandidate));
		if ($geneCandidate == '') {
			return null;
		}
		$gene = Gene::getGene($geneCandidate);
		if ($gene != null) {
			return strtoupper($gene->getSymbol());
		}

		// Avoid aggressive fuzzy matching for short tokens (e.g. "of", "in").
		if (strlen($geneCandidate) <= 3) {
			return null;
		}

		$genes = Gene::getAllSymbols();
		$bestSymbol = null;
		$bestDistance = 99;
		foreach ($genes as $item) {
			$symbol = strtoupper($item->symbol);
			$distance = levenshtein($geneCandidate, $symbol);
			if ($distance < $bestDistance) {
				$bestDistance = $distance;
				$bestSymbol = $symbol;
			}
			if ($bestDistance == 0) {
				break;
			}
		}

		if ($bestSymbol != null && $bestDistance <= 2) {
			return $bestSymbol;
		}

		return null;
	}

	private function extractExactGeneSymbolFromQuery($query) {
		$tokens = preg_split('/[^A-Za-z0-9\-\.]+/', (string)$query, -1, PREG_SPLIT_NO_EMPTY);
		foreach ($tokens as $token) {
			$candidate = strtoupper(trim($token));
			if ($candidate == '') {
				continue;
			}
			$gene = Gene::getGene($candidate);
			// Strict match only: ignore fuzzy/alias remaps where returned symbol differs.
			if ($gene != null && strtoupper((string)$gene->getSymbol()) === $candidate) {
				return strtoupper($gene->getSymbol());
			}
		}
		return null;
	}

	private function extractRawGeneTokenFromQuery($query) {
		$patterns = [
			'/\b(?:gene\s+)?of\s+([A-Za-z0-9\-\.]{2,20})\b/i',
			'/\bfor\s+(?:gene\s+)?([A-Za-z0-9\-\.]{2,20})\b/i',
			'/\bkapl(?:an|en)\s*[- ]\s*meier(?:\s+analysis)?\s+(?:of|for|by|based\s+on)\s+(?:gene\s+)?([A-Za-z0-9\-\.]{2,20})\b/i',
			'/\bkm(?:\s+analysis)?\s+(?:of|for)\s+(?:gene\s+)?([A-Za-z0-9\-\.]{2,20})\b/i',
			'/\b(?:survival|kapl(?:an|en)\s*[- ]\s*meier|km)(?:\s+analysis)?\s+based\s+on\s+([A-Za-z0-9\-\.]{2,20})\s+expression\b/i',
			'/\bbased\s+on\s+([A-Za-z0-9\-\.]{2,20})\s+expression\b/i',
			'/\b([A-Za-z0-9\-\.]{2,20})\s+expression\b/i',
			'/\bexpression\s+of\s+([A-Za-z0-9\-\.]{2,20})\b/i',
			'/\bgene\s+([A-Za-z0-9\-\.]{2,20})\b/i',
		];

		$stopWords = [
			'survival', 'analysis', 'show', 'me', 'the', 'by', 'based', 'on', 'for', 'of', 'expression', 'gene', 'and', 'or', 'please', 'kaplan', 'kaplen', 'meier', 'km'
		];

		foreach ($patterns as $pattern) {
			if (!preg_match($pattern, (string)$query, $matches)) {
				continue;
			}
			$candidate = strtoupper(trim((string)$matches[1]));
			if ($candidate == '') {
				continue;
			}
			if (in_array(strtolower($candidate), $stopWords, true)) {
				continue;
			}
			return $candidate;
		}

		return null;
	}

	private function isSurvivalLikeQuery($query) {
		$lower = strtolower((string)$query);
		if (strpos($lower, 'survival') !== false) {
			return true;
		}
		if (preg_match('/\bkapl(?:an|en)\s*[- ]\s*meier\b/i', (string)$query)) {
			return true;
		}
		if (preg_match('/\bkm\b/i', (string)$query)) {
			return true;
		}
		return false;
	}

	private function extractFusionIntentFromQuery($query) {
		$lowerQuery = strtolower($query);
		if (strpos($lowerQuery, 'fusion') === false && strpos($lowerQuery, 'fusions') === false) {
			return null;
		}

		$geneCandidate = null;
		$patterns = [
			'/fusion(?:s)?\s+of\s+([A-Za-z0-9\-\.]+)/i',
			'/show\s+me\s+the\s+fusion(?:s)?\s+of\s+([A-Za-z0-9\-\.]+)/i',
			'/show\s+fusion(?:s)?\s+of\s+([A-Za-z0-9\-\.]+)/i',
			'/([A-Za-z0-9\-\.]+)\s+fusion(?:s)?/i'
		];

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $query, $matches)) {
				$geneCandidate = $matches[1];
				break;
			}
		}

		$gene = $this->resolveGeneSymbol((string)$geneCandidate);
		if ($gene == null) {
			return null;
		}

		return ['action' => 'fusion_by_gene', 'gene' => $gene];
	}

	private function extractCnvIntentFromQuery($query) {
		$lowerQuery = strtolower($query);
		if (strpos($lowerQuery, 'cnv') === false && strpos($lowerQuery, 'copy number') === false) {
			return null;
		}

		$geneCandidate = null;
		$patterns = [
			'/cnv\s+of\s+([A-Za-z0-9\-\.]+)/i',
			'/copy\s+number\s+of\s+([A-Za-z0-9\-\.]+)/i',
			'/show\s+me\s+the\s+cnv\s+of\s+([A-Za-z0-9\-\.]+)/i',
			'/show\s+cnv\s+of\s+([A-Za-z0-9\-\.]+)/i',
			'/([A-Za-z0-9\-\.]+)\s+cnv/i'
		];

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $query, $matches)) {
				$geneCandidate = $matches[1];
				break;
			}
		}

		$gene = $this->resolveGeneSymbol((string)$geneCandidate);
		if ($gene == null) {
			return null;
		}

		return ['action' => 'cnv_by_gene', 'gene' => $gene];
	}

	private function extractCorrelationIntentFromQuery($query) {
		$lowerQuery = strtolower($query);
		if (strpos($lowerQuery, 'correlation') === false && 
			strpos($lowerQuery, 'correlate') === false &&
			strpos($lowerQuery, 'correlated') === false &&
			!$this->hasApproxKeywordInQuery($query, ['correlation', 'correlate', 'correlated'], 2)) {
			return null;
		}

		$geneCandidate = null;
		$patterns = [
			'/correlation(?:s)?\s+of\s+([A-Za-z0-9\-\.]+)/i',
			'/correlate(?:d)?\s+(?:with|to)\s+([A-Za-z0-9\-\.]+)/i',
			'/show\s+me\s+the\s+correlation(?:s)?\s+of\s+([A-Za-z0-9\-\.]+)/i',
			'/genes\s+correlate(?:d)?\s+(?:to|with)\s+([A-Za-z0-9\-\.]+)/i',
			'/([A-Za-z0-9\-\.]+)\s+correlation(?:s)?/i'
		];

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $query, $matches)) {
				$geneCandidate = $matches[1];
				break;
			}
		}

		// Fallback for misspelled intent words (e.g. "corrleation of FGFR4").
		if ($geneCandidate == null) {
			$geneCandidate = $this->extractExactGeneSymbolFromQuery($query);
		}
		if ($geneCandidate == null) {
			$geneCandidate = $this->extractRawGeneTokenFromQuery($query);
		}

		$gene = $this->resolveGeneSymbol((string)$geneCandidate);
		if ($gene == null) {
			return null;
		}

		return ['action' => 'correlation_by_gene', 'gene' => $gene];
	}

	private function hasApproxKeywordInQuery($query, $keywords, $maxDistance = 2) {
		$tokens = preg_split('/[^A-Za-z]+/', strtolower((string)$query), -1, PREG_SPLIT_NO_EMPTY);
		if (!is_array($tokens) || empty($tokens)) {
			return false;
		}

		foreach ($tokens as $token) {
			if (strlen($token) < 5) {
				continue;
			}
			foreach ($keywords as $keyword) {
				$keyword = strtolower((string)$keyword);
				if ($token === $keyword) {
					return true;
				}
				if (abs(strlen($token) - strlen($keyword)) > $maxDistance) {
					continue;
				}
				if (levenshtein($token, $keyword) <= $maxDistance) {
					return true;
				}
			}
		}

		return false;
	}

	private function selectToolsByLlm($query, $tools, $cohort_id, $scope = 'project') {
		$llmConfig = Config::get('services.llm', []);
		if (empty($tools)) return [];
		$availableToolNames = [];
		foreach ($tools as $tool) {
			if (!isset($tool['name'])) {
				continue;
			}
			$canonicalName = strtolower(trim((string)$tool['name']));
			if ($canonicalName !== '') {
				$availableToolNames[$canonicalName] = trim((string)$tool['name']);
			}
		}

		$toolDescriptions = array_map(function ($tool) {
			return [
				'name' => $tool['name'] ?? '',
				'description' => $tool['description'] ?? '',
				'inputSchema' => $tool['inputSchema'] ?? [],
			];
		}, $tools);

		$toolsJson = json_encode($toolDescriptions, JSON_PRETTY_PRINT);
		$scopeContext = match ($scope) {
			'project' => "This chatbot is fixed to project ID {$cohort_id}. The server injects project_id, or cohort_type=project and cohort_id for cohort tools.",
			'cancer_type' => "This chatbot is fixed to cancer type '{$cohort_id}'. The server injects cohort_type=cancer_type and this exact cohort_id.",
			default => 'This is the global chatbot. It may only list the projects and cancer types visible to the user.',
		};
		$prompt = "You are a tool selector for a Clinomics genomics chatbot.\n\n" .
			"Chatbot scope: {$scope}. {$scopeContext}\n\n" .
			"Available MCP tools:\n$toolsJson\n\n" .
			"User query: $query\n\n" .
			"Select only from the tools shown above and provide their non-context arguments. Do not invent a different project or cancer type ID. " .
			"For gene-related tools, extract the gene symbol from the query and correct minor typos. " .
			"For pathogenic mutation queries, use the exact tool name get_pathogeic_mutations. Treat cancer types, disease names, and acronyms such as NSCLC as the diagnosis argument; gene_id is optional unless the query names a gene.\n" .
			"Whenever the query names a cancer type or disease, pass it as the diagnosis argument of every selected tool whose schema accepts one, for example \"the alterations of FOXO1 in Osteosarcoma\".\n" .
			"One tool is usually enough. Return several tools only when the query asks for more than one kind of data at once. " .
			"\"Alteration\" or \"alterations\" of a gene means its genomic alterations: copy number, fusions and pathogenic mutations, so select get_project_cnv, get_fusion_genes and get_pathogeic_mutations together. " .
			"Add expression_by_gene on top of those when the query also asks for the expression level.\n" .
			"A page tool such as fusion_by_gene is only for a simple single-topic question like \"show me the fusion of FGFR4\"; as soon as the answer has to be filtered, counted or combined with other information, use the data tool get_fusion_genes instead.\n" .
			"Return at most 4 tools, and only combine tools that return data; never combine a tool that only returns a page URL with another tool.\n" .
			"Return strict JSON only: {\"tool_calls\": [{\"tool_name\": \"<name>\", \"arguments\": {<key>: <value>}}]}\n" .
			"If no tool matches, return: {\"tool_calls\": []}";

		try {
			$text = $this->dispatchLlmTextRequest($prompt, $llmConfig);
			if (!is_string($text) || trim($text) == '') return [];

			$parsed = $this->parseIntentJsonFromText($text);
			if (!is_array($parsed)) return [];

			$candidates = [];
			if (isset($parsed['tool_calls']) && is_array($parsed['tool_calls'])) {
				$candidates = $parsed['tool_calls'];
			} elseif (isset($parsed['tool_name'])) {
				$candidates = [$parsed];
			}

			$selections = [];
			foreach ($candidates as $candidate) {
				if (!is_array($candidate)) continue;
				$toolName = $this->canonicalLlmToolName($candidate['tool_name'] ?? '', $availableToolNames);
				if ($toolName === null || isset($selections[$toolName])) continue;
				$selections[$toolName] = [
					'tool_name' => $toolName,
					'arguments' => (array)($candidate['arguments'] ?? []),
				];
				if (count($selections) >= 4) break;
			}

			return array_values($selections);
		} catch (\Exception $e) {
			Log::warning('LLM tool selection failed.', ['message' => $e->getMessage()]);
			return [];
		}
	}

	private function canonicalLlmToolName($rawName, $availableToolNames) {
		$toolName = strtolower(trim((string)$rawName));
		if ($toolName == '' || $toolName == 'none') return null;

		$toolAliases = [
			'getpathogenicmutations' => 'get_pathogeic_mutations',
			'get_pathogenic_mutations' => 'get_pathogeic_mutations',
			'getpathogeicmutations' => 'get_pathogeic_mutations',
			'pathogenic_mutations' => 'get_pathogeic_mutations',
			'pathogeic_mutations' => 'get_pathogeic_mutations',
		];
		$collapsedToolName = preg_replace('/[^a-z0-9_]+/', '', $toolName);
		if (isset($toolAliases[$toolName])) {
			$toolName = $toolAliases[$toolName];
		} elseif ($collapsedToolName !== null && isset($toolAliases[$collapsedToolName])) {
			$toolName = $toolAliases[$collapsedToolName];
		}

		return $availableToolNames[$toolName] ?? null;
	}

	private function availablePlotLlmProviders($llmConfig) {
		// The generic LLM_API_KEY / LLM_ENDPOINT is shared by every provider block as a
		// fallback in config/services.php. That makes openai and anthropic *appear*
		// configured even when only a Groq (OpenAI-compatible) key is present. Only treat a
		// provider as genuinely available when it has its own dedicated key, distinct from
		// the shared generic key. openai_compatible is the legitimate consumer of the
		// generic key, so it is allowed to use it.
		$genericKey = trim((string)($llmConfig['api_key'] ?? ''));
		$available = [];

		// Gemini: dedicated GEMINI_API_KEY (distinct from the shared generic key).
		$geminiKey = trim((string)($llmConfig['gemini']['api_key'] ?? ''));
		if ($geminiKey !== '' && $geminiKey !== $genericKey) {
			$available[] = 'gemini';
		}

		// OpenAI-compatible (Groq/Ollama/etc.): allowed to use the shared generic key.
		$compatKey = trim((string)$this->getLlmSetting($llmConfig, 'openai_compatible', 'api_key', ''));
		$compatEndpoint = trim((string)$this->getLlmSetting($llmConfig, 'openai_compatible', 'endpoint', ''));
		$genericEndpoint = strtolower(trim((string)($llmConfig['endpoint'] ?? '')));
		$genericIsCompatible = $genericKey !== '' && (
			stripos($genericKey, 'gsk_') === 0
			|| ($genericEndpoint !== '' && strpos($genericEndpoint, 'api.openai.com') === false)
		);
		if ($genericIsCompatible || ($compatKey !== '' && $compatEndpoint !== '')) {
			$available[] = 'openai_compatible';
		}

		// Native OpenAI: only with a dedicated OpenAI key that is NOT the shared generic key
		// and is not a Groq (gsk_) key.
		$openaiKey = trim((string)($llmConfig['openai']['api_key'] ?? ''));
		if ($openaiKey !== '' && $openaiKey !== $genericKey && stripos($openaiKey, 'gsk_') !== 0) {
			$available[] = 'openai';
		}

		// Anthropic: only with a dedicated Anthropic key that is NOT the shared generic key.
		$anthropicKey = trim((string)($llmConfig['anthropic']['api_key'] ?? ''));
		if ($anthropicKey !== '' && $anthropicKey !== $genericKey && stripos($anthropicKey, 'gsk_') !== 0) {
			$available[] = 'anthropic';
		}

		// Guarantee at least Gemini is attempted even if key detection is imperfect.
		if (empty($available)) {
			$available[] = 'gemini';
		}
		return array_values(array_unique($available));
	}

	private function dispatchLlmTextRequest($prompt, $llmConfig, $forceProvider = null) {
		$preferred = strtolower((string)($llmConfig['provider'] ?? 'gemini'));
		if ($preferred === 'claude') {
			$preferred = 'anthropic';
		}
		if (in_array($preferred, ['groq', 'openai-compatible', 'openai_compatible'], true)) {
			$preferred = 'openai_compatible';
		}

		$openAiApiKey = (string)$this->getLlmSetting($llmConfig, 'openai', 'api_key', '');
		$openAiEndpoint = strtolower(rtrim((string)$this->getLlmSetting($llmConfig, 'openai', 'endpoint', 'https://api.openai.com/v1'), '/'));
		$hasOpenAiCompatibleConfig = trim((string)$this->getLlmSetting($llmConfig, 'openai_compatible', 'api_key', '')) !== ''
			|| trim((string)$this->getLlmSetting($llmConfig, 'openai_compatible', 'endpoint', '')) !== '';

		// Auto-prioritize OpenAI-compatible provider when config clearly indicates it.
		if (
			$preferred === 'openai'
			&& (
				stripos($openAiApiKey, 'gsk_') === 0
				|| strpos($openAiEndpoint, 'api.openai.com') === false
				|| $hasOpenAiCompatibleConfig
			)
		) {
			$preferred = 'openai_compatible';
			Log::info('LLM provider preference switched to openai_compatible based on endpoint/key configuration.', [
				'original_provider' => 'openai',
				'endpoint' => $openAiEndpoint,
			]);
		}

		// When a caller forces a specific provider (e.g. plot-generation fallback), try only that provider.
		if ($forceProvider !== null) {
			$forceProvider = strtolower((string)$forceProvider);
			if ($forceProvider === 'claude') {
				$forceProvider = 'anthropic';
			}
			if (in_array($forceProvider, ['groq', 'openai-compatible', 'openai_compatible'], true)) {
				$forceProvider = 'openai_compatible';
			}
			$providerOrder = [$forceProvider];
		} else {
			$providerOrder = array_values(array_unique(array_merge(
				['gemini', $preferred],
				['openai_compatible', 'openai', 'anthropic']
			)));
		}

		foreach ($providerOrder as $provider) {
			if (!in_array($provider, ['openai_compatible', 'openai', 'gemini', 'anthropic'], true)) {
				continue;
			}

			if ($provider === 'openai_compatible' && $this->isOpenAiCompatibleTemporarilyUnavailable()) {
				$this->noteChatbotLlmError('openai_compatible', 'cooldown', null, 'temporary transport cooldown', true);
				Log::warning('LLM provider skipped due to temporary openai_compatible transport cooldown.', ['provider' => 'openai_compatible']);
				continue;
			}

			if ($provider === 'gemini' && $this->isGeminiTemporarilyUnavailable()) {
				$this->noteChatbotLlmError('gemini', 'cooldown', null, 'temporary transport cooldown', false);
				Log::warning('LLM provider skipped due to temporary Gemini transport cooldown.', ['provider' => 'gemini']);
				continue;
			}

			if (!$this->hasLlmApiKey($llmConfig, $provider)) {
				Log::warning('LLM provider skipped due to missing API key.', ['provider' => $provider]);
				continue;
			}

			try {
				if ($provider === 'openai_compatible') {
					$text = $this->requestOpenAiCompatibleText($prompt, $llmConfig);
				} elseif ($provider === 'openai') {
					$text = $this->requestOpenAiText($prompt, $llmConfig);
				} elseif ($provider === 'anthropic') {
					$text = $this->requestAnthropicText($prompt, $llmConfig);
				} else {
					$text = $this->requestGeminiText($prompt, $llmConfig);
				}

				if (is_string($text) && trim($text) !== '') {
					$this->clearChatbotLlmError();
					if (($this->chatbotLlmTrace['provider'] ?? null) == null) {
						$this->chatbotLlmTrace['provider'] = $provider;
					}
					Log::info('LLM provider request succeeded.', ['provider' => $provider]);
					return $text;
				}

				Log::warning('LLM provider returned empty response.', ['provider' => $provider]);
				// Do not overwrite a more specific error already captured in provider-specific request logic.
				$this->noteChatbotLlmError($provider, 'empty_response', null, 'provider returned empty response', false);
			} catch (\Exception $e) {
				// Preserve earlier detailed errors such as curl_35 instead of replacing with generic exception markers.
				$this->noteChatbotLlmError($provider, 'exception', null, $e->getMessage(), false);
				Log::warning('LLM provider request exception.', [
					'provider' => $provider,
					'message' => $e->getMessage(),
				]);
			}
		}

		return null;
	}

	private function hasLlmApiKey($llmConfig, $provider) {
		if ($provider === 'openai_compatible') {
			$apiKey = (string)$this->getLlmSetting($llmConfig, 'openai_compatible', 'api_key', '');
			if (trim($apiKey) === '') {
				$apiKey = (string)$this->getLlmSetting($llmConfig, 'openai', 'api_key', '');
			}
			return trim($apiKey) !== '';
		}

		$apiKey = (string)$this->getLlmSetting($llmConfig, $provider, 'api_key', '');
		return trim($apiKey) !== '';
	}

	private function getLlmSetting($llmConfig, $provider, $key, $default = null) {
		$providerConfig = (array)($llmConfig[$provider] ?? []);
		$providerValue = $providerConfig[$key] ?? null;
		if ($providerValue !== null && trim((string)$providerValue) !== '') {
			return $providerValue;
		}

		$genericValue = $llmConfig[$key] ?? null;
		if ($genericValue !== null && trim((string)$genericValue) !== '') {
			return $genericValue;
		}

		return $default;
	}

	private function getGeminiCooldownCacheKey() {
		return 'chatbot:llm:gemini:transport_cooldown';
	}

	private function isGeminiTemporarilyUnavailable() {
		if (self::GEMINI_COOLDOWN_SECONDS <= 0) {
			return false;
		}
		try {
			return Cache::has($this->getGeminiCooldownCacheKey());
		} catch (\Throwable $e) {
			Log::warning('Gemini cooldown cache read failed.', ['message' => $e->getMessage()]);
			return false;
		}
	}

	private function markGeminiTemporarilyUnavailable($reason = '') {
		if (self::GEMINI_COOLDOWN_SECONDS <= 0) {
			return;
		}
		try {
			Cache::put(
				$this->getGeminiCooldownCacheKey(),
				['reason' => (string)$reason, 'at' => date('c')],
				now()->addSeconds(self::GEMINI_COOLDOWN_SECONDS)
			);
			Log::warning('Gemini marked temporarily unavailable due to transport failure.', [
				'cooldown_seconds' => self::GEMINI_COOLDOWN_SECONDS,
				'reason' => $reason,
			]);
		} catch (\Throwable $e) {
			Log::warning('Gemini cooldown cache write failed.', ['message' => $e->getMessage()]);
		}
	}

	private function getOpenAiCompatibleCooldownCacheKey() {
		return 'chatbot:llm:openai_compatible:transport_cooldown';
	}

	private function isOpenAiCompatibleTemporarilyUnavailable() {
		if (self::OPENAI_COMPAT_COOLDOWN_SECONDS <= 0) {
			return false;
		}
		try {
			return Cache::has($this->getOpenAiCompatibleCooldownCacheKey());
		} catch (\Throwable $e) {
			Log::warning('OpenAI-compatible cooldown cache read failed.', ['message' => $e->getMessage()]);
			return false;
		}
	}

	private function markOpenAiCompatibleTemporarilyUnavailable($reason = '') {
		if (self::OPENAI_COMPAT_COOLDOWN_SECONDS <= 0) {
			return;
		}
		try {
			Cache::put(
				$this->getOpenAiCompatibleCooldownCacheKey(),
				['reason' => (string)$reason, 'at' => date('c')],
				now()->addSeconds(self::OPENAI_COMPAT_COOLDOWN_SECONDS)
			);
			Log::warning('OpenAI-compatible marked temporarily unavailable due to transport failure.', [
				'cooldown_seconds' => self::OPENAI_COMPAT_COOLDOWN_SECONDS,
				'reason' => $reason,
			]);
		} catch (\Throwable $e) {
			Log::warning('OpenAI-compatible cooldown cache write failed.', ['message' => $e->getMessage()]);
		}
	}

	private function requestGeminiText($prompt, $llmConfig) {
		$apiKey = (string)$this->getLlmSetting($llmConfig, 'gemini', 'api_key', '');
		if (trim($apiKey) == '') {
			return null;
		}

		$model = (string)$this->getLlmSetting($llmConfig, 'gemini', 'model', 'gemini-3.5-flash-lite');
		$allowedGeminiModels = ['gemini-3.5-flash-lite', 'gemini-3.5-flash', 'gemini-3.1-flash-lite', 'gemini-3.1-flash'];
		if (!in_array($model, $allowedGeminiModels, true)) {
			Log::warning('Configured Gemini model is not in the allowlist; using gemini-3.5-flash-lite instead.', [
				'configured_model' => $model,
			]);
			$model = 'gemini-3.5-flash-lite';
		}
		$endpoint = rtrim((string)$this->getLlmSetting($llmConfig, 'gemini', 'endpoint', 'https://generativelanguage.googleapis.com/v1beta'), '/');
		$temperature = (float)($llmConfig['temperature'] ?? 0);
		$maxOutputTokens = max(1000, min(32768, (int)($llmConfig['max_output_tokens'] ?? 16384)));
		$requestTimeout = max(20, min(120, (int)($llmConfig['request_timeout'] ?? 60)));
		$expectsJson = stripos($prompt, 'Return JSON only') !== false;
		$modelsToTry = array_values(array_unique(array_filter([
			$model,
			'gemini-3.5-flash-lite',
			'gemini-3.5-flash',
			'gemini-3.1-flash-lite',
			'gemini-3.1-flash',
		])));
		$endpointsToTry = array_values(array_unique(array_filter([
			$endpoint,
			preg_replace('#/v1beta$#', '/v1', $endpoint),
			preg_replace('#/v1$#', '/v1beta', $endpoint),
		])));

		foreach ($endpointsToTry as $tryEndpoint) {
			foreach ($modelsToTry as $tryModel) {
				$url = rtrim($tryEndpoint, '/') . '/models/' . $tryModel . ':generateContent?key=' . $apiKey;
				$payload = [
					'contents' => [
						[
							'parts' => [
								['text' => $prompt]
							]
						]
					],
					'generationConfig' => [
						'temperature' => $temperature,
						'maxOutputTokens' => $maxOutputTokens,
					]
				];
				if ($expectsJson) {
					$payload['generationConfig']['responseMimeType'] = 'application/json';
				}

				$tlsPolicy = 'default';
				$request = Http::timeout($requestTimeout);
				if (defined('CURLOPT_SSLVERSION') && defined('CURL_SSLVERSION_TLSv1_3')) {
					$request = $request->withOptions([
						'curl' => [
							CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_3,
						],
					]);
					$tlsPolicy = 'forced_tls1_3';
				}

				try {
					$response = $request->post($url, $payload);
				} catch (\Illuminate\Http\Client\ConnectionException $e) {
					$message = (string)$e->getMessage();
					$this->markGeminiTemporarilyUnavailable($message);
					Log::warning('Gemini transport failure.', [
						'endpoint' => $tryEndpoint,
						'model' => $tryModel,
						'tls_policy' => $tlsPolicy,
						'message' => $message,
					]);

					if ($tlsPolicy === 'forced_tls1_3') {
						try {
							$tlsPolicy = 'default';
							$response = Http::timeout($requestTimeout)->post($url, $payload);
						} catch (\Illuminate\Http\Client\ConnectionException $e2) {
							$this->markGeminiTemporarilyUnavailable((string)$e2->getMessage());
							Log::warning('Gemini transport failure after TLS fallback.', [
								'endpoint' => $tryEndpoint,
								'model' => $tryModel,
								'tls_policy' => $tlsPolicy,
								'message' => (string)$e2->getMessage(),
							]);
							return null;
						}
					} else {
						return null;
					}
				}

				if ($response->ok()) {
					$body = $response->json();
					$text = (string)data_get($body, 'candidates.0.content.parts.0.text', '');
					if (trim($text) !== '') {
						$this->chatbotLlmTrace['provider'] = 'gemini';
						$this->chatbotLlmTrace['model'] = $tryModel;
						$this->chatbotLlmTrace['finish_reason'] = data_get($body, 'candidates.0.finishReason');
						$this->chatbotLlmTrace['output_tokens'] = data_get($body, 'usageMetadata.candidatesTokenCount');
						return $text;
					}
					Log::warning('Gemini API response was empty.', [
						'endpoint' => $tryEndpoint,
						'model' => $tryModel,
					]);
					continue;
				}

				$status = $response->status();
				$bodyText = substr((string)$response->body(), 0, 500);
				Log::warning('Gemini API request failed.', [
					'status' => $status,
					'endpoint' => $tryEndpoint,
					'model' => $tryModel,
					'tls_policy' => $tlsPolicy,
					'body' => $bodyText,
				]);

				// Stop early for auth/quota errors; retries won't help.
				if ($status === 401 || $status === 403 || $status === 429) {
					return null;
				}

				// This specific model is retired for the account; no value in trying it on other API versions.
				if ($status === 404 && stripos($bodyText, 'no longer available to new users') !== false) {
					continue;
				}
			}
		}

		return null;
	}

	private function requestOpenAiText($prompt, $llmConfig) {
		$apiKey = (string)$this->getLlmSetting($llmConfig, 'openai', 'api_key', '');
		if (trim($apiKey) == '') {
			return null;
		}

		$endpoint = rtrim((string)$this->getLlmSetting($llmConfig, 'openai', 'endpoint', 'https://api.openai.com/v1'), '/');
		if (stripos($apiKey, 'gsk_') === 0 && stripos($endpoint, 'api.openai.com') !== false) {
			Log::warning('OpenAI provider skipped due to non-OpenAI key prefix. Check provider/endpoint mapping.', [
				'key_prefix' => 'gsk_',
			]);
			return null;
		}

		$model = (string)$this->getLlmSetting($llmConfig, 'openai', 'model', 'gpt-4o-mini');
		$temperature = (float)($llmConfig['temperature'] ?? 0);
		$maxOutputTokens = max(1000, min(32768, (int)($llmConfig['max_output_tokens'] ?? 16384)));
		$requestTimeout = max(20, min(120, (int)($llmConfig['request_timeout'] ?? 60)));
		$url = $endpoint . '/chat/completions';

		$response = Http::timeout($requestTimeout)
			->withToken($apiKey)
			->acceptJson()
			->post($url, [
				'model' => $model,
				'temperature' => $temperature,
				'max_tokens' => $maxOutputTokens,
				'messages' => [
					[
						'role' => 'system',
						'content' => 'You are a strict JSON generator.',
					],
					[
						'role' => 'user',
						'content' => $prompt,
					]
				]
			]);

		if (!$response->ok()) {
			Log::warning('OpenAI API request failed.', [
				'status' => $response->status(),
				'body' => substr((string)$response->body(), 0, 500),
			]);
			return null;
		}

		$body = $response->json();
		$this->chatbotLlmTrace['provider'] = 'openai';
		$this->chatbotLlmTrace['model'] = $model;
		return (string)data_get($body, 'choices.0.message.content', '');
	}

	private function requestOpenAiCompatibleText($prompt, $llmConfig) {
		if ($this->isOpenAiCompatibleTemporarilyUnavailable()) {
			$this->noteChatbotLlmError('openai_compatible', 'cooldown', null, 'temporary transport cooldown', true);
			return null;
		}

		$apiKey = (string)$this->getLlmSetting($llmConfig, 'openai_compatible', 'api_key', '');
		if (trim($apiKey) == '') {
			$apiKey = (string)$this->getLlmSetting($llmConfig, 'openai', 'api_key', '');
		}
		if (trim($apiKey) == '') {
			return null;
		}

		$model = (string)$this->getLlmSetting($llmConfig, 'openai_compatible', 'model', '');
		if (trim($model) == '') {
			$model = (string)$this->getLlmSetting($llmConfig, 'openai', 'model', 'llama-3.1-8b-instant');
		}

		$endpoint = rtrim((string)$this->getLlmSetting($llmConfig, 'openai_compatible', 'endpoint', ''), '/');
		if (trim($endpoint) == '') {
			$endpoint = rtrim((string)$this->getLlmSetting($llmConfig, 'openai', 'endpoint', ''), '/');
		}
		if (trim($endpoint) == '') {
			$endpoint = 'https://api.groq.com/openai/v1';
		}

		$temperature = (float)($llmConfig['temperature'] ?? 0);
		$maxOutputTokens = max(1000, min(32768, (int)($llmConfig['max_output_tokens'] ?? 16384)));
		$requestTimeout = max(20, min(120, (int)($llmConfig['request_timeout'] ?? 60)));
		$url = $endpoint . '/chat/completions';
		$maxAttempts = 2;

		for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
			try {
				$tlsPolicy = 'default';
				$request = Http::timeout($requestTimeout)
					->withToken($apiKey)
					->acceptJson();

				if ($attempt === 1 && defined('CURLOPT_SSLVERSION') && defined('CURL_SSLVERSION_TLSv1_3')) {
					$request = $request->withOptions([
						'curl' => [
							CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_3,
						],
					]);
					$tlsPolicy = 'forced_tls1_3';
				}

				$response = $request->post($url, [
						'model' => $model,
						'temperature' => $temperature,
						'max_tokens' => $maxOutputTokens,
						'messages' => [
							[
								'role' => 'system',
								'content' => 'You are a strict JSON generator.',
							],
							[
								'role' => 'user',
								'content' => $prompt,
							]
						]
					]);

				if (!$response->ok()) {
					$body = $response->json();
					$errorCode = data_get($body, 'error.code');
					if ($errorCode == null || trim((string)$errorCode) === '') {
						$errorCode = 'http_' . $response->status();
					}
					$this->noteChatbotLlmError(
						'openai_compatible',
						$errorCode,
						(string)$response->status(),
						(string)data_get($body, 'error.message', ''),
						true
					);
					Log::warning('OpenAI-compatible API request failed.', [
						'status' => $response->status(),
						'endpoint' => $endpoint,
						'model' => $model,
						'attempt' => $attempt,
						'tls_policy' => $tlsPolicy,
						'body' => substr((string)$response->body(), 0, 500),
					]);
					return null;
				}

				$body = $response->json();
				$this->chatbotLlmTrace['provider'] = 'openai_compatible';
				$this->chatbotLlmTrace['model'] = $model;
				return (string)data_get($body, 'choices.0.message.content', '');
			} catch (\Illuminate\Http\Client\ConnectionException $e) {
				$message = (string)$e->getMessage();
				$isSslTransportIssue = stripos($message, 'cURL error 35') !== false
					|| stripos($message, 'SSL_ERROR_SYSCALL') !== false;
				$errorCode = 'connection_exception';
				if (preg_match('/cURL error\s+(\d+)/i', $message, $m)) {
					$errorCode = 'curl_' . $m[1];
				}
				$this->noteChatbotLlmError('openai_compatible', $errorCode, null, $message, true);
				$this->markOpenAiCompatibleTemporarilyUnavailable($message);

				Log::warning('OpenAI-compatible transport failure.', [
					'endpoint' => $endpoint,
					'model' => $model,
					'attempt' => $attempt,
					'tls_policy' => ($attempt === 1 && defined('CURLOPT_SSLVERSION') && defined('CURL_SSLVERSION_TLSv1_3')) ? 'forced_tls1_3' : 'default',
					'ssl_related' => $isSslTransportIssue,
					'message' => $message,
				]);

				if ($attempt < $maxAttempts && $isSslTransportIssue) {
					continue;
				}
				return null;
			} catch (\Throwable $e) {
				Log::warning('OpenAI-compatible request unexpected exception.', [
					'endpoint' => $endpoint,
					'model' => $model,
					'attempt' => $attempt,
					'message' => $e->getMessage(),
				]);
				return null;
			}
		}

		return null;
	}

	private function requestAnthropicText($prompt, $llmConfig) {
		$apiKey = (string)$this->getLlmSetting($llmConfig, 'anthropic', 'api_key', '');
		if (trim($apiKey) == '') {
			return null;
		}

		$model = (string)$this->getLlmSetting($llmConfig, 'anthropic', 'model', 'claude-3-5-sonnet-latest');
		$endpoint = rtrim((string)$this->getLlmSetting($llmConfig, 'anthropic', 'endpoint', 'https://api.anthropic.com/v1'), '/');
		$anthropicConfig = (array)($llmConfig['anthropic'] ?? []);
		$version = (string)($anthropicConfig['version'] ?? '2023-06-01');
		$temperature = (float)($llmConfig['temperature'] ?? 0);
		$maxOutputTokens = max(1000, min(32768, (int)($llmConfig['max_output_tokens'] ?? 16384)));
		$requestTimeout = max(20, min(120, (int)($llmConfig['request_timeout'] ?? 60)));
		$url = $endpoint . '/messages';

		$response = Http::timeout($requestTimeout)
			->withHeaders([
				'x-api-key' => $apiKey,
				'anthropic-version' => $version,
			])
			->acceptJson()
			->post($url, [
				'model' => $model,
				'max_tokens' => $maxOutputTokens,
				'temperature' => $temperature,
				'messages' => [
					[
						'role' => 'user',
						'content' => $prompt,
					]
				]
			]);

		if (!$response->ok()) {
			Log::warning('Anthropic API request failed.', [
				'status' => $response->status(),
				'body' => substr((string)$response->body(), 0, 500),
			]);
			return null;
		}

		$body = $response->json();
		$this->chatbotLlmTrace['provider'] = 'anthropic';
		$this->chatbotLlmTrace['model'] = $model;
		return (string)data_get($body, 'content.0.text', '');
	}

	private function parseIntentJsonFromText($text) {
		$text = trim((string)$text);
		$text = preg_replace('/^```json\s*/i', '', $text);
		$text = preg_replace('/^```\s*/i', '', $text);
		$text = preg_replace('/\s*```$/', '', $text);

		$parsed = json_decode($text, true);
		if (is_string($parsed)) {
			$parsed = json_decode($parsed, true);
		}
		if (is_array($parsed)) {
			return $parsed;
		}

		if (preg_match('/\{.*\}/s', $text, $m)) {
			$parsed = json_decode($m[0], true);
			if (is_array($parsed)) {
				return $parsed;
			}
		}

		return null;
	}

	private function normalizeParsedIntent($parsed) {
		if (!is_array($parsed)) {
			return null;
		}

		$action = strtolower((string)($parsed['action'] ?? ''));
		$gene = $this->resolveGeneSymbol((string)($parsed['gene'] ?? ''));
		if ($gene == null) {
			return null;
		}

		if ($action === 'expression_by_gene') {
			return ['action' => 'expression_by_gene', 'gene' => $gene];
		}

		if ($action === 'mutation_by_gene') {
			$type = $this->normalizeMutationTypeFromQuery((string)($parsed['type'] ?? 'somatic'));
			if ($type == null) {
				$type = 'somatic';
			}
			return ['action' => 'mutation_by_gene', 'gene' => $gene, 'type' => $type];
		}

		if ($action === 'fusion_by_gene') {
			return ['action' => 'fusion_by_gene', 'gene' => $gene];
		}

		if ($action === 'cnv_by_gene') {
			return ['action' => 'cnv_by_gene', 'gene' => $gene];
		}

		return null;
	}

	public function getExpression($project_id, $gene_list, $genome_version = 'all', $library_type = 'all') {
		if ($project_id == "all" || $project_id == "any")
			return json_encode(Gene::getExpression($gene_list, $genome_version, $library_type));
		$gs = explode(' ', $gene_list);
		$genes = array();
		foreach ($gs as $g) {
			if (rtrim($g) != '')
				$genes[] = $g;
		}
		$project = Project::getProject($project_id);
		$project_data = $project->getGeneExpression($genes, $genome_version, $library_type, 'gene', false);
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

	public function getExpressionByGeneList($project_id, $patient_id="null", $case_id="null", $gene_list="MYCN", $genome_version = 'hg19', $library_type = 'all', $value_type="tpm") {
		if ($genome_version == 'null')
			$genome_version = "hg19";
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
		$tumor_project_data = $project->getGeneExpression($genes, $genome_version, $library_type, 'gene', true, 'all', $value_type);
		//$tumor_project_data['patient_meta'] = $project->getPatientMetaData();
		$normal_project = Project::getNormalProject();
		$normal_project_data = null;
		if ($normal_project != null)		
			$normal_project_data = $normal_project->getGeneExpression($genes, $genome_version, $library_type, 'gene', true, 'normal', $value_type);
		//$normal_project_data['patient_meta'] = $normal_project->getPatientMetaData();
		return json_encode(array("hight_light_samples" => $hight_light_samples, "tumor_project_data"=> $tumor_project_data, "normal_project_data" => $normal_project_data, "gene_meta" => $gene_meta));		
	}

	public function getExpressionByLocus($project_id, $patient_id, $case_id, $chr, $start_pos, $end_pos, $genome_version, $library_type) {		
		$genes = Gene::getGeneListByLocus($chr, $start_pos, $end_pos, $genome_version);
		$gene_list = implode(' ', $genes);
		return $this->getExpressionByGeneList($project_id, $patient_id, $case_id, $gene_list, $genome_version, $library_type);		
	}

	public function getPCAData($project_id, $value_type="all", $genome_version="hg19") {
		ini_set('memory_limit', '1024M');
		$project = Project::getProject($project_id);
		$value_type = ($value_type == "zscore")? ".zscore" : "";
		$genome = ".$genome_version";
		$loading_file = storage_path()."/project_data/$project_id/pca$genome-loading$value_type.tsv";
		$groups = [];
		Log::info($loading_file);
		if (!file_exists($loading_file) && $genome_version == "hg19") {
			$genome = "";
			$loading_file = storage_path()."/project_data/$project_id/pca$genome-loading$value_type.tsv";
		}
		if (!file_exists($loading_file)) {
			return json_encode(array("status"=>"no data"));
		}
		$coord_file = storage_path()."/project_data/$project_id/pca$genome-coord$value_type.tsv";
		$std_file = storage_path()."/project_data/$project_id/pca$genome-std$value_type.tsv";		
		
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
		$genome_versions = explode(',', $project->getGenomeVersion());
		$survival_diags = $project->getSurvivalDiagnosis();	
		$view_name = "viewSurvivalByExpression";
		if ($include_header == "Y")
			$view_name = "viewSurvivalByExpressionHeader";

		return View::make("pages/$view_name",['project' => $project, 'symbol'=>$symbol, 'survival_diagnosis' => $survival_diags, 'show_search' => $show_search, 'include_header' => $include_header, 'type'=>$type, 'selected_diagnosis' => $selected_diagnosis, 'genome_versions' => $genome_versions]);	
	}

	public function viewTIL($project_id) {		
		return View::make('pages/viewTIL',['cohort_id' => $project_id, 'cohort_type' => 'Project', 'include_public' => '']);
	}

	public function getTIL($project_id, $format="json") {
		$project = Project::getProject($project_id);
		$til = $this->getDataTableJson($project->getTCellExTRECT());
		if ($format == "text") {
			$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='."$project->name-TIL.tsv");
			$content = $this->dataTableToTSV($til["cols"], $til["data"]);
			return Response::make($content, 200, $headers);			
		}	
		return json_encode($til);
	}

	public function viewProjectChIPseqIGV($project_id, $patient_id=null, $case_id=null) {
		$project = Project::getProject($project_id);
		$rows = $project->getChIPseq($patient_id, $case_id);
		$chip_samples = [];
   		$celllines = [];
   		$targets = [];
   		foreach ($rows as $row) {
   			if (strtolower($row->library_type) == "input")
   				continue;
   			foreach (glob(storage_path()."/ProcessedResults/chipseq/hg19/$row->sample_id/*.bw") as $filename) {
   				$fn = basename($filename);
   				$celllines[$row->patient_id] = "";
   				$targets[$row->library_type] = "";
   				if ($row->rnaseq_sample == "")
   					$row->rnaseq_sample = "NA";
   				$chip_samples[] = [$row->patient_id, $row->sample_id, $row->sample_name, $row->library_type, $row->rnaseq_sample, $row->tissue_type, $fn];
   			}
   		}
   		$celllines = array_keys($celllines);
   		$targets = array_keys($targets);
   		asort($celllines);
   		asort($targets);
		return View::make("pages/viewChIPseqSamplesIGV",['cohort' => $project, 'chip_samples'=>$chip_samples, 'celllines' => $celllines, 'targets'=> $targets, 'include_public' => '']);
	}

	public function viewChIPseq($project_id) {
		$url = url("/getProjectChIPseq/$project_id/json");
		$files = glob(storage_path()."/project_data/".$project_id."/chipseq/*_hg19_rpkm_annotation.tsv");
		$targets = array();
		foreach ($files as $file) {
			$file = basename($file);
			$target = str_replace("_hg19_rpkm_annotation.tsv", "", $file);
			$targets[] = $target;
		}
		$igv_url = url("/viewProjectChIPseqIGV/$project_id");
		return View::make('pages/viewChIPseqSamples', ['cohort_id' => $project_id, 'url'=>$url, 'cohort_type' => "Project", 'igv_url' => $igv_url, 'targets' => $targets, 'include_public' => '']);
	}

	public function getChIPSeqMatrix($project_id, $target, $format="json") {
		$file = storage_path()."/project_data/$project_id/chipseq/".$target."_hg19_rpkm_annotation.tsv";
		if ($format == "text") {
			$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='."$project_id-$target.tsv");
			$content = file_get_contents($file);
			return Response::make($content, 200, $headers);			
		}
		return $this->fileToTable($file);
	}

	public function getChIPseq($project_id, $format="json", $patient_id=null, $case_id=null) {
		$project = Project::getProject($project_id);
		$rows = $project->getChIPseq($patient_id, $case_id);
		$cutoffs = array();
		$cutoff_data = array();
		foreach ($rows as $row) {
			$cutoff_strs = explode(",", $row->cutoffs);
			foreach($cutoff_strs as $cutoff_str) {
				$cutoff = explode(":", $cutoff_str);
				if (substr($cutoff[0],0,1) == "q") {
					$cutoffs[$cutoff[0]] = "";
					$cutoff_data[$cutoff[0]][$row->sample_id] = $cutoff[1];
				}
			}
		}
		$cols = [["title" => "Library"],["title" => "Target"],["title" => "Diagnosis"],["title" => "Total Reads"],["title" => "Mapped Reads"],["title" => "Mapped Rate"],["title" => "Dup Reads"],["title" => "Dup Rate"],["title" => "Paired-End"],["title" => "SpikeIn"],["title" => "SpikeIn Reads"]];
		$cutoffs = array_keys($cutoffs);
		sort($cutoffs);
		foreach ($cutoffs as $cutoff) {
			$cols[] = ["title" => "Peaks: $cutoff"];
		}
		$cols[] = ["title" => "SuperEnhancer"];
		$data = [];
		$url = url("viewChIPseqSample");
		foreach ($rows as $row) {
			$total_reads = "NA";
			$mapped_rate = "NA";
			if ($row->total_reads != null) {
				if ($row->paired == "Y")
					$row->total_reads = $row->total_reads * 2;
				$mapped_rate = round($row->mapped_reads/$row->total_reads,2);
				$total_reads = number_format($row->total_reads);
			}
			if ($format == "json")
				$row->sample_name = "<a href='$url/$row->patient_id/$row->sample_id' target=_blank>$row->sample_name</a>";
			$row_data = [$row->sample_name,$row->library_type,$row->tissue_type,$total_reads,number_format($row->mapped_reads), $mapped_rate, number_format($row->duplicate_reads), round($row->duplicate_reads/$row->mapped_reads,2), ($format == "json")? $this->formatLabel($row->paired) : $row->paired, ($format == "json")? $this->formatLabel($row->spike_in) : $row->spike_in, number_format($row->spike_in_reads)];

			foreach ($cutoffs as $cutoff) {
				if (array_key_exists($row->sample_id, $cutoff_data[$cutoff]))
					$row_data[] = number_format($cutoff_data[$cutoff][$row->sample_id]);
				else
					$row_data[] = "NA";
			}
			$row_data[] = ($format == "json")? $this->formatLabel($row->super_enhancer) : $row->super_enhancer;
			$data[] = $row_data;
		}
		if ($format == "text") {
			$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='."$project->name-ChIPseq.tsv");
			$content = $this->dataTableToTSV($cols, $data);
			return Response::make($content, 200, $headers);			
		}
		return json_encode(["cols" => $cols, "data" => $data]);
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

	public function getFusionProjectDetail($project_id, $diagnosis=null, $cutoff=null, $format="json") {
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
			$count = $row->count;
			if ($format == "json")
				$count = "<a target=_blank href='$root_url/viewFusionGenes/$project_id/$row->left_gene/$row->right_gene/tier/tier$row->var_level/$diagnosis' class='mytooltip'>".$this->formatLabel($row->count)."</a>";
			$row_value = [$row->left_chr,$row->left_gene,$row->right_chr,$row->right_gene,$row->var_level,$count];
			$data[] = $row_value;

		}
		$time = microtime(true) - $time_start;
		if ($format == "text") {
			$filename = $project->name."-fusions.txt";
			$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='.$filename);
			$content = $this->dataTableToTSV($cols, $data);
			return Response::make($content, 200, $headers);
		}
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

	

	public function viewFusionGenes($project_id, $left_gene, $right_gene = "null", $type = "null", $value = "null", $diagnosis="null", $include_public="N") {
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

		return View::make($view, ['title' => 'Fusion', 'url' => $url, 'project_id' => $project_id, 'patient_id' => 'null', 'case_name' => 'any', 'filter_definition' => $filter_definition, 'setting' => $setting, 'has_qci' => false, 'diagnosis' => $diagnosis, 'include_public' => $include_public]);
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

	public function downloadFusionGenes($project_id, $left_gene, $right_gene = null, $type = null, $value = null) {
		$rows = Project::getFusionGenes($project_id, $left_gene, $right_gene, $type, $value);
		foreach ($rows as $row) {
			unset($row->plot);
			unset($row->igv);
			$tools = json_decode($row->tool);
			$tools_str_arr = array();
			foreach ($tools as $tool) {
				foreach ($tool as $key => $value) {
					$tools_str_arr[] = "$key:$value";
				}
			}			
			$row->tool = implode(", ", $tools_str_arr);
		}
		$project = Project::getProject($project_id);
		$filename = $project->name."-fusion-$left_gene-$right_gene.txt";
		$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='.$filename);
		$data = $this->getDataTableJson(VarAnnotation::postProcessFusion($rows));
		$content = $this->dataTableToTSV($data["cols"], $data["data"]);
		return Response::make($content, 200, $headers);	
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
				$cmd = "Rscript ".app_path()."/scripts/survival_fit.r '$surv_file' '$surv_fit_file' '$surv_summary_file'";
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
		$suv_types = array("event_free", "overall");
		$types = array();
		$source = "";
		$diagnosis = "NA";
		foreach ($suv_types as $type) {
			$files = $project->getSurvivalPvalueFile($type);
			foreach ($files as $file) {
				$file = basename($file);
				Log::info($file);
				if (str_contains($file, '_iter_')) {
					$source = "kmcut_p";
					preg_match("/(.*)\.$type.*txt/", $file, $m );
					$diagnosis = $m[1];
				} elseif (str_contains($file, '_KMopt_')) {
					$source = "kmcut_s";
					preg_match("/(.*)\.$type.*txt/", $file, $m );
					$diagnosis = $m[1];
				} else {
					$source = "pmin";
					preg_match('/pvalues\.(.*)\.tsv/', $file, $m );
					$diagnosis = $m[1];
				}
				$type_label = ucfirst(str_replace("_", " ", $type));
				$types["$type_label - $diagnosis"] = array($type, $diagnosis);				
			}
		}
		return View::make('pages/viewSurvivalListByExpression', ['project_id' => $project_id, 'types' => $types, 'source' => $source]);
	}

	public function getSurvivalListByExpression($project_id, $type, $diagnosis, $source="pmin") {
		$project = Project::getProject($project_id);
		set_time_limit(240);
		$time_start = microtime(true);
		$genes = array();
		$exp_types = array("overall", "event_free");
		$types = array();
		$files = $project->getSurvivalPvalueFile($type, $diagnosis);
		if (count($files) > 0)
			$file = $files[0];
		else
			return "No data";
		$content = file_get_contents($file);
		$lines = explode("\n", $content);
		$cols = array(['title'=>'Gene'],["title"=>"Median"],["title"=>"Median Chisq"],["title"=>"Median Better Group"],["title"=>"Median Pvalue"],["title"=>"Minimum Cutoff"],["title"=>"Minimum Chisq"],["title"=>"Minimum Better Group"],["title"=>"Minimum Pvalue"],["title"=>"FDR"]);
		if ($source != "pmin") {
			$p_label = ($source == "kmcut_s")? "Log-rank p-value" : "Permutation p-value";
			$cols = array(['title'=>'Gene'],["title"=>"Cutoff(log2)"],["title"=>"Chi-square"],["title"=>"Low-N"],["title"=>"Hight-N"],["title"=>"Spearman Correlation"],["title"=>$p_label],["title"=>"FDR"]);

		}		
		$data = array();
		foreach ($lines as $line) {			
			$fields = explode("\t", $line);
			if ($fields[0] == "tracking_id")
				continue;
			$gene = $fields[0];
			#if empty then it is the title
			if ($fields[0] != "") {
				if ($source != "pmin") {
					$fields[1] = round(log10($fields[1]+1)/log10(2),4);
					$fields[2] = round($fields[2], 4);
					$fields[5] = round($fields[5], 4);
					$fields[6] = round($fields[6], 4);
					$fields[7] = round($fields[7], 4);
				}
				if ($fields[1] == "NA")
					continue;
				$data[] = $fields;
			}
		}		
		Log::info("getSurvivalListByExpression time: ". (microtime(true)-$time_start));
		return json_encode(array("cols"=>$cols, "data"=>$data));
	}

	public function getExpSurvivalData($project_id, $target_id, $level, $cutoff=null, $genome_version="hg19", $data_type="overall", $value_type="tpm", $diag="any") {
		if ($cutoff == "null")
			$cutoff = null;
		$diag = urldecode($diag);
		Log::info("diagnosis: $diag");
		$project = Project::getProject($project_id);
		$surv_file = $project->getExpSurvivalFile($target_id, $genome_version, $level, $data_type, $value_type, $diag);
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

				//$pvalue_file = storage_path()."/project_data/$project_id/survival/survival_pvalue.$target_id.$genome_version.$data_type.$value_type.$diag.tsv";
				$pvalue_file = $surv_file.".pvalue.tsv";
				$pvalue_summary_file = $surv_file.".summary.tsv";
				//$pvalue_summary_file = storage_path()."/project_data/$project_id/survival/survival_pvalue.$target_id.$genome_version.$data_type.$value_type.$diag.summary.tsv";
				//if (!file_exists($pvalue_file) && !file_exists($pvalue_summary_file)) {
					$cmd = "Rscript ".app_path()."/scripts/survival_pvalues.r '$surv_file' '$pvalue_file' '$pvalue_summary_file'";	
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
				list($median_survival_file, $median_high_num, $median_low_num) = $this->calculateExpSurvival($project_id, $target_id, $level, $median, $genome_version, $data_type, $value_type, $diag);
				$user_cutoff = $min_cutoff;
				$user_pvalue = $min_pvalue;
				$pvalue_file_content = file_get_contents($pvalue_file);
				$pvalue_file_lines = explode("\n", $pvalue_file_content);				
				foreach ($pvalue_file_lines as $line) {
					$line = trim($line);
					$fields = preg_split('/\s+/', $line);
					if (count($fields) == 3)
						$pvalue_data[] = array($fields[0], round($fields[1], 3), round($fields[2], 3));
				}
				$median_data = $this->getExpSurvivalFileContent($median_survival_file, $patient_surv_time);

			} else {
				$user_cutoff = $cutoff;
			}

			list($user_survival_file, $user_high_num, $user_low_num) = $this->calculateExpSurvival($project_id, $target_id, $level, $user_cutoff, $genome_version, $data_type, $value_type, $diag);			
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

	public function calculateExpSurvival($project_id, $target_id, $level, $cutoff, $genome_version="hg19", $data_type="overall", $value_type="tpm", $diag="any") {
		$project = Project::getProject($project_id);
		$surv_file = $project->getExpSurvivalFile($target_id, $genome_version, $level, $data_type, $value_type, $diag);
		$text_file = $surv_file."$cutoff.text";
		//$plot_file = storage_path()."/survival/$project_id"."_survival_pvalue$cutoff.$target_id.$genome_version.svg";
		//$text_file = storage_path()."/project_data/$project_id/survival/survival_pvalue$cutoff.$target_id.$genome_version.$data_type.$diag.tsv";
		$cmd = "Rscript ".app_path()."/scripts/survival_fit_exp.r '$surv_file' '$text_file' $cutoff";
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

	public function getCorrelationData($project_id, $gene_id, $cutoff=0.2, $genome_version="hg19", $method="pearson", $value_type="tpm") {
		set_time_limit(240);
		ini_set('memory_limit', '1024M');
		$project = Project::getProject($project_id);
		list($corr_p, $corr_n) = $project->getCorrelation($gene_id, $cutoff, $genome_version, $method, $value_type);
		arsort($corr_p, SORT_NUMERIC);
		//$corr_p_topn = array_slice($corr_p, 0, $top_n);
		asort($corr_n, SORT_NUMERIC);
		//$corr_n_topn = array_slice($corr_n, 0, $top_n);		
		//if ($genome_version=="ensembl")
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
		//$json_p = $this->getCorrelationHeatmapJson($corr_p_topn, $project_id, $gene_id, $genome_version);
		//$json_n = $this->getCorrelationHeatmapJson($corr_n_topn, $project_id, $gene_id, $genome_version);
		//$best_gene = array_keys($corr_p_topn)[0];
		//list($best_gene, $best_symbol) = explode(',', $best_gene);
		//$json = array("p"=>$json_p, "n"=>$json_n, "table_data" => $table_data);
		return json_encode($table_data);
   	}


	public function getTwoGenesDotplotData($project_id, $g1, $g2, $genome_version) {
		Log::info("g1 - g2: $g1 - $g2");
		$project = Project::getProject($project_id);
		$exp_data = $project->getGeneExpression(array($g1, $g2), $genome_version, "all");

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
			$exp_value1 = $exp_data["exp_data"][$g1][$genome_version][$i];
			$exp_value2 = $exp_data["exp_data"][$g2][$genome_version][$i];
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

	public function getExpMatrixFile($project_id, $data_type, $genome_version="hg19") {
		//$pathToFile = storage_path()."/project_data/$project_id/$genome_version-gene.$lib_type.$value_type.tsv";
		$pathToFile = storage_path()."/project_data/$project_id/expression.${data_type}.${genome_version}.tsv";
		if (!file_exists($pathToFile))
			$pathToFile = storage_path()."/project_data/$project_id/expression.${data_type}.tsv";
		return response()->download($pathToFile);
	}

	public function getIsofromZippedFile($project_id) {
		//$pathToFile = storage_path()."/project_data/$project_id/$genome_version-gene.$lib_type.$value_type.tsv";
		$pathToFile = storage_path()."/project_data/$project_id/isoforms.zip";
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
		return View::make('pages/viewFusionProjectDetail', ['cohort_name' => $project->name, 'cohort_id' =>$project_id, 'cohort_type' => 'Project', 'setting' => $setting, 'filter_definition' => $filter_definition, 'diags' => $diags, 'include_public' => '']);
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

	public function getPathogeicMutations($project_id, $diagnosis = "null", $gene_id = "null", $topGeneOnly = false) {
		$project = Project::getProject($project_id);

		if (!$topGeneOnly) {
			$rows = $project->getPathogeicMutations($diagnosis, $gene_id);
			$url = url('/viewPatient');
			foreach ($rows as $row) {
				$row->case_id = "<a target=_blank href=\"$url/$project_id/$row->patient_id/$row->case_id\">$row->case_id</a>";
			}
		} else {
			$rows = $project->getPathogeicCount($diagnosis, $gene_id);
		}

		return $this->getDataTableJson($rows);
		
	}

	public function viewQCITypeProjectDetail($project_id, $type) {
		$filter_definition = array();
		$filter_lists = UserGeneList::getDescriptions($type);
		foreach ($filter_lists as $list_name => $desc) {
			$filter_definition[$list_name] = $desc;
		}
		$project = Project::getProject($project_id);

		return View::make('pages/viewQCITypeProjectDetail', ['cohort_id' => $project_id, 'cohort_type' => 'Project', 'type' => $type, 'filter_definition' => $filter_definition]);
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
			return View::make('pages/viewQCIProjectDetail', ['cohort' => $project, 'cohort_type' => 'Project', 'types' => $types, 'filter_definition' => $filter_definition]);
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
		return View::make('pages/viewVarProjectDetail', ['cohort_id' => $project_id, 'cohort_type' => "Project", 'type' => $type, 'setting' => $setting, 'filter_definition' => $filter_definition, 'diag_counts' => $diag_counts, 'diagnosis' => $diagnosis, 'annotation' => $annotation, 'meta' => $meta, 'has_variant_file' => $project->hasVariantFile($type), 'include_public' => '']);
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

	public function getProjectCases($project_id) {
		$rows = Project::getCases($project_id);
		return json_encode($this->getDataTableJson($rows));
	}

	public function getProjectSampleCases($project_id) {
		$rows = Project::getSampleCases($project_id);
		return json_encode($this->getDataTableJson($rows));
	}

	public function getProjectPatients($project_id) {
		$rows = Project::getPatients($project_id);
		return json_encode($this->getDataTableJson($rows));
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
		return View::make('pages/viewMixcr',['cohort_id'=>$project_id,'cohort_type' => 'Project', 'type'=>$type, 'include_public' => '']);
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

	public function getProjectHLA($project_id, $format="json") {
		$project = Project::getProject($project_id);
		$rows = $project->getHLA();
		$callers = array();
		$values = array();
		foreach ($rows as $row) {
			if ($row->tissue_cat == "normal" && !Config::get('site.isPublicSite'))
				continue;
			$callers[$row->caller] = "";
			$key = implode(";", [$row->patient_id, $row->case_id, $row->sample_id, $row->allele, $row->tissue_cat, $row->tissue_type]);
			$values[$key][$row->caller] = $row->value;
		}
		$cols = [["title"=>"Patient ID"],["title"=>"Case ID"],["title"=>"Sample ID"],["title"=>"Allele"],["title"=>"Tumor/Normal"],["title"=>"Tissue type"]];
		$callers = array_keys($callers);
		foreach ($callers as $caller) {
			$cols[] = ["title" => $caller];
		}
		$data = [];
		foreach ($values as $key=>$value) {
			$row_data = explode(";", $key);
			foreach ($callers as $caller) {
				if (array_key_exists($caller, $values[$key])) {
					$row_data[] = $values[$key][$caller];
				} else {
					$row_data[] = "NA";
				}				
			}
			$data[] = $row_data;
		}
		if ($format == "text") {
			$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='."$project->name-HLA.tsv");
			$content = $this->dataTableToTSV($cols, $data);
			return Response::make($content, 200, $headers);			
		}
		return json_encode(["cols" => $cols, "data" => $data]);
	}

	public function getProjectSTR($project_id, $format="json") {
		$project = Project::getProject($project_id);
		$rows = $project->getSTR();
		$data = $this->getDataTableJson($rows);
		if ($format == "text") {
			$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='."$project->name-STR.tsv");
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
		$pathToFile = storage_path()."/project_data/$project_id/variants/$project_id.$type.merged.zip";
		Log::info("downloading $pathToFile");
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

	public function getPacBioData($project_id, $search_field, $search_value) {
		$search_value = strtoupper($search_value);
		$project = Project::getProject($project_id);
		if ($project == null) {
			return json_encode(array("status"=>"no data"));
		}
		
		$query = DB::table('pacbio_orf_report');
		if ($search_field === 'gene') {
			$query->where('gene_name', '=', $search_value);
		} elseif ($search_field === 'tcons') {
			$query->where('tcons', '=', $search_value);
		} else {
			return json_encode(array("status"=>"invalid search field"));
		}
		
		$rows = $query->get();
		
		if (count($rows) == 0) {
			return json_encode(array("status"=>"no data"));
		}
		
		$data = $this->getDataTableJson($rows);

		// Add IGV column as first column and map link target from the ID column.
		array_unshift($data['cols'], array("title" => "IGV"));
		$id_col_index = null;
		foreach ($data['cols'] as $idx => $col) {
			if (!isset($col['title']))
				continue;
			if (strtolower($col['title']) == 'id') {
				$id_col_index = $idx;
				break;
			}
		}

		$root_url = url('/');
		foreach ($data['data'] as &$row) {
			$igv_html = "";
			if ($id_col_index !== null && isset($row[$id_col_index - 1])) {
				$pacbio_id = $row[$id_col_index - 1];
				$href = "$root_url/viewPacBioIGV/$project_id/$pacbio_id";
				$igv_html = "<a target=_blank href='$href'><img width=15 height=15 src='$root_url/images/igv.jpg' title='View in IGV'></a>";
			}
			array_unshift($row, $igv_html);
		}
		
		return json_encode($data);
	}

	public function getPacBioSamples($project_id) {
		$project = Project::getProject($project_id);
		if ($project == null) {
			return json_encode(array("status"=>"no data"));
		}

		$rows = DB::table('pacbio_samples')->get();
		if (count($rows) == 0) {
			return json_encode(array("status"=>"no data"));
		}

		return json_encode($this->getDataTableJson($rows));
	}

	public function downloadPacbio($project_id, $cell_line_count, $tumor_count, $normal_count) {
		set_time_limit(240);

		$project = Project::getProject($project_id);
		if ($project == null) {
			return View::make('pages/error', ['message' => "Project $project_id not found!"]);
		}

		Log::info("Downloading PacBio data for project $project_id with cell_line_count >= $cell_line_count, tumor_count >= $tumor_count, normal_count <= $normal_count");
		
		$rows = DB::table('pacbio_orf_report_filtered')
			->where('cell_line_count', '>=', $cell_line_count)
			->where('tumor_count',     '>=',  $tumor_count)
			->where('normal_count',    '<=', $normal_count)
			->get();
	
		Log::info("Found " . count($rows) . " rows matching the criteria.");
		$filename = "pacbio_{$project_id}_cl{$cell_line_count}_t{$tumor_count}_n{$normal_count}.tsv";

		$headers = [
			'Content-Type'        => 'text/tab-separated-values',
			'Content-Disposition' => 'attachment; filename="' . $filename . '"',
		];

		Log::info("Preparing to output data as TSV with headers: " . json_encode($headers));
		$output = '';
		if (count($rows) > 0) {
			$output .= implode("\t", array_keys((array) $rows[0])) . "\n";
			foreach ($rows as $row) {
				$output .= implode("\t", array_values((array) $row)) . "\n";
			}
		}
		Log::info("Outputting data with length: " . strlen($output));

		return Response::make($output, 200, $headers)
			->cookie('pacbio_download_ready', '1', 5, '/', null, false, false);
	}

	private function processSamples($str) {
	    if (empty($str)) {
	        return [];
	    }

	    $items = explode(',', $str);

	    $items = array_map(function ($item) {
	        $item = trim($item);
	        $item = preg_replace('/^Sample_\d+_/', '', $item); // remove Sample_x_
	        $item = preg_replace('/_RNA\d*$/', '', $item);     // remove _RNA, _RNA2, _RNA3, etc.
	        return $item;
	    }, $items);

	    $items = array_filter($items, function ($item) {
	        $gtf = storage_path("ProcessedResults/pacbio/per_sample/{$item}.filtered.sorted.gtf.gz");
	        return file_exists($gtf);
	    });

	    return array_values($items);
	}

	public function viewPacBioIGV($project_id, $id) {
		$project = Project::getProject($project_id);
		if ($project == null) {
			return View::make('pages/error', ['message' => "Project $project_id not found!"]);
		}

		$row = DB::table('pacbio_orf_report')->where('ID', '=', $id)->first();
		if ($row == null)
			$row = DB::table('pacbio_orf_report')->where('id', '=', $id)->first();

		if ($row == null) {
			return View::make('pages/error', ['message' => "PacBio record $id not found!"]);
		}

		$tumors = $this->processSamples($row->tumor);
	    	$normals = $this->processSamples($row->normal);

		return View::make('pages/viewPacBioIGV', ['tumors' => $tumors, 'normals' => $normals, 'gene' => $row->gene_name, 'project_id' => $project_id]);
	}

	public function getPacBioGTF($project_id, $sample, $type) {
	    $extension = ($type == "gtf") ? "gtf.gz" : "gtf.gz.tbi";
	    $file = storage_path("ProcessedResults/pacbio/per_sample/{$sample}.filtered.sorted.{$extension}");
		Log::info("Fetching PacBio GTF file: $file");

	    if (!file_exists($file)) {
	        Log::warning("PacBio GTF file not found: $file");
	        abort(404, 'File not found');
	    }

	    $mime = ($type == "gtf") ? 'application/gzip' : 'application/octet-stream';

	    return response()->file($file, [
	        'Content-Type' => $mime,
	        'Content-Disposition' => 'inline; filename="' . basename($file) . '"',
	        'Access-Control-Allow-Origin' => '*',
	        'Access-Control-Allow-Headers' => 'Range, Content-Type',
	        'Access-Control-Expose-Headers' => 'Content-Length, Content-Range, Accept-Ranges',
	        'Accept-Ranges' => 'bytes',
	    ]);
	}
	
}
