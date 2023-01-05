@extends('layouts.default')
@section('content')

{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('packages/bootstrap-switch-master/dist/css/bootstrap3/bootstrap-switch.css') }}
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}
{{ HTML::style('packages/bootstrap-3.3.7/dist/css/bootstrap.min.css') }}
{{ HTML::style('packages/bootstrap-3.3.7/dist/css/checkbox.css') }}
{{ HTML::style('packages/bootstrap-3.3.7/dist/css/font-awesome.css') }}
{{ HTML::style('packages/w2ui/w2ui-1.4.min.css') }}

{{ HTML::script('packages/DataTables-1.10.8/media/js/jquery.dataTables.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/dataTables.buttons.min.js') }}
{{ HTML::script('packages/yadcf-0.8.8/jquery.dataTables.yadcf.js')}}
{{ HTML::script('packages/bootstrap-3.3.7/dist/js/bootstrap.min.js') }}
{{ HTML::script('js/togglebutton.js') }}
{{ HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') }}
{{ HTML::script('packages/bootstrap-switch-master/dist/js/bootstrap-switch.js') }}
{{ HTML::script('packages/w2ui/w2ui-1.4.min.js')}}
{{ HTML::script('packages/highchart/js/highcharts.js')}}

<script type="text/javascript">
	var tbl;
	var tblCheckout;
	var show_cols = [0,1,2,3,4,5,6,9,10,11];
	var patient_idx = 2;
	var col_html = '';
	var columns = [];
	var selected_diagnosis = {};
	var patient_cart = {{$patients}};
	var patient_data;
	var project_id = '{{$project_id}}';
	var project_name = '{{$project_name}}';
	var project_desc = '{{$project_desc}}';
	var project_ispublic = ('{{$project_ispublic}}' == '1');
	
	$(document).ready(function() {
		var url = '{{url("/getPatientTree")}}';
		$.ajax({ url: url, async: true, dataType: 'text', success: function(json_data) {
				$('#oncotree').tree({data : JSON.parse(json_data), method:'get', cascadeCheck:true, checkbox:true, onCheck: function(node){
						var nodes = $('#oncotree').tree("getChecked");	
						selected_diagnosis = {};
						for(var i=0; i<nodes.length; i++) {  
							var node = nodes[i];
							if (node.checked) {
								selected_diagnosis[node.name.toLowerCase()] = '';
							}
						}
						//console.log(JSON.stringify(selected_diagnosis));
						doFilter();						

					}
				});
				var root = $('#oncotree').tree("getRoot");				
			}
		});
		
		getData();

		$.fn.dataTableExt.afnFiltering.push( function( oSettings, aData, iDataIndex ) {			
			if (oSettings.nTable != document.getElementById('tblOnco'))
				return true;
			var diagnosis_idx = 4;
			var patients = {};
			var root = $('#oncotree').tree("getRoot");
			var root_checked = root.checked;
			
			if (root_checked)
				return true;
			return selected_diagnosis.hasOwnProperty(aData[diagnosis_idx].toLowerCase());						
		});

		$("#btnEmpty").click(function() {
			patient_cart = [];
			tbl.draw();
			$('#cart_count').text(patient_cart.length);			
		});

		$("#btnCheckout").click(function() {
			if (patient_cart.length == 0) {
				w2alert('<H4>No data in cart!</H4>');
				return;
			}
			$('#form_checkout').w2popup();
			if (tblCheckout != null)
				tblCheckout.destroy();
			$('#w2ui-popup #tblCheckout').empty();			

			var checkout_data = [];
			var diagnosis_data = [];
			patient_data.data.forEach(function(d){				
				var patient_html = d[patient_idx];
				var patient_link = document.createElement("div");
				var patient_id = getInnerText(patient_html);
				if (patient_cart.indexOf(patient_id) != -1) {
					var row_data = d.slice();
					row_data.splice(0, 1);
					checkout_data.push(row_data);
					diagnosis_data.push(d[patient_idx+1]);
				}
			});

			var cols = patient_data.cols.slice();
			cols.splice(0, 1);
			var show_cols = [0,1,2,3,4,5,8,9,10];
			tblCheckout = showTable({data: checkout_data, cols: cols}, show_cols, patient_idx - 1, "w2ui-popup #tblCheckout", 0, 'w2ui-popup #Checkout');
						
			pie_data = getPieChartData(diagnosis_data);	
			showPieChart("w2ui-popup #diag_pie", "Diagnosis", pie_data, function (p) {
				console.log(p.name);
				tblCheckout.search(p.name);
				tblCheckout.draw();
			});

			$('#w2ui-popup #project_name').val(project_name);
			$('#w2ui-popup #project_desc').val(project_desc);
			$('#w2ui-popup #ispublic').prop("checked", project_ispublic);

			$('#w2ui-popup #btnSave').click(function(){
				var name = $('#w2ui-popup #project_name').val();
				var desc = $('#w2ui-popup #project_desc').val();
				var ispublic = $('#w2ui-popup #ispublic').is(":checked");
				if (name.trim() == "") {
					w2alert("<H5>Please input project name</H5>");
					return;
				}
				if (desc.trim() == "") {
					w2alert("<H5>Please input project name</H5>");
					return;
				}
				if (desc.length > 4000) {
					w2alert("<H5>The description is longer than 4000 characters</H5>");
					return;
				}
				var json_data = {id:project_id, name:name, desc: desc, ispublic: ispublic, patients: patient_cart};
				console.log(JSON.stringify(json_data));
				var url = '{{url("/saveProject")}}';
				w2popup.open({body: "<img src='{{url('/images/ajax-loader.gif')}}'></img><H4>Processing project data...</H4>", width: 300, height: 200});
				var t0 = performance.now();
				$.ajax({ url: url, async: true, type: 'POST', dataType: 'text', data: json_data, success: function(data) {
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
							project_id = results.desc;
							w2alert("<H4>Successful! Please <a target='_blank' href='{{url('/viewProjectDetails')}}" + "/" + results.desc + "'>click here</a> to view the results. The RNAseq results are still processing. You will receive an email when it is finished</H4>");
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
				project_name = name;
				project_desc = desc;
				project_ispublic = ispublic;
			});
		});

		
		
	});

	function doFilter() {
		tbl.draw();
	}

	function getData() {
		$("#loadingMaster").css("display","block");
		$('#onco_layout').css('display', 'none');
		var url = '{{url("/getPatients/any/any")}}';
		console.log(url);
       	$.ajax({ url: url, async: true, dataType: 'text', success: function(json_data) {
				$("#loadingMaster").css("display","none");
				$('#onco_layout').css('display', 'block');
				patient_data = JSON.parse(json_data);
				if (patient_data.data.length == 0) {
					alert('no data!');
					return;
				}
				patient_data.cols.splice(0, 0, {title:'Cart'});
				for (var i=0; i<patient_data.data.length; i++) {
					var patient_html = patient_data.data[i][patient_idx];
					var patient_link = document.createElement("div");
					var patient_id = getInnerText(patient_html);
					patient_data.data[i].splice(0, 0, "<div class='checkbox checkbox-primary'><input id='ck_" + patient_id + "' class='styled ckCart' type='checkbox'><label for='" + patient_id + "'></label></div>");
				}
				col_html = '';
				columns = [];
				patient_idx++;
				tbl = showTable(patient_data, show_cols, patient_idx);					
				$('#cart_count').text(patient_cart.length);
				$('#tblOnco tbody').on('click', 'input[type="checkbox"]', function(e) {
					var patient_id = $(this).prop("id").substr(3);					
					if ($(this).is(":checked")) {
						patient_cart.push(patient_id);						
					}
					else {
						patient_cart.splice(patient_cart.indexOf(patient_id), 1);						
					}
					$('#cart_count').text(patient_cart.length);					
				});

				$('#btnAddAll').click(function() {
					tbl.$('tr', {"filter":"applied"}).each( function () {
						var patient_id = $(this).find("td:eq(" + patient_idx + ")").text();
						$('#ck_' + patient_id).prop('checked', true);
						patient_cart.push(patient_id);										
					});
					patient_cart = unique_array(patient_cart);	
					$('#cart_count').text(patient_cart.length);
				});					
			}
		});
	}

	function showTable(data, show_cols, patient_idx, tbl_div="tblOnco", detail_idx=1, prefix="") {
		cols = data.cols;
		cols[detail_idx] = {
                "class": "details-control",
                "title": "Samples",
                "orderable":      false,                
                "defaultContent": ""
        };
        
        cols[detail_idx+1] = {
                "class": "details-control",
                "title": "Cases",
                "orderable":      false,                
                "defaultContent": ""
        };
		
		hide_cols = data.hide_cols;
       	var tbl = $('#' + tbl_div).DataTable( 
		{
				"data": data.data,
				"columns": cols,
				"ordering":    true,
				"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
				"pageLength":  25,			
				"processing" : true,			
				"pagingType":  "simple_numbers",			
				"dom": 'lf<"toolbar">tip',
				"rowCallback": function(row, data, dataIndex){
						       	var patient_html = data[patient_idx];
								var patient_link = document.createElement("div");
								var patient_id = getInnerText(patient_html);
								$(row).find('input[type="checkbox"]').prop("checked", (patient_cart.indexOf(patient_id) != -1));
								//$("#ck_" + patient_id).prop("checked", (patient_cart.indexOf(patient_id) != -1));
							}
		} );		

		$('#' + tbl_div).on( 'draw.dt', function () {
			refreshCount(tbl, prefix ,tbl_div);

    	});

		var detailRows = [];
		$('#' + tbl_div + ' tbody').on( 'click', 'tr td.details-control', function () {
	        var tr = $(this).closest('tr');
	        tbl.cell( this ).data("<img width=20 height=20 src='{{url('images/details_open.png')}}'></img>");
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
	            tbl.cell( this ).data("<img width=20 height=20 src='{{url('images/details_close.png')}}'></img>");
	            if (tbl.cell( this ).index().column == 0)
	            	row.child( showDetails( row.data(), 'samples', patient_idx ) ).show();
	            else
	            	row.child( showDetails( row.data(), 'cases' , patient_idx) ).show();
	            // Add to the 'open' array
	            if ( idx === -1 ) {
	                detailRows.push( tr.attr('id') );
	            }
	        }
	    } );

		if (tbl_div == "tblOnco") {
			var html = '<button id="btnAddAll" type="button" class="btn btn-default btn-sm"><span class="glyphicon glyphicon-shopping-cart"></span><span id="addText">&nbsp;Add All to Cart</span></button>&nbsp;';
			$("div.toolbar").html(html + '<button id="popover" data-toggle="popover" data-placement="bottom" type="button" class="btn btn-default" style="font-size: 12px;">Select Columns</button>');
		}
		tbl.columns().iterator('column', function ( context, index ) {
				var show = (show_cols.indexOf(index) != -1);
				tbl.column(index).visible(show);
				if (tbl_div == "tblOnco") {
					columns.push(tbl.column(index).header().innerHTML);
					checked = (show)? 'checked' : '';
					col_html += '<input type=checkbox ' + checked + ' class="onco_checkbox" id="data_column" value=' + index + '><font size=3>&nbsp;' + tbl.column(index).header().innerHTML + '</font></input><BR>';
				}
		});		

		if (tbl_div == "tblOnco") {
			$('[data-toggle="popover"]').popover({
					title: 'Select column <a href="#" class="close" data-dismiss="alert">×</a>',
					placement : 'bottom',  
					html : true,
					content : function() {
						return col_html;
					}
			});

			$(document).on("click", ".popover .close" , function(){
				$(this).parents(".popover").popover('hide');
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
					tbl.column($(this).attr("value")).visible($(this).is(":checked"));
					
			});

			$('#tbl' + tbl_div).on( 'xhr.dt', function () {			
					$('#loadingMaster').css('display', 'none');
					$('#onco_layout').css('visibility', 'visible');
			});			
		}

		refreshCount(tbl, prefix, tbl_div);

		return tbl;
    }

	function showDetails ( d, type, patient_idx ) {
		console.log(patient_idx);
		var patient_link = document.createElement("div");
		patient_id = getInnerText(d[patient_idx]);
		var url = (type == 'samples')? '{{url('/getSampleByPatientID')}}' + '/any/' + patient_id : '{{url('/getCasesByPatientID')}}' + '/any/' + patient_id;
		tbl_id = "tbl" + patient_id;
		loading_id = "loading" + patient_id;
		lbl_id = "lbl" + patient_id;
		num_samples = 0;
		console.log(url);
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
		return '<div style="padding: 20px;margin: 0px 0px 0px;font-size: 13px;line-height:1;"><div id="' + loading_id + '"><img src="{{url('/images/ajax-loader.gif')}}""></img></div>Patient ' + patient_id + ' has <label ID="' + lbl_id + '"></label> ' + type + '<BR><table align="left" cellpadding="5" cellspacing="5" class="prettyDetail" word-wrap="break-word" id="' + tbl_id + '" style="width:60%;border:2px solid"></table></div>';
	  	
	}

	function refreshCount(tbl, prefix="", tbl_div) {
		$('#' + prefix + 'lblCountDisplay').text(tbl.page.info().recordsDisplay);
    	$('#' + prefix + 'lblCountTotal').text(tbl.page.info().recordsTotal);
    	if (tbl_div == "tblOnco")
    		$('#addText').text(" Add All " + tbl.page.info().recordsDisplay + " Patients to Cart");
	}

</script>

<div id="form_checkout" style="display: none; width: 95%; height: 95%; overflow: auto; background-color=white;">
	<div style="padding:10px">
		<div id="main" class="container-fluid" style="padding:10px" >
			<div class="row">
				<div class="col-md-6">
					<table class="table table-bordered table-hover">
						<tr>
							<th>Project Name</th>
							<td>
								<input id="project_name" class="form-control"></input>
							</td>
						</tr>
						<tr>
							<th>Description</th>
							<td>
								<textarea id="project_desc" class="form-control"></textarea>
							</td>
						</tr>
						<tr>
							<th>Public</th>
							<td>
								<div class='checkbox checkbox-primary'>
									<input id="ispublic" class="styled" type="checkbox"></input>
									<label for='ispublic'></label>
								</div>
							</td>
						</tr>
						<tr>
							<th colspan="2"><button id="btnSave" class="btn btn-info btn-md">
			          			<span class="glyphicon glyphicon-send"></span>&nbsp;Save</span>
			        		</button></th>							
						</tr>
					</table>
				</div>
				<div class="col-md-6">
					<div id="diag_pie" style="height: 250px;margin: 0 auto;"></div>
				</div>
			</div>
			<HR>
			<div class="row">
				<div class="col-md-12">
					<span style="width:98%;">				
						<span style="font-family: monospace; font-size: 20;float:right;padding-right:40px">	
								        			        
							Patients: <span id="CheckoutlblCountDisplay" style="text-align:left;color:red;" text=""></span>/
									<span id="CheckoutlblCountTotal" style="text-align:left;" text=""></span>
						</span>						
					</span>
					<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblCheckout" style='width:100%;'>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="easyui-panel" style="padding:10px;border:0px">
	<div id='loadingMaster' style="height:98;">
    		<img src='{{url('/images/ajax-loader.gif')}}'></img>
	</div>	
	<div id="onco_layout" class="easyui-layout" data-options="fit:true,border:false" style="height:98%;">
		<div class="easyui-panel" data-options="region:'west',split:true, border:false, collapsed:false" style="width:350px;padding:10px;overflow:none;">
			<ul id="oncotree"></ul>
		</div>		
		<div data-options="region:'center',split:true, border:false" style="width:100%;padding:10px;overflow:none;" >			
			<span style="width:98%;">				
				<span style="font-family: monospace; font-size: 20;float:right;padding-right:40px">	
					<button id="btnCheckout" class="btn btn-info btn-md">
	          			<span class="glyphicon glyphicon-shopping-cart"></span>&nbsp;Checkout&nbsp;<span id="cart_count" class="badge">0</span>
	        		</button>	        		
	        		<button id="btnEmpty" class="btn btn-warning btn-md">
	          			<span class="glyphicon glyphicon-trash"></span>&nbsp;Empty
	        		</button>
					Patients: <span id="lblCountDisplay" style="text-align:left;color:red;" text=""></span>/
							<span id="lblCountTotal" style="text-align:left;" text=""></span>
				</span>
			</span>
			<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblOnco" style='width:100%;'>
			</table> 
		</div>			
	</div>
</div> 
@stop
