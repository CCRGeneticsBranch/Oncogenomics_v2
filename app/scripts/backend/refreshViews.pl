#!/usr/bin/env perl
use strict;
use warnings;
use File::Basename;
use Cwd 'abs_path';
require(dirname(abs_path($0))."/../lib/Onco.pm");

my $refresh_all = 0;
my $do_cnv = 0;
my $do_prj_summary = 0;
my $do_avia = 0;
my $do_cohort = 0;

my $usage = <<__EOUSAGE__;

Usage:

$0 [options]

Options:

  -a            Refresh all
  -c            Refresh CNV views
  -p            Refresh Project views
  -v            Refresh AVIA views
  -h            Refresh Cohort views
  
__EOUSAGE__



GetOptions (
  'a' => \$refresh_all,
  'c' => \$do_cnv,
  'p' => \$do_prj_summary,
  'v' => \$do_avia,
  'h' => \$do_cohort
);

if (!$refresh_all && !$do_cnv && !$do_prj_summary && !$do_avia && !$do_cohort) {
    die "Please specifiy options!\n$usage";
}

my $dbh = getDBI();
my $sid = getDBSID();
my $host = getDBHost();

if ($refresh_all || $do_prj_summary) {
	print_log("Refrshing project views...on $sid");
	$dbh->do("BEGIN Dbms_Mview.Refresh('PROJECT_PATIENTS','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('PROJECT_SAMPLES','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('CASES','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_CASES','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('SAMPLE_CASES','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('PROCESSED_SAMPLE_CASES','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('PROJECT_CASES','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('PROJECT_PROCESSED_CASES','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('PROJECT_CASES','C');END;");	
	$dbh->do("BEGIN Dbms_Mview.Refresh('PROJECT_PATIENT_SUMMARY','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('PROJECT_SAMPLE_SUMMARY','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('USER_PROJECTS','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('FUSION_COUNT','C');END;");	
	$dbh->do("BEGIN Dbms_Mview.Refresh('PROJECT_MVIEW','C');END;");	
	#$dbh->do("BEGIN Dbms_Mview.Refresh('PROJECT_SAMPLES','C');END;");	
	#$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_SAMPLE_KHANLAB','C');END;");
#	$dbh->do("truncate table cache");
}

if ($refresh_all || $do_avia) {
	print_log("Refrshing AVIA view...");
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_SAMPLE_AVIA','C', ATOMIC_REFRESH => FALSE);END;");
}

if ($refresh_all || $do_cnv) {
	print_log("Refrshing CNV views...on $sid");
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_CNV_GENES','C',ATOMIC_REFRESH => FALSE);END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_CNVKIT_GENES','C',ATOMIC_REFRESH => FALSE);END;");
}

if ($refresh_all || $do_cohort) {
	print_log("Refrshing cohort views...on $sid");
	print_log("VAR_AA_COHORT");
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_AA_COHORT','C',ATOMIC_REFRESH => FALSE);END;");
	print_log("VAR_GENES");
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_GENES','C',ATOMIC_REFRESH => FALSE);END;");
	print_log("VAR_COUNT");
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_COUNT','C',ATOMIC_REFRESH => FALSE);END;");
	print_log("FUSION_COUNT");	
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_DIAGNOSIS_AA_COHORT','C',ATOMIC_REFRESH => FALSE);END;");
	print_log("VAR_DIAGNOSIS_GENE_COHORT");
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_DIAGNOSIS_GENE_COHORT','C',ATOMIC_REFRESH => FALSE);END;");
	print_log("VAR_GENE_COHORT");
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_GENE_COHORT','C',ATOMIC_REFRESH => FALSE);END;");
	print_log("VAR_GENE_TIER");
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_GENE_TIER','C',ATOMIC_REFRESH => FALSE);END;");	
	print_log("PROJECT_DIAGNOSIS_GENE_TIER");
	$dbh->do("BEGIN Dbms_Mview.Refresh('PROJECT_DIAGNOSIS_GENE_TIER','C',ATOMIC_REFRESH => FALSE);END;");
	print_log("PROJECT_GENE_TIER");
	$dbh->do("BEGIN Dbms_Mview.Refresh('PROJECT_GENE_TIER','C',ATOMIC_REFRESH => FALSE);END;");
	print_log("PROJECT_GENE_TIER");
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_TIER_AVIA_COUNT','C',ATOMIC_REFRESH => FALSE);END;");
	print_log("VAR_TOP20");
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_TOP20','C');END;");
	
}

#$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_PATIENT_ANNOTATION','C');END;");
$dbh->disconnect();
print_log("done updating on $host ($sid)");

sub print_log {
    my ($msg) = @_;
    #open CMD_FILE, ">>$cmd_log_file" || print_log("cannot create command log file";
    #print CMD_FILE "[".localtime->strftime('%Y-%m-%d %H:%M:%S')."] $msg");
    #close(CMD_FILE);
    $msg = "[".localtime->strftime('%Y-%m-%d %H:%M:%S')."] $msg\n";
	  print_log("$msg");
}
