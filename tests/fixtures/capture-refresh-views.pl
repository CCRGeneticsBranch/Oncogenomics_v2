#!/usr/bin/env perl
use strict;
use warnings;
use DBI;
use JSON::PP;

my ($db_type, $script, $original_script) = @ARGV;
die "Expected database type and refresh script paths\n" unless $db_type =~ /^(mysql|oracle)$/ && $script && $original_script;
my @statements;
{
    no warnings 'redefine';
    # Never connect to a database: capture SQL emitted by the actual refresh script.
    *DBI::connect = sub { return bless {}, 'CaptureRefreshDB'; };
}
{
    package CaptureRefreshDB;
    sub do { push @statements, $_[1]; return 1; }
    sub commit { return 1; }
    sub disconnect { return 1; }
}
@ENV{qw(DB_CONNECTION DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD DB_PORT)} =
    ($db_type, 'test.invalid', 'test', 'test', 'test', '1234');
my $log = '';
{
    open my $output, '>', \$log or die $!;
    local *STDOUT = $output;
    # Keep Onco.pm resolution anchored to the real script when testing a staged copy.
    local $0 = $original_script;
    local @ARGV = ('-h');
    my $result = do $script;
    die $@ if $@;
    die "Could not run refresh script: $!\n" unless defined $result;
}
print encode_json(\@statements);
