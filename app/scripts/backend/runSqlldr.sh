#!/bin/bash
user=$1
data_file=$2
ctrl_file=$3
bad_file=$4
log_file=$5



export PATH=${PATH}:/opt/nasapps/production/oracle/product/11.2.0/client/bin
export LD_LIBRARY_PATH=/opt/nasapps/production/oracle/product/11.2.0/client/lib
export ORACLE_HOME=/opt/nasapps/production/oracle/product/11.2.0/client

#source /etc/profile.d/modules.sh
#module load oracle
sqlldr userid=$1 data=$2 control=$3 bad=$4 log=$5
