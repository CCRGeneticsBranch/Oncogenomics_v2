@extends('layouts.default')
@section('title', "Sync Public")
@section('content')

{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::style('css/style.css') }}
{{ HTML::style('css/font-awesome.min.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-core-css.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-blue/sm-blue.css') }}    
{{ HTML::script('js/jquery-3.6.0.min.js') }}
{{ HTML::script('packages/smartmenus-1.0.0-beta1/jquery.smartmenus.min.js') }}

{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}

{!! HTML::script('packages/DataTables/datatables.min.js') !!}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') }}
{{ HTML::script('js/onco.js') }}

<meta name="csrf-token" content="{{ csrf_token() }}">

<style>

html, body { height:100%; width:100%;}

th {
    white-space: nowrap;
}

.btn-default.active {
    background-color: DarkCyan;
    border-color: #000000;
    color: #fff;
}

.modal-title{
  font-size: 20px;
}
.modal-backdrop.in { z-index: auto;}

.modal { z-index: 99999;}

</style>
<script type="text/javascript">
	var tbls = [];	
	$(document).ready(function() {
		//$('.alert').alert('close');
		$.ajaxSetup({
					  headers: {
					    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
					  }
					});
		getData();



		$.fn.dataTableExt.afnFiltering.push( function( oSettings, aData, iDataIndex ) {
			var status_idx = 6;
			if ($('#ckPending').is(":checked"))
				return (aData[6] == "pending");
			return true;
		});
			
	});	

	function doFilter() {
		tbl.draw();
	}

	function getData() {
		$("#loadingMaster").css("display","block");
		$('#onco_layout').css('visibility', 'hidden');		
		var url = '{{Config::get("site.url_public")}}/getProjects';
		var internal_url = '{{url("/")}}';
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', data: '{"token":"{{Config::get("site.public_token")}}"}', method: 'post', headers: {'Accept': 'application/json','Content-Type': 'application/json'}, success: function(public_json_data) {					
					public_data = JSON.parse(public_json_data);
					public_data.cols = [{"title":"Action"},{"title":"Name"},{"title":"Description"}];
					var rows = [];
					public_data.forEach(function(d, i) {
						rows.push(['<a href="javascript:sync(\'' + d.name + '\',\'resync\')"><i class="fa fa-history" aria-hidden="true"></i>&nbsp;Resync</a>', d.name, d.description]);
					});
					public_data.data = rows;
					if (public_data.data.length == 0) {
						alert('no data!');
						return;
					}
					showTable(public_data, 'tblPublic');
					var url = internal_url + '/getProjects';
					console.log(url);
					$.ajaxSetup({
					  headers: {
					    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
					  }
					});
	       	$.ajax({ url: url, async: true, dataType: 'text', data: '{"token":"{{Config::get("site.token")}}"}', method: 'post', headers: {'Accept': 'application/json','Content-Type': 'application/json'}, success: function(json_data) {
	       		console.log(json_data);
							$("#loadingMaster").css("display","none");
							$('#onco_layout').css('visibility', 'visible');
							data = JSON.parse(json_data);
							data.cols = [{"title":"Action"},{"title":"Name"},{"title":"Description"}];
							var rows = [];
							data.forEach(function(d, i) {
								rows.push(['<a href="javascript:sync(\'' + d.name + '\', \'sync\')"><i class="fa fa-exchange" aria-hidden="true"></i>&nbsp;Sync</a>', d.name, d.description]);

							});
							data.data = rows;
							if (data.data.length == 0) {
								alert('no data!');
								return;
							}
							showTable(data, 'tblInternal');
						}
					});
			}				
		});
		$('#button_yes').on('click', function (event) {
  		var button = $(event.relatedTarget) // Button that triggered the modal  		
  		var project_name = $('#project_name').text();
  		console.log(project_name);
  		var url = '{{url("/syncPublicProject")}}' + '/' + project_name;
			console.log(url);
			$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
					$('#sync_modal').modal('hide');
		    	if (data == "ok")
					 	$('.alert').show();
					else
						alert("Error! " + data);
				}
			});
  	});
	}

	function sync(name, action) {
		$('#action').text(action);
		$('#project_name').text(name);
		$('#sync_modal').modal();
	}
	function showTable(data, tbl_ID) {
		cols = data.cols;		

		//hide_cols = data.hide_cols;
		tbl = $('#' + tbl_ID).DataTable( 
		{
				"data": data.data,
				"columns": cols,
				"ordering":    true,
				"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
				"pageLength":  15,			
				"processing" : true,			
				"pagingType":  "simple_numbers",			
				"dom": 'lfrtip'
		} );
		tbls[tbl_ID] = tbl;

		$('#' + tbl_ID + 'CountDisplay').text(tbl.page.info().recordsDisplay);
    $('#' + tbl_ID + 'CountTotal').text(tbl.page.info().recordsTotal);

    $('#' + tbl_ID).on( 'draw.dt', function () {
			$('#' + tbl_ID + 'CountDisplay').text(tbls[tbl_ID].page.info().recordsDisplay);
    	$('#' + tbl_ID + 'CountTotal').text(tbls[tbl_ID].page.info().recordsTotal);
    });
	}		

        

	
</script>
<div id='loadingMaster' style="height:90%">
	  		<img src='{{url('/images/ajax-loader.gif')}}'></img>
</div>
<div class="alert alert-success" role="alert" style="display:none">
  The sync is launched
  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>
</div>
<div class="row" style="padding:20px">
				<div class="col-md-6">					
					<span style="font-family: monospace; font-size: 20;float:right;">					
					Internal site projects: <span id="tblInternalCountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="tblInternalCountTotal" style="text-align:left;" text=""></span></span>				
				<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblInternal" style='white-space: nowrap;width:100%;'>
				</table>
				</div>
				<div class="col-md-6">										
					<span style="font-family: monospace; font-size: 20;float:right;">					
					Public site projects: <span id="tblPublicCountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="tblPublicCountTotal" style="text-align:left;" text=""></span></span>				
				<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblPublic" style='white-space: nowrap;width:100%;'>
				</table>
			</div>
</div>


<div id="sync_modal" class="modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Sync action</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to <span id="action" style="color:red">New message</span> project <span id="project_name" style="color:red">New message</span> to public site? This might take several hours. You will get an email when it is finished.</p>
      </div>
      <div class="modal-footer">
        <button id="button_yes" type="button" class="btn btn-primary">Yes</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">No</button>
      </div>
    </div>
  </div>
</div>

@stop
