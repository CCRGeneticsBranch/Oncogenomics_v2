@extends('layouts.default')
@section('content')
@section('title', "Patient--$patient_id")
{!! HTML::style('css/style_datatable.css') !!}
{!! HTML::style('packages/jquery-easyui/themes/bootstrap/easyui.css') !!}
{!! HTML::style('css/heatmap.css') !!}
{!! HTML::style('packages/d3/d3.css') !!}
{!! HTML::style('packages/bootstrap-switch-master/dist/css/bootstrap3/bootstrap-switch.css') !!}
{!! HTML::style('packages/fancyBox/source/jquery.fancybox.css') !!}
{!! HTML::style('packages/tooltipster-master/dist/css/tooltipster.bundle.min.css') !!}
{!! HTML::style('css/filter.css') !!}
{!! HTML::style('packages/w2ui/w2ui-1.4.min.css') !!}

{!! HTML::script('packages/d3/d3.min.js') !!}
{!! HTML::script('packages/d3/d3.tip.js') !!}
{!! HTML::script('packages/DataTables/datatables.min.js') !!}
{!! HTML::script('packages/jquery-easyui/jquery.easyui.min.new.js') !!}
{!! HTML::script('js/togglebutton.js') !!}
{!! HTML::script('packages/tooltipster-master/dist/js/tooltipster.bundle.min.js') !!}
{!! HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') !!}
{!! HTML::script('packages/bootstrap-switch-master/dist/js/bootstrap-switch.js') !!}
{!! HTML::script('js/filter.js') !!}
{!! HTML::script('packages/w2ui/w2ui-1.4.min.js')!!}


<style>
div.toolbar {
	display:inline;
}

.btn-default:focus,
.btn-default:active,
.btn-default.active {
    background-color: DarkCyan;
    border-color: #000000;
    color: #fff;
}

.block_details {
    display:none;
    width:90%;
    border-radius: 10px;
	border: 2px solid #73AD21;
	padding: 10px; 
	margin: 10px; 
	overflow: auto; 
}

.comment {
    width:90%;    
	border-radius: 20px;
	border: 2px solid #73AD21;
	padding: 10px; 
	margin: 10px;
	overflow: auto; 
}

td.details-control {
	text-align: center;
    cursor: pointer;
}

tr.details td.details-control {
    background: '{!!url('/images/details_close.png')!!}' no-repeat center center;
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

/* end only demo styles */

.checkbox-custom, .radio-custom {
    opacity: 0;
    position: absolute;     
}

.checkbox-custom, .checkbox-custom-label, .radio-custom, .radio-custom-label {
    display: inline-block;
    vertical-align: middle;
    margin: 5px;
    cursor: pointer;
}

.checkbox-custom-label, .radio-custom-label {
    position: relative;
}

.checkbox-custom + .checkbox-custom-label:before, .radio-custom + .radio-custom-label:before {
    content: '';
    background: #fff;
    border: 2px solid #ddd;
    display: inline-block;
    vertical-align: middle;
    width: 30px;
    height: 30px;
    padding: 2px;
    margin-right: 10px;
    text-align: center;
}

.checkbox-custom:checked + .checkbox-custom-label:before {
    content: "\f00c";
    font-family: 'FontAwesome';
    background: rebeccapurple;
    font-size: 16;
    color: #fff;
}

.radio-custom + .radio-custom-label:before {
    border-radius: 50%;
}

.radio-custom:checked + .radio-custom-label:before {
    content: "\f00c";
    font-family: 'FontAwesome';
    font-size: 16;
    width : 30px;
    height : 30px;
    color: red;
}

.checkbox-custom:focus + .checkbox-custom-label, .radio-custom:focus + .radio-custom-label {
  outline: 1px solid #ddd; /* focus style */
}

.left_pep {
	background-color: rbga(23,34,56,1);
}

.right_pep {
	background-color: "pink";
}

.in_domain {
	opacity: 1;
}

.out_domain {
	opacity: 0.6;
}


</style>

<script type="text/javascript">
	
	var options = [];
	var tbls = [];
	var column_tbls = [];
	var col_html = [];
	var patient_state = 'collapse';
	var tblGenotypingHistory = null;
	var exp_plot = null;
	var exp_data = null;
	var value_range = {};
	var left_gene_idx = 4
	var left_chr_idx = left_gene_idx + 2;
	var right_chr_idx = left_gene_idx + 4;
	var type_idx = left_gene_idx + 9;
	var tier_idx = left_gene_idx + 10;
	var user_list_idx = 15;
	//project, diagnosis and patient data
	var projects = {!!$projects!!};
	//console.log(projects)
	var project_names = {!!$project_names!!};
		console.log(project_names)

	//list data shown in combobox
	var patient_list = [];
	var diagnosis_list = [];
	var project_list = [];
	var project_ids = [];	

	for (var i in project_names) {
		project_list.push({value:project_names[i], text: i});
	}
	
	//current project, diagnosis and patient data
	var current_patient = "{!!$patient_id!!}";
	var current_project = "{!!$project!!}";
	var default_project = "{!!$default_project!!}";
	var default_diagnosis = "{!!$default_diagnosis!!}";		

	var selected_project;
	var selected_diagnosis;
	var selected_patient;

	var tab_urls = [];
	var sub_tabs = [];
	var title_id_mapping = [];
	var loaded_list = [];
	var tab_shown = true;

	$(document).ready(function() {			
		$("a.img_group").fancybox();

		var first_tab = null;
		@foreach($case_list as $case_name => $qcase_name)
			console.log('{!!$case_name!!}' + ' ==> ' + '{!!$qcase_name!!}');
			var url = '{!!url("/viewCase/$default_project")!!}' + '/' + encodeURIComponent('{!!$patient_id!!}') + '/' + encodeURIComponent('{!!$case_name!!}');
			var case_name = '{!!$case_name!!}';
			@if ($default_case_name == $case_name)
				first_tab = '{!!$case_name!!}';
			@endif
			title_id_mapping['{!!$case_name!!}'] = '{!!$qcase_name!!}';
			tab_urls['{!!$qcase_name!!}'] = url;
			console.log(url);
		@endforeach
		@if ($imaging_url != null)
		tab_urls['rms_imaging'] = "{!!$imaging_url!!}";
		@endif
	
		$('#tabCases').tabs('select', first_tab);
		
		$('#selPatientList').combobox({
		        panelHeight: '400px',
		        selectOnNavigation: false,
		        editable: true,
		        
		        onSelect: function(d) {		        	
		        	var patient = d.value
		        			        	
		        },
		        
		        data: patient_list
		});		

		$('#selDiagnosisList').combobox({
		        panelHeight: '400px',
		        selectOnNavigation: false,
		        editable: true,
		        onSelect: function(d) {
		        	selected_diagnosis = d.value
		        	var selected_patients = objAttrToArray(projects[selected_project][selected_diagnosis]).sort();
		        	patient_list = [];
		        	var patient = selected_patients[0];
					selected_patients.forEach(function(d) {
						patient_list.push({value: d, text: d});
					})
		        	$('#selPatientList').combobox('loadData', patient_list);
		        	$('#selPatientList').combobox('setValue', patient);		        	
		        },
		        data: diagnosis_list
		});
		
		$('#selSwitchProject').on('change', function() {			
			var project_id = $("#selSwitchProject").val();
			window.location.href = '{!!url("/viewPatient")!!}' + '/' + project_id + '/' + '{!!$patient_id!!}';
		});


		$('#selProjectList').combobox({
		        panelHeight: '400px',
		        selectOnNavigation: false,
		        editable: true,
		        onSelect: function(d) {	
		        	selected_project =d.value;
		        	var diagnosis_list = [];
		        	var selected_diags = objAttrToArray(projects[selected_project]).sort();
		        	var diagnosis = selected_diags[0];
					selected_diags.forEach(function(d) {
						diagnosis_list.push({value: d, text: d});
					})
		        	$('#selDiagnosisList').combobox('loadData', diagnosis_list);
		        	$('#selDiagnosisList').combobox('setValue', diagnosis);		        	
		        },
		        data: project_list
		});
		$('#selProjectList').combobox('setValue', default_project);
		$('#selDiagnosisList').combobox('setValue', default_diagnosis);
		$('#selPatientList').combobox('setValue', current_patient);
		
		$('#btnGO').on('click', function() {
			var value = $("#selPatientList").combobox('getValue');
			url = '{!!url('/viewPatient')!!}' + '/' + $("#selProjectList").combobox('getValue') + '/' + $("#selPatientList").combobox('getValue');
			window.open(url, '_blank');
		});

		$('.easyui-tabs').tabs({
			onSelect:function(title, idx) {
				var tab = null;
				var url = null;				
				var id = title_id_mapping[title];
				//if (id != undefined) {
				//	tab = $('#' + id).tabs('getSelected');
				//}
				//else
					tab = $(this).tabs('getSelected');				
				var id = tab.panel('options').id;
				console.log("showing frame " + id);
				showFrameHtml(id);				
		   }
		});

		$('.mytooltip').tooltipster();	

		$('#tabCases').tabs({
			tools:'#tab-tools'
		});			
		var first_tab_id = title_id_mapping[first_tab];
		showFrameHtml(first_tab_id);
	});

	function showFrameHtml(id) {
		if (loaded_list.indexOf(id) == -1) {
			var url = tab_urls[id];
			if (url != undefined) {
				console.log(url);
				//var url = encodeURIComponent(url);
				var html = '<iframe id ="case" scrolling="no" frameborder="0"  src="' + url + '" style="width:100%;height:100%;overflow:auto;border-width:0px;padding:5px"></iframe>';
				$('#' + id).html(html);
				loaded_list.push(id);
			} else {
				console.log("Cannot find tab " + id);
			}
		} else {
			console.log("Tab " + id + ' has been loaded already');
		}
	}

	function showDetails ( d, type ) {
		//type = "sample";		
		var patient_link = document.createElement("div");
		patient_id = getInnerText(d[2]);
		var url = (type == 'samples')? '{!!url("/getSampleByPatientID/$default_project")!!}' + '/' + patient_id : '{!!url("/getCasesByPatientID/$default_project")!!}' + '/' + patient_id;
		tbl_id = "tbl" + patient_id;
		loading_id = "loading" + patient_id;
		lbl_id = "lbl" + patient_id;
		num_samples = 0;
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
					data = JSON.parse(data);
					console.log("data");
					console.log(data);
					num_samples = data.data.length;
					if (num_samples > 0) {
					var tblSampleDetail = $('#' + tbl_id).DataTable( 
						{				
							"paging":   false,
							"ordering": true,
							"info":     false,
							"dom": '',
							"data": data.data,
							"columns": data.cols,									
						} );
					}
					$('#' + loading_id).css("display","none");
					$('#' + lbl_id).text(data.data.length);

				}
			});
		return '<div style="padding: 20px;margin: 0px 0px 0px;font-size: 13px;line-height:1;"><div id="' + loading_id + '"><img src="{!!url('/images/ajax-loader.gif')!!}""></img></div>Patient ' + patient_id + ' has <label ID="' + lbl_id + '"></label> ' + type + '<BR><table align="left" cellpadding="5" cellspacing="5" class="table table-bordered pretty" id="' + tbl_id + '" style="width:60%;border:2px solid"></table></div>';
	  	
	}

	function generateList(project, diagnosis, patient) {
		case_list = [];
		patient_list = [];
		diagnosis_list = [];		
		alert("hi")
		var selected_diags = projects[project].sort();
		selected_diags.forEach(function(d) {
			diagnosis_list.push({value: d, text: d});
		})

		var selected_patients = diagnoses[diagnosis].sort();
		selected_patients.forEach(function(d) {
			patient_list.push({value: d, text: d});
		})

		var selected_cases = patients[patient].sort();

		selected_cases.forEach(function(d) {
			case_list.push({value: d, text: d});
		})
		
	}

	function getFirstProperty(obj) {
		for (key in obj) {
			if (obj.hasOwnProperty(key))
				return key;
		}
	}	

	function showPatientTable(data) {		
		var hide_cols = data.hide_cols;
		var cols = data.cols;
		cols[0] = {
                "class": "details-control",
                "title": "Samples",
                "orderable":      false,                
                "defaultContent": ""
        };
        
        cols[1] = {
                "class": "details-control",
                "title": "Cases",
                "orderable":      false,                
                "defaultContent": ""
        };
		var tbl = $('#w2ui-popup #tblPatient').DataTable( 
		{
			"data": data.data,
			"columns": cols,
			"paging":   false,
			"ordering": false,
			"info":     false,
			"dom": ''
		} );
		
		tbl.columns().iterator('column', function ( context, index ) {
				var show = (hide_cols.indexOf(index) == -1);
				tbl.column(index).visible(show);		
		} );

		//do not show patient_id
		tbl.column(3).visible(false);

		var detailRows = [];
		$('#w2ui-popup #tblPatient tbody').on( 'click', 'tr td.details-control', function () {
	        var tr = $(this).closest('tr');
	        tbl.cell( this ).data("<img width=20 height=20 src='{!!url('images/details_open.png')!!}'></img>");
	        console.log(tbl.cells);
	        var row = tbl.row( tr );
	        var idx = $.inArray( tr.attr('id'), detailRows );
	 
	 		if ( row.child.isShown() ) {
	 			$('w2ui-popup #patient_details').height(110);
	            tr.removeClass( 'details' );
	            row.child.hide();
	            // Remove from the 'open' array
	            detailRows.splice( idx, 1 );
	        }
	        else {
	            tr.addClass( 'details' );
	            $('#w2ui-popup #patient_details').height(300);
	            tbl.cell( this ).data("<img width=20 height=20 src='{!!url('images/details_close.png')!!}'></img>");
	            if (tbl.cell( this ).index().column == 0)
	            	row.child( showDetails( row.data(), 'samples' ) ).show();
	            else
	            	row.child( showDetails( row.data(), 'cases' ) ).show();
	            // Add to the 'open' array
	            if ( idx === -1 ) {
	                detailRows.push( tr.attr('id') );
	            }
	        }
	    } );	    
		
	}
	
	function switch_details(state, block_class, image_id) {
		if ( state === 'collapse' ) {
			state = 'expand';
			$("#" + block_class).css("display","block");
			$("#" + image_id).attr("src",'{!!url('/images/details_close.png')!!}');
		}
		else {
			state = 'collapse';
			$("#"  + block_class).css("display","none");
			$("#"  + image_id).attr("src",'{!!url('/images/details_open.png')!!}');
    	}
    	return state;
	}

	function do_switch(type) {
		if (type == "patient")
			patient_state = switch_details(patient_state, 'patient_details', 'imgPatientDetails');		
	}

	function show_patient_details() {
		$.ajax({ url: '{!!url("/getPatients/null/$patient_id/true")!!}', async: true, dataType: 'text', success: function(json_data) {
				json_data = JSON.parse(json_data);				
				$('#w2ui-popup #loading').css('display', 'none');
				showPatientTable(json_data);
			}
		});
		$('#patient_details').w2popup();
	}	

</script>
<!--div class="card mx-0 my-0 px-0 py-0"-->
<div style="margin:5px">
	<div class="row h6">
		<div class="col-md-12">
			<ol class="breadcrumb" style="margin-bottom:0px;padding:4px 20px 0px 0px;background-color:#ffffff;font-size: 0.75rem;">		
				<li class="breadcrumb-item active">{!!$project_link!!}</font></li><li class="breadcrumb-item active">{!!$default_diagnosis!!}</li><li class="breadcrumb-item active">{!!$patient_id!!}&nbsp;
					<a href="#" onclick="show_patient_details()"><img class='mytooltip' title='Patient details' width=15 height=15 src='{!!url('/')!!}/images/info2.png'></img></a>
				</li>				
			@if (count($patient_projects) > 1)	
			<span style="float:right;display:inline;font-size: 0.75rem;">	
				<label for="selSwitchProject" style="font-size:0.75rem;">&nbsp;&nbsp;&nbsp;&nbsp;Switch project:</label>
				<select id="selSwitchProject" class="form-select" style="width:150px;display:inline;padding:1px 0px;font-size:11px">
				@foreach ($patient_projects as $project_name => $project_id)
					@if ($project_id == $default_project)
						<option value="{!!$project_id!!}" selected="">{!!$project_name!!}</option>
					@else
						<option value="{!!$project_id!!}">{!!$project_name!!}</option>
					@endif			
				@endforeach
				</select>
			</span>				
			@endif
			</ol>
			
		</div>

		<!--div class="col-md-5">
			<span style="float:right;font-size: 0.75rem;">
					<img width="20" height="20" src="{!!url('images/search-icon.png')!!}"></img>
					Projects: 
					<input id="selProjectList" style="width:140px"></input>
					Diagnosis: 
					<input id="selDiagnosisList" style="width:130px"></input>
					Patient: 
					<input id="selPatientList" style="width:90px"></input>	
					<button id='btnGO' class="btn btn-info" style="font-size: 0.5rem;"">GO</button>	
			</span>
		</div-->
	<!--/div-->	
	@if (count($patient_projects) > 1)		
	<!--div class="row">
		<div class="col-md-4">
					<label for="selSwitchProject" style="font-size:11px">Switch project:</label>
					<select id="selSwitchProject" class="form-select" style="width:150px;display:inline;padding:1px 0px;font-size:11px">
					@foreach ($patient_projects as $project_name => $project_id)
						@if ($project_id == $default_project)
							<option value="{!!$project_id!!}" selected="">{!!$project_name!!}</option>
						@else
							<option value="{!!$project_id!!}">{!!$project_name!!}</option>
						@endif			
					@endforeach
					</select>		
		</div>		
	</div-->
	@endif
	</div>
</div>

<div id="patient_details" style="display: none; width: 95%; height: 70%; overflow: auto; background-color=white; padding: 10px">
	<div rel="title">
        Patient: {!!$patient_id!!}
    </div>
	<div rel="body" style="text-align:left;padding:10px;">
		<div id='loading' style="height:70%">
			<img src='{!!url('/images/ajax-loader.gif')!!}'></img>
		</div>
		<table cellpadding="0" cellspacing="0" border="0" class="table table-bordered pretty" id="tblPatient" style='width:100%;'></table>	
	</div>
</div>
<!--div id="tab-tools">
	Cases	
</div-->
<div id="case_container" class="easyui-panel" data-options="border:false" style="width: 100%;height:100%;padding:0px;border-width:0px">
	<div id="tabCases" class="easyui-tabs" data-options="tabPosition:'top',fit:true,plain:true, pill:false,border:false" style="width: 100%;height:100%;padding:5px;overflow:auto;border-width:0px">
	@foreach($case_list as $case_name => $qcase_name)
		<div id='{!!$qcase_name!!}' title='{!!$case_name!!}'></div>
	@endforeach
	@if ($imaging_url != null)
	<div id='rms_imaging' title='RMS Imaging'></div>
	@endif
	</div>
</div>

<script type="text/javascript">
</script>
@stop
