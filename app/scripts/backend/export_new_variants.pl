#!/usr/bin/env perl
use strict;
use warnings;
use File::Basename;
use Cwd 'abs_path';
use Time::Piece;
require(dirname(abs_path($0))."/../lib/Onco.pm");

my $table_name="hg19_annot_oc";
my $out_file;

my $usage = <<__EOUSAGE__;

Usage:

$0 [options]

Options:

  -t            Table name (default: $table_name)
  -o            Output file
  
__EOUSAGE__



GetOptions (
	't=s' => \$table_name,
  'o=s' => \$out_file
);

if (!$out_file) {
    die "Please specifiy options!\n$usage";
}

my $dbh = getDBI();
my $sid = getDBSID();
my $host = getDBHost();

print_log("Exporting to $out_file on $host ($sid)");
my $sql = "select distinct chromosome,start_pos,end_pos,ref,alt from var_samples v where not exists(select * from $table_name a where v.chromosome=a.chr and v.start_pos=a.query_start and v.end_pos=a.query_end and v.ref=a.allele1 and v.alt=a.allele2) order by chromosome,start_pos,end_pos";
my $sth_novel = $dbh->prepare($sql);
$sth_novel->execute();
open(OUT_FILE, ">$out_file") or die "Cannot open file $out_file";
while (my @row = $sth_novel->fetchrow_array) {
	print OUT_FILE join("\t",@row)."\n";

}
close(OUT_FILE);
$dbh->disconnect();
print_log("Done");