module load bedtools
cd $PBS_O_WORKDIR
bedtools intersect -a $a -b $b -wa > $o
