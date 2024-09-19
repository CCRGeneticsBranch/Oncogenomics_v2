@extends('layouts.default')
@section('content')

{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/w2ui/w2ui-1.4.min.css') }}

{!! HTML::script('packages/DataTables/datatables.min.js') !!}
{{ HTML::script('js/bootstrap.bundle.min.js') }}
{!! HTML::script('js/upload.js') !!}
{!! HTML::script('packages/w2ui/w2ui-1.4.min.js')!!}
{!! HTML::script('packages/jquery-easyui/jquery.easyui.min.js') !!}
{!! HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') !!}
{!! HTML::script('js/onco.js') !!}

<meta name="csrf-token" content="{{ csrf_token() }}">

{{ HTML::style('css/uploadfile.css') }}

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
	var projects = {!!$projects!!};
	for (var i in projects.names) {
		project_list.push({value:projects.names[i], text: i});
	}
	$(document).ready(function() {
		addVCF();
		$.ajaxSetup({
		   headers: {
		      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
		   }
		});
		getData();
		$('#btnDownload').on('click', function() {	
			$("#downloadHiddenform").submit();			
		});
		
	});



	function getData() {
		$("#loadingMaster").css("display","block");
		$('#onco_layout').css('visibility', 'hidden');
		var url = '{{url("/getUploads")}}';
		console.log(url);
       	$.ajax({ url: url, async: true, dataType: 'text', success: function(json_data) {
				$("#loadingMaster").css("display","none");
				$('#onco_layout').css('visibility', 'visible');
				data = JSON.parse(json_data);
				if (data.data.length == 0) {
					$('#no_data').css('visibility', 'visible');
					$('#btnDownload').css('visibility', 'hidden');
				} else {
					$('#no_data').css('visibility', 'hidden');
					$('#btnDownload').css('visibility', 'visible');
					if (typeof tbl != "undefined") {
						$('#tblOnco').empty();
						tbl.destroy();
					}	
					tbl = $('#tblOnco').DataTable( 
						{
								"data": data.data,
								"columns": data.cols,
								"ordering":    true,
								"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
								"pageLength":  25,			
								"processing" : true,			
								"pagingType":  "simple_numbers",			
								"dom": 'lfrtip'
						} );
				}
			}
		});
	}


	function addVCF(show_input=false) {
		var html = '<div id="row_vcf" class="row"><div class="col-md-10"><div class="panel panel-primary"><div class="panel-body"><div class="container-fluid" style="padding:10px"><div class="row"><div class="col-md-4"><H4> VCF File </H4><div id="vcf_upload_file">Upload VCF</div></div><div class="col-md-5"><div id="info_vcf"></div></div></div></div></div></div></div></div>';
		$("#vcf_upload").append(html);
		$("#vcf_upload_file").uploadFile({
				url:"{!!url('/uploadVCF')!!}",
				fileName:"myfile",
				fileUploadId:"fileInput_vcf",
				showDelete: false,
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
					w2alert("<H5>VCF uploaded successful!</H5>");
					getData();
					var upload_id = data[0].upload_id;
					vcf_list[upload_id] = data[0];
					console.log(data);					
				}
		});
		if (show_input) {
			//$("#fileInput_" + uuid).click();
		}		
	}



</script>
<form style="display: hidden" action='{!!url('/downloadVariantsFromUpload')!!}' method="POST" target="_blank" id="downloadHiddenform">
	@csrf
	<input type="hidden" id="var_list" name="var_list" value=""/>
	<input type="hidden" name="_token" value="{{ csrf_token() }}" />
</form>
<div style="padding:5px">
	<div id="main" class="container-fluid" style="padding:5px" >
		<h5><b> Upload your VCF file </b></h5>
		<!--div class="row">
			<div class="col-md-5">						
				<table class="table table-bordered table-hover">
					<tr>
						<th>VCF Type:</th>
						<td>
							<select id="selType" class="form-control">
								<option value="variants">Unpaired Tumor</option>
								<option value="germline">Germline</option>
								<option value="somatic">Somatic</option>
								<option value="rnaseq">RNAseq</option>
							</select>
						</td>
					</tr>
					<tr>
						<th>Tumor/Normal:</th>
						<td>
							<select id="selTissueCat" class="form-control">
								<option value="tumor">Tumor</option>
								<option value="normal">Normal</option>
							</select>
						</td>
					</tr>
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
		</div-->
		<div class="row">
			<div class="col-md-10">
				<div id="vcf_upload"></div>
			</div>
		</div>
		<HR>
		<h5><b> Uploaded</b></h5>
		<button id="btnDownload" class="btn btn-info" style="visibility:hidden"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Download</button>		
		<div class="easyui-panel" style="padding:0px;">
			<div id='loadingMaster' style="height:90%">
		    		<img src='{{url('/images/ajax-loader.gif')}}'></img>
			</div>
			<div id='no_data' style="visibility:hidden">
		    	(No data found)
			</div>	
			<div id="onco_layout" class="easyui-layout" data-options="fit:true" style="width:80%;visibility:hidden">		
				<div data-options="region:'center',split:true" style="padding:10px;" >
					<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblOnco" style='white-space: nowrap;width:98%;'>
					</table> 			
				</div>		
			</div>
		</div>		
	</div>
</div>
@stop
