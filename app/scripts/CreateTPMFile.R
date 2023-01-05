library(dplyr)
Args<-commandArgs()

inFile<-Args[6]
annotationRDS <- Args[7]
outDIR <- Args[8]
exp_sample_id <- Args[9]

#annotationRDS=("./TPM_upload/Annotation/annotation_ENSEMBL_gene_19.RDS")
#exp_sample_id="Sample012345"

#inFile="./TPM_upload/NCI0002/20160415/test_sample/TPM_ENS/tmm.gene.tsv"
#outDIR="./TPM_upload/NCI0002/20160415/test_sample/TPM_ENS/"

Annotation=readRDS(annotationRDS)
tmm<-read.table(inFile, header=TRUE, com='', sep="\t")
colnames(tmm)=c("GeneID","GeneID.1","Gene",exp_sample_id)

TPM=merge(tmm,Annotation,by="GeneID")
TPM=TPM[c("Chr","Start","End","GeneName",exp_sample_id)]

write.table(TPM, file=paste(outDIR,exp_sample_id,".gene.TPM.txt",sep=""), sep='\t', col.names=TRUE,row.names =FALSE,quote=FALSE)