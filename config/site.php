<?php

return array(

	/*
	|--------------------------------------------------------------------------
	| Site specific variables
	|--------------------------------------------------------------------------
	|	
	|
	*/
	'cache' => env('CACHE', 0),
	'cache.var' => env('CACHE_VAR', 0),
	'cache.mins' => env('CACHE_MINS', 60*24),
	'var.use_table' => env('VAR_USE_TABLE', 0),	
	'avia' => env('AVIA', true),
    'token' => env('TOKEN'),
    'public_token' => env('PUBLIC_TOKEN'),
	#'avia_table' => 'avia.hg19_avia3@abcc_lnk',//PROD; database links have to be checked for each DB 
    #'avia_version' => 'avia.avia_db_ver@abcc_lnk',//PROD
    'avia_table' => env('AVIA_TABLE', 'hg19_annot@aviap_lnk'),
    'hg19_annot_table' => env('AVIA_HG19_ANNOTATION_TABLE', 'hg19_annot_oc'),
    'hg38_annot_table' => env('AVIA_HG38_ANNOTATION_TABLE', 'hg38_annot_oc'),
    #'avia_table' => 'hg19_annot@pub_lnk',
    'avia_version' => env('AVIA_VERSION', 'avia_db_ver@aviap_lnk'),
    'url' => env('URL', 'https://fsabcl-onc01d.ncifcrf.gov/clinomics5/public'),
    'url_production' => env('URL_PRODUCTION', 'https://oncogenomics.ccr.cancer.gov/production/public'),
    'url_public' => env('URL_PUBLIC','https://clinomics.ccr.cancer.gov/clinomics/public/'),
    'url_dev' => env('URL_DEV','https://fsabcl-onc01d.ncifcrf.gov/clinomics_dev/public'),
    'R_LIBS' => env('R_LIBS','/mnt/nasapps/development/R/r_libs/4.0.2/'),
    'R_PATH' => env('R_PATH','/mnt/nasapps/development/R/4.0.2/bin/'),
    'LD_LIBRARY_PATH' => env('LD_LIBRARY_PATH','/mnt/nasapps/development/R/shared_libs/4.0.2'),
    'mount' => env('MOUNT','/mnt/projects/CCR-JK-oncogenomics/static/clones/clinomics'),
    'mount_public' => env('MOUNT_PUBLIC','/mnt/projects/CCR-JK-oncogenomics/static/clones/clinomics_public'),
    'isPublicSite'=>env('IS_PUBLIC_SITE',1),
    'isCILogon'=>env('IS_CILOGON',0),
    'db_connection'=>env('DB_CONNECTION','oracle'),
    'projects' => 
    	array(
    		"RNAseq_Landscape_Manuscript" => 
    			array(
    				"GSEA"=>false
    			),                
    		"COG_NCI_UK_RMS" => 
    			array(
    				"GSEA"=>false,
    				"germline"=>true,
    				"somatic"=>true,
    				"rnaseq"=>false,
    				"variants"=>true,
    				"fusion"=>false,
    				"mutation_burden"=>true,
                    "hotspot"=>true,
                    "cnv"=>false,
    				"hotspot"=>true,
    				"expression"=>false,
    				"qc"=>false,
                    "igv"=>true,
    				"download"=>false,
                    "survival_meta_list"=>array("Diagnosis","Cohort","Grouping FP or FN","Risk Group","Anatomic Group","Stage","Sex","Race","Study","ALK","ARID1A","ATM","BCOR","BRAF","CDK4","CDKN2A","CTNNB1","DICER1","ERBB2","FBXW7","FGFR1","FGFR4","HRAS","IGF1R","KRAS","MDM2","MET","MTOR","MYCN","MYOD1","NF1","NRAS","PDGFRA","PIK3CA","PTEN","PTPN11","SOS1","TP53","Tier 1 Lesion count")
    			)
    	),
    // Has to be registered for each site due to callback urls
    'auth'=>array(
            'redirect'=>env('AUTH_REDIRECT','https://fsabcl-onc01d.ncifcrf.gov/clinomics/public/login'),
            'oauth'=>env('AUTH_OATH','https://cilogon.org/oauth2'),
            'client_id'=>env('AUTH_CLIENT_ID','cilogon:/client_id/1f20b9575caaff38c161cf58483910ff'),
            'client_secrete'=>env('AUTH_CLIENT_SECRETE','hZYxdiWBYb5NuG-ZsKuBGj9oyl4eg3ESBVTT3XhgOWZ8rDOjj3zEHiW0J8ZTYjHO0nFtlQhvoAFdnB8otLOH6Q'),
            'scope'=>env('AUTH_SCOPE',"email+profile+org.cilogon.userinfo+openid"),
            'website'=>env('AUTH_WEBSITE','https://cilogon.org/oauth2')
    )
);
