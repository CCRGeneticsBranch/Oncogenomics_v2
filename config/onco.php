<?php

return array(

	/*
	|--------------------------------------------------------------------------
	| Study visible column setting
	|--------------------------------------------------------------------------
	|
	| 0: invisible, 1: visible
	|
	*/
	'cache' => 0,
	'cache' => ['list' => 0, 'var' => 0, 'mins' => 60*24],
	'var' => ['use_table' => 1],
    //'avia_table' => 'avia.hg19_avia3@abcc_lnk',//PROD; database links have to be checked for each DB 
    //'avia_version' => 'avia.avia_db_ver@abcc_lnk',//PROD
    //'avia_table' => 'hg19_annot@pub_lnk',
	'labmatrix'=>'https://fsabcl-lbm01p.ncifcrf.gov/?project=oncogenomics',
	'project_column_exclude' => array('id', 'user_id'),
	'study_column_hide' => array(0,1,9),
	'study_column_exclude' => array('study_type_code','samples','analyses'),
	'study_column_filter' => array(1,5,6,7,8),
	'sample_column_exclude' => array('datapath', 'reference', "biomaterial_id", "tissue_type", "source", "dataset", "sample_capture", "alias", "relation","enhancepipe","peakcalling","sample_name"),
	'sample_column_hide' => array(0,12,14),
	'sample_column_filter' => array(5,6,7,8,9),
	'patient_column_exclude' => array('person_id','subject_id','first_name','last_name','middle_name','study_code','is_cellline'),
	'patient_column_hide' => array("Projects"),
	'patient_column_filter' => array(6,7,10,11,15,16),
	'qc' => ['cufoff_file' => 'QC_cutoff.txt',	'rna' => ['cols' => 'Diagnosis,TOTAL_READS,ALIGNED_READS,PCT_ALIGNED_READS,PCT_ALIGNED_Q20_BASES,PCT_RIBOSOMAL_BASES,PCT_CODING_BASES,PCT_UTR_BASES,PCT_INTRONIC_BASES,PCT_INTERGENIC_BASES,PCT_MRNA_BASES,PCT_USABLE_BASES'],
		'rnaV2' => ['cols' => 'Total Purity Filtered Reads Sequenced,Mapped,Mapped Unique,Unique Rate of Mapped,Duplication Rate of Mapped,rRNA rate,Exonic Rate,Intronic Rate,Fragment Length Mean'],
		'dna' => ['cols' => 'Diagnosis,MEAN_BAIT_COVERAGE,MEAN_TARGET_COVERAGE,total_reads,mapped_reads,percent_mapped,ontarget_reads,percent_ontarget,unique_ontarget_reads,percent_unique_ontarget,min_mapq,mean_mapq,stddev_mapq,hq_unique_ontarget_reads,percent_hq_unique_ontarget,percent_hq_unique_positions_at_5x,percent_hq_unique_positions_at_10x,percent_hq_unique_positions_at_15x,percent_hq_unique_positions_at_20x,percent_hq_unique_positions_at_30x,percent_hq_unique_positions_at_50x,percent_hq_unique_positions_at_100x,percent_hq_unique_positions_at_200x,percent_hq_unique_positions_at_400x,percent_hq_unique_positions_at_1000x']],
	'default' => ['chr' => '1',	'start_pos' => '1000000', 'end_pos' => '1500000', 'gene_list' => 'ALK BRAF FGFR1 FGFR2 FGFR3 FGFR4 MET MYC MYCN NRAS PAX8 PIK3CA RET TP53 VHL WT1'],
	'hotspot' => ['actionable' => 'hg19_Hotspot.01.25.16.txt','exons' => 'hotspot_gene_exon.01.18.17.txt','predicted' => 'MSKCC.470sites_clean.txt'],
	'cancer' => ['germline' => 'sanger_germline.txt','somatic' => 'sanger_somatic.txt'],
	'inherited_diseases' => 'TruSightInheritedDiseases.txt',
	'refseq_canonical' => 'refseq_canonical.txt',
	'genotyping' => 'genotyping.txt',
	'sample_capture' => 'seqcap',
	'clinomics_exome_capture' => 'clin.ex.v1',
	'clinomics_panel_capture' => 'clin.snv.v1',
	'igv_url' => 'http://fr-s-ccr-cbio-d.ncifcrf.gov/cbioportal/bam.jsp',
	'pubmed_url' => 'http://www.ncbi.nlm.nih.gov/pubmed/',
	'clinvar_url' => 'http://www.ncbi.nlm.nih.gov/clinvar/',
	'hgmd_url' => 'https://my.qiagendigitalinsights.com/bbp/view/hgmd/pro/mut.php?acc=',
	'hgmd_gene_url' => 'https://my.qiagendigitalinsights.com/bbp/view/hgmd/pro/gene.php?gene=',
	'hgmd_hint' => 'NIH Users can create account and access the database using code 1881-6975-97565225 in the license field during the account registration process.',
	'classification_germline_file' => 'Germline-Classification--03022017.pdf',
	'classification_somatic_file' => 'Somatic-Classification--01032017.pdf',
	'classification_fusion' => 'fusions_classification_04022021.pdf',
	'fusion_genes' => 'sanger_mitelman_pairs.txt',
	'chipseq_rnaseq_path' => '/data/khanlab/projects/processed_DATA/',
	'acmg_list_name' => 'acmg_v3',
	'pcg_list' => array('ALL_22237106','ALL_22897843','ALL_22897847','ALL_23212523','ALL_23334668','AML_23153540','DIPG_22661320','EPEN_24553141','EPEN_24553142','ETMR_24316981','EWS_25010205','EWS_25186949','EWS_25223734','GBM_18772396','GBM_23079654','HEP_23887712','HGG_20068183','HGG_22286061','HGG_22286216','HGG_23417712','HGG_24705250','HGG_24705251','LGG_23104868','LGG_23222849','LGG_23583981','MED_21163964','MED_22265402','MED_22722829','MED_22722829_G','MED_22832583','MIX_24055113','MIX_24710217','MRT_22797305','NBL_22142829','NBL_22367537','NBL_22416102','NBL_23202128','NBL_23334666','NBL_24147068','NBL_25517749','NBL_26121087','NEO_22187960','OST_24703847','OST_25512523','RB_22237022','RB_24688104_G','RMS_22142829','RMS_24272621','RMS_24332040','RMS_24436047','RMS_24436047_T','RMS_24793135','RMS_24824843','RMS_26138366','SAR_20601955','UVM_TCGA','WT_24909261','WT_25190313','WT_25313908','WT_25670082','FMI_BRAIN_ATRT','FMI_BRAIN_Astrocytoma','FMI_BRAIN_Ependymoma','FMI_BRAIN_Glioblastoma','FMI_BRAIN_Glioma','FMI_BRAIN_Medulloblastoma','FMI_BRAIN_PNET','FMI_CARCINOMA_Adrenal','FMI_CARCINOMA_Gyn_carcinoma','FMI_CARCINOMA_HCC','FMI_CARCINOMA_Head_and_neck','FMI_CARCINOMA_Lower_GI','FMI_CARCINOMA_Panc_or_biliary','FMI_CARCINOMA_Upper_GI','FMI_EXTRACRANIAL_EMBRYONAL_Liver_hepatoblastoma','FMI_EXTRACRANIAL_EMBRYONAL_Neuroblastoma','FMI_EXTRACRANIAL_EMBRYONAL_Wilms_tumor','FMI_GONADAL_TUMORS_Ovarian_or_Testis','FMI_HEME_ALL','FMI_HEME_AML','FMI_HEME_Histiocytic_neoplasm','FMI_HEME_Leukemia_NOS','FMI_HEME_Lymphoma','FMI_HEME_MDS_and_or_MPN','FMI_HEME_MLL','FMI_SARCOMA_Bone_sarcoma','FMI_SARCOMA_DSRC','FMI_SARCOMA_Ewing','FMI_SARCOMA_Fibromatosis','FMI_SARCOMA_Hemangioendothelioma','FMI_SARCOMA_MPNST','FMI_SARCOMA_RMS','FMI_SARCOMA_Soft_tissue_NOS','FMI_SARCOMA_Soft_tissue_assorted','FoundMed_Pediatric'),
	'filter_definition' => array(
			"ClinomicsTier2" => "Tier2 gene list created by Kathy Calzone.",
			"Actionable Sites" => "Mutation from : <BR><a target='_blank' href='http://docm.genome.wustl.edu/'>DoCM</a><BR><a target='_blank' href='https://civic.genome.wustl.edu/#/home'>civic_10262015</a><BR>
								<a target='_blank' href='https://targetedcancercare.massgeneral.org/'>targeted_cancer_care_10262015</a><BR><a target='_blank' href='https://candl.osu.edu/'>candl_10262015</a><BR>
								<a target='_blank' href='https://www.mycancergenome.org//'>My Cancer Genome(MCG.06.24.15)</a><BR><a target='_blank' href='https://matchbox.nci.nih.gov/matchbox/index.html'>MATCHTrial_2015_11</a><BR>",
			"Predicted Sites" => "Mutations from : <BR><a target='_blank' href='https://github.com/taylor-lab/hotspots'>MSKCC_Predicted</a>",
			"Hotspot Genes" => "Union of genes harboring Actionable Mutations sites",
			"LossOfFunction" => "Frameshift Indels, splicing mutation and stopgain mutation",
			"Clinvar" => "Pathogenic and Likely Pathogenic Mutations as describle in clinvar",
			"HGMD" => "Disease causing mutations in HGMD database",
			"ACMG" => "gene list with 57 genes for incidental findings as describle by ACMG guidelines"),
	'default_project' => 'any',
	'default_annotation' => 'avia',	
	'minPatients' => 1,
	'antigen' => array('columns' => array("hide" => ["High conf","Chr","Start","End","Ref","Alt"])),
	'high_conf' => array('maf' => 0.01, 'germline_total_cov' => 20, 'germline_fisher' => 75, 'germline_vaf' => 0.25,'somatic_panel_total_cov' => 50, 'somatic_panel_normal_total_cov' => 20,'somatic_panel_vaf' => 0.05,'somatic_exome_total_cov' => 20,'somatic_exome_normal_total_cov' => 20,'somatic_exome_vaf' => 0.1),
	'page' => array(
			'columns' => ["show" => ["Flag","IGV", "Libraries","Gene cohort","Site cohort","Chr","Start","End","Ref","Alt","Gene","AAChange","Actionable","MAF","Prediction","Clinvar","Cosmic","HGMD","Reported","ACMG V2","VAF"]],
			'germline' => [
				'maf' => 0.01,
				'total_cov' => 10,
				'vaf' => 0.25,
				'tier1' => true,
				'tier2' => true,
				'tier3' => true,
				'tier4' => false,
				'no_tier' => false,
				'no_fp' => false
			],		
			'somatic'  => [
				'maf' => 0.01,
				'total_cov' => 50,
				'vaf' => 0.1,
				'expressed' => false,
				'exp_var' => 2,
				'exp_total' => 9,
				'tier1' => true,
				'tier2' => true,
				'tier3' => true,
				'tier4' => false,
				'no_tier' => false,
				'no_fp' => false
			],
			'rnaseq'  =>  [
				'maf' => 0.01,
				'total_cov' => 50,
				'vaf' => 0.1,
				'dna_mutation' => false,
				'tier_type' => 'tier_or',
				'tier1' => true,
				'tier2' => true,
				'tier3' => true,
				'tier4' => false,
				'no_tier' => false,
				'no_fp' => true
			],
			'variants'  =>  [
				'maf' => 0.01,
				'total_cov' => 50,
				'vaf' => 0.1,
				'tier_type' => 'tier_or',
				'tier1' => true,
				'tier2' => true,
				'tier3' => true,
				'tier4' => false,
				'no_tier' => false,
				'no_fp' => false
			],
			'hotspot'  =>  [
				'maf' => 0.01,
				'total_cov' => 50,
				'vaf' => 0.1,
				'tier_type' => 'tier_or',
				'tier1' => true,
				'tier2' => true,
				'tier3' => true,
				'tier4' => true,
				'no_tier' => true,
				'no_fp' => false
			],
			'fusion'  =>  [
				'inter_chr' => false,
				'type' => '',
				'tier1' => true,
				'tier2' => true,
				'tier3' => true,
				'tier4' => false,
				'no_tier' => false
			],			
			'germline_all' =>  [
				'maf' => 1,
				'total_cov' => 0,
				'vaf' => 0,
				'tier1' => true,
				'tier2' => true,
				'tier3' => true,
				'tier4' => true,
				'no_tier' => true
			],
			'somatic_all'  => [
				'maf' => 1,
				'total_cov' => 0,
				'vaf' => 0,
				'expressed' => false,
				'exp_var' => 2,
				'exp_total' => 9,
				'tier1' => true,
				'tier2' => true,
				'tier3' => true,
				'tier4' => true,
				'no_tier' => true
			],
			'rnaseq_all'  => [
				'maf' => 1,
				'total_cov' => 0,
				'vaf' => 0,
				'dna_mutation' => false,
				'tier_type' => 'tier_or',
				'tier1' => true,
				'tier2' => true,
				'tier3' => true,
				'tier4' => true,
				'no_tier' => true
			],
			'variants_all'  => [
				'maf' => 1,
				'total_cov' => 0,
				'vaf' => 0,
				'tier_type' => 'tier_or',
				'tier1' => true,
				'tier2' => true,
				'tier3' => true,
				'tier4' => true,
				'no_tier' => true
			],
			'fusion_all'  =>  [
				'type' => '',
				'tier1' => true,
				'tier2' => true,
				'tier3' => true,
				'tier4' => true,
				'no_tier' => true
			],
			'expression' => [
				'annotation' => 'ensembl',
				'search_type' => 'gene_list',
				'gene_list' => 'ALK MYCN FTP53 MDM2 MDM4 CDKN2A CDKN2B TP53BP1 EGFR ERBB2',
				'chr' => 'chr2',
				'start_pos' => 223082000,
				'end_pos' => 223088000,
				'cluster_genes' => false,
				'cluster_samples' => false,
				'value_type' => 'log2',
				'norm_type' => 'tmm-rpkm',
				'library_type' => 'all',
				'color_scheme' => 0
			]
		),
        // Has to be registered for each site due to callback urls
    'auth'=>array(
                'redirect'=>'https://clinomics.ccr.cancer.gov/clinomics/public/login',
                'oauth'=>'https://cilogon.org/oauth2',
                'client_id'=>'cilogon:/client_id/1f20b9575caaff38c161cf58483910ff',
                'client_secrete'=>'hZYxdiWBYb5NuG-ZsKuBGj9oyl4eg3ESBVTT3XhgOWZ8rDOjj3zEHiW0J8ZTYjHO0nFtlQhvoAFdnB8otLOH6Q',
                'scope'=>"email+profile+org.cilogon.userinfo+openid",
                'website'=>'https://cilogon.org/oauth2'
        )    

);
