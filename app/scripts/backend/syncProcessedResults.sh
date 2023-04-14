#!/bin/bash
target_project=$1
target_type=$2
target_db=$3
EXPECTED_ARGS=3
E_BADARGS=65
export PATH=/mnt/nasapps/development/perl/5.28.1/bin:$PATH
if [ $# -ne $EXPECTED_ARGS ]
then
	echo "Usage: `basename $0` {target project} {process type: db/tier/bam} {production/development/public/all}"
	exit $E_BADARGS
fi

export ADMIN_ADDY='chouh@nih.gov';

script_file=`realpath $0`
script_home=`dirname $script_file`
html_home=`realpath ${script_home}/../../../..`
script_home_dev=${html_home}/clinomics_dev/app/scripts/backend
batch_home=`realpath ${script_home}/../../../storage/batch_home`
echo `date +"%Y-%m-%d %H:%M:%S"` "batch home = $batch_home"
data_home=${script_home}/../../../storage/ProcessedResults
sync_home=${script_home}/../../../storage/sync
log_home=${script_home}/../../../storage/logs
bam_home=${script_home}/../../../storage/bams
script_lib_home=`realpath ${script_home}/../lib`
db_name=$target_db
url=`grep '^URL=' ${script_home}/../../../.env | sed 's/URL=//'`


project_file=$sync_home/project_mapping.txt
echo `date +"%Y-%m-%d %H:%M:%S"` "project_file = $project_file";
while IFS=$'\t' read -r -a cols
do
	project=${cols[0]}
	succ_list_path=${cols[1]}
	source_path=${cols[2]}
	project_desc=${cols[3]}
	emails=${cols[4]}
	prefix=${project}_${target_type}_`date +"%Y%m%d-%H%M%S"`
	case_log=${prefix}_case.log
	echo `date +"%Y-%m-%d %H:%M:%S"` "working on $project ...."
	if [ "$target_project" == "$project" ] || [ "$target_project" == "all" ]
	then

		project_home=${data_home}/${project}
		project_bam_home=${bam_home}/${project}
		log_file=${sync_home}/logs/${prefix}.log
		update_list=""
		sync_list=""
		#if type is db, then sync update list from biowulf
		if [ "$target_type" == "db" ];then
			update_list=`realpath ${sync_home}/case_list/${prefix}_caselist.txt`
			sync_list=`realpath ${sync_home}/case_list/${prefix}_sync.txt`
			

			if [ ! -d ${project_home} ]; then
				mkdir ${project_home}
			fi
			date >> ${log_file}
			echo `date +"%Y-%m-%d %H:%M:%S"` "[ Processing project: $project ]" >> ${log_file}
			echo `date +"%Y-%m-%d %H:%M:%S"` "update_list=$update_list,sync_list=$sync_list, log_file=$log_file, project_home=$project_home"  >> ${log_file}
			echo `date +"%Y-%m-%d %H:%M:%S"` "rsync ${succ_list_path} ${update_list_home}/case_list" >> ${log_file}
			rsync -e 'ssh -q' ${succ_list_path} ${sync_home}/case_list 2>&1

			if [ -f ${sync_home}/case_list/new_list_${project}.txt ];then
				awk -F" " '{print $1}' ${sync_home}/case_list/new_list_${project}.txt > ${sync_list}
				rm ${sync_home}/case_list/new_list_${project}.txt
			fi
			echo -n "" > ${update_list}
			while IFS='' read -r line || [[ -n "$line" ]]
			do
				pat_id=`echo "$line" | awk -F/ '{print $(NF-2)}'`
				case_id=`echo "$line" | awk -F/ '{print $(NF-1)}'`
				status=`echo "$line" | awk -F/ '{print $(NF)}'`
				echo ${pat_id}/${case_id}/${status} >> ${update_list}
			done < $sync_list
			rm $sync_list

		else
			#if type is tier or bam, then use the last update/sync list			
			if ls  ${sync_home}/case_list/${project}_db_*_caselist.txt 1> /dev/null 2>&1;then
				update_list=`ls -tr ${sync_home}/case_list/${project}_db_*_caselist.txt | tail -n1`
				update_list=`realpath $update_list`
			fi			
		fi

		#only production sync processed data and bams
		if [ "$target_db" == "all" ] || [ "$target_db" == "production" ];then
			while IFS='' read -r line || [[ -n "$line" ]]
			do
					pat_id=`echo "$line" | awk -F/ '{print $(NF-2)}'`
					case_id=`echo "$line" | awk -F/ '{print $(NF-1)}'`
					status=`echo "$line" | awk -F/ '{print $(NF)}'`

					folder=${pat_id}/${case_id}
					echo `date +"%Y-%m-%d %H:%M:%S"` "$pat_id $case_id $status"
					if [[ $status == "successful.txt" ]];then
						
						mkdir -p ${project_home}/${pat_id}
						#sync data file
						if [ "$target_type" == "db" ];then
							echo ${pat_id}/${case_id}/${status} >> ${update_list}
							echo `date +"%Y-%m-%d %H:%M:%S"` "deleteing old case..."
							perl ${script_home}/deleteCase.pl -p ${pat_id} -c ${case_id} -t ${project} -r
							echo "syncing ${source_path}${folder} ${project_home}/${pat_id}"
							#rsync -tirm --include '*/' --include "*.txt" --exclude "fusions.discarded.tsv" --include '*.SJ.out.tab' --include '*.SJ.out.bed.gz' --include '*.SJ.out.bed.gz.tbi' --include '*.star.final.bam.tdf' --include '*.tsv'  --include '*.vcf' --include "*.png" --include '*.pdf' --include "*.gt" --include "*.bwa.loh" --include "*hotspot.depth" --include "*.tmb" --include "*.status" --include "*selfSM" --include 'db/*' --include "*tracking" --include "qc/rnaseqc/*" --include "RSEM*/*" --include 'HLA/*' --include 'NeoAntigen/*' --include 'HLA/*' --include 'MHC_Class_I/*' --include 'sequenza/*' --include 'cnvkit/*' --include 'cnvTSO/*' --include '*fastqc/*' --exclude "TPM_*/" --exclude "log/" --exclude "igv/" --exclude "topha*/" --exclude "fusion/*" --exclude "calls/" --exclude '*' ${source_path}${folder} ${project_home}/${pat_id} 2>&1
							rsync -e 'ssh -q' -tirm --include '*/' --include "*.txt" --include "*.html" --exclude "fusions.discarded.tsv" --include '*.SJ.out.tab' --include '*.SJ.out.bed.gz' --include '*.SJ.out.bed.gz.tbi' --include '*.star.final.bam.tdf' --include '*.tsv'  --include '*.vcf' --include "*.png" --include '*.pdf' --include "*.gt" --include "*.bwa.loh" --include "*hotspot.depth" --include "*.tmb" --include "*.status" --include "*selfSM" --include 'db/*' --include "*tracking" --include "*exonExpression*" --include "TPM_ENS/*" --include "qc/rnaseqc/*" --include "TPM_UCSC/*" --include "RSEM*/*" --include 'HLA/*' --include 'NeoAntigen/*' --include 'HLA/*' --include 'MHC_Class_I/*' --include 'sequenza/*' --include 'cnvkit/*' --include 'cnvTSO/*' --include '*fastqc/*' --include '*multiqc_data/*' --exclude "TPM_*/" --exclude "log/" --exclude "igv/" --exclude "topha*/" --exclude "fusion/*" --exclude "calls/" --exclude '*' ${source_path}${folder} ${project_home}/${pat_id} 2>&1
							chmod -R g+w ${project_home}/${pat_id}/${case_id}
						fi
						if [ "$target_type" == "bam" ];then
							if [[ $project == "compass_tso500" ]];then
								rsync -e 'ssh -q' -tirm -L --size-only --remove-source-files --exclude '*/*/*/*/' --include '*/' --include '*.bam*' --exclude '*' ${source_path}${folder} ${project_bam_home}/${pat_id} >>${log_file} 2>&1
							else
								#echo "rsync -tirm -L --size-only --remove-source-files --exclude '*/*/*/*/' --include '*/' --include '*bwa.final.squeeze.bam*' --include '*star.final.squeeze.bam*' --exclude '*' ${source_path}${folder} ${project_home}/${pat_id} >>${log_file} 2>&1"
								rsync -e 'ssh -q' -tirm -L --size-only --remove-source-files --exclude '*/*/*/*/' --include '*/' --include '*bwa.final.squeeze.bam*' --include '*star.final.squeeze.bam*' --include '*star.fusions.bam*' --exclude '*' ${source_path}${folder} ${project_bam_home}/${pat_id} >>${log_file} 2>&1
							fi
						fi				
					fi								
			done < $update_list
		fi
		
		echo `date +"%Y-%m-%d %H:%M:%S"` "done syncing writing to log file ${log_file}"
		echo `date +"%Y-%m-%d %H:%M:%S"` "update list file: ${update_list}"
		if [[ -s ${update_list} && "$target_type" != "bam" ]]; then
			echo `date +"%Y-%m-%d %H:%M:%S"` "uploading" >> ${log_file}

			if [ "$target_db" != "pub" ]
			then
					if [ "$target_type" == "db" ] 
					then
						echo `date +"%Y-%m-%d %H:%M:%S"` "${script_home}/uploadCase.pl -i ${project_home} -o $project_desc $folder-l ${update_list} -d ${db_name} -u ${url}" >> ${log_file}
						LC_ALL="en_US.utf8" perl ${script_home}/uploadCase.pl -i ${project_home} -o $project_desc -l ${update_list} -d ${db_name} -u ${url} 2>&1 1>>${log_file}
						if [ "$project" != "compass_tso500" ]
						then
							LC_ALL="en_US.utf8" perl ${script_home}/updateVarCases.pl
							#submit this to batch server
							if [ -s ${update_list} ];then
								sbatch -D ${batch_home}/app/scripts/slurm -o ${batch_home}/app/scripts/slurm/slurm_log/${prefix}.preprocessProject.o -e ${batch_home}/app/scripts/slurm/slurm_log/${prefix}.preprocessProject.e ${batch_home}/app/scripts/slurm/submitPreprocessProject.sh ${update_list} $emails $url
							fi							
						fi						
					else
						LC_ALL="en_US.utf8" perl ${script_home}/uploadCase.pl -i ${project_home} -l ${update_list} -t $target_type -d ${db_name} -u ${url} 2>&1 1>>${log_file}												
					fi
					echo `date +"%Y-%m-%d %H:%M:%S"` "done uploading" >> ${log_file}
			else
					if [ "$target_type" == "db" ];then
						echo `date +"%Y-%m-%d %H:%M:%S"` "${script_home}/uploadCase.pl -i ${project_home} -o $project_desc -l ${update_list} -d ${db_name_pub} -u ${url}" >> ${log_file}
						LC_ALL="en_US.utf8" perl ${script_home}/uploadCase.pl -i ${project_home} -o $project_desc -l ${update_list} -d ${db_name_pub} -u ${url} -e chouh@nih.gov 2>&1 1>>${log_file}
						LC_ALL="en_US.utf8" perl ${script_home}/uploadCase.pl -i ${project_home} -o $project_desc -l ${update_list} -d ${db_name_pub} -u ${url} -t tier 2>&1 1>>${log_file}
						LC_ALL="en_US.utf8" perl ${script_home}/updateVarCases.pl
						if [ -s ${update_list} ];then
							echo `date +"%Y-%m-%d %H:%M:%S"` "sbatch -D ${batch_home}/app/scripts -o ${batch_home}/storage/logs/slurm/${prefix}.preprocessProject.o -e ${batch_home}/storage/logs/slurm/${prefix}.preprocessProject.e ${batch_home}/app/scripts/submitPreprocessProject.sh ${update_list} $emails $url"
							sbatch -D ${batch_home}/app/scripts/slurm -o ${batch_home}/app/scripts/slurm/slurm_log/${prefix}.preprocessProject.o -e ${batch_home}/app/scripts/slurm/slurm_log/${prefix}.preprocessProject.e ${batch_home}/app/scripts/slurm/submitPreprocessProject.sh ${update_list} $emails $url
						fi
					fi
			fi		
		fi
		find $sync_home -size 0c -delete
		#chmod -f -R 775 ${project_home}
			
	fi
		
#	fi
done < $project_file
if [ "$target_type" == "db" ];then
	echo `date +"%Y-%m-%d %H:%M:%S"` "refreshing views -c -p -h"
	LC_ALL="en_US.utf8" ${script_home}/refreshViews.pl -c -p -h
	LC_ALL="en_US.utf8" ${script_home}/updateVarCases.pl
fi
if [ "$target_type" == "bam" ];then
	LC_ALL="en_US.utf8" ${script_home}/checkProcessedResults.pl
fi
if [ "$target_type" == "tier" ];then
	echo `date +"%Y-%m-%d %H:%M:%S"` "refreshing views -h"
	LC_ALL="en_US.utf8" ${script_home}/refreshViews.pl -h
fi

echo `date +"%Y-%m-%d %H:%M:%S"` "Done syncing! at " `date`



