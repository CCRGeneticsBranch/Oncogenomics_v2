{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') }}
{{ HTML::style('packages/jquery-easyui/themes/bootstrap/easyui.css') }}
{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::style('css/light-bootstrap-dashboard.css') }}

{{ HTML::script('js/jquery-3.6.0.min.js') }}
{{ HTML::script('packages/DataTables-1.10.8/media/js/jquery.dataTables.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/dataTables.buttons.min.js') }}

<script type="text/javascript">	
	var tblTIL;
	$(document).ready(function() {
		var root_url = '{{url("/")}}';
		var url = root_url + '/getTIL/' + '{{$project_id}}';
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				$("#loadingTIL").css("display","none");
				$("#TIL_panel").css("visibility","visible");
				tcell_json_data = JSON.parse(data);
				tcell_json_data.data.forEach(function(d){
					d[1] = '<a target=_blank href="' + root_url + "/viewPatient/" + {{$project_id}} + '/' + d[1] + '"">' + d[1] + '</a>';
						if (d[5] < 0.0001)
							d[5] = 0;
				});
				tcell_json_data.cols.forEach(function(d, i){
						if (d.title == "Fraction") {
							d.title = "TCellExTRECT fraction";							
						}
						d[1] = '<a target=_blank href="' + root_url + "/viewPatient/" + {{$project_id}} + '/' + d[1] + '"">' + d[1] + '</a>';
				});
					
				tblTIL=$('#tblTIL').DataTable( {				
						"paging":   true,
						"ordering": true,
						"info":     true,
						"dom": 'lfrtip',
						"data": tcell_json_data.data,
						"columns": tcell_json_data.cols,
						"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
						"pageLength":  25,
						"pagingType":  "simple_numbers",									
				} );
				tblTIL.draw();				
			}
		});

		$('.num_filter').numberbox({onChange : function () {
			if (tblTIL)
				tblTIL.draw();
		}});

		$.fn.dataTableExt.afnFiltering.push( function( oSettings, aData, iDataIndex ) { 
			var frag_min = $('#fraction_min').numberbox("getValue");
			if (frag_min == 0)
				return true;
			if (aData[5] == "NA")
				return false;
			return (aData[5] >= frag_min);
		});


		$('#tblTIL').on( 'draw.dt', function () {
			if (tblTIL) {
				$('#lblCountDisplay').text(tblTIL.page.info().recordsDisplay);
    			$('#lblCountTotal').text(tblTIL.page.info().recordsTotal);
    		}
    	});		
	});

</script>

<html>	
	<body>		
		<div id='loadingTIL' style="min-height: 70%;height:100%">
		    <img src='{{url('/images/ajax-loader.gif')}}'></img>
		</div>
		<div id='TIL_panel' sytle="visibility:hidden;">
			<H5 style="display: inline;">Minimum TCellExTRECT fraction: </H5><input id="fraction_min" class="easyui-numberbox num_filter" data-options="min:0,max:1,precision:3,value:0" style="width:50px;height:26px">
			<span style="font-family: monospace; font-size: 20;float:right;margin:0px;">
					<span id="lblCountDisplay" style="text-align:left;color:red;" text="0"></span>/<span id="lblCountTotal" style="text-align:left;" text="0"></span>
			</span>
			<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblTIL" style='width:100%'>
			</table>
		</div>
	</body>
</html>
			