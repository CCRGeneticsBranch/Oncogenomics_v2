<?php

class QC extends Eloquent {
	protected $fillable = [];
    protected $table = 'qc';
    protected $primaryKey = null;
    public $incrementing = false;
	
	static public function getQCByPatientID($patient_id)
    {
        $rows = QC::where('patient_id', '=', $patient_id)->get();
        $cols = array(array('title' => 'Patient_ID'), array('title' => 'Sample_ID'));
        $qc_data = array();
        $attrs = array();
        $sample_ids = array();
        foreach ($rows as $row) {
        	$attrs[$row->attr_id] = '';
        	$sample_ids[$row->sample_id] = '';
        	$qc_data[$row->sample_id][$row->attr_id] = $row->attr_value;
        }

        $sample_id_list = array_keys($sample_ids);
        $attr_list = array_keys($attrs);

		foreach ($attr_list as $key) {
			$key_label = Lang::get("messages.$key");
			if ($key_label == "messages.$key") {
				$key_label = ucfirst(str_replace("_", " ", $key));
			}
			$cols[] = array('title' => $key_label);
		}

		$data = array();
        foreach ($sample_id_list as $sample_id) {
        	$row_data = array($patient_id, $sample_id);
        	foreach ($attr_list as $attr_id) 
				$row_data[] = $qc_data[$sample_id][$attr_id];
			$data[] = $row_data;
		}

		return array($cols, $data);
    }

}
