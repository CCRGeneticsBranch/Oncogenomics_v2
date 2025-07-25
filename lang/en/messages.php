<?php

return array(

	/*
	|--------------------------------------------------------------------------
	| Oncogenomic message file
	|--------------------------------------------------------------------------
	|
	| All message and labels should be defined here
	|
	*/

	"id" => "ID",
	"user_id" => "User ID",
	"study_type" => "Study Type",
	"study_name" => "Study Name",
	"study_desc" => "Description",
	"status" => "Status",
	"created_at" => "Create Time",
	"updated_at" => "Update Time",
	"edit" => "Edit",
	"delete" => "Delete",
	"group_num" => "Total Groups",
	"sample_num" => "Total Samples",
	"sample_id" => "Sample ID",
	"sample_name" => "Sample Name",
	"sample_alias" => "Library",
	"biomaterial_id" => "Biomaterial ID",
	"source_biomaterial_id" => "Source Biomaterial ID",
	"custom_id" => "Custom ID",
	"adj_germline_count" => "+/-5bp Germline",
	"adj_somatic_count" => "+/-5bp Somatic",
	"hotspot" => "Hotspot",
	"mrn" => "MRN",
	"project_name" => "Projects",
	"case_list" => "Cases",
	"case_id" => "Case ID",
	"protocol_no" => "Protocol NO",
	"material_type" => "DNA/RNA",
	"exp_type" => "Experiment Type",
	"platform" => "Platform",
	"library_type" => "Library Type",
	"tissue_cat" => "Tumor|Normal",
	"tissue_type" => "Tissue|Diagnosis",
	"created_at" => "Create Time",
	"updated_at" => "Update Time",
	"source" => "Source",
	"reference" => "Reference",
	"dataset" => "Data Set",
	"datapath" => "Data Path",
	"status" => "Status",	
	"view_igv" => "IGV",
	"cohort" => "Gene cohort",
	"view_igv.help" => "Launch IGV to view the reads coverage",
	"chromosome" => "Chr",
	"start_pos" => "Start",
	"end_pos" => "End",
	"actionable" => "CLIA Actionable",
	"reported_mutations" => "Reported",
	"reported_mutations.help" => "Reported variants from research reports",
	"str" => "STR",
	"hgmd" => "HGMD",
	"hgmd_cat" => "HGMD Disease",
	"acmg" => "ACMG V3",
	"gene" => "Gene",
	"in_germline_somatic" => "In Germline/Somatic",
	"hotspot_genes" => "Hotspot Genes",
	"actionable_hotspots" => "Hotspots",
	"prediction_hotspots" => "Prediction Hotspots",	
	"loss_func" => "Loss of Function",
	"hgmd.help" => "Human Gene Mutation Database",	
	"frequency" => "MAF",
	"frequency.help" => "Max public variant allele frequency",
	"genotyping" => "GenoTyping",
	"candl" => "CanDL",
	"civic" => "CIViC",
	"docm" => "DoCM",
	"mcg" => "My Cancer Genome",
	"pcg" => "Pediatric Cancer Genome",
	"icgc" => "ICGC",
	"oncokb" => "OncoKB",
	"tcga_counts" => "TCGA",
	"matchtrial" => "NCI-MATCH Trial",
	"clinvar_clisig" => "Clinvar Pathogenic", //this should be changed to clinvar_path
	"clinvar_clinsig" => "Variant Clinical Significance",
	"clndsdb" => "Variant disease database name",
	"clndsdbid" => "Variant disease database ID",
	"not" => "No assertion provided",
	"no_assertion" => "No assertion provided", 
	"no_criteria" => "No assertion criteria provided", 
	"single" => "Criteria provided single submitter", 
	"mult" => "Criteria provided multiple submitters no conflicts",
	"conf" => "Criteria provided conflicting interpretations", 
	"exp" => "Reviewed by expert panel", 
	"guideline" => "Practice guideline",
	"caller" => "Caller",
	"fisher_score" => "Fisher score",
	"diagnosis" => "Diagnosis",	
	"var_comment" => "Comment",
	"vaf" => "VAF",
	"total_cov" => "Total coverage",
	"var_cov" => "Variant coverage",
	"matched_var_cov" => "Matched variant coverage",
	"matched_total_cov" => "Matched total coverage",
	"normal_var_cov" => "Normal variant cov",
	"normal_total_cov" => "Normal total cov",
	"in_exome" => "In Exome",
	"germline_vaf" => "Germline VAF",
	"b" => "Benign",
	"p" => "Pathogenic",
	"pb" => "Probable benign",
	"pp" => "Probable pathogenic",
	"pmid" => "Pubmed ID",
	"pmids" => "Pubmed ID",
	"pubmed_id" => "Pubmed ID",
	"pubmedid" => "Pubmed ID",
	"clnrevstat" => "Clinvar Review Status",
	"clndbn" => "Disease Name",
	"clnsig" => "Clinical significance",
	"clnacc" => "Clinvar Accession",
	"spanreadcount" => "Span read count",	
	"u" => "Unknown",
	"url" => "URL",
	"ad" => "Autosomal dominant",
	"ar" => "Autosomal recessive",
	"tsg" => "Tumor surpress gene",
	"func" => "Region",
	"exonicfunc" => "Exonic function",
	"aachange" => "AAChange",
	"aapos" => "AA position",
	"targeted" => "Targeted Cancer Care",
	"targetedcancercare" => "Targeted Cancer Care",
	"ispublic" => "Public project",
	"rnaseq" => "RNAseq",
	"germline_level" => "Germline Tier",
	"somatic_level" => "Somatic Tier",
	"germline" => "Germline",
	"somatic" => "Somatic",
	"variants" => "Tumor",
	"cgi" => "CGI",
	"acmg_guide" => "ACMG Guide",
	"acmg_v1" => "ACMG V1",
	"patient_id" => "Patient ID",	
	"germline_message" => "Germline mutations",
	"somatic_message" => "Somatic mutations",
	"rnaseq_message" => "RNAseq mutations",
	"QCI_message" => "QCI annotation",
	"variants_message" => "Mutations from unpaired samples or patients' family",
	"circos" => "Circos",
	"coveragePlot" => "Coverage",
	"transcriptCoverage" => "Transcript Cov",
	"burden_per_mb" => "Burden Per MB",
	"fc" => "FC",
	"lp_exome_germlinepanel" => "LP_Exome_GermlinePanel",
	"lp_rna_exomefusionpanel" => "LP_RNA_ExomeFusionPanel",
	"qci_assessment" => "QCI Assessment",
	"qci_actionability" => "QCI Actionability",
	"qci_nooactionability" => "QCI Nof1 Actionability",
	"left_trans" => "Canonical left trans",
	"right_trans" => "Canonical right trans",
	"QCI" => "QCI",
	"TSO" => "TSO",
	"spliceai" => "SpliceAI"

);
