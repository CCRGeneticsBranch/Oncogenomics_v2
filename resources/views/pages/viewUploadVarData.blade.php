@extends('layouts.default')
@section('content')

{{ HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::style('packages/w2ui/w2ui-1.4.min.css') }}

{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('js/upload.js') }}
{{ HTML::script('packages/w2ui/w2ui-1.4.min.js')}}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') }}
{{ HTML::script('js/onco.js') }}


<link href="https://hayageek.github.io/jQuery-Upload-File/4.0.10/uploadfile.css" rel="stylesheet">

<style>

.textbox .textbox-text {
	font-size: 14px;	
}

.tree-title {
	font-size: 14px;
}

</style>
<script type="text/javascript">
	
	var vcf_list = {};
	var exp_list = {};
	var fusion_data = null;
	//list data shown in combobox
	var patient_list = [];
	var project_list = [];
	var case_list = [];
	var selected_project;
	var selected_diagnosis;
	var selected_patient;
	var projects = {{$projects}};
	for (var i in projects.names) {
		project_list.push({value:projects.names[i], text: i});
	}
	$(document).ready(function() {
		addVCF();
		addExp();
		addFusion();
		$('#selCaseList').combobox({
	        panelHeight: '200px',
	        selectOnNavigation: false,
	        editable: true,
	        
	        onSelect: function(d) {		        	
	        	var selected_case = d.text
	        			        	
	        },
	        
	        data: case_list
		});	
		$('#selPatientList').combobox({
	        panelHeight: '200px',
	        selectOnNavigation: false,
	        editable: true,
	        
	        onSelect: function(d) {		        	
	        	selected_patient = d.value
	        	var case_list = [];
	        	selected_diagnosis=Object.keys(projects[selected_project][selected_patient])[0]
				var selected_cases = objAttrToArray(projects[selected_project][selected_patient][selected_diagnosis]).sort();
	        	selected_case=""
				selected_cases.forEach(function(d) {
					var path=Object.keys(projects[selected_project][selected_patient][selected_diagnosis][d])[0]
					if(path=="uploads"){
						selected_case = d;
						case_list.push({value: d, text: d});
						$('#selCaseList').combobox('setValue', selected_case);
					}
				})

				$('#selCaseList').combobox('loadData', case_list);
				$('#selCaseList').combobox('setValue', selected_case);
				$('#diagnosis').combotree('setValue',selected_diagnosis)

	        },
	        
	        data: patient_list
		});	

		$('#selProjectList').combobox({
		        
		        onSelect: function(d) {	
		        	selected_project =d.value;
					var selected_patients = objAttrToArray(projects[selected_project]).sort();
		        	patient_list = [];
		        	selected_patient = selected_patients[0];
					selected_patients.forEach(function(d) {
						patient_list.push({value: d, text: d});
					})
					$('#selPatientList').combobox('loadData', patient_list);
		        	$('#selPatientList').combobox('setValue', selected_patient);
		        	selected_diagnosis=Object.keys(projects[selected_project][selected_patient])[0]
		        	$('#selPatientList').combobox('setValue', selected_patient);
		        	$('#diagnosis').combotree('setValue',selected_diagnosis)

		        },
		        data: project_list
		});


		$('#diagnosis').combotree({
				url : '{{url('/getOncoTree')}}',
				panelHeight: '500px',
				height : '32px',
				method : 'get',  
			    valueField : 'text',  
			    textField : 'text',  
			    editable:true ,  
			    required: true,
			   	onSelect: function(d) {
			   		selected_diagnosis = d.text
			   	}
		});
		$('#selProjectList').combobox('setValue', project_list[0].value);

		(function(){  
	        $.fn.combotree.defaults.editable = true;  
	        $.extend($.fn.combotree.defaults.keyHandler,{  
	            up:function(){  
	                console.log('up');  
	            },  
	            down:function(){  
	                console.log('down');  
	            },  
	            enter:function(){  
	                console.log('enter');  
	            },  
	            query:function(q){  
	                var t = $(this).combotree('tree');  
	                var nodes = t.tree('getChildren');  
	                for(var i=0; i<nodes.length; i++){  
	                    var node = nodes[i];  
	                    if (node.text.toLowerCase().indexOf(q.toLowerCase()) >= 0){  
	                        $(node.target).show();  
	                    } else {  
	                        $(node.target).hide();  
	                    }  
	                }  
	                var opts = $(this).combotree('options');  
	                if (!opts.hasSetEvents){  
	                    opts.hasSetEvents = true;  
	                    var onShowPanel = opts.onShowPanel;  
	                    opts.onShowPanel = function(){  
	                        var nodes = t.tree('getChildren');  
	                        for(var i=0; i<nodes.length; i++){  
	                            $(nodes[i].target).show();  
	                        }  
	                        onShowPanel.call(this);  
	                    };  
	                    $(this).combo('options').onShowPanel = opts.onShowPanel;  
	                }  
	            }  
	        });  
	    })(jQuery);

		$("#tumor_loader").uploadFile({
			url:"{{url('/uploadVarData')}}",
			fileName:"myfile"
		});

		$("#rnaseq_loader").uploadFile({
			url:"{{url('/uploadVarData')}}",
			fileName:"myfile"
		});

		$("#btnSave").click(function() {
			var type=$('input[name=upload]:checked').val();
			if(type!=="pre"){	
				if ($("#patient_id").val() == "") {
					w2alert("<H4>Please input patient ID!</H4>");
					return;
				}
				if ($("#case_id").val() == "") {
					w2alert("<H4>Please input case name!</H4>");
					return;
				}
			}
			else{
				var case_type=$('input[name=case_type]:checked').val();
				if(case_type!="pre"){
					if ($("#case_id_pre").val() == "") {
						w2alert("<H4>Please input case name!</H4>");
						return;
					}
				}
			}
			if ($("#diagnosis").combobox('getText') == "") {
				w2alert("<H4>Please input diagnosis!</H4>");
				return;
			}
		

			var vcfs = {};
			var tumor_sample_id = "";
			var normal_sample_id = "";
			var rnaseq_sample_id = "";
			if (Object.keys(vcf_list).length == 0 && Object.keys(exp_list).length == 0 && Object.keys(fusion_data).length ==0) {
				w2alert("<H4>Please upload files!</H4>");
				return;
			}
			for (var key in vcf_list) {
				var sample_data = [];
				for (var i in vcf_list[key].samples) {
					var d = vcf_list[key].samples[i];
					var sample_id = $("#" + key + "_" + d + "_name").val();
					var tissue_cat = $("#" + key + "_" + d + "_tissue_cat").val();
					var material_type = $("#" + key + "_" + d + "_material_type").val();
					if (tissue_cat.toLowerCase() == "normal" && material_type.toLowerCase() == "dna") {
						if (normal_sample_id != "" && sample_id != normal_sample_id) {
							w2alert("<H4>Only one normal DNA sample accepted!</H4>");
							return;
						} else {
							normal_sample_id = sample_id;	
						}
					}
					if (tissue_cat.toLowerCase() == "tumor" && material_type.toLowerCase() == "dna") {
						if (tumor_sample_id != "" && sample_id != tumor_sample_id) {
							w2alert("<H4>Only one tumor DNA sample accepted!</H4>");
							return;
						} else {
							tumor_sample_id = sample_id;
						}
					} 
					if (material_type.toLowerCase() == "rna") {
						if (rnaseq_sample_id != "" && sample_id != rnaseq_sample_id) {
							w2alert("<H4>Only one RNA sample accepted!</H4>");
							return;
						}
						else {
							rnaseq_sample_id = sample_id;
						}
					} 
					sample_data.push({sample_id_vcf : d, sample_id: sample_id, tissue_cat : tissue_cat, material_type : material_type});
				}
				vcfs[vcf_list[key].file_name] = {caller : vcf_list[key].caller, type : $("#" + key + "_type").val(), samples: sample_data };				
			}
			var exps = {};
			for (var key in exp_list) {
				if ($("#exp_sample_name").val() == "") {
					w2alert("<H4>Please input expression sample name!</H4>");
					return;
				}
				exps[exp_list[key].file_name] = {level: exp_list[key].level, type: exp_list[key].type}
			}

			if (fusion_data != null) {
				if ($("#fusion_sample_name").val() == "") {
					w2alert("<H4>Please input fusion sample name!</H4>");
					return;
				}
			}
		var type=$('input[name=upload]:checked').val();
		if (type=="pre"){
			patient_id= $("#selPatientList").combobox('getText')
			var case_type=$('input[name=case_type]:checked').val();
			if (case_type=="pre")			
				case_id=$("#selCaseList").combobox('getText')
			else
				case_id=$("#case_id_pre").val();
		}
		else{
			patient_id=$("#patient_id").val();
			case_id=$("#case_id").val();
		}
			var data = {
						project_id: selected_project, 
						override:'N', 
						patient_id: patient_id, 
						case_id: case_id, 
						diagnosis: $("#diagnosis").combobox('getText'), 
						exp_type: $("#selExpType").val(), 
						vcfs: vcfs, 
						var_samples: {normal_sample_id: normal_sample_id, tumor_sample_id: tumor_sample_id, rnaseq_sample_id: rnaseq_sample_id}, 
						exp_sample_id: $("#exp_sample_name").val(), 
						exp_tissue_cat: $("#selExpTissueCat").val(), 
						exp_library_type: $("#selExpLibType").val(), 
						exps: exps,
						fusion_sample_id: $("#fusion_sample_name").val(), 
						fusion_tissue_cat: $("#selFusionTissueCat").val(), 
						fusion_library_type: $("#selFusionLibType").val(),
						fusion_data : fusion_data 
					};
			console.log(JSON.stringify(data));
			var url = '{{url("/processVarUpload")}}';
			w2popup.open({body: "<img src='{{url('/images/ajax-loader.gif')}}'></img><H3>Processing data...</H3>", height: 200});
			var t0 = performance.now();
			$.ajax({ url: url, async: true, type: 'POST', dataType: 'text', data: data, success: function(data) {
					var results = JSON.parse(data);
					w2popup.close();
					var t1 = performance.now();
					console.log("Processing time " + (t1 - t0) + " milliseconds.")
					if (results.code == "no_user") {
						w2alert("<H4>Please login first!</H4>");
						return;
					}
					if (results.code == "success") {
						console.log(results.desc);
						w2alert("<H4>Successful! Please <a target='_blank' href='{{url('/viewPatient')}}" + "/" + $("#selProjects").val() + "/" + $("#patient_id").val() + "/" + $("#case_id").val() + "'>click here</a> to view the results</H4>");
						return;
					}
					if (results.code == "error") {
						w2alert("<H4>Save failed: reason:" + results.desc + "</H4>");
						return;
					}					

				}, error: function(xhr, textStatus, errorThrown){
					w2popup.close();
					w2alert("<H4>Save failed: reason:" + JSON.stringify(xhr) + ' ' + errorThrown + "</H4>");					
				}
			});			
		});	

		selected_project=project_list[0].value
		var selected_patients = objAttrToArray(projects[selected_project]).sort();
			patient_list = [];
			selected_patient = selected_patients[0];
			selected_patients.forEach(function(d) {
					patient_list.push({value: d, text: d});
			})
		selected_diagnosis=Object.keys(projects[selected_project][selected_patient])[0]
		var selected_cases = objAttrToArray(projects[selected_project][selected_patient][selected_diagnosis]).sort();
			selected_cases.forEach(function(d) {
					var path=Object.keys(projects[selected_project][selected_patient][selected_diagnosis][d])[0]
					if(path=="uploads"){
						selected_case = d;
						case_list.push({value: d, text: d});
						$('#selCaseList').combobox('setValue', selected_case);
					}
			})
		$('#selPatientList').combobox('loadData', patient_list);
		$('#selCaseList').combobox('loadData', case_list);
		$('#selPatientList').combobox('setValue', selected_patient);
		$('#diagnosis').combotree('setValue',selected_diagnosis)
	
	});

	function addVCF(show_input=false) {
		var html = '<div id="row_vcf" class="row"><div class="col-md-12"><div class="panel panel-primary"><div class="panel-body"><div class="container-fluid" style="padding:10px"><div class="row"><div class="col-md-6"><H4> VCF File </H4><div id="vcf_upload_file">Upload VCF</div></div><div class="col-md-6"><div id="info_vcf"></div></div></div></div></div></div></div></div>';
		$("#vcf_upload").append(html);
		$("#vcf_upload_file").uploadFile({
				url:"{{url('/uploadVarData')}}",
				fileName:"myfile",
				fileUploadId:"fileInput_vcf",
				dragDropStr: "<span><b>Drag and Drop VCF File</b></span>",
				onSelect:function(files)
				{				    
				    return true; //to allow file submission.
				},
				onSuccess: function(files, data, xhr, pd) {
					var data = JSON.parse(data);
					if (data[0].code == "no_user") {
						w2alert("<H5>Session timeout! Please login again</H5>");
						pd.statusbar.hide();
						return;
					}
					if (data[0].caller == "caller") {
						w2alert("<H5>The caller is not supported!</H5>");
						pd.statusbar.hide();
						return;
					}
					var upload_id = data[0].upload_id;
					vcf_list[upload_id] = data[0];
					console.log(data);
					var vcf_info_html = '<div id="table_' + upload_id + '"><H4><img width=25 height=25 src="{{url("/images/check.png")}}"></img>&nbsp;&nbsp;' + data[0].file_name + '</H4><table class="table table-bordered table-hover"><tr><th style="text-align:center">Caller</th><td colspan="2">' + data[0].caller + '</td></tr><tr><th style="text-align:center">Type</th><td colspan="2"><select id="' + upload_id + '_type" name="type" class="form-control"><option value="germline">Germline</option><option value="somatic">Somatic</option><option value="rnaseq">RNASeq</option><option value="variants">Unpaired DNA</option></select></td></tr><tr><th class="text-center">Sample Name</th><th class="text-center">Tissue Category</th><th class="text-center">Material</th></tr>';
					
					data[0].samples.forEach(function(d){
						vcf_info_html += '<tr><td><input type="text" id="' + upload_id + '_' + d + '_name" placeholder="Sample Name" value="' + d + '" class="form-control"/></td><td><select id="' + upload_id + '_' + d + '_tissue_cat" name="tissue_cat" class="form-control"><option value="normal">Normal</option><option value="tumor">Tumor</option></select></td><td><select id="' + upload_id + '_' + d + '_material_type" class="form-control"><option value="DNA">DNA</option><option value="RNA">RNA</option></select></tr>';
					})
					vcf_info_html += "</table></div>";
					$("#info_vcf").append(vcf_info_html);
				},
				deleteCallback: function(data,pd)
				{
					console.log(JSON.parse(data)[0].file_name);
					var upload_id = JSON.parse(data)[0].upload_id;
					for(var i=0;i<data.length;i++)
					{						
						/*$.post("delete.php",{op:"delete",name:data[i]},						
						function(resp, textStatus, jqXHR)
						{
				            //Show Message    
				            alert("File Deleted");        
						});*/
					}        
					pd.statusbar.hide();
					delete vcf_list[upload_id];
					$("#table_" + upload_id).remove();
				}
		});
		if (show_input) {
			//$("#fileInput_" + uuid).click();
		}		
	}

	function addExp(show_input=false) {		
		var html = '<div id="row_exp" class="row"><div class="col-md-12"><div class="panel panel-primary"><div class="panel-body"><div class="container-fluid" style="padding:10px"><div class="row"><div class="col-md-6"><H4> Expression File </H4><div id="exp_upload_file">Upload Expression File</div></div><div class="col-md-6"><div id="info_exp"></div></div></div><a href=\'{{url("/downloadExampleExpression/ENSEMBL")}}\'>Download example ENSEMBL file</a></br><a href=\'{{url("/downloadExampleExpression/UCSC")}}\'>Download example UCSC file</a></div></div></div></div></div>';
		$("#exp_upload").append(html);
		$("#exp_upload_file").uploadFile({
				url:"{{url('/uploadExpData')}}",
				fileName:"myfile",
				fileUploadId:"fileInput_exp",
				dragDropStr: "<span><b>Drag and Drop Expression File</b></span>",
				onSelect:function(files)
				{				    
				    return true; //to allow file submission.
				},
				onSuccess: function(files, data, xhr, pd) {					
					var data = JSON.parse(data);
					if (data[0].code == "no_user") {
						w2alert("<H5>Session timeout! Please login again</H5>");
						pd.statusbar.hide();
						return;
					}
					if (data[0].type == "NA") {
						w2alert("<H5>The file cannot be recognized!</H5>");
						pd.statusbar.hide();
						return;
					}
					var file_name = data[0].file_name;
					var upload_id = data[0].upload_id;
					exp_list[upload_id] = data[0];
					var exp_info_html = '<div id="table_' + upload_id + '"><H4><img width=25 height=25 src="{{url("/images/check.png")}}"></img>&nbsp;&nbsp;' + file_name + '</H4><table class="table table-bordered table-hover"><tr><th>Level</th><td>' + data[0].level.toUpperCase() + '</td></tr><tr><th>Annotation Type</th><td>' + data[0].type.toUpperCase() + '</td></tr></table>';
					$("#info_exp").append(exp_info_html);
				},
				deleteCallback: function(data,pd)
				{
					var upload_id = JSON.parse(data)[0].upload_id;
					for(var i=0;i<data.length;i++)
					{						
						/*$.post("delete.php",{op:"delete",name:data[i]},						
						function(resp, textStatus, jqXHR)
						{
				            //Show Message    
				            alert("File Deleted");        
						});*/
					}        
					pd.statusbar.hide();
					delete exp_list[upload_id];
					//$("#table_" + uuid).remove();
					$("#table_" + upload_id).remove();
				}
		});
		if (show_input) {
			//$("#fileInput_" + uuid).click();
		}		
	}

	function addFusion(show_input=false) {		
		var html = '<div id="row_fusion" class="row"><div class="col-md-12"><div class="panel panel-primary"><div class="panel-body"><div class="container-fluid" style="padding:10px"><div class="row"><div class="col-md-6"><H4> Fusion File </H4><div id="fusion_upload_file">Upload Fusion File</div></div><div class="col-md-6"><div id="info_fusion"></div></div></div></div></div></div></div></div>';
		$("#fusion_upload").append(html);
		$("#fusion_upload_file").uploadFile({
				url:"{{url('/uploadFusionData')}}",
				fileName:"myfile",
				fileUploadId:"fileInput_fusion",
				maxFileCount : 1,
				dragDropStr: "<span><b>Drag and Drop Fusion File</b></span>",
				onSelect:function(files)
				{				    
				    return true; //to allow file submission.
				},
				onSuccess: function(files, data, xhr, pd) {					
					var data = JSON.parse(data);
					if (data[0].code == "no_user") {
						w2alert("<H5>Session timeout! Please login again</H5>");
						pd.statusbar.hide();
						return;
					}
					if (data[0].code == "failed") {
						w2alert("<H5>The file cannot be recognized!</H5>");
						pd.statusbar.hide();
						return;
					}
					var file_name = data[0].file_name;
					fusion_data = data[0];
					//var fusion_info_html = '<div id="table_' + upload_id + '"><H4><img width=25 height=25 src="{{url("/images/check.png")}}"></img>&nbsp;&nbsp;' + file_name + '</H4><table class="table table-bordered table-hover"><tr><th>Level</th><td>' + data[0].level.toUpperCase() + '</td></tr><tr><th>Annotation Type</th><td>' + data[0].type.toUpperCase() + '</td></tr></table>';
					$("#info_fusion").append('<div id="info_fusion_lbl"><H4><img width=25 height=25 src="{{url("/images/check.png")}}"></img>&nbsp;&nbsp;' + file_name + '</H4></div>');
				},
				deleteCallback: function(data,pd)
				{
					pd.statusbar.hide();
					fusion_data = null;	
					$("#info_fusion_lbl").remove();				
				}
		});
		if (show_input) {
			//$("#fileInput_" + uuid).click();
		}		
	}




$(function(){
	$('input[name=upload]').click(function() {
		var type=$('input[name=upload]:checked').val();
		if (type=="pre"){
			$("#patient_id").prop('disabled', true);
			$("#case_id").prop('disabled', true);
			
			$("#selPatientList").combobox('enable');
			$("#selCaseList").combobox('enable');
			$("#case_id_pre").prop('disabled', false);
		}
		else{
			$("#patient_id").prop('disabled', false);
			$("#case_id").prop('disabled', false);

			$("#selPatientList").combobox('disable');
			$("#selCaseList").combobox('disable');
			$("#case_id_pre").prop('disabled', true);

		}
	});
});


$(function(){
	$('input[name=case_type]').click(function() {
		var type=$('input[name=case_type]:checked').val();
		if (type=="pre"){			
			$("#selCaseList").combobox('enable');
			$("#case_id_pre").prop('disabled', true);
		}
		else{
			$("#selCaseList").combobox('disable');
			$("#case_id_pre").prop('disabled', false);

		}
	});
});
</script>
<div style="padding:10px">
	<div id="main" class="container-fluid" style="padding:10px" >
		<div class="row">
			<div class="row" style="padding:10px">
				<div class="col-md-6">
					<table class="table table-bordered table-hover">
						<tr>
							<th>Project</th>
							<td>
								<input id="selProjectList"></input>
							</td>
						</tr>
						<tr>
							<th>Diagnosis</th>
							<td>						
								<input id="diagnosis" class="form-control easyui-combotree ">
							</td>						
						</tr>
					</table>
				</div>
			</div>
			
			<p  style="padding:10px">  <input type="radio" name="upload" id="upload" value="pre" checked > Select an exisiting patient</p>

			<div class="row" style="padding:10px">
				<div class="col-md-6" >
					<table class="table table-bordered table-hover">
						<tr>
							<th>Patient ID</th>
							<td>
								<input id="selPatientList" ></input>	
							</td>

						</tr>		
						<tr>
							<th><input type="radio" name="case_type" value="pre" checked > Select Existing Case</th>

							<td>
							<p>*Please note you can only upload to existing cases created outside of the pipeline</p>
								<input id="selCaseList" ></input>

							</td>

						</tr>
						<tr>
							<th> <input type="radio" name="case_type" value="new" > Or Create a new Case ID</th>
							<td>
								<input id="case_id_pre" class="form-control" disabled></input>							
							</td>
						</tr>							
					</table>
				</div>
			</div>
			<p style="padding:10px" >  <input type="radio" name="upload"  value="new"  ><b> OR </b>Create a new Patient and Case</p>

			<div class="row" style="padding:10px" id="new upload">
				<div class="col-md-6" >
					<table class="table table-bordered table-hover">
						<tr>
							<th>Patient ID</th>
							<td>
								<input id="patient_id" class="form-control" disabled></input>							
							</td>
						</tr>		
						<tr>
							<th>Case</th>
							<td>
								<input id="case_id" class="form-control" disabled></input>							
							</td>
						</tr>					
					</table>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<ul class="nav nav-tabs" >
						<li class="active"><a data-toggle="tab" href="#variants">Variants</a></li>
						<li><a data-toggle="tab" href="#expression">Expression</a></li>
						<li><a data-toggle="tab" href="#fusion">Gene Fusion</a></li>						
					</ul>
					<div class="tab-content" >
						<div id="variants" class="tab-pane fade active in" style="padding:10px">
							<div class="row">
								<div class="col-md-5">						
									<table class="table table-bordered table-hover">
										<tr>
											<th>Sequencing Type:</th>
											<td>
												<select id="selExpType" class="form-control">
													<option value="Exome">Exome</option>
													<option value="Panel">Panel</option>
													<option value="Whole Genome">Whole Genome</option>
													<option value="RNAseq">RNAseq</option>
												</select>
											</td>
										</tr>
									</table>
								</div>
							</div>
							<div class="row">
								<div class="col-md-10">
									<div id="vcf_upload"></div>
									<!--a href="#" id="btnAddVar" class="btn btn-primary" ><H4> + Add New VCF</H4></a-->
								</div>
							</div>
						</div>
						<div id="expression" class="tab-pane fade" style="padding:10px">
							<div class="row">
								<div class="col-md-10">						
									<table class="table table-bordered table-hover">
										<tr>
											<th>Sample Name</th>
											<td><input id="exp_sample_name" class="form-control"></input></td>
											<th>Tissue Category</th>
											<td>
												<select id="selExpTissueCat" class="form-control">
													<option value="tumor">Tumor</option>
													<option value="normal">Normal</option>													
												</select>
											</td>
											<th>Library Type</th>
											<td>
												<select id="selExpLibType" class="form-control">
													<option value="polyA">PolyA</option>
													<option value="ribozero">Ribozero</option>
													<option value="access">Access</option>
												</select>
											</td>
										</tr>
									</table>
								</div>
							</div>							
							<div class="row">
								<div class="col-md-10">
									<div id="exp_upload"></div>
									<!--a href="#" id="btnAddExp" class="btn btn-primary" ><H4> + Add Expression</H4></a-->
								</div>
							</div>
						</div>
						<div id="fusion" class="tab-pane fade" style="padding:10px">
							<div class="row">
								<div class="col-md-10">						
									<table class="table table-bordered table-hover">
										<tr>
											<th>Sample Name</th>
											<td><input id="fusion_sample_name" class="form-control"></input></td>
											<th>Tissue Category</th>
											<td>
												<select id="selFusionTissueCat" class="form-control">
													<option value="tumor">Tumor</option>
													<option value="normal">Normal</option>													
												</select>
											</td>
											<th>Library Type</th>
											<td>
												<select id="selFusionLibType" class="form-control">
													<option value="polyA">PolyA</option>
													<option value="ribozero">Ribozero</option>
													<option value="access">Access</option>
												</select>
											</td>
										</tr>
									</table>
								</div>
							</div>		
							<div class="row">
								<div class="col-md-10">
									<div id="fusion_upload"></div>
									<!--a href="#" id="btnAddExp" class="btn btn-primary" ><H4> + Add Expression</H4></a-->
								</div>
							</div>
						</div>							
					</div>
				</div>
			</div>
		</div>			
	</div>
	<BR><HR>
	<a href="#" id="btnSave" class="btn btn-success" ><H4>Process</H4></a>	
</div>
@stop
