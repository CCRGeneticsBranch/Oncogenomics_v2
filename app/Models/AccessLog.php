<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use DB,Log,Config,File,Lang;

/**
 *
 * OR mapping class for table 'access_log'. This table keeps track of the access of genes, patients and projects.
 *
 * @copyright 2018 Javed Khan's group
 * @author Hsien-chao Chou, Scott Goldweber
 * @package models
 */
class AccessLog extends Model {
	protected $fillable = [];
	protected $primaryKey = null;
	protected $table = 'access_log';

	/**
	 * 
	 * Save the log data
	 */
	public function saveLog() {
		\DB::table($this->table)->insert(array('target' => $this->target, 'project_id' => $this->project_id, 'type' => $this->type, 'user_id' => $this->user_id, 'created_at' => \Carbon\Carbon::now()));
	}

	/**
	 * 
	 * Get the log information from specific user
	 *
	 * @param int $user_id User ID
	 * @param string $type ['patient, 'gene', 'project']
	 * @return Array Array of AccessLog objects
	 */
	static public function getUserLog($user_id, $type) {
		$logs = AccessLog::where('user_id', $user_id)->where('type',$type)->orderBy('created_at', 'desc')->get();
		//$logs = DB::select("select distinct target from access_log where user_id=$user_id and type='$type' order by created_at desc");
		return $logs;
	}

	/**
	 * 
	 * Get access statistical information by project
	 *
	 * @return Array Array of project and count data
	 */
	static public function getProjectCount() {
		$logged_user = User::getCurrentUser();
		if ($logged_user != null)
			return \DB::select("select a.project_id, p.name, count(*) as project_count from access_log a, projects p, user_projects u where a.project_id<>'any' and a.project_id=p.id and p.id=u.project_id and u.user_id=$logged_user->id group by a.project_id, p.name order by project_count desc");
		return array();;
	}

	/**
	 * 
	 * Get access statistical information by gene
	 *
	 * @return Array Array of gene and count data
	 */
	static public function getGeneCount() {
		$logs = \DB::select("select target, count(*) as gene_count from access_log where type = 'gene' group by target order by gene_count desc");
		return $logs;
	}

	public function setUpdatedAt($value)
	{
		//Do-nothing
	}

	public function getUpdatedAtColumn()
	{
		//Do-nothing
	}

	static public function getEvents($period, $keyword="") {
		$where = "where ".AccessLog::getPeriodCondition($period);
		$sql = "select type, count($keyword target) as cnt from access_log a $where group by type";
		$rows = DB::select($sql);
		return $rows;
	}

	static public function getEventGroupByTime($period, $time_format) {
		$where = "where ".AccessLog::getPeriodCondition($period);
		$db_type = Config::get("site.db_connection");
		Log::info("DB type: $db_type");
		Log::info("time_format: $time_format");
		$convert = "to_char(created_at,'$time_format')";
		if ($db_type == "mysql") {
			$time_format = str_replace("YYYY", "%Y",$time_format);
			$time_format = str_replace("MM", "%m",$time_format);
			$convert = "date_format(created_at,'$time_format')";
		}
		$sql = "select $convert as period,count(*) as cnt, count(distinct user_id) as cnt_users from access_log a $where group by $convert order by $convert";
		Log::info($sql);
		$rows = DB::select($sql);
		return $rows;
	}

	static public function getEventGroups($period, $type="all") {
		$where = "where ".AccessLog::getPeriodCondition($period);
		if ($type != "all")
			$where = $where." and a.type='$type'";
		$db_type = Config::get("site.db_connection");
		Log::info("DB type: $db_type");
		$convert = "to_char(p.id)";
		if ($db_type == "mysql")
			$convert = "convert(p.id,char)";
		$sql = "select target, type, count(*) as cnt, name from access_log a left join projects p on a.target=$convert $where group by target, type, name order by  type, count(*) desc";
		$rows = DB::select($sql);
		return $rows;
	}

	static public function getEventProjectGroups($period) {
		$where = AccessLog::getPeriodCondition($period);
		$db_type = Config::get("site.db_connection");
		Log::info("DB type: $db_type");
		$convert = "to_char(p.id)";
		if ($db_type == "mysql")
			$convert = "convert(p.id,char)";
		$sql = "select project_group, count(*) as cnt from access_log a, projects p where a.type='project' and a.target=$convert and $where group by project_group order by  cnt desc";
		$rows = DB::select($sql);
		return $rows;
	}

	static public function getDistinctUsers($period) {
		$where = "where ".AccessLog::getPeriodCondition($period);
		$sql = "select count(distinct user_id) as cnt from access_log a $where";
		$rows = DB::select($sql);
		return $rows[0]->cnt;
	}

	static public function getEventByDiagnosis($period) {
		$where = AccessLog::getPeriodCondition($period);
		$sql = "select diagnosis, count(*) as cnt from access_log a, patients p where a.type='patient' and a.target=p.patient_id and $where  group by diagnosis order by cnt desc";
		$rows = DB::select($sql);
		return $rows;
	}

	static public function getEventByEmailDomain($period) {
		$where = AccessLog::getPeriodCondition($period);
		$db_type = Config::get("site.db_connection");
		Log::info("DB type: $db_type");
		$extract = "REGEXP_REPLACE (email, '.*@(.*)', '\\1')";
		$group_by = $extract;
		if ($db_type == "mysql") {
			$extract = "substring_index(email,'@',-1)";
			$group_by = "email_domain";
		}
		$sql = "select $extract as email_domain, count(*) as cnt from access_log a, users u where a.user_id=u.id and $where group by $group_by order by  cnt desc";
		$rows = DB::select($sql);
		return $rows;
	}

	static public function getEventByUsers($period) {
		$where = AccessLog::getPeriodCondition($period);
		$db_type = Config::get("site.db_connection");
		Log::info("DB type: $db_type");
		$concat = "(first_name || ' ' || last_name)";
		$group_by = $concat;
		if ($db_type == "mysql") {
			$concat = "concat(first_name, ' ', last_name)";
			$group_by = "name";
		}
		$sql = "select $concat as name, count(*) as cnt from access_log a, user_profile u where a.user_id=u.user_id and $where group by $group_by order by  cnt desc";
		$rows = DB::select($sql);
		return $rows;
	}

	static public function getPeriodCondition($period) {
		$db_type = Config::get("site.db_connection");
		Log::info("DB type: $db_type");
		if ($period == "last30") {
			if ($db_type == "mysql")
				return "a.created_at >= NOW() - INTERVAL 1 MONTH";
			return "a.created_at > sysdate-30";
		}
		if ($period == "last12month") {
			if ($db_type == "mysql")
				return "a.created_at >= NOW() - INTERVAL 12 MONTH";
			return "a.created_at > sysdate-365";
		}
		if ($period == "this_year") {
			if ($db_type == "mysql")
				return "to_char(a.created_at, 'YYYY') = to_char(NOW(), 'YYYY')";
			return "to_char(a.created_at, 'YYYY') = to_char(sysdate, 'YYYY')";
		}
		return "1=1";
	}

}


