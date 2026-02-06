@extends(($with_header)? 'layouts.default' : 'layouts.noheader')
@section('title', "Mutation Annotation--Patient:$patient_id--$case_id--Gene:$gene_id")
@section('content')

{!! HTML::style('packages/w2ui/w2ui-1.4.min.css') !!}
{!! HTML::script('js/jquery-3.6.0.min.js') !!}
@if ($with_header)
	{!! HTML::script('js/jquery-3.6.0.min.js') !!}
@else
	{!! HTML::style('css/bootstrap.min.css') !!}
@endif
{!! HTML::style('css/style.css') !!}
{!! HTML::style('css/style_datatable.css') !!}

{!! HTML::style('packages/jquery-easyui/themes/icon.css') !!}
{!! HTML::style('packages/jquery-easyui/themes/default/easyui.css') !!}
{!! HTML::style('packages/fancyBox/source/jquery.fancybox.css') !!}
{!! HTML::style('packages/muts-needle-plot/build/muts-needle-plot.css') !!}
{!! HTML::style('packages/bootstrap-switch-master/dist/css/bootstrap3/bootstrap-switch.css') !!}
{!! HTML::style('css/filter.css') !!}
{!! HTML::style('packages/tooltipster-master/dist/css/tooltipster.bundle.min.css') !!}

{!! HTML::script('packages/DataTables/datatables.min.js') !!}
{!! HTML::script('js/bootstrap.bundle.min.js') !!}
{!! HTML::script('js/togglebutton.js') !!}
{!! HTML::script('packages/jquery-easyui/jquery.easyui.min.js') !!}
{!! HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') !!}
{!! HTML::script('packages/tooltipster-master/dist/js/tooltipster.bundle.min.js') !!}
{!! HTML::script('packages/bootstrap-switch-master/dist/js/bootstrap-switch.js') !!}
{!! HTML::script('packages/w2ui/w2ui-1.4.min.js')!!}
{!! HTML::script('js/filter.js') !!}
{!! HTML::script('js/onco.js') !!}
{!! HTML::script('js/FileSaver.js') !!}

<title>{!!($patient_id == 'null')? "" : "$patient_id-"!!}{!!$type!!}</title>
   
<meta name="csrf-token" content="{{ csrf_token() }}">

<style>

.desc {
  white-space: nowrap; 
  width: auto; 
  overflow: hidden;
  text-overflow: ellipsis;   
}

.card label {
	font-size: 10px;
}
div.toolbar {
	display:inline;
}

.btn {
	padding: 6px 10px;
	font-size: 11px;
}

.btn-default:focus,
.btn-default:active,
.btn-default.active {
    background-color: DarkCyan;
    border-color: #000000;
    color: #fff;
}
.btn-default.active:hover {
	background-color: Cyan;
}

.col-md-12 {
	padding: 5px 5px 5px 5px;
	margin: 0;
}

.progress {
    position: relative;
    margin-bottom : 0px;
}

.progress span {
    position: absolute;
    display: block;
    width: 100%;
    color: black;    
 }

 td.details-cohort {
    text-align: center;
    cursor: pointer;
}

.circle-green{	
	width: 20px;
	height: 20px;
	-webkit-border-radius: 10px;
	-moz-border-radius: 10px;
	border-radius: 10px;
	background: green;
}

.circle-yellow{	
	width: 20px;
	height: 20px;
	-webkit-border-radius: 10px;
	-moz-border-radius: 10px;
	border-radius: 10px;
	background: yellow;
}

.circle-red{	
	width: 20px;
	height: 20px;
	-webkit-border-radius: 10px;
	-moz-border-radius: 10px;
	border-radius: 10px;
	background: red;
}

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

p.pvs{
border-style: solid;
border-color: red;
padding: 8px;
}
p.pm{
border-style: solid;
border-color: orange;
padding: 8px;
}
p.pp{
border-style: solid;
border-color: green;
padding: 8px;
}
p.bp{
border-style: solid;
border-color: blue;
padding: 8px;
}
p.bs{
border-style: solid;
border-color: #CC00CC;
padding: 8px;
}
p.art{
border-style: solid;
border-color: black;
padding: 8px;
}

.popover{
    max-width: 80%; /* Max Width of the popover (depending on the container!) */
}

</style>
    
<script type="text/javascript">
	var tbl;
	var tblVarDetail;
	var tblCommentHistory;
	var tblSignoutHistory;
	var tblACMGHistory;
	var acmgguide_idx = -1;
	var signout_idx = -1;
	var patient_id = '{!!$patient_id!!}';
	var sample_id = '{!!$sample_id!!}';
	var case_id = '{!!$case_id!!}';
	var gene_id = '{!!$gene_id!!}';
	var type = '{!!$type!!}';
	var show_columns = {!!$show_columns!!};
	//console.log('{!!$show_columns!!}');

	@if ($gene_id != 'null')
	var attr_values = {!!json_encode($meta)!!};
	var attr_keys = {!!json_encode(array_keys($meta))!!};
	var meta_type = '{!!$meta_type!!}';
	var meta_value = '{!!$meta_value!!}';
	@endif

	var can_signout = false;
	var show_signout = (sample_id != 'null' && type != 'rnaseq' && type != 'hotspot');
	var can_reopen = false;
	var is_signout_manager = false;
	@if (\App\Models\User::isSignoutManager())
		is_signout_manager = true;
	@endif
	if (show_signout && is_signout_manager ) {
		can_signout = true;
		can_reopen = true;
	}

	var flag_var = null;
	var expanded = false;
	var exp_plot;
	var json_data;
	var mutsPlot;
	var needleSampleData;
	var plotConfig;
	var max_filter = 6;	
	
	var col_html = '';
	var columns = [];
	var tbl_columns = {};
	var onco_filter;
	var checking_high_conf = false;
	var var_list = {!!$var_list!!};
	var_list = unique_array(var_list);
	var status = '{!!$status!!}';
	var signedout_list = [];
	signedout_list = var_list.slice();

	var all_patients = [];
	var filtered_patients = [];
	var diagnosis = "{!!isset($diagnosis)? $diagnosis : 'null'!!}";
	//diagnosis = "Melanoma";
	var diagnosis_list = [];
	var aa_pos_list = [];
	var aa_poses;	
	var first_loading = true;	
	var high_conf_settings = [];
	var high_conf_setting = null;
	@foreach ( \App\Models\UserSetting::getHighConfSetting() as $config_name => $high_conf)
		console.log('{!!$config_name!!}');
		high_conf_settings['{!!$config_name!!}'] = {!!json_encode($high_conf)!!};
		if (high_conf_setting == null)
			high_conf_setting = {!!json_encode($high_conf)!!};
	@endforeach;
	//{!!json_encode(\App\Models\UserSetting::getSetting("high_conf", true, true))!!};

	var filter_list = null;
	var filter_settings = [];
	var default_settings = {!!json_encode(Config::get("onco.page.$type"))!!}
	@if (property_exists($setting, "filters"))
		filter_settings = {!!$setting->filters!!};
	@endif 
	var launched = false;
	//var rows_selected = [];
	var hotspot_site_idx = -1;
	var patient_id_idx = -1;
	var freq_idx = -1;
	var vaf_idx = -1;
	var aachange_idx = -1;
	var caller_idx = -1;
	var fisher_score_idx = -1;
	var exp_type_idx = -1;
	var normal_cov_idx = -1;
	var meta_idx = -1;
	var aapos_idx = -1;
	var germline_level_idx = -1;
	var somatic_level_idx = -1;	
	var in_exome_idx = -1;
	var total_cov_idx = -1;
	var gene_id_idx = -1;
	var in_germline_somatic_idx = -1;
	var filter_idx = -1;
	var pause_filtering = false;
	var select_all = false;
	var qci_actionability_idx = -1;
	var disclaimer = "You are going to a non-federal website which has it’s own privacy policy";

	$.ajaxSetup({
	    headers: {
	        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
	    }
	});

	$(document).ready(function() {
		var url = '{!!url("/getVarAnnotation/$project_id/$patient_id/$sample_id/$case_id/$type")!!}';
		if (gene_id != 'null')
			url = '{!!url("/getVarAnnotationByGene/$project_id/$gene_id/$type/$cancer_type")!!}';
		@if (isset($file_name))
			url = '{!!url("/getVarUploadAnnotation/$file_name/$type")!!}';
		@endif		
		console.log(url);
		high_conf_setting = high_conf_settings[$('#selHighConf').val()];	        	
		//w2popup.open({body: "<img src='{!!url('/images/ajax-loader.gif')!!}'></img><H3>Loading...</H3>", height: 200});
		$.ajax({ url: url, async: true, dataType: 'text', success: function(d) {
				$("#loadingVar").css("display","none");
				//w2popup.close();				
				json_data = parseJSON(d);
				//console.log("got data");
				//console.log(json_data.cols);

				//console.log(json_data)
				if (json_data.data.length == 0) {
					$("#lblMessage").text("No data!");
					return;					
				}
				else 
					$("#var_content").css("display","block");				

				if (gene_id != 'null') {
					//disable for now					
					needleSampleData = json_data.var_plot_data.sample_data;
					//console.log(JSON.stringify(json_data.var_plot_data.sample_data));					
					$("#plot_area").css("display","block");
				}				
				setVisibleColumn();
				showTable(json_data);
				gene_id_idx = columns.indexOf('{!!Lang::get("messages.gene")!!}');				
				freq_idx = columns.indexOf('{!!Lang::get("messages.frequency")!!}');				
				vaf_idx = columns.indexOf('{!!Lang::get("messages.vaf")!!}');
				total_cov_idx = columns.indexOf('{!!Lang::get("messages.total_cov")!!}');
				// console.log(total_cov_idx);
				in_exome_idx = columns.indexOf('{!!Lang::get("messages.in_exome")!!}');
				caller_idx = columns.indexOf('{!!Lang::get("messages.caller")!!}');
				fisher_score_idx = columns.indexOf('{!!Lang::get("messages.fisher_score")!!}');
				exp_type_idx = columns.indexOf('{!!Lang::get("messages.exp_type")!!}');
				normal_cov_idx = columns.indexOf('{!!Lang::get("messages.normal_total_cov")!!}');
				//diag_idx = columns.indexOf('{!!Lang::get("messages.diagnosis")!!}');
				hotspot_site_idx = columns.indexOf('{!!Lang::get("messages.actionable_hotspots")!!}');
				patient_id_idx = columns.indexOf('{!!Lang::get("messages.patient_id")!!}');
				aachange_idx = columns.indexOf('{!!Lang::get("messages.aachange")!!}');
				aapos_idx = columns.indexOf('{!!Lang::get("messages.aapos")!!}');
				//console.log(JSON.stringify(columns));
				germline_level_idx = columns.indexOf('{!!Lang::get("messages.germline_level")!!}');			
				somatic_level_idx = columns.indexOf('{!!Lang::get("messages.somatic_level")!!}');
				signout_idx = columns.indexOf('Signout');
				matched_total_idx = columns.indexOf('{!!Lang::get("messages.matched_total_cov")!!}');
				matched_var_idx = columns.indexOf('{!!Lang::get("messages.matched_var_cov")!!}');
				in_germline_somatic_idx = columns.indexOf('{!!Lang::get("messages.in_germline_somatic")!!}');
				//console.log(germline_level_idx + ' ' + somatic_level_idx);
				var clinvar_idx = columns.indexOf('{!!Lang::get("messages.clinvar_clisig")!!}');
				var hgmd_idx = columns.indexOf('{!!Lang::get("messages.hgmd_cat")!!}');
				var acmg_idx = columns.indexOf('{!!Lang::get("messages.acmg")!!}');
				var hotspot_gene_idx = columns.indexOf('{!!Lang::get("messages.hotspot_genes")!!}');				
				var prediction_sites_idx = columns.indexOf('{!!Lang::get("messages.prediction_hotspots")!!}');
				var loss_of_function_idx = columns.indexOf('{!!Lang::get("messages.loss_func")!!}');
				filter_idx = germline_level_idx + 2;
				if (gene_id != 'null') {
					filter_idx = germline_level_idx + attr_keys.length + 2;					
				}
				//console.log("filter" + filter_idx);
				filter_list = {'Clinvar Pathogenic':clinvar_idx, 'HGMD Pathogenic':hgmd_idx, 'ACMG':acmg_idx, 'Hotspot genes':hotspot_gene_idx, 'Actionable Sites':hotspot_site_idx, 'Predicted Sites':prediction_sites_idx, 'Loss of function': loss_of_function_idx};
				for (var i=filter_idx;i<json_data.cols.length;i++) {
					if (json_data.cols[i].title == 'Fp')
						break;
					filter_list[json_data.cols[i].title] = i;
				}
				qci_actionability_idx = columns.indexOf('{!!Lang::get("messages.qci_actionability")!!}');
				if (qci_actionability_idx != -1) {
					$('#QCIfilter').css("display","inline");
				}
				onco_filter = new OncoFilter(Object.keys(filter_list), filter_settings, function() {doFilter();});

				if (gene_id != 'null')
					setAttrValue(meta_type, meta_value);

				$('#freq_max').numberbox({
    				min:0,
    				max:100,
    				precision:20,
    				formatter:function(v){    					    					
    					var f = parseFloat(v.toString());
    					if (isNaN(f))
    						f = 1;
    					return f.toString();
					}
				});

				$('#vaf_min').numberbox({
    				min:0,
    				max:1,
    				precision:20,
    				formatter:function(v){ 
    					var f = parseFloat(v.toString());
    					if (isNaN(f))
    						f = 0;   					    					
    					return f.toString();
					}
				});

				$('#total_cov_min').numberbox({
    				min:0,
    				max:10000,
    				precision:0,
    				formatter:function(v){ 
    					var f = parseFloat(v.toString());
    					if (isNaN(f))
    						f = 0;   					    					
    					return f.toString();
					}
				});

				$('#matched_var_cov_min').numberbox({
    				min:0,
    				max:10000,
    				precision:0
				});

				$('#matched_total_cov_min').numberbox({
    				min:0,
    				max:10000,
    				precision:0
				});


				$('.num_filter').numberbox({onChange : function () {
						if (!first_loading) {
							console.log("do filtering...");
							doFilter();
						}
					}
				});

				$('.ckMut').on('change', function() {
					doFilter();
		        });
				
				$('.filter').on('change', function() {
					/*
					if (!$('#ckTier1').is(":checked") || !$('#ckTier2').is(":checked") || !$('#ckTier3').is(":checked") || !$('#ckTier4').is(":checked"))
						$('#ckTierAll').prop('checked', false); */
					doFilter();
		        });

				$('#grpRNAMutation').on('change', function() {
					//if ($('#ckRNAMutation').is(":checked")) {
		        	//	$('#grpRNAMutation').addClass('active');		        		
		        	//}
					doFilter();
				});

				$('#grpDNAMutation').on('change', function() {
					//if ($('#ckRNAMutation').is(":checked")) {
		        	//	$('#grpRNAMutation').addClass('active');		        		
		        	//}
					doFilter();
				});

				$('#tiers').on('change', function() {
					/*
					if (!$('#ckTier1').is(":checked") || !$('#ckTier2').is(":checked") || !$('#ckTier3').is(":checked") || !$('#ckTier4').is(":checked") || !$('#ckNoTier').is(":checked")) {
						$('#btnTierAll').removeClass('active');
						$('#ckTierAll').prop('checked', false);
					}*/
					doFilter();
		        });

				$('#QCItiers').on('change', function() {
					doFilter();
				});

		        


		        $('#tier_all').on('change', function() {	
		        	if ($('#ckTierAll').is(":checked")) {
		        		$('.tier_filter').addClass('active');
		        		$('.ckTier').prop('checked', true);		        		
		        	}
					doFilter();
		        });

		        $('.filter_btn').on('change', function() {	
		        	doFilter();
		        });
				
				$('#btnSignedOut').on('change', function() {
					if ($('#ckSignedOut').is(":checked")) {
						showAll();
						$('#ckSignedOut').prop("checked", true);
						showSignout();
					} else {
						hideSignout();
					}
					doFilter();
					$('#lblSignoutCount').text(var_list.length);
				});

				$('.highConf').on('change', function() {
					console.log("clicked");
					checking_high_conf = true;
					if ($('#ckHighConf').is(":checked")) {
						showAll();
						$('#ckHighConf').prop("checked", true);						
						pause_filtering = true;
						showSignout();
						high_conf_setting = high_conf_settings[$('#selHighConf').val()];
						$('#freq_max').numberbox("setValue", high_conf_setting.maf);
						@if ($type == "germline")
							$('#total_cov_min').numberbox("setValue", high_conf_setting.germline_total_cov);
							$('#vaf_min').numberbox("setValue", high_conf_setting.germline_vaf);
			        	@endif
			        	console.log("set high conf {!!$type!!}");
			        	@if ($type == "somatic" || $type == "variants")
			        		@if ($exp_type == "Exome")
								$('#total_cov_min').numberbox("setValue", high_conf_setting.somatic_exome_total_cov);
								$('#vaf_min').numberbox("setValue", high_conf_setting.somatic_exome_vaf);
			        		@endif
			        		@if ($exp_type == "Panel")
								$('#total_cov_min').numberbox("setValue", high_conf_setting.somatic_panel_total_cov);
								$('#vaf_min').numberbox("setValue", high_conf_setting.somatic_panel_vaf);
			        		@endif
			        	@endif
			        	pause_filtering = false;
			        	//if ($(this).attr('id') == "btnHighConf")
			        	//	$('#btnHighConf').addClass('active');     	
			        } else {
			        	hideSignout();
			        }			        
			        //if (status != 'closed')
			        //if ($('#ckSelectSignoutAll').is(":checked"))
			        //	var_list = [];
		        	doFilter();
		        	$('#lblSignoutCount').text(var_list.length);
		        	checking_high_conf = false;	
		        });

		        $('#matched').on('change', function() {	
		        	if ($('#ckMatched').is(":checked")) 
		        		$("#matchedCovFilter").css("display","inline");
		        	else
		        		$("#matchedCovFilter").css("display","none"); 
					doFilter();
		        });

		        $('#inExome').on('change', function() {
		        	doFilter();
		        });

		        $('#notInGermlineSomatic').on('change', function() {
		        	doFilter();
		        });
		        

				$('#btnClearFilter').on('click', function() {
					//$('#btnHighConf').removeClass('active');
					$('#ckHighConf').prop('checked', false);
					//$('#btnSignedOut').removeClass('active');
					$('#ckSignedOut').prop('checked', false);
					hideSignout();
					showAll();
					doFilter();					
				});

				$('#btnResetFilter').on('click', function() {
					$('#freq_max').numberbox("setValue", default_settings.maf);
					$('#total_cov_min').numberbox("setValue", default_settings.total_cov);
					$('#vaf_min').numberbox("setValue", default_settings.vaf);
					onco_filter.clearFilter();
				});
				
		       	$('#selMeta').on('change', function() {
		       		var value = $('#selMeta').val();
		       		setAttrValue(value);		       		
		       		console.log("meta idx: " + meta_idx);
		       		doFilter();					
		        });

		        $('#selMetaValue').on('change', function() {
		       		doFilter();					
		        });

		       	$('#selAAPos').on('change', function() {					
					doFilter();
		       	});

		       	$('#selPatients').on('change', function() {
					patient_id = $('#selPatients').val();
					//diagnosis = "any";
					//$('#selDiagnosis').val(diagnosis);					
					doFilter();
		       	});						


				applySetting();
				doFilter();				
				//$('.mytooltip').tooltipster();			
			}
		});

		$('body').on('change', 'input#data_column', function() {             
			//refresh col_html which is read everytime when "select column" clicked
			col_html = '<table>';
			var sorted = columns.slice(0).sort();
			var col_idx = 0;
			for (i = 0; i < sorted.length; i++) {
				var index = tbl_columns[sorted[i]];
				//if (index >= filter_idx)
				//	continue;
				if (col_idx % 4 == 0) {
					if (col_html != "<table>")
						col_html += "</tr>";
					col_html += "<tr>";
				}
				col_idx++;
				if (index == $(this).attr("value"))
					checked = ($(this).is(":checked"))?'checked' : '';
				else
					checked = (tbl.column(index).visible())?'checked' : '';
				//if (index < filter_idx)
					col_html += '<td><input type=checkbox ' + checked + ' class="onco_checkbox" id="data_column" value=' + index + '><font size=3>&nbsp;' + sorted[i] + '</font></input></td>';				
			}
			col_html += '</tr></table>';
			//set visible
			tbl.column($(this).attr("value")).visible($(this).is(":checked"));
			if ($(this).is(":checked"))
				show_columns.push(columns[$(this).attr("value")]);
			else
				removeElement(show_columns, columns[$(this).attr("value")]);
			uploadColumnSetting();
			
		});
		
		$('#selFilter0').on('change', function() {
			doFilter();

       	});		

		$('#btnAddFilter').on('click', function() {						
			onco_filter.addFilter();			
        });

		$('.getDetail').on('change', function() {
			
        	});

		$('#btnGene').on('click', function() {
			window.location.replace("{!!url('/viewVarAnnotationByGene')!!}" + "/" + $('#gene_id').val());
        	});

		$('#lblCaseStatus').text(status);

		$('#btnLocus').on('click', function() {
			if (!isInt($('#start_pos').val())) {
				alert('The start position is not a number!');
				$('#start_pos').focus();
				return;
			}
			if (!isInt($('#end_pos').val())) {
				alert('The end position is not a number!');
				$('#end_pos').focus();
				return;
			}
			window.location.replace("{!!url('/viewVarAnnotationByLocus')!!}" + "/" + $('#selChr').val() + "/" + $('#start_pos').val() + "/" + $('#end_pos').val());
        });

		$('#btnPatient').on('click', function() {
			window.location.replace("{!!url('/viewVarAnnotation')!!}" + "/" + $('#selPatients').val() + "/null");
        });
			

		$('#btnP1Signout').on('click', function() {
			doSignout('Phase 1', 'do phase 1 signout');
		});

		$('#btnP2Signout').on('click', function() {
			doSignout('Closed', 'close');
		});

		$('#btnReopen').on('click', function() {
			doSignout('Reopened', 'reopen');
		});

		$('#btnSignoutHistroy').on('click', function() {
			$("#loadingSignoutHistory").css("display","block");
			$("#signout_history").css("display","none");
			url = '{!!url('/getSignoutHistory')!!}' + '/' + patient_id + '/' + sample_id + '/' + case_id + '/' + type;
			$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
					table_data = parseJSON(data);
					$('#pop_signout_history').w2popup();
					$("#w2ui-popup").css("top","20px");	
					if (tblSignoutHistory != null) {
						tblSignoutHistory.destroy();
						$('#w2ui-popup #tblSignoutHistory').empty();
					}
					if (table_data.cols.length == 0)
						table_data = {cols:[{'title':'No data found'}],data:[['.']]};			
					
					tblSignoutHistory = $('#w2ui-popup #tblSignoutHistory').DataTable( 
								{				
									"processing": true,
									"paging":   false,
									"ordering": true,
									"order" : [1, 'desc'],
									"info":     false,
									"data": table_data.data,
									"columns": table_data.cols,									
								} );
					if (status.toLowerCase() == "closed" || !can_signout)
						tblSignoutHistory.column(5).visible(false);
					$("#w2ui-popup #loadingSignoutHistory").css("display","none");
					$("#w2ui-popup #signout_history").css("display","block");
					
									
				}				
			});
		});			


		$('#btnSignoutOld').on('click', function() {			
			var url = '{!!url('/signOutCase')!!}' + '/' + '{!!$patient_id!!}' + '/' + '{!!$case_id!!}' + '/' + '{!!$type!!}';
			$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
					console.log(data);
					if (data == "NoUserID")
						alert("Please login first!");
					else if (data == "Success") {
						$('#lblCaseStatus').text('closed');
						$('#btnSignout').css("display","none");
						alert("Save successful!");						
					}
					else
						alert("Save failed: reason:" + data);
				}, error: function(xhr, textStatus, errorThrown){
					console.log('save failed! Reason:' + JSON.stringify(xhr) + ' ' + errorThrown);
					alert('save failed! Reason:' + JSON.stringify(xhr) + ' ' + errorThrown);
				}
			});		
		});

		$('#btnDownload').on('click', function() {
			var data = tbl.rows( { filter : 'applied'} ).data();
			var var_ids = [];
			for (var i in data) {
				var d = data[i];
				var id = d[d.length-1];
				if (id != undefined) {
					if (typeof(id) == "string")
						var_ids.push(id);					
				}
			}
			var var_list = var_ids.join(',');
			//console.log(var_list);
			$("#var_list").val(var_list);
			$("#downloadHiddenform").submit();			
		});

		$('#btnDownloadGet').on('click', function() {
			var url = '{!!url('/getVarActionable')!!}' + '/' + '{!!$patient_id!!}' + '/' + '{!!$case_id!!}' + '/' + '{!!$type!!}' + '/N';
			window.location.replace(url);	
		});

		$('#btnDownloadFlag').on('click', function() {			
			var url = '{!!url('/getVarActionable')!!}' + '/' + '{!!$patient_id!!}' + '/' + '{!!$case_id!!}' + '/' + '{!!$type!!}' + '/Y';
			window.location.replace(url);	
		});		

		$('#gene_id').keyup(function(e){
			if(e.keyCode == 13) {
        		$('#btnGene').trigger("click");
    		}
		});		

		tblDetail = $('#tblDetail').DataTable( 
		{
			"processing": true,
			"paging":   false,
			"ordering": false,
			"info":     false,
			"searchHighlight": true,
			"columns": [{"title":"Key"},{"title":"Value"}],
			"language" : {
				"processing": "<img src='{!!url('/images/ajax-loader.gif')!!}'></img>"
			}			
		} );		
		
		$('.acmg_definition').fancybox({
    		fitToView: false,
    		beforeShow: function () {
		        this.width = 500;
				this.height = 680;
			}
		});

		
		$("#various2").fancybox({
			'modal' : true
		});

		$(".option-heading").click(function(){
			$(this).find(".arrow-up, .arrow-down").toggle();
		});

		$(".collapse").on('shown.bs.collapse', function(){
        		$('#tabDetails').tabs('select', 2);
		});

		$(".ckPVS").on('chang', function(){
        	alert('change!');
		});		


	});	

	function setAttrValue(attr_type, attr_value=null) {
		$('#selMetaValue').empty();
		console.log(attr_type, attr_value);
		if (attr_type == "any" || attr_type == "null") {
			meta_idx = -1;
			$('#selMetaValue').css("display","none");								
		}
		else {
			meta_idx = columns.indexOf(attr_type);
			$('#selMetaValue').css("display","inline");
			meta_values = attr_values[attr_type];
			meta_values.forEach(function(attr){
				if (attr_value == null || attr_value != attr)
					$('#selMetaValue').append('<option value="' + attr + '">' + attr + '</option>');
				else
					$('#selMetaValue').append('<option value="' + attr + '" selected>' + attr + '</option>');
			});
		}

	}

	function setVisibleColumn() {
		removeElement(show_columns, '{!!Lang::get("messages.acmg_guide")!!}');
		removeElement(show_columns, '{!!Lang::get("messages.somatic_level")!!}');
		removeElement(show_columns, '{!!Lang::get("messages.diagnosis")!!}');
		removeElement(show_columns, '{!!Lang::get("messages.patient_id")!!}');
		removeElement(show_columns, '{!!Lang::get("messages.case_id")!!}');
		//removeElement(show_columns, '{!!Lang::get("messages.qci_nooactionability")!!}');
		show_columns.push('{!!Lang::get("messages.qci_assessment")!!}');
		show_columns.push('{!!Lang::get("messages.qci_actionability")!!}');
		show_columns.push('Open cravat');
		if (type == "germline") {
			show_columns.push('{!!Lang::get("messages.acmg_guide")!!}');
			show_columns.push('{!!Lang::get("messages.germline_level")!!}');			
		} else if (type == "somatic") {
			show_columns.push('{!!Lang::get("messages.somatic_level")!!}');
		} else {
			show_columns.push('{!!Lang::get("messages.germline_level")!!}');
			show_columns.push('{!!Lang::get("messages.somatic_level")!!}');
		}

		if (gene_id != 'null') {
			show_columns.push('{!!Lang::get("messages.diagnosis")!!}');
			show_columns.push('{!!Lang::get("messages.patient_id")!!}');
			show_columns.push('{!!Lang::get("messages.case_id")!!}');
		}
		@if (isset($file_name))
		removeElement(show_columns, '{!!Lang::get("messages.cohort")!!}');
		removeElement(show_columns, 'Site cohort');
		removeElement(show_columns, '{!!Lang::get("messages.view_igv")!!}');
		removeElement(show_columns, 'Libraries');
		@endif
		show_columns = unique_array(show_columns);
	}

	function retrieveVar(patient_id, sample_id, case_id, type, signout_time) {
		var url = '{!!url('/getSignoutVars')!!}' + '/' + patient_id + '/' + sample_id + '/' + case_id + '/' + type + '/' + signout_time;
		console.log(url);
	}

	function checkoutVar(var_list_str) {
		//var url = '{!!url('/getSignoutVars')!!}' + '/' + patient_id + '/' + sample_id + '/' + case_id + '/' + type + '/' + signout_time;
		var old_var_list = var_list_str.split(',');
		w2confirm('<H4>Are you sure you want to load &nbsp;<font color="red">' + (old_var_list.length) + '</font>&nbsp;signed out variants?</H4>')
   			.yes(function () {
   				$('#ckSelectSignoutAll').prop('checked', false);
				var_list = old_var_list;
				signedout_list = var_list.slice();
				showSignout();
				doFilter();
				$('#lblSignoutCount').text(var_list.length);
				w2popup.close();
				w2alert("<H4>Loaded successful!</H4>");				
			});
	}

	function doSignout(new_status, msg1, msg2="Singout out") {
		var msg1 = "signout";
		var msg2 = "Signing out";
		if (new_status.toLowerCase() == "closed") {
			msg1 = "close";
			msg2 = "Closing";
		}
		if (new_status.toLowerCase() == "reopened") {
			msg1 = "reopen";
			msg2 = "Reopening";
		}
		if (var_list.length == 0) {
			w2alert("<H4>No variants selected!</H4>");
			return;
		}
		w2confirm('<H4>Are you sure you want to ' + msg1 + '&nbsp;<font color="red">' + (var_list.length) + '</font>&nbsp;variants for for sample <font color="red">' + sample_id + '</font>?</H4>')
   			.yes(function () {
        			var json_data = {"patient_id" : patient_id, "sample_id" : sample_id, "case_id" : case_id, "type" : type, 'var_list': var_list.join(','), "status" : new_status};	
					var url = '{!!url("/signOut")!!}';

					w2popup.open({body: "<img src='{!!url('/images/ajax-loader.gif')!!}'></img><H3>" + msg2 + "...</H3>", height: 200});
					$.ajax({ url: url, async: true, type: 'POST', dataType: 'text', data: json_data, success: function(data) {
							w2popup.close();
							if (data == "NoUserID")
								w2alert("<H4>Please login first!</H4>");
							else if (data == "Success") {
								status = new_status;
								signedout_list = var_list.slice();
								showSignout();
								tbl.draw();
								w2alert("<H4>Save successful!</H4>");
								$("#btnSignedOut").css("display","inline");
							}
							else
								w2alert("<H4>Save failed: reason:" + data + '/<H4>');							
						}			
			});
		})
	}

	function showSignout() {
		$('#lblSignoutDesc').text("Signed out");
		if (can_signout) {
			$('#ckSelectSignoutAll').prop('disabled', status.toLowerCase() == 'closed');			
			if (status.toLowerCase() == 'active') {
				$("#btnP1Signout").css("display","inline");
				$("#btnP2Signout").css("display","none");
				$("#btnReopen").css("display","none");
				$('#lblSignoutDesc').text("To be signed out");
			}
			if (status.toLowerCase() == 'phase 1' || status.toLowerCase() == 'reopened') {
				$("#btnP1Signout").css("display","inline");
				$("#btnP2Signout").css("display","inline");
				$("#btnReopen").css("display","none");
				$('#lblSignoutDesc').text("To be signed out");				
			}
			if (status.toLowerCase() == 'closed') {				
				$('#btnSignoutHistroy').css("display","inline");
				$("#btnP1Signout").css("display","none");
				$("#btnP2Signout").css("display","none");
				if (can_reopen)
					$("#btnReopen").css("display","inline");
			}
		} else {
			$("#btnP1Signout").css("display","none");
			$("#btnP2Signout").css("display","none");
			$("#btnReopen").css("display","none");
			$('#btnSignoutHistroy').css("display","none");
		}
		if (status.toLowerCase() != 'active')
			$('#btnSignoutHistroy').css("display","inline");
		else
			$('#btnSignoutHistroy').css("display","none");
		$("#signoutCount").css("display","inline");
		$("#signout_label").css("display","inline");
		$('#lblCaseStatus').text(status);				
		tbl.column(signout_idx).visible(true);
	}

	function hideSignout() {
		if (show_signout) {
			$("#btnP1Signout").css("display","none");
			$("#btnP2Signout").css("display","none");
			$("#signoutCount").css("display","none");
			$("#signout_label").css("display","none");
			$('#btnSignoutHistroy').css("display","none");
			//tbl.column(signout_idx).visible(false);
			//console.log("status:" + status);
			if (can_signout && status.toLowerCase() == 'closed')
				var_list = [];
		}
	}

	function showAll() {
		pause_filtering = true;
		$('#freq_max').numberbox("setValue", 1);
		$('#total_cov_min').numberbox("setValue", 0);
		$('#vaf_min').numberbox("setValue", 0);
		//$('#btnTierAll').addClass('active');
		$('#ckTierAll').prop('checked', true);		
		//$('.tier_filter').addClass('active');
		$('.ckTier').prop('checked', true);
		$('.ckMut').prop('checked', false);
		$('#ckMatched').prop('checked', false);
		$("#matchedCovFilter").css("display","none"); 
		//$('.mut').removeClass('active');	
		if (type == 'variants' || type == 'rnaseq')
			$('#tier_type').val('tier_or');
		tbl.search('');
		if (gene_id != 'null') {
			//diagnosis = "any";
			//$("#selDiagnosis").val("any"); 
			setAttrValue("any")
			$("#selAAPos").val("any");
		}
		//$('#btnHighConf').removeClass('active');
		$('#ckHighConf').prop('checked', false);
		//$('#btnSignedOut').removeClass('active');
		$('#ckSignedOut').prop('checked', false);

		@if ($exp_type == "Panel")
			//$('#btnInExome').removeClass('active');
			$('#ckInExome').prop('checked', false);
		@endif

		if (type == "hotspot") {
			$('#ckNotInGermlineSomatic').prop('checked', false);
			$('#btnNotInGermlineSomatic').removeClass('active');
		}
		onco_filter.clearFilter();
		pause_filtering = false;
	}
	

	function selectExpSamples(samples) {
		if (json_data.exp_plot_data.x == null)
			return;
		for (var i = 0; i < json_data.exp_plot_data.x.selected.length; i++)
			json_data.exp_plot_data.x.selected[i] = "2";
		for (var i = 0; i < samples.length; i++) {
			var idx = json_data.exp_plot_data.y.smps.indexOf(samples[i]);
			if (idx != -1) {
				//alert(idx);
				json_data.exp_plot_data.x.selected[idx] = "8";
			}
		}
		exp_plot.initData(json_data.exp_plot_data);
		exp_plot.initialize();
		exp_plot.sortSamplesByVariable("expression");
		exp_plot.draw();
	}


	function launchIGV(session, locus) {

		//url = 'http://www.broadinstitute.org/igv/projects/current/igv.php?file=' + session + '&locus=' + locus + '&genome=hg19';
		//alert(url);
		//window.open(url);return;
		//prepIGVLaunch(session,locus,'hg19','');
		//appRequest(60151, session, 'hg19', 'true', locus);
		//return;


		session = session.replace("https","http");
		if (launched)
			url = 'http://localhost:60151/goto?locus=' + locus;
		else
			url = 'http://localhost:60151/load?file=' + session + '&locus=' + locus + '&merge=false&genome=hg19';
		//url = 'http://www.broadinstitute.org/igv/projects/current/igv.php?sessionURL=' + bam;
		//url="https://www.google.com";
		//doAjax(url);return;
		console.log(url);
		//window.location.href = url;

		var oldScript = document.getElementById("igvlink");
		if (oldScript) {
			oldScript.parentNode.removeChild(oldScript);
		}

		// create new script
		var newElem = document.createElement("img");
		newElem.id = SCRIPT_ELEMENT_ID;
    	newElem.setAttribute("src", url);    
    	// add new script to document (head section)
    	var head = document.getElementsByTagName("head")[0];
    	head.appendChild(newElem);


		//window.open(url);
		launched = true;
		return;

		$.ajax({ type:"GET", url: url, timeout:3000, async: false, dataType : "jsonp", jsonp : "jsonp",success: function(data) {
			}, error: function(e) {
				alert(e.message);
				url = 'http://www.broadinstitute.org/igv/projects/current/igv.php?sessionURL=' + bam;
				window.location.href = url;
			}
		});
	}	

	function doFilter() {
		if (pause_filtering)
			return;
		all_patients = [];
		filtered_patients = [];		
		tbl.draw();	

		$('#lblCountPatients').text(objAttrToArray(filtered_patients).length);
    	$('#lblCountTotalPatients').text(objAttrToArray(all_patients).length);
    	
    	if (first_loading) {
    		//var diagnoses = objAttrToArray(diagnosis_list);
    		aa_poses = objAttrToArray(aa_pos_list);
    		if (gene_id != 'null') {
	    		attr_keys.forEach(function(d){
	    			if (meta_type == d)
	    				$('#selMeta').append($('<option>', {value: d, text: d, selected: true}));    			
	    			else
	    				$('#selMeta').append($('<option>', {value: d, text: d}));	
	    		});

	    		getNumberArray(aa_poses).sort(function(a, b){return a-b}).forEach(function(d){
	    			$('#selAAPos').append($('<option>', {value: d, text: d}));	
	    		})

	    		var patients = objAttrToArray(all_patients);
	    		console.log(patients)
	    		patients.sort().forEach(function(d){
	    			if (patient_id == d)
	    				$('#selPatients').append($('<option>', {value: d, text: d, selected: true}));    			
	    			else
	    				$('#selPatients').append($('<option>', {value: d, text: d}));	
	    		}) 
	    	}

    		first_loading=false;
    	}
    	//$('#lblCountDisplay').text(tbl.page.info().recordsDisplay);
    	//$('#lblCountTotal').text(tbl.page.info().recordsTotal);
    	uploadSetting();
    	return;
    	$('.mytooltip').tooltipster();
    	$('.flag_tooltip').tooltipster({animation: 'fade', trigger: 'hover'});
	}

	function applySetting() {
		var maf = {!!!is_numeric($setting->maf)?"0.05":$setting->maf!!}; 
		var total_cov = {!!!is_numeric($setting->total_cov)?"10":$setting->total_cov!!}; 
		var vaf = {!!!is_numeric($setting->vaf)?"0.25":$setting->vaf!!}; 
		
		$('#freq_max').numberbox("setValue" , maf);
		$('#total_cov_min').numberbox("setValue", total_cov);
		$('#vaf_min').numberbox("setValue", vaf);

		var tier1 = {!!empty($setting->tier1)?"true":$setting->tier1!!};
		var tier2 = {!!empty($setting->tier2)?"true":$setting->tier2!!};
		var tier3 = {!!empty($setting->tier3)?"false":$setting->tier3!!};
		var tier4 = {!!empty($setting->tier4)?"false":$setting->tier4!!};
		var no_tier = {!!empty($setting->no_tier)?"false":$setting->no_tier!!};
		var no_fp = {!!empty($setting->no_fp)?"false":$setting->no_fp!!};
		$('#ckFlagged').prop('checked', {!!empty($setting->flagged)?"false":$setting->flagged!!});

		if (tier1) {
			//$('#btnTier1').addClass('active');
			$('#ckTier1').prop('checked', true);
		}else {
			//$('#btnTier1').removeClass('active');
			$('#ckTier1').prop('checked', false);	
		}
		if (tier2) {
			//$('#btnTier2').addClass('active');
			$('#ckTier2').prop('checked', true);
		}else {
			//$('#btnTier2').removeClass('active');
			$('#ckTier2').prop('checked', false);	
		}
		if (tier3) {
			//$('#btnTier3').addClass('active');
			$('#ckTier3').prop('checked', true);
		}else {
			//$('#btnTier3').removeClass('active');
			$('#ckTier3').prop('checked', false);	
		}
		if (tier4) {
			//$('#btnTier4').addClass('active');
			$('#ckTier4').prop('checked', true);
		}else {
			//$('#btnTier4').removeClass('active');
			$('#ckTier4').prop('checked', false);	
		}
		if (no_tier) {
			//$('#btnNoTier').addClass('active');
			$('#ckNoTier').prop('checked', true);
		}else {
			//$('#btnNoTier').removeClass('active');
			$('#ckNoTier').prop('checked', false);	
		}
		if (no_fp) {
			//$('#btnNoFP').addClass('active');
			$('#ckNoFP').prop('checked', true);
		}else {
			//$('#btnNoFP').removeClass('active');
			$('#ckNoFP').prop('checked', false);	
		}
		/*
		if (tier1 && tier2 && tier3 && tier4 && no_tier) {
			$('#btnTierAll').addClass('active');
			$('#ckTierAll').prop('checked', true);	
		} else {
			$('#btnTierAll').removeClass('active');
			$('#ckTierAll').prop('checked', false);	
		}*/

		var rna_mutation = false;
		var dna_mutation = false;
		@if ($type != "rnaseq")
			@if ($patient == null || ($patient != null && $patient->hasVar($case_id, "rna")))
				console.log({!!$patient!!});
				var matched_rna_var = 2;
				var matched_rna_total = 9;

				if (rna_mutation) {
					//$('#btnMatched').addClass('active');
					$('#ckMatched').prop('checked', true);
				}
				else {
					//$('#btnMatched').removeClass('active');
					$('#ckMatched').prop('checked', false);
				}

				$('#matched_var_cov_min').numberbox("setValue" , matched_rna_var);
				$('#matched_total_cov_min').numberbox("setValue" , matched_rna_total);				
			@endif
		@endif

		@if ($type == "variants" || $type == "rnaseq")
			$('#tier_type').val('{!!$setting->tier_type!!}');
		@endif
		@if ($type == "rnaseq")
			@if ($patient == null || ($patient != null && $patient->hasVar($case_id, "dna")))
				var matched_dna_var = 2;
				var matched_dna_total = 9;

				if (dna_mutation) {
					//$('#btnMatched').addClass('active');
					$('#ckMatched').prop('checked', true);
				}
				else {
					//$('#btnMatched').removeClass('active');
					$('#ckMatched').prop('checked', false);
				}

				$('#matched_var_cov_min').numberbox("setValue" , matched_dna_var);
				$('#matched_total_cov_min').numberbox("setValue" , matched_dna_total);
			@endif
		@endif		
	}

	function uploadSetting() {
		if (checking_high_conf)
			return;
		@if ($gene_id != "null")
		return;
		@endif
		var setting = {
						'maf' : $('#freq_max').numberbox("getValue"),
						'total_cov' : $('#total_cov_min').numberbox("getValue"),
						'vaf' : $('#vaf_min').numberbox("getValue"),
						'tier1' : $('#ckTier1').is(":checked"), 
						'tier2' : $('#ckTier2').is(":checked"), 
						'tier3' : $('#ckTier3').is(":checked"),
						'tier4' : $('#ckTier4').is(":checked"),
						'no_tier' : $('#ckNoTier').is(":checked"),
						'flagged' : $('#ckFlagged').is(":checked"),
						'no_fp' : $('#ckNoFP').is(":checked"),
						'filters' : JSON.stringify(filter_settings)
					};
		// console.log("upload setting " + JSON.stringify(setting));
		
		@if ($type == "variants" || $type == "rnaseq")
			setting.tier_type = $('#tier_type').val();
		@endif
		
		var url = '{!!url("/saveSetting")!!}' + '/page.' + '{!!$type!!}';
		console.log(JSON.stringify(setting));
		$.ajax({ url: url, async: true, type: 'POST', dataType: 'text', data: setting, success: function(data) {
			}, error: function(xhr, textStatus, errorThrown){
					console.log('save failed! Reason:' + JSON.stringify(xhr) + ' ' + errorThrown);
				}
		});	

	}

	function uploadColumnSetting() {
		var url = '{!!url("/saveSetting")!!}' + '/page.columns';

		//console.log(JSON.stringify(show_columns));
		$.ajax({ url: url, async: true, type: 'POST', dataType: 'text', data: {"show":show_columns}, success: function(data) {
			}, error: function(xhr, textStatus, errorThrown){
					console.log('save failed! Reason:' + JSON.stringify(xhr) + ' ' + errorThrown);
				}
		});	

	}

	function clearFilter() {
		selectExpSamples([]);		
	}

	function parseFrameJSON(response) {
        try{
            return JSON.parse(response);
        }catch(e){
            alert('Session expired! please refresh the page!');
            console.log('cannot parse Ajax response. It should be session timeout. Error: ' + e);            
        }
    }

	function getCohortDetails( d, idx) {
		var patient_link = document.createElement("div");
		var patient_id = getInnerText(d[patient_id_idx]);
		var gene_id = getInnerText(d[gene_id_idx]);
		
		url = '{!!url('/getCohorts')!!}' + '/' + patient_id + '/' + gene_id + '/{!!$type!!}';
		console.log(url);
		var id = d[patient_id_idx+2] + d[patient_id_idx+3] + d[patient_id_idx+4];
		tbl_id = "tblcohort" + id;
		loading_id = "loading_cohort" + id;
		lbl_id = "lblcohort" + id;
		num_samples = 0;
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
					data = JSON.parse(data);
					num_samples = data.data.length;
					var tblSampleDetail = $('#' + tbl_id).DataTable( 
						{				
							"paging":   false,
							"ordering": true,
							"info":     false,
							"dom": '',
							"data": data.data,
							"columns": data.cols,
						} );
					$('#' + loading_id).css("display","none");
					$('#' + lbl_id).text(data.data.length);
					$('.cohortDetailtooltip').tooltipster();

				}
			});
		return '<div style="background-color: #f5f5f5;border: 1px solid #cccccc;padding: 20px;margin: 0px 0px 0px;font-size: 13px;line-height:1;"><div id="' + loading_id + '"><img src="{!!url('/images/ajax-loader.gif')!!}""></img></div><H4><a href=javascript:closeDetails("' + idx + '")><img width=30 height=30 src="{!!url('/images/close-button.png')!!}""></img></a>Cohort summary of gene <font color="red">' + gene_id + '</font> (<font color=green>*</font>: mutations in patient <font color=red>' + patient_id +'</font>)</H4><BR><table cellpadding="5" cellspacing="5" class="prettyDetail" word-wrap="break-word" id="' + tbl_id + '" style="width:60%;border:2px solid"></table></div>';
	  	
	}

	function closeDetails(idx) {
		var row = tbl.row(idx);
		row.child.hide();	 	    
	}

	function format( d, idx ) {	
		var type = "sample";
		var patient_link = document.createElement("div");
		//var patient_id_idx = 5;
		patient_id = getInnerText(d[patient_id_idx]);
		case_id = getInnerText(d[patient_id_idx+1]);
		ref = getTitleText(d[patient_id_idx+6]);
		alt = getTitleText(d[patient_id_idx+7]);
		
		var cases = case_id.split(',');
		if (cases.length > 1)
			case_id = "any";
		url = '{!!url('/getVarSamples')!!}' + '/' + d[patient_id_idx+3] + '/' + d[patient_id_idx+4] + '/' + d[patient_id_idx+5] + '/' + ref + '/' + alt + '/' + patient_id + '/' + case_id + '/' + '{!!$type!!}';
		var id = d[patient_id_idx+2] + d[patient_id_idx+3] + d[patient_id_idx+4];
		tbl_id = "tbl" + id;
		loading_id = "loading" + id;
		lbl_id = "lbl" + id;
		num_samples = 0;
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
					// console.log(data);
					data = parseJSON(data);
					num_samples = data.data.length;
					var tblSampleDetail = $('#' + tbl_id).DataTable( 
						{				
							"paging":   false,
							"ordering": false,
							"info":     false,
							"dom": '',
							"data": data.data,
							"columns": data.columns,									
						} );
					$('#' + loading_id).css("display","none");
					$('#' + lbl_id).text(data.data.length);

				}
			});
		return '<div style="background-color: #f5f5f5;border: 1px solid #cccccc;padding: 20px;margin: 0px 0px 0px;font-size: 13px;line-height:1;"><div id="' + loading_id + '"><img src="{!!url('/images/ajax-loader.gif')!!}""></img></div>Patient ' + patient_id + ' has <label ID="' + lbl_id + '"></label> samples<BR><table cellpadding="5" cellspacing="5" class="prettyDetail" word-wrap="break-word" id="' + tbl_id + '" style="width:60%;border:2px solid"></table></div>';
	  	//return '<div style="background-color: #f5f5f5;border: 1px solid #cccccc;padding: 20px;margin: 0px 0px 0px;font-size: 13px;line-height:1;"><div id="' + loading_id + '"><img src="{!!url('/images/ajax-loader.gif')!!}""></img></div><a href="javascript:alert(idx);">X</a>Patient ' + patient_id + ' has <label ID="' + lbl_id + '"></label> samples<BR><table cellpadding="5" cellspacing="5" class="prettyDetail" word-wrap="break-word" id="' + tbl_id + '" style="width:60%;border:2px solid"></table></div>';
	    
	}

	function showTable(data) {
		cols = data.cols;
		col_arr = [];
		cols.forEach(function(d) {
			col_arr.push(d.title);
		})
		if (can_signout)
			cols[col_arr.indexOf('Signout')] = {"title":"<input id='ckSelectSignoutAll' type='checkbox' class='mytooltip' tilte='Select/unselect all'>Signout</input>"};
		/*
		cols[col_arr.indexOf('Details')] = {
                "class": "details-control",
                "title": "Libraries",
                "orderable":      false,                
                "defaultContent": ""
        		};
        
        cols[col_arr.indexOf('Cohort')] = {
                "class": "details-cohort",
                "title": "Gene cohort",
                "orderable":      true,                
                "defaultContent": ""
        		};
        cols[col_arr.indexOf('Site cohort')] = {
                "class": "details-cohort",
                "title": "Site cohort",
                "orderable":      true,                
                "defaultContent": ""
        		};
		*/
		tbl = $('#tblOnco').DataTable( 
		{
			"data": data.data,
			"columns": cols,
			"ordering":    true,
			"deferRender": true,
			"searchHighlight": true,
			"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
			"pageLength":  15,
			"pagingType":  "simple_numbers",			
			"dom": '<"toolbar">lfrtip',
			"buttons": ['csv', 'colvis'],
			"drawCallback" : function(settings) {
		      	@if ($gene_id != 'null')
		      		drawMutPlot();
		      	@endif
		     },
			@if ($sample_id != "null" && $type != "rnaseq")
			'columnDefs': [{
		         'targets': 1,
		         'searchable': false,
		         'orderable': false,
		         'width': '1%',
		         'className': 'select-checkbox',
		         'render': function (data, type, full, meta){
		             return '<input type="checkbox">';
		         }
		      }],		      
		      "rowCallback": function(row, data, dataIndex){
						         // Get row ID
						         var rowId = data[data.length-1];
						         // If row ID is in the list of selected row IDs
						         if($.inArray(rowId, var_list) !== -1){
						         	$(row).find('input[type="checkbox"]').prop('checked', true);
						            //$(row).addClass('selected');
						         } else {
						         	$(row).find('input[type="checkbox"]').prop('checked', false);
						         	//$(row).removeClass('selected');
						         }
						         if (!is_signout_manager || status.toLowerCase() == 'closed')
						         	$(row).find('input[type="checkbox"]').prop('disabled', true);
						         else
						         	$(row).find('input[type="checkbox"]').prop('disabled', false);
						      }
			@endif						      
			
		} );

		// Handle click on checkbox
		$('#tblOnco tbody').on('click', 'input[type="checkbox"]', function(e){
			if (!is_signout_manager || status.toLowerCase() == 'closed')
				return;
			var $row = $(this).closest('tr');
	      	var data = tbl.row($row).data();
			var rowId = data[data.length - 1];

			// Determine whether row ID is in the list of selected row IDs 
			var index = $.inArray(rowId, var_list);

			// If checkbox is checked and row ID is not in list of selected row IDs
			if(this.checked && index === -1){
				var_list.push(rowId);
				// Otherwise, if checkbox is not checked and row ID is in list of selected row IDs
			} else if (!this.checked && index !== -1){
				var_list.splice(index, 1);
			}

			if(this.checked){
				$row.addClass('selected');
			} else {
				$('#ckSelectSignoutAll').prop('checked', false);
				$row.removeClass('selected');
			}

			$('#lblSignoutCount').text(var_list.length);
	      // Update state of "Select all" control
	      //updateDataTableSelectAllCtrl(table);

	      // Prevent click event from propagating to parent
	      //e.stopPropagation();
		});

		var detailRows = [];

		$('#tblOnco').on( 'draw.dt', function () {
			$('#lblCountDisplay').text(tbl.page.info().recordsDisplay);
    		$('#lblCountTotal').text(tbl.page.info().recordsTotal);    		
    	});

    	$('#tblOnco').on( 'search.dt', function () {
    		//tbl.draw();
    	});

		$('#tblOnco tbody').on( 'click', 'tr td.details-control', function () {
	        var tr = $(this).closest('tr');
	        tbl.cell( this ).data("<img width=20 height=20 src='{!!url('images/details_open.png')!!}'></img>");
	        var row = tbl.row( tr );
	        var idx = $.inArray( tr.attr('id'), detailRows );
	 
	 		if ( row.child.isShown() ) {
	            tr.removeClass( 'details' );
	            row.child.hide();
	 
	            // Remove from the 'open' array
	            detailRows.splice( idx, 1 );
	        }
	        else {
	            tr.addClass( 'details' );
	            tbl.cell( this ).data("<img width=20 height=20 src='{!!url('images/details_close.png')!!}'></img>");
	            row.child( format( row.data(),idx ) ).show();
	 
	            // Add to the 'open' array
	            if ( idx === -1 ) {
	                detailRows.push( tr.attr('id') );
	            }
	        }
	    } );

	    $('#tblOnco tbody').on( 'click', 'tr td.details-cohort', function () {
	        var tr = $(this).closest('tr');
	        //tbl.cell( this ).data("<img width=20 height=20 src='{!!url('images/details_open.png')!!}'></img>");
	        var row = tbl.row( tr );
	        var idx = $.inArray( tr.attr('id'), detailRows );
	 
	 		if ( row.child.isShown() ) {
	            tr.removeClass( 'details' );
	            row.child.hide();
	 
	            // Remove from the 'open' array
	            detailRows.splice( idx, 1 );
	        }
	        else {
	            tr.addClass( 'details' );
	            //tbl.cell( this ).data("<img width=20 height=20 src='{!!url('images/details_close.png')!!}'></img>");
	            row.child( getCohortDetails( row.data(),row.index() ) ).show();
	 
	            // Add to the 'open' array
	            if ( idx === -1 ) {
	                detailRows.push( tr.attr('id') );
	            }
	        }
	    } );
		
		//tbl.columns().iterator('column', function ( context, index ) {			
		//	tbl.column(index).visible(true);
		//} );
		
		var tier_html = '</td><td><span class="mytooltip" title="Maximum population allele frequency">MAF:&nbsp;</span></td><td><input id="freq_max" class="easyui-numberbox num_filter" data-options="min:0,max:1,precision:20" style="width:60px;height:26px"></td>';
			tier_html += '<td><span class="mytooltip" title="Minimum total coverage">Min Total Cov:&nbsp;</span></td><td><input id="total_cov_min" class="easyui-numberbox num_filter" data-options="min:0,max:10000,precision:1" style="width:50px;height:26px"></td>';
			tier_html += '<td><span class="mytooltip" title="Minimum allele frequency">Min VAF:&nbsp;</span></td><td><input id="vaf_min" class="easyui-numberbox num_filter" data-options="min:0,max:1,precision:20" style="width:50px;height:26px"></td>';
		@if (($type == 'rnaseq' || $exp_type == "RNAseq") && ($patient == null || ($patient != null && $patient->hasVar($case_id, "dna")))) 
			// tier_html += '<td><span class="btn-group" id="matched" data-toggle="buttons">' +
  	// 					 '	<label id="btnMatched" class="btn btn-default mut">' +
			// 			 '		<input id="ckMatched" type="checkbox" autocomplete="off">In DNA' +
			// 			 '	</label></span>'//This does not work!  This is hard coded values and query does not return this
			tier_html+="<td>";
			tier_html += '<span id="matchedCovFilter" style="display:none">&nbsp;<input id="matched_var_cov_min" class="easyui-numberbox num_filter" data-options="min:0,max:10000,precision:1" style="width:40px;height:26px">/<input id="matched_total_cov_min" class="easyui-numberbox num_filter" data-options="min:0,max:10000,precision:1" style="width:40px;height:26px">';
			tier_html += '(Variant/Total)</span></td>';			
		@endif
		@if ($type != 'rnaseq' && $exp_type != "RNAseq" && ($patient == null || ($patient != null && $patient->hasVar($case_id, "rna")))) 
			// tier_html += '<td><span class="btn-group" id="matched" data-toggle="buttons">' +
  	// 					 '	<label id="btnMatched" class="btn btn-default mut">' +
			// 			 '		<input id="ckMatched" type="checkbox" autocomplete="off">In RNA' +
			// 			 '	</label></span>'
			tier_html+="<td>";
			tier_html += '<span id="matchedCovFilter" style="display:none">&nbsp;<input id="matched_var_cov_min" class="easyui-numberbox num_filter" data-options="min:0,max:10000,precision:1" style="width:40px;height:26px">/<input id="matched_total_cov_min" class="easyui-numberbox num_filter" data-options="min:0,max:10000,precision:1" style="width:40px;height:26px">';
			tier_html += '(Variant/Total)</span></td>';			
		@endif

		@if (($exp_type == "Panel" || $exp_type == "RNAseq") && ($has_exome))
			tier_html += '<td><span class="btn-group-toggle" id="inExome" data-toggle="buttons">' +
  						 '	<label id="btnInExome" class="btn btn-default mut">' +
						 '		<input id="ckInExome" type="checkbox" autocomplete="off">In Exome' +
						 '	</label></span></td>'
		@endif

		@if ($type == "hotspot")
			tier_html += '<td><span class="btn-group-toggle" id="notInGermlineSomatic" data-toggle="buttons">' +
  						 '	<label id="btnNotInGermlineSomatic" class="btn btn-default mut active">' +
						 '		<input id="ckNotInGermlineSomatic" type="checkbox" autocomplete="off" checked>Not called in Germline and Somatic' +
						 '	</label></span></td>'
		@endif

		@if ($type != 'somatic')
			tier_html +='<td><a target=_blank href="' + '{!!url("data/".Config::get('onco.classification_germline_file'))!!}' + '" title="Germline tier definitions" class="mytooltip"><img src={!!url("images/help.png")!!}></img></a></td>';
		@endif
		@if ($type != 'germline')
			tier_html +='<td><a target=_blank href="' + '{!!url("data/".Config::get('onco.classification_somatic_file'))!!}' + '" title="Somatic tier definitions" class="mytooltip "><img src={!!url("images/help.png")!!}></img></a></td>';
		@endif
		
		tier_html +='<td>Tier:&nbsp;';
		@if ($type == 'rnaseq' || $type == "variants" || $type == "hotspot") 
			tier_html += '<select id="tier_type" class="form-control" style="width:150px;height:30px;display:inline;padding:2px 2px;font-size:11px"><option value="tier_or">Germline or somatic</option><option value="tier_and">Germline and somatic</option><option value="germline_only">Germline tier only</option><option value="somatic_only">Somatic tier only</option></select>';
		@endif

		tier_html +='<td><span class="btn-group" role="group" id="tiers">' +			  						
					'<input id="ckTier1" class="btn-check ckTier" type="checkbox" autocomplete="off">' + 
					'<label id="btnTier1" class="btn btn-outline-primary" for="ckTier1">1</label>' +
					'<input id="ckTier2" class="btn-check ckTier" type="checkbox" autocomplete="off">' +
					'<label id="btnTier2" class="btn btn-outline-primary" for="ckTier2">2</label>' +
					'<input id="ckTier3" class="btn-check ckTier" type="checkbox" autocomplete="off">' +
					'<label id="btnTier3" class="btn btn-outline-primary" for="ckTier3">3</label>' +
					'<input id="ckTier4" class="btn-check ckTier" type="checkbox" autocomplete="off">' +
					'<label id="btnTier4" class="btn btn-outline-primary" for="ckTier4">4</label>' +
					'<input id="ckNoTier" class="btn-check ckTier" type="checkbox" autocomplete="off">' +
					'<label id="btnNoTier" class="btn btn-outline-primary" for="ckNoTier">No Tier</label>' +					
					'</span>' +					
					'&nbsp;<span class="btn-group" role="group" id="flagged">' +
					'<input id="ckFlagged" class="btn-check ckMut" type="checkbox" autocomplete="off">' +
					'<label id="btnFlagged" class="btn btn-outline-primary mut" for="ckFlagged">Flag</label>' +
					'</span>' +
					'&nbsp;<span class="btn-group" role="group" id="NoFP">' +
					'<input id="ckNoFP" class="btn-check ckMut" type="checkbox" autocomplete="off">' +
					'<label id="btnNoFP" class="btn btn-outline-primary mut" for="ckNoFP">No FP</label>' +
					'</span>';		
		if (type == "germline")
			tier_html +='&nbsp;<a target=_blank href="{!!url('/images/ACMG.png')!!}" title="ACMG definitions" class="mytooltip acmg_definition"><img src={!!url("images/help.png")!!}></img></a>' + 
					'&nbsp;<span class="btn-group filter_btn" role="group" id="acmg_guide"">' +
					'	<input id="ckACMGGuide" class="btn-check ckMut" type="checkbox" autocomplete="off">' +
					'	<label id="btnACMGGuide" class="btn btn-outline-primary mut" for="ckACMGGuide">ACMG Guide</label>' +
					'</span>';					

		if (gene_id != 'null') {
			tier_html += '&nbsp;Patients:&nbsp;<select id="selPatients" class="form-select" style="width:90px;height:30px;display:inline;padding:2px 2px;font-size:0.75rem;" ><option value="any">All</option></select>';
			tier_html += '&nbsp;Metadata:&nbsp;<select id="selMeta" class="form-select" style="width:90px;height:30px;display:inline;padding:2px 2px;font-size:0.75rem;"><option value="any">All</option></select><select id="selMetaValue" class="form-control" style="width:150px;height:30px;display:none;padding:2px 2px;"></select>';
			tier_html += '&nbsp;AA pos:&nbsp;<select id="selAAPos" class="form-select" style="width:80px;height:30px;display:inline;padding:2px 2px;font-size:0.75rem;" ><option value="any">All</option></select>';
		}
		if (show_signout) {
			tier_html +='&nbsp;<a id="high_conf_definition" target=_blank href="{!!url('/images/HighConf.pdf')!!}" title="High confident variants definitions" class="mytooltip"><img src={!!url("images/help.png")!!}></img></a>' + 
					'&nbsp;<span class="btn-group filter_btn" role="group" id="high_conf">' +
					'<input id="ckHighConf" class="btn-check mut highConf" type="checkbox" autocomplete="off">' + 
					'<label id="btnHighConf" class="btn btn-outline-primary" for="ckHighConf">High Conf</label>' +
					'</span><span>' +
					'	<select id="selHighConf" class="form-select highConf" style="font-size:0.75rem;display:inline-block;width:auto;height:auto;padding: 6px 4px;">' +
					@foreach ( \App\Models\UserSetting::getHighConfSetting() as $config_name => $high_conf)
							'<option value="{!!$config_name!!}" {!!($project != null && $project->isCOMPASS() && $config_name == "Compass")? "selected" : ""!!}>{!!$config_name!!}</option>' +						
					@endforeach
					'	</select>' +
					'</span>';
		}
		tier_html += '<td><span id="QCIfilter" style="display:none">QCI:&nbsp;' +
					'<span class="btn-group" role="group" id="QCItiers">' +					
  					'<input id="ckQCITier1" class="btn-check ckQCITier" type="checkbox" autocomplete="off" checked>' + 
					'<label id="btnQCITier1" class="btn btn-outline-primary" for="ckQCITier1">1</label>' +
					'<input id="ckQCITier2" class="btn-check ckQCITier" type="checkbox" autocomplete="off" checked>' +
					'<label id="btnQCITier2" class="btn btn-outline-primary" for="ckQCITier2">2</label>' +
					'<input id="ckQCITier3" class="btn-check ckQCITier" type="checkbox" autocomplete="off" checked>' +
					'<label id="btnQCITier3" class="btn btn-outline-primary" for="ckQCITier3">3</label>' +
					'<input id="ckQCITier4" class="btn-check ckQCITier" type="checkbox" autocomplete="off" checked>' +
					'<label id="btnQCITier4" class="btn btn-outline-primary" for="ckQCITier4">4</label>' +
					'<input id="ckQCINoTier" class="btn-check ckQCITier" type="checkbox" autocomplete="off" checked>' +
					'<label id="btnQCINoTier" class="btn btn-outline-primary" for="ckQCINoTier">No Tier</label>' +
					'</span></span>';		
		if (1==2) {
			tier_html +='&nbsp;<span class="btn-group-toggle filter_btn" id="signedOut" data-toggle="buttons">' +
					'	<label id="btnSignedOut" class="btn btn-default" style="display:"' + ((status.toLowerCase() == 'active')? 'none' : 'inline') + '">' +
					'		<input id="ckSignedOut" type="checkbox" autocomplete="off">Signed out' +
					'	</label>' +
					'</span>';
			tier_html +='<span id="signoutCount" style="display:none;"">&nbsp;&nbsp;<label id="lblSignoutDesc" style="text-align:left;" text=""></label>&nbsp;:&nbsp;<span id="lblSignoutCount" style="text-align:left;color:red;" text=""></span>&nbsp;variants</span>';
										
		}
		tier_html += '</td></tr></table>';	
		var annotation = '{!!\App\Models\UserSetting::getSetting("default_annotation", false)!!}';
		$("div.toolbar").html('<div><table style="font-size:12;padding:2px"><tr>' + tier_html + 
								'</div><button id="popover" data-toggle="popover" data-placement="bottom" type="button" class="btn btn-secondary" style="padding:5px">' + 
								'Select Columns</button>' + 
								@if (!Config::get('site.isPublicSite') && 1==2)
								'&nbsp;Annotation: <span style="color:red">' + 								
								'<select id="selAnnotation">' + 
									'<option value="khanlab"' + ((annotation == 'khanlab')? 'selected' : '') + '>Khan Lab</option>' +
									'<option value="avia"' + ((annotation == 'avia')? 'selected' : '') + '>AVIA</option>' +
								'</select>'+ 
								@endif
								'</span>');
		col_html = '';

		$('.tierbox').fancybox({
    		width  : '95%',
    		height : '800px',
    		top : '0px',
    		type   :'images',
    		autoSize: false,
    		fitToView: false,
    		beforeShow: function () {
		        this.width = 500;
				this.height = 680;
			}
		});
		//$("#ckLevel").bootstrapSwitch();

		$("#selAnnotation").on('change', function() {
			var url = '{!!url("/saveSettingGet/default_annotation")!!}' + '/' + $('#selAnnotation').val();
			$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				if (data == "Success")
					location.reload();
				}, error: function(xhr, textStatus, errorThrown){
					alert('save failed! Reason:' + JSON.stringify(xhr) + ' ' + errorThrown);
				}
			});		
		});

		$("#tier_type").on('change', function() {
			doFilter();			
		});

		$("#ckSelectSignoutAll").on('change', function() {								
			if ($("#ckSelectSignoutAll").is(':checked')) {
				select_all = true;	
				doFilter();				
				var_list = unique_array(var_list);
				select_all = false;
			} else {
				var_list = [];
				doFilter();
			}
			$('#lblSignoutCount').text(var_list.length);			
		});

		var visible_cols = [];
		col_html = "<table>";		
		tbl.columns().iterator('column', function ( context, index ) {
			//if (index > filter_idx) return;
			var column_html = tbl.column(index).header().innerHTML;
			var column_text = column_html;			
			column_text = getInnerText(column_html);
			tbl_columns[column_text] = index;
			columns.push(column_text);			
		});
		germline_level_idx = columns.indexOf('{!!Lang::get("messages.germline_level")!!}');			
		filter_idx = germline_level_idx + 6;
		if (gene_id != 'null')
			filter_idx = germline_level_idx + attr_keys.length + 2;		
		var col_texts = Object.keys(tbl_columns);
		var i = 0;
		//sort column text so that it is easier for users to check/uncheck columns
		col_texts.sort().forEach(function(column_text, idx) {			
			//get index of original table
			var index = tbl_columns[column_text];
			//console.log(index + ' => ' + filter_idx);
			var show = (show_columns.indexOf(column_text) != -1 && index < filter_idx);
			tbl.column(index).visible(show, false);
			
			checked = (show)? 'checked' : '';
			
			//if (show && index < filter_idx)
			if (show)
				visible_cols.push(column_text);						
				//show column list as 4 columns
			//if (index < filter_idx) {
				if (i % 4 == 0) {
					if (col_html != "<table>")
						col_html += "</tr>";
					col_html += "<tr>";
				}			
				col_html += '<td><input type=checkbox ' + checked + ' class="onco_checkbox" id="data_column" value=' + index + '><font size=3>&nbsp;' + column_text + '</font></input></td>';
				i++;			
			//}
		} );
		col_html += "</tr></table>";

		if (show_signout)
			tbl.column(signout_idx).visible(false);

		$('[data-toggle="popover"]').popover({
			title: 'Select column <a href="#inline" class="close" data-dismiss="alert">×</a>',
			placement : 'bottom',  
			html : true,
			width : '500px',
			sanitize: false,
			content : function() {
				//console.log(col_html);
				return col_html;
			}

		}); 

		$(document).on("click", ".popover .close" , function(){
				$('[data-toggle="popover"]').popover('hide');
		});
		
		$.fn.dataTableExt.afnFiltering.push( function( oSettings, aData, iDataIndex ) { 
			if (oSettings.nTable != document.getElementById('tblOnco'))
				return true;
			//if show all clicked, ignore filtering
			if (pause_filtering)
				return true;

			var gene = aData[gene_id_idx].trim();
			

			var checked_high_conf = false;			
			var is_hotspot = (aData[hotspot_site_idx] != '');
			var caller;
			var fisher_score;
			var exp_type;
			var normal_cov_val;
			@if ($sample_id != 'null')			
				caller = aData[caller_idx];
				fisher_score = aData[fisher_score_idx];
				exp_type = aData[exp_type_idx];
				normal_cov_val = parseInt(aData[normal_cov_idx]);
				// console.log(normal_cov_idx)				
				checked_high_conf = $('#ckHighConf').is(":checked");
			@endif
			
			all_patients[aData[patient_id_idx]] = '';
			if (first_loading) {
				//diagnosis_list[aData[diag_idx]] = '';
				if (aData[aapos_idx] != '')
					aa_pos_list[aData[aapos_idx]] = '';
			}
			if (gene_id != 'null') {
				var aa_pos = $('#selAAPos').val();
				if (aa_pos != 'any') {				
					if (aa_pos != aData[aapos_idx])
						return false
				}
			}
			
			if (patient_id != 'any' && patient_id != 'null') {
				if (patient_id != aData[patient_id_idx])
					return false;
			}

			
			/*
			if (diagnosis != 'any' && diagnosis != 'null') {
				if (diagnosis != aData[diag_idx])
					return false;
			}
			*/

			var freq_cutoff = $('#freq_max').numberbox("getValue");						
			var freq_val = parseFloat(aData[freq_idx]);
			if (freq_val != NaN) {
				if (freq_val > freq_cutoff ) {
					//console.log("filtered!");
					//console.log(freq_val);
					//console.log(freq_cutoff);
					return false;
				}
			}
			var var_id = aData[aData.length - 1];
			var flag_status = aData[aData.length - 2];
			var acmg_status = aData[aData.length - 3];
			var fp = aData[aData.length - 4];
			var img_id = "img_" + var_id;
			if ($('#ckFlagged').is(':checked')) {
				//var img_file = $('td:eq(0)', oSettings.aoData[ iDataIndex ].nTr).children('a').children('img').prop('src');
				//if (typeof(img_file) != "undefined" && img_file.indexOf('circle_green.png') != -1) {					
				//	return false;
				//} else {
				if (flag_status == 0) {
					//console.log("hello");
					return false;
				}
			}

			if ($('#ckNoFP').is(':checked')) {
				if (fp == 'Y')
					return false;
			}

			if ($('#ckSignedOut').is(":checked")) {
				if (signedout_list.indexOf(var_id) == -1)
					return false;				
			}
			
			//if hotspot, always high confidence
			/*
			if (checked_high_conf) {
				if (is_hotspot) {
					if (select_all && status != 'closed' && can_signout && $('#ckSelectSignoutAll').is(":checked"))
						var_list.push(var_id);					
					return true;
				}
			}
			*/

			var vaf_cutoff = parseFloat($('#vaf_min').numberbox("getValue"));
			var vaf_val = parseFloat(aData[vaf_idx]);
			if (vaf_val < vaf_cutoff){				
				return false;
			}
			// console.log(vaf_cutoff + '=>' + vaf_val);
			var total_cov_cutoff = $('#total_cov_min').numberbox("getValue");
			var total_cov_val = parseInt(aData[total_cov_idx]);
			if (total_cov_val < total_cov_cutoff){
				//console.log("hello");
				return false;
			}

			@if ($type == "germline")				
				if (!checkTier(aData[germline_level_idx]))
					return false;
				if ($('#ckACMGGuide').is(":checked")) {					
					if (acmg_status == 0)
						return false;
				}				
			@endif

			@if ($type == "germline")
				if (checked_high_conf) {
					if (high_conf_setting == undefined)
						high_conf_setting = high_conf_settings[$('#selHighConf').val()];
					var callers = caller.split(';');
					var hc_idx = -1;
					callers.forEach(function(d, i){
						if (d == 'HC_DNASeq')
							hc_idx = i;
					})
					if (hc_idx == -1)
						return false;
					//if (caller.indexOf('HC_DNASeq') == -1)
					//	return false;
					var fisher_scores = fisher_score.split(';');
					fisher_score = parseFloat(fisher_scores[hc_idx]);
					var high_conf = false;
					if (!(fisher_score < high_conf_setting.germline_fisher && total_cov_val >= high_conf_setting.germline_total_cov && vaf_val >= high_conf_setting.germline_vaf))
						return false;
				}
			@endif

			
			@if ($type == "somatic" || $type == "variants")
				if (!checkTier(aData[somatic_level_idx])) {					
					return false;					
				}
				if (checked_high_conf) {
					if (high_conf_setting == undefined)
						high_conf_setting = high_conf_settings[$('#selHighConf').val()];					 
					var exonic_function = aData[gene_id_idx + 1];
					
					// console.log('highconf');
					//if indel, caller must be strelka. If SNP, caller must be mutect					
					// updated 10/23/2020 for TSO data, we should remove this filtering
					/*
					if (exonic_function.toLowerCase().indexOf('insertion') == -1 && exonic_function.toLowerCase().indexOf('deletion') == -1) {
						if (caller.toLowerCase().indexOf('mutect') == -1){
							return false;
						}
					}
					*/
					if (exp_type == "Exome") {
						// console.log("exome");
						// console.log("pos="  +':'+ aData[12] +':'+ aData[13] +':'+aData[14] +':'+aData[15]+ aData[16]);
						// console.log("Total Cov Val= " +total_cov_val + '>=' + high_conf_setting.somatic_exome_total_cov);
						// console.log("Normal Cov Val:" + normal_cov_val  + '>= ' +high_conf_setting.somatic_exome_normal_total_cov );
						// console.log('VAF val: ' + vaf_val  + '>= '+high_conf_setting.somatic_exome_vaf );
						// console.log('---------');
						@if ($type == "somatic")

							//caller filtering for high confidence
							if (exonic_function.toLowerCase().indexOf('insertion') == -1 && exonic_function.toLowerCase().indexOf('deletion') == -1)  {
								if (caller.toLowerCase().indexOf('mutect') == -1){
									return false;
								}
							}
							if (!(total_cov_val >= high_conf_setting.somatic_exome_total_cov && normal_cov_val >= high_conf_setting.somatic_exome_normal_total_cov && vaf_val >= high_conf_setting.somatic_exome_vaf)){
								return false;
							}
						@else
							if (!(total_cov_val >= high_conf_setting.somatic_exome_total_cov && vaf_val >= high_conf_setting.somatic_exome_vaf)){
								return false;
							}
						@endif
						// console.log('passed exome');

					}
					if (exp_type == "Panel") {
						@if ($type == "somatic")
							if (!(total_cov_val >= high_conf_setting.somatic_panel_total_cov && normal_cov_val >= high_conf_setting.somatic_panel_normal_total_cov && vaf_val >= high_conf_setting.somatic_panel_vaf)){
								return false; 
							}
						@else
							if (!(total_cov_val >= high_conf_setting.somatic_panel_total_cov && vaf_val >= high_conf_setting.somatic_panel_vaf)){
								return false; 
							}
						@endif
					}					
				}
			@endif
			

			if ($('#ckMatched').is(":checked")){
				console.log('matched_total_idx' + matched_total_idx);
				if (parseInt(aData[matched_total_idx]) < $('#matched_total_cov_min').numberbox("getValue"))
					return false;
				if (parseInt(aData[matched_var_idx]) < $('#matched_var_cov_min').numberbox("getValue"))
					return false;

			}
			
			@if ($exp_type == "Panel" || $exp_type == "RNAseq")
					if ($('#ckInExome').is(":checked")){
						if (aData[in_exome_idx] != 'Y')
							return false;
					}
			@endif
			@if ($type == "rnaseq" || $type == "variants" || $type == "hotspot")
				somatic_check = checkTier(aData[somatic_level_idx]);
				germline_check = checkTier(aData[germline_level_idx]);
				if ($('#tier_type').val() == "germline_only") {
					if (!germline_check)
						return false;
				}
				if ($('#tier_type').val() == "somatic_only") {
					if (!somatic_check)
						return false;
				}
				if ($('#tier_type').val() == "tier_or") {
					if (!somatic_check && !germline_check)
						return false;
				}
				if ($('#tier_type').val() == "tier_and") {
					if (!somatic_check || !germline_check)
						return false;
				}				
			@endif
				
			@if ($type == "hotspot")
				if ($('#ckNotInGermlineSomatic').is(":checked")){
					if (aData[in_germline_somatic_idx] == 'Y')
						return false;
				}
			@endif

			if (qci_actionability_idx != -1) {
				qci_check = checkQCITier(aData[qci_actionability_idx]);
				if (!qci_check)
					return false;

			}
			var outer_comp_list = [];
			filter_settings = [];
			for (var filter in onco_filter.filters) {
				var comp_list = [];
				var filter_setting = [];				
				for (var i in onco_filter.filters[filter]) {
					var filter_item_setting = [];
					var filter_name = onco_filter.getFilterName(filter, i);
					var idx = filter_list[filter_name];
					filter_item_setting.push(filter_name);
					if (idx == -1)
						currentEval = true;
					else
						currentEval = (aData[idx] != '');
        			if (onco_filter.hasFilterOperator(filter, i)) {
        				var op = (onco_filter.getFilterOperator(filter, i))? "&&" : "||";
        				filter_item_setting.push(op);
        				comp_list.push(op);
        			}
        			filter_setting.push(filter_item_setting);
        			comp_list.push(currentEval);
				}				
				outer_comp_list.push('(' + comp_list.join(' ') + ')');
				filter_settings.push(filter_setting);
			}

			if (gene_id != 'null') {
				if (meta_idx != -1) {
					if ($('#selMetaValue').val() != aData[meta_idx])
						return false;
				}
			}
			//console.log(outer_comp_list.join('||'));
			if (outer_comp_list.length == 0) {
				//filtered_patients[aData[patient_id_idx]] = '';
				final_decision = true;
			}
			else {
				final_decision = eval(outer_comp_list.join('||'));
			}
			
			if (final_decision && select_all && status != 'closed' && can_signout && $('#ckSelectSignoutAll').is(":checked")) {
				var_list.push(var_id);				
			}
			if (final_decision)
				filtered_patients[aData[patient_id_idx]] = '';
        	return final_decision;


 		});
	} // end of showTable()

	function drawMutPlot() {
		var sample_mutation = {};
		if (tbl == undefined)
			return;
		tbl.rows({filter: 'applied'}).every( function ( rowIdx, tableLoop, rowLoop ) {
			var data = this.data();
			if (data[aapos_idx] == null)
				return;
			var patient_id = getInnerText(data[patient_id_idx]);
			var aapos = data[aapos_idx].toString();
			var category = data[gene_id_idx + 1];
			//if (sample_mutation[aapos] == undefined) {
			//	sample_mutation[aapos] = {};
			//	sample_mutation[aapos][category] = 1;
			//}
			//else if (sample_mutation[aapos][category] == undefined) {
			//		sample_mutation[aapos][category].push(patient_id);						
			//}
			//else
			//	sample_mutation[aapos][category]++;
			if (sample_mutation[aapos] == undefined)
				sample_mutation[aapos] = {};
			if (sample_mutation[aapos][category] == undefined)
				sample_mutation[aapos][category] = [];
			sample_mutation[aapos][category].push(patient_id);
		} );
		var mutation_data = [];
		for (var coord in sample_mutation) {
			if (sample_mutation.hasOwnProperty(coord)) {
				var pos_mut = sample_mutation[coord];
				for (var cat in pos_mut) {
					if (pos_mut.hasOwnProperty(cat)) {
						mutation_data.push({"coord":coord, "category":cat, "value": unique_array(pos_mut[cat]).length});						
					}
				}
			}			
		}
		var plotData = json_data.var_plot_data;
		plotData.sample_data = mutation_data;
		console.log(JSON.stringify(plotData));
		initNeedlePlot(plotData);

	}

	function checkTier(value) {		
		if ($('#ckTier1').is(":checked") && (value=="Tier 1" || value=="Tier 1.0" || value=="Tier 1.1" || value=="Tier 1.2" || value=="Tier 1.3" || value=="Tier 1.4"))
			return true;
		if ($('#ckTier2').is(":checked") && value=="Tier 2")
			return true;
		if ($('#ckTier3').is(":checked") && value=="Tier 3")
			return true;
		if ($('#ckTier4').is(":checked") && value=="Tier 4")
			return true;
		if ($('#ckNoTier').is(":checked") && value=="")
			return true;
		if ($('#ckTierAll').is(":checked") && value=="")
			return true;
		return false;
	}

	function checkQCITier(value) {		
		if ($('#ckQCITier1').is(":checked") && value.substring(0,1)=="1")
			return true;
		if ($('#ckQCITier2').is(":checked") && value.substring(0,1)=="2")
			return true;
		if ($('#ckQCITier3').is(":checked") && value.substring(0,1)=="3")
			return true;
		if ($('#ckQCITier4').is(":checked") && value.substring(0,1)=="4")
			return true;
		if ($('#ckQCINoTier').is(":checked") && (value=="" || value=="N/A"))
			return true;
		return false;
	}

	function isInt(value) {
		var x;
		if (isNaN(value)) {
			return false;
		}
		x = parseFloat(value);
		return (x | 0) === x;
	}
	function nodeSelected(coord, samples) {
		if (samples == undefined)
			return;
		if (samples.length > 0) {
			selectExpSamples(samples);
			/*
			sample_list = sample.join(',');
			$.ajax({ url: '{!!url('/getExpSamplesFromVarSamples')!!}' + '/' + sample_list, async: true, dataType: 'text', success: function(data) {				
				exp_samples = JSON.parse(data);
				selectExpSamples(exp_samples);				
				}			
			});
			*/
		}

		//clearFilter();
		tbl.column(aachange_idx).search(coord, true);
		doFilter();

	}
	
	function initNeedlePlot(plotData) {
		colorMap = {
			// mutation categories
			"nonsynonymous SNV": "yellow",
			"synonymous SNV": "green",
			"stopgained": "red",
			"frameshift deletion": "blue",
			"frameshift insertion": "orange",
			"nonframeshift deletion": "pink",
			"nonframeshift insertion": "purple",
			"stoplost": "lightblue",
			// regions
			"X-binding": "olive",
			"region1": "olive"
		};

		legends = {
			x: "Protein positions",
			y: "Number of mutation",
			title: "{!!$gene_id!!}"
		};
		//Create config Object
		//console.log('var_layout:' + $('#var_layout').width());
		var buffer = 0;		
		plotConfig = {
			maxCoord :      plotData.max_coord,
			minCoord :      plotData.min_coord,
			width: $('#var_layout').width()*0.96,
			height:         198 - buffer,
			targetElement : "mut_plot",
			mutationData:   plotData.sample_data,
			mutationRefData:   plotData.ref_data,
			regionData:     plotData.domain,
			colorMap:       colorMap,
			legends:        legends,
			geneID: '{!!$gene_id!!}',
			downloadID: 'btnDownloadSVG',
			selectNode: function(data) { 
							if (aa_poses.indexOf(data.coord.toString()) != -1) {
								$('#selAAPos').val(data.coord); 
								doFilter();
							}
						}
			//responsive: 'resize'
		};		
		drawNeedlePlot(plotConfig);		
	}

	function drawNeedlePlot(plotConfig) {
		// Instantiate a plot
		MutsNeedlePlot = require("muts-needle-plot");
		$("#mut_plot").empty();
		$(".d3-tip.d3-tip-needle").remove();
		$(".d3-tip\\:after").remove();
		$(".d3-tip\\.n\\:after").remove();		
		mutsPlot = new MutsNeedlePlot(plotConfig);				
	}

	function showFlagDetails(var_id, gene_id, type, status) {
		flag_var = getVarByID(var_id);
		flag_var.id = var_id;
		flag_var.status = status;
		flag_var.type = type;
		flag_var.gene_id = gene_id;
		$("#loadingCommentDetail").css("display","block");
		$("#comment_history").css("display","none");
		var url = '{!!url('/getFlagStatus')!!}' + '/' + flag_var.chr + '/' + flag_var.start_pos + '/' + flag_var.end_pos + '/' + flag_var.ref + '/' + flag_var.alt + '/' + type + '/' + flag_var.patient_id;
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
			flag_var.status = data;
			url = '{!!url('/getFlagHistory')!!}' + '/' + flag_var.chr + '/' + flag_var.start_pos + '/' + flag_var.end_pos + '/' + flag_var.ref + '/' + flag_var.alt + '/' + type + '/' + flag_var.patient_id;
			console.log(url);
			$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
					table_data = parseJSON(data);
					$('#pop_comments').w2popup();	
					$("#w2ui-popup").css("top","20px");
					setControls(table_data);
					$('#w2ui-popup #txtVarComment').focus();
									
				}				
			});
			}			
		});		
	}

	function getVarByID(var_id) {
		var id_arr = var_id.split(':');
		var v = {patient_id:id_arr[0], case_id:id_arr[1], chr:id_arr[2], start_pos:id_arr[3], end_pos:id_arr[4], ref:id_arr[5], alt:id_arr[6] };
		return v;
	}

	function deleteFlag(chr, start_pos, end_pos, ref, alt, type, patient_id, updated_at) {
		w2confirm('<H4>Are you sure you want to delete this comment?</H4>')
   			.yes(function () {
				var url = '{!!url('/deleteFlag')!!}' + '/' + chr + '/' + start_pos + '/' + end_pos + '/' + ref + '/' + alt + '/' + type + '/' + patient_id + '/' + updated_at;
				console.log(url);
				$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
						console.log(data);
						table_data = parseJSON(data);
						w2alert("<H4>Delete successful!</H4>");
						url = '{!!url('/getFlagStatus')!!}' + '/' + chr + '/' + start_pos + '/' + end_pos + '/' + ref + '/' + alt + '/' + type + '/' + patient_id;
						$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
								flag_var.status = data;													
								var img_id = 'img_' + flag_var.id;
								var status_info = getStatusInfo(flag_var.status);
								var flag_id = 'flag_' + flag_var.id;
								var img_id = 'img_' + flag_var.id;
								var status_info = getStatusInfo(flag_var.status);
								$("[id='" + img_id + "']").attr('src', status_info.img_src);
								$("[id='" + flag_id + "']").tooltipster('content', status_info.status_desc);
								setControls(table_data);
								updateFlagIcon(flag_var.id, flag_var.gene_id, flag_var.type, flag_var.status, status_info);
							}
						});
					}, error: function(xhr, textStatus, errorThrown){					
						w2alert("<H4>Error:</H4>" + JSON.stringify(xhr) + ' ' + errorThrown);
					}
				});		
			});
	}

	function deleteACMGGuide(chr, start_pos, end_pos, ref, alt, patient_id, updated_at) {
		w2confirm('<H4>Are you sure you want to delete this classification?</H4>')
   			.yes(function () {
				var url = '{!!url('/deleteACMGGuide')!!}' + '/' + chr + '/' + start_pos + '/' + end_pos + '/' + ref + '/' + alt + '/' + patient_id + '/' + updated_at;
				console.log(url);
				$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
						console.log(data);
						table_data = parseJSON(data);
						w2alert("<H4>Delete successful!</H4>");
						var url = '{!!url('/getACMGGuideClass')!!}' + '/' + chr + '/' + start_pos + '/' + end_pos + '/' + ref + '/' + alt + '/' + patient_id;
						console.log(url);
						$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
								acmg_class = parseJSON(data);
								console.log(data);
								acmg_var.classification = acmg_class.classification;
								acmg_var.mode = 'append';
								if (acmg_var.classification.toLowerCase() != 'none')
									acmg_var.mode = 'edit';
								updateHistoryTable(table_data)
								updateACMGLabel(acmg_var);
							}
						});
					}, error: function(xhr, textStatus, errorThrown){					
						w2alert("<H4>Error:</H4>" + JSON.stringify(xhr) + ' ' + errorThrown);
					}
				});		
			});
	}


	function updateFlagIcon(var_id, gene_id, type, status, status_info) {
		tbl.rows().every( function() {
			var d = this.data();
			if (d[d.length-1] == var_id) {
				if (status == '1') {
					d[d.length-2] = 'Y';
					d[flag_idx] = d[flag_idx].replace("'0'", "'1'");
					d[flag_idx] = d[flag_idx].replace("circle_green", "circle_yellow");
					d[flag_idx] = d[flag_idx].replace("No comments about this variant in this patient", "This variant has been commented");
				} else {
					d[d.length-2] = '';
					d[flag_idx] = d[flag_idx].replace("'1'", "'0'");
					d[flag_idx] = d[flag_idx].replace("circle_yellow", "circle_green");
					d[flag_idx] = d[flag_idx].replace("This variant has been commented", "No comments about this variant in this patient");
				}
				this.data(d).draw();
				$('.flag_tooltip').tooltipster({animation: 'fade', trigger: 'hover'});				
			}			
		});
	}

	function updateACMGLabel(acmg_var) {
		tbl.rows().every( function() {
			var d = this.data();
			if (d[d.length-1] == acmg_var.id) {
				var current_classification = getInnerText(d[acmgguide_idx]);
				d[acmgguide_idx] = d[acmgguide_idx].replace(current_classification, acmg_var.classification);
				if (acmg_var.classification.toLowerCase() != 'none')
					d[d.length-3] = 'Y';
				else
					d[d.length-3] = '';					
				this.data(d).draw();				
			}			
		});
	}

	function showMutalyzer(chr, start_pos, end_pos, ref, alt, gene, transcript) {
		var url = '{!!url('/getAAChangeHGVSFormat')!!}' + '/' + chr + '/' + start_pos + '/' + end_pos + '/' + ref + '/' + alt + '/' + gene + '/' + transcript;	
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				var hgvs_string = data;
				var mut_url = 'https://mutalyzer.nl/normalizer/' + hgvs_string;
				console.log(mut_url);
				alert("You are going to a non federal website which has it’s own privacy policy");
				window.open(mut_url, '_blank');
			}
		});
	}

	function showPecan(genome, gene) {
		var url = "https://pecan.stjude.cloud/variants/proteinpaint/?genome=" + genome + "&gene=" + gene;
		alert(disclaimer);
		window.open(url, '_blank');
	}

	function showArchs4(gene) {
		var url = "https://maayanlab.cloud/archs4/gene/" + gene;
		alert(disclaimer);
		window.open(url, '_blank');
	}

	function showCOSMIC(gene) {
		var url = "https://cancer.sanger.ac.uk/cosmic/census-page/" + gene;
		alert(disclaimer);
		window.open(url, '_blank');
	}

	function showGenecards(gene) {
		var url = "http://www.genecards.org/cgi-bin/carddisp.pl?gene=" + gene;
		alert(disclaimer);
		window.open(url, '_blank');
	}

	function showICGC(type, id) {
		var url = "https://dcc.icgc.org/" + type + "/" + id;
		alert(disclaimer);
		window.open(url, '_blank');
	}

	function showFilterDefinition() {
		$('#filter_definition').w2popup();
		$("#w2ui-popup").css("top","20px");
	}
	function getDetails(type, patient_id, case_id, sample_id, chr, start_pos, end_pos, ref, alt, gene_id) {
		@if (isset($file_name))
			if (type == "samples")
				return;
		@endif
			
		$('#pop_var_details').w2popup();
		$("#w2ui-popup").css("top","20px");
		$("#w2ui-popup #loadingDetail").css("display","block");
		$("#w2ui-popup #table_area").css("display","none");
		//if (!expanded) {
		/*			
			if (type == 'clinvar')
				$("#var_layout").layout('panel','east').panel('resize', {width: 750});
			else if (type == 'freq')
				$("#var_layout").layout('panel','east').panel('resize', {width: 400});
			else
				$("#var_layout").layout('panel','east').panel('resize', {width: 300});
			$("#var_layout").layout('expand','east');
			expanded = true;
		*/
		//}		
		url = '{{url('/getVarDetails')}}' + '/' + type + '/' + patient_id + '/' + case_id + '/' + sample_id + '/' + chr + '/' + start_pos + '/' + end_pos + '/' + ref + '/' + alt + '/' + gene_id;
		if (type == 'samples')
			url = '{{url('/getVarSamples')}}' + '/' + chr + '/' + start_pos + '/' + end_pos + '/' + ref + '/' + alt + '/' + patient_id + '/' + '{{$case_id}}' + '/' + '{{$type}}';
		if (type == 'cohort')
			url = '{{url('/getCohorts')}}' + '/' + patient_id + '/' + gene_id + '/{{$type}}';
			//url = '{{url('/getVarSamples')}}' + '/' + chr + '/' + start_pos + '/' + end_pos + '/' + ref + '/' + alt + '/' + patient_id + '/' + '{{$case_id}}' + '/' + '{{$type}}';
		@if (isset($file_name))
		url = url + '/hg19/upload';
		@endif 
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				//alert(data);
				console.log(data);
				data = parseJSON(data);
				if (tblVarDetail != null) {				
					tblVarDetail.destroy();
					$('#w2ui-popup #tblVarDetail').empty();
					$('#w2ui-popup .var_dtl_info').empty();
				}
				tblVarDetail = $('#w2ui-popup #tblVarDetail').DataTable( 
					{				
						"processing": true,
						"paging":   false,
						"ordering": true,
						"info":     false,
						"data": data.data,
						"columns": data.columns,									
					} );
				$("#w2ui-popup #loadingDetail").css("display","none");
				$("#w2ui-popup #table_area").css("display","block");
				//$('[data-toggle="tooltip"]').tooltip(); 
				var var_pk = chr + '_' + start_pos + '_' + end_pos + '_' + ref + '_' + alt;
				if (type == 'hgmd')
					$('#w2ui-popup .hgmdTip').tooltipster({
						 interactive: true
					});
				$('#w2ui-popup #lblVarDetailVar').html(formatLabel(chr + ':' + start_pos + '-' + end_pos + ' ' + ref + '->' + alt));
				$('#w2ui-popup #lblVarDetailGene').html(formatLabel(gene_id));
				$('#w2ui-popup #lblVarDetailPatientID').html(formatLabel(patient_id));
				if (type=='reported') {
					$('#w2ui-popup .var_dtl_info').DataTable({"paging":false});
				}


			}
		});
 
		//tblDetail.ajax.url(url).load();
	}

	function getDetailsPop(type, chr, start_pos, end_pos, ref, alt, patient_id, gene_id) {
		$("#w2ui-popup #loadingDetail").css("display","block");
		$("#w2ui-popup #table_area").css("display","none");
		url = '{!!url('/getVarDetails')!!}' + '/' + type + '/' + chr + '/' + start_pos + '/' + end_pos + '/' + ref + '/' + alt + '/' + gene_id;
		console.log(url);		
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				//alert(data);
				data = parseJSON(data);
				w2popup.message({ 
       				width   : 800, 
        			height  : 700,
        			hideOnClick: false,
        			html : '<div style="container-fluid;padding:10px">' +
    						'<table width="100%"><tr>' +
    							'<td>' +
    								'<H5>Patient&nbsp;:&nbsp;<lable id="lblVarDetailPatientID" style="color: red;">' + formatLabel(patient_id) + '</lable></H5>' +
    							'</td>' +
    							'<td>' +
    								'<H5>Variants&nbsp;:&nbsp;<lable id="lblVarDetailVar" style="color: red;">' + formatLabel(chr + ':' + start_pos + '-' + end_pos + ' ' + ref + '->' + alt) + '</lable> in gene <lable id="lblVarDetailGene" style="color: red;">' + formatLabel(gene_id) + '</lable></H5>' +
    							'</td>' +
    							'<td style="text-align:right;">' +
    								'<a class="btn btn-info py-1" href="javascript:w2popup.message();">Close</a>' +
        						'</td>' +
    						'</tr></table>' +
    						'</div>' +     						
							'<div style="padding:5px"><table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblVarDetail" style="padding:10px;width:100%;border-style:solid">' +
							'</table></div>'});

				if (tblVarDetail != null) {				
					tblVarDetail.destroy();
					$('#w2ui-popup #tblVarDetail').empty();
				}
				tblVarDetail = $('#w2ui-popup #tblVarDetail').DataTable( 
					{				
						"processing": true,
						"paging":   false,
						"ordering": false,
						"info":     false,
						"data": data.data,
						"columns": data.columns,									
					} );
				$("#w2ui-popup #loadingDetail").css("display","none");
				$("#w2ui-popup #table_area").css("display","block");
				//$('[data-toggle="tooltip"]').tooltip(); 
				var var_pk = chr + '_' + start_pos + '_' + end_pos + '_' + ref + '_' + alt;
				if (type == 'hgmd')
					$('#w2ui-popup .hgmdTip').tooltipster({
						 interactive: true
					});

			}
		});
 
		//tblDetail.ajax.url(url).load();
	}


	function showACMGGuide(var_id, gene_id, patient_id, maf, exonicfunc, loss_func, clinvar, hgmd, acmg, clinvar_path, hgmd_path, reported_muations, hotspot) {
		clearACMG();
		if (exonicfunc == "") exonicfunc = "No info";
		if (maf == '') maf = "No info";
		if (reported_muations == '') reported_muations = "No info";
		if (loss_func == '') loss_func = "N";
		if (clinvar == '') clinvar = "N";
		if (hgmd == '') hgmd = "N";
		if (clinvar_path == '') clinvar_path = "N";
		if (hgmd_path == '') hgmd_path = "N";
		if (acmg == '') acmg = "N";
		if (hotspot == '') hotspot = "N";

		acmg_var = getVarByID(var_id);
		acmg_var.id = var_id;
		acmg_var.mode = 'append';
		acmg_var.gene_id = gene_id;
		acmg_var.maf = maf;
		acmg_var.exonicfunc = exonicfunc;
		acmg_var.loss_func = loss_func;
		acmg_var.clinvar = clinvar;
		acmg_var.hgmd = hgmd;
		acmg_var.acmg = acmg;
		acmg_var.clinvar_path = clinvar_path;
		acmg_var.hgmd_path = hgmd_path;
		acmg_var.reported_muations = reported_muations;
		acmg_var.hotspot = hotspot;
		$("#loadingACMGDetail").css("display","block");
		$("#acmg_guide").css("display","none");		
		var url = '{!!url('/getACMGGuideClass')!!}' + '/' + acmg_var.chr + '/' + acmg_var.start_pos + '/' + acmg_var.end_pos + '/' + acmg_var.ref + '/' + acmg_var.alt + '/' + acmg_var.patient_id;
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
			acmg_class = parseJSON(data);
			acmg_var.classification = acmg_class.classification;
			acmg_var.checked_list = [];
			if (acmg_var.classification.toLowerCase() != 'none') {
				acmg_var.mode = 'edit';
				if (acmg_class.checked_list != null)
					acmg_var.checked_list = acmg_class.checked_list.toLowerCase().split(' ');				
			}
				
			url = '{!!url('/getACMGGuideHistory')!!}' + '/' + acmg_var.chr + '/' + acmg_var.start_pos + '/' + acmg_var.end_pos + '/' + acmg_var.ref + '/' + acmg_var.alt + '/' + acmg_var.patient_id;
			$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {					
					table_data = parseJSON(data);
					$('#pop_acmg').w2popup();
					$("#w2ui-popup").css("top","20px");
					setACMGGuideControls();
					if (acmg_var.mode == 'append')
						initACMGGuide();
					updateHistoryTable(table_data)
					//$('#w2ui-popup #txtVarComment').focus();
									
				}				
			});
			}			
		});
		
	}

	//if first time, check some items with known fact
	function initACMGGuide() {
		if (acmg_var.loss_func == 'Y')
			checkItem('pvs1');
		if (acmg_var.hotspot == 'Y')
			checkItem('ps1');
		if (acmg_var.maf == 'No Info' ||  parseFloat(acmg_var.maf) <= 0.001)
			checkItem('pm2');
		if (acmg_var.clinvar_path == 'Y' || acmg_var.hgmd_path == 'Y')
			checkItem('pp5');
		else
			checkItem('bp6');

	}

	function checkItem(item) {
		updateClass(item);
		$('#w2ui-popup #' + item).prop('checked', true);
	}

	function getStatusInfo(status) {
		var status_desc = "No comments about this variant in this patient";		
		var img_src = '{!!url('images/circle_green.png')!!}';
		if (flag_var.status == '1') {
			img_src = '{!!url('images/circle_yellow.png')!!}'
			status_desc = "This variant has been commented";
		}
		if (flag_var.status == '2') {
			img_src = '{!!url('images/circle_red.png')!!}'
			status_desc = "The comments of this variant has been closed";
		}
		return {img_src: img_src, status_desc: status_desc};
	}

	function setControls(table_data) {
		$('#w2ui-popup #lblType').text(flag_var.type);
		$('#w2ui-popup #lblVar').text(flag_var.chr + ':' + flag_var.start_pos + '-' + flag_var.end_pos + ' ' + flag_var.ref + '->' + flag_var.alt);
		$('#w2ui-popup #lblGene').text(flag_var.gene_id);
		$('#w2ui-popup #lblPatientID').text(flag_var.patient_id);
		$('#w2ui-popup #lblStatus').text(flag_var.status);
		$("#w2ui-popup #txtVarComment").val("");
		var status_info = getStatusInfo(flag_var.status);
		if (flag_var.status == '2') {
			$('#w2ui-popup #btnAddComment').addClass('disabled');
			$('#w2ui-popup #btnAddCloseComment').addClass('disabled');
		}
		$("#w2ui-popup #imgStatus").attr('src', status_info.img_src);
		$("#w2ui-popup #lblStatus").text(status_info.status_desc);
		if (tblCommentHistory != null) {
			tblCommentHistory.destroy();
			$('#w2ui-popup #tblCommentHistory').empty();
		}
		if (table_data.cols.length == 0)
			table_data = {cols:[{'title':'No data found'}],data:[['.']]};			
		
		tblCommentHistory = $('#w2ui-popup #tblCommentHistory').DataTable( 
					{				
						"processing": true,
						"paging":   false,
						"ordering": true,
						"info":     false,
						"data": table_data.data,
						"columns": table_data.cols,									
					} );
		$("#w2ui-popup #loadingCommentDetail").css("display","none");
		$("#w2ui-popup #comment_history").css("display","block");
		//$('#w2ui-popup #pop_comments').w2popup();				
	}

	function clearACMG() {
		pvsScore = 0;
		psScore = 0;
		pmScore = 0;
		ppScore = 0;
		baScore = 0;
		bsScore = 0;
		bpScore = 0;
		artScore = 0;
		acmg_var.checked_list = [];
		$('#w2ui-popup .acmg_guide').prop("checked", false);
		var classification = doClassificaiton();	
		$('#w2ui-popup #lblACMGClass').text(classification);
	}

	function setACMGGuideControls() {
		$('#w2ui-popup #lblACMGVar').html(formatLabel(acmg_var.chr + ':' + acmg_var.start_pos + '-' + acmg_var.end_pos + ' ' + acmg_var.ref + '->' + acmg_var.alt));
		$('#w2ui-popup #lblACMGGene').html(formatLabel(acmg_var.gene_id));
		$('#w2ui-popup #lblACMGPatientID').html(formatLabel(acmg_var.patient_id));
		//$('#w2ui-popup #lblACMGMAF').html(formatLabel(acmg_var.maf));
		$('#w2ui-popup #lblACMGMAF').html(getDetailPopupHref('freq', acmg_var.maf));
		$('#w2ui-popup #lblACMGExonifunc').html(formatLabel(acmg_var.exonicfunc));
		$('#w2ui-popup #lblACMGLOF').html(formatLabel(acmg_var.loss_func));
		$('#w2ui-popup #lblACMGClinvar').html(getDetailPopupHref('clinvar', acmg_var.clinvar));
		$('#w2ui-popup #lblACMGHGMD').html(getDetailPopupHref('hgmd', acmg_var.hgmd));
		$('#w2ui-popup #lblACMGACMG').html(getDetailPopupHref('acmg', acmg_var.acmg));
		$('#w2ui-popup #lblACMGReported').html(getDetailPopupHref('reported', acmg_var.reported_muations));
		$('#w2ui-popup #lblACMGHotspot').html(formatLabel(acmg_var.hotspot));
		
		$('#w2ui-popup #lblACMGClassification').text(acmg_var.classification);
		$("#w2ui-popup #txtACMGComment").val("");
		acmg_var.checked_list.forEach(function(d) {
			$('#w2ui-popup #' + d).prop('checked', true);
		});
		updateScore();
		
		//$('#w2ui-popup #pop_comments').w2popup();				
	}

	function getDetailPopupHref(type, label) {
		if (label == 'No info' || label == 'N')
			return formatLabel(label);
		return "<a href=javascript:getDetailsPop('" + type + "','" + acmg_var.chr + "','" + acmg_var.start_pos + "','" + acmg_var.end_pos + "','" + acmg_var.ref + "','" + acmg_var.alt + "','" + acmg_var.patient_id + "','" + acmg_var.gene_id + "');>" + formatLabel(label) + "</a>"
	}
	function getDetails2(type, chr, start_pos, end_pos, ref, alt, patient_id, gene_id) {		
    w2popup.message({ 
        width   : 300, 
        height  : 200,
        hideOnClick: false,
        html    : '<div style="padding: 60px; text-align: center">You must click button to hide message!</div>'+
                  '<div style="text-align: center"><button class="btn" onclick="w2popup.message()">Close</button>'});
	}

	function closePopup(source='') {
		if (source == '')
			w2popup.close();
		else
			$('#' + source).w2popup();

	}
	function formatLabel(txt) {
		return "<span class='badge rounded-pill text-bg-success'>" + txt + "</span>";
	}

	function updateHistoryTable(table_data) {
		if (tblACMGHistory != null) {
			tblACMGHistory.destroy();
			$('#w2ui-popup #tblACMGHistory').empty();
		}
		if (table_data.cols.length == 0)
			table_data = {cols:[{'title':'No data found'}],data:[['.']]};			
		
		tblACMGHistory = $('#w2ui-popup #tblACMGHistory').DataTable( 
					{				
						"processing": true,
						"paging":   false,
						"ordering": true,
						"info":     false,
						"data": table_data.data,
						"columns": table_data.cols,									
					} );
		$("#w2ui-popup #loadingACMGDetail").css("display","none");
		$("#w2ui-popup #acmg_guide").css("display","block");
	}

	function addComment(close=false) {
		var new_status = (close)? 2 : 1;
		var is_public = ($('#w2ui-popup #isCommentPublic').is(":checked"))? 'Y' : 'N';
		var comment = $("#w2ui-popup #txtVarComment").val();

		if (comment == '') {
			alert('Please input comment!');
			return;
		}
		var url = '{!!url('/addFlag')!!}' + '/' + flag_var.chr + '/' + flag_var.start_pos + '/' + flag_var.end_pos + '/' + flag_var.ref + '/' + flag_var.alt + '/' + flag_var.type + '/' + flag_var.status + '/' + new_status + '/' + flag_var.patient_id + '/' + is_public + '/' + comment;
		url = encodeURI(url);
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				table_data = parseJSON(data);
				flag_var.status = new_status;
				setControls(table_data);
				var flag_id = 'flag_' + flag_var.id;
				var img_id = 'img_' + flag_var.id;
				var status_info = getStatusInfo(flag_var.status);
				$("[id='" + img_id + "']").attr('src', status_info.img_src);
				$("[id='" + flag_id + "']").tooltipster('content', status_info.status_desc);
				updateFlagIcon(flag_var.id, flag_var.gene_id, flag_var.type, flag_var.status, status_info);
			}
		});
	}
	
	function addACMGClass() {
		var classification = $("#w2ui-popup #lblACMGClass").text();
		var is_public = ($('#w2ui-popup #isACMGPublic').is(":checked"))? 'Y' : 'N';
		//var comment = $("#w2ui-popup #txtACMGComment").val();
		var list = acmg_var.checked_list.join(' ');
		if (list == "")
			list = "null";
		acmg_var.classification = classification;
		var url = '{!!url('/addACMGClass')!!}' + '/' + acmg_var.chr + '/' + acmg_var.start_pos + '/' + acmg_var.end_pos + '/' + acmg_var.ref + '/' + acmg_var.alt + '/' + acmg_var.mode + '/' + classification + '/' + list + '/' + acmg_var.patient_id + '/' + is_public;
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				w2alert("Save successful!");
				table_data = parseJSON(data);
				acmg_var.mode='edit';
				//flag_var.status = new_status;
				//clearACMG();
				updateHistoryTable(table_data);
				updateACMGLabel(acmg_var);
				//var acmg_id = 'acmg_' + acmg_var.chr + '_' + acmg_var.start_pos + '_' + acmg_var.end_pos + '_' + acmg_var.ref + '_' + acmg_var.alt + '_' + acmg_var.patient_id;
				//$('#' + acmg_id).text(acmg_var.classification);
				//var img_id = 'img_' + flag_id;
				//$("#" + flag_id).attr('title', status_info.status_desc);
				//$("#" + img_id).attr('src', status_info.img_src);
				//$("#" + acmg_id).tooltipster('content', acmg_var.classification);				
				//console.log(img_id);
			}
		});
	}

	var pvsScore = 0;
	var psScore = 0;
	var pmScore = 0;
	var ppScore = 0;
	var baScore = 0;
	var bsScore = 0;
	var bpScore = 0;
	var artScore = 0;
	var acmg_var = {};
	
	function getAMCGCategory(item) {
		if (item == 'pp1_s') return 'ps';
		if (item == 'pp1_m') return 'pm';
		if (item == 'art') return 'art';
		return item.substring(0, item.length - 1);

	}
	
	function updateScore() {
		acmg_var.checked_list.forEach(function(d) {
			var cat = getAMCGCategory(d);
			//alert(cat + 'Score' + '+=1');
			eval(cat + 'Score' + '+=1');
		});
		$('#w2ui-popup #lblACMGClass').text(doClassificaiton());
	}

	function copyACMG(checked_list) {
		clearACMG();
		acmg_var.checked_list = checked_list.toLowerCase().split(' ');
		setACMGGuideControls();
	}

	function doClassificaiton() {
		acmgArtifact();
		acmgPathogenic();
		acmgBenign();
		var classification = acmgCompare();
		return classification;
	}

	function updateClass(item) {
		var cat = getAMCGCategory(item);
		var op = $('#w2ui-popup #' + item).is(":checked")? '+' : '-';
		if (op == "+")
			acmg_var.checked_list.push(item);
		else
			removeElement(acmg_var.checked_list, item);
		eval(cat + 'Score' + op + '=1');	
		var classification = doClassificaiton();	
		$('#w2ui-popup #lblACMGClass').text(classification);
		acmg_var.classification = classification;
	}
	
	var pathAssign = "none";
	var pathogenicACMG = "VUS";
	var acmgPathogenic = function() {
			if (pvsScore == 1 && psScore >= 1) {
				pathogenicACMG = "Pathogenic (Ia)";
				pathAssign = "assigned";
				} else if (pvsScore == 1 && pmScore >= 2) {
				pathogenicACMG = "Pathogenic (Ib)";
				pathAssign = "assigned";
				} else if (pvsScore == 1 && pmScore == 1 && ppScore == 1) {
				pathogenicACMG = "Pathogenic (Ic)";
				pathAssign = "assigned";
				} else if (pvsScore == 1 && ppScore >= 2) {
				pathogenicACMG = "Pathogenic (Id)";
				pathAssign = "assigned";
				} else if (psScore >= 2) {
				pathogenicACMG = "Pathogenic (II)";
				pathAssign = "assigned";
				} else if (psScore == 1 && pmScore >= 3) {
				pathogenicACMG = "Pathogenic (IIIa)";
				pathAssign = "assigned";
				} else if (psScore == 1 && pmScore == 2 && ppScore >= 2) {
				pathogenicACMG = "Pathogenic (IIIb)";
				pathAssign = "assigned";
				} else if (psScore == 1 && pmScore == 1 && ppScore >= 4) {
				pathogenicACMG = "Pathogenic (IIIc)";
				pathAssign = "assigned";
				} else if (pvsScore == 1 && pmScore == 1) {
				pathogenicACMG = "Likely pathogenic (I)";
				pathAssign = "assigned";
				} else if (psScore == 1 && (pmScore == 1 || pmScore == 2)) {
				pathogenicACMG = "Likely pathogenic (II)";
				pathAssign = "assigned";
				} else if (psScore == 1 && ppScore >= 2) {
				pathogenicACMG = "Likely pathogenic (III)";
				pathAssign = "assigned";
				} else if (pmScore >= 3) {
				pathogenicACMG = "Likely pathogenic (IV)";
				pathAssign = "assigned";
				} else if (pmScore == 2 && ppScore >= 2) {
				pathogenicACMG = "Likely pathogenic (V)";
				pathAssign = "assigned";
				} else if (pmScore == 1 && ppScore >= 4) {
				pathogenicACMG = "Likely pathogenic (VI)";
				pathAssign = "assigned";
				} else {
				pathogenicACMG = "VUS";
				pathAssign = "none";
				}
		};

		//Algorithm to assign benign status by ACMG standards
		var benAssign = "none";
		var benignACMG = "VUS";
		var acmgBenign = function() {
				if (baScore == 1){
					benignACMG = "Benign (I)";
					benAssign = "assigned";
					} else if (bsScore >= 2) {
					benignACMG = "Benign (II)";
					benAssign = "assigned";
					} else if (bsScore == 1 && bpScore == 1) {
					benignACMG = "Likely benign (I)";
					benAssign = "assigned";
					} else if (bpScore >= 2) {
					benignACMG = "Likely benign (II)";
					benAssign = "assigned";
					} else {
					benignACMG = "VUS";
					benAssign = "none"
					}
			};
			
		//Algorithm to assign artifact status by ACMG standards
		var artAssign = "none";
		var artACMG = "VUS";
		var acmgArtifact = function() {
				if (artScore == 1){
					artACMG = "Artifact";
					artAssign = "assigned";
					} else {
					artACMG = "VUS";
					artAssign = "none"
					}
			};

		//Function to output pathogenicity to screen
		var ClassificationACMG = "";
		var acmgCompare = function(){
				if (artAssign == "assigned"){
					ClassificationACMG = artACMG;
					} else if (pathAssign == "assigned" && benAssign == "assigned"){
					ClassificationACMG = "VUS - conflicting evidence";
					} else if (pathAssign == "assigned"){
					ClassificationACMG = pathogenicACMG;
					} else if (benAssign == "assigned"){
					ClassificationACMG = benignACMG;
					} else {
					ClassificationACMG = "VUS - not enough evidence";
					};
				return ClassificationACMG;
		};

</script>
<!-- mutation definition popup window-->
@if (isset($file_name))
<form style="display: hidden" action='{!!url('/downloadVariantsFromUpload')!!}' method="POST" target="_blank" id="downloadHiddenform">
	@csrf
	<input type="hidden" id="file_name" name="file_name" value='{!!$file_name!!}'/>
	<input type="hidden" id="type" name="type" value='{!!$type!!}'/>
	<input type="hidden" id="flag" name="flag" value="N"/>
	<input type="hidden" id="var_list" name="var_list" value=""/>
	<input type="hidden" name="_token" value="{{ csrf_token() }}" />
</form>
@else
<form style="display: hidden" action='{!!url('/downloadVariants')!!}' method="POST" target="_blank" id="downloadHiddenform">
	@csrf
	<input type="hidden" id="project_id" name="project_id" value='{!!$project_id!!}'/>
	<input type="hidden" id="patient_id" name="patient_id" value='{!!$patient_id!!}'/>
	<input type="hidden" id="gene_id" name="gene_id" value='{!!$gene_id!!}'/>
	<input type="hidden" id="sample_id" name="sample_id" value='{!!$sample_id!!}'/>
	<input type="hidden" id="case_id" name="case_id" value='{!!$case_id!!}'/>
	<input type="hidden" id="type" name="type" value='{!!$type!!}'/>
	<input type="hidden" id="flag" name="flag" value="N"/>
	<input type="hidden" id="var_list" name="var_list" value=""/>
	<input type="hidden" name="_token" value="{{ csrf_token() }}" />
</form>
@endif
<div id="filter_definition" style="display: none; position: absolute; left: 10px; top: 10px; width:85%;min-height:500px;max-height:700px;overflow: auto; background-color:white;padding:10px">
		<H4>
		The definition of filters:<HR>
		</H4>
		<table>
			@foreach ($filter_definition as $filter_name=>$content)
			<tr valign="top"><td><font color="blue">{!!$filter_name!!}:</font></td><td>{!!$content!!}</td></tr>
			@endforeach
		</table>

</div>


<div id="pop_acmg" style="display: none; width: 1000px; height: 800px; overflow: auto; background-color=white;">
    <div rel="title">
        ACMG Classification Tool
    </div>
    <div rel="body" style="text-align:left;">
    	<div class="container-fluid">
    		<div class="row">    			
    			<div class="col-md-1">
    				<H5>Patient:</H5>
    			</div>
    			<div class="col-md-2">
    				<H5><lable id="lblACMGPatientID" style="color: red;"></lable></H5>
    			</div>
    			<div class="col-md-2">
    				<H5>Variant:</H5>
    			</div>
    			<div class="col-md-7">
    				<H5><lable id="lblACMGVar" style="color: red;"></lable> in gene <lable id="lblACMGGene" style="color: red;"></lable></H5>
    			</div>
    		</div>
    		<HR>
    		<div class="row">
    			<div class="col-md-1">
    				<H5>MAF:</H5>
    			</div>
    			<div class="col-md-2">
    				<H5><lable id="lblACMGMAF" style="color: red;"></lable></H5>
    			</div>
    			<div class="col-md-2">
    				<H5>Exonic function:</H5>
    			</div>
    			<div class="col-md-3">
    				<H5><lable id="lblACMGExonifunc" style="color: red;"></lable></H5>
    			</div>
    			<div class="col-md-1">    		
    				<H5>LOF:</H5>
    			</div>
    			<div class="col-md-1">    		
    				<H5><lable id="lblACMGLOF" style="color: red;"></lable></H5>
    			</div>
    			<div class="col-md-1">
    				<H5>ACMG:</H5>
    			</div>
    			<div class="col-md-1">
    				<H5><lable id="lblACMGACMG" style="color: red;"></lable></H5>
    			</div>
    		</div>
    		<div class="row">
    			<div class="col-md-1">
    				<H5>Clinvar:</H5>
    			</div>
    			<div class="col-md-2">
    				<H5><lable id="lblACMGClinvar" style="color: red;"></lable></H5>
    			</div>
    			<div class="col-md-2">
    				<H5>HGMD:</H5>
    			</div>
    			<div class="col-md-3">
    				<H5><lable id="lblACMGHGMD" style="color: red;"></lable></H5>
    			</div>
    			<div class="col-md-1">
    				<H5>Reported:</H5>
    			</div>
    			<div class="col-md-1">
    				<H5><lable id="lblACMGReported" style="color: red;"></lable></H5>
    			</div>
    			<div class="col-md-1">
    				<H5>Hotspot:</H5>
    			</div>
    			<div class="col-md-1">
    				<H5><lable id="lblACMGHotspot" style="color: red;"></lable></H5>
    			</div>
    		</div>
    		<!--div class="row">
				<div class="col-md-9">
        			<textarea id="txtACMGComment" rows=2 cols=50 placeholder="Comment..." class="form-control"></textarea>
        			<input type="checkbox" id="isACMGPublic" checked>&nbsp;Public</input>
        		</div>        		
        	</div-->
        	<HR>
        	<div class="row">        		
        		<div class="col-md-7">
        			<H4>
        			<a width="500" target=_blank href="{!!url('/images/ACMG.png')!!}" title="ACMG definitions" class="mytooltip acmg_definition"><img src="{!!url('/images/help.png')!!}"></a>&nbsp;
        			Classification&nbsp;:&nbsp;<lable id="lblACMGClass" style="color: red;"></lable></H4>
        		</div>
        		<div class="col-md-5 text-right">
        			<input type="checkbox" id="isACMGPublic" checked>&nbsp;Public</input>
        			<button id="btnClearACMG" onclick="clearACMG();" class="btn btn-info" >Clear</button>
        			<button id="btnAddACMGClass" onclick="addACMGClass();" class="btn btn-warning" >Save ACMG Classification</button>
        		</div>        		        		
        	</div>
        	<HR>
        	<div class="row">
		        <div id='loadingACMGDetail' style="display:none">
					<img src='{!!url('/images/ajax-loader.gif')!!}'></img>
				</div>
				<div id="acmg_guide">
					<p class="pvs">
					<input type="checkbox" class="acmg_guide" id="pvs1" onclick="updateClass('pvs1');"></input> PVS1 null variant (nonsense, frameshift, canonical ±1 or 2 splice sites, initiation codon, single or multiexon deletion) in a gene where LOF is a known mechanism of disease<br>
					</p>
					<p class="pvs">
					<input type="checkbox" class="acmg_guide" id="ps1" onclick="updateClass('ps1');"></input> PS1 Same amino acid change as a previously established pathogenic variant regardless of nucleotide change<br>
					<input type="checkbox" class="acmg_guide" id="ps2" onclick="updateClass('ps2');"></input> PS2 De novo (both maternity and paternity confirmed) in a patient with the disease and no family history<br>
					<input type="checkbox" class="acmg_guide" id="ps3" onclick="updateClass('ps3');"></input> PS3 Well-established in vitro or in vivo functional studies supportive of a damaging effect on the gene or gene product<br>
					<input type="checkbox" class="acmg_guide" id="ps4" onclick="updateClass('ps4');"></input> PS4 The prevalence of the variant in affected individuals is significantly increased compared with the prevalence in controls<br>
					<input type="checkbox" class="acmg_guide" id="pp1_s" onclick="updateClass('pp1_s');"></input> PP1 (Strong evidence) Cosegregation with disease in multiple affected family members in a gene definitively known to cause the disease<br>
					</p>
					<p class="pm">
					<input type="checkbox" class="acmg_guide" id="pm1" onclick="updateClass('pm1');"></input> PM1 Located in a mutational hot spot and/or critical and well-established functional domain (e.g., active site of an enzyme) without benign variation<br>
					<input type="checkbox" class="acmg_guide" id="pm2" onclick="updateClass('pm2');"></input> PM2 Absent from controls (or at extremely low frequency if recessive) in Exome Sequencing Project, 1000 Genomes Project, or Exome Aggregation Consortium<br>
					<input type="checkbox" class="acmg_guide" id="pm3" onclick="updateClass('pm3');"></input> PM3 For recessive disorders, detected in trans with a pathogenic variant<br>
					<input type="checkbox" class="acmg_guide" id="pm4" onclick="updateClass('pm4');"></input> PM4 Protein length changes as a result of in-frame deletions/insertions in a nonrepeat region or stop-loss variants<br>
					<input type="checkbox" class="acmg_guide" id="pm5" onclick="updateClass('pm5');"></input> PM5 Novel missense change at an amino acid residue where a different missense change determined to be pathogenic has been seen before<br>
					<input type="checkbox" class="acmg_guide" id="pm6" onclick="updateClass('pm6');"></input> PM6 Assumed de novo, but without confirmation of paternity and maternity<br>
					<input type="checkbox" class="acmg_guide" id="pp1_m" onclick="updateClass('pp1_m');"></input> PP1 (Moderate evidence) Cosegregation with disease in multiple affected family members in a gene definitively known to cause the disease<br>
					</p>
					<p class="pp">
					<input type="checkbox" class="acmg_guide" id="pp1" onclick="updateClass('pp1');"></input> PP1 Cosegregation with disease in multiple affected family members in a gene definitively known to cause the disease<br>
					<input type="checkbox" class="acmg_guide" id="pp2" onclick="updateClass('pp2');"></input> PP2 Missense variant in a gene that has a low rate of benign missense variation and in which missense variants are a common mechanism of disease<br>
					<input type="checkbox" class="acmg_guide" id="pp3" onclick="updateClass('pp3');"></input> PP3 Multiple lines of computational evidence support a deleterious effect on the gene or gene product (conservation, evolutionary, splicing impact, etc.)<br>
					<input type="checkbox" class="acmg_guide" id="pp4" onclick="updateClass('pp4');"></input> PP4 Patient’s phenotype or family history is highly specific for a disease with a single genetic etiology<br>
					<input type="checkbox" class="acmg_guide" id="pp5" onclick="updateClass('pp5');"></input> PP5 Reputable source recently reports variant as pathogenic, but the evidence is not available to the laboratory to perform an independent evaluation
					</p>
					<p class="bp">
					<input type="checkbox" class="acmg_guide" id="bp1" onclick="updateClass('bp1');"></input> BP1 Missense variant in a gene for which primarily truncating variants are known to cause disease<br>
					<input type="checkbox" class="acmg_guide" id="bp2" onclick="updateClass('bp2');"></input> BP2 Observed in trans with a pathogenic variant for a fully penetrant dominant gene/disorder or observed in cis with a pathogenic variant in any inheritance pattern<br>
					<input type="checkbox" class="acmg_guide" id="bp3" onclick="updateClass('bp3');"></input> BP3 In-frame deletions/insertions in a repetitive region without a known function<br>
					<input type="checkbox" class="acmg_guide" id="bp4" onclick="updateClass('bp4');"></input> BP4 Multiple lines of computational evidence suggest no impact on gene or gene product (conservation, evolutionary, splicing impact, etc.)<br>
					<input type="checkbox" class="acmg_guide" id="bp5" onclick="updateClass('bp5');"></input> BP5 Variant found in a case with an alternate molecular basis for disease<br>
					<input type="checkbox" class="acmg_guide" id="bp6" onclick="updateClass('bp6');"></input> BP6 Reputable source recently reports variant as benign, but the evidence is not available to the laboratory to perform an independent evaluation<br>
					<input type="checkbox" class="acmg_guide" id="bp7" onclick="updateClass('bp7');"></input> BP7 A synonymous (silent) variant for which splicing prediction algorithms predict no impact to the splice consensus sequence nor the creation of a new splice site AND the nucleotide is not highly conserved<br>
					</p>
					<p class="bs">
					<input type="checkbox" class="acmg_guide" id="bs1" onclick="updateClass('bs1');"></input> BS1 Allele frequency is greater than expected for disorder<br>
					<input type="checkbox" class="acmg_guide" id="bs2" onclick="updateClass('bs2');"></input> BS2 Observed in a healthy adult individual for a recessive (homozygous), dominant (heterozygous), or X-linked (hemizygous) disorder, with full penetrance expected at an early age<br>
					<input type="checkbox" class="acmg_guide" id="bs3" onclick="updateClass('bs3');"></input> BS3 Well-established in vitro or in vivo functional studies show no damaging effect on protein function or splicing<br>
					<input type="checkbox" class="acmg_guide" id="bs4" onclick="updateClass('bs4');"></input> BS4 Lack of segregation in affected members of a family<br>
					</p>
					<p class="bs">
					<input type="checkbox" class="acmg_guide" id="ba1" onclick="updateClass('ba1');"></input> BA1 Allele frequency is >5% in Exome Sequencing Project, 1000 Genomes Project, or Exome Aggregation Consortium<br>
					</p>
					<p class="art">
					<input type="checkbox" class="acmg_guide" id="art" onclick="updateClass('art');"></input> Sequencing artifact as determined by depth, quality, or other previously reviewed data<br>
					</p>
					<H4>History</H4>
					<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblACMGHistory" style='width:100%'></table>
				</div>
			</div>
		</div>	
    </div>    
</div>

<div id="pop_comments" style="display: none; width: 800px; height: 800px; overflow: auto; background-color=white;">
    <div rel="title">
        Comments of <lable id="lblType" style="color: red;"></lable> mutation
    </div>
    <div rel="body" style="text-align:left;">
    	<div class="container-fluid">
    		<div class="row">
    			<div class="col-md-3">
    				<H5>Variants: </H5>
    			</div>
    			<div class="col-md-9">
    				<H5><lable id="lblVar" style="color: red;"></lable> in gene <lable id="lblGene" style="color: red;"></lable></H5>
    			</div>
    		</div>
    		<div class="row">
    			<div class="col-md-3">
    				<H5>Patient: </H5>
    			</div>
    			<div class="col-md-9">
    				<H5><lable id="lblPatientID" style="color: red;"></lable></H5>
    			</div>
    			<div class="col-md-3">
    				<H5>Status: </H5>
    			</div>
    			<div class="col-md-9">
    				<H5><img id="imgStatus" width=18 height=18></img><lable id="lblStatus"></lable></H5>
    			</div>
    		</div>
			<div class="row">
				<div class="col-md-12">
        			<textarea id="txtVarComment" rows=4 cols=50 placeholder="Comment..." class="form-control"></textarea>
        			<input type="checkbox" id="isCommentPublic" checked>&nbsp;Public comment</input>
        		</div>
        	</div>
        	<br>
        	<div class="row">
        		<div class="col-md-6">
        			<button id="btnAddComment" onclick="addComment();" class="btn btn-warning" >Add comment</button>
        		</div>        		
        		<div class="col-md-6">
        			<!--button id="btnAddCloseComment" onclick="addComment(true);" class="btn btn-danger" >Close comments</button-->
        		</div>
        	</div>
        	<HR>
        	<div class="row">
		        <H3>History</H3>
		        <div id='loadingCommentDetail' style="display:none">
					<img src='{!!url('/images/ajax-loader.gif')!!}'></img>
				</div>
				<div id="comment_history">
					<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblCommentHistory" style='width:100%'></table>
				</div>
			</div>
		</div>	
    </div>    
</div>

<div id="pop_signout_history" style="display: none; width: 800px; height: 600px; overflow: auto; background-color=white;">
    <div rel="body" style="text-align:left;">
    	<div class="container-fluid">
    		<HR>
        	<div class="row">         	
        		<div class="col-md-10">
    				<H3>Signout History</H3>
    			</div>
    			<div class="col-md-2">
    				<a class="btn btn-info" style="width: 50px;" href="javascript:closePopup();">Close</a>
    			</div>
    		</div>    		  
    		<div class="row">   
		        <div id='loadingSignoutHistory' style="display:none">
					<img src='{!!url('/images/ajax-loader.gif')!!}'></img>
				</div>				
				<div id="signout_history">
					<HR>
					<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblSignoutHistory" style='width:100%'></table>
				</div>
			</div>
		</div>	
    </div>    
</div>

<div id="pop_var_details" style="display: none; position: absolute; left: 10px; top: 10px; width:70%;min-height:720px;max-height:800px;overflow: auto; padding:5px">
    <div rel="body" style="text-align:left;background-color:#f2f2f2;">
    	<div id='loadingDetail'>
				<img src='{!!url('/images/ajax-loader.gif')!!}'></img>
		</div>		
    	<div id='table_area'>
    		<div class="container-fluid" style="padding:10px">
    			<div class="row">
					<div class="col-md-12">
						<div class="card" style="overflow:none;margin: 0 auto; padding: 10px 30px 10px 30px;">
			    			<span>Patient&nbsp;:&nbsp;<label id="lblVarDetailPatientID" style="color: red;"></label></span>
				    		<span>Variants&nbsp;:&nbsp;<label id="lblVarDetailVar" style="color: red;"></label> in gene <label id="lblVarDetailGene" style="color: red;"></label>
				    		<a class="btn btn-info" style="style="width: 50px;"float:right;height:25px;padding: 3px 10px;" href="javascript:closePopup();">Close</a></span>
			    		</div>
		    		</div>
		    	</div>
		    	<div class="row">
					<div class="col-md-12">
						<div class="card" style="overflow:auto;min-height:400px;margin: 0 auto; padding: 10px 30px 10px 30px;">
    						<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblVarDetail" style='width:100%'>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<div id='loadingVar' style="height:{!!($gene_id=='null')?90:70!!}%">
	<img src='{!!url('/images/ajax-loader.gif')!!}'></img>
</div>
<lable id="lblMessage" style="font-size: 200%;"></lable>
<div id="var_content" style="background-color:#f2f2f2;height:100%;width:100%;text-align:left;display:none">
	<div class="container-fluid" style="background-color:#f2f2f2;">
				@if ($gene_id != 'null')
				<div class="row">
					<div class="col-md-12">					
						<div id="plot_area" class="card" style="padding: 5px 20px 5px 20px;height:250px;solid;overflow:auto;margin: 5 auto;display:none;">
							<div id="mut_plot"></div>			
						</div>									
					</div>
				</div>
				@endif
				<div class="row">
					<div class="col-md-12">
						<div class="card" style="overflow:auto;margin: 0 auto; padding: 5px 20px 5px 20px;">
							<span id='filter' style='display: inline;'>
								<button id="btnAddFilter" class="btn btn-primary">Add filter</button>&nbsp;<a id="fb_filter_definition" href="javascript:showFilterDefinition();" title="Filter definitions" class="mytooltip"><img src={!!url("images/help.png")!!}></img></a>&nbsp;						
							
							<button id="btnClearFilter" type="button" class="btn btn-secondary" >Show all</button>
							<button id="btnResetFilter" type="button" class="btn btn-secondary" >Reset</button>							
							@if ($gene_id == 'null')
								<a target=_blank href="{!!Request::url()!!}" class="btn btn-secondary" role="button" >Open in new tab</a>
							@endif
							@if (isset($new_avia_cnt))
								@if ($new_avia_cnt != 0)
									<font color="red">&nbsp;{!!$new_avia_cnt!!} novel variants are under annotation processing</font>
								@else
									<font color="red">&nbsp;The annotation of all varaints has been processed</font>
								@endif
							@endif
							&nbsp;&nbsp;
							@if (isset($other_info))
								@foreach ($other_info as $header => $value)
								<font color="blue">{!!$header!!}:</font>&nbsp;{!!$value!!}
								&nbsp;&nbsp;
								@endforeach
							@endif
							<span style="font-family: monospace; font-size: 20;float:right;"><!--span id="signout_label" style="display:none">Signout status: <label id="lblCaseStatus" style="color:red"></label></span-->
										@if ($sample_id != "null" && \App\Models\User::isSignoutManager() && $type != "rnaseq")
											<button id="btnP1Signout" class="btn btn-primary" style="display:none">Phase I Signout</button>
											<button id="btnP2Signout" class="btn btn-primary" style="display:none">Phase II Signout</button>
											<button id="btnReopen" class="btn btn-primary" style="display:none">Reopen</button>
										@endif
										<button id="btnSignoutHistroy" style="display:none" class="btn btn-info">Signout History</button>
										<button id="btnDownload" class="btn btn-info"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Download</button>&nbsp;
										@if ($gene_id != 'null')
										<button id="btnDownloadSVG" class="btn btn-warning"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;SVG</button>
										&nbsp;Patients: <span id="lblCountPatients" style="text-align:left;color:red;" text=""></span>/<span id="lblCountTotalPatients" style="text-align:left;" text=""></span>
										@endif
										&nbsp;Variants: <span id="lblCountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="lblCountTotal" style="text-align:left;" text=""></span>
							</span></span>
						</div>
					</div>
				</div>
				<div class="row">
					<div class="col-md-12">
						<div class="card" style="overflow:auto;min-height:400px;margin: 0 auto; padding: 20px 10px 20px 10px;">
							<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblOnco" style='width:100%;'></table>
						</div>
					</div>
				</div>			
	</div>
</div>		

{!! HTML::script('packages/muts-needle-plot/build/muts-needle-plot.js') !!}
{!! HTML::script('packages/muts-needle-plot/src/js/dependencies/d3.js') !!}
{!! HTML::script('packages/muts-needle-plot/src/js/d3-svg-legend.js') !!}
{!! HTML::script('packages/muts-needle-plot/src/js/dependencies/underscore.js') !!}


@stop
