<?php

class VarSamples extends Eloquent {
	protected $fillable = [];
    protected $table = 'var_samples';
    protected $primaryKey = null;
    public $incrementing = false;

    static public function getAnnotation($patient_id, $sample_id, $case_id, $type, $flag, $var_hash) {
        
        $rows = DB::select("select v1.* from var_annotation_details v1, var_samples v2 where 
                    v2.patient_id = '$patient_id' and
                    v2.case_id = '$case_id' and
                    v2.type = '$type' and
                    v1.chromosome=v2.chromosome and
                    v1.start_pos=v2.start_pos and
                    v1.end_pos=v2.end_pos and
                    v1.ref=v2.ref and
                    v1.alt=v2.alt");
        $cols = array();
        $anno = array();
        foreach ($rows as $row) {
            $key = $row->chromosome.$row->start_pos.$row->end_pos.$row->ref.$row->alt;
            $anno_cols[$row->attr_name] = '';
            $anno[$key][$row->attr_name] = $row->attr_value;
        }

        $anno_col_rows = VarAnnotationCol::all();
        
        $exp_type = "";
        if ($sample_id != "null") {
            $sample = Sample::where('sample_id', '=', $sample_id)->get()[0];
            $exp_type = $sample->exp_type;
        }

        $header = "Chr\tStart\tEnd\tRef\tAlt";
        foreach ($anno_col_rows as $anno_col_row) {
            $cols[] = $anno_col_row->column_name;
            $col_name = $anno_col_row->column_name;
            $header .= "\t".$col_name;
        }
        //read the hotspot genes
        list($hotspot_actionable_list, $hotspot_actionable_desc) = VarAnnotation::getHotspots(storage_path()."/data/".Config::get('onco.hotspot.actionable'));
        list($hotspot_predicted_list, $hotspot_predicted_desc) = VarAnnotation::getHotspots(storage_path()."/data/".Config::get('onco.hotspot.predicted'));

        $join_type = ($flag)? "inner" : "left";
        $sql = "select distinct v1.*,v2.sample_id,v2.exp_type, v2.tissue_cat,v2.exp_type,v2.caller,v2.qual,v2.fisher_score,v2.total_cov,v2.var_cov,v2.vaf_ratio,v2.relation from var_annotation v1 $join_type join var_flag f on 
                        f.patient_id = '$patient_id' and
                        v1.chromosome=f.chromosome and
                        v1.start_pos=f.start_pos and
                        v1.end_pos=f.end_pos and
                        v1.ref=f.ref and
                        v1.alt=f.alt
            , var_samples v2 where 
                    v2.patient_id = '$patient_id' and
                    v2.case_id = '$case_id' and
                    v2.type = '$type' and
                    v1.chromosome=v2.chromosome and
                    v1.start_pos=v2.start_pos and
                    v1.end_pos=v2.end_pos and
                    v1.ref=v2.ref and
                    v1.alt=v2.alt order by v1.chromosome, v1.start_pos";        
        $var_samples = DB::select($sql);        
        $results = $header."\tCanonical AAChange\tSample\tExpType\tSampleType\tCaller\tQUAL\tFS\tTotalReads\tAltReads\tVAF\tLevel_Somatic\tLevel_Germline";
        $results .= "\tGene\tProtein Change\tGenomic Location\tcDNA Change\tTranscript\tCoverage\tVAF (%)\n" ;
        //$results .= $sql;
        $user_filter_list = UserGeneList::getGeneList($type);
        $acmg_list_name = Config::get('onco.acmg_list_name');

        foreach ($var_samples as $var) {    
            if ($exp_type != "" && $var->exp_type != $exp_type)
                continue;
            $var_id = implode(":", [$patient_id, $case_id, $var->chromosome, $var->start_pos, $var->end_pos, $var->ref, $var->alt]);
            //$results .= $var_id."\n";
            if (!array_key_exists($var_id, $var_hash))
                continue;
            
            $var->{'hotspot_gene'} = '';
            $var->{'actionable_hotspots'} = '';
            $var->{'prediction_hotspots'} = '';
            $var->{'loss_func'} = '';
            if (isset($hotspot_actionable_list[$var->gene]))
                $var->hotspot_gene = 'Y';
            if (isset($hotspot_actionable_list[$var->gene][$var->chromosome][$var->start_pos][$var->end_pos]))
                $var->actionable_hotspots = 'Y';
            if (isset($hotspot_predicted_list[$var->gene][$var->chromosome][$var->start_pos][$var->end_pos]))                   
                $var->prediction_hotspots = 'Y';
            //user defined filters
            foreach ($user_filter_list as $list_name => $gene_list) {
                $has_gene = (array_key_exists($var->gene, $gene_list))? "Y":"";
                if (strtolower($list_name) == $acmg_list_name) {
                    $var->acmg = $has_gene;
                    continue;
                }
                $var->{strtolower($list_name)} = $has_gene;
            }

            foreach ($user_filter_list as $list_name => $gene_list) {
                $has_gene = (array_key_exists($var->gene, $gene_list))? "Y":"";
                $var->{strtolower($list_name)} = $has_gene;
            }

            $var->loss_func = VarAnnotation::isLOF($var->func, $var->exonicfunc);

            $line = $var->chromosome."\t".$var->start_pos."\t".$var->end_pos."\t".$var->ref."\t".$var->alt;
            $key = $var->chromosome.$var->start_pos.$var->end_pos.$var->ref.$var->alt;

            $vaf = ($var->total_cov == 0)? 0 : $var->var_cov/$var->total_cov;

            //$var->{'vaf'} = $var->normal_vaf;
            $var->{'vaf'} = $vaf;

            $somatic_level = ($type != "germline")?  VarAnnotation::getLevel('somatic', $var, $type) : '';
            $germline_level = ($type != "somatic")? VarAnnotation::getLevel('germline', $var, $type) : '';

            $aachage_str = "";
            $gene_detail = "";
            foreach ($cols as $col) {
                $value = isset($anno[$key][$col])? $anno[$key][$col] : "";
                $line .= "\t$value";
                if (strtolower($col) == "aachange")
                    $aachage_str = $value;
                if (strtolower($col) == "genedetail")
                    $gene_detail = $value;
            } 

            $aachanges = explode(",", $aachage_str);
            $canonical_aachange = "";
            foreach ($aachanges as $aachange) {
                $aainfos = explode(":", $aachange);
                if (isset($aainfos[1]) && $aainfos[1] == $var->transcript) {
                    $canonical_aachange = $aachange;
                    break;
                }
            }
            if ($canonical_aachange == "")
                $canonical_aachange = $gene_detail;

            $canonical_fields = explode(":", $canonical_aachange);
            $additional_fields = "\t\t\t\t\t$var->total_cov\t".round($vaf * 100, 0)."%";
            if (count($canonical_fields) == 5) {
                $additional_fields = "$var->gene\t$canonical_fields[4]\t".$var->chromosome.":".$var->start_pos."\t$canonical_fields[3]\t$canonical_fields[1]\t$var->total_cov\t".round($vaf * 100, 0)."%";
            }
            if (count($canonical_fields) == 3) {
                $additional_fields = "$var->gene\t\t".$var->chromosome.":".$var->start_pos."\t$canonical_fields[2]\t$canonical_fields[0]\t$var->total_cov\t".round($vaf * 100, 0)."%";
            }
            

            //for Manoj's report
            $line .= "\t$canonical_aachange\t".$var->sample_id."\t".$var->exp_type."\t".$var->tissue_cat."\t".$var->caller."\t".$var->qual."\t".$var->fisher_score."\t".$var->total_cov."\t".$var->var_cov."\t".$vaf."\t$somatic_level\t$germline_level\t$additional_fields";
            $results .= "$line\n";
        }

        return $results;

    }	
}
