@section('title', "ChIPseq--$cohort_id")
{!! HTML::style('css/bootstrap.min.css') !!}
{!! HTML::style('css/style.css') !!}
{!! HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-core-css.css') !!}
{!! HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-blue/sm-blue.css') !!}    
{!! HTML::script('js/jquery-3.6.0.min.js') !!}
{!! HTML::script('packages/smartmenus-1.0.0-beta1/jquery.smartmenus.min.js') !!}
{!! HTML::style('packages/jquery-easyui/themes/bootstrap/easyui.css') !!}

{!! HTML::style('css/style_datatable.css') !!}
{!! HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') !!}
{!! HTML::style('packages/fancyBox/source/jquery.fancybox.css') !!}
{!! HTML::style('packages/w2ui/w2ui-1.4.min.css') !!}
{!! HTML::style('css/light-bootstrap-dashboard.css') !!}
{!! HTML::style('css/filter.css') !!}
{!! HTML::style('packages/bootstrap-switch-master/dist/css/bootstrap3/bootstrap-switch.min.css')!!}

{!! HTML::script('packages/DataTables/datatables.min.js') !!}
{!! HTML::script('js/bootstrap.bundle.min.js') !!}
{!! HTML::script('packages/jquery-easyui/jquery.easyui.min.js') !!}
{!! HTML::script('js/filter.js') !!}
{!! HTML::script('js/onco.js') !!}


<script type="text/javascript">
	
	var targets = [];
	var tbls = [];
	@foreach ($targets as $target)
		targets.push('{!!$target!!}');
	@endforeach
	$(document).ready(function() {		
		showChIPSeqTable();

		$('#btnDownloadChIPseq').on('click', function() {
    		var url = '{!!url("/get${cohort_type}ChIPseq/$cohort_id")!!}' + '/text';
    		@if ($cohort_type == "Case")
    			url = '{!!url("/getProjectChIPseq/$cohort_id")!!}' + '/text/' + '{!!$patient_id!!}' + '/'  + '{!!$case_id!!}';
    		@endif
			console.log(url);
			window.location.replace(url);	
		});

		$('.downloadMatrix').on('click', function() {
			var id = this.id;
			var target = id.replace("btnDownload","") 
			var url = '{!!url("/getChIPSeqMatrix/$cohort_id")!!}' + "/" + target + '/text';
    		console.log(url);
    		window.location.replace(url);	
    		return;
		});

		$('.easyui-tabs').tabs({
			onSelect:function(title) {
				if (title == "Matrix") {
					var target = targets[0]
					console.log("showMatrixTable(" + target + ")");
					showMatrixTable(target);
				} else {
					if (targets.includes(title)) {
						console.log("showMatrixTable(" + title + ")");
						showMatrixTable(title);
					}
				}	
		   }
		});
	});

	function showMatrixTable(target) {
		$("#loadingMatrix" + target).css("display","block");
		var url = '{!!url("/getChIPSeqMatrix/$cohort_id")!!}' + "/" + target;
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
			$("#loadingMatrix" + target).css("display","none");
			data = JSON.parse(data);
			if (data.status == "no data") {
				alert("No data!");
				return;
			}

			tbls[target] = $('#tblMatrix' + target).DataTable( 
					{				
						"paging":   true,
						"ordering": true,
						"info":     true,
						"dom": 'lfrtip',
						"data": data.data,
						"columns": data.cols,
						"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
						"pageLength":  15,
						"pagingType":  "simple_numbers",
															
					} );
			$('#lbl' + target + 'CountDisplay').text(tbls[target].page.info().recordsDisplay);
    		$('#lbl' + target + 'CountTotal').text(tbls[target].page.info().recordsTotal);
			}
		});
	}

	function showChIPSeqTable() {
		$("#loadingChIPseq").css("display","block");
		var url = '{!!$url!!}';
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
			$("#loadingChIPseq").css("display","none");
			data = JSON.parse(data);
			if (data.status == "no data") {
				alert("No ChIPseq data!");
				return;
			}

			tblChIPseq = $('#tblChIPseq').DataTable( 
					{				
						"paging":   true,
						"ordering": true,
						"info":     true,
						"dom": 'lfrtip',
						"data": data.data,
						"columns": data.cols,
						"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
						"pageLength":  15,
						"pagingType":  "simple_numbers",
															
					} );
			$('#lblChIPseqCountDisplay').text(tblChIPseq.page.info().recordsDisplay);
    		$('#lblChIPseqCountTotal').text(tblChIPseq.page.info().recordsTotal);
			}
		});
	}

</script>

<div id="tabChipseqs" class="easyui-tabs" data-options="tabPosition:'top',plain:true,pill:false" style="width:98%;padding:0px;overflow:visible;border-width:0px">
	            <div id="ChIPseqSummary" title="Summary" style="padding:5px">
					<div id='loadingChIPseq' class='loading_img'>
						<img src='{!!url('/images/ajax-loader.gif')!!}'></img>
					</div>
					<button id="btnDownloadChIPseq" class="btn btn-info"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Download</button>
					<!--button id="btnChIPseqIGV" class="btn btn-info"><img width=15 height=15 src={!!url("images/igv.jpg")!!}></img>&nbsp;IGV</button-->
					<span style="font-family: monospace; font-size: 20;float:right;">					
					ChIPSeq: <span id="lblChIPseqCountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="lblChIPseqCountTotal" style="text-align:left;" text=""></span>
					</span>
					<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblChIPseq" style='width:100%'>
					</table>
				</div>
				@if (count($targets) > 0)
					<div id="Matrix" title="Matrix" style="padding:5px">
						<div id="tabTargets" class="easyui-tabs" data-options="tabPosition:'top',plain:true,pill:false" style="width:98%;padding:0px;overflow:visible;border-width:0px">
							@foreach ($targets as $target)
								<div id="Target_{!!$target!!}" title="{!!$target!!}" style="padding:5px">
									<div id='loadingMatrix{!!$target!!}' class='loading_img'>	
										<img src='{!!url('/images/ajax-loader.gif')!!}'></img>
									</div>
									<button id="btnDownload{!!$target!!}" class="btn btn-info downloadMatrix"><img width=15 height=15 src={!!url("images/download.svg")!!}></img>&nbsp;Download</button>
									<span style="font-family: monospace; font-size: 20;float:right;">					
										Peaks: <span id="lbl{!!$target!!}CountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="lbl{!!$target!!}CountTotal" style="text-align:left;" text=""></span>
									</span>
										
									<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblMatrix{!!$target!!}" style='width:100%'>
									</table>
								</div>
							@endforeach
						</div>							
					</div>
				@endif
				<div id="ChIPseqIGV" title="IGV" style="padding:5px">
					<object data="{!!$igv_url!!}" type="text/html" width="100%" height="100%"></object>
				</div>
</div>

