@extends('layouts.default')
@section('title', "Upload VCF")
@section('content')

{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/w2ui/w2ui-1.4.min.css') }}
{{ HTML::style('packages/jquery-easyui/themes/bootstrap/easyui.css') }}

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
		addUpload("vcf");
		addUpload("text");
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


	function addUpload(type="vcf", show_input=false) {
		var description = (type=="vcf")?"VCF File (VCF4 format)":"Text file (Text separated file. Columns:Chromosome/Start/End/Ref/Alt/Sample/Quality/Var coverage/Total Coverage)";
		var html = '<div id="row_' + type + '" class="row"><div class="col-md-10"><div class="panel panel-primary"><div class="panel-body"><div class="container-fluid" style="padding:10px"><div class="row"><div class="col-md-12"><H4> ' + description + '</H4><div id="' + type + '_upload_file">Upload file</div></div><div class="col-md-5"><div id="info_' + type + '"></div></div></div></div></div></div></div></div>';
		$("#" + type + "_upload").append(html);
		$("#" + type + "_upload_file").uploadFile({
				url:(type=="vcf")?"{!!url('/uploadVCF')!!}":"{!!url('/uploadVarText')!!}",
				fileName:"myfile",
				fileUploadId:"fileInput_vcf",
				showDelete: false,
				dragDropStr: "<span><b>Drag and Drop " + type.toUpperCase() + " File</b></span>",
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
					w2alert("<H5>Uploaded successful!</H5>");
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
		<div id="out_container" class="easyui-panel" data-options="border:false" style="min-height:250px;padding:10px;">	
				<div id="tabSetting" class="easyui-tabs" data-options="tabPosition:'top',fit:true,plain:true,pill:false,border:false,headerWidth:80" style="padding:0px;">
					<div id="tab_vcf_upload" title="VCF" style="overflow:hidden;width:100%;padding:5px;">
						<h5><b>  </b></h5>
						<div class="row">
							<div class="col-md-10">
								<div id="vcf_upload"></div>
							</div>
						</div>
					</div>
					<div id="tab_text_upload" title="Text" style="overflow:hidden;width:100%;padding:5px;">
						<div class="row">
							<div class="col-md-10">
								<div id="text_upload"></div>
							</div>
						</div>
					</div>
				</div>
		</div>
		
		<h5><b> Uploaded</b></h5>
		<button id="btnDownload" class="btn btn-info" style="visibility:hidden"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Download</button>	
		<div class="easyui-panel" data-options="border:false" style="padding:0px;">
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
