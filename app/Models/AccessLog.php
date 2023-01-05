<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

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

}


