#!/bin/bash
target_project=$1
target_type=$2
target_db=$3
EXPECTED_ARGS=3
E_BADARGS=65
export PATH=/mnt/nasapps/development/perl/5.28.1/bin:$PATH
if [ $# -ne $EXPECTED_ARGS ]
then
	echo "Usage: `basename $0` {target project} {process type: db/tier/bam} {prod/dev/pub/all}"
	exit $E_BADARGS
fi

export ADMIN_ADDY='chouh@nih.gov';

script_file=`realpath $0`
script_home=`dirname $script_file`
html_home=`realpath ${script_home}/../../../..`
script_home_dev=${html_home}/clinomics_dev/app/scripts/backend
batch_home=`realpath ${script_home}/../../../batch_home`
echo "batch home = $batch_home"
data_home=${script_home}/../../storage/ProcessedResults
update_list_home=${script_home}/../../storage/update_list
log_home=${script_home}/../../storage/logs
bam_home=${script_home}/../../storage/bams
script_lib_home=`realpath ${script_home}/../lib`
url=`php ${script_lib_home}/getSiteConfig.php url`
url_dev=`php ${script_lib_home}/getSiteConfig.php url_dev`

#projects=( "clinomics":"tgen:/projects/Clinomics/ProcessedResults/" "processed_DATA":"biowulf2.nih.gov:/data/khanlab/projects/processed_DATA/" "cmpc":"biowulf2.nih.gov:/data/Clinomics/Analysis/CMPC/" "nbl":"biowulf2.nih.gov:/data/khanlab/projects/NBL/" "guha":"biowulf2.nih.gov:/data/GuhaData/" "alex":"biowulf2.nih.gov:/data/AlexanderP3/Alex/" "collobaration":"biowulf2.nih.gov:/data/khanlab2/collobaration_DATA/" "toronto":"biowulf2.nih.gov:/data/AlexanderP3/batch_11/")
#projects=( "clinomics":"tgen:/projects/Clinomics/ProcessedResults/" )
#projects=( "cmpc":"biowulf2:/data/Clinomics/Analysis/CMPC/" )
db_name='production'
db_name_dev='development'
db_name_pub='public'
url='https://fsabcl-onc01d.ncifcrf.gov/clinomics/public'
url_dev='https://fsabcl-onc01d.ncifcrf.gov/clinomics_dev/public'
url_pub='https://fsabcl-onc01d.ncifcrf.gov/clinomics_public/public'


project_file=$update_list_home/project_mapping.txt
#project_file=$script_home_dev/project_mapping_wu.txt
echo "project_file = $project_file";
while IFS=$'\t' read -r -a cols
do
	project=${cols[0]}
	succ_list_path=${cols[1]}
	source_path=${cols[2]}
	project_desc=${cols[3]}
	prefix=${project}_${target_type}_`date +"%Y%m%d-%H%M%S"`
	case_log=${prefix}_case.log
	echo "working on $project ...."
	if [ "$target_project" == "$project" ] || [ "$target_project" == "all" ]
	then

		project_home=${data_home}/${project}
		project_bam_home=${bam_home}/${project}
		log_file=${update_list_home}/log/${prefix}.log
		log_dev_file=${update_list_home}/log/${prefix}.dev.log
		update_list=""
		sync_list=""
		#if type is db, then sync update list from biowulf
		if [ "$target_type" == "db" ];then
			update_list=`realpath ${update_list_home}/new_list/${prefix}_caselist.txt`
			sync_list=`realpath ${update_list_home}/new_list/${prefix}_sync.txt`
			

			if [ ! -d ${project_home} ]; then
				mkdir ${project_home}
			fi
			date >> ${log_file}
			echo "[ Processing project: $project ]" >> ${log_file}
			echo "update_list=$update_list,sync_list=$sync_list, log_file=$log_file, project_home=$project_home"  >> ${log_file}
			echo "rsync ${succ_list_path} ${update_list_home}/new_list" >> ${log_file}
			rsync ${succ_list_path} ${update_list_home}/new_list 2>&1

			if [ -f ${update_list_home}/new_list/new_list_${project}.txt ];then
				awk -F" " '{print $1}' ${update_list_home}/new_list/new_list_${project}.txt > ${sync_list}
				rm ${update_list_home}/new_list/new_list_${project}.txt
			fi
			echo -n "" > ${update_list}
		else
			#if type is tier or bam, then use the last update/sync list
			#echo "looking for ${update_list_home}/${project}_db_*_caselist.txt"
			if ls  ${update_list_home}/new_list/${project}_db_*_caselist.txt 1> /dev/null 2>&1;then
				update_list=`ls -tr ${update_list_home}/new_list/${project}_db_*_caselist.txt | tail -n1`
				update_list=`realpath $update_list`
			fi			
			sync_list=`ls -tr ${update_list_home}/${project}_db_*_sync.txt | tail -n1`
			sync_list=`realpath $sync_list`
			echo "sync_list: $sync_list"
		fi

		while IFS='' read -r line || [[ -n "$line" ]]
		do
				pat_id=`echo "$line" | awk -F/ '{print $(NF-2)}'`
				case_id=`echo "$line" | awk -F/ '{print $(NF-1)}'`
				status=`echo "$line" | awk -F/ '{print $(NF)}'`

				folder=${pat_id}/${case_id}
				echo "$pat_id $case_id $status"
				if [[ $status == "successful.txt" ]];then
					
					mkdir -p ${project_home}/${pat_id}
					#sync data file
					if [ "$target_type" == "db" ];then
						echo ${pat_id}/${case_id}/${status} >> ${update_list}
						echo "deleteing old case..."
						perl ${script_home}/deleteCase.pl -p ${pat_id} -c ${case_id} -t ${project} -r
						echo "syncing ${source_path}${folder} ${project_home}/${pat_id}"
						rsync -tirm --include '*/' --include "*.txt" --exclude "fusions.discarded.tsv" --include '*.SJ.out.tab' --include '*.SJ.out.bed.gz' --include '*.SJ.out.bed.gz.tbi' --include '*.star.final.bam.tdf' --include '*.tsv'  --include '*.vcf' --include "*.png" --include '*.pdf' --include "*.gt" --include "*.bwa.loh" --include "*hotspot.depth" --include "*.tmb" --include "*.status" --include "*selfSM" --include 'db/*' --include "*tracking" --include "qc/rnaseqc/*" --include "RSEM*/*" --include 'HLA/*' --include 'NeoAntigen/*' --include 'HLA/*' --include 'MHC_Class_I/*' --include 'sequenza/*' --include 'cnvkit/*' --include 'cnvTSO/*' --include '*fastqc/*' --exclude "TPM_*/" --exclude "log/" --exclude "igv/" --exclude "topha*/" --exclude "fusion/*" --exclude "calls/" --exclude '*' ${source_path}${folder} ${project_home}/${pat_id} 2>&1
						chmod -R g+w ${project_home}/${pat_id}/${case_id}
					fi
					if [ "$target_type" == "bam" ];then
						if [[ $project == "compass_tso500" ]];then
							rsync -tirm -L --size-only --remove-source-files --exclude '*/*/*/*/' --include '*/' --include '*.bam*' --exclude '*' ${source_path}${folder} ${project_bam_home}/${pat_id} >>${log_file} 2>&1
						else
							#echo "rsync -tirm -L --size-only --remove-source-files --exclude '*/*/*/*/' --include '*/' --include '*bwa.final.squeeze.bam*' --include '*star.final.squeeze.bam*' --exclude '*' ${source_path}${folder} ${project_home}/${pat_id} >>${log_file} 2>&1"
							rsync -tirm -L --size-only --remove-source-files --exclude '*/*/*/*/' --include '*/' --include '*bwa.final.squeeze.bam*' --include '*star.final.squeeze.bam*' --include '*star.fusions.bam*' --exclude '*' ${source_path}${folder} ${project_bam_home}/${pat_id} >>${log_file} 2>&1
						fi
					fi				
				fi
				if [[ $status == "failed_delete.txt" ]];then
					if [ "$target_type" == "db" ];then
						echo ${pat_id}/${case_id}/${status} >> ${update_list}					
						#helix_path=`echo $source_path | sed 's/helix\.nih.gov://'`
						#echo "ssh helix rmdir ${helix_path}/${folder}"
						#ssh -q helix.nih.gov "rm ${helix_path}/${folder}/failed_delete.txt;rmdir ${helix_path}/${folder}"
					fi
				fi	

		done < $sync_list
		rm $sync_list
		echo "done syncing writing to log file ${log_file}"
		date >> ${log_file}
		echo "update list file: ${update_list}"
		if [[ -s ${update_list} && "$target_type" != "bam" ]]; then
			echo "uploading" >> ${log_file}

			if [ "$target_db" == "all" ] || [ "$target_db" == "prod" ]
			then
					if [ "$target_type" == "db" ] 
					then
						emails="hsien-chao.chou@nih.gov,khanjav@mail.nih.gov"
						if [ "$project" == "compass_tso500" ] || [ "$project" == "compass_exome" ]
						then
							emails="hsien-chao.chou@nih.gov,khanjav@mail.nih.gov,manoj.tyagi@nih.gov"
						fi
						echo "${script_home}/loadVarPatients.pl -i ${project_home} -o $project_desc $folder-l ${update_list} -d ${db_name} -u ${url}" >> ${log_file}
						LC_ALL="en_US.utf8" perl ${script_home}/loadVarPatients.pl -i ${project_home} -o $project_desc -l ${update_list} -d ${db_name} -u ${url} 2>&1 1>>${log_file}
						if [ "$project" != "compass_tso500" ]
						then
							LC_ALL="en_US.utf8" perl ${script_home}/updateVarCases.pl
							#submit this to batch server
							if [ -s ${update_list} ];then
								sbatch -D ${batch_home}/app/scripts/slurm -o ${batch_home}/app/scripts/slurm/slurm_log/${prefix}.preprocessProject.o -e ${batch_home}/app/scripts/slurm/slurm_log/${prefix}.preprocessProject.e ${batch_home}/app/scripts/slurm/submitPreprocessProject.sh ${update_list} $emails https://oncogenomics.ccr.cancer.gov/production/public
							fi
							#LC_ALL="en_US.utf8" perl ${script_home}/../preprocessProjectMaster.pl -p ${update_list} -e $emails -u https://oncogenomics.ccr.cancer.gov/production/public
						fi
						#echo "${script_home}/updateVarCases.pl 2>&1 1>>${case_log}" >>${log_file}
						#LC_ALL="en_US.utf8" ${script_home}/updateVarCases.pl 2>&1 1>>${case_log}
					else
						LC_ALL="en_US.utf8" perl ${script_home}/loadVarPatients.pl -i ${project_home} -l ${update_list} -t $target_type -d ${db_name} -u ${url} 2>&1 1>>${log_file}						
						#LC_ALL="en_US.utf8" ${script_home}/updateVarCases.pl 2>&1 1>>${case_log}
					fi
					echo " done uploading" >> ${log_file}
					
			fi
			if [ "$target_db" == "all" ] || [ "$target_db" == "dev" ]
			then
					if [ "$target_type" == "db" ] 
					then
						echo "${script_home_dev}/loadVarPatients.pl -i ${project_home} -l ${update_list} -d ${db_name_dev} -u ${url_dev}" >>${log_file}
						LC_ALL="en_US.utf8" perl ${script_home_dev}/loadVarPatients.pl -i ${project_home} -l ${update_list} -d ${db_name_dev} -u ${url_dev} 2>&1 1>>${log_dev_file}
						#LC_ALL="en_US.utf8" perl ${script_home_dev}/../preprocessProjectMaster.pl -p ${update_list} -e chouh@nih.gov -u ${url_dev}
					else
						LC_ALL="en_US.utf8" perl ${script_home_dev}/loadVarPatients.pl -i ${project_home} -l ${update_list} -t $target_type -d ${db_name_dev} -u ${url_dev} 2>&1 1>>${log_dev_file}
						#LC_ALL="en_US.utf8" perl ${script_home}/../preprocessProject.pl -p ${update_list} -e chouh@nih.gov
						#LC_ALL="en_US.utf8" ${script_home_dev}/updateVarCases.pl 2>&1 1>>${case_log}		
					fi
					echo " done uploading" >> ${log_dev_file}
					#LC_ALL="en_US.utf8" ${script_home_dev}/refreshViews.pl -c -p -h 2>&1 1>>${case_log} &
			fi
			if [ "$target_db" == "pub" ]
			then
					if [ "$target_type" == "db" ];then
						echo "${script_home}/loadVarPatients.pl -i ${project_home} -o $project_desc -l ${update_list} -d ${db_name_pub} -u ${url}" >> ${log_file}
						LC_ALL="en_US.utf8" perl ${script_home}/loadVarPatients.pl -i ${project_home} -o $project_desc -l ${update_list} -d ${db_name_pub} -u ${url} -e chouh@nih.gov 2>&1 1>>${log_file}
						LC_ALL="en_US.utf8" perl ${script_home}/loadVarPatients.pl -i ${project_home} -o $project_desc -l ${update_list} -d ${db_name_pub} -u ${url} -t tier 2>&1 1>>${log_file}
						LC_ALL="en_US.utf8" perl ${script_home}/updateVarCases.pl
						if [ -s ${update_list} ];then
							echo "sbatch -D ${batch_home}/app/scripts -o ${batch_home}/app/storage/logs/slurm/${prefix}.preprocessProject.o -e ${batch_home}/app/storage/logs/slurm/${prefix}.preprocessProject.e ${batch_home}/app/scripts/submitPreprocessProject.sh ${update_list} hsien-chao.chou@nih.gov https://clinomics.ccr.cancer.gov/clinomics/public"
							sbatch -D ${batch_home}/app/scripts -o ${batch_home}/app/storage/logs/slurm/${prefix}.preprocessProject.o -e ${batch_home}/app/storage/logs/slurm/${prefix}.preprocessProject.e ${batch_home}/app/scripts/submitPreprocessProject.sh ${update_list} hsien-chao.chou@nih.gov https://clinomics.ccr.cancer.gov/clinomics/public
						fi
					fi
			fi
		else
			rm $update_list
			rm $log_file
		fi
			#chmod -f -R 775 ${project_home}
			
	fi
		
#	fi
done < $project_file
if [ "$target_type" == "db" ];then

	echo "refreshing views -c -p -h"
	echo "refreshing views on prod"
	LC_ALL="en_US.utf8" ${script_home}/refreshViews.pl -c -p -h
	LC_ALL="en_US.utf8" ${script_home}/updateVarCases.pl
fi
if [ "$target_type" == "bam" ];then
	LC_ALL="en_US.utf8" ${script_home}/checkProcessedResults.pl
fi
if [ "$target_type" == "tier" ];then
	echo "refreshing views -h"
	echo "refreshing cohort views on prod"
	LC_ALL="en_US.utf8" ${script_home}/refreshViews.pl -h
fi

#if [ "$target_type" == "variants" ] 
#then	
#	echo "refreshing dev??" >> ${log_file}
#	echo "${script_home_dev}/updateVarCases.pl"
#	LC_ALL="en_US.utf8" ${script_home_dev}/updateVarCases.pl 2>&1 
#	LC_ALL="en_US.utf8" ${script_home_dev}/refreshViews.pl -c -p -h 2>&1 
#fi

echo "Done syncing! at " `date`
#/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics_dev/app/scripts/backend/syncProcessedResults.sh $target_project $target_type $target_db


