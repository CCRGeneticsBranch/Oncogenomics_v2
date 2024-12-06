<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use DB,Log,Config,File,Lang;

class PatientDetail extends Model {
	protected $fillable = [];
	protected $table = 'patient_details';

	static public function updateData ($patient_id, $old_key, $new_key, $value) {
		DB::update('update patient_details set attr_name = ?,  attr_value = ? where patient_id= ? and attr_name = ?', array($new_key, $value, $patient_id, $old_key));
	}

	static public function addData ($patient_id, $key, $value) {
		DB::insert('insert into patient_details values(?,?,?)', array($patient_id, $key, $value));
	}

	static public function deleteData ($patient_id, $key) {
		DB::delete('delete patient_details where patient_id = ? and attr_name = ?', array($patient_id, $key));
	}

	static public function getPatientDetailByProject($project_id) {
		$sql = "select d.* from patient_details d";
		if (strtolower($project_id) != "any" && strtolower($project_id) != "null")
			$sql = "$sql,project_patients p where p.project_id=$project_id and d.patient_id=p.patient_id";
		Log::info($sql);
		$rows = DB::select($sql);
		return $rows;
	}

	static public function getPatientDetailByPatientID($patient_id) {
		return DB::select("select * from patient_details where patient_id='$patient_id'");		
	}

	static function	addDetailsToPatients($patients, $patient_details, $patient_details_cols = []) {
		$detail_array = array();
		$detail_fields = array();
		
		foreach ($patient_details as $patient_detail) {
			$detail_array[$patient_detail->attr_name][$patient_detail->patient_id] = $patient_detail->attr_value;
			$detail_fields[$patient_detail->patient_id][] = $patient_detail->attr_name;
		}
		
		$fields = array();		
		foreach ($patients as $patient) {
			if (isset($detail_fields[$patient->patient_id])) {
				$patient_fields = $detail_fields[$patient->patient_id];
				foreach ($patient_fields as $patient_field) {
					//echo $patient_field. " ";
					$fields[$patient_field] = '';
				}
			}			
		}
		$fields = array_keys($fields);
		
		if (count($patient_details_cols) > 0)
			$fields = $patient_details_cols;

		foreach ($patients as $patient) {
			/*
			foreach ($patient_attrs as $patient_attr) {
				if ($patient_attr->included) {
					$patient->{$patient_attr->display_name} = "";
					if (isset($detail_array[$patient_attr->attr_id][$patient->patient_id]))
						$patient->{$patient_attr->display_name} = $detail_array[$patient_attr->attr_id][$patient->patient_id];
				}
			}
			*/
			
			foreach ($fields as $field) {
				$patient->{$field} = "";				
				if (isset($detail_array[$field][$patient->patient_id])) {
					$patient->{$field} = $detail_array[$field][$patient->patient_id];
				}
			}			
		}
	}

	
	static function	getPatientSpecimenInfo() {
		$specimen_col = "surgical specimen ID";
		$source_bio_col = "Source Biomaterial ID";
		$samples = DB::select("select distinct patient_id, sample_id, source_biomaterial_id from samples");
		$details = DB::select("select distinct sample_id,attr_name, attr_value from sample_details s2 where s2.attr_name='$specimen_col'");
		$sample_ids = array();
		$data = array();
		foreach ($samples as $sample) {
			$sample_ids[$sample->sample_id] = $sample->patient_id;			
			$data[$sample->patient_id][$source_bio_col][$sample->source_biomaterial_id] = '';
		}
		foreach ($details as $detail) {
			if (array_key_exists($detail->sample_id, $sample_ids)) {
				$patient_id = $sample_ids[$detail->sample_id];
				$data[$patient_id][$detail->attr_name][$detail->attr_value] = '';
			}
		}
		$rows = array();
		$patient_ids = array_keys($data);
		foreach ($patient_ids as $patient_id) {			
			$attr_keys = array_keys($data[$patient_id]);
			foreach ($attr_keys as $attr) {
				$patient = new \stdClass();
				$patient->patient_id = $patient_id;
				$patient->attr_name = $attr;
				$patient->attr_value = implode(",", array_keys($data[$patient_id][$attr]));
				$rows[] = $patient;
			}
			
		}
		return $rows;
	}

}
