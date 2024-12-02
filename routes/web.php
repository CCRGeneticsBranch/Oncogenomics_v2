<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('downloadVariantsGet/{token}/{project_id}/{patient_id}/{case_id}/{type}/{sample_id?}/{gene_id?}/{stdout?}/{include_details?}/{high_conf_only?}/{var_list?}','App\Http\Controllers\VarController@downloadVariantsGet');
Route::get('/viewPortal',function() { return View::make('pages/viewPortal'); });
Route::get('/viewJunction/{patient_id}/{case_id}/{symbol?}','App\Http\Controllers\VarController@viewJunction');
Route::get('/viewDataIntegrityReport/{target?}','App\Http\Controllers\SampleController@viewDataIntegrityReport');
Route::get('/downloadDataIntegrityReport/{report_name}/{target?}','App\Http\Controllers\SampleController@downloadDataIntegrityReport');
Route::post('/downloadVariants', 'App\Http\Controllers\VarController@downloadVariants');
Route::post('/downloadVariantsFromUpload', 'App\Http\Controllers\VarController@downloadVariantsFromUpload');
Route::post('/getAntigenData', 'App\Http\Controllers\VarController@getAntigenDataByPost');
Route::get('/getVarGeneSummary/{gene_id}/{value_type}/{category}/{min_pat}/{tiers}','App\Http\Controllers\VarController@getVarGeneSummary');
Route::get('/getCNVGeneSummary/{gene_id}/{value_type}/{category}/{min_pat}/{min_amplified}/{max_deleted}','App\Http\Controllers\VarController@getCNVGeneSummary');
Route::get('/getFusionGeneSummary/{gene_id}/{value_type}/{category}/{min_pat}/{fusion_type}/{tiers}','App\Http\Controllers\VarController@getFusionGeneSummary');
Route::get('/getFusionGenePairSummary/{gene_id}/{value_type}/{category}/{min_pat}/{fusion_type}/{tiers}','App\Http\Controllers\VarController@getFusionGenePairSummary');
Route::get('/getExpGeneSummary/{gene_id}/{category}/{tissue}/{target_type?}/{lib_type?}','App\Http\Controllers\GeneDetailController@getExpGeneSummary');
Route::get('/viewQC/{patient_id}/{case_id}/{path}','App\Http\Controllers\VarQCController@viewQC');
Route::get('/getContent/{patient_id}/{case_id}/{file_path}/{type}/{subtype?}','App\Http\Controllers\VarQCController@getContent');
Route::get('/getPatients/{sid}/{search_text?}/{patient_id_only?}/{format?}', 'App\Http\Controllers\SampleController@getPatients');
Route::get ('/getPatientMetaData/{pid}/{format?}/{include_diagnosis?}/{includeOnlyRNAseq?}/{include_numeric?}/{meta_list_only?}', 'App\Http\Controllers\ProjectController@getPatientMetaData');
Route::get ('getRNAseqSample/{sample_id}', 'App\Http\Controllers\SampleController@getRNAseqSample');

Route::get('/getTierCount/{project_id}/{patient_id}/{case_id?}', 'App\Http\Controllers\SampleController@getTierCount');
    Route::get('/getCoveragePlotData/{project_id}/{patient_id}/{case_name}/{samples}'            , 'App\Http\Controllers\VarQCController@getCoveragePlotData'  );

#Route::middleware(['authorized_token'])->group(function () {
    Route::post('/getProjects'            , 'App\Http\Controllers\ProjectController@getProjectsByPost'  );
#});

Route::middleware(['logged','authorized_project'])->group(function () {
    Route::get('/getSurvivalData/{project_id}/{filter_attr_name1}/{filter_attr_value1}/{filter_attr_name2}/{filter_attr_value2}/{group_by1}/{group_by2}/{group_by_values?}' , 'App\Http\Controllers\ProjectController@getSurvivalData');
    Route::get('/getExpressionByGeneList/{project_id}/{patient_id}/{case_id}/{gene_list}/{target_type?}/{library_type?}/{value_type?}', 'App\Http\Controllers\ProjectController@getExpressionByGeneList');
    Route::get('/getExpression/{project_id}/{gene_list}/{target_type?}/{library_type?}', 'App\Http\Controllers\ProjectController@getExpression');
    Route::get('/getProjectCNV/{project_id}/{gene_list}', 'App\Http\Controllers\ProjectController@getCNV');
    Route::get('/getExpressionByLocus/{project_id}/{patient_id}/{case_id}/{chr}/{start_pos}/{end_pos}/{target_type}/{library_type}', 'App\Http\Controllers\ProjectController@getExpressionByLocus');
    Route::get('/getCases/{project_id}/{format?}'                       , 'App\Http\Controllers\SampleController@getCases');
    Route::get('/getProjectSummary/{project_id}', 'App\Http\Controllers\ProjectController@getProjectSummary');
    Route::get('/getPCAData/{project_id}/{target_type}/{value_type?}' , 'App\Http\Controllers\ProjectController@getPCAData');
    Route::get('/getMutationGenes/{project_id}/{type}/{meta_type?}/{meta_value?}/{maf?}/{min_total_cov?}/{vaf?}', 'App\Http\Controllers\ProjectController@getMutationGenes' );
    Route::get('/getMutationGeneList/{project_id}/{tier?}', 'App\Http\Controllers\ProjectController@getMutationGeneList' );
    Route::get('/getFusionProjectDetail/{project_id}/{diagnosis?}/{cutoff?}', 'App\Http\Controllers\ProjectController@getFusionProjectDetail' );
    Route::get('/getFusionGenes/{project_id}/{left_gene}/{right_gene?}/{type?}/{value?}', 'App\Http\Controllers\ProjectController@getFusionGenes' );
    Route::get('/getSampleByPatientID/{project_id}/{patient_id}/{case_id?}', 'App\Http\Controllers\SampleController@getSampleByPatientID');  
    Route::get('/getProjectQC/{project_id}/{type}/{format?}', 'App\Http\Controllers\ProjectController@getQC' );
    Route::get('/getCorrelationData/{project_id}/{gene_id}/{cufoff}/{target_type}/{method?}/{value_type?}' , 'App\Http\Controllers\ProjectController@getCorrelationData');
    Route::get('/getProjectGenotyping/{project_id}/{type?}', 'App\Http\Controllers\ProjectController@getProjectGenotyping');
    Route::get('/getProjectGenotypingByPatient/{project_id}/{patient_id}', 'App\Http\Controllers\ProjectController@getProjectGenotypingByPatient');
    Route::get('/getMatchedGenotyping/{project_id}/{cutoff?}', 'App\Http\Controllers\ProjectController@getMatchedGenotyping');
    Route::get('/getExpMatrixFile/{project_id}/{target_type}/{data_type?}', 'App\Http\Controllers\ProjectController@getExpMatrixFile');
    Route::get('/getVarAnnotation/{project_id}/{patient_id}/{sample_id}/{case_id}/{type}', 'App\Http\Controllers\VarController@getVarAnnotation'  );
    Route::get('/getVarAnnotationByGene/{project_id}/{gene_id}/{type}'            , 'App\Http\Controllers\VarController@getVarAnnotationByGene'  );
    Route::get('/getExpressionByCase/{project_id}/{patient_id}/{case_id}/{sample_name}/{source}'            , 'App\Http\Controllers\SampleController@getExpressionByCase'  );
    Route::get('/getGSEAResults/{project_id}/{token_id}'            , 'App\Http\Controllers\SampleController@getGSEAResults'  );
    Route::get('/getExpSurvivalData/{project_id}/{target_id}/{level}/{cutoff?}/{target_type?}/{data_type?}/{value_type?}/{diagnosis?}' , 'App\Http\Controllers\ProjectController@getExpSurvivalData');
    Route::get('/plotExpSurvival/{project_id}/{target_id}/{level}/{cutoff}/{pvalue}/{target_type}' , 'App\Http\Controllers\ProjectController@plotExpSurvival');  
    Route::get('/downloadProjectVariants/{project_id}/{type}'            , 'App\Http\Controllers\ProjectController@downloadProjectVariants'  );
    Route::get('/downloadProjectVCFs/{project_id}'            , 'App\Http\Controllers\ProjectController@downloadProjectVCFs'  );
    Route::get('/getCNVSummary/{project_id}', 'App\Http\Controllers\ProjectController@getCNVSummary');
    Route::get('/downloadCNVFiles/{project_id}/{file_type?}', 'App\Http\Controllers\ProjectController@downloadCNVFiles');
    Route::get('/downloadMixcrFile/{project_id}/{file}', 'App\Http\Controllers\ProjectController@downloadMixcrFile');
    Route::get('/getUserList/{project_id}', 'App\Http\Controllers\ProjectController@getUserList');
    Route::get('/viewSplice/{project_id}/{patient_id}/{case_id}'            , 'App\Http\Controllers\VarController@viewSplice'  );
    Route::get('/getSplice/{project_id}/{patient_id}/{case_id}'            , 'App\Http\Controllers\VarController@getSplice'  );
    Route::get('/getCNVTSO/{project_id}/{patient_id}/{case_id}'            , 'App\Http\Controllers\VarController@getCNVTSO'  );
    Route::get('/viewVarQC/{project_id}/{patient_id}/{case_id}',  'App\Http\Controllers\VarQCController@viewVarQC');
    Route::get('/viewProjectQC/{project_id}',  'App\Http\Controllers\VarQCController@viewProjectQC');
    Route::get('/getProjectQCI/{project_id}/{type}',  'App\Http\Controllers\ProjectController@getProjectQCI');
    Route::get('/viewQCITypeProjectDetail/{project_id}/{type}',  'App\Http\Controllers\ProjectController@viewQCITypeProjectDetail');
    Route::get('/viewProjectDetails/{project_id}', 'App\Http\Controllers\ProjectController@viewProjectDetails'); 
    Route::get('/viewSurvivalByExpression/{project_id}/{symbol}/{show_search?}/{include_header?}/{type?}/{diagnosis?}', 'App\Http\Controllers\ProjectController@viewSurvivalByExpression');
    Route::get('/viewSurvivalListByExpression/{project_id}', 'App\Http\Controllers\ProjectController@viewSurvivalListByExpression');
    Route::get('/getSurvivalListByExpression/{project_id}/{type}/{diagnosis}', 'App\Http\Controllers\ProjectController@getSurvivalListByExpression');
    Route::get('/viewProjectMixcr/{project_id}/{type}'            , 'App\Http\Controllers\ProjectController@viewProjectMixcr'  );
    Route::get('/getProjectMixcr/{project_id}/{type}/{format?}'            , 'App\Http\Controllers\ProjectController@getProjectMixcr'  );
    Route::get('/getProjectSamples/{project_id}/{format?}/{exp_type?}', 'App\Http\Controllers\ProjectController@getProjectSamples'  );
    
});

Route::middleware(['logged','authorized_patient'])->group(function () {
    Route::get('/getPatientProjects/{patient_id}', 'App\Http\Controllers\ProjectController@getPatientProjects');
    Route::get('/getCohorts/{patient_id}/{gene}/{type}', 'App\Http\Controllers\VarController@getCohorts');
    Route::get('/getSamplesByCaseName/{patient_id}/{case_name}', 'App\Http\Controllers\SampleController@getSamplesByCaseName');  
    Route::get('/getQCLogs/{patient_id}/{case_id}/{log_type}', 'App\Http\Controllers\VarController@getQCLogs' );
    Route::get('/getQC/{patient_id}/{case_id}/{type}/{project_id?}', 'App\Http\Controllers\VarController@getQC' );
    Route::get('/publishCase/{patient_id}/{case_id}', 'App\Http\Controllers\SampleController@publishCase');
    Route::get('/getHLAData/{patient_id}/{case_id}/{sample_name}'            , 'App\Http\Controllers\VarController@getHLAData'  );
    Route::get('/getAntigenData/{project_id}/{patient_id}/{case_id}/{sample_id}/{high_conf_only?}/{format?}'            , 'App\Http\Controllers\VarController@getAntigenData'  );
    Route::get('/downloadAntigenData/{patient_id}/{case_id}/{sample_name}'            , 'App\Http\Controllers\VarController@downloadAntigenData'  );
    Route::get('/downloadHLAData/{patient_id}/{case_id}/{sample_name}'            , 'App\Http\Controllers\VarController@downloadHLAData'  );
    Route::get('/createReport'            , 'App\Http\Controllers\VarController@createReport'  );
    Route::get('/getExpressionByCase/{project_id}/{patient_id}/{case_id}/{target_type?}/{sample_id?}'            , 'App\Http\Controllers\SampleController@getExpressionByCase'  );
    Route::get('/getGSEA/{project_id}/{patient_id}/{case_id}/{sample_id}'            , 'App\Http\Controllers\SampleController@getGSEA'  );   
    Route::post('/GSEAcalc/{project_id}/{patient_id}/{case_id}'            , 'App\Http\Controllers\SampleController@GSEAcalc'  );
    Route::get('/getVarActionable/{patient_id}/{case_id}/{type}/{flag}', 'App\Http\Controllers\VarController@getVarActionable'  );
    Route::get('/getVCF/{patient_id}/{case_id}'            , 'App\Http\Controllers\VarController@getVCF'  ); 
    Route::get('/getQCPlot/{patient_id}/{case_id}/{type}'            , 'App\Http\Controllers\VarQCController@getQCPlot'  );  
    Route::get('/getCaseExpMatrixFile/{patient_id}/{case_id}','App\Http\Controllers\SampleController@getCaseExpMatrixFile'  );
    Route::get('/getAnalysisPlot/{patient_id}/{case_id}/{type}/{name}','App\Http\Controllers\SampleController@getAnalysisPlot');
    Route::get('/getGSEAReport/{patient_id}/{case_id}/{geneset}/{group}/{filename}'            , 'App\Http\Controllers\SampleController@getGSEAReport'  );
    Route::get('/getGSEASummary/{patient_id}/{case_id}/{geneset}/{format?}','App\Http\Controllers\SampleController@getGSEASummary'  );
    Route::get('/getDEResults/{patient_id}/{case_id}/{de_file}','App\Http\Controllers\SampleController@getDEResults');
    Route::get('/getCNVPlot/{patient_id}/{sample_name}/{case_id}/{type}'            , 'App\Http\Controllers\VarController@getCNVPlot'  );
    Route::get('/getCNVPlotByChromosome/{patient_id}/{sample_name}/{case_id}/{type}/{chromosome}'            , 'App\Http\Controllers\VarController@getCNVPlotByChromosome'  );   
    Route::get('/getmixcrPlot/{patient_id}/{sample_name}/{case_id}/{type}'            , 'App\Http\Controllers\SampleController@getmixcrPlot'  );
    Route::get('/getmixcrTable/{patient_id}/{sample_name}/{case_id}/{type}'            , 'App\Http\Controllers\SampleController@getmixcrTable'  );
    Route::get('/viewMixcr/{patient_id}/{case_id}/{type}'            , 'App\Http\Controllers\SampleController@viewMixcr'  );
    Route::get('/getMixcr/{patient_id}/{case_id}/{type}/{format?}'            , 'App\Http\Controllers\SampleController@getMixcr'  );

    Route::get('/getCNV/{patient_id}/{case_id}/{sample_id}/{source?}/{gene_centric?}/{format?}'            , 'App\Http\Controllers\VarController@getCNV'  ); 
    Route::get('/getPatientExpression/{patient_id}/{gene}', 'App\Http\Controllers\SampleController@getPatientExpression' );
    Route::get('/signOutCase/{patient_id}/{case_id}/{type}', 'App\Http\Controllers\VarController@signOutCase' );
    Route::get('/saveVarAnnoationData/{patient_id}/{case_id}/{type}', 'App\Http\Controllers\VarController@saveVarAnnoationData' );
    Route::get('/getSignoutHistory/{patient_id}/{sample_id}/{case_id}/{type}', 'App\Http\Controllers\VarController@getSignoutHistory' );
    Route::get('/getSignoutVars/{patient_id}/{sample_id}/{case_id}/{type}/{update_at}', 'App\Http\Controllers\VarController@getSignoutVars' );
    Route::get('/downloadSignout/{patient_id}/{filename}', 'App\Http\Controllers\VarController@downloadSignout' );

    Route::get('/getCircosData/{patient_id}/{case_id}', 'App\Http\Controllers\VarController@getCircosData' );
    Route::get('/getCircosDataFromDB/{patient_id}/{case_id}', 'App\Http\Controllers\VarController@getCircosDataFromDB' );
    Route::get('/getHotspotCoverage/{patient_id}/{case_id}/{project_id?}', 'App\Http\Controllers\VarQCController@getHotspotCoverage' );
    Route::get('/getArribaPDF/{path}/{patient_id}/{case_id}/{sample_id}/{sample_name}'            , 'App\Http\Controllers\VarController@getArribaPDF'  );
    Route::get('/viewIGV/{patient_id}/{sample_id}/{case_id}/{type}/{center}/{locus}', 'App\Http\Controllers\VarController@viewIGV');
    Route::get('/viewFusionIGV/{patient_id}/{sample_id}/{case_id}/{left_chr}/{left_position}/{right_chr}/{right_position}', 'App\Http\Controllers\VarController@viewFusionIGV');
    Route::get('/getProejctListForPatient/{patient_id}', 'App\Http\Controllers\SampleController@getProejctListForPatient');
    Route::get('/viewPatient/{project_name}/{patient_id}/{case_id?}'                       , 'App\Http\Controllers\SampleController@viewPatient');
    Route::get('/requestDownloadCase/{patient_id}/{case_id?}'                       , 'App\Http\Controllers\SampleController@requestDownloadCase');
    Route::get('/viewCNVGeneLevel/{patient_id}/{case_id}/{sample_name}/{source}', 'App\Http\Controllers\VarController@viewCNVGenelevel');
    Route::get('/getCNVGeneLevel/{patient_id}/{case_id}/{sample_name}/{source}', 'App\Http\Controllers\VarController@getCNVGenelevel');    

});

Route::get('/', [
            "as"   => "user.login",
            "uses" => 'LaravelAcl\Authentication\Controllers\AuthController@getClientLogin'
    ])->name('login');

Route::middleware(['logged','can_see'])->group(function () {
    Route::get('/getUploads', 'App\Http\Controllers\VarController@getUploads');
    Route::get('/viewSyncPublic',function() { return View::make('pages/viewSyncPublic'       ); });
    Route::get ('/syncPublicProject/{project_name}','App\Http\Controllers\ProjectController@syncPublicProject');

    Route::get('/', 'App\Http\Controllers\BaseController@viewHome');
    
    Route::get('/home', 'App\Http\Controllers\BaseController@viewHome');
    Route::get('/getTopVarGenes','App\Http\Controllers\VarController@getTopVarGenes');
    Route::get ('/viewCreateProject'                   , 'App\Http\Controllers\ProjectController@viewCreateProject' );
    Route::get ('/viewEditProject/{project_id}'               , 'App\Http\Controllers\ProjectController@viewEditProject'   );
    Route::post('/saveProject'                     , 'App\Http\Controllers\ProjectController@saveProject'          );
    Route::get ('/deleteProject/{project_id}'             , 'App\Http\Controllers\ProjectController@deleteProject'        );
    Route::get ('/viewTIL/{project_id}'             , 'App\Http\Controllers\ProjectController@viewTIL');
    Route::get ('/getTIL/{project_id}'             , 'App\Http\Controllers\ProjectController@getTIL');
                                                                                             
    Route::get('/viewGeneDetail/{gene_id}' , 'App\Http\Controllers\GeneDetailController@viewGeneDetail'   );
    Route::get('/viewProjectGeneDetail/{project_id}/{gid}/{tab_id?}' , 'App\Http\Controllers\GeneDetailController@viewProjectGeneDetail'   );
    
    Route::get('/viewCase/{project_name}/{patient_id}/{case_id}/{with_header?}'                       , 'App\Http\Controllers\SampleController@viewCase');
    Route::get('/viewCases/{project_id}'                       , 'App\Http\Controllers\SampleController@viewCases');     
    Route::get('/viewPatients/{sid}/{search_text}/{include_header}/{source}'                       , 'App\Http\Controllers\SampleController@viewPatients');
    Route::get('/viewProjects', 'App\Http\Controllers\ProjectController@viewProjects');
    Route::get('/viewChIPseq/{patient_id}/{case_id}', 'App\Http\Controllers\SampleController@viewChIPseq');
    Route::get('/viewChIPseqIGV/{patient_id}/{case_id}', 'App\Http\Controllers\SampleController@viewChIPseqIGV');
    Route::get('/viewChIPseqMotif/{patient_id}/{case_id}/{sample_id}/{cutoff}/{type}', 'App\Http\Controllers\SampleController@viewChIPseqMotif');    
    Route::get('/viewExpression/{project_id}/{patient_id?}/{case_id?}/{meta_type?}/{setting?}', 'App\Http\Controllers\ProjectController@viewExpression');
    Route::get('/viewExpressionByGene/{project_id}/{gene_id}', 'App\Http\Controllers\ProjectController@viewExpressionByGene');
    Route::get('/getProjects', 'App\Http\Controllers\ProjectController@getProjects');    
    Route::get('/getGeneListByLocus/{chr}/{start_pos}/{end_pos}/{target_type}', 'GeneController@getGeneListByLocus');
    
    Route::get('/getFlagHistory/{chromosome}/{start_pos}/{end_pos}/{ref}/{alt}/{type}/{patient_id}', 'App\Http\Controllers\VarController@getFlagHistory');
    Route::get('/getFlagStatus/{chromosome}/{start_pos}/{end_pos}/{ref}/{alt}/{type}/{patient_id}', 'App\Http\Controllers\VarController@getFlagStatus');
    Route::get('/deleteFlag/{chromosome}/{start_pos}/{end_pos}/{ref}/{alt}/{type}/{patient_id}/{updated_at}', 'App\Http\Controllers\VarController@deleteFlag');
    Route::get('/deleteACMGGuide/{chromosome}/{start_pos}/{end_pos}/{ref}/{alt}/{patient_id}/{updated_at}', 'App\Http\Controllers\VarController@deleteACMGGuide');
    Route::get('/getACMGGuideClass/{chromosome}/{start_pos}/{end_pos}/{ref}/{alt}/{patient_id}', 'App\Http\Controllers\VarController@getACMGGuideClass');
    Route::get('/getACMGGuideHistory/{chromosome}/{start_pos}/{end_pos}/{ref}/{alt}/{patient_id}', 'App\Http\Controllers\VarController@getACMGGuideHistory');

    Route::get('/addFlag/{chromosome}/{start_pos}/{end_pos}/{ref}/{alt}/{type}/{old_status}/{new_status}/{patient_id}/{is_public}/{comment}', 'App\Http\Controllers\VarController@addFlag');
    Route::get('/addACMGClass/{chromosome}/{start_pos}/{end_pos}/{ref}/{alt}/{mode}/{classification}/{checked_list}/{patient_id}/{is_public}', 'App\Http\Controllers\VarController@addACMGClass');
    
    Route::post('/downloadCaseExpression', 'App\Http\Controllers\SampleController@downloadCaseExpression');
    Route::post('/broadcast', 'App\Http\Controllers\BaseController@broadcast');  
    Route::get('/viewVarProjectDetail/{project_id}/{type}/{diagnosis?}', 'App\Http\Controllers\ProjectController@viewVarProjectDetail');
    Route::get('/viewFusionProjectDetail/{project_id}',  'App\Http\Controllers\ProjectController@viewFusionProjectDetail');
    Route::get('/viewFusionGenes/{project_id}/{left_gene}/{right_gene?}/{type?}/{value?}/{diag?}',  'App\Http\Controllers\ProjectController@viewFusionGenes');

    Route::get('/viewVarAnnotation/{project_id}/{patient_id}/{sample_id}/{case_id}/{type}'            , 'App\Http\Controllers\VarController@viewVarAnnotation'  );
    Route::get('/viewVarUploadAnnotation/{file_name}'            , 'App\Http\Controllers\VarController@viewVarUploadAnnotation'  );   
    Route::get('/getVarAnnotationByVariant/{chr}/{start}/{end}/{ref}/{alt}'            , 'App\Http\Controllers\VarController@getVarAnnotationByVariant'  );
    Route::get('/getVarUploadAnnotation/{file_name}/{type}'            , 'App\Http\Controllers\VarController@getVarUploadAnnotation'  );
    Route::get('/insertVariant/{chr}/{start}/{end}/{ref}/{alt}'            , 'App\Http\Controllers\VarController@insertVariant'  );

    Route::get('/viewVariant/{patient_id}/{case_id}/{sample_id}/{type}/{chr}/{start}/{end}/{ref}/{alt}/{gene}/{genome?}/{source?}'            , 'App\Http\Controllers\VarController@viewVariant'  );

    Route::get('/viewVarAnnotationByGene/{project_id}/{gene_id}/{type}/{with_header?}/{tier_type?}/{tier?}/{meta_type?}/{meta_value?}/{patient_id?}/{no_fp?}/{maf?}/{total_cov?}/{vaf?}'            , 'App\Http\Controllers\VarController@viewVarAnnotationByGene'  );
    
    
    Route::get('/viewExpressionByCase/{project_id}/{patient_id}/{case_id}/{sample_id?}'            , 'App\Http\Controllers\SampleController@viewExpressionByCase'  );
    Route::get('/viewExpressionAnalysisByCase/{project_id}/{patient_id}/{case_id}', 'App\Http\Controllers\SampleController@viewExpressionAnalysisByCase'  );
    Route::get('/getExpressionMatrix/{patient_id}/{case_id}', 'App\Http\Controllers\SampleController@getExpressionMatrix');
    Route::get('/viewMixcrTable/{project_id}/{patient_id}/{case_id}/{sample_name}/{source}', 'App\Http\Controllers\SampleController@viewMixcrTable' );
    
    Route::get('/viewGSEA/{project_id}/{patient_id}/{case_id}/{token_id}'            , 'App\Http\Controllers\SampleController@viewGSEA'  );  
    Route::get('/viewGSEAResults/{project_id}/{token_id}'            , 'App\Http\Controllers\SampleController@viewGSEAResults'  );
    Route::get('/getGSEAInput/{token_id}'            , 'App\Http\Controllers\SampleController@getGSEAInput'  );
    Route::get('/downloadGSEAResults/{project_id}/{token_id}'            , 'App\Http\Controllers\SampleController@downloadGSEAResults'  );   
    Route::get('/removeGSEArecords/{token_id}'            , 'App\Http\Controllers\SampleController@removeGSEArecords'  );
    Route::get('/viewContact'                       , function() { return View::make('pages/viewContact'       ); });
    Route::get('/viewAPIs'                       , function() { return View::make('pages/viewAPIs'       ); });
    Route::get('/getSample/{id}'                       , 'App\Http\Controllers\SampleController@getSample');
    
    Route::get('/getCaseDetails/{sid}/{search_text}/{case_id}/{patient_id_only?}'                       , 'App\Http\Controllers\SampleController@getCaseDetails');

    Route::get('/getPatientIDs/{sid}/{search_text}'                       , 'App\Http\Controllers\SampleController@getPatientIDs');
    Route::get('/viewPatientTree/{custom_id}'                       , 'App\Http\Controllers\SampleController@viewPatientTree');
    Route::get('/viewGenotyping/{id}/{type?}/{source?}/{has_header?}'                       , 'App\Http\Controllers\SampleController@viewGenotyping');
    Route::get('/getPatientTreeJson/{id}'                       , 'App\Http\Controllers\SampleController@getPatientTreeJson');
    Route::get('/getCasesByPatientID/{project_id}/{patient_id}'                       , 'App\Http\Controllers\SampleController@getCasesByPatientID');
    Route::get('/getCaseSummary{case_id}/'                       , 'App\Http\Controllers\SampleController@getCaseSummary');
    Route::get('/getpipeline_summary/{patient_id}/{case_id}'                       , 'App\Http\Controllers\SampleController@getpipeline_summary');
    Route::get('/getAvia_summary'                       , 'App\Http\Controllers\SampleController@getAvia_summary');

    Route::get('/getGenotyping/{id}/{type?}/{source?}'                       , 'App\Http\Controllers\SampleController@getGenotyping');
    Route::get('/getPatientGenotyping/{patient_id}/{case_id}/{project_id?}'                       , 'App\Http\Controllers\SampleController@getPatientGenotyping');
    Route::get ('/getTranscriptExpressionData/{gene_list}/{sample_id}', 'App\Http\Controllers\GeneDetailController@getTranscriptExpressionData');
    
    
    Route::get('/getCNVByGene/{project_id}/{gene_id}/{source?}/{format?}'            , 'App\Http\Controllers\VarController@getCNVByGene'  );
    Route::get('/getFusionByPatient/{patient_id}/{case_id}'            , 'App\Http\Controllers\VarController@getFusionByPatient'  );
    Route::get('/viewFusion/{patient_id}/{case_id}/{with_header?}', 'App\Http\Controllers\VarController@viewFusion'  );
    Route::get('/getFusion/{patient_id}/{case_id}', 'App\Http\Controllers\VarController@getFusion'  );

    Route::get('/getVarDetails/{type}/{patient_id}/{case_id}/{sample_id}/{chr}/{start_pos}/{end_pos}/{ref_base}/{alt_base}/{gene_id}/{genome?}/{source?}', 'App\Http\Controllers\VarController@getVarDetails'  );;
    Route::get('/getVarSamples/{chr}/{start_pos}/{end_pos}/{ref_base}/{alt_base}/{patient_id}/{case_id}/{type}', 'App\Http\Controllers\VarController@getVarSamples'  );
    Route::get('/getBAM/{path}/{patient_id}/{case_id}/{sample_id}/{file}', 'App\Http\Controllers\VarController@getBAM');
    Route::get('/getBigWig/{path}/{patient_id}/{case_id}/{sample_id}/{file}', 'App\Http\Controllers\VarController@getBigWig');

    Route::get('/getPatientDetails/{patient_id}'            , 'App\Http\Controllers\SampleController@getPatientDetails'  );

    Route::get('/updatePatientDetail/{patient_id}/{old_key}/{key}/{value}', 'App\Http\Controllers\SampleController@updatePatientDetail'  );
    Route::get('/addPatientDetail/{patient_id}/{key}/{value}', 'App\Http\Controllers\SampleController@addPatientDetail'  );
    Route::get('/deletePatientDetail/{patient_id}/{key}', 'App\Http\Controllers\SampleController@deletePatientDetail'  );
    Route::get('/getExpSamplesFromVarSamples/{sample_list}', 'App\Http\Controllers\SampleController@getExpSamplesFromVarSamples'  );
    Route::get('/getIGVLink/{patient_id}/{locus}', 'App\Http\Controllers\SampleController@getIGVLink'  );
    Route::get('/getSignaturePlot/{patient_id}/{sample_name}/{case_id}/{file?}', 'App\Http\Controllers\VarController@getSignaturePlot');
    Route::get('/getTCellExTRECTPlot/{patient_id}/{case_id}/{sample_id}', 'App\Http\Controllers\VarController@getTCellExTRECTPlot'); 
    Route::get('/viewSetting', 'App\Http\Controllers\UserSettingController@viewSetting'  );
    Route::get('/viewIGVByLocus/{locus}', function($locus) { return View::make('pages/viewIGV', ['locus'=>$locus]);});

    Route::get('/viewUploadClinicalData',  function() { return View::make('pages/viewUploadClinicalData', ["projects" => \App\Models\User::getCurrentUserProjects()]);});
    Route::get('/viewUploadVarData',  function() { return View::make('pages/viewUploadVarData', ["projects" => \App\Models\User::getCurrentUserProjectsData()]);});
    Route::get('/viewUploadVCF',  function() { return View::make('pages/viewUploadVCF', ["projects" => \App\Models\User::getCurrentUserProjectsData()]);});

    Route::get('/calculateTransFusionData/{left_gene}/{left_trans}/{right_gene}/{right_trans}/{left_junction}/{right_junction}',  'App\Http\Controllers\VarController@calculateTransFusionData');
    Route::get('/getFusionDetailData/{left_gene}/{left_trans}/{right_gene}/{right_trans}/{left_chr}/{right_chr}/{left_junction}/{right_junction}/{sample_id}',  'App\Http\Controllers\VarController@getFusionDetailData');
    Route::get('/getFusionData/{left_gene}/{right_gene}/{left_chr}/{right_chr}/{left_junction}/{right_gene_junction}/{sample_id}/{type}', 'App\Http\Controllers\VarController@getFusionData');
    Route::post('/saveGeneList', 'App\Http\Controllers\UserSettingController@saveGeneList'  );
    Route::post('/saveSetting/{attr_name}', 'App\Http\Controllers\UserSettingController@saveSetting'  );
    Route::post('/saveSystemSetting/{attr_name}', 'App\Http\Controllers\UserSettingController@saveSystemSetting'  );
    Route::get('/syncClinomics', 'App\Http\Controllers\UserSettingController@syncClinomics'  );

    Route::post('/processVarUpload', 'App\Http\Controllers\VarController@processVarUpload'  );
    Route::get('/saveSettingGet/{attr_name}/{attr_value}', 'App\Http\Controllers\UserSettingController@saveSettingGet'  );
    Route::post('/saveClinicalData', 'App\Http\Controllers\SampleController@saveClinicalData' );
    Route::post('/uploadVarData', 'App\Http\Controllers\VarController@uploadVarData' );
    Route::post('/uploadVCF', 'App\Http\Controllers\VarController@uploadVCF' );
    Route::post('/uploadVarText', 'App\Http\Controllers\VarController@uploadVarText' );
    Route::post('/uploadExpData', 'App\Http\Controllers\VarController@uploadExpData' );
    Route::post('/uploadFusionData', 'App\Http\Controllers\VarController@uploadFusionData' );
    Route::post('/signOut', 'App\Http\Controllers\VarController@signOut' );

    Route::get ('/viewProjectPatient/{project_id}'                       , 'App\Http\Controllers\ProjectController@viewPatient');
    Route::get('/getProject/{id}', 'App\Http\Controllers\ProjectController@getProject' );

    Route::post('/saveQCLog', 'App\Http\Controllers\VarController@saveQCLog' );
    Route::get('/getCytobandData', 'App\Http\Controllers\VarController@getCytobandData' );
    Route::get('/viewCircos/{patient_id}/{case_id}', 'App\Http\Controllers\VarController@viewCircos' );


    Route::get('/viewCNV/{project_id}/{patient_id}/{case_id}/{sample_name}/{source}/{gene_centric?}', 'App\Http\Controllers\VarController@viewCNV' );

    Route::get('viewAntigen/{project_id}/{patient_id}/{case_id}/{sample_name}', 'App\Http\Controllers\VarController@viewAntigen' );
    Route::get('/viewCNVByGene/{project_id}/{gene_id}', 'App\Http\Controllers\VarController@viewCNVByGene' );
    Route::get('/getAllFusions/{patient_id?}', 'App\Http\Controllers\VarController@getAllFusions' ); 

    Route::get('/getPatientTree', 'App\Http\Controllers\ProjectController@getPatientTree');
    Route::get('/getOncoTree', 'App\Http\Controllers\ProjectController@getOncoTree');
    Route::post('/runPipeline', 'App\Http\Controllers\SampleController@runPipeline');    
    Route::get('/getPatientsByFusionGene/{gene_id}/{cat_type}/{category}/{fusion_type}/{tiers}', 'App\Http\Controllers\VarController@getPatientsByFusionGene');
    Route::get('/getPatientsByFusionPair/{left_gene}/{right_gene}/{fusion_type}/{tiers}', 'App\Http\Controllers\VarController@getPatientsByFusionPair');
    Route::get('/getPatientsByVarGene/{gene_id}/{type}/{cat_type}/{category}/{tiers}', 'App\Http\Controllers\VarController@getPatientsByVarGene');
    Route::get('/getPatientsByCNVGene/{gene_id}/{cat_type}/{category}/{min_amplified}/{max_deleted}', 'App\Http\Controllers\VarController@getPatientsByCNVGene');
    Route::get ('/getMutationBurden/{project_id}/{patient_id}/{case_id}', 'App\Http\Controllers\VarController@getMutationBurden');
    Route::get ('/viewMutationBurden/{project_id}/{patient_id}/{case_id}', 'App\Http\Controllers\VarController@viewMutationBurden');
    
    //unused links

    Route::get('/viewSearchSample/{keyword}'                       , 'App\Http\Controllers\SampleController@viewSearchSample');
    Route::get('/searchSample/{keyword}'                       , 'App\Http\Controllers\SampleController@searchSample');
    Route::get('/viewSTR/{id}'                       , 'App\Http\Controllers\SampleController@viewSTR');
    Route::get('/getSampleByBiomaterialID/{id}'                       , 'App\Http\Controllers\SampleController@getSampleByBiomaterialID');
    Route::get('/getBiomaterial/{id}'                       , 'App\Http\Controllers\SampleController@getBiomaterial');
    Route::get('/getSampleDetails/{id}'                       , 'App\Http\Controllers\SampleController@getSampleDetails');
    Route::get('/getSTR/{id}'                       , 'App\Http\Controllers\SampleController@getSTR');
    Route::get('/getStudies'                       , 'StudyController@getStudies');
    Route::get('/viewStudyDetails/{id}'                       , 'StudyDetailController@viewStudyDetails');
    Route::get('/getStudyDetails/{id}'                       , 'StudyDetailController@getStudyDetails');
    Route::get('/viewCorrelation/{sid}/{gid}' , 'App\Http\Controllers\GeneDetailController@viewCorrelation'   );
    Route::get('/getCorrelationHeatmapData/{sid}/{gid}/{cufoff}/{topn}/{target_type}' , 'App\Http\Controllers\GeneDetailController@getCorrelationHeatmapData');
    Route::get('/getTTestHeatmapData/{sid}/{gid}/{target_type}' , 'App\Http\Controllers\GeneDetailController@getTTestHeatmapData');
    Route::get('/getTwoGenesDotplotData/{sid}/{g1}/{g2}/{target_type}/{norm_type}' , 'App\Http\Controllers\ProjectController@getTwoGenesDotplotData'   );
    Route::get('/getStudyQueryData/{sid}/{gene_list}/{target_type}' , 'StudyDetailController@getStudyQueryData');
    Route::get('/getStudySummaryJson/{sid}' , 'StudyDetailController@getStudySummaryJson');
    Route::get('/getPCAPlatData/{sid}' , 'StudyDetailController@getPCAPlatData');
    Route::get ('/viewStudyQuery/{sid}'           , 'StudyDetailController@viewStudyQuery'         );
    Route::post('/viewStudyQuery/{sid}'           , 'StudyDetailController@viewStudyQuery'         );
    Route::get('/viewExpressionHeatmapByLocus/{sid}/{chr}/{start}/{end}/{target_type}'           , 'StudyDetailController@viewExpressionHeatmapByLocus'         );
    Route::get ('/getGeneDetailExpressionData/{sid}/{gid}/{target_type}', 'App\Http\Controllers\GeneDetailController@getGeneDetailExpressionData');
    Route::get ('/getGeneStructure/{gid}/{target_type}', 'App\Http\Controllers\GeneDetailController@getGeneStructure');
    Route::get ('/getCodingSequences/{gid}/{target_type}', 'App\Http\Controllers\GeneDetailController@getCodingSequences');
    Route::get ('/hasEnsemblData/{sid}', 'StudyDetailController@hasEnsemblData');

    Route::get ('/getPfamDomains/{symbol}', 'VarianceController@getPfamDomains');
    Route::get ('/predictPfamDomain/{seq}', 'App\Http\Controllers\GeneDetailController@predictPfamDomain');
    Route::get ('/getSampleMutation/{sample_id}/{gene_id}', 'VarianceController@getSampleMutation');
    Route::get ('/getRefMutation/{sample_id}/{gene_id}', 'VarianceController@getRefMutation');
    Route::get ('/viewMutationPlot/{sample_id}/{gene_id}/{type}', 'App\Http\Controllers\VarController@viewMutationPlot');
    Route::get ('/getMutationPlotData/{sample_id}/{gene_id}/{type}', 'App\Http\Controllers\VarController@getMutationPlotData');
    Route::get ('/downloadExampleExpression/{type}', 'App\Http\Controllers\ProjectController@downloadExampleExpression');

    //end of unused links
});
Route::get ('/getCaseBySampleID/{sample_id}', 'App\Http\Controllers\SampleController@getCaseBySampleID');
Route::get ('/getCaseByLibrary/{sample_name}/{FCID}', 'App\Http\Controllers\SampleController@getCaseByLibrary');
Route::get ('/getPatientsJsonByProject/{project_name}/{patient_list?}/{exp_types?}/{excluded_list?}', 'App\Http\Controllers\SampleController@getPatientsJsonByProject');
Route::get ('/getPatientsJson/{patient_list}/{case_id_list?}/{exp_types?}/{source?}/{fcid?}/{do_format?}/{sample_name?}/{excluded_samples?}', 'App\Http\Controllers\SampleController@getPatientsJson');
Route::get ('/getPatientsJsonByCaseName/{case_name}', 'App\Http\Controllers\SampleController@getPatientsJsonByCaseName');
Route::post ('/getPatientsJson', 'App\Http\Controllers\SampleController@getPatientsJsonByPost');
Route::post ('/getPatientsJsonByFCID', 'App\Http\Controllers\SampleController@getPatientsJsonByFCID');
Route::get ('/getPatientsJsonV2/{patient_list}/{case_id_list?}/{exp_types?}/{source?}/{fcid?}/{do_format?}/{sample_name?}/{excluded_samples?}', 'App\Http\Controllers\SampleController@getPatientsJsonV2');
Route::get('/getChIPseqSampleSheet/{sample_id}'            , 'App\Http\Controllers\SampleController@getChIPseqSampleSheet'  );
Route::get('/calculateGeneFusionData/{left_gene}/{right_gene}/{left_chr}/{right_chr}/{left_junction}/{right_junction}',  'App\Http\Controllers\VarController@calculateGeneFusionData');
Route::get('/getAAChangeHGVSFormat/{chr}/{start_pos}/{end_pos}/{ref}/{alt}/{gene}/{transcript}',  'App\Http\Controllers\VarController@getAAChangeHGVSFormat');
Route::get('/getVarTier/{patient_id}/{case_id}/{type}/{sample_id?}/{annotation?}/{avia_table_name?}',  'App\Http\Controllers\VarController@getVarTier');
Route::get('/predictPfamDomain/{id}/{seq}',  'GeneController@predictPfamDomain');
Route::post('/downloadFusion', 'App\Http\Controllers\VarController@downloadFusion');
Route::post('/getFusionBEDPE', 'App\Http\Controllers\VarController@getFusionBEDPE');
Route::post('/getFusionBEDPEv2', 'App\Http\Controllers\VarController@getFusionBEDPEv2');
Route::post('/getFusionBEDPEv3', 'App\Http\Controllers\VarController@getFusionBEDPEv3');
Route::post('/getVariants', 'App\Http\Controllers\VarController@getVariants');

Route::get('/downloadCNV/{patient_id}/{case_id}/{sample_id}/{source}', 'App\Http\Controllers\VarController@downloadCNV');
Event::listen('illuminate.query', function($query)
{
    //Log::info($query);
});
