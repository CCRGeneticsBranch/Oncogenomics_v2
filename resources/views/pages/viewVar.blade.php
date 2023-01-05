@extends('layouts.default')
@section('content')

{{ HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') }}
{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') }}
{{ HTML::style('packages/jquery-easyui/themes/metro/easyui.css') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}
{{ HTML::style('packages/muts-needle-plot/build/muts-needle-plot.min.css') }}
{{ HTML::style('css/heatmap.css') }}

{{ HTML::script('packages/DataTables-1.10.8/media/js/jquery.dataTables.min.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/dataTables.buttons.min.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.flash.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.html5.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.print.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.colVis.js') }}
{{ HTML::script('packages/DataTables-1.10.8/extensions/ColReorder/js/dataTables.colReorder.min.js') }}
{{ HTML::script('packages/DataTables-1.10.8/extensions/FixedColumns/js/dataTables.fixedColumns.min.js') }}
{{ HTML::script('packages/yadcf-0.8.8/jquery.dataTables.yadcf.js')}}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('js/togglebutton.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') }}
{{ HTML::script('packages/muts-needle-plot/build/muts-needle-plot.min.js') }}
{{ HTML::script('packages/muts-needle-plot/src/js/dependencies/d3.js') }}
{{ HTML::script('packages/muts-needle-plot/src/js/d3-svg-legend.js') }}
{{ HTML::script('packages/muts-needle-plot/src/js/dependencies/underscore.js') }}


<style>
div.toolbar {
	display:inline;
}

button.btnToggle {
	font-size: 1em;
	padding: 1em;
	border-radius: .3em;
	border: none;
	background-color: #888;
	color: #fff;
	outline: none;
}

button.active {
	background-color: #e74c3c;
}



</style>
    
<script type="text/javascript">
	var tbl;
	var tblDetail;
	var hide_cols = [6,8,10,11,12,13,14];
	var col_html = '';
	var columns = [];

	var patients = {{json_encode($patients)}};
	var col_groups = {{json_encode($col_groups)}};
	var current_diag = "{{$current_diag}}";
	var current_patient = "{{$current_patient}}";
	var expanded = false;
	//var hotspot_gene_desc = "{{$hotspot_gene_desc}}";
	//var hotspot_desc = "{{$hotspot_desc}}";

	$(document).ready(function() {

		$.ajax({ url: '{{url("/getVarAnnotationData/$sid/$patient_id/$gene_id")}}', async: true, dataType: 'text', success: function(json_data) {
				$("#loadingMaster").css("display","none");				
				data = JSON.parse(json_data);
				showTable(data);
				$('.filter').on('change', function() {
					doFilter();
		        	});
				
			}
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


		$('body').on('change', 'input#data_column', function() {             
			tbl.column($(this).attr("value")).visible($(this).is(":checked"));			
		});

		$('#selDiagnosis').on('change', function() {
			addDiagPatients(this.value);

        	});						

		$('#selPatients').on('change', function() {
        	});

		$('.getDetail').on('change', function() {
			alert('HA');
        	});

		$('#btnGene').on('click', function() {
			window.location.replace("{{url('/viewVarAnnotationByGene')}}" + "/" + $('#gene_id').val());
        	});

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
			window.location.replace("{{url('/viewVarAnnotationByLocus')}}" + "/" + $('#selChr').val() + "/" + $('#start_pos').val() + "/" + $('#end_pos').val());
        	});

		$('#btnPatient').on('click', function() {
			window.location.replace("{{url('/viewVarAnnotation')}}" + "/" + $('#selPatients').val() + "/null");
        	});

		$('#gene_id').keyup(function(e){
			if(e.keyCode == 13) {
        			$('#btnGene').trigger("click");
    			}
		});		

		for (diag_key in patients) {
			diag_patients = patients[diag_key];
			$('#selDiagnosis').append($('<option>', {
				value: diag_key,
				text: diag_key
			}));
			addDiagPatients(current_diag);		
		}
		$("#selDiagnosis").val(current_diag);
		$("#selPatients").val(current_patient);

		$(".btnToggle").toggleButton({radio: false})
			.on("toggle.change", function(e, active){
			for (i in col_groups[$(this).text()]) {
				var col_idx = col_groups[$(this).text()][i];
				tbl.column(col_idx).visible(active);
			}
		});

		tblDetail = $('#tblDetail').DataTable( 
		{
			"processing": true,
			"paging":   false,
			"ordering": false,
			"info":     false,
			"columns": [{"title":"Key"},{"title":"Value"}],
			"language" : {
				"processing": "<img src='{{url('/images/ajax-loader.gif')}}'></img>"
			}			
		} );

		$('.fancybox').fancybox();

		$(".option-heading").click(function(){
			$(this).find(".arrow-up, .arrow-down").toggle();
		});

		$(".collapse").on('shown.bs.collapse', function(){
        		$('#tabDetails').tabs('select', 2);
		});


	});

	function addDiagPatients(diag) {
		$('#selPatients').empty();
		diag_patients = patients[diag];
		for (patient_key in diag_patients) {
			$('#selPatients').append($('<option>', {
				value: patient_key,
				//text: patient_key + ' (' + diag_patients[patient_key] + ' rows)'
				text: patient_key
			}));							
		}
	}


	function launchIGV(bam) {
		url = 'http://localhost:60151/load?file=' + bam;
		$.ajax({ url: url, async: false, dataType: 'text', success: function(data) {				
			}, error: function(data) {
				url = 'http://www.broadinstitute.org/igv/projects/current/igv.php?sessionURL=' + bam;
				window.location.href = url;
			}
		});
	}

	function getDetails(type, chr, start_pos, end_pos, ref_base, alt_base, sample_id) {
		$("#loadingDetail").css("display","block");
		$("#table_area").css("display","none");
		//if (!expanded) {
			$("#var_layout").layout('expand','east');
			expanded = true;
		//}
		$.ajax({ url: '{{url('/getVarDetails')}}' + '/' + type + '/' + chr + '/' + start_pos + '/' + end_pos + '/' + ref_base + '/' + alt_base + '/' + sample_id, async: true, dataType: 'text', success: function(data) {
				data = JSON.parse(data);
				tblDetail.destroy();
				$('#tblDetail').empty();
				tblDetail = $('#tblDetail').DataTable( 
					{				
						"processing": true,
						"paging":   false,
						"ordering": false,
						"info":     false,
						"data": data.data,
						"columns": data.columns,									
					} );
				$("#loadingDetail").css("display","none");
				$("#table_area").css("display","block");

			}
		});
 
		//tblDetail.ajax.url(url).load();
	}

	function doFilter() {
		tbl.column(6).search('');
		tbl.column(10).search('');
		tbl.column(11).search('');
		tbl.column(12).search('');
		tbl.column(13).search('');
		if ($('#ckHotspots').is(":checked"))
			tbl.column(10).search('Y');
		if ($('#ckHotspotGenes').is(":checked"))
			tbl.column(11).search('Y');
		if ($('#ckPanel').is(":checked"))
			tbl.column(12).search('Y');
		if ($('#ckExome').is(":checked"))
			tbl.column(13).search('Y');
		if ($('#ckExonic').is(":checked"))
			tbl.column(6).search('exonic');
		tbl.draw();		
	}


	function showTable(data) {
		
		tbl = $('#tblOnco').DataTable( 
		{
			"data": data.data,
			"columns": data.cols,
			"ordering":    true,
			"deferRender": true,
			"lengthMenu": [[15, 25, 50], [15, 25, 50]],
			"pageLength":  15,
			"pagingType":  "simple_numbers",			
			"dom": '<"toolbar">lfrtip',
			"buttons": ['csv', 'colvis']
		} );		
		
		
		tbl.columns().iterator('column', function ( context, index ) {
			
			tbl.column(index).visible(true);
		} );

$("div.toolbar").html('<button id="popover" data-toggle="popover" title="Select Column" data-placement="bottom" type="button" class="btn btn-default" style="font-size: 12px;">Select Columns</button>&nbsp;&nbsp;<input type=checkbox class="filter" id="ckHotspots">&nbsp;Hotspots</input>&nbsp;<a href="#hotspot_desc" title="Hotspot sites" class="fancybox"><img src={{url("images/help.png")}}></img></a>&nbsp;&nbsp;<input type=checkbox class="filter" id="ckHotspotGenes">&nbsp;Hotspot genes</input>&nbsp;<a href="#hotspot_gene_desc" title="Hotspot genes" class="fancybox"><img src={{url("images/help.png")}}></img></a>&nbsp;&nbsp;<input class="filter" type=checkbox id="ckPanel">&nbsp;Panel</input>&nbsp;<a href="#hotspot_gene_desc" title="Hotspot genes" class="fancybox"><img src={{url("images/help.png")}}></img></a>&nbsp;&nbsp;<input class="filter" type=checkbox id="ckExome">&nbsp;Exome</input>&nbsp;<a href="#hotspot_gene_desc" title="Hotspot genes" class="fancybox"><img src={{url("images/help.png")}}></img></a>&nbsp;&nbsp;<input class="filter" type=checkbox id="ckExonic">&nbsp;Exonic</input>');
		col_html = '';

		tbl.columns().iterator('column', function ( context, index ) {
			var show = (hide_cols.indexOf(index) == -1);
			tbl.column(index).visible(show);
			checked = (show)? 'checked' : '';
			columns.push(tbl.column(index).header().innerHTML);
			col_html += '<input type=checkbox ' + checked + ' class="onco_checkbox" id="data_column" value=' + index + '><font size=3>&nbsp;' + tbl.column(index).header().innerHTML + '</font></input><BR>';
		} );

		
		/*
		yadcf.init(tbl, [
			{column_number : 5},
			{column_number : 10},
			
		]);*/
        
		$('[data-toggle="popover"]').popover({
			placement : 'bottom',  
			html : true,
			content : function() {
				return col_html;
			}
		}); 
	}

	function isInt(value) {
		var x;
		if (isNaN(value)) {
			return false;
		}
		x = parseFloat(value);
		return (x | 0) === x;
	}
</script>

<div style=display:none;>
<div id="hotspot_gene_desc" style="width:800px;height=600px">
{{$hotspot_gene_desc}}
</div>

<div id="hotspot_desc" style="display:none;width:800px;height=600px">
{{$hotspot_desc}}
</div>
</div>

{{$current_setting}}



<button type="button" class="btn btn-link" data-toggle="collapse" data-target="#search">&#9658;Search</button>
<div id="search" class="collapse">
	<div id="out_container" class="easyui-panel" style="width:1200px;height:100%;padding:10px;border:0">
	<div id="tabDetails" class="easyui-tabs" data-options="tabPosition:top" style="width:80%;height:220px;padding:10px">               
		<div title="By gene (cross patients)" style="width:100%;height:200px;padding:10px">
			<table>
				<tr>
					<td style="padding-top: 10px; padding-bottom:10px">
						Gene:&nbsp;&nbsp;<input type="text" id="gene_id" @if ($gene_id != 'null')value="{{$gene_id}}@endif"/>&nbsp;&nbsp;<button id='btnGene' >GO</button>
				</td></tr>								
			</table>
	     	</div>
		<div title="By locus" style="width:80%;height:200px;padding:10px">
			<table>
				<tr>
					<td style="padding-top: 10px; padding-bottom:10px">
						Chromosome:</td><td><select id="selChr">
						@for ($i = 1; $i <= 22; $i++)
							<option value="chr{{$i}}">chr{{$i}}</option>
						@endfor
						<option value="chrX">chrX</option>
						</select>
					</td>
					<td style="padding-top: 10px; padding-bottom:10px">
						&nbsp;&nbsp;Position: </td><td> <input type="text" id="start_pos"/>
					</td>
					<td style="padding-top: 10px; padding-bottom:10px">
						- </td><td> <input type="text" id="end_pos"/>&nbsp;&nbsp;<button id='btnLocus' >GO</button>
					</td></tr>
			</table>
	     	</div>
	     	<div title="By patients" style="width:80%;height:200px;padding:10px">
			<table>
				<tr>
					<td style="padding-top: 10px; padding-bottom:10px">
						Diagnosis:</td><td> <select id="selDiagnosis"></select>
					</td></tr>
				<tr>
					<td style="padding-top: 10px; padding-bottom:10px">
						Patients: </td><td><select id="selPatients" ></select>&nbsp;&nbsp;<button id='btnPatient' >GO</button>
					</td></tr>	
			</table>
		</div>
	</div>
	
	</div>

</div>


	


<div class="easyui-panel" style="height:100%;padding:0px;">
	<div id="var_layout" class="easyui-layout" data-options="fit:true">
		<div data-options="region:'center',split:true" style="width:80%;padding:10px;overflow:none;" title="">
		     <div class="easyui-layout" data-options="fit:true">
			@if ($gene_id != 'null')
				<div class="easyui-panel" data-options="region:'north',split:true" style="height:320px;width:100%;padding:10px;overflow:none;" title="Plot">
					<table>
					<tr><td><iframe frameborder=0 width=600 height=240 src="{{url('/viewMutationPlot/null/'.$gene_id.'/sample')}}"></iframe>
					</td><td><iframe frameborder=0 width=600 height=240 src="{{url('/viewMutationPlot/null/'.$gene_id.'/ref')}}"></iframe>
					</td></tr>
					</table>							
				</div>		
			@endif
			<div class="easyui-panel" data-options="region:'center',split:true" style="height:100%;padding:10px;overflow:auto;" title="">
				<div id='loadingMaster'><img src='{{url('/images/ajax-loader.gif')}}'></img></div>
				<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblOnco" style='width:100%'>
				</table>
    			</div>
		    </div>
		</div>
		<div id="panelDetail" class="easyui-panel" data-options="region:'east',split:true,collapsed:true" style="width:20%;padding:10px;overflow:auto;" title="Detail">
			<div id='loadingDetail'>
				<img src='{{url('/images/ajax-loader.gif')}}'></img>
			</div>
			<div id='table_area'>
				<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblDetail" style='width:100%'>
				</table>
			</table>
		</div>
		
	</div>
</div>

<HR>


  
@stop
