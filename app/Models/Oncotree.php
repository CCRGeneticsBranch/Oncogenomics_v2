<?php

class Oncotree extends Eloquent {
	public $timestamps = false;
	public $incrementing = false;
	protected $fillable = [];
	protected $table = 'oncotree';	

	static public function getPatientTree() {
		//$diagnoses = DB::select('select distinct diagnosis,d.* from diagnosis d, patients p where diagnosis=secondary_name or diagnosis=tertiary_name or diagnosis=quaternary_name or diagnosis=quinternary_name');
		$diagnoses = Diagnosis::all();
		$patients = Patient::search('any', 'any');
		$patient_diagnosis = array();
		$all_patients = array();
		foreach ($patients as $patient) {
			$all_patients[] = $patient->patient_id;
			$patient_diagnosis[strtolower($patient->diagnosis)][] = $patient->patient_id;
		}
		$diagnosis_data = array();
		foreach ($diagnoses as $diagnosis) {
			$arr = array_values($diagnosis->toArray());
			$found = false;
			$patient_list = array();
			for ($i=8; $i>=0;$i-=2) {
				$code = $arr[$i];
				$text = $arr[$i+1];
				$nci_code = $diagnosis->metanci;
				$umls_code = $diagnosis->metaumls;
				$parent_code = ($i == 0)? "root" : $arr[$i-2];				
				if ($code == '')
					continue;
				if (!$found) {
					if (array_key_exists(strtolower($text), $patient_diagnosis)) {
						$found = true;						
					} else {
						continue;
					} 
				}
				if (array_key_exists(strtolower($text), $patient_diagnosis))
					$patient_list = array_unique (array_merge ($patient_list, $patient_diagnosis[strtolower($text)]));
				
				if (array_key_exists($code, $diagnosis_data)) 
					$patient_list = array_unique (array_merge ($patient_list, $diagnosis_data[$code]["patients"]));
				
				$diagnosis_data[$code] = array("id" => $code, "name" => $text, "text" => $text."(".count($patient_list).")", "parent" => $parent_code, "patients" => $patient_list);
			}			
		}
		$diagnosis_data[] = array("id" => "root", "name" => "All Types", "text" => "All Types", "parent" => "0", "patients" => [], "checked" => true);
		//$oncotrees = Oncotree::all();
		ksort($diagnosis_data);		
		$tree = Oncotree::buildTree( array_values($diagnosis_data), 'parent', 'id' );
		return json_encode($tree);
		//return json_encode(array("id" => "root", "text" => "All Types", "parent" => "0","children" => $tree));
	}

	static public function getOncoTree() {
		//$diagnoses = DB::select('select distinct diagnosis,d.* from diagnosis d, patients p where diagnosis=secondary_name or diagnosis=tertiary_name or diagnosis=quaternary_name or diagnosis=quinternary_name');
		$diagnoses = Diagnosis::all();
		
		$diagnosis_data = array();
		foreach ($diagnoses as $diagnosis) {
			$arr = array_values($diagnosis->toArray());
			for ($i=8; $i>=0;$i-=2) {
				$code = $arr[$i];
				$text = $arr[$i+1];
				$nci_code = $diagnosis->metanci;
				$umls_code = $diagnosis->metaumls;
				$parent_code = ($i == 0)? "0" : $arr[$i-2];				
				if ($code == '')
					continue;
				
				$diagnosis_data[$code] = array("id" => $code, "text" => $text, "parent" => $parent_code);
			}			
		}
		//$oncotrees = Oncotree::all();
		ksort($diagnosis_data);
		$code_mapping = array();		
		$tree = Oncotree::buildTree( array_values($diagnosis_data), 'parent', 'id' );

		return json_encode($tree);
	}


	static public function buildTree($flat, $pidKey, $idKey = null) {
	    $grouped = array();
	    foreach ($flat as $sub){
	        $grouped[$sub[$pidKey]][] = $sub;
	    }

	    $fnBuilder = function($siblings) use (&$fnBuilder, $grouped, $idKey) {
	        foreach ($siblings as $k => $sibling) {
	            $id = $sibling[$idKey];
	            if(isset($grouped[$id])) {
	                $sibling['children'] = $fnBuilder($grouped[$id]);
	            }
	            $siblings[$k] = $sibling;
	        }

	        return $siblings;
	    };

	    $tree = $fnBuilder($grouped[0]);

	    return $tree;
	}
	
}
