@extends(($has_header)? 'layouts.default' : 'layouts.noheader')
@section('content')
{{ HTML::style('packages/w2ui/w2ui-1.4.min.css') }}
{{ HTML::style('css/style.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-core-css.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-blue/sm-blue.css') }}    
{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::script('js/jquery-3.6.0.min.js') }}
{{ HTML::script('packages/smartmenus-1.0.0-beta1/jquery.smartmenus.min.js') }}


{{ HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') }}
{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('packages/DataTables-1.10.8/media/css/jquery.dataTables.min.css') }}
{{ HTML::style('packages/DataTables-1.10.8/extensions/FixedColumns/css/fixedColumns.dataTables.min.css') }}

{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('packages/DataTables-1.10.8/media/js/jquery.dataTables.min.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/dataTables.buttons.min.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.flash.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.html5.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.print.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.colVis.js') }}
{{ HTML::script('packages/DataTables-1.10.8/extensions/FixedColumns/js/dataTables.fixedColumns.min.js') }}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
<style>
thead th {
    vertical-align: top;
    -moz-transform: rotate(270deg);
    writing-mode:tb-rl;
}

th, td { white-space: nowrap; padding: 0px;}
div.dataTables_wrapper {
	margin: 0 auto;
}

div.DTFC_LeftBodyLiner {
	width: 150px;
}

</style>    
<script type="text/javascript">
	var tblOnco = null;
	
	$(document).ready(function() {
		filterColumn('{{$search_text}}');
	});
	
	function filterColumn(search_text) {
		$("#loadingTable").css("display","block");
		var url = '{{url('/getGenotyping/')}}' + '/' + search_text + '/' + '{{$type}}' + '/' + '{{$source}}';
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				data = JSON.parse(data);
				$("#loadingTable").css("display","none");				
				if (data.cols.length == 1) {
					$("#message").css("display","block");
					return;
				}
				if (tblOnco != null)
					tblOnco.destroy();
				$('#tblOnco').empty();				
				tblOnco = $('#tblOnco').DataTable( 
					{				
						"paging":   true,
						"lengthMenu": [[15, 25, 50], [15, 25, 50]],
						"pageLength":  15,
						"pagingType":  "simple_numbers",
						"ordering": true,
						"order":[[1, "Desc"]],
						"deferRender": true,
						"info":     false,
						"data": data.data,
						"columns": data.cols,						
						scrollY:        700,
						scrollX:        true,
						scrollCollapse: true,
						fixedColumns:   true,
						"dom": '<"toolbar">lfrtip',
						/*
						"columnDefs": [{
                							"render": function ( data, type, row ) {
                								
                											if (isNaN(data))
                												return data;
                											else {
                												color_value = getColor(data);
                    											return $("<div></div>", {"class": "bar-chart-cell"}).append(function () {
                    													var bars = [];
                    													bars.push($("<div></div>",{"class": "bar"}).text(Math.round((data * 100)) + '%').css({"width": (data * 100) + '%', "background-color" : color_value}));
                    													return bars;
                    											}).prop("outerHTML");
                    										}
                    							

                										},
                							"targets": '_all'
            							}            							
        							]						
        				*/
					} );

				$('.DTFC_LeftBodyLiner').css('overflow-y', 'hidden');
				@if ($source == "all")			
				$("div.toolbar").html("<B>Search Patient ID (User \",\" if multiple patients): </B><input id='search_text' type='text' value='" + search_text + "'/>");

				$('#search_text').on('keyup', function(e) {
					if (e.keyCode == 13)
						filterColumn($('#search_text').val());
				});
				@endif

			}
		});

		function getColor(value){
			var hue=((1-value)*120).toString(10);
			return ["hsl(",hue,",100%,50%)"].join("");
		}

	}
</script>
<div style="padding:10px">
	<div id='message' style='display: none'>
	    <H4>No data</H4>
	</div>
	<div id='loadingTable'>
	    <img src='{{url('/images/ajax-loader.gif')}}'></img>
	</div>
	<table id="tblOnco" cellpadding="0" cellspacing="0" border="0" class="order-column pretty" word-wrap="break-word" style='width:100%'></table>
</div>
@stop
