{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::style('css/style.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-core-css.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-blue/sm-blue.css') }}    
{{ HTML::script('js/jquery-3.6.0.min.js') }}
{{ HTML::script('packages/smartmenus-1.0.0-beta1/jquery.smartmenus.min.js') }}

{{ HTML::style('packages/DataTables-1.10.8/media/css/jquery.dataTables.min.css') }}
{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') }}
{{ HTML::style('packages/jquery-easyui/themes/bootstrap/easyui.css') }}
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}
{{ HTML::style('packages/w2ui/w2ui-1.4.min.css') }}
{{ HTML::style('css/light-bootstrap-dashboard.css') }}
{{ HTML::style('css/filter.css') }}
{{ HTML::style('packages/bootstrap-switch-master/dist/css/bootstrap3/bootstrap-switch.min.css')}}

{{ HTML::script('packages/DataTables-1.10.8/media/js/jquery.dataTables.min.js') }}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') }}
{{ HTML::script('js/filter.js') }}
{{ HTML::script('js/onco.js') }}
{{ HTML::script('packages/w2ui/w2ui-1.4.min.js')}}
{{ HTML::script('packages/highchart/js/highcharts.js')}}
{{ HTML::script('packages/highchart/js/highcharts-more.js')}}
{{ HTML::script('packages/bootstrap-switch-master/dist/js/bootstrap-switch.min.js') }}

<style>

.block_details {
    display:none;
    width:90%;
    height:130px;    
	border-radius: 10px;
	border: 2px solid #73AD21;
	padding: 10px; 
	margin: 10px; 
	overflow: auto; 
}

.toolbar {
	display:inline;
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
    background:url('{{url('/images/close-button.png')}}') no-repeat center center;  
}

.btn-default:focus,
.btn-default:active,
.btn-default.active {
    background-color: DarkCyan;
    border-color: #000000;
    color: #fff;
}
.btn-default.active:hover {
    background-color: #005858;
    border-color: gray;
    color: #fff;    
}

</style>

<script type="text/javascript">
	
	var hide_cols = {"tblExp" : []};
	var tbls = [];
	var column_tbls = [];
	var col_html = [];
	var filter_list = {'Select filter' : -1}; 
	var filter_gene_list = {{$filter_gene_list}};
	var onco_filter;
	var type_idx = 2;
	var user_list_idx = 0;
	var gene_idx = 0;
	var target_type = null;
	var patients;
	
	$(document).ready(function() {		
		var url = '{{url('/getExpressionMatrix')}}' + '/' + '{{$patient_id}}' + '/' + '{{$case_id}}';
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				$("#loading").css("display","none");	
				$("#tableArea").css("display","block");
				//console.log(data);
				data = JSON.parse(data);				
				if (data.cols.length == 1) {
					return;
				}
				user_list_idx = data.user_list_idx;
				target_type = data.target_type;
				$('#exp_type').text(data.expression_type); 
				$('#sum_type').text(data.count_type);    		
				type_idx = data.type_idx;
				/*
				for (var i=user_list_idx;i<data.cols.length;i++) {
					filter_list[data.cols[i].title] = i;
					hide_cols.tblExp.push(i);
				}
				*/

				showTable(data, 'tblExp');				
				onco_filter = new OncoFilter(Object.keys(filter_gene_list), null, function() {doFilter();});	
				doFilter();
			}			
		});

		@if (count($de_files)>0)
			de_summary = {{$de_summary}};
			showTable(de_summary, 'tblDESummary');
		@endif
		@foreach ($gsea_htmls as $gsea_type => $groups)
			showGSEAReport('{{$gsea_type}}','{{$gsea_htmls[$gsea_type][0]}}');			
		@endforeach

		$('#fb_tier_definition').fancybox({ 
			width  : 1200,
    		height : 800,
    		type   :'iframe'   		
		});

		$('#fb_filter_definition').fancybox({    		
		});

		$('#btnAddFilter').on('click', function() {						
			onco_filter.addFilter();			
        });

		$('#btnClearFilter').on('click', function() {
			showAll();		
		});

		$('.de_filter').on('change', function() {
			var tid = $(this).attr("id").replace("selLFC", "tblDE");
			tid = tid.replace("selAdjP", "tblDE");
			tbls[tid].draw();
		});

		$('.easyui-tabs').tabs({
			onSelect:function(title, idx) {				
				var tab = $(this).tabs('getSelected');
				var id = tab.panel('options').id;
				console.log("type: " + id.substring(0,4));
				var geneset = "NCI";
				if (id.substring(0,4) == "GSEA") {
					if (id != "GSEA")
						geneset = id.substring(4);
					tab = $("#tabGeneset" + geneset).tabs('getSelected');
				}
				var id = tab.panel('options').id;
				console.log("ID: " + id);
				if (id != null && id.substring(0,7) == "summary") {
					id = id.replace("summary","");					
					if (tbls["tbl"+ id] === undefined) {
						var url = '{{url('/getGSEASummary')}}' + '/' + '{{$patient_id}}' + '/' + '{{$case_id}}' + '/' + id;
						console.log(url);
						$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
								var data = JSON.parse(data);				
								if (data.cols.length == 1) {
									return;
								}
								showTable(data, 'tbl' + id);							
							}			
						});
					}
				}
				if (id != null && id.substring(0,3) == "DE_") {
					id = id.replace("DE_","");					
					if (tbls["tblDE"+ id] === undefined) {
						var url = '{{url('/getDEResults')}}' + '/' + '{{$patient_id}}' + '/' + '{{$case_id}}' + '/' + id;
						console.log(url);
						$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
								var data = JSON.parse(data);				
								if (data.cols.length == 1) {
									return;
								}
								$("#loading" + id).css("display","none");	
								$("#tableArea" + id).css("display","block");
								showTable(data, 'tblDE' + id);							
							}			
						});
					}
				}
		   }
		});		
	});

	function showGSEAReport(geneset, group) {
		var url = '{{url("/getGSEAReport/$patient_id/$case_id/")}}' + '/' + geneset + '/' + group + '/index.html';
		var html = '<iframe scrolling="auto" frameborder="0" frameborder="0" scrolling="no" src="' + url + '" style="width:100%;height:100%;min-height:800px;border-width:0px"></iframe>';
		$('#geneset' + geneset).html(html);		
	}

	function showTable(data, tblId) {	
		var tbl = $('#' + tblId).DataTable( 
		{
			"data": data.data,
			"columns": data.cols,
			"ordering":    true,
			"deferRender": true,
			"lengthMenu": [[15, 25, 50], [15, 25, 50]],
			"pageLength":  15,
			"pagingType":  "simple_numbers",			
			"dom": '<"toolbar">lfrtip',			
		} );

		tbls[tblId] = tbl;

		if (tblId != "tblExp")
			return;
		var columns =[];
		col_html[tblId] = '';
				
		var toolbar_html = '<button id="' + tblId + '_popover" data-toggle="popover" data-placement="bottom" type="button" class="btn btn-default" style="font-size: 12px;">Select Columns</button>';
		//toolbar_html += '<span style="float:right;"><label>Search:&nbsp;<input id="search_input" type="text"></input></label>&nbsp;<input id="ckExactMatch" type="checkbox"></input><label>Exact gene match</label><span>'; 
		$("div.toolbar").html(toolbar_html);
		tbl.columns().iterator('column', function ( context, index ) {
			var show = (hide_cols[tblId].indexOf(index) == -1);
			tbl.column(index).visible(show);
			columns.push(tbl.column(index).header().innerHTML);
			checked = (show)? 'checked' : '';
			//checked = 'checked';
			col_html[tblId] += '<input type=checkbox ' + checked + ' class="onco_checkbox data_column" id="data_column_' + tblId + '" value=' + index + '><font size=3>&nbsp;' + tbl.column(index).header().innerHTML + '</font></input><BR>';
		});
		column_tbls[tblId] = columns;
	    
	    $('.gsea_report').on('change', function() {
			var group = $(this).val();
			var geneset = $(this).attr('id')
			geneset = geneset.replace("sel", "");
			console.log(geneset + ',' + group);
			showGSEAReport(geneset, group);
			
		});

		$("#" + tblId + "_popover").popover({				
				title: 'Select column <a href="#inline" class="close" data-dismiss="alert">×</a>',
				placement : 'bottom',  
				html : true,
				content : function() {
					var tblId= $(this).attr("id").substring(0, $(this).attr("id").indexOf('_popover'));
					return col_html[tblId];
				}
		});

		$(document).on("click", ".popover .close" , function(){
				$(this).parents(".popover").popover('hide');
		});

		
		$('body').on('change', 'input.data_column', function() {             				
				var tblId = $(this).attr("id").substring($(this).attr("id").indexOf('data_column_') + 12);
				console.log(tblId);
				var tbl = tbls[tblId];
				var columns = column_tbls[tblId];
				col_html[tblId] = '';
				for (i = 0; i < columns.length; i++) { 
					if (i == $(this).attr("value"))
						checked = ($(this).is(":checked"))?'checked' : '';
					else
						checked = (tbl.column(i).visible())?'checked' : '';
					col_html[tblId] += '<input type=checkbox ' + checked + ' class="onco_checkbox data_column" id="data_column_' + tblId + '" value=' + i + '><font size=3>&nbsp;' + columns[i] + '</font></input><BR>';
				}
				tbl.column($(this).attr("value")).visible($(this).is(":checked"));
				
		});
		
		
		$('#tblExp').on( 'draw.dt', function () {
			$('#lblCountDisplay').text(tbl.page.info().recordsDisplay);
    		$('#lblCountTotal').text(tbl.page.info().recordsTotal);    		
    	});

		
    	$.fn.dataTableExt.afnFiltering.push( function( oSettings, aData, iDataIndex ) {	
    		if (oSettings.nTable.id.substring(0,5)=="tblDE") {
    			var de_id=oSettings.nTable.id.substring(5);
    			if ($('#selLFC' + de_id).val() != "all") {
    				if (!eval("aData[2]" + $('#selLFC' + de_id).val()))
    					return false;
    			}
				if ($('#selAdjP' + de_id).val() != "all") {
    				return (aData[5] < parseFloat($('#selAdjP' + de_id).val()));
    			}
    			return true;
    		}
    		
			if (oSettings.nTable == document.getElementById('tblExp')) {
				/*
				var cnt_cutoff = $('#cnt_cutoff').numberbox("getValue");
				var cnt_op = $('#cnt_op').val();
				var cnt_val = parseInt(aData[cnt_idx]);
				var allele_a_val = parseInt(aData[cnt_idx + 1]);
				var allele_b_val = parseInt(aData[cnt_idx + 2]);
				if (cnt_cutoff != NaN) {
					if (cnt_op == "larger" && cnt_val < cnt_cutoff)
						return false;
					if (cnt_op == "smaller" && cnt_val > cnt_cutoff)
						return false;
					if (cnt_op == "equal" && cnt_val != cnt_cutoff)
						return false;
				}
				*/
				/*
				if ($('#ckProteinCoding').is(":checked")) {
					if (aData[type_idx] != "protein-coding")
					return false;
				}
				*/
				if (onco_filter == null)
					return true;
				
				var outer_comp_list = [];
				filter_settings = [];
				var gene = aData[gene_idx];
				for (var filter in onco_filter.filters) {
					var comp_list = [];
					var filter_setting = [];				
					for (var i in onco_filter.filters[filter]) {
						var filter_item_setting = [];
						var filter_name = onco_filter.getFilterName(filter, i);
						currentEval =  (filter_gene_list[filter_name].hasOwnProperty(gene));
						/*
						var idx = filter_list[filter_name];
						filter_item_setting.push(filter_name);
						if (idx == -1)
							currentEval = true;
						else
							currentEval = (aData[idx] != '');
						*/
	        			if (onco_filter.hasFilterOperator(filter, i)) {
	        				var op = (onco_filter.getFilterOperator(filter, i))? "&&" : "||";
	        				filter_item_setting.push(op);
	        				comp_list.push(op);
	        			}
	        			filter_setting.push(filter_item_setting);
	        			comp_list.push(currentEval);
					}				
					outer_comp_list.push('(' + comp_list.join(' ') + ')');
					filter_settings.push(filter_setting);
				}

				if (outer_comp_list.length == 0)
					final_decision = true;
				else	
					final_decision = eval(outer_comp_list.join('||'));
	        	return final_decision;
			}
			return true;
		});		

		$('#cnt_cutoff').numberbox({onChange : function () {
				doFilter();
			}
		});

		$('#ckProteinCoding').on('change', function() {
			doFilter();
		});		

		$('#cnt_op').change(function() {
			doFilter();
		});
		
		$('#btnDownload').on('click', function() {
			var url = '{{url("/getCaseExpMatrixFile/$patient_id/$case_id")}}';
			console.log(url);
			window.location.replace(url);	
		});		
		
		//$('.mytooltip').tooltipster();

	}

	function showAll() {
		tbls['tblExp'].search('');
		//$('#cnt_op').val("larger");
		//$('#cnt_cutoff').numberbox("setValue", 0);
		$('#ckProteinCoding').prop('checked', false);
		$('#btnProteinCoding').removeClass("active");
		onco_filter.clearFilter();
	}

	function doFilter() {
		tbls['tblExp'].draw();
		//uploadSetting();
	}

	function doSearch() {
		return;
		var body = $( tbls['tblExp'].table().body() );
		var value = $('#search_input').val();
		if (value == "") {
			tbls['tblExp'].search('');
			tbls['tblExp'].draw();
			return;
		}
		body.unhighlight();
		if ($('#ckExactMatch').is(":checked")) {
			var pattern = '(\\s\\s' + value + '\,\|\,' + value + '\,\|\,' + value + '\\s\\s)';
			//console.log(pattern);
			body.highlight(tbls['tblCNV'].search(pattern, true));								
		}
		else
			body.highlight(tbls['tblCNV'].search(value));
		tbls['tblCNV'].draw();
	}

	function click_handler(p) {
		patient_id = patients[p.name];
		if (patient_id != null) {
			var url = '{{url("/viewPatient/$project_id")}}' + '/' + patient_id;
			console.log(url);
			window.open(url, '_blank');		    		
	    }
		
	}

	function showExp(d, gene_id, rnaseq_sample, target_type="ensembl") {
		//var url = '{{url("/getExpression/$project_id/")}}' + '/' + gene_id + '/' + target_type;
		//target_type="refseq";
		var url = '{{url("/getExpression/$project_id/")}}' + '/' + gene_id + '/' + target_type;
		console.log(JSON.stringify(url));
		console.log(rnaseq_sample);
		$('#plot_popup').w2popup();
		$("#w2ui-popup").css("top","20px");	
		$('#w2ui-popup #loading_plot').css('display', 'block');
		//$(d).w2overlay('<H4><div style="padding:30px">loading...<div></H4>');
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				var data = parseJSON(data);
				if (data.hasOwnProperty("patients")) {
					patients = data.patients;
				} else {
					$('#w2ui-popup #loading_plot').css('display', 'none');
					$('#w2ui-popup #no_data').css('display', 'block');
					return;
				}
				var rnaseq_sample_names = [rnaseq_sample];
				//console.log(JSON.stringify(data.exp_data));
				//console.log(JSON.stringify(data.samples));
				//console.log(JSON.stringify(rnaseq_sample_names));
				//return;
				var exp_val;
				if (target_type == 'refseq')
					exp_val = data.exp_data[gene_id].refseq;
				else
					exp_val = data.exp_data[gene_id].ensembl;					
				log2_exp_val = [];
				exp_val.forEach(function(v, i){
					log2_exp_val.push(Math.round(Math.log2(v+1) * 100)/100);
				});
				//console.log(JSON.stringify(exp_val));

				values = getSortedScatterValues(log2_exp_val, data.samples, rnaseq_sample_names);
				//console.log(JSON.stringify(exp_val));
				var sample_idx = 0;
				for (var i in data.samples) {
					for (var j in rnaseq_sample_names) {
						if (data.samples[i] == rnaseq_sample_names[j]) {
							sample_idx = i;
							break;
						}
					}
				}
				tpm = (sample_idx == 0)? "NA" : Math.round(log2_exp_val[sample_idx] * 100) / 100;
				//fpkm = Math.log2(fpkm+1);
				var title = gene_id + ', ' + rnaseq_sample_names[0] + ' <font color="red">(TPM: ' + tpm + ')</font>';
				//$(d).w2overlay('<div id="exp_plot" style="width:380px;height:260px"></div>', { css: { width: '400px', height: '250px', padding: '10px' } });				
				$('#w2ui-popup #loading_plot').css('display', 'none');
				drawScatterPlot('w2ui-popup #scatter_plot', title, values, 'Samples', 'log2(TPM+1)', click_handler);
			}							
		});
	}

	function showCNV(d, gene_id, sample_name) {
		var url = '{{url("/getProjectCNV/$project_id/")}}' + '/' + gene_id;
		console.log(JSON.stringify(url));
		//$(d).w2overlay('<H4><div style="padding:30px">loading...<div></H4>');		
		$('#plot_popup').w2popup();
		$('#w2ui-popup #loading_plot').css('display', 'block');
		$('#w2ui-popup #no_data').css('display', 'none');
		
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				var data = parseJSON(data);
				if (data.hasOwnProperty("patients")) {
					patients = data.patients;
				} else {
					$('#w2ui-popup #loading_plot').css('display', 'none');
					$('#w2ui-popup #no_data').css('display', 'block');
					return;
				}
				var sample_names = [sample_name];
				values = getSortedScatterValues(data.cnv_data[gene_id], data.samples, sample_names);
				var sample_idx = 0;
				for (var i in data.samples) {
					for (var j in sample_names) {
						if (data.samples[i] == sample_names[j]) {
							sample_idx = i;
							break;
						}
					}
				}				
				cnt = (sample_idx == 0)? "NA" : Math.round(data.cnv_data[gene_id][sample_idx] * 100) / 100;
				var title = gene_id + ', ' + sample_names[0] + ' <font color="red">(CN: ' + cnt + ')</font>';
				//$(d).w2overlay('<div id="exp_plot" style="width:380px;height:260px"></div>', { css: { width: '400px', height: '250px', padding: '10px' } });
				$('#w2ui-popup #loading_plot').css('display', 'none');
				drawScatterPlot('w2ui-popup #scatter_plot', title, values, 'Samples', 'Copy Number', click_handler);
			}							
		});
	}


</script>
<form style="display: hidden" action='{{url('/downloadCaseExpression')}}' method="POST" target="_blank" id="downloadHiddenform">
	<input type="hidden" id="patient_id" name="patient_id" value='{{$patient_id}}'/>
	<input type="hidden" id="case_id" name="case_id" value='{{$case_id}}'/>	
	<input type="hidden" id="gene_list" name="gene_list" value=""/>
</form>

<div id="plot_popup" style="display: none; width:680px;height:360px; overflow: auto; background-color=white;">	
	<div rel="body" style="text-align:left;padding:20px">
		<a href="javascript:w2popup.close();" class="boxclose"></a>
		<div id='loading_plot'><img src='{{url('/images/ajax-loader.gif')}}'></img></div>
		<h4 id="no_data" style="display: none;">No Data</h4>
		<div id="scatter_plot" style="width:580px;height:300px"></div>
	</div>
</div>

<div style="display:none;">	
	<div id="filter_definition" style="display:none;width:800px;height=600px">
		<H4>
		The definition of filters:<HR>
		</H4>
		<table>
			@foreach ($filter_definition as $filter_name=>$content)
			<tr valign="top"><td><font color="blue">{{$filter_name}}:</font></td><td>{{$content}}</td></tr>
			@endforeach
		</table>

	</div>
</div>
<div id="out_container" class="easyui-panel" data-options="border:false" style="width:100%;padding:0px;border-width:0px">	
	<div id="tabVar" class="easyui-tabs" data-options="tabPosition:'top',fit:true,plain:true,pill:false,border:true" style="width:100%;height:100%;padding:0px;border-width:0px">
		<div id="Matrix" title="Matrix" style="width:100%;padding:5px;">
			<div id='loading'><img src='{{url('/images/ajax-loader.gif')}}'></img></div>
			<div id='tableArea' style="background-color:#f2f2f2;width:100%;padding:5px;overflow:auto;display:none;text-align: left;font-size: 12px;">
				<div class="card">
					<span style="font-family: monospace; font-size: 20;float:right;">				
							&nbsp;&nbsp;Genes:&nbsp;<span id="lblCountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="lblCountTotal" style="text-align:left;" text=""></span>
					</span>
					<span id='filter' style='height:200px;width:80%'>
						<button id="btnAddFilter" class="btn btn-primary">Add filter</button>&nbsp;<a id="fb_filter_definition" href="#filter_definition" title="Filter definitions" class="fancybox mytooltip"><img src={{url("images/help.png")}}></img></a>&nbsp;						
					</span>
					<button id="btnClearFilter" type="button" class="btn btn-info" style="font-size: 12px;">Show all</button>		
					<span style="font-size: 14px;">			
						<!--span class="btn-group" data-toggle="buttons">
							<label id="btnProteinCoding" class="btn btn-info mytooltip" title="Show protein coding genes">
								<input class="ck" id="ckProteinCoding" type="checkbox" autocomplete="off" >Protein Coding Genes
							</label>				
						</span-->
						<button id="btnDownload" class="btn btn-info"><img width=15 height=15 src={{url("images/download.svg")}}></img>&nbsp;Download</button>
					</span>
					<span style="font-family: monospace; font-size: 14">				
							&nbsp;&nbsp;Format: &nbsp;Ensembl log2 (TPM + 1)&nbsp;
					</span>
				</div>
				<div style="height:5px"></div>	

				<div class="card">
					<table cellpadding="0" cellspacing="0" border="1" class="pretty" word-wrap="break-word" id="tblExp" style='width:100%;border: 1px solid black;'>
					</table> 
				</div>
			</div>
		</div>
		@if (count($files) > 0)
		<div id="QC" title="QC" style="width:100%;padding:5px;">
			<div id="tabQC" class="easyui-tabs" data-options="tabPosition:'top',fit:true,plain:true,pill:false,border:true" style="width:100%;height:100%;padding:0px;border-width:0px">
				@foreach ($files as $name => $file)
				<div id="{{$name}}" title="{{$name}}">
					<object data="{{url("/getAnalysisPlot/$patient_id/$case_id/expression/$file")}}" type="application/pdf" style="width:98%;height:800px"></object>
				</div>
				@endforeach
			</div>
		</div>
		@endif
		@if (count($de_files)>0)
		<div id="DE" title="DE" style="width:100%;padding:5px;">
			<div id="tabDE" class="easyui-tabs" data-options="tabPosition:'top',fit:true,plain:true,pill:false,border:true" style="width:100%;height:100%;padding:0px;border-width:0px">				
				<div title="Summary" style="padding:5px;">
					<H5>Cutoff: adj-pvalue < 0.05</H5>
					<div style="width:70%;padding:5px;">
						<table cellpadding="0" cellspacing="0" border="1" class="pretty" word-wrap="break-word" id="tblDESummary" style='width:100%;border: 1px solid black;'>
						</table>
					</div>
				</div>
				@foreach ($de_files as $de_file)
				<div id="DE_{{$de_file}}" title="{{$de_file}}">
					<div id="tabGeneset{{$gsea_type}}" class="easyui-tabs" data-options="tabPosition:'top',fit:true,plain:true,pill:false,border:true" style="width:100%;height:100%;padding:0px;border-width:0px">
						<div title="Table" id="Table{{$de_file}}">
							<div id='loading{{$de_file}}'><img src='{{url('/images/ajax-loader.gif')}}'></img></div>
							<div id='tableArea{{$de_file}}' style="display:none;width:80%;padding:5px;">
								Log2FoldChange: 
								<select class="form-control de_filter" id="selLFC{{$de_file}}" style="width:200px;display:inline">
										<option value="all">All</option>
										<option value="<-1">< -1</option>
										<option value="<0">< 0</option>
										<option value=">0">> 0</option>
										<option value=">1">> 1</option>
								</select>
								AdjPvalue: 
								<select class="form-control de_filter" id="selAdjP{{$de_file}}" style="width:200px;display:inline">
										<option value="all">All</option>
										<option value="0.001">0.001</option>
										<option value="0.01">0.01</option>
										<option value="0.05">0.05</option>
										<option value="0.1">0.1</option>
								</select>
								<table cellpadding="0" cellspacing="0" border="1" class="pretty" word-wrap="break-word" id="tblDE{{$de_file}}" style='width:100%;border: 1px solid black;'>
								</table>
							</div>
						</div>
						<div title="MA plot">
							<object data="{{url("/getAnalysisPlot/$patient_id/$case_id/expression/$de_file.MA.pdf")}}" type="application/pdf" style="width:98%;height:800px"></object>
						</div>
					</div>
				</div>
				@endforeach
			</div>
		</div>
		@endif
		@if (count($gsea_htmls)>0)
		<div id="GSEA" title="GSEA" style="width:100%;padding:5px;">
			<div id="tabGSEA" class="easyui-tabs" data-options="tabPosition:'top',fit:true,plain:true,pill:false,border:true" style="width:100%;height:100%;padding:0px;border-width:0px">				
				@foreach ($gsea_htmls as $gsea_type => $groups)
				<div id="GSEA{{$gsea_type}}" title="{{$gsea_type}}">
					<div id="tabGeneset{{$gsea_type}}" class="easyui-tabs" data-options="tabPosition:'top',fit:true,plain:true,pill:false,border:true" style="width:100%;height:100%;padding:0px;border-width:0px">
						<div title="Summary" id="summary{{$gsea_type}}">
							<table cellpadding="0" cellspacing="0" border="1" class="pretty" word-wrap="break-word" id="tbl{{$gsea_type}}" style='width:100%;border: 1px solid black;'>
							</table>
						</div>
						<div title="Report">
							<select id="sel{{$gsea_type}}" class="form-control gsea_report" style="width:350px">
							@foreach ($groups as $group)								
									<option value='{{$group}}'>{{$group}}</option>								
							@endforeach
							</select>
							<div id="geneset{{$gsea_type}}"></div>
						</div>
					</div>
				</div>
				@endforeach
			</div>
		</div>
		@endif
	</div>
</div>

