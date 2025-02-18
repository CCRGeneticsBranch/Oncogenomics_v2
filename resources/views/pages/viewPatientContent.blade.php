@section('title', "Patients-$project_id")
{{ HTML::script('js/jquery-3.6.0.min.js') }}

{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}
{{ HTML::style('packages/w2ui/w2ui-1.4.min.css') }}
{!! HTML::style('css/bootstrap.min.css') !!}
{{ HTML::style('css/style.css') }}

{!! HTML::script('js/bootstrap.bundle.min.js') !!}
{!! HTML::script('packages/DataTables/datatables.min.js') !!}
{{ HTML::script('js/bootstrap.bundle.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') }}
{{ HTML::script('js/onco.js') }}
{{ HTML::script('packages/w2ui/w2ui-1.4.min.js')}}
{{ HTML::script('js/FileSaver.js') }}

<meta name="csrf-token" content="{{ csrf_token() }}">

<style>
html, body { height:100%; width:100%;}


.layout-split-west {
	border-right: 0px;
}
.layout-panel {
	overflow-y: hidden;
}
.fancybox-overlay {
    z-index: 1000000 !important;
}
</style>
<script type="text/javascript">
	var tbl;	
	var sample_cols = {!!json_encode($detail_cols)!!};
	var hide_cols = {!!json_encode($col_hide)!!};
	//var hide_cols = ['Project name'];
	var search_text = '{!!$search_text!!}';
	var expanded = false;
	var col_html = '';
	var columns = [];
	var old_key = '';
	var current_patient = '';
	var project_list = [{"id":"any", "text" : "(ANY)"}];
	var project_id = '{!!$project_id!!}';
	var current_mode = 0; //0:insert, 1:edit, 2:delete
	$(document).ready(function() {
		@foreach ($projects as $project)
			project_list.push({"id": '{!!$project->id!!}', "text": "{!!$project->name!!}"});
		@endforeach

		getData();

		$('#btnCancel').click(function(e) {    
			$('#popwindow').window('close');
		});

		$('#btnOK').click(function(e) {
			$('#popwindow').window('close');
			updateDetail(current_patient, current_mode, $('#txtKey').val(), $('#txtValue').val());
		});

		if (search_text != 'any') {
			//getDetails(primary_key);
			$('#search_text').val(search_text);
		}		
	});


	function getData() {
		$("#loadingMaster").css("display","block");
		$('#onco_layout').css('display', 'none');
		var url = '{!!url("/getPatients")!!}' + '/' + project_id + '/' + '{!!$search_text!!}';
		console.log(url);
       	$.ajax({ url: url, async: true, dataType: 'text', success: function(json_data) {
				$("#loadingMaster").css("display","none");
				$('#onco_layout').css('display', 'block');
				data = JSON.parse(json_data);
				if (data.data.length == 0) {
					alert('no data!');
					return;
				}
				col_html = '';
				columns = [];
				showTable(data);

				$('#selProjectList').combobox({
				        panelHeight: '400px',
				        selectOnNavigation: false,
				        valueField: 'id',
				        textField: 'text',
				        editable: true,
				        filter: function (q, row) {
				        	var opts = $(this).combobox('options');
							return row[opts.textField].toUpperCase().indexOf(q.toUpperCase()) >= 0;
				        },
				        onSelect: function(d) {		        	
				        	project_id = d.id;
				        	getData();
				        },
				        data: project_list
				});				
				$('#selProjectList').combobox('setValue', project_id);
			}
		});
	}

	function doFilter() {
    		var id_list = [];
    		var search_text = $('#search_text').val().trim();
    		//if ($('#ckFCID').is(':checked'))
			//	search_text = $('#txtFCID').val().trim();
			if ( search_text != '' ) {
				var url = '{!!url("/getPatientIDs/$project_id")!!}' + '/' + search_text;
				console.log(url);
				$.ajax({ url: url, async: false, dataType: 'text', success: function(json_data) {			
						id_list = JSON.parse(json_data);						
						if (id_list.length == 0) {
							alert('No data for ' + $('#search_text').val().trim());
							return;
						}
					}
				});
			}

			tbl.column(2).search('');
			//tbl.column(4).search('');
			//tbl.column(5).search('');
			var fields = [];
			if ($('#patient_list').val().trim() != '')
				fields = $('#patient_list').val().split(/\s+/);
			fields = fields.concat(id_list);
			var filter_str = '^' + fields.join('$|^') + '$';
			if (fields.length == 0) {
				filter_str = '';
				//$("#btnDownloadJson").attr("disabled", true);
			} else {
				//$("#btnDownloadJson").attr("disabled", false);
			}
			//tbl.column($('#selCol').val()).search(filter_str, true, false);
			tbl.column(2).search(filter_str, true, false);
			tbl.draw();			
    }

	function showTable(data) {
		cols = data.cols;
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

		var inited = false;
		if (tbl != null) {
			//yadcf.exResetAllFilters(tbl, true, []);
			tbl.destroy();
			$('#tblOnco').empty();
			inited = true;
		}

		//hide_cols = data.hide_cols;
       	tbl = $('#tblOnco').DataTable( 
		{
				"data": data.data,
				"columns": cols,
				"ordering":    true,
				"deferRender": true,
				"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
				"pageLength":  15,			
				"processing" : true,			
				"pagingType":  "simple_numbers",			
				"dom": 'B<"toolbar">lfrtip',
				"buttons": []
		} );

		$('#lblCountDisplay').text(tbl.page.info().recordsDisplay);
    	$('#lblCountTotal').text(tbl.page.info().recordsTotal);

		$('#tblOnco').on( 'draw.dt', function () {
			$('#lblCountDisplay').text(tbl.page.info().recordsDisplay);
    		$('#lblCountTotal').text(tbl.page.info().recordsTotal);
    	});

		var detailRows = [];
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

		var html = '';
		@if (\App\Models\User::accessAll() && $source == "normal" && !Config::get('onco.isPublicSite')) 
			html = '<!--button class="btn" id="btnJson">JSON</button-->'
		@endif
		html += '<button class="btn btn-primary mx-1 my-1" id="btnDownload">Download</button><button class="btn btn-primary mx-1 my-1" id="btnShowAll">Show All</button>';
		$("div.toolbar").html(html + '<button id="popover" data-toggle="popover" data-placement="bottom" type="button" class="btn btn-success mx-1 my-1" >Select Columns</button>');
		tbl.columns().iterator('column', function ( context, index ) {
				var col_text = tbl.column(index).header().innerText;
				var show = (hide_cols.indexOf(col_text) == -1);
				tbl.column(index).visible(show, false);
				columns.push(col_text);
				checked = (show)? 'checked' : '';
				col_html += '<input type=checkbox ' + checked + ' class="onco_checkbox" id="data_column" value=' + index + '><font size=3>&nbsp;' + col_text + '</font></input><BR>';
		});		

		$('[data-toggle="popover"]').popover({
				title: 'Select column <a href="#inline" class="close" data-dismiss="alert">×</a>',
				placement : 'bottom',  
				html : true,
				sanitize: false,
				content : function() {
					return col_html;
				}
			});

		$(document).on("click", ".popover .close" , function(){
			$('[data-toggle="popover"]').popover('hide');
		});

		$('body').on('change', 'input#data_column', function() {             
				col_html = '';
				for (i = 0; i < columns.length; i++) { 
					if (i == $(this).attr("value"))
						checked = ($(this).is(":checked"))?'checked' : '';
					else
						checked = (tbl.column(i).visible())?'checked' : '';
					col_html += '<input type=checkbox ' + checked + ' class="onco_checkbox" id="data_column" value=' + i + '><font size=3>&nbsp;' + columns[i] + '</font></input><BR>';
				}
				tbl.column($(this).attr("value")).visible($(this).is(":checked"), false);
				
		});

		$('#tblOnco').on( 'xhr.dt', function () {			
				$('#loadingMaster').css('display', 'none');
				$('#onco_layout').css('visibility', 'visible');
		});

		$('#fcid_list').on('keyup', function() {			
			$('#json_filter').css('display',"none");
			$('#patient_list').val('');
       	});

       	$('#patient_list').on('keyup', function() {
       		$('#json_filter').css('display',"block");
			$('#fcid_list').val('');
       	});	

		$('#btnDownloadJson').on('click', function() {
			doFilter();
			downloadJson();
       	});

       	$('#btnJson').on('click', function() {
       		$('#onco_layout').layout('expand','west');
       		doFilter();       			
			downloadJson();
       	});

       	$('#btnShowAll').on('click', function() {
       		tbl.search('');
       		tbl.draw();
       	});

		$('#btnDownload').on('click', function() {
       		var url = '{!!url("/getPatients")!!}' + '/' + project_id + '/any/false/text';
			console.log(url);
			window.location.replace(url);
       	});       	

		$('#btnSaveJson').on('click', function() {	
			var json_data = $('#txtJson').val();
			var file_name = $('#txtJsonFileName').val();
       		var blob = new Blob([json_data], {type: "text/plain;charset=utf-8"});
  			saveAs(blob, file_name);
  		});

       	$('#btnRunPipeline').on('click', function() {
			var json_data = $('#txtJson').val();
			var file_name = $('#txtJsonFileName').val();
			var dest = $('#selDestination').val();			
			console.log(json_data);
			var url = '{!!url("/runPipeline")!!}';
			$.ajaxSetup({
			  headers: {
				    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			  }
			});
			$.ajax({ url: url, async: true, type: 'POST', dataType: 'text', data: {patient_id: patient_id, dest: dest, file_name: file_name, data: JSON.parse(json_data)}, success: function(data) {
						var results = JSON.parse(data);
						if (results.code == "no_user") {
								alert("Please login first!");
								return;
						}
						if (results.code == "success") {
								console.log(results.desc);
								project_id = results.desc;
								alert("Successful!");
								return;
						}
						if (results.code == "error") {
								alert("Save failed: reason:" + results.desc);
								return;
						}					

					}, error: function(xhr, textStatus, errorThrown){
							alert("Save failed: reason:" + JSON.stringify(xhr) + ' ' + errorThrown);
					}
				});
       	});

    }

	function showDetails ( d, type ) {
		//type = "sample";
		var patient_link = document.createElement("div");
		patient_id = getInnerText(d[2]);
		var url = (type == 'samples')? '{!!url('/getSampleByPatientID')!!}' + '/' + project_id + '/' + patient_id : '{!!url('/getCasesByPatientID')!!}' + '/' + project_id + '/' + patient_id;
		tbl_id = "tbl" + patient_id;
		loading_id = "loading" + patient_id;
		lbl_id = "lbl" + patient_id;
		num_samples = 0;
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
					try{
						data = JSON.parse(data);
					} catch(e) {
						$('#' + loading_id).css("display","none");
						alert("Session expired, please login again!");
					}
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
		return '<div style="padding: 20px;margin: 0px 0px 0px;font-size: 13px;line-height:1;"><div id="' + loading_id + '"><img src="{!!url('/images/ajax-loader.gif')!!}""></img></div>Patient ' + patient_id + ' has <label ID="' + lbl_id + '"></label> ' + type + '<BR><table align="left" cellpadding="5" cellspacing="5" class="prettyDetail" word-wrap="break-word" id="' + tbl_id + '" style="width:60%;border:2px solid"></table></div>';
	  	
	}

    function downloadJson() {
    	if ($('#fcid_list').val() != '') {
    		var use_sample_name = 'n';
    		if ($('#ckUseSampleName').is(':checked'))
				use_sample_name = 'y';
			var case_name = "all";
			if ($('#ckCaseName').is(':checked'))
				case_name = $('#txtCaseName').val().trim();
			var json_data = {"fcid_list" : $('#fcid_list').val(), "use_sample_name" : use_sample_name, "case_name" : case_name};
    		var url = '{!!url("/getPatientsJsonByFCID")!!}';
    		console.log(url);
    		$("#loadingJson").css("display","inline");
    		
    		$.ajaxSetup({
			  headers: {
				    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			  }
			});

    		$.ajax({ url: url, async: true, type: 'POST', dataType: 'text', data: json_data, success: function(data) {
    					$("#loadingJson").css("display","none");
						json_data = JSON.parse(data);
						var json_str = JSON.stringify(json_data, undefined, 4);
						$('#txtJson').val(json_str);
						//document.getElementById('txtJson').innerHTML = json_str;
						jQuery.fancybox.open({href: "#json_content"});	
						//.trigger('click');					
												
					}, error: function(xhr, textStatus, errorThrown){
						alert('download JSON failed! Reason:' + JSON.stringify(xhr) + ' ' + errorThrown);
					}
				});					
    		return;
    	}


    			var id_list = '';

				$('#tblOnco').DataTable().$('tr', {"filter":"applied"}).each(function(i){
					$(this).children('td').each(function(j){						
						if (j==2) {
							var patient_link = document.createElement("div");
							patient_link.innerHTML = $(this).html();
							//patient_id = patient_link.innerText;
							patient_id = patient_link.textContent;
							id_list = id_list + ',' + patient_id;
						}
							
					});
				});
        	
        		/*
				tbl.rows({filter: 'applied'}).every( function ( rowIdx, tableLoop, rowLoop ) {
					var data = this.data();
					var patient_link = document.createElement("div");
					patient_link.innerHTML = data[3];
					patient_id = patient_link.innerText;
					id_list = id_list + ',' + patient_id;
				});
				*/
				var type_list = [];
				if ($('#ckExome').is(':checked'))
					type_list.push('Exome');
				if ($('#ckPanel').is(':checked'))
					type_list.push('Panel');
				if ($('#ckWholeGenome').is(':checked'))
					type_list.push('Whole Genome');
				if ($('#ckRNASeq').is(':checked'))
					type_list.push('RNAseq');
				if ($('#ckMethylSeq').is(':checked'))
					type_list.push('Methylseq');
				/*
				var fcid_text = 'null';
				if ($('#ckFCID').is(':checked')) {
					fcid_text = $('#txtFCID').val().trim();
					if (fcid_text.trim() == '') {
						alert('FCID is empty');
						return;
					}					
				}
				*/

				var use_sample_name = 'n';
				if ($('#ckUseSampleName').is(':checked'))
					use_sample_name = 'y';
				var case_name = "all";
				if ($('#ckCaseName').is(':checked'))
					case_name = $('#txtCaseName').val().trim();
				
				var json_data = {"type_list" : type_list.join(), "use_sample_name" : use_sample_name, "id_list" : id_list.substr(1), "case_name" : case_name};
				var url = '{!!url("/getPatientsJson")!!}';
				var patient_list = json_data.id_list.split(',');
				var d = new Date();
				var year = d.getFullYear().toString();
				var month = (d.getMonth() + 1);
				var day = d.getDate();
				if (month < 10)
					month = "0" + month.toString();
				else
					month = month.toString();
				if (day < 10)
					day = "0" + day.toString();
				else
					day = day.toString();
				patient_id = "Clinomics";
				if (patient_list.length == 1)
					patient_id = patient_list[0];					
				$('#txtJsonFileName').val(patient_id + '=' + case_name + '=' + year + month + day + ".json");
				$("#loadingJson").css("display","inline");

				$.ajaxSetup({
			  		headers: {
				    	'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			  		}
				});

				$.ajax({ url: url, async: true, type: 'POST', dataType: 'text', data: json_data, success: function(data) {
						$("#loadingJson").css("display","none");
						json_data = JSON.parse(data);
						var json_str = JSON.stringify(json_data, undefined, 4);
						$('#txtJson').val(json_str);
						//document.getElementById('txtJson').innerHTML = json_str;
						jQuery.fancybox.open({href: "#json_content"});	
						//.trigger('click');					
												
					}, error: function(xhr, textStatus, errorThrown){
						alert('download JSON failed! Reason:' + JSON.stringify(xhr) + ' ' + errorThrown);
					}
				});					
    }

	function getDetails(patient_id) {
		//if (!expanded) {
			$("#onco_layout").layout('expand','south');
			$("#onco_layout").layout('expand','east');
			//expanded = true;
		//}		
		var sample_url = '{!!$detail_url!!}' + '/' + patient_id;
		current_patient = patient_id;
		updateDetailTable(patient_id);
		tblSampleDetail.ajax.url(sample_url).load();
		$("#onco_layout").layout('panel', '{!!$detail_pos!!}').panel('setTitle', 'Details - ' + patient_id);
	}

	function showEditForm(patient_id, mode, key, value) {
		$('#popwindow').window('open');
		$('#txtKey').val(key);
		$('#txtValue').val(value);
		$('#txtKey').focus();
		old_key = key;
		current_patient = patient_id;
		current_mode = mode;
	}

	function updateDetail(patient_id, mode, key, value) {
		url = '{!!url("/addPatientDetail")!!}' + '/' + patient_id + '/' + key + '/' + value;
		message = 'insert';
		if (mode == 1) {
			url = '{!!url("/updatePatientDetail")!!}' + '/' + patient_id + '/' + old_key + '/' + key + '/' + value;
			message = 'update';
		}
		if (mode == 2) {
			url = '{!!url("/deletePatientDetail")!!}' + '/' + patient_id + '/' + key;
			message = 'delete';
		}
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				alert(message + " successful!");
				updateDetailTable(patient_id);
			}
		});
	}

	function deleteDetail(patient_id, key) {
		updateDetail(patient_id, 2, key, '');
	}

	function updateDetailTable(patient_id) {
		var detail_url = "{!!url('/getPatientDetails/')!!}" + "/" + patient_id;
		tblDetail.ajax.url(detail_url).load();
	}
</script>


<div id="json_content" style="display:none;width:1200px;height:100%">
	<div id="main" class="container-fluid" style="padding:10px" >
			<div class="row">
				<div class="col-md-12">
					<textarea id='txtJson' rows="30" cols="150"></textarea>
				</div>
			</div>
			<div class="row" style="padding:10px">
				<div class="col-md-6">
					<div class="input-group">
						<span class="input-group-addon">JSON file name</span>
						<input id="txtJsonFileName" type="text" class="form-control" placeholder="JSON file name">						
					</div>
				</div>
				<div class="col-md-2">
					<button id="btnSaveJson" class="btn btn-info" style="width:100%;padding:10px">Save</button>
				</div>
			</div>			
	</div>
</div>

<div class="easyui-panel" style="padding:0px;border:0px">
	<div id='loadingMaster' style="height:{!!($source == "normal")?90:98!!}%;">
    		<img src='{!!url('/images/ajax-loader.gif')!!}'></img>
	</div>	
	<div id="onco_layout" class="easyui-layout" data-options="fit:true,border:false" style="display:none;height:{!!($source == "normal")?90:98!!}%;">
		@if (\App\Models\User::isPipelineExecutor() && $source == "normal" && !Config::get('onco.isPublicSite')) 
		<div class="easyui-panel" id="json_panel" data-options="region:'west',split:true, border:false, collapsed:true" style="width:250px;" title="Download JSON">
			<table>
				<tr><td>
					<div style="display:none">
						Keyword Search: <input id='search_text' type='text' style="border:1px solid #0091EA"/>
					</div>
				</td></tr>
				<tr><td>
					Search patients:
				</td></tr>
				<tr><td>	
					<textarea id="patient_list" name="patient_list"  rows="5" cols="18"></textarea>
				</td></tr>
				<tr><td>
					Search FCID:
				</td></tr>
				<tr><td>	
					<textarea id="fcid_list" name="fcid_list"  rows="5" cols="18"></textarea>
				</td></tr>
				<tr><td>
					<input type="checkbox" id="ckUseSampleName">Use sample name</input>					
				</td></tr>				
				<tr><td>
					<div id="json_filter">
						<HR><H4>JSON filters</H4>
						<H5 style="color:red">Experient type:</H5>
						<input type="checkbox" id="ckExome" checked>Exome</input><br>
						<input type="checkbox" id="ckPanel" checked>Panel</input><br>
						<input type="checkbox" id="ckWholeGenome" checked>Whole Genome</input><br>
						<input type="checkbox" id="ckRNASeq" checked>RNAseq</input><br>
						<input type="checkbox" id="ckMethylSeq" checked>Methylseq</input><br>						
						<input type="checkbox" id="ckCaseName" checked style="color:red">Case Name:</input>
						<input type="text" id="txtCaseName" style="border:1px solid #0091EA"></input><br>
					</div>
					<br>										
				</td></tr>
				<tr><td>
					<hr>
					<button id="btnDownloadJson" class="btn btn-info"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Download JSON</button><img style="display:none" id='loadingJson' width=40 height=40 src='{!!url('/images/ajax-loader-sm.gif')!!}'></img>
				</td></tr>				
			</table>
		</div>
		@endif
		<div data-options="region:'center',split:true, border:false" style="width:100%;padding:5px;overflow:none;" >
			<div style="margin:10px 0;">
				@if ($source == "normal")
				Projects: 
				<input id="selProjectList"></input>
				@endif
				<span style="font-family: monospace; font-size: 20;float:right;">	
				Patients: <span id="lblCountDisplay" style="text-align:left;color:red;" text=""></span>/
						<span id="lblCountTotal" style="text-align:left;" text=""></span>
				</span>
			</div>			
			<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblOnco" style='width:100%;'>
			</table> 			

		</div>			
	</div>
</div>