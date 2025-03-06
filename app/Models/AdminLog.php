<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use DB,Log,Config,File,Lang;

/**
 *
 * OR mapping class for table 'access_log'. This table keeps track of the access of genes, patients and projects.
 *
 * @copyright 2018 Javed Khan's group
 * @author Hsien-chao Chou
 * @package models
 */
class AdminLog extends Model {
	protected $fillable = [];
	protected $primaryKey = null;
	protected $table = 'admin_log';

	
	/**
	 * 
	 * Get the log information from specific user
	 *
	 * @param int $user_id User ID
	 * @param string $type ['patient, 'gene', 'project']
	 * @return Array Array of AccessLog objects
	 */
	static public function getAll() {
		$db_type = Config::get("site.db_connection");
		$convert = "to_char(p.id)";
		if ($db_type == "mysql") {
			$convert = "convert(p.id,char)";
		}
		$sql = "select u1.first_name as user_name,u1.last_name as user_last_name,u2.first_name as admin_name,u2.last_name as admin_last_name,a.action,a.target,a.created_at, p.name from user_profile u1, user_profile u2, admin_log a left join projects p on a.target=$convert where u1.user_id=a.user_id and u2.user_id=a.admin_id";
		$rows = DB::select($sql);
		foreach ($rows as $row) {
			$row->user_name = "$row->user_name $row->user_last_name";
			$row->admin_name = "$row->admin_name $row->admin_last_name";
			if ($row->name != "")
				$row->target = $row->name;
			unset($row->user_last_name);
			unset($row->admin_last_name);
			unset($row->name);
		}
		return $rows;
	}

	

}


