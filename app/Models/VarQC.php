<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB,Log,Config,Lang;

class VarQC extends Model {
	protected $fillable = [];
    protected $table = 'var_qc';
    protected $primaryKey = null;
    public $incrementing = false;
	
    static public function getQCByProjectID($project_id, $type="dna", $format="json")
    {
        $sql = "select v.*,s.exp_type, s.library_type, s.tissue_cat from var_qc v, project_samples s where v.sample_id=s.sample_id and s.project_id=$project_id and v.type='$type'";
        $var_qcs = DB::select($sql);
        Log::info($sql);
        return VarQC::getQC($var_qcs, $type, $format);
    }

    static public function getQCByCancerType($cancer_type_id, $type="dna", $format="json")
    {
        $logged_user = User::getCurrentUser();
        $sql = "select v.*,s.exp_type, s.library_type, s.tissue_cat from var_qc v, project_samples s, user_projects u where u.user_id = $logged_user->id and u.project_id=s.project_id and s.diagnosis='$cancer_type_id' and v.sample_id=s.sample_id and v.type='$type'";
        $var_qcs = DB::select($sql);
        Log::info($sql);
        return VarQC::getQC($var_qcs, $type, $format);
    }

	static public function getQCByPatientID($patient_id, $case_id, $type="dna", $project_id="any")
    {
        $project_condition = "";
        if ($project_id != "any") {
            $project_condition = " and exists(select * from project_samples s2 where s2.project_id = $project_id and s2.sample_id=s.sample_id) ";
        }
        if ($case_id == "any")
            $var_qcs = DB::select("select v.*,s.exp_type, s.library_type, s.tissue_cat from var_qc v, samples s where v.sample_id=s.sample_id and v.patient_id = '$patient_id' and v.type='$type' $project_condition");
        else
            $var_qcs = DB::select("select v.*,s.exp_type, s.library_type, s.tissue_cat from var_qc v, samples s where v.sample_id=s.sample_id and v.patient_id = '$patient_id' and v.case_id='$case_id' and v.type='$type' $project_condition");
        Log::info("select v.*,s.exp_type, s.library_type, s.tissue_cat from var_qc v, samples s where v.sample_id=s.sample_id and v.patient_id = '$patient_id' and v.case_id='$case_id' and v.type='$type' $project_condition");
        return VarQC::getQC($var_qcs, $type);
        
    }

    static function getQC($var_qcs, $type="dna", $format="json") {
        $cols = array(array('title' => 'Patient_ID'), array('title' => 'Sample_ID'));
        $qc_data = array();
        $attrs = array();
        $sample_ids = array();
        $col_names = explode(',',Config::get("onco.qc.$type.cols"));
        $attr_list = array();
        $qc_cutoff_file = storage_path()."/data/".Config::get("onco.qc.cufoff_file");
        list($cutoff_header, $cutoff_data) = Utility::readFileWithHeader($qc_cutoff_file);
        $cuotff_datatable_cols = array();
        foreach ($cutoff_header as $key) {
            $key_label = Lang::get("messages.$key");
            if ($key_label == "messages.$key") {
                $key_label = ucfirst(str_replace("_", " ", $key));
            }
            $cuotff_datatable_cols[] = array('title' => $key_label);            
        }
        $cutoffs = array();
        foreach ($cutoff_data as $d) {
            for ($j=1; $j<count($cutoff_header); $j++) {
                $value = "";
                if (isset($d[$j]))
                    $value = $d[$j];
                $cutoffs[$d[0]][$cutoff_header[$j]] = $value;                
            }
        }
        $exp_types = array();
        $lib_types = array();
        $tissue_cats = array();
        $patient_ids = array();
        foreach ($var_qcs as $var_qc) {
            if (!array_key_exists($var_qc->attr_id, $attrs)) {
               $attr_list[] = $var_qc->attr_id;
               $attrs[$var_qc->attr_id] = '';
            }
            $sample_ids[$var_qc->sample_id] = '';
            $qc_data[$var_qc->sample_id][$var_qc->attr_id] = $var_qc->attr_value;
            $exp_types[$var_qc->sample_id] = $var_qc->exp_type;
            $lib_types[$var_qc->sample_id] = $var_qc->library_type;
            $tissue_cats[$var_qc->sample_id] = $var_qc->tissue_cat;
            $patient_ids[$var_qc->sample_id] = $var_qc->patient_id;
        }

        $sample_id_list = array_keys($sample_ids);
        //$attr_list = array_keys($attrs);
         //Log::info($type);
        foreach ($col_names as $key) {
            $key_label = Lang::get("messages.$key");
            //Log::info($key);
            if ($key_label == "messages.$key") {
                $key_label = ucfirst(str_replace("_", " ", $key));
            }
            $cols[] = array('title' => $key_label);
        }

        $data = array();
        foreach ($sample_id_list as $sample_id) {
            $row_data = array($patient_ids[$sample_id], $sample_id);
            $cutoff_type = "";
            if ($type == "dna") {
                $exp_type = $exp_types[$sample_id];
                $library_type = $lib_types[$sample_id];
                $tissue_cat = $tissue_cats[$sample_id];
                if (strtolower($exp_type) == "panel" && $library_type = 'clin.cnv.v2')
                    $exp_type = "Panel2";
                $cutoff_type = "Ref Min $exp_type ".ucfirst($tissue_cat);                
            }
            foreach ($col_names as $col) 
                if (isset($qc_data[$sample_id][$col])) {
                    $cutoff = "";
                    if (isset($cutoffs[$cutoff_type][$col]))
                        $cutoff = $cutoffs[$cutoff_type][$col];
                    if ($format == "json" && is_numeric($cutoff) && $cutoff > $qc_data[$sample_id][$col])
                        $row_data[] = "<font color=red><B>".$qc_data[$sample_id][$col]."</B></font>";
                    else
                        $row_data[] = $qc_data[$sample_id][$col];
                }
                else
                    $row_data[] = "";
            $data[] = $row_data;
        }

        return array("qc_data" => array("cols" => $cols, "data" => $data), "qc_cutoff" => array("cols" => $cuotff_datatable_cols, "data" => $cutoff_data));
    }

    static public function getHotspotCoverage($samples) {
        $hotspot_data = array();
        foreach($samples as $sample) {            
            $file_content = "";
            $caller = "bwa";
            if ($sample->exp_type == "RNAseq")
                $caller = "star";
            $file = storage_path()."/ProcessedResults/".$sample->path."/$sample->patient_id/$sample->case_id/$sample->sample_name/qc/$sample->sample_name".".$caller.hotspot.depth";            
            if (!file_exists($file)) {
                $file = storage_path()."/ProcessedResults/".$sample->path."/$sample->patient_id/$sample->case_id/$sample->sample_id/qc/$sample->sample_id".".$caller.hotspot.depth";
                if (!file_exists($file)) {
                    $file = storage_path()."/ProcessedResults/".$sample->path."/$sample->patient_id/$sample->case_id/Sample_$sample->sample_id/qc/Sample_$sample->sample_id".".$caller.hotspot.depth";
                    if (!file_exists($file)) {
                        $file = storage_path()."/ProcessedResults/".$sample->path."/$sample->patient_id/$sample->case_id/qc/$sample->sample_id"."_Pair.hotspot.depth";
                        $caller = "tso";
                        if (!file_exists($file))
                            continue;
                        else
                            $file_content = file_get_contents($file);
                    }
                    else
                        $file_content = file_get_contents($file);
                } else
                    $file_content = file_get_contents($file);
            }
            else
                $file_content = file_get_contents($file);            
            if ($file_content != null) {
                $sample_hotspot = array();
                $lines = explode("\n", $file_content);
                foreach ($lines as $line) {
                    $fields = explode("\t", $line);
                    if (count($fields) < 5)
                        continue;
                    if ($fields[3] != "ROCK1")
                        $sample_hotspot[] = array($fields[3], $fields[4], $fields[5]);
                }
            }
            $hotspot_data[$sample->sample_name] = $sample_hotspot;
        }
        return json_encode($hotspot_data);
    }
}
