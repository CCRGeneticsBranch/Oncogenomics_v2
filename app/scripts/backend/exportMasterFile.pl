#!/usr/bin/env perl

use strict;
use warnings;
use DBI;
use Getopt::Long qw(GetOptions);
use File::Basename;
use Cwd 'abs_path';
require(dirname(abs_path($0))."/../lib/Onco.pm");

my $script_dir = dirname(__FILE__);

my $out_file;
my $usage = <<__EOUSAGE__;

Usage:

$0 [options]

Options:

  -o            Output file
  
__EOUSAGE__



GetOptions (
  'o=s' => \$out_file
  
);

if (!$out_file) {
    die "Please input output file name\n$usage";
}


my $dbh = getDBI();

my $sth_samples = $dbh->prepare("select  diagnosis,alternate_id,sample_id,sample_name,run_id,s.patient_id,source_biomaterial_id,biomaterial_id,material_type,exp_type,library_type,tissue_cat,tissue_type,reference,normal_sample,rnaseq_sample from samples s,patients p where s.patient_id=p.patient_id");
my $sth_sample_cases = $dbh->prepare("select distinct sample_id,case_name from sample_cases");
my $sth_project_samples = $dbh->prepare("select distinct sample_id,name from project_samples");

$sth_sample_cases->execute();
my %sample_cases;
while (my ($sample_id, $case_name) = $sth_sample_cases->fetchrow_array) {
  if (exists($sample_cases{$sample_id})) {
    $sample_cases{$sample_id} = $sample_cases{$sample_id}.",".$case_name;
  } else {
    $sample_cases{$sample_id} = $case_name;
  }
}
$sth_sample_cases->finish;
my %project_samples;
$sth_project_samples->execute();
while (my ($sample_id, $project_name) = $sth_project_samples->fetchrow_array) {
  if (exists($project_samples{$sample_id})) {
    $project_samples{$sample_id} = $project_samples{$sample_id}.",".$project_name;
  } else {
    $project_samples{$sample_id} = $project_name;
  }
}
$sth_project_samples->finish;

open OUT_FILE, ">$out_file" or die "Cannot open $out_file";

my @headers = ( "Biomaterial ID","Source Biomaterial ID","Patient ID", "Type",  "Anatomy/Cell Type", "Diagnosis", "Project", "Type of sequencing",  "Enrichment step", "FCID",  "Library ID", "Matched normal", "Matched RNA-seq lib", "Case Name", "sc cite-seq feature ref", "ALTERNATE_ID",  "Original File Name", "SampleRef" );
print OUT_FILE join("\t", @headers)."\n";
$sth_samples->execute();
while (my ($diagnosis, $alternate_id, $sample_id,$sample_name,$run_id,$patient_id,$source_biomaterial_id,$biomaterial_id,$material_type,$exp_type,$library_type,$tissue_cat,$tissue_type,$reference,$normal_sample,$rnaseq_sample) = $sth_samples->fetchrow_array) {
  my $case_name = "";
  if (exists($sample_cases{$sample_id})) {
    $case_name = $sample_cases{$sample_id};
  }
  my $project_name = "";
  if (exists($project_samples{$sample_id})) {
    $project_name = $project_samples{$sample_id};
  }
  if ($tissue_cat ne "normal") {
    $tissue_type = "";
  }

  
  if ($exp_type eq "Whole Genome") {
    $exp_type = "WG-il";
  } 

  if ($exp_type eq "Panel") {
    $exp_type = "P-il";
  } 

  if ($exp_type eq "Exome") {
    $exp_type = "E-il";
  }

  if ($exp_type eq "Methylseq") {
    $exp_type = "M-il";
  }

  if ($exp_type eq "TCR") {
    $exp_type = "TCR";
  }

  if ($exp_type eq "RNAseq") {
    $exp_type = "T-il";
  }

  if ($exp_type eq "ChIPseq") {
    $exp_type = "C-il";
  }

  if ($exp_type eq "HiC") {
    $exp_type = "H-il";
  } 

  $library_type = "" if (!$library_type);
  $biomaterial_id = "" if (!$biomaterial_id);
  $source_biomaterial_id = "" if (!$source_biomaterial_id);
  $normal_sample = "" if (!$normal_sample);
  $rnaseq_sample = "" if (!$rnaseq_sample);
  $run_id = "" if (!$run_id);
  $alternate_id = "" if (!$alternate_id);
  $reference = "" if (!$reference);

  print OUT_FILE "$biomaterial_id\t$source_biomaterial_id\t$patient_id\t$tissue_cat $material_type\t$tissue_type\t$diagnosis\t$project_name\t$exp_type\t$library_type\t$run_id\t$sample_name\t$normal_sample\t$rnaseq_sample\t$case_name\t\t$alternate_id\t\t$reference\n"
}
$sth_samples->finish;


$dbh->disconnect();


