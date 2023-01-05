#!/usr/bin/perl
use strict;
use warnings;

open(ANN_FH, "$ARGV[0]");
my %ANNOVAR;
my $cols=0;
while(<ANN_FH>){
        chomp;
	$ANNOVAR{"$_"} = "$ARGV[1]";
}
close ANN_FH;

open (ORI,"$ARGV[2]");
while (<ORI>){
        chomp;
	my @a = split("\t", $_);
	my $key = "$a[0]";
	if(exists $ANNOVAR{$key}){
		my $sum =$a[2] +1;
		print "$a[0]\t$a[1];$ANNOVAR{$key}\t$sum\n";
		delete $ANNOVAR{$key};
	}
	else{
		print "$_\n";
	}
}
close ORI;
for (keys %ANNOVAR){
	print "$_\t$ARGV[1]\t1\n";
}
