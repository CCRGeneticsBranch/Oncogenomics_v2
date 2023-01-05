<?php

namespace App\Http\Controllers;
use App\Models\Project;
use App\Models\User;
use App\Models\VarCases;
use App\Models\AccessLog;
use App\Models\Sample;
use App\Models\Gene;
use Lang,DB,Log,Cache;

/**
 *
 * BaseController is the super class of all controllers.
 *
 * @copyright 2018 Javed Khan's group
 * @author Hsien-chao Chou, Scott Goldweber
 * @package controllers
 */
class BaseController extends Controller {

	/**
	 * Setup the layout used by the controller.
	 *
	 * @return void
	 */
	protected function setupLayout()
	{
		if ( ! is_null($this->layout))
		{
			$this->layout = \View::make($this->layout);
		}
	}
	
	/**
	 * 
	 * Convert PHP objects to JQuery DataTable JSON format
	 *	 
	 * @param array $obj_array array of objects
	 * @param array $invisible_columns array of columns you want to exclude.
	 * @return array two elements: cols: The JQuery DataTable columns. data: The JQuery DataTable data (two dim array)
	 */
    protected function getDataTableJson($obj_array, $invisible_columns = []) {		
		$data = array();
		$columns = array();
		$first = true;
		foreach ($obj_array as $obj){			
			$row = array();			
			foreach ((array)$obj as $key=>$value){				
				if (!in_array($key, $invisible_columns)) {
					if ($first) {
						$key_label = Lang::get("messages.$key");
						if ($key_label == "messages.$key") {
							$key_label = ucfirst(str_replace("_", " ", $key));
						}
						$columns[] = array("title" => $key_label);						
					}				
					if ($value == null) $value = '';
					$value = str_replace('¿', 'µ', $value);
					$row[] = "$value";													
				}
			}
			if (count($row) > 0) {
				$data[] = $row;
			}
			if ($first) $first = false;
		}		
		return array("cols" => $columns, "data" => $data);
	}
	
	/**
	 * 
	 * Save the access log data
	 *	 
	 * @param string $target could be patient ID or gene ID
	 * @param int $project_id project ID.
	 * @param string $type project ID.
	 * @return array two elements: code: ['ok','error']. desc: Description
	 */	
	protected function saveAccessLog($target, $project_id, $type) {		
		$logged_user = User::getCurrentUser();
		if ($logged_user == null)
			return array("code"=>"error","desc"=>"no_user");
		try {
			$access_log = new AccessLog();
			$access_log->target = $target;
			$access_log->project_id = $project_id;
			$access_log->type = $type;
			$access_log->user_id = $logged_user->id;
			$access_log->saveLog();
		} catch (\Exception $e) { 
			return array("code"=>"error","desc"=>$e->getMessage());
		}
		return array("code"=>"ok","desc"=>"");
	}
	
	/**
	 * 
	 * Convert a DB table to JQuery DataTable JSON format
	 *	 
	 * @param string $table table name
	 * @param array $invisible_columns array of columns you want to exclude.
	 * @return array two elements: cols: The JQuery DataTable columns. data: The JQuery DataTable data (two dim array)
	 */
	protected function getColumnJson($table, $invisible_columns) {		
		$key = "$table.columns";
		if (\Config::get('onco.cache')) {			
			if (Cache::has($key))
				return Cache::get($key);
		} else {
			//Cache::flush();
		}
		$rows = DB::select("select * from $table where rownum=1");
		$json_cols = array();
		$row = $rows[0];
		foreach ($row as $key=>$value) {
			if (!in_array($key, $invisible_columns)) {
				$key_label = Lang::get("messages.$key");
				if ($key_label == "messages.$key") {
					$key_label = ucfirst(str_replace("_", " ", $key));
				}
				$json_cols[] = array("title" => $key_label);
			}
		}
		if (\Config::get('onco.cache')) Cache::put($key, $json_cols, \Config::get('onco.cache.mins'));
		return $json_cols;
	}

	/**
	 * 
	 * Get current user ID
	 *	 
	 * @return int user ID
	 */
	protected function getUserID() {
		$user_id = null;
		//$user = Session::get('user');
		$user = Sentry::getUser();
		if (isset($user)) {
			$user_id = $user->id;
		}
		return $user_id;

	}

	/**
	 * 
	 * Add the badge css class
	 *	 
	 * @param string $text the text to be formatted
	 * @return string formated text
	 */
	protected function formatLabel($text) {
		if ($text != "")
			return "<span class='badge'>".$text."</span>";
		else
			return "";
	}

	protected function fileToTable($file, $first_column_name=null) {
		$cols = array();		
		$data = array();
		if (file_exists($file)) {
			$content = file_get_contents($file);
			$lines = explode("\n", $content);			
			foreach ($lines as $line) {
				$fields = explode("\t", $line);
				if (count($cols) == 0) {
					if ($first_column_name!=null)
						$cols[] = array("title" => $first_column_name);
					foreach ($fields as $field)
						$cols[] = array("title" => $field);
				} else {
					if (count($cols)==count($fields))					
						$data[] = $fields;					
				}				
			}

		}
		return json_encode(array("cols" => $cols, "data" => $data));		
	}

	/**
	 * 
	 * Convert JQuery DataTable to TSV file
	 *	 
	 * @param array $cols The JQuery DataTable columns
	 * @param array $data The JQuery DataTable data (two dim array)
	 * @return string TSV file content
	 */
	protected function dataTableToTSV($cols, $data) {
		$headers = array();
		$output = "";
		foreach ($cols as $col) {
			$headers[] = $col["title"]; 
		}
		$output .= implode("\t", $headers)."\n";
		foreach ($data as $row) {
			$ol = str_replace("\n", '', implode("\t", $row));
			$ol = str_replace("\r", '', $ol)."\n";
			$output .= $ol;
		}
		return $output;
	}
	
	/**
	 * 
	 * This function return the Oncogenomics home page object
	 *	 
	 * @return view pages/viewHome object
	 */
	public function viewHome() {
		$project_count = Project::getCount();
		$projects = Project::getAll(false);
		$project_id = "any";
		if (count($projects) == 1)
			$project_id = $projects[0]->id;		
		$patient_count = VarCases::getPatientCount();
		$case_count = VarCases::getCount();
		$user = User::getCurrentUser();
		$user_log = array();
		$top_n = 10;
		if ($user != null) {
			$logs = AccessLog::getUserLog($user->id, "patient");
			foreach ($logs as $log) {
				if (count($user_log) > $top_n)
					break;
				$user_log[$log->target] = '';			
			}
		}
		$user_log = array_keys($user_log);
		$project_list = array();
		$gene_list = array();
		$logs = AccessLog::getProjectCount();
		$cnt = 1;
		foreach ($logs as $log) {
			if ($cnt > $top_n)
				break;
			$project_list[$log->project_id] = $log->name;
			$cnt++;
		}
		$logs = AccessLog::getGeneCount();
		$cnt = 1;
		foreach ($logs as $log) {
			if ($cnt > $top_n)
				break;
			$gene_list[] = $log->target;
			$cnt++;
		}
		$rows = Sample::getSampleCountByExpType();
		$exp_types = array();
		foreach ($rows as $row) {
			$exp_types[] = array("name" => ucwords($row->exp_type), "y" => (int)$row->sample_count);
		}
		$rows = Sample::getSampleCountByTissueCat();
		$tissue_cats = array();
		foreach ($rows as $row) {
			$tissue_cats[] = array("name" => ucwords($row->tissue_cat), "y" => (int)$row->sample_count);
		}
		$labmatrixurl=\Config::get('onco.labmatrix');

		$projects = User::getCurrentUserProjects();
		$project_data = array();
		foreach ($projects as $p)
			$project_data[] = array("label" => $p->name, "v" => $p->id);
		
		$patients = User::getCurrentUserPatients();
		$patient_data = array();
		foreach ($patients as $p)
			$patient_data[] = "$p->patient_id";
			

		$samples = User::getCurrentUserSamples();
		$sample_data = array();
		foreach ($samples as $s)
			$sample_data[] = "$s->sample_id";

		$genes = Gene::getAllSymbols();
		$gene_data = array();
		foreach ($genes as $g)
			$gene_data[] = "$g->symbol";

		return \View::make('pages/viewHome', ['project_count' => $project_count, 'patient_count' => $patient_count, 'case_count' => $case_count, 'user_log' => $user_log, 'project_list' => $project_list, 'gene_list' => $gene_list, 'exp_types' => $exp_types, 'tissue_cats' => $tissue_cats, 'project_id' => $project_id , 'user'=>$user,'lbm'=>$labmatrixurl, 'project_data' => json_encode($project_data), "patient_data" => json_encode($patient_data), "sample_data" => json_encode($sample_data), "gene_data" => json_encode($gene_data)]);
	}

	public function broadcast() {
		if (!User::isSuperAdmin())
			return \View::make('pages/error', ['message' => 'Access denied!']);		
		$txtMessage = Input::get('txtMessage');
		$users = User::all();
		foreach($users as $user) {
			$profile = $user->user_profile();
			$email = $user->email_address;
			$my_message = "Dear $profile->first_name,<br><br>$txtMessage<br><br>Sincerely,<br><br>Oncogenomics Team<br>";
			#if ($user->email_address == "hsien-chao.chou@nih.gov" || $user->email_address == "hsienchao.chou@gmail.com") {
					Mail::send('emails.message', array('txtMessage'=>$my_message), function($message) use ($email) {
                	$message->to("$email")->subject("Oncogenomics notification");
            	});
            #}
        }
		return \View::make('pages/error', ['message' => "Message sent"]);
	}
}
