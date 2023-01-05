<?php

use Illuminate\Auth\UserTrait;
use Illuminate\Auth\UserInterface;
use Illuminate\Auth\Reminders\RemindableTrait;
use Illuminate\Auth\Reminders\RemindableInterface;
use Illuminate\Database\Eloquent\Model;

class SampleAnnotation extends Model {
   protected $table       = 'sample_annotation';
   protected $primary_key = NULL;
   public    $timestamps  = FALSE;
  
   public function sample_annotation_biomaterial() {
      return $this->belongsToMany('App\Sample_Annotation_Biomaterial');
   } 
   
   static public function JSamples () {
      #===get data from model Sample_Annotation
      $smpls    = SampleAnnotation::all();
      $samples  = $smpls->toArray();

      #===generate the data in format for samples 
      $pps_vtree= "\'1430768727551\',\'width=1000,height=700,toolbar=0,menubar=0,location=0,status=1,scrollbars=1,resizable=1,left=0,top=0\'";
      $jsamples ='[';

      foreach($samples as $sample){
         $jsamples .='[';
         foreach($sample as $key=>$vv) {
            $value=$vv;
            if($vv==null or $vv=="-" or $vv=="---") { 
               $value='';
            }
            if($key=='biomaterial_id') {
               $bid = $value;
            }
            elseif($key=='biomaterial_name' or $key=='genotyping' or $key=='hiseq' or $key=='solid' or $key=='person'){
               $url = url("viewtip/".$key.'='.rawurlencode($value.'|'.$bid));
               $jsamples .= '["<div class=\"mytooltip\" onclick=\"tooltip.pop(this, \' <iframe class=biomframe src='.$url.'></iframe>\', {smartPosition: true})\">'.$value.'</div>"],';
            }
            elseif($key=='diagnosis') {
               $url = url("viewtree/".$key."=".urlencode($value));
               $jsamples .= '["<a onclick=\"javascript:void window.open(\''.$url.'\','.$pps_vtree.' ); return false;\" >'.$value.'</a>"],';
            }
            elseif($key!='subject_id' and $key!='person_id' and $key!='type_sequencing' and $key!='type_seq' and $key!='run_name' and $key!='file_name') {
               $jsamples .= '["'.$value.'"],';
            }
         }
         $jsamples .='],';
      }
      $jsamples .=']';
      return $jsamples;
   }

   static public function JColumns () {
      #===get data from model Sample_Annotation
      $smpls    = SampleAnnotation::all();
      $samples  = $smpls->toArray();
      #$samples    = DB::select('select a.biomaterial_id, a.biomaterial_name, a.person from sample_annotation a join sample_annotation_biomaterial b on a.biomaterial_id=b.biomaterial_id');

      #===generate the data in format for column names 
      $jcolnames ='[';
      foreach($samples[0] as $key=>$value) {
         if($key=='biomaterial_id') {
            $biomaterial_id = $key;
         }
         elseif($key!='subject_id' and $key!='person_id' and $key!='type_sequencing' and $key!='type_seq' and $key!='run_name' and $key!='file_name'){
            if($key=="diagnosis") {  
               $key=$key."(tree)";
            }
            $jcolnames .= '{"title":"'.$key.'"},';
         }
      }
      $jcolnames .=']';
      return $jcolnames;
   }
}
