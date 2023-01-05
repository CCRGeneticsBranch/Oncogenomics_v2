@extends('layouts.default')
@section('content')

{{ HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/jquery-steps/demo/css/jquery.steps.css') }}
{{ HTML::style('packages/w2ui/w2ui-1.4.min.css') }}

{{ HTML::script('packages/DataTables-1.10.8/media/js/jquery.dataTables.min.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/dataTables.buttons.min.js') }}
{{ HTML::script('packages/w2ui/w2ui-1.4.min.js')}}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/jquery-steps/build/jquery.steps.min.js') }}
{{ HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') }}

<script type="text/javascript">
	var tbl_patient = null;
	var tbl_sample = null;
	var patient_data = null;
	var sample_data = null;

	$(document).ready(function() {	
		var wizard = $("#uploadWizard").steps(
		{
			labels: {
				finish: "Upload"
			},
			onStepChanging : function (e, currentIndex, newIndex) {
    			if (newIndex < currentIndex)
    				return true;
    			if (currentIndex == 0)
    				type = "patient";
    			if (currentIndex == 1)
    				type = "sample";
    			//eval('var tbl = tbl_' + type);
    			//if (tbl == null) {
    			//	return false;    				
    			//} 
    			return true;    			
    		},
    		onFinished : function (event, currentIndex) {    			
    			if (patient_data == null && sample_data == null) {
					alert('Please select patient or sample data!');
					return false;
				}    							
				//var upload_data = {project: $('#selProject').val(), patients: {patient_id_col:$('#selPatientPatientID').val(), diagnosis_col: $('#selPatientDiagnosis').val(), survival_time_col: $('#selPatientSurvivalTime').val(), survival_status_col: $('#selPatientSurvivalStatus').val(), meta_data : patient_meta, patient_data : patient_data}, samples: {patient_id_col:$('#selSamplePatientID').val(), sample_id_col:$('#selSampleSampleID').val(), tissue_type_col:$('#selSampleTissueType').val(), meta_data : sample_meta, sample_data : sample_data}};
				
				var upload_data = {};
				if (patient_data != null) {
					var patient_meta = getMetaData('patient');
					upload_data.patients = {patient_id_col:$('#selPatientPatientID').val(), survival_time_col: $('#selPatientSurvivalTime').val(), survival_status_col: $('#selPatientSurvivalStatus').val(), event_free_survival_time_col: $('#selPatientEFSurvivalTime').val(), event_free_survival_status_col: $('#selPatientEFSurvivalStatus').val(), meta_data : patient_meta, patient_data : patient_data};
				}
				if (sample_data != null) {
					var sample_meta = getMetaData('sample');
					upload_data.samples = {patient_id_col:$('#selSamplePatientID').val(), sample_id_col:$('#selSampleSampleID').val(), meta_data : sample_meta, sample_data : sample_data};
				}
				upload_data.delete_old = $('#ckClearOldData').is(':checked');
				//var url = '{{url("/saveClinicalData")}}' + '/' + JSON.stringify(upload_data);
				upload_data = {upload_data:JSON.stringify(upload_data)};
				var url = '{{url("/saveClinicalData")}}';
				console.log(upload_data);
				//return;
				w2popup.open({body: "<img src='{{url('/images/ajax-loader.gif')}}'></img><H3>Processing data...</H3>", height: 200});
				$.ajax({ url: url, async: true, type: 'POST', dataType: 'text', data: upload_data, success: function(data) {
				//$.ajax({ url: url, async: true, type: 'GET', dataType: 'text', success: function(data) {
						w2popup.close();
						console.log(data);
						if (data == "ok")
							w2alert("<H4>Upload successful!</H4>");
						else
							w2alert("<H4>Upload failed! reason:" + data + "</H4>");
					}, error: function(xhr, textStatus, errorThrown){
						alert('Upload failed! reason:' + JSON.stringify(xhr) + ' ' + errorThrown);
					}
				});						
    		}
		});


		$('#patientFile').on('change', function () {
    		fileChosen(this, document.getElementById('list_content'), "patient");
		});

		$('#sampleFile').on('change', function () {
    		fileChosen(this, document.getElementById('list_content'), "sample");
		});
		
	});


	function getMetaData(type) {
		if ($('#tbl_' + type + '_header tr') == undefined)
			return null;
		var meta_data = [];			
		$('#tbl_' + type + '_header tr').each(function(i){				
			$(this).children('td').each(function(j){
        		//wordVal = $(this).children('input').val().trim();
        		if (i == 0)
        			meta_data.push({"id": $(this).html()});
        		if (i == 1) {
        			d = $(this).children('input').val().trim();
        			meta_data[j]["label"] = d;        				
        		}
        		/*
        		if (i == 2) {
        			d = $(this).children('select').val().trim();
        			meta_data[j]["type"] = d;        				
        		}
        		*/
        		if (i == 2) {
        			d = $(this).children('input:checkbox').is(':checked');
        			meta_data[j]["include"] = d;        				
        		}
        		/*
        		if (i == 4) {
        			d = $(this).children('input').val().trim();
        			meta_data[j]["values"] = d;        				
        		} 
        		*/       			
        	});  

   		});
   		return meta_data;
	}	

	function readTextFile(file, callback, encoding) {
		var reader = new FileReader();
		reader.addEventListener('load', function (e) {
			callback(this.result);
		});
		if (encoding) 
			reader.readAsText(file, encoding);
		else 
			reader.readAsText(file);
	}

	function addRequiredColumnSelection(col, type, idx) {
		if (type == "patient") {
			$('#selPatientPatientID').append($('<option>', { value : idx }).text(col));
			//$('#selPatientDiagnosis').append($('<option>', { value : idx }).text(col));
			$('#selPatientSurvivalTime').append($('<option>', { value : idx }).text(col));
			$('#selPatientSurvivalStatus').append($('<option>', { value : idx }).text(col));
			$('#selPatientEFSurvivalTime').append($('<option>', { value : idx }).text(col));
			$('#selPatientEFSurvivalStatus').append($('<option>', { value : idx }).text(col));
			if (col.toLowerCase() == "patient_id")
				$('#selPatientPatientID option[value=' + idx + ']').prop('selected',true);
			//if (col.toLowerCase().substring(0, 4) == "diag")
			//	$('#selPatientDiagnosis option[value=' + idx + ']').prop('selected',true);
			if (col.toLowerCase().substring(0, 3) == "efs")
				$('#selPatientSurvivalTime option[value=' + idx + ']').prop('selected',true);
			if (col.toLowerCase().substring(0, 5) == "scens")
				$('#selPatientSurvivalStatus option[value=' + idx + ']').prop('selected',true);
		} else {
			$('#selSamplePatientID').append($('<option>', { value : idx }).text(col));
			$('#selSampleSampleID').append($('<option>', { value : idx }).text(col));
			//$('#selSampleTissueType').append($('<option>', { value : idx }).text(col));
			if (col.toLowerCase() == "patient_id")
				$('#selSamplePatientID option[value=' + idx + ']').prop('selected',true);
			if (col.toLowerCase() == "sample_id")
				$('#selSampleSampleID option[value=' + idx + ']').prop('selected',true);
			//if (col.toLowerCase().substring(0, 6) == "tissue")
			//	$('#selSampleTissueType option[value=' + idx + ']').prop('selected',true);

		}

	}
	function fileChosen(input, output, type) {
		eval('var tbl = tbl_' + type);
		tblName = 'tbl_' + type;
		tblHeaderName = "tbl_" + type + "_header";
		contentTag = type + "_content";		
		var total_rows = 3;
		if (input.files && input.files[0]) {
			var data = [];
			var cols = [];
			var first_line = true;
			var col_html = '<tr><th>ID</th>';
			var label_html = '<tr><th>Display label</th>';
			readTextFile(input.files[0],function (str) {
				var lines = str.split('\n');				
    			lines.map(function(line){
      				var fields = line.split('\t');
      				if (first_line) {
      					var idx = 0;
      					fields.map(function(field){
      						col_html += '<td>' + field + '</td>';
      						label_html += '<td><input type="text" value="' + field.replace('_', ' ') + '"></input></td>';
      						cols.push({"title":field});
      						addRequiredColumnSelection(field, type, idx);
      						idx++;
      					});
      					first_line = false;
      				}
      				else {
      					if (fields.length == cols.length)
      						data.push(fields);
      				}
    			});
    			
    			if (tbl != null) {
    				tbl.destroy();
					$('#' + tblName).empty();
				}

				eval(type + '_data = data');
    			tbl = $('#' + tblName).DataTable( 
					{
						"data": data,
						"columns": cols,
						"ordering":    true,
						"order":[[1, "Desc"]],
						"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
						"pageLength":  15,
						"pagingType":  "simple_numbers",			
						"dom": 'B<"toolbar">lfrtip',
						"buttons": ['csv', 'excel']
					} 
				);

				eval('tbl_' + type + ' = tbl');

				$('#' + tblHeaderName + ' tr:last').after(col_html + '</tr>');
				$('#' + tblHeaderName + ' tr:last').after(label_html + '</tr>');

				//var type_html = '<tr><th>Type</th>';
				var include_html = '<tr><th>Include</th>';
				//var values_html = '<tr><th>Values (example: 0:male, 1: female)</th>';
				cols.forEach(function (d) {
					//type_html += '<td><select><option>category</option><option>string</option><option>number</option></select></td>'; 
					include_html += '<td><input type="checkbox" checked>Include</input></select></td>'; 
					//values_html += '<td><input type="text" value=""></input></td>';
				});

				//$('#' + tblHeaderName + ' tr:last').after(type_html + '</tr>');
				$('#' + tblHeaderName + ' tr:last').after(include_html + '</tr>');
				//$('#' + tblHeaderName + ' tr:last').after(values_html + '</tr>');				

				var count = $('#' + tblHeaderName + ' tr').length;
				for (var i=0;i<(count-total_rows);i++)
					$('#' + tblHeaderName + ' tr:first').remove();
				$('#' + contentTag).css("display", "block");

			});
			
		}
	}	
</script>
<div id="uploadWizard" style="width:100%;height:90%;padding:10px;overflow:auto;">
	<h1>Patients</h1>
	<div style="width:100%;height:100%;padding:10px;overflow:auto;">
		Patient file (tab separated text file. Skip this step if you do not have any): <input name="file" type="file" id="patientFile" class="form-control" width=50 value="">
		<div id="patient_content" style="display:none;">
			<table cellpadding="10" cellspacing="0" border="0" word-wrap="break-word" style='width:100%;overflow:auto;'>
				<tr><td width=300px>Patient ID column: </td><td><select id="selPatientPatientID" style="width: 200px"></select></td></tr>
				<!--tr><td width=250px>Diagnosis column: </td><td><select id="selPatientDiagnosis" style="width: 150px"></select></td></tr-->
				<tr><td width=300px>Overall survival time column: </td><td><select id="selPatientSurvivalTime" style="width: 200px"><option value="none">None</option></select></td></tr>
				<tr><td width=300px>Overall survival status column: </td><td><select id="selPatientSurvivalStatus" style="width: 200px"><option value="none">None</option></select></td></tr>
				<tr><td width=300px>EventFree survival time column: </td><td><select id="selPatientEFSurvivalTime" style="width: 200px"><option value="none">None</option></select></td></tr>
				<tr><td width=300px>EventFree survival status column: </td><td><select id="selPatientEFSurvivalStatus" style="width: 200px"><option value="none">None</option></select></td></tr>
				<tr><td colspan=2>Header Definition:</td></tr>
				<tr><td colspan=2><table cellpadding="10" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tbl_patient_header" style='width:100%;overflow:auto;'><tr><td></td></tr></table></td></tr>
				<tr><td colspan=2>Content:</td></tr>
				<tr><td colspan=2><table cellpadding="10" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tbl_patient" style='width:100%;overflow:auto;'></table></td></tr>
			</table>
		</div>
	</div>
	<h1>Samples</h1>
	<div>
		Sample file (tab separated text file. Skip this step if you do not have any): <input name="file" type="file" id="sampleFile" class="form-control" value="">
		<div id="sample_content" style="display:none;">
			<table cellpadding="10" cellspacing="0" border="0" word-wrap="break-word" style='width:100%;overflow:auto;'>
				<tr><td width=250px>Patient ID column: </td><td><select id="selSamplePatientID" style="width: 150px"></select></td></tr>
				<tr><td width=250px>Sample ID column: </td><td><select id="selSampleSampleID" style="width: 150px"></select></td></tr>
				<!--tr><td width=250px>Tissue type column: </td><td><select id="selSampleTissueType" style="width: 150px"></select></td></tr-->
				<tr><td colspan=2>Header Definition:</td></tr>
				<tr><td colspan=2><table cellpadding="10" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tbl_sample_header" style='width:100%;overflow:auto;'><tr><td></td></tr></table></td></tr>
				<tr><td colspan=2>Content:</td></tr>
				<tr><td colspan=2><table cellpadding="10" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tbl_sample" style='width:100%;overflow:auto;'></table></td></tr>
			</table>
		</div>	
	</div>
	<h1>Upload</h1>
	<div>
	<!--
	Select project:
	<select id="selProject">
	@foreach ($projects as $project)
		<option value={{$project->id}}>{{$project->name}}</option>
	@endforeach
	</select--><BR>
	<input type="checkbox" id="ckClearOldData" checked>Delete old data</input>
	
	</div>

</div>	

@stop
