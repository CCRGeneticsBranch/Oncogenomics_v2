<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Project;
use App\Models\Patient;
use App\Models\Sample;
use App\Models\VarCases;
use App\Models\AccessLog;
use App\Models\AdminLog;
use Response,Config,DB,Log,Lang,View;

class ReportController extends BaseController {

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

	public function viewAccessLogSummary() {
		return View::make('pages/viewAccessLogSummary',[]);
		
	}

	public function getAccessLogSummary($period="all", $by="YYYY-MM") {
		Log::info("getAccessLogSummary");
		$events = AccessLog::getEvents($period);
		$distinct = AccessLog::getEvents($period, "distinct");
		$distinct_users = AccessLog::getDistinctUsers($period);
		$events_by_time = AccessLog::getEventGroupByTime($period, $by);
		$event_gourps = AccessLog::getEventGroups($period);
		$event_diagnosis = AccessLog::getEventByDiagnosis($period);
		$event_project_groups = AccessLog::getEventProjectGroups($period);
		$event_email_domain = AccessLog::getEventByEmailDomain($period);
		$event_users = AccessLog::getEventByUsers($period);

		return json_encode(["events"=>$events, "distinct"=>$distinct, "distinct_users" => $distinct_users, "events_by_time" => $events_by_time, 'event_gourps' => $event_gourps, 'event_diagnosis' => $event_diagnosis, 'event_project_groups' => $event_project_groups, 'event_email_domain' => $event_email_domain, 'event_users' => $event_users], true);
		
	}

	public function downloadAccessLogSummary($period, $by, $type) {
		Log::info("downloadAccessLogSummary");
		$data = array();
		$hidden_cols = ["type"];
		if ($type == "patient" || $type == "gene")
			$hidden_cols = ["name"];
		if ($type == "event")
			$data = AccessLog::getEventGroupByTime($period, $by);
		if ($type == "project" || $type == "patient" || $type == "gene")
			$data = AccessLog::getEventGroups($period, $type);
		if ($type == "diagnosis")
			$data = AccessLog::getEventByDiagnosis($period);
		if ($type == "projectgroup")
			$data = AccessLog::getEventProjectGroups($period);
		if ($type == "emailgroup")
			$data = AccessLog::getEventByEmailDomain($period);
		if ($type == "user")
			$data = AccessLog::getEventByUsers($period);

		$content = $this->DBRowsToTSV($data, $hidden_cols);
		$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='."Accesslog-$period-$type.txt");
		return Response::make($content, 200, $headers);
	}

	public function getAdminLog() {
		return $this->getDataTableJson(AdminLog::getAll());;
		
	}
	
}
