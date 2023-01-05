use DBI;
use Getopt::Long qw(GetOptions);


sub getDBI {
  my ($host, $sid, $username, $passwd, $port) = getDBConfig();  
  my $dbh = DBI->connect( "dbi:Oracle:host=$host;port=$port;sid=$sid", $username, $passwd, {
      AutoCommit => 0,
      RaiseError => 1,    
  }) || die( $DBI::errstr . "\n" );
  return $dbh;
}

sub getDBHost {
  my ($host, $sid, $username, $passwd, $port) = getDBConfig();
  return $host;
}

sub getDBSID {
  my ($host, $sid, $username, $passwd, $port) = getDBConfig();
  return $sid;
}


sub getConfig {
  my ($key) = @_;
  my $script_dir = dirname(__FILE__);
  return _getConfig("$script_dir/../../config/site.php",$key);  
}

sub formatDir {
    my ($dir) = @_;
    if ($dir !~ /\/$/) {
        $dir = $dir."/";
    }
    return $dir;
}

sub getDBConfig {
  my $script_dir = dirname(__FILE__);
  my $file = "$script_dir/../../config/database.php";
  my $default = _getConfig($file, "default");
  open(FILE, "$file") or die "Cannot open file $file";
  my $found = 0;
  my $host = "";
  my $sid = "";
  my $username = "";
  my $passwd = "";
  my $port = "";
  while (<FILE>) {
    chomp;
    if (/(.*)\=\>.*array\($/) {
      my $key = $1;
      $key =~ s/[\s\'\"]//g;
      if ($key eq $default) {
        $found = 1;        
      }
    } 
    if ($found) {   
      if (/(.*)\=\>(.*)$/) {
        my $key = $1;
        my $value = $2;
        $key =~ s/[\s\t\'\",]//g;
        $value =~ s/[\s\t\'\",]//g;        
        if ($key eq "host") {
          $host = $value;
        }
        if ($key eq "database") {
          $sid = $value;
        }
        if ($key eq "username") {
          $username = $value;
        }
        if ($key eq "password") {
          $passwd = $value;
        }
        if ($key eq "port") {
          $port = $value;
        }
        if ($host ne "" && $sid ne "" && $username ne "" && $passwd ne "" && $port ne "") {
          return ($host, $sid, $username, $passwd, $port);
        }
      }
    }
  }
  close(FILE);
  return ();
}

sub _getConfig {
  my ($file, $query) = @_;
  open(FILE, "$file") or die "Cannot open file $file";
  while (<FILE>) {
    chomp;
    if (/(.*)\=\>(.*),$/) {
      my $key = $1;
      my $value = $2;
      $key =~ s/[\s\'\"]//g;
      $value =~ s/[\s\'\"]//g;
      #print("$key => $value\n");
      if ($key eq $query) {
        close(FILE);
        return $value;
      }
    }
  }
  close(FILE);
  return "";
}
1;