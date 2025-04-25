@extends('layouts.default')
@section('title', 'Project--'.$project->name)
@section('content')
{!! HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') !!}
{!! HTML::style('css/style_datatable.css') !!}
{!! HTML::style('packages/jquery/jquery.dataTables.css') !!}
{!! HTML::style('packages/jquery-easyui/themes/bootstrap/easyui.css') !!}
{!! HTML::style('packages/fancyBox/source/jquery.fancybox.css') !!}
{!! HTML::style('packages/tooltipster-master/dist/css/tooltipster.bundle.min.css') !!}
{!! HTML::style('packages/w2ui/w2ui-1.4.min.css') !!}
{!! HTML::style('css/light-bootstrap-dashboard.css') !!}

{!! HTML::script('packages/DataTables/datatables.min.js') !!}
{!! HTML::script('js/bootstrap.min.js') !!}
{!! HTML::script('packages/jquery-easyui/jquery.easyui.min.js') !!}
{!! HTML::script('packages/highchart/js/highcharts.js')!!}
{!! HTML::script('packages/highchart/js/highcharts-3d.js')!!}
{!! HTML::script('packages/highchart/js/highcharts-more.js')!!}
{!! HTML::script('packages/highcharts-regression/highcharts-regression.js')!!}
{!! HTML::script('packages/highchart/js/modules/exporting.js')!!}
{!! HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') !!}

{!! HTML::script('js/onco.js') !!}
{!! HTML::script('packages/tooltipster-master/dist/js/tooltipster.bundle.min.js') !!}
{!! HTML::script('packages/w2ui/w2ui-1.4.min.js')!!}

<style>
a.boxclose{
    float:right;
    margin-top:-12px;
    margin-right:-12px;
    cursor:pointer;
    color: #fff;
    border-radius: 10px;
    font-weight: bold;
    display: inline-block;
    line-height: 0px;
    padding: 8px 3px; 
    width:25px;
    height:25px;    
    background:url('{!!url('/images/close-button.png')!!}') no-repeat center center;  
}
</style>

<script type="text/javascript">
	var tbl;	
	var pca_data;
	var pca_plot = null;
	var pcaPLoadingPlot;
	var pcaNLoadingPlot;
	var col_html = '';
	var columns = [];
	var tab_urls = [];
	var loaded_list = [];
	var tbl;
	var tblGSVA;
	var tblHLA;
	var tblSTR;
	var has_survival = {!!$has_survival!!};	
	var survival_meta_list = {!!$survival_meta_list!!};
	var survival_diags = {!!$survival_diags!!};
	var attr_values = {};
	var my_sp = null;
	var tblSample = null;
	var tblCase = null;
	var exp_type_idx = 5;
	var lib_type_idx = 6;
	var tissue_cat_idx=7;
	var tissue_type_idx=8;
	var version_idx = 6;	
	
	$(document).ready(function() {
		$("#loadingSummary").css("display","block");
		var url='{!!url("/getProjectSummary/".$project->id)!!}';
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {			
				summary_json = parseJSON(data);
				$("#loadingSummary").css("display","none");
				var table = document.getElementById("project_summary");
				var row = null;
				var num_cols = 3;
				var width = $('#tabDetails').width() * 0.95;
				//$('#summary_header').css('width', width);

				var offset = 0;
				if (summary_json.fusion.length > 0) {
					row = table.insertRow(0);
					var cell = row.insertCell(0);
					var div_id = 'tier1fusion';
					var chart_html = '<div class="card" style="height: 350px; width:' + parseInt(width/num_cols) + 'px; margin: 5 5 5 5" id="' + div_id + '"></div>';
					cell.innerHTML = chart_html;
					//console.log(JSON.stringify(summary_json.fusion));
					var fusion_data = [];
					summary_json.fusion.forEach(function (d) {
						fusion_data.push([d.genes, d.count]);
					});
					//console.log(JSON.stringify(fusion_data));
					showColumnPlot(div_id, 'Fusion - Tier 1.1', 'Genes', fusion_data, '.0f', function(p) {
							var cols = [{title:'Patient ID'}];
							summary_json.patient_meta.attr_list.forEach(function(attr){
								cols.push({title:attr});
							});
							var data = [];
							summary_json.fusion.forEach(function(d){
								if (d.genes == p.name) {									
									for (var patient in summary_json.patient_meta.data) {
										if (d.patient_list.indexOf(patient) >= 0) {
											var patient_url = '<a target=_blank href="{!!url("/viewPatient/$project->id")!!}' + '/' + patient + '">' + patient + '</a>';
											var row_data = [patient_url];
											summary_json.patient_meta.attr_list.forEach(function(attr, attr_idx) {
												row_data.push(summary_json.patient_meta.data[patient][attr_idx]);
											});
											data.push(row_data);
										}
									}
								}								
							});
							$('#selected_patients').w2popup();
							showTable('tblSelPatients', {cols: cols, data: data});
							$('#w2ui-popup #lblTotalPatients').text('Fusion : ' + p.name + ' (' + data.length + ' patients)');									
					});												
					offset++;
				}

				var cell_idx = offset;
				summary_json.patient_meta.attr_list.forEach(function(attr, attr_idx){
					var width_scale = (attr == "Diagnosis") ? 1 : 0;
					var values = [];
					for (var patient in summary_json.patient_meta.data)
						values.push(summary_json.patient_meta.data[patient][attr_idx]);
					var data = [];
					idx = attr_idx + offset;
					if (cell_idx % num_cols == 0)
						row = table.insertRow(cell_idx/num_cols);
					if (row != null) {
						//var cell = row.insertCell(cell_idx % num_cols);
						var cell = row.insertCell(-1);
						var chart_width = parseInt(width/num_cols);
						cell_idx++;
						if (width_scale == 1) {
							cell.colSpan = 2;
							cell_idx++;							
							chart_width = parseInt(width/num_cols)*(width_scale+1) + 25;
						}

						var div_id = attr.replace(/[\s\(\)]/g, '');						
						var chart_html = '<div class="card" style="height: 350px; width:' + chart_width + 'px; margin: 5 5 5 5" id="' + div_id + '"></div>';
						cell.innerHTML = chart_html;
						//console.log(attr);
						var plotHist = isNumberArray(values);
						var minHist = 7;
						if (plotHist) {
							//console.log(JSON.stringify(values));
							values = getNumberArray(values);							
							if (unique_array(values).length > minHist) {
								//console.log(JSON.stringify(values));
								var bin_data = getHistData(values);
								//console.log(JSON.stringify(bin_data));
								//how about negative values?
								showHistChart(div_id, attr, bin_data, function(p) {
										var cols = [{title:'Patient ID'}];
										summary_json.patient_meta.attr_list.forEach(function(attr){
											cols.push({title:attr});
										});
										var bin_bottom = 0;
										var bin_top = 0;
										bin_data.forEach(function(bin) {
											if (p.x > bin[2] && p.x <= bin[3]) {
												bin_bottom = bin[2];
												bin_top = bin[3];
											}
										});
										var data = [];
										for (var patient in summary_json.patient_meta.data) {										
											var patient_value = parseFloat(summary_json.patient_meta.data[patient][attr_idx]);

											var bin_bottom_cutoff  = (bin_bottom == 0)? bin_bottom - 1 : bin_bottom;
											if ( patient_value > bin_bottom_cutoff && patient_value <= bin_top) {
												var patient_url = '<a target=_blank href="{!!url("/viewPatient/$project->id")!!}' + '/' + patient + '">' + patient + '</a>';
												var row_data = [patient_url];
												summary_json.patient_meta.attr_list.forEach(function(attr, attr_idx) {
													row_data.push(summary_json.patient_meta.data[patient][attr_idx]);
												});
												data.push(row_data);
												//console.log(patient);
											}
										}
										$('#selected_patients').w2popup();
										showTable('tblSelPatients', {cols: cols, data: data});
										bin_bottom = Math.round(bin_bottom*100) / 100;
										bin_top = Math.round(bin_top*100) / 100;
										$('#w2ui-popup #lblTotalPatients').text(attr + ' : ' + bin_bottom + ' ~ ' + bin_top + ' (' + data.length + ' patients)');
									});
							} else {
								plotHist = false;
							}
						}
						if (!plotHist) {		
							//console.log(JSON.stringify(values));					
							data = getPieChartData(values);							
							if (has_survival) {
								var attr_value = [];
								for (var i in data){
									var a = data[i];
									attr_value.push(a.name);
								};
								attr_values[attr] = attr_value;
								if (survival_meta_list == null) {
									$('#selSurvFilterType1').append('<option value="' + attr + '">' + attr + ' </option>');
									$('#selSurvFilterType2').append('<option value="' + attr + '">' + attr + ' </option>');
									$('#selSurvGroupBy1').append('<option value="' + attr + '">' + attr + ' </option>');
									$('#selSurvGroupBy2').append('<option value="' + attr + '">' + attr + ' </option>');
								}
							}							
							if (attr == "Diagnosis") {
								if (data.length > 5) {									
									if (cell_idx % num_cols == 0)
										row = table.insertRow(cell_idx/num_cols);
									cell_idx++;
									var tbl_cell = row.insertCell(-1);
									tbl_cell.colSpan = 1;
									var tbl_div_id = 'tbl_' + div_id;
									chart_width = parseInt(width/num_cols);
									var tbl_chart_html = '<div class="card" style="height: 350px; width:' + chart_width + 'px; margin: 5 auto"><table id="' + tbl_div_id + '" style="font-size:13px;"></table></div>';
									tbl_cell.innerHTML = tbl_chart_html;
									var tbl_cols = [{"title":"Diagnosis"}, {"title":"Number"}];
									var tbl_data = [];
									data.forEach(function(d){
										tbl_data.push([d.name, d.y]);
									});
									$('#' + tbl_div_id).DataTable( {
										ordering: true,										
										data: tbl_data,
										columns: tbl_cols,
										scrollY:        '220',
        								scrollCollapse: true,
        								paging:         false					
									});	
									$('#' + tbl_div_id + '_wrapper').css("max-height","320px");


								}
								console.log(JSON.stringify(data));
							}
							showPieChart(div_id, attr, data, function(p) {								
								var cols = [{title:'Patient ID'}];
								summary_json.patient_meta.attr_list.forEach(function(attr){
									cols.push({title:attr});
								});

								var data = [];
								for (var patient in summary_json.patient_meta.data) {
									//console.log(summary_json.patient_meta.data[patient][attr_idx]);
									//console.log(p.name);
									if (summary_json.patient_meta.data[patient][attr_idx] == p.name) {
										var patient_url = '<a target=_blank href="{!!url("/viewPatient/$project->id")!!}' + '/' + patient + '">' + patient + '</a>';
										var row_data = [patient_url];
										summary_json.patient_meta.attr_list.forEach(function(attr, attr_idx) {
											row_data.push(summary_json.patient_meta.data[patient][attr_idx]);
										});
										data.push(row_data);
										//console.log(patient);
									}
								}
								//console.log(JSON.stringify(cols));
								//console.log(JSON.stringify(data));
								//$('#' + div_id).w2overlay('HAHA');								
								$('#selected_patients').w2popup();
								showTable('tblSelPatients', {cols: cols, data: data});
								$('#w2ui-popup #lblTotalPatients').text(attr + ' : ' + p.name + ' (' + data.length + ' patients)');
								//alert(p.name);
							});
						}						
					}
				})				

				if (has_survival) {
					//$('#selSurvGroupBy').append('<option value="mutation">Tier1 Mutation Genes</option>');
					if (survival_meta_list != null) {
						survival_meta_list.forEach(function(attr) {
							$('#selSurvFilterType1').append('<option value="' + attr + '">' + attr + ' </option>');
							$('#selSurvFilterType2').append('<option value="' + attr + '">' + attr + ' </option>');
							$('#selSurvGroupBy1').append('<option value="' + attr + '">' + attr + ' </option>');
							$('#selSurvGroupBy2').append('<option value="' + attr + '">' + attr + ' </option>');
						})
					}
					getSurvivalData();
				}
				@if ($has_cnv_summary)
				var url='{!!url("/getCNVSummary/".$project->id)!!}';
				console.log(url);
				$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {			
						cnv_json = parseJSON(data);	
						//console.log(JSON.stringify(cnv_json.cols));
						var tblcnv = $('#tblCNVSummary').DataTable( {				
								"paging":   true,
								"ordering": true,
								"info":     true,
								"dom": 'lfrtip',
								"data": cnv_json.data,
								"columns": cnv_json.cols,
								"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
								"pageLength":  25,
								"pagingType":  "simple_numbers",									
							} );		
					}
				});
				@endif

				@if ($has_hla)
					showHLATable();
				@endif

				@if ($has_str)
					showSTRTable();
				@endif

				@if (App\Models\User::isProjectManager() || App\Models\User::isSuperAdmin())
				var url='{!!url("/getUserList/".$project->id)!!}';
				console.log(url);
				$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {			
						user_json = parseJSON(data);	
						//console.log(JSON.stringify(cnv_json.cols));
						var tblUsers = $('#tblUsers').DataTable( {				
								"paging":   true,
								"ordering": true,
								"info":     true,
								"dom": 'lfrtip',
								"data": user_json.data,
								"columns": user_json.cols,
								"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
								"pageLength":  55,
								"pagingType":  "simple_numbers",									
							} );
						$('#lblTotalUsers').text(user_json.data.length);
					}
				});
				@endif
				@if (count($genesets) > 0)
					showGSVATable();
				@endif
			}

		});
		
		$('.box').fancybox({
    		width  : '90%',
    		height : '90%',
    		type   :'iframe',
    		autoSize: false
		});

		@if ($project->getExpressionCount() > 0)
			showPCA();
		@endif
		
		$('#gene_id').keyup(function(e){
			if(e.keyCode == 13) {
        		$('#btnGene').trigger("click");
    		}
		});	

		$('#btnPlotSurvival').on('click', function() {
			getSurvivalData();
		});


		$('#btnDownloadVCF').on('click', function() {
			var url = '{!!url('/downloadProjectVCFs')!!}' + '/' + '{!!$project->id!!}';
			window.location.replace(url);	
		});

		$('#btnGene').on('click', function() {
			if ($('#gene_id').val().trim() != "")
				window.open("{!!url('/viewProjectGeneDetail')!!}" + "/{!!$project->id!!}/" + $('#gene_id').val() + '/0');
        });

        $('.pca-control').on('change', function() {
			showPCA();
        });

        $('#selSurvFilterType1').on('change', function() {
			$('#selSurvFilterValues1').empty();
			var value = $('#selSurvFilterType1').val();
			if (value == "any") {
				$('#selSurvFilterValues1').css("display","none");
				$('#selSurvFilterType2').val("any");
				$('#selSurvFilterType2').css("display","none");
				$('#selSurvFilterValues2').css("display","none");
				$('#lblFilter2').css("display","none");
				return;
			}
			else {
				$('#selSurvFilterValues1').css("display","inline");
				$('#selSurvFilterType2').css("display","inline");
				//$('#selSurvFilterValues2').css("display","inline");
				$('#lblFilter2').css("display","inline");
			}
			attr_value = attr_values[value];
			if (value.toLowerCase() == "diagnosis")
				survival_diags.sort().forEach(function(attr) {
					$('#selSurvFilterValues1').append('<option value="' + attr + '">' + attr + '</option>');
				});
			else
				attr_value.sort().forEach(function(attr){
					$('#selSurvFilterValues1').append('<option value="' + attr + '">' + attr + '</option>');
				});

        });

        $('#selSurvFilterType2').on('change', function() {
			$('#selSurvFilterValues2').empty();
			var value = $('#selSurvFilterType2').val();
			if (value == "any") {
				$('#selSurvFilterValues2').css("display","none");				
				return;
			}
			else {
				$('#selSurvFilterValues2').css("display","inline");				
			}
			attr_value = attr_values[value];
			if (value.toLowerCase() == "diagnosis")
				survival_diags.sort().forEach(function(attr) {
					$('#selSurvFilterValues2').append('<option value="' + attr + '">' + attr + '</option>');
				});
			else
				attr_value.sort().forEach(function(attr){
					$('#selSurvFilterValues2').append('<option value="' + attr + '">' + attr + '</option>');
				});

        });
		
		$('#selSurvGroupBy1').on('change', function() {			
			/*
			if ($('#selSurvGroupBy1').val() == "mutation")
				$('#mutationGenes').css("display","inline");
			else 
				$('#mutationGenes').css("display","none");
			*/
		});		

    	$('.surv_radio').click(function () {
        	setVisible();
    	});

    	$('.gsva').on('change', function () {
        	showGSVATable();
    	});

    	$('#btnDownloadGSVA').on('click', function() {
    		var url = '{!!url("/getGSVAData/$project->id")!!}' + '/' + $('#selGeneset').val() + '/' + $('#selGSVAMethod').val() + '/text' ;
			console.log(url);
			window.location.replace(url);	
		});

		$('#btnDownloadHLA').on('click', function() {
    		var url = '{!!url("/getProjectHLA/$project->id")!!}' + '/text' ;
			console.log(url);
			window.location.replace(url);	
		});

		$('#btnDownloadSTR').on('click', function() {
    		var url = '{!!url("/getProjectSTR/$project->id")!!}' + '/text' ;
			console.log(url);
			window.location.replace(url);	
		});

		var url = '{!!url("/getProjectHLA/$project->id")!!}';
		
		$('#btnDownloadMatrix').on('click', function() {
			if ($('#selDownloadDataType').val() == "sample_meta") {
				var url = '{!!url('/getProjectSamples')!!}' + '/' + '{!!$project->id!!}' + '/text/RNAseq';
				window.location.replace(url);
			} else if ($('#selDownloadDataType').val() == "isoforms") {
				var url = '{!!url('/getIsofromZippedFile')!!}' + '/' + '{!!$project->id!!}';
				console.log(url);
				window.location.replace(url);

			} else {
				var url = '{!!url('/getExpMatrixFile')!!}' + '/' + '{!!$project->id!!}' + '/' + $('#selDownloadTargetType').val() + '/' + $('#selDownloadDataType').val();
				console.log(url);
				window.location.replace(url);
			}
		});
		
		$('#btnDownloadCNVFile').on('click', function() {
			var url = '{!!url('/downloadCNVFiles')!!}' + '/' + '{!!$project->id!!}' + '/' + $('#selCNVFile').val();
			console.log(url);
			window.location.replace(url);	
		});

		$('#btnDownloadMixcr').on('click', function() {
			var url = '{!!url('/downloadMixcrFile')!!}' + '/' + '{!!$project->id!!}' + '/' + $('#selMixcrFile').val();
			console.log(url);
			window.location.replace(url);	
		});

		$('#ckShowLabel').change(function() {
			var value=$('#ckShowLabel').is(":checked");
			pca_plot.series.forEach(function(s) {
            	s.update({dataLabels : {enabled: value}});
        	})
		});


		$('.easyui-tabs').tabs({
			onSelect:function(title) {
				if (title == "Samples") {
					getSampleData();
					return;
				}
				if (title == "Cases") {
					getCaseData();
					return;
				}
				if (title == "Mutations" || title == "Expression" || title == "Survival" || title=="TCR/BCR")
					if (title == "TCR/BCR")
						tab = $('#tabTCRBCR').tabs('getSelected');
					else
						tab = $('#tab' + title).tabs('getSelected');
				else
					tab = $(this).tabs('getSelected');				
				var id = tab.panel('options').id;
				console.log(id);
				showFrameHtml(id);	
		   }
		});

		$.fn.dataTableExt.afnFiltering.push( function( oSettings, aData, iDataIndex ) {	
			if (oSettings.nTable == document.getElementById('tblSamples')) {
				if ($('#selExpTypes').val() != "All") {
					if ($('#selExpTypes').val() != aData[exp_type_idx])
						return false;
				}
				if ($('#selLibTypes').val() != "All") {
					if ($('#selLibTypes').val() != aData[lib_type_idx])
						return false;
				}
				if ($('#selTissueCats').val() != "All") {
					if ($('#selTissueCats').val() != aData[tissue_cat_idx])
						return false;
				}
				if ($('#selTissueTypes').val() != "All") {
					if ($('#selTissueTypes').val() != aData[tissue_type_idx])
						return false;
				}
				return true;
			}
			if (oSettings.nTable == document.getElementById('tblCases')) {
				if ($('#selVersions').val() != "All") {
					if ($('#selVersions').val() != aData[version_idx])
						return false;
				}
				return true;
			}
			if (oSettings.nTable == document.getElementById('tblHLA')) {
				if ($('#ckHLA').is(":checked")) {
					var called = 0;
					for (var i=4;i<aData.length;i++) {
						if (aData[i] != "NA" && aData[i] != "NotCalled" && aData[i] != "")
							called++;
						if (called >= 2)
							return true;
					}
					return false;
				}
			}
			return true;
		});	

		$('#btnDownloadSamples').on("click", function(){
			var url = '{!!url('/getProjectSamples')!!}' + '/' + '{!!$project->id!!}' + '/text';
			window.location.replace(url);
		});

		$('#btnDownloadCases').on("click", function(){
			var url = '{!!url('/getCases')!!}' + '/' + '{!!$project->id!!}' + '/text';
			window.location.replace(url);
		});
		

		patient_url = '{!!url('/viewPatients/'.$project->id.'/any/0/project_details')!!}'
		//alert(url);
		//addTab('Patient data', url);
		tab_urls['Patients'] = patient_url;
		@if ($project->getSampleSummary("RNAseq") > 0)
			//addTab('Expression', '{!!url('/viewExpression/'.$project->id)!!}' + '/null/null/all/null');
		@endif
		
		@if ($project->getSampleSummary("RNAseq") > 0)
			//addTab('Fusion genes', '{!!url("/viewFusionProjectDetail/$project->id")!!}');
		@endif
		@foreach ( $var_count as $type => $cnt)
			@if ($cnt > 0)
				url = '{!!url("/viewVarProjectDetail/$project->id/$type")!!}';
				tab_urls['{!!Lang::get("messages.$type")!!}'] = url;				
				//addTab('{!!$type!!} mutation', url);
			@endif
		@endforeach
		@if ($project->hasBurden())
			tab_urls['Mutation_Burden'] = '{!!url("/viewMutationBurden/$project->id/null/null")!!}';;
		@endif
		tab_urls['fusion_summary'] = '{!!url("/viewFusionProjectDetail/$project->id")!!}';
		tab_urls['Heatmap'] = '{!!url('/viewExpression/'.$project->id)!!}';
		tab_urls['Stats'] = '{!!url("/viewProjectMixcr/$project->id/summary")!!}';
		tab_urls['Clones'] = '{!!url("/viewProjectMixcr/$project->id/clones")!!}';
		tab_urls['QC'] = '{!!url('/viewProjectQC/'.$project->id)!!}';
		tab_urls['GSEA'] = '{!!url("/viewGSEA/$project->id/any/any/".rand())!!}';
		@if ($has_survival_pvalues)
			tab_urls['ByExpression'] = '{!!url("/viewSurvivalListByExpression/$project->id")!!}';
		@else
			tab_urls['ByExpression'] = '{!!url("/viewSurvivalByExpression/$project->id/".App\Models\User::getLatestGene()."/Y")!!}';
		@endif

		tab_urls['TIL'] = '{!!url("/viewTIL/$project->id")!!}';

		//addTab('GSEA', '{!!url('/viewGSEA/'.$project->id)!!}');	
		$('#tabDetails').tabs('select', 'Summary');
		$('.mytooltip').tooltipster();				
	});

	function downloadCase(patient_id, case_id) {
		var url = '{!!url('/requestDownloadCase')!!}' + '/' + patient_id + '/' + case_id;
		console.log(url);		
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
					w2alert("<H5>" + data + "</H5>");
				}, error: function(xhr, textStatus, errorThrown){					
						w2alert("<H5>Error:</H5>" + JSON.stringify(xhr) + ' ' + errorThrown);
				}
		});
	}

	function getCaseData() {
		if (tblCase != null)
			return;
		$("#loadingCases").css("display","block");
		var url = '{!!url("/getCases/$project->id")!!}';
		console.log(url);
       	$.ajax({ url: url, async: true, dataType: 'text', success: function(json_data) {
				$("#loadingCases").css("display","none");
				$('#caseContent').css('display', 'block');
				data = JSON.parse(json_data);
				if (data.data.length == 0) {
					alert('no data!');
					return;
				}

				@if (\App\Models\User::hasProjectGroup("khanlab"))
					data.cols.push({title:"Download"});

					data.data.forEach(function(row,i) {
						var patient_id = row[0];
						var case_id = row[2];
						data.data[i].push("<a href=\"javascript:downloadCase('" + patient_id + "','" + case_id + "');\"><img width=15 height=15 src={!!url("images/download.svg")!!}></a>");
					})
				@endif	

				var versions = {};
				
				tblCase = $('#tblCases').DataTable( 
				{				
					"paging":   true,
					"ordering": true,
					"info":     true,
					"dom": 'lfrtip',
					"data": data.data,
					"columns": data.cols,
					"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
					"pageLength":  15,
					"pagingType":  "simple_numbers",
					"columnDefs": [{
                			"render": function ( data, type, row, meta ) {
                						if (meta.col == version_idx)
                							versions[data] = ''; 
                						return data;
                						},
                			"targets": '_all'
            				}],
				} );

				versions = objAttrToArray(versions);
				versions.sort().forEach(function(d){
    				$('#selVersions').append($('<option>', {value: d, text: d}));	
    			});

    			$('#lblCaseCountDisplay').text(tblCase.page.info().recordsDisplay);
    			$('#lblCaseCountTotal').text(tblCase.page.info().recordsTotal);

				$('#tblCases').on( 'draw.dt', function () {
					$('#lblCaseCountDisplay').text(tblCase.page.info().recordsDisplay);
    				$('#lblCaseCountTotal').text(tblCase.page.info().recordsTotal);
    			});

    			$('.caseFilter').on('change', function() {
					tblCase.draw();
					$('#lblCaseCountDisplay').text(tblCase.page.info().recordsDisplay);
    				$('#lblCaseCountTotal').text(tblCase.page.info().recordsTotal);
				});
			}
		});
	}

	function getSampleData() {
		if (tblSample != null)
			return;
		$("#loadingSamples").css("display","block");
		var url = '{!!url("/getProjectSamples/$project->id")!!}';
		console.log(url);
       	$.ajax({ url: url, async: true, dataType: 'text', success: function(json_data) {
				$("#loadingSamples").css("display","none");
				$('#sampleContent').css('display', 'block');
				data = JSON.parse(json_data);
				if (data.data.length == 0) {
					alert('no data!');
					return;
				}
				var exp_types = {};
				var lib_types = {};
				var tissue_cats = {};
				var tissue_types = {};

				tblSample = $('#tblSamples').DataTable( 
				{				
					"paging":   true,
					"ordering": true,
					"info":     true,
					"dom": 'lfrtip',
					"data": data.data,
					"columns": data.cols,
					"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
					"pageLength":  15,
					"pagingType":  "simple_numbers",
					"columnDefs": [{
                			"render": function ( data, type, row, meta ) {
                						if (meta.col == exp_type_idx)
                							exp_types[data] = ''; 
                						if (meta.col == lib_type_idx)
                							lib_types[data] = '';
                						if (meta.col == tissue_cat_idx)
                							tissue_cats[data] = '';
                						if (meta.col == tissue_type_idx)
                							tissue_types[data] = ''; 
                						//	console.log(data);
                						return data;
                						},
                			"targets": '_all'
            				}],
				} );

				exp_types = objAttrToArray(exp_types);
				exp_types.sort().forEach(function(d){
    				$('#selExpTypes').append($('<option>', {value: d, text: d}));	
    			});
    			lib_types = objAttrToArray(lib_types);
				lib_types.sort().forEach(function(d){
    				$('#selLibTypes').append($('<option>', {value: d, text: d}));	
    			});
    			tissue_cats = objAttrToArray(tissue_cats);
				tissue_cats.sort().forEach(function(d){
    				$('#selTissueCats').append($('<option>', {value: d, text: d}));	
    			});
    			tissue_types = objAttrToArray(tissue_types);
				tissue_types.sort().forEach(function(d){
    				$('#selTissueTypes').append($('<option>', {value: d, text: d}));	
    			});
    			

				$('#lblSampleCountDisplay').text(tblSample.page.info().recordsDisplay);
    			$('#lblSampleCountTotal').text(tblSample.page.info().recordsTotal);

				$('#tblSamples').on( 'draw.dt', function () {
					$('#lblSampleCountDisplay').text(tblSample.page.info().recordsDisplay);
    				$('#lblSampleCountTotal').text(tblSample.page.info().recordsTotal);
    			});

    			$('.sampleFilter').on('change', function() {
					tblSample.draw();
					$('#lblSampleCountDisplay').text(tblSample.page.info().recordsDisplay);
    				$('#lblSampleCountTotal').text(tblSample.page.info().recordsTotal);
				});
			}
		});
	}

	function setVisible() {
		var meta_display = ($('#radioMeta').is(':checked'))? "inline" : "none";
		var mutation_display = ($('#radioMut').is(':checked'))? "inline" : "none";
		var fusion_display = ($('#radioFusion').is(':checked'))? "inline" : "none";
		$('#meta_group').css("display", meta_display);
       	$('#mut_group').css("display", mutation_display);
       	$('#fusion_group').css("display", fusion_display);
	}

	function showFrameHtml(id) {
		if (loaded_list.indexOf(id) == -1) {
			var url = tab_urls[id];
			if (url != undefined) {
				var html = '<iframe scrolling="no" frameborder="0"  src="' + url + '" style="width:100%;min-height:100%;height:100%;overflow:auto;border-width:0px"></iframe>';
				$('#' + id).html(html);
				console.log('#' + id);
				console.log(html);
				loaded_list.push(id);
			}
		}
	}

	function getSurvivalData() {
		var filter_attr_name1 = $('#selSurvFilterType1').val();
		var filter_attr_value1 = $('#selSurvFilterValues1').val();
		var filter_attr_name2 = $('#selSurvFilterType2').val();
		var filter_attr_value2 = $('#selSurvFilterValues2').val();
		var group_by_attr_name1 = $('#selSurvGroupBy1').val();
		var group_by_attr_name2 = $('#selSurvGroupBy2').val();
		if (group_by_attr_name1 == group_by_attr_name2)
			group_by_attr_name2 = "no_used";
		if (filter_attr_name1 == "any")
			filter_attr_value1 = "any";
		if (filter_attr_name2 == "any")
			filter_attr_value2 = "any";
		var mutation_values = "null";
		if ($('#radioMut').is(':checked')) {
			var tier = $('#selTier').val();
			var tier_type = $('#selTierType').val();
			var gene1 = $('#selGene1').val();
			var gene2 = $('#selGene2').val();
			var relation = $('#selMutationRelation').val();
			/*
			if (gene1.trim() == "") {
				alert("Please input gene name!");
				return;
			}
			*/
			group_by_attr_name1 = "mutation";
			group_by_attr_name2 = "not_used";
			mutation_values = tier + ':' + tier_type + ':' + gene1 + ':' + relation + ':' + gene2;
		}
		if ($('#radioFusion').is(':checked')) {
			group_by_attr_name1 = "fusion";
			group_by_attr_name2 = "not_used";
			mutation_values = encodeURIComponent($('#selFusionPairs').val());
		}
		$("#loadingAllSurvival").css("display","block");
		$("#survival_status").css("display","none");
		$("#survival_panel").css("visibility","hidden");
		var url = '{!!url("/getSurvivalData/$project->id")!!}' + '/' + encodeURIComponent(encodeURIComponent(filter_attr_name1)) + '/' + encodeURIComponent(encodeURIComponent(filter_attr_value1)) + '/' + encodeURIComponent(encodeURIComponent(filter_attr_name2)) + '/' + encodeURIComponent(encodeURIComponent(filter_attr_value2)) + '/' + encodeURIComponent(encodeURIComponent(group_by_attr_name1)) + '/' + encodeURIComponent(encodeURIComponent(group_by_attr_name2)) + '/' + mutation_values;
		console.log(url);		

		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {	
				$("#loadingAllSurvival").css("display","none");
				$("#survival_panel").css("visibility","visible");
				survival_data = parseJSON(data);
				if (data == "no data" || survival_data.length == 0) {
					$("#message_row").css("display","block");
					$("#plot_row").css("display","none");
					return;
				} else {					
					$("#message_row").css("display","none");
					$("#plot_row").css("display","block");
				}
				if (survival_data.hasOwnProperty('overall')) {										
					$("#overall_survival_plot").css("display","block");
					$("#overall_col").css("display","block");
					showSurvivalPlot("overall_survival_plot", "Overall Survival" , survival_data.overall);					
				}
				else {
					$("#overall_survival_plot").css("display","none");
					$("#overall_col").css("display","none");
				}
				if (survival_data.hasOwnProperty('event_free')) {
					//$("#event_free_survival_plot").css("display","block");
					$("#event_free_col").css("display","block");
					showSurvivalPlot("event_free_survival_plot", "Event Free Survival" , survival_data.event_free);
				}
				else {
					$("#event_free_survival_plot").css("display","none");
					$("#event_free_col").css("display","none");	
					//$("#overall_col").addClass("col-md-12");
					//$("#overall_col").removeClass("col-md-6");					
				}
				//showSurvivalCutoffPlot(user_plot, "User Defined Survival", "Exp cutoff: " + survival_data.user_data.cutoff + ", P-value :" + selected_pvalue, survival_data.user_data.high_num, survival_data.user_data.low_num, survival_data.user_data.data);
			}
		});
	}

	Highcharts.Renderer.prototype.symbols.cross = function (x, y, w, h) {
		return [
        'M', x + w/2, y, // move to position
        'L', x + w/2, y + h, // line to position
        'M', x, y + h/2, // move to position
        'L', x + w, y + h/2, // line to position
        'z']; // close the shape, but there's nothing to close!!
	}

	function showSurvivalPlot(div, title, surv_data) {
		//console.log(JSON.stringify(data));
		//var sample_num = {"Low" : low_num, "High" : high_num};		
		var patient_count = surv_data.patient_count;
		var plot_data = surv_data.plot_data;
		var data = surv_data.data
		var subtitle = "P-value: " + surv_data.pvalue;
		data.forEach(function(d){
			var s = 5;
			var cencored = (d[3] == 0);
			if (plot_data[d[2]] == null) {
				//console.log(d[2]);
				return;
			}
			plot_data[d[2]].push({name: d[4][0][0], cencored: cencored, x:parseFloat(d[0]), y:parseFloat(d[1]), 
					marker: {
                		radius: s, 
                		lineWidth:1,                		
                		states: { hover: { radius: s+2}},
                		enabled : cencored,
                		symbol : 'cross',                		
                	},                	
            });
		});		
		var series = [];
		var series_size = Object.keys(plot_data).length;
		for (var cat in plot_data) {
			//console.log("cat " + cat);
			series.push(
				{
					data: plot_data[cat], 
					step: 'left', 					
			 		name: cat + '(' + patient_count[cat] + ')',
			 		marker : {lineColor: null},
			 		cursor: 'pointer',
			        point: {
		               events: {
			                    click: function () {
			                    	if (this.name == undefined)
			                    		return;
			                    	var url = '{!!url("/viewPatient/")!!}' + '/' +  '{!!$project->id!!}' + '/' + this.name;
									window.open(url, '_blank');			                    	
								}
							}                    
					},
			 	});
			if (series_size <= 2 && cat == "Y") series[series.length-1].color = "blue";
			if (series_size <= 2 && cat == "NoValue") series[series.length-1].color = "black";
		}		
		
		my_sp=Highcharts.chart(div, {
			credits: false,
		    title: {
		        text: title
		    },
		    subtitle: {
		    	text: subtitle
		    },		    
            tooltip: {
            	crosshairs: [true, true],
		        formatter: function(chart) {
		            var p = this.point;
		            var status = (p.cencored)? "Alive" : "Dead";
		            if (p.y == 1) return;
		            return '<b>Patient ID: </b>' + p.name + '<br><b>Survival Rate: </b>' + p.y + ' <br><b>Days: </b>' + p.x  + ' <br><b>Status: </b>' + status;
		        }
		    },
		    yAxis: {
		    	min: 0,
        		max: 1
    		},
            series: series
		});
	}

	function addTab(title, url){
		console.log(url);
		var type='add';		
		var content = '<iframe scrolling="auto" frameborder="0"  src="'+url+'" style="width:100%;overflow:none"></iframe>';
			$('#tabDetails').tabs(type, {
					title:title,
					content:content,
					closable:false,
			});
	}

	function showPCA() {
		$("#loadingPCA").css("display","block");
		$("#no_pca_data").css("display","none");
		var url = '{!!url("/getPCAData/$project->id")!!}' + '/' + $('#selTargetType').val() + '/' + $('#selValueType').val();
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
					pca_data = JSON.parse(data);					
					$("#loadingPCA").css("display","none");					
					$("#pca_panel").css("display","block");
					if (pca_data.status == "no data") {						
						$("#no_pca_data").css("display","block");
						return;	
					}
										
					if ($('#selSampleAttr').children('option').length == 0) {
						pca_data.sample_meta.attr_list.forEach(function(d, idx){
							$('#selSampleAttr').append($('<option>', { value : idx }).text(d));
						})

						pca_data.pca_variance.forEach(function(d, idx){
						var pc_idx = idx + 1;
							$('#selPC').append($('<option>', { value : pc_idx }).text('PC' + pc_idx));
						})
					}
					
					showPCAPlot();
					showLoading();
					showVariance();
					$('#selSampleAttr').on('change', function() {
						showPCAPlot();

					});

					$('#selPC').on('change', function() {
						showLoading();

					});

				}
		});
	}
	function showPCAPlot() {
		var sample_meta_idx = $('#selSampleAttr').val();
		var groups = {};		
		for (var sample in pca_data.sample_meta.data) {
			if (groups[pca_data.sample_meta.data[sample][sample_meta_idx]] == null)
				groups[pca_data.sample_meta.data[sample][sample_meta_idx]] = [];
			if (pca_data.data[sample] != null) {
				var coord = getNumberArray(pca_data.data[sample]);
				if (coord.length == 3)
					groups[pca_data.sample_meta.data[sample][sample_meta_idx]].push({name: sample, x : coord[0], y:coord[1], z:coord[2]});
			} else { //this is for landscape project								
				var patient_id = pca_data.patients[sample];
				//console.log(patient_id);
				if (patient_id != null) {
					var coord = getNumberArray(pca_data.data[patient_id]);
					if (coord.length == 3)
						groups[pca_data.sample_meta.data[sample][sample_meta_idx]].push({name: sample, x : coord[0], y:coord[1], z:coord[2]});
				}				
			}
		}
		var series = [];
		//console.log(JSON.stringify(pca_data.sample_meta.data));
		for (var sample_attr in groups) {
			if (groups[sample_attr].length > 0)
				series.push(
					{
						name: sample_attr, 
						colorByPoint: false, 
						data: groups[sample_attr],
						cursor: 'pointer',
						point: {
                    		events: {
	                    		click: function (p) {
	                    			var patient_id = pca_data.patients[p.point.name];
	                    			var url = '{!!url("/viewPatient/")!!}' + '/' +  '{!!$project->id!!}' + '/' + patient_id;
									window.open(url, '_blank');	                    			
								}
							}
						}					
					});
		}
		var width = $('#tabDetails').width() * 0.8;
		var pca_height = (width * 0.6 - 100 > 750)? 750: width * 0.7;
		var legend_height = Object.keys(groups).length * 5;
		$("#pca_plot").css("width",width * 0.6 + 'px');		
			//$("#pca_plot").css("height", + pca_height + 'px');
		$("#pca_plot").css("height", + (width * 0.6 + legend_height) + 'px');		
		//console.log(JSON.stringify(series));
		show3DScatter('pca_plot', 'Principle component Analysis', 'PC1(' + pca_data.variance_prop[0] + '%)', 'PC2(' + pca_data.variance_prop[1] + '%)', 'PC3(' + pca_data.variance_prop[2] + '%)', series, $('#ckShowLabel').is(":checked"), legend_height);
	}

	function showLoading() {
		var pc_idx = $('#selPC').val();
		var p_genes = pca_data.pca_loading.p["PC" + pc_idx][0];
		var p_loading = pca_data.pca_loading.p["PC" + pc_idx][1];
		var p_data = [];
		p_genes.forEach(function(d, idx){
			p_data.push([d, p_loading[idx]]);
		});
		showColumnPlot('pca_p_loading_plot', 'Loading - positive', 'Loading', p_data);
		var n_genes = pca_data.pca_loading.n["PC" + pc_idx][0];
		var n_loading = pca_data.pca_loading.n["PC" + pc_idx][1];
		var n_data = [];
		n_genes.forEach(function(d, idx){
			n_data.push([d, n_loading[idx]]);
		});		
		showColumnPlot('pca_n_loading_plot', 'Loading - negative', 'Loading', n_data);
	}

	function showVariance() {
		var x_labels = [];
		for (var i=1; i<=pca_data.pca_variance.length; i++)
			x_labels.push('PC' + i);
		showLinePlot('pca_var_plot', 'Variance', x_labels, pca_data.pca_variance);
	}

	function showGSVATable() {
		@if ($gsva_nsmps < 200)
		$("#loading_gsva").css("display","block");
		var url = '{!!url("/getGSVAData/$project->id")!!}' + '/' + $('#selGeneset').val() + '/' + $('#selGSVAMethod').val();
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
			$("#loading_gsva").css("display","none");
			data = JSON.parse(data);
			if (data.status == "no data") {
				alert("No data!");
				return;
			}
			if (tblGSVA != null) {
				tblGSVA.destroy();
				$('#tblGSVA').empty();
			}
			tblGSVA = $('#tblGSVA').DataTable( 
					{				
						"paging":   true,
						"ordering": true,
						"info":     true,
						"dom": 'lfrtip',
						"data": data.data,
						"columns": data.cols,
						"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
						"pageLength":  15,
						"pagingType":  "simple_numbers",
															
					} );
			}
		});
		@endif
		

	}

	function showHLATable() {
		$("#loadingHLA").css("display","block");
		var url = '{!!url("/getProjectHLA/$project->id")!!}';
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
			$("#loadingHLA").css("display","none");
			data = JSON.parse(data);
			if (data.status == "no data") {
				alert("No HLA data!");
				return;
			}

			tblHLA = $('#tblHLA').DataTable( 
					{				
						"paging":   true,
						"ordering": true,
						"info":     true,
						"dom": 'lfrtip',
						"data": data.data,
						"columns": data.cols,
						"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
						"pageLength":  15,
						"pagingType":  "simple_numbers",
															
					} );
			$('#lblHLACountDisplay').text(tblHLA.page.info().recordsDisplay);
    		$('#lblHLACountTotal').text(tblHLA.page.info().recordsTotal);
			}
		});

		$('#ckHLA').on('change', function() {
					tblHLA.draw();
					$('#lblHLACountDisplay').text(tblHLA.page.info().recordsDisplay);
    				$('#lblHLACountTotal').text(tblHLA.page.info().recordsTotal);
		});

	}

	function showSTRTable() {
		$("#loadingSTR").css("display","block");
		var url = '{!!url("/getProjectSTR/$project->id")!!}';
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
			$("#loadingSTR").css("display","none");
			data = JSON.parse(data);
			if (data.status == "no data") {
				alert("No STR data!");
				return;
			}

			tblSTR = $('#tblSTR').DataTable( 
					{				
						"paging":   true,
						"ordering": true,
						"info":     true,
						"dom": 'lfrtip',
						"data": data.data,
						"columns": data.cols,
						"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
						"pageLength":  15,
						"pagingType":  "simple_numbers",
															
					} );
			$('#lblSTRCountDisplay').text(tblSTR.page.info().recordsDisplay);
    		$('#lblSTRCountTotal').text(tblSTR.page.info().recordsTotal);
			}
		});
	}

	function showTable(tbl_id, data) {
		if (tbl != null) {
			tbl.destroy();
			$('#w2ui-popup #' + tbl_id).empty();
		}

		tbl = $('#w2ui-popup #' + tbl_id).DataTable( 
				{				
					"paging":   true,
					"ordering": true,
					"info":     true,
					"dom": 'lfrtip',
					"data": data.data,
					"columns": data.cols,
					"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
					"pageLength":  15,
					"pagingType":  "simple_numbers",									
				} );		
	}



</script>

<div id="selected_patients" style="display: none; width: 90%; height: 80%;background-color=white;">	
	<div rel="body" style="text-align:left;padding: 20px;">
		<a href="javascript:w2popup.close();" class="boxclose"></a>
    	<H4><lable id="lblTotalPatients"></lable></H4><HR>
    	<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblSelPatients" style='width:100%'>
		</table>
	</div>
</div>

<div id="out_container" class="easyui-panel" data-options="border:false" style="width:100%;padding:0px;border-width:0px">
	<div class="row" style="padding: 0px 20px 0px 20px">
		<div class="col-md-8">
			<ol class="breadcrumb" style="margin-bottom:0px;padding:4px 20px 0px 0px;background-color:#ffffff;font-size: 16px;">
				<li class="breadcrumb-item active"><a href="{!!url('/')!!}">Home</a></li>
				<li class="breadcrumb-item active"><a href="{!!url('/viewProjects/')!!}">Projects</a></li>
				<li class="breadcrumb-item active"><a href="{!!url('/viewProjectDetails/'.$project->id)!!}">{!!$project->name!!}</a></li>			
			</ol>
		</div>
		<div class="col-md-4">
			<span class="float-right h6">
					<img width="20" height="20" src="{!!url('images/search-icon.png')!!}"></img> Gene: <input id='gene_id' type='text' value=''/>&nbsp;&nbsp;<button id='btnGene' class="btn btn-info mx-1 my-1">GO</button>
			</span>
		</div>
	</div>
	<div id="tabDetails" class="easyui-tabs" data-options="tabPosition:top,plain:true,pill:false" style="width:100%;padding:10px;overflow:auto;">
	<!--div id="tabMain" class="easyui-tabs" data-options="tabPosition:'top',plain:true, pill:true,border:false" style="width:95%;padding:10px;overflow:auto;border-width:0px"-->		
		<div title="Summary" style="width:98%;padding:5px;">
			<div id='loadingSummary' class='loading_img'>
				<img src='{!!url('/images/ajax-loader.gif')!!}'></img>
			</div>
			<div id="summary_header" style="width:100%;padding:5 5 5 5px;">
				<font size=3>
						<div class="container-fluid card">
							<div class="row mx-1 my-1">
								<div class="col-md-2">Project ID: <span class="onco-label">{!!$project->id!!}</span></div>
								<div class="col-md-2">Version: <span class="onco-label">hg{!!$project->version!!}</span></div>
								<div class="col-md-2">Project Group: <span class="onco-label">{!!strtoupper($project->project_group)!!}</span></div>
								<div class="col-md-6">Project Name: <span class="onco-label">{!!$project->name!!}</span></div>
							</div>
							<div class="row mx-1 my-1">
								<div class="col-md-2">Patients: <span class="onco-label">{!!$project_info->patients!!}</span></div>
								<div class="col-md-2">Cases: <span class="onco-label">{!!$project_info->cases!!}</span></div>
								<div class="col-md-2">Samples: <span class="onco-label">{!!$project_info->samples!!}</span></div>
								<div class="col-md-3">Processed Patients: <span class="onco-label">{!!$project_info->processed_patients!!}</span></div>
								<div class="col-md-3">Processed Cases: <span class="onco-label">{!!$project_info->processed_cases!!}</span></div>
							</div>							
							<div class="row mx-1 my-1">								
								<div class="col-md-2">Survival: <span class="onco-label">{!!$project_info->survival!!}</span></div>
								<div class="col-md-2">Exomes: <span class="onco-label">{!!$project_info->exome!!}</span></div>
								<div class="col-md-2">Panels: <span class="onco-label">{!!$project_info->panel!!}</span></div>
								<div class="col-md-3">RNAseq: <span class="onco-label">{!!$project_info->rnaseq!!}</span></div>
								<div class="col-md-3">Whole Genome: <span class="onco-label">{!!$project_info->whole_genome!!}</span></div>
							</div>
							<div class="row mx-1 my-1">
								<div class="col-md-12">Description: <span class="onco-label">{!!$project->description!!}</span></div>
							</div>
							@foreach ($additional_links as $additional_link)
							<div class="row mx-1 my-1">
								<div class="col-md-12">{!!$additional_link->name!!}: 
									<span class="onco-label"><a target=_blank href="{!!$additional_link->url!!}">{!!$additional_link->description!!}</a></span></div>
							</div>
							@endforeach
						</div>
					
				</font>
			</div>
			<table id="project_summary" style="width:100%;padding:5px;border:1px;"></table>
		</div>	
		<div id="Patients" title="Patients" style="width:98%;border:1px">
		</div>
		<div id="Samples" title="Samples" style="height: 100%;width:98%;border:1px">
			<div id='loadingSamples' class='loading_img'>
				<img src='{!!url('/images/ajax-loader.gif')!!}'></img>
			</div>
			<div id="sampleContent" style="font-size: 14px;overflow: auto;display: none;margin:10px 0">				
				Experiment Types: 
				<select id="selExpTypes" class="form-select sampleFilter" style="width:150px;display: inline;">
					<option value="All">All</option>												
				</select>
				&nbsp;
				Library Types: 
				<select id="selLibTypes" class="form-select sampleFilter" style="width:150px;display: inline;">
					<option value="All">All</option>												
				</select>
				&nbsp;
				Tissue Category: 
				<select id="selTissueCats" class="form-select sampleFilter" style="width:150px;display: inline;">
					<option value="All">All</option>												
				</select>
				&nbsp;
				Tissue/Diagnosis: 
				<select id="selTissueTypes" class="form-select sampleFilter" style="width:150px;display: inline;">
					<option value="All">All</option>												
				</select>
				&nbsp;
				<button id="btnDownloadSamples" type="button" class="btn btn-default" style="font-size: 12px;">
					<img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Download</button>
				<span style="font-family: monospace; font-size: 20;float:right;">					
				Samples: <span id="lblSampleCountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="lblSampleCountTotal" style="text-align:left;" text=""></span>
				</span>
				<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblSamples" style='white-space: nowrap;width:100%;overflow:auto'>
				</table>				
			</div>
			
		</div>
		<div id="Cases" title="Cases" style="height: 100%;width:98%;border:1px">
			<div id='loadingCases' class='loading_img'>
				<img src='{!!url('/images/ajax-loader.gif')!!}'></img>
			</div>
			<div id="caseContent" style="font-size: 14px;overflow: auto;display: none;margin:10px 0">
				Versions: 
				<select id="selVersions" class="form-select caseFilter" style="width:150px;display: inline;">
					<option value="All">All</option>												
				</select>
				&nbsp;
				<button id="btnDownloadCases" type="button" class="btn btn-default" style="font-size: 12px;">
					<img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Download</button>
				<span style="font-family: monospace; font-size: 20;float:right;">					
				Cases: <span id="lblCaseCountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="lblCaseCountTotal" style="text-align:left;" text=""></span>
				</span>
				<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblCases" style='white-space: nowrap;width:100%;overflow:auto'>
				</table>				
			</div>
			
		</div>
		@if ($project->hasMutation())
		<div id="Mutations" title="Mutations" style="height:90%;width:100%;padding:10px;">
			<div id="tabMutations" class="easyui-tabs" data-options="tabPosition:'top',plain:true,pill:false" style="width:98%;padding:0px;overflow:visible;border-width:0px">
				@foreach ( $var_count as $type => $cnt)
					@if ($project->showFeature($type) && ($type != "germline" || ($type == "germline" && !Config::get('site.isPublicSite'))))
						@if ($cnt > 0 && $type != "hotspot")
							<div id="{!!Lang::get("messages.$type")!!}" title="{!!Lang::get("messages.$type")!!}" data-options="tools:'#{!!$type!!}_mutation_help'" style="width:98%;padding:0px;">
							</div>
						@endif
					@endif
				@endforeach
				@if ($project->showFeature('mutation_burden'))
					@if ($project->hasBurden())				
					<div id="Mutation_Burden" title="Mutation_Burden" style="width:98%;height:95%;padding:0px;">								
					</div>
					@endif
				@endif
				@if ($project->hasVCF() && !Config::get('site.isPublicSite'))
					<div id="Download" title="Download VCFs" style="width:98%;height:95%;padding:0px;">
						<div style="padding:30px">
						<H4>VCF file:&nbsp;<button id="btnDownloadVCF" class="btn btn-info"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;VCF</button></H4>
						</div>
					</div>
				@endif
			</div>
		</div>
		@endif
	@if ($has_tcell_extrect_data)
			<div id="TIL" title="TIL" style="width:98%;padding:5px;">					
			</div>
	@endif
	@if ($has_hla)
			<div id="HLA" title="HLA" style="width:98%;padding:5px;">
				<div id='loadingHLA' class='loading_img'>
					<img src='{!!url('/images/ajax-loader.gif')!!}'></img>
				</div>
				<span class="btn-group" role="group" id="HLAHighConf">
			  		<input class="btn-check" id="ckHLA" type="checkbox" autocomplete="off"">
					<label id="lblHLA" class="mut btn btn-outline-primary" for="ckHLA">High conf</label>
				</span>
				<button id="btnDownloadHLA" class="btn btn-info"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Download</button>
				<span style="font-family: monospace; font-size: 20;float:right;">					
				HLA: <span id="lblHLACountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="lblHLACountTotal" style="text-align:left;" text=""></span>
				</span>
				<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblHLA" style='width:100%'>
				</table>					
			</div>
	@endif
	@if ($has_str)
			<div id="STR" title="STR" style="width:98%;padding:5px;">
				<div id='loadingSTR' class='loading_img'>
					<img src='{!!url('/images/ajax-loader.gif')!!}'></img>
				</div>
				<button id="btnDownloadSTR" class="btn btn-info"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Download</button>
				<span style="font-family: monospace; font-size: 20;float:right;">					
				STR: <span id="lblSTRCountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="lblSTRCountTotal" style="text-align:left;" text=""></span>
				</span>
				<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblSTR" style='width:100%'>
				</table>					
			</div>
	@endif
	@if ($project->hasMixcr())
			<div id="TCR" title="TCR/BCR" style="width:98%;padding:5px;">
				<div id="tabTCRBCR" class="easyui-tabs" data-options="tabPosition:'top',plain:true,pill:false,border:false,headerWidth:100" style="width:100%;padding:0px;overflow:auto;border-width:0px">
					<div id="Stats" title="TCR Stats"></div>
					<div id="Clones" title="TCR Clones"></div>
					@if (count($project->getMixcrFiles()) > 0)
						<div title="Download">
							<div style="padding:20px">
							<H5>TCR/BCR file:&nbsp;
								<select id="selMixcrFile" class="form-control" style="width:400px;display:inline">
									@foreach ($project->getMixcrFiles() as $mixcr_file)
										<option value="{!!$mixcr_file!!}">{!!$mixcr_file!!}</option>
									@endforeach
								</select>
								<button id="btnDownloadMixcr" class="btn btn-info"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Download</button>
							</H5>
							</div>
						</div>
					@endif
				</div>
			</div>
	@endif
	@if ($project->showFeature('fusion'))	
	@if ($project->getSampleSummary("RNAseq") > 0)
		<div id="fusion_summary" title="Fusions" style="padding:0px;">			
		</div>
	@endif
	@endif
	@if ($project->showFeature('expression'))
	  @if ($project->getExpressionCount() > 0)
		<div id="Expression" title="Expression" style="width:100%;padding:5;">
			<div id="tabExpression" class="easyui-tabs" data-options="tabPosition:'top',plain:true,pill:false,border:false,headerWidth:100" style="width:100%;padding:0px;overflow:visible;border-width:0px">
				<div title="PCA" style="width:100%;padding:5px;">
					<div id='loadingPCA' class='loading_img'>
						<img src='{!!url('/images/ajax-loader.gif')!!}'></img>						
					</div>
					<div class="container-fluid" id="pca_panel" style="display:none;">
						<div class="row">
							<div class="col-md-8">
								<div class="card" style="margin: 5 auto">
									<div class="row mx-1 my-1">
										<div class="col-md-3 h6">
											<label for="selSampleAttr">Group by:</label><select id="selSampleAttr" class="form-select"></select>
										</div>										
										<div class="col-md-3 h6">
											<label for="selTargetType">Annotation:</label>
											<select id="selTargetType" class="form-select pca-control">
												@foreach ($project->getTargetTypes() as $target_type)
													<option value="{!!$target_type!!}">{!!strtoupper($target_type)!!}</option>
												@endforeach
											</select>
										</div>										
										<div class="col-md-2 h6">
											<label for="selValueType">Value type:</label>
											<select id="selValueType" class="form-select pca-control">
												<option value="zscore">Zscore</option>
												<option value="log2">Log2</option>												
											</select>											
										</div>
										<div class="col-md-2 h6">
											<label for="ckShowLabel">Options:</label>
											<div>
											<input id="ckShowLabel" type="checkbox">&nbsp;Show labels</input>
											</div>
										</div>
										<div class="col-md-2 h6">
											Processed: <div>{!!$project->getExpressionProcessingTime()!!}</div>
										</div>

									</div>
									<div id="no_pca_data" style="height: 720px; margin: 0 auto display:none"><H4>No data!</H4></div>
									<div id="pca_plot" style="height: 720px; margin: 0 auto" ></div>
								</div>
							</div>
							<div class="col-md-4">
								<div class="row card">
									<div class="col-md-6">
										<label for="selPC">Component:</label>
										<select id="selPC" class="form-control"></select>
									</div>
								</div>
								<div class="row">
									<div class="card" style="height: 240px; margin: 5 auto; padding: 2px 2px 2px 2px;" id="pca_p_loading_plot"></div>
								</div>
								<div class="row">
									<div class="card" style="height: 240px; margin: 5 auto; padding: 2px 2px 2px 2px;" id="pca_n_loading_plot"></div>
								</div>
								<div class="row">
									<div class="card" style="height: 240px; margin: 5 auto; padding: 2px 2px 2px 2px;" id="pca_var_plot"></div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div id="Heatmap" title="Heatmap" style="width:100%;padding:5px;">
				</div>
				<div title="Download" style="width:100%;padding:5px;">
					<div class="container-fluid" id="pca_panel">
						<div class="row">
							<div class="col-md-2">
								<label for="selDownloadTargetType">Annotation:</label>
								<select id="selDownloadTargetType" class="form-select pca-control">
								@foreach ($project->getTargetTypes() as $target_type)
									<option value="{!!$target_type!!}">{!!strtoupper($target_type)!!}</option>
								@endforeach
								</select>
							</div>
							<div class="col-md-3">
								<label for="selDownalodDataType">Data Type:</label>
								<select id="selDownloadDataType" class="form-select pca-control">
									<option value="count">Raw count</option>
									<option value="tpm">TPM</option>
									<option value="tmm-rpkm">TMM-RPKM (coding genes)</option>
									<option value="sample_meta">Sample metadata</option>
									@if ($has_isoforms)
									<option value="isoforms">Isoforms (RSEM zipped)</option>
									@endif

								</select>
							</div>
							<div class="col-md-3">
								<label for="btnDownloadMatrix">Download:</label><br><button id="btnDownloadMatrix" class="btn btn-info"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Expression file</button>
							</div>
						</div>
					</div>
				</div>
				@if (count($genesets) > 0)
				<div title="ssGSEA" style="width:100%;padding:5px;">
					<label for="selGeneset" class="h6">Geneset:</label>
					<select id="selGeneset" class="form-select gsva" style="width:250px;display:inline">
					@foreach ($genesets as $geneset)
						<option value="{!!$geneset!!}">{!!$geneset!!}</option>
					@endforeach
					</select>
					<label for="selGSVAMethod" class="h6">Method:</label>
					<select id="selGSVAMethod" class="form-select gsva" style="width:250px;display:inline">
					@foreach ($gsva_methods as $gsva_method)
						<option value="{!!$gsva_method!!}">{!!$gsva_method!!}</option>
					@endforeach
					</select>
					<button id="btnDownloadGSVA" class="btn btn-info"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Download</button>
					<span id="loading_gsva" style="display: none;">
						<img src='{!!url('/images/ajax-loader.gif')!!}'></img>
					</span>
					<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblGSVA" style='width:100%'>
					</table>
				</div>
				@endif
			</div>
		</div>		
		@if ($project->showFeature("GSEA"))
		<!--div id="GSEA" title="GSEA" style="width:100%;padding:10px;">
			<object data="{!!url("/viewGSEA/$project->id/any/any/".rand())!!}" type="application/pdf" width="100%" height="100%"></object>
		</div-->
		@endif
	  @endif
	@endif
	@if (count($cnv_files) > 0)
		<div id="CNV" title="CNV" style="width:100%;padding:5;">
			<div id="tabCNV" class="easyui-tabs" data-options="tabPosition:'top',plain:true,pill:false,border:false,headerWidth:100" style="width:100%;padding:0px;overflow:visible;border-width:0px">
				@if ($has_cnv_summary)
				<div title="Summary" style="width:100%;padding:5px;background-color:#f2f2f2">					
					<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblCNVSummary" style='width:100%'>
					</table>					
				</div>
				@endif
				<div title="Download" style="width:100%;padding:5px;background-color:#f2f2f2">					
					<br>
					<div>
						<H5 style="display:inline">File type:</H5>
						<select id="selCNVFile" class="form-control" style="width:250px;display:inline">
						@foreach ($cnv_files as $cnv_file_name => $cnv_file)
							<option value="{!!$cnv_file!!}">{!!$cnv_file_name!!}</option>
						@endforeach
						</select>
						<button id="btnDownloadCNVFile" class="btn btn-info"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Download</button>
					</div>					
					<br>					
				</div>
			</div>
		</div>

	@endif	
	@if ($has_survival)		

		<div title="Survival">
			@if ($project->getExpressionCount() > 0)
			<div id="tabSurvival" class="easyui-tabs" data-options="tabPosition:'top',plain:true,pill:false,border:false,headerWidth:100" style="width:100%;padding:0px;overflow:hidden;border-width:0px;padding:5px">
				<div title="By Metadata/Mutation">
			@endif
					<div id='loadingAllSurvival' style="display: none">
					    <img src='{!!url('/images/ajax-loader.gif')!!}'></img>
					</div>
					<div class="container-fluid" id="survival_panel" style="visibility: false">
						<div class="row" style="padding:5px">
							<div class="col-md-12">
								<!--div class="card mx-1 my-1 px-1 py-1" style="display:inline-block;"-->
									<H5  style="display:inline;font-size: 12px">Filer:&nbsp;</H5>
									<select id="selSurvFilterType1" class="form-select surv" style="display:inline;width:130px;font-size: 12px;padding:2px 2px;">
										<option value="any">All Data</option>
									</select>
									<select id="selSurvFilterValues1" class="form-select surv" style="display:none;width:130px;font-size: 12px;padding:2px 2px;">
									</select>
									<H5 id="lblFilter2" style="display:none;font-size: 12px">&nbsp;And&nbsp;</H5>
									<select id="selSurvFilterType2" class="form-select surv" style="display:none;width:130px;font-size: 12px;padding:2px 2px;">
										<option value="any">All Data</option>
									</select>
									<select id="selSurvFilterValues2" class="form-select surv" style="display:none;width:130px;font-size: 12px;padding:2px 2px;">
									</select>
									</span>
								<!--/div-->
							</div>
						</div>
						<div class="row" style="padding:5px">
							<div class="col-md-12">
								<!--div class="card mx-1 my-1 px-1 py-1" style="display:inline-block;"-->
									<H5  style="display:inline;font-size: 12px">Group by:&nbsp;</H5>
									<input id="radioMeta" name="radioGroupBy" type="radio" class="surv_radio" checked><H5 style="display:inline;font-size: 12px;">&nbsp;Metadata&nbsp;</H5>
									<div id="meta_group" style="display:inline;">
										<select id="selSurvGroupBy1" class="form-select surv" style="display:inline;width:130px;font-size: 12px;padding:2px 2px;">
										</select>
										<H5  style="display:inline;font-size: 12px">And:&nbsp;</H5>
										<select id="selSurvGroupBy2" class="form-select surv" style="display:inline;width:130px;font-size: 12px;padding:2px 2px;">
											<option value="not_used" >Not used</option>
										</select>
									</div>
									&nbsp;&nbsp;&nbsp;
									<input id="radioMut" name="radioGroupBy" type="radio" class="surv_radio"><H5 style="display:inline;font-size: 12px;">&nbsp;Mutation Genes&nbsp;</H5>	
									<span id="mut_group" style="display:none;">									
									<!--input id="gene1" class="fomr-control" style="width:80;font-size: medium;"></input-->
										<select id="selTier" class="form-select surv" style="display:inline;font-size: 12px;width:90px;padding:2px 2px;">
											<option value="tier1" >Tier 1</option>
											<option value="other_tier" >2-4 Tiers</option>
											<!--option value="all_tier" >All Tiers</option-->
										</select>									
										<select id="selTierType" class="form-select surv" style="display:inline;font-size: 12px;width:140px;padding:2px 2px;">
											<option value="somatic" >Somatic Tiering</option>
											<option value="germline" >Germline Tiering</option>
											<option value="germline_somatic" >Germline or Somatic Tiering</option>
										</select>
										<a href='{!!url("data/".\Config::get('onco.classification_germline_file'))!!}' title="Germline tier definitions" class="fancybox mytooltip box"><img src={!!url("images/help.png")!!}></img></a>
										<a href='{!!url("data/".\Config::get('onco.classification_somatic_file'))!!}' title="Somatic tier definitions" class="fancybox mytooltip box"><img src={!!url("images/help.png")!!}></img></a>
										<H5  style="display:inline;font-size: 12px;">&nbsp;&nbsp;&nbsp;Gene1:</H5>
										<select id="selGene1" class="form-select surv" style="display:inline;font-size: 12px;width:100px;padding:2px 2px;">
											@foreach ($tier1_genes as $tier1_gene)
												<option value="{!!$tier1_gene!!}"" >{!!$tier1_gene!!}</option>
											@endforeach
										</select>
										<select id="selMutationRelation" class="form-select surv" style="display:inline;font-size: 11px;width:70px;padding:2px 2px;">
											<option value="and">And</option>
											<option value="or">Or</option>
											<option value="andNot">And Not</option>
										</select><H5  style="display:inline;font-size: 12px;">&nbsp;&nbsp;Gene2: </H5>
										<select id="selGene2" class="form-select surv" style="display:inline;width:100px;font-size: 12px;padding:2px 2px;">
											<option value="any">Any</option>
											@foreach ($tier1_genes as $tier1_gene)
												<option value="{!!$tier1_gene!!}">{!!$tier1_gene!!}</option>
											@endforeach
										</select>
										<!--input id="gene2" class="fomr-control" style="width:80;font-size: medium;"></input-->
										
									</span>
									<input id="radioFusion" name="radioGroupBy" type="radio" class="surv_radio"><H5 style="display:inline;font-size: 12px;">&nbsp;Fusion&nbsp;</H5>
									<div id="fusion_group" style="display:none;">
										<select id="selFusionPairs" class="form-select surv" style="display:inline;width:130px;font-size: 12px;padding:2px 2px;">
											@foreach ($fusion_genes as $fusion_gene)
												<option value="{!!$fusion_gene!!}"" >{!!$fusion_gene!!}</option>
											@endforeach
										</select>
									</div>								
									&nbsp;&nbsp;
									<button id='btnPlotSurvival' class="btn btn-info">Submit</button>
								<!--/div-->
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<div id="message_row" style="display:none">
									<H3>No data!</H3>
								</div>
							</div>
						</div>
						<div id="plot_row">
							<div class="row">							
								<div id="overall_col" class="col-md-6" >
									<div class="card" style="display:inline-block;">
										<div id='overall_survival_plot' style="height:450;width=100%"></div>
									</div>								
								</div>
								<div  id="event_free_col" class="col-md-6">
									<div class="card" style="display:inline-block;">							
										<div id='event_free_survival_plot' style="height:450;width=100%"></div>
									</div>
								</div>
							</div>
						</div>		

				</div>
			@if ($project->getExpressionCount() > 0)
				</div>
				@if ($project->showFeature('expression'))
				<div id="ByExpression" title="By Expression"></div>
				@endif
			</div>

			@endif
		</div>
	@endif
	@foreach ($additional_tabs as $additional_tab)
	<div id="{!!$additional_tab->name!!}" title="{!!$additional_tab->name!!}" style="width:100%;border:1px">
		<a target=_blank href="{!!$additional_tab->url!!}">{!!$additional_tab->name!!}</a>
	</div>
	@endforeach
	@if ($project->showFeature("qc"))
		@if ($project->hasMutation())
		<div id="QC" title="QC" style="width:100%;border:1px">
		</div>
		@endif	
	@endif	
	@if (App\Models\User::isProjectManager() || App\Models\User::isSuperAdmin())
	<div id="Users" title="Users" style="width:100%;border:1px;;padding:0px 20px 0px 20px">
		<H5>Total users in {!!$project->name!!}: <lable id="lblTotalUsers"></lable></H5><HR>
		<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblUsers" style='width:100%'>
		</table>
	</div>
	@endif
</div>

@foreach ( $var_count as $type => $cnt)
<div id="{!!$type!!}_mutation_help" style="display:none">
    <img class="mytooltip" title="{!!Lang::get("messages.$type"."_message")!!}" width=12 height=12 src={!!url("images/help.png")!!}></img>
</div>
@endforeach

@stop
