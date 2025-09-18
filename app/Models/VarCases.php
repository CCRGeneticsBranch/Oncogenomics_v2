<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use DB,Log;

class VarCases extends Model {
    protected $fillable = [];
    protected $table = 'cases';
    protected $primaryKey = null;
    public $incrementing = false;
    
    static public function getCases($project_id)
    {
        $logged_user = User::getCurrentUser();
        if ($logged_user == null)
            return array();
        $condition = "and exists(select * from project_cases p2, user_projects u where p2.project_id=u.project_id and u.user_id=$logged_user->id and p2.patient_id=c.patient_id and p2.case_name=c.case_name)";
        if ($project_id != "any")
            $condition = "and exists(select * from project_cases p2 where p.patient_id=p2.patient_id and p2.project_id=$project_id and p2.case_name=c.case_name)";
        $sql = "select p.patient_id, p.diagnosis, c.case_id, c.case_name, c.finished_at as pipeline_finish_time, c.updated_at as upload_time, status,version from cases c, patients p where c.patient_id=p.patient_id $condition order by p.patient_id ASC, c.finished_at DESC";
        Log::info($sql);
        $rows = DB::select($sql);//Added ordering for download query

        if ($project_id != "any") {
            $cnts = DB::select("select p.patient_id,p.case_name,exp_type,count(*) as cnt from project_cases p, sample_cases c where p.project_id=$project_id and p.patient_id=c.patient_id and p.case_name=c.case_name group by p.patient_id,p.case_name,c.exp_type");
            $exp_types = array();
            $exp_type_counts = array();
            foreach ($cnts as $cnt) {
                $exp_types[$cnt->exp_type] = '';
                $exp_type_counts["$cnt->patient_id,$cnt->case_name,$cnt->exp_type"] = $cnt->cnt;
            }
            $exp_types = array_keys($exp_types);
            foreach ($rows as $row) {
                unset($row->status);
                foreach ($exp_types as $exp_type) {
                    $value = "0";
                    if (array_key_exists("$row->patient_id,$row->case_name,$exp_type", $exp_type_counts))
                        $value = $exp_type_counts["$row->patient_id,$row->case_name,$exp_type"];
                    $row->{$exp_type} = $value;
                }

            }
        }
        
        return $rows;
    }    

    static public function getCase($patient_id, $case_id)
    {
        $sql = "select distinct c1.*,c2.case_name from processed_cases c1 left join cases c2 on c1.patient_id=c2.patient_id and c1.case_id=c2.case_id where c1.patient_id='$patient_id'";
        if ($case_id != "any")
            $sql = $sql." and c1.case_id='$case_id'";
        $rows = DB::select($sql);
            //$rows = VarCases::where('patient_id', '=', $patient_id)->where('case_id', '=', $case_id)->get();
        if (count($rows) == 0)
            return null;
        return $rows[0];
    }

    static public function getCount() {
        $logged_user = User::getCurrentUser();
        if ($logged_user != null)
            return DB::select("select count(distinct case_name) as cnt from project_processed_cases p1, user_projects p2 where p1.project_id=p2.project_id and p2.user_id=$logged_user->id")[0]->cnt;
        return 0;
    }

    static public function getPatientCount() {
        $logged_user = User::getCurrentUser();
        if ($logged_user != null)
            return DB::select("select count(distinct patient_id) as cnt from project_processed_cases p1, user_projects p2 where p1.project_id=p2.project_id and p2.user_id=$logged_user->id")[0]->cnt;
        return 0;
    }

    static public function getCaseNames()
    {
        $rows = DB::select("select distinct patient_id, case_name from cases");
        $cases = array();
        foreach ($rows as $row) {
            $cases[$row->patient_id][] = $row->case_name;
        }
        return $cases;
    }

    static public function getSamples($patient_id, $case_id = "any", $project_id = "any")
    {
        $project_condition = "";
        if ($project_id != "any") {
            $project_condition = " and exists(select * from project_samples sp where sp.project_id=$project_id and sp.sample_id=s1.sample_id)";
        }
        if ($case_id == "any")
            return DB::select("select distinct s1.*,s2.sample_name, s2.exp_type path from processed_sample_cases s1, samples s2, cases c where s1.patient_id=c.patient_id and s1.case_id=c.case_id and s1.patient_id=c.patient_id and s1.sample_id=s2.sample_id and s1.patient_id='$patient_id' $project_condition");
        $sql = "select distinct s1.*,s2.sample_name, s2.exp_type, c.path from processed_sample_cases s1, samples s2, cases c where s1.patient_id=c.patient_id and s1.case_id=c.case_id and s1.patient_id = c.patient_id and s1.sample_id=s2.sample_id and s1.patient_id='$patient_id' and s1.case_id='$case_id' $project_condition";
        Log::info($sql);
        return DB::select($sql);
    }    

    static public function publish($patient_id, $case_id){
        return VarCases::where('patient_id', '=', $patient_id)->where('case_id', '=', $case_id)->update(array('status' => "passed"));
    }

    static public function getPath($patient_id, $case_id)
    {
        if ($case_id == "any")
            $rows = VarCases::where('patient_id', '=', $patient_id)->get();
        else
            $rows = DB::select("select * from cases where patient_id = '$patient_id' and (case_id='$case_id' or case_name='$case_id')");
            //$rows = VarCases::where('patient_id', '=', $patient_id)->where('case_id', '=', $case_id)->orwhere('case_name', '=', $case_id)->get();
        if (count($rows) == 0)
            return null;
        return $rows[0]->path;
    }

    static public function getProcessedSampleCases($patient_id, $case_name=null, $exp_type=null) {
        $case_condition = '';
        $exp_type_condition = '';
        if ($case_name != null)
            $case_condition = "and c.case_name = '$case_name'";
        if ($exp_type != null)
            $exp_type_condition = "and s.exp_type = '$exp_type'";
        $sql = "select distinct s.sample_id, s.sample_name, s.sample_alias, c.case_id, c.path from sample_cases c, samples s where s.sample_id=c.sample_id and s.patient_id='$patient_id' $case_condition and c.case_id is not null and c.path is not null $exp_type_condition";
        Log::info("================= getProcessedSampleCases ===============");
        Log::info($sql);
        return DB::select($sql);
        
    }
    static public function getExpressionSamples($patient_id, $case_name) {
        $rows = VarCases::getProcessedSampleCases($patient_id, $case_name, 'RNAseq');
        $root = storage_path()."/ProcessedResults/";
        $exp_samples = array();        
        foreach ($rows as $row) {
            Log::info("Looking for expression data: case_id: $row->case_id, sample_id: $row->sample_id, sample_name, $row->sample_name");
            $exp_file = Sample::getExpressionFile($root.$row->path."/$patient_id/$row->case_id", $row->sample_id, $row->sample_name, 'refseq', 'gene');
            if ($exp_file == "")
                $exp_file = Sample::getExpressionFile($root.$row->path."/$patient_id/$row->case_id", $row->sample_id, $row->sample_name, 'ensembl', 'gene');
            if ($exp_file != "")
                $exp_samples[$row->sample_alias] = $row->sample_id;
        }
        return $exp_samples;
    }

    static public function getChIPseqSamples($project_id, $patient_id, $case_id) {
        $case_condition = '';
        if ($case_id != null)
            $case_condition = "and s1.case_id = '$case_id'";
        $project_condition = '';
        if ($project_id != null || $project_id != "null" || $project_id != "any")
            $project_condition = "and s2.project_id = $project_id";
        $sql = "select distinct s1.*,s2.sample_name, s2.exp_type, 'chipseq' as path from processed_sample_cases s1, project_samples s2 where s1.patient_id='$patient_id' and s1.sample_id=s2.sample_id and s1.patient_id='$patient_id' $case_condition $project_condition";
        Log::info($sql);
        return DB::select($sql);

    }

    static public function getMixcrSamples($patient_id, $case_name,$type) {
        $rows = VarCases::getProcessedSampleCases($patient_id, $case_name);
        
        $root = storage_path()."/ProcessedResults/";
        $mix_samples = array();
        foreach ($rows as $row) {
        #    $mix_files = Sample::getMixcrFile($root.$row->path."/$patient_id/$case_id", $row->sample_id, $row->sample_name, $type);
        #    if ($mix_files != "")
        #        $mix_samples[$row->sample_name] = $row->sample_id;
            $case_id = $row->case_id;
            Log::info($row->sample_id);
            Log::info($root.$row->path."/$patient_id/$case_id/".$row->sample_name."/mixcr");
            if($type=="mixcr"){
              if (file_exists($root.$row->path."/$patient_id/$case_id/".$row->sample_id."/mixcr"))
                  $mix_samples[$row->sample_id] = $case_id;
		          //the sample folder name for clinomics is sample name.
		      if (file_exists($root.$row->path."/$patient_id/$case_id/".$row->sample_name."/mixcr"))
                  $mix_samples[$row->sample_name] = $case_id;
            }
            if($type=="rna"){
              if (file_exists($root.$row->path."/$patient_id/$case_id/".$row->sample_id."/mixcr/convert.".$row->sample_id.".clones.RNA.txt"))
                  $mix_samples[$row->sample_id] = $case_id;
		          //the sample folder name for clinomics is sample name.
		      if (file_exists($root.$row->path."/$patient_id/$case_id/".$row->sample_name."/mixcr/convert.".$row->sample_name.".clones.RNA.txt"))
                  $mix_samples[$row->sample_name] = $case_id;
              if (file_exists($root.$row->path."/$patient_id/$case_id/".$row->sample_name."/mixcr/convert.".$row->sample_id.".clones.RNA.txt"))
                  $mix_samples[$row->sample_name] = $case_id;
              if (file_exists($root.$row->path."/$patient_id/$case_id/".$row->sample_name."/mixcr/convert.".$row->sample_name.".clonotypes.ALL.txt"))
                  $mix_samples[$row->sample_name] = $case_id;
            }
            if($type=="tcr"){
                if (file_exists($root.$row->path."/$patient_id/$case_id/".$row->sample_id."/mixcr/convert.".$row->sample_id.".clones.TCR.txt"))
                  $mix_samples[$row->sample_id] = $case_id;
		        //the sample folder name for clinomics is sample name.
		        if (file_exists($root.$row->path."/$patient_id/$case_id/".$row->sample_name."/mixcr/convert.".$row->sample_name.".clones.TCR.txt"))
                  $mix_samples[$row->sample_name] = $case_id;
                if (file_exists($root.$row->path."/$patient_id/$case_id/".$row->sample_name."/mixcr/convert.".$row->sample_id.".clones.TCR.txt"))
                  $mix_samples[$row->sample_name] = $case_id;                
            }
        }
        return $mix_samples;
    }


}
