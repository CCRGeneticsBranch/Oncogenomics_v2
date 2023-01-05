{{ HTML::style('packages/w2ui/w2ui-1.4.min.css') }}
{{ HTML::style('css/style.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-core-css.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-blue/sm-blue.css') }}    
{{ HTML::script('js/jquery-3.6.0.min.js') }}
{{ HTML::script('packages/smartmenus-1.0.0-beta1/jquery.smartmenus.min.js') }}


{{ HTML::style('css/style.css') }}
{{ HTML::style('packages/jquery-easyui/themes/icon.css') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}
{{ HTML::style('packages/bootstrap-switch-master/dist/css/bootstrap3/bootstrap-switch.css') }}
{{ HTML::style('css/filter.css') }}
{{ HTML::style('packages/tooltipster-master/dist/css/tooltipster.bundle.min.css') }}
{{ HTML::style('packages/tooltipster-master/dist/css/tooltipster.bundle.min.css') }}
{{ HTML::style('css/font-awesome.min.css') }}
{{ HTML::script('packages/d3/d3.min.js') }}
{{ HTML::script('packages/d3/d3.tip.js') }}

{{ HTML::script('js/FileSaver.js') }}
{{ HTML::script('packages/gene_fusion/gene-fusion.1.0.js') }}

{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('js/togglebutton.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') }}
{{ HTML::script('packages/tooltipster-master/dist/js/tooltipster.bundle.min.js') }}
{{ HTML::script('packages/bootstrap-switch-master/dist/js/bootstrap-switch.js') }}
{{ HTML::script('packages/w2ui/w2ui-1.4.min.js')}}
{{ HTML::script('js/filter.js') }}
{{ HTML::script('js/onco.js') }}
{{ HTML::script('packages/highchart/js/highcharts.js')}}
{{ HTML::script('packages/highchart/js/highcharts-more.js')}}
{{ HTML::script('packages/highchart/js/modules/exporting.js')}}


{{ HTML::script('packages/DataTables-1.10.8/media/js/jquery.dataTables.min.js') }}

{{ HTML::script('packages/Buttons-1.0.0/js/dataTables.buttons.min.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.flash.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.html5.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.print.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.colVis.js') }}

{{ HTML::script('packages/DataTables-1.10.8/extensions/ColReorder/js/dataTables.colReorder.min.js') }}
{{ HTML::script('packages/DataTables-1.10.8/extensions/FixedColumns/js/dataTables.fixedColumns.min.js') }}
{{ HTML::script('packages/yadcf-0.8.8/jquery.dataTables.yadcf.js')}}

{{ HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') }}
{{ HTML::style('packages/DataTables-1.10.8/media/css/jquery.dataTables.min.css') }}
{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') }}


<style>

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
    height:130px;    
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

.toolbar {
	display:inline;
}

td.details-control {
	text-align: center;
    cursor: pointer;
}

tr.details td.details-control {
    background: '{{url('/images/details_close.png')}}' no-repeat center center;
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
    width:15px;
    height:15px;    
    background:url('{{url('/images/close-button.png')}}') no-repeat center center;  
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

	var patient_id = '{{$patient_id}}';
	var left_gene_idx = 4;
	var hide_cols = null;
	if (patient_id != 'null') {
		hide_cols = {"tblFusion" : [2,3,10,18,19,20,21]};
		//hide_cols.tblFusion.push(11);		
	}
	else {
		hide_cols = {"tblFusion" : [3,11,19,20,21,22]};
		left_gene_idx++;
	}
	patient_id_idx = left_gene_idx - 1;
	var options = [];
	var tbls = [];
	var column_tbls = [];
	var col_html = [];
	var value_range = {};	
	var left_chr_idx = left_gene_idx + 2;
	var right_chr_idx = left_gene_idx + 4;
	var tool_idx = left_gene_idx + 7;
	var type_idx = left_gene_idx + 8;	
	var tier_idx = left_gene_idx + 9;
	var user_list_idx = left_gene_idx + 10 + 8;
	@if ($has_qci)
		user_list_idx = user_list_idx + 3;
		var qci_actionability_idx = user_list_idx - 2;
	@endif
	var filter_settings = [];
	@if (property_exists($setting, "filters"))
		filter_settings = {{$setting->filters}};
	@endif
	var filter_list = {'Select filter' : -1}; 
	var onco_filter;
	var filtered_patients = [];
	var all_patients = [];	
	var type_list = {};
	var tool_list = {};
	var first_loading = true;
	var fusion_data = {};
	console.log('{{json_encode($setting)}}');


	$(document).ready(function() {	
			console.log('{{$url}}');
			$.ajax({ url: '{{$url}}', async: true, dataType: 'text', success: function(d) {
				$("#loadingFusion").css("display","none");	
				$("#tableAreaFusion").css("display","block");
				json_data = JSON.parse(d);
				cols = json_data.cols;
				cols[0] = {
                	"class":"details-control",
                	"title": "Details",
                	"orderable":      false,                
                	"defaultContent": ""
        		};
        		if (json_data.data.length == 0) {
        			$('#lblCountDisplay').text("0");
    				$('#lblCountTotal').text("0");
    				$('#lblCountPatients').text("0");
    				$('#lblCountTotalPatients').text("0");
        		}
				showTable(json_data, 'tblFusion');

				onco_filter = new OncoFilter(Object.keys(filter_list), filter_settings, function() {doFilter();});

				var detailRows = [];
				$('#tblFusion tbody').on( 'click', 'tr td.details-control', function () {
					var tbl = tbls['tblFusion'];
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
			            tbl.cell( this ).data("<img width=20 height=20 src='{{url('images/details_close.png')}}'></img>");
			            row.child( format( row.data(),idx ) ).show();
			 
			            // Add to the 'open' array
			            if ( idx === -1 ) {
			                detailRows.push( tr.attr('id') );
			            }
			        }
			    } );	

				tbls['tblFusion'].order([[2, "desc"]]);
				if (patient_id == 'null')
					showAll();
				applySetting();
				doFilter();
			}
		});
		
		$('#fb_tier_definition').fancybox({ 
			width  : 1200,
    		height : 800,
    		type   :'iframe'   		
		});

		$('#fb_filter_definition').fancybox({    		
		});

		$('#selTypes').on('change', function() {
			doFilter();
		});

		$('#selTools').on('change', function() {
			doFilter();
		});

		$('#ckInterChr').on('change', function() {
			doFilter();
		});

		$('#btnAddFilter').on('click', function() {						
			onco_filter.addFilter();			
        });

        $('#QCItiers').on('change', function() {
			doFilter();
		});

		$('.filter').on('change', function() {
			if (!$('#ckTier1').is(":checked") || !$('#ckTier2').is(":checked") || !$('#ckTier3').is(":checked") || !$('#ckTier4').is(":checked") || !$('#ckNoTier').is(":checked")) {
				$('#btnTierAll').removeClass('active');
				$('#ckTierAll').prop('checked', false);
			}
			doFilter();
		});

		$('#tiers').on('change', function() {
			if (!$('#ckTier1').is(":checked") || !$('#ckTier2').is(":checked") || !$('#ckTier3').is(":checked") || !$('#ckTier4').is(":checked") || !$('#ckNoTier').is(":checked")) {
				$('#btnTierAll').removeClass('active');
				$('#ckTierAll').prop('checked', false);
			}
			doFilter();
		});

		$('#tier_all').on('change', function() {	
	       	if ($('#ckTierAll').is(":checked")) {
	       		$('.tier_filter').addClass('active');
	       		$('.ckTier').prop('checked', true);		        		
	       	}
			doFilter();
        });

        
		$.fn.dataTableExt.afnFiltering.push( function( oSettings, aData, iDataIndex ) {	
			if (oSettings.nTable == document.getElementById('tblFusion')) {
				if (first_loading) {
					type_list[aData[type_idx]] = '';
					var tool_str = aData[tool_idx];
					var tools = tool_str.split(' ');
					tools.forEach(function(t) {
						tp = t.split(":");
						tool_list[tp[0]] = '';
					});					
				}
				all_patients[aData[patient_id_idx]] = '';
				@if ($has_qci)
					qci_check = checkQCITier(aData[qci_actionability_idx]);
					if (!qci_check)
						return false;
				@endif
				if (!checkTier(aData[tier_idx]))
					return false;
				if ($('#selTypes').val() != "All") {
					if ($('#selTypes').val() != aData[type_idx])
						return false;
				}
				if ($('#selTools').val() != "All") {
					if (aData[tool_idx].indexOf($('#selTools').val()) == -1)
						return false;
				}
				if ($('#ckInterChr').is(":checked") && aData[left_chr_idx]==aData[right_chr_idx])
					return false;
				if (onco_filter == null)
					return true;
				var outer_comp_list = [];
				filter_settings = [];
				for (var filter in onco_filter.filters) {
					var comp_list = [];
					var filter_setting = [];				
					for (var i in onco_filter.filters[filter]) {
						var filter_item_setting = [];
						var filter_name = onco_filter.getFilterName(filter, i);
						var idx = filter_list[filter_name];
						filter_item_setting.push(filter_name);
						if (idx == -1)
							currentEval = true;
						else
							currentEval = (aData[idx] != '');
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

				if (outer_comp_list.length == 0) {
					filtered_patients[aData[patient_id_idx]] = '';
					final_decision = true;
				}
				else	
					final_decision = eval(outer_comp_list.join('||'));
	        	return final_decision;
			}
			return true;
		});	
				
		$('#btnDownload').on('click', function() {
			var url = '{{url('/getVarActionable')}}' + '/' + '{{$patient_id}}' + '/' + '{{$case_name}}' + '/fusion/N';
			window.location.replace(url);	
		});

		$('#btnClearFilter').on('click', function() {
			showAll();		
		});	

		$('.mytooltip').tooltipster();		

	});	

	function getFirstProperty(obj) {
		for (key in obj) {
			if (obj.hasOwnProperty(key))
				return key;
		}
	}
	function doFilter() {
		if (first_loading) {
    		var types = objAttrToArray(type_list);
    		types.sort().forEach(function(d){
    			//if (d == "in-frame")
    			//	$('#selTypes').append($('<option>', {value: d, text: d, selected: true}));
    			//else
    				$('#selTypes').append($('<option>', {value: d, text: d}));	
    		});
    		var tools = objAttrToArray(tool_list);
    		tools.sort().forEach(function(d){
    			if (d == "Arriba")
    				$('#selTools').append($('<option>', {value: d, text: d, selected: true}));
    			else
    				$('#selTools').append($('<option>', {value: d, text: d}));	
    		});
    		first_loading = false;
    	}		
		all_patients = [];
		filtered_patients = [];
		tbls['tblFusion'].draw();
		$('#lblCountPatients').text(objAttrToArray(filtered_patients).length);
    	$('#lblCountTotalPatients').text(objAttrToArray(all_patients).length);

    	uploadSetting();
	}

	function applySetting() {

		console.log("{{$setting->tier1}}");

		var tier1 = {{empty($setting->tier1)?"false":$setting->tier1}};
		var tier2 = {{empty($setting->tier2)?"false":$setting->tier2}};
		var tier3 = {{empty($setting->tier3)?"false":$setting->tier3}};
		var tier4 = {{empty($setting->tier4)?"false":$setting->tier4}};
		var inter_chr = {{empty($setting->inter_chr)?"false":$setting->inter_chr}};		
		var type = '{{!property_exists($setting, 'type')? "": $setting->type}}';

		if (type == '')
			type = 'All';
		$('#selTypes').val(type);
		
		if (tier1) {
			$('#btnTier1').addClass('active');
			$('#ckTier1').prop('checked', true);
		}else {
			$('#btnTier1').removeClass('active');
			$('#ckTier1').prop('checked', false);	
		}
		if (tier2) {
			$('#btnTier2').addClass('active');
			$('#ckTier2').prop('checked', true);
		}else {
			$('#btnTier2').removeClass('active');
			$('#ckTier2').prop('checked', false);	
		}
		if (tier3) {
			$('#btnTier3').addClass('active');
			$('#ckTier3').prop('checked', true);
		}else {
			$('#btnTier3').removeClass('active');
			$('#ckTier3').prop('checked', false);	
		}
		if (tier4) {
			$('#btnTier4').addClass('active');
			$('#ckTier4').prop('checked', true);
		}else {
			$('#btnTier4').removeClass('active');
			$('#ckTier4').prop('checked', false);	
		}
		if (tier1 && tier2 && tier3 && tier4) {
			$('#btnTierAll').addClass('active');
			$('#ckTierAll').prop('checked', true);	
		} else {
			$('#btnTierAll').removeClass('active');
			$('#ckTierAll').prop('checked', false);	
		}
		
	}

	function uploadSetting() {
		if (patient_id == 'null')
			return;
		var setting = {
						'tier1' : $('#ckTier1').is(":checked"), 
						'tier2' : $('#ckTier2').is(":checked"), 
						'tier3' : $('#ckTier3').is(":checked"),
						'tier4' : $('#ckTier4').is(":checked"),						
						'inter_chr' : $('#ckInterChr').is(":checked"),
						'type' : '',
						'filters' : JSON.stringify(filter_settings)
					};		
		if ($('#ckInFrame').is(":checked"))
			setting.type = 'In-frame';
		if ($('#ckRightIntact').is(":checked"))
			setting.type = 'Right gene intact';
		if ($('#ckLeftIntact').is(":checked"))
			setting.type = 'Left gene intact';
		if ($('#ckOutOfFrame').is(":checked"))
			setting.type = 'Out-of-frame';
		var url = '{{url("/saveSetting")}}' + '/page.fusion';
		$.ajax({ url: url, async: true, type: 'POST', dataType: 'text', data: setting, success: function(data) {
			}, error: function(xhr, textStatus, errorThrown){
					console.log('save failed! Reason:' + JSON.stringify(xhr) + ' ' + errorThrown);
				}
		});	

	}
	
	function checkTier(value) {
		//console.log(value.substring(0, 6));
		if ($('#ckTier1').is(":checked") && value.substring(0, 1) =="1")
			return true;
		if ($('#ckTier2').is(":checked") && value.substring(0, 1)=="2")
			return true;
		if ($('#ckTier3').is(":checked") && value.substring(0, 1)=="3")
			return true;
		if ($('#ckTier4').is(":checked") && value.substring(0, 1)=="4")
			return true;
		if ($('#ckTierAll').is(":checked") && value=="")
			return true;
		return false;
	}

	function checkQCITier(value) {		
		if ($('#ckQCITier1').is(":checked") && value.substring(0,1)=="1")
			return true;
		if ($('#ckQCITier2').is(":checked") && value.substring(0,1)=="2")
			return true;
		if ($('#ckQCITier3').is(":checked") && value.substring(0,1)=="3")
			return true;
		if ($('#ckQCITier4').is(":checked") && value.substring(0,1)=="4")
			return true;
		if ($('#ckQCINoTier').is(":checked") && value=="")
			return true;
		return false;
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
			"dom": 'B<"toolbar">lfrtip',
			"buttons": [
        		{
            		text: '<img width=15 height=15 src={{url("images/download.svg")}}></img>&nbsp;Download',
            		extend:'csv',
            		title: "{{$patient_id}}+'_'+fusion"
        		},

   			]
		} );		
		tbls[tblId] = tbl;
		var columns =[];
		col_html[tblId] = '';

		if (tblId == 'tblFusion') {
			for (var i=user_list_idx;i<data.cols.length;i++) {
				filter_list[data.cols[i].title] = i;
				hide_cols.tblFusion.push(i);
			}

		}
		$("div.toolbar").html('<button id="' + tblId + '_popover" data-toggle="popover" data-placement="bottom" type="button" class="btn btn-default" style="font-size: 12px;">Select Columns</button>');
		tbl.columns().iterator('column', function ( context, index ) {
			var show = (hide_cols[tblId].indexOf(index) == -1);
			tbl.column(index).visible(show);
			columns.push(tbl.column(index).header().innerHTML);
			checked = (show)? 'checked' : '';
			//checked = 'checked';
			col_html[tblId] += '<input type=checkbox ' + checked + ' class="onco_checkbox data_column" id="data_column_' + tblId + '" value=' + index + '><font size=3>&nbsp;' + tbl.column(index).header().innerHTML + '</font></input><BR>';
		});
		column_tbls[tblId] = columns;
	        
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

		$('#tblFusion').on( 'draw.dt', function () {
			var tbl = tbls[tblId];
			$('#lblCountDisplay').text(tbl.page.info().recordsDisplay);
    		$('#lblCountTotal').text(tbl.page.info().recordsTotal);
    	});

	}

	function showAll() {
		$('#btnTierAll').addClass('active');
		$('#ckTierAll').prop('checked', true);
		$('.tier_filter').addClass('active');
		$('.mut').removeClass('active');
		$('.ckTier').prop('checked', true);
		$('#selTypes').val('All');
		$('#selTools').val('All');
		$('#ckInterChr').prop('checked', false);
		tbls['tblFusion'].search('');
		onco_filter.clearFilter();
	}

	function getAAHint(triplet) {
		var triplets = {
                'TCA':'S','TCC':'S','TCG':'S',
                'TCT':'S','TTC':'F','TTT':'F',
                'TTA':'L','TTG':'L','TAC':'Y',
                'TAT':'Y','TAA':'*','TAG':'*',
                'TGC':'C','TGT':'C','TGA':'*',
                'TGG':'W','CTA':'L','CTC':'L',
                'CTG':'L','CTT':'L','CCA':'P',
                'CCC':'P','CCG':'P','CCT':'P',
                'CAC':'H','CAT':'H','CAA':'Q',
                'CAG':'Q','CGA':'R','CGC':'R',
                'CGG':'R','CGT':'R','ATA':'I',
                'ATC':'I','ATT':'I','ATG':'M',
                'ACA':'T','ACC':'T','ACG':'T',
                'ACT':'T','AAC':'N','AAT':'N',
                'AAA':'K','AAG':'K','AGC':'S',
                'AGT':'S','AGA':'R','AGG':'R',
                'GTA':'V','GTC':'V','GTG':'V',
                'GTT':'V','GCA':'A','GCC':'A',
                'GCG':'A','GCT':'A','GAC':'D',
                'GAT':'D','GAA':'E','GAG':'E',
                'GGA':'G','GGC':'G','GGG':'G',
                'GGT':'G'
            }; 
        var aa = triplets[triplet];
        if (aa == null)
        	aa = 'X'
        return aa;
	}

	function getAADesc(aa) {
		var aa_desc = {
			'G':'Glycine (Gly)',
			'P':'Proline (Pro)',
			'A':'Alanine (Ala)',
			'V':'Valine (Val)',
			'L':'Leucine (Leu)',
			'I':'Isoleucine (Ile)',
			'M':'Methionine (Met)',
			'C':'Cysteine (Cys)',
			'F':'Phenylalanine (Phe)',
			'Y':'Tyrosine (Tyr)',
			'W':'Tryptophan (Trp)',
			'H':'Histidine (His)',
			'K':'Lysine (Lys)',
			'R':'Arginine (Arg)',
			'Q':'Glutamine (Gln)',
			'N':'Asparagine (Asn)',
			'E':'Glutamic Acid (Glu)',
			'D':'Aspartic Acid (Asp)',
			'S':'Serine (Ser)',
			'T':'Threonine (Thr)',
			'X':'Incomplete codon',
			'*':'Stop codon'
		};
		return aa_desc[aa];
	}

	function inDomains(pos, domains) {
		for (i in domains) {
			if (pos >= domains[i][0] && pos <= domains[i][1])
				return domains[i][3];
		}
		return '';
	}
	//get fusion info from server and plot
	function getAAprop(fused_pep, domains, fused_pos) {
		console.log("fused_pos: " + fused_pos);
		var current_pos = 0;
		//fused_pos--;
		var aa_seqs = [];
		var html = '';
		var left_color = 'rgba(0,204,102,';
		var right_color = 'rgba(255,153,153,';
		var domain_coord = '';
		fused_pos = parseInt(fused_pos);
		if (domains == null) 
			return '<span style=background-color:' + left_color + '0.4)>' + fused_pep.substring(0, fused_pos) + '</span><span style=background-color:' + right_color + '0.4)>' + fused_pep.substring(fused_pos, fused_pep.length) + '</span>';	
		for (var i=0;i<domains.length;i++) {
			var domain = domains[i];
			//adjust domain coordinate in case domains have overlapped
			
			domain.start_pos = parseInt(domain.start_pos);
			domain.end_pos = parseInt(domain.end_pos);
			domain_coord += domain.hint.Name + ' ';
			console.log("domain.start_pos: " + domain.start_pos);
			console.log("domain.end_pos: " + domain.end_pos);

			if (current_pos > domain.start_pos)
				domain.start_pos = current_pos;

			//area not in domains
			if (current_pos < fused_pos && domain.start_pos >= fused_pos) {
				html += '<span style=background-color:' + left_color + '0.4)>' + fused_pep.substring(current_pos, fused_pos) + '</span>';
				html += '<span style=background-color:' + right_color + '0.4)>' + fused_pep.substring(fused_pos, domain.start_pos) + '</span>';
			} else {
				var color = (domain.start_pos < fused_pos)? left_color: right_color;
				html += '<span style=background-color:' + color + '0.4)>' + fused_pep.substring(current_pos, domain.start_pos) + '</span>';
			}
			if (domain.hint.Name == "dummy")
				break;			
			//area in domains
			if (domain.start_pos <= fused_pos &&  domain.end_pos >= fused_pos) {
				html += '<span title="' + domain.hint.Name + '(' + domain.hint.Description + ')" style="border-style: dotted;"><span style="background-color:' + left_color + '1);">' + fused_pep.substring(domain.start_pos, fused_pos) + '</span>';
				html += '<span style="background-color:' + right_color + '1);">' + fused_pep.substring(fused_pos, domain.end_pos) + '</span></span>';
			} else {
				var color = (domain.end_pos < fused_pos)? left_color: right_color;
				html += '<span title="' + domain.hint.Name + '(' + domain.hint.Description + ')" style="background-color:' + color + '1);border-style: dotted;">' + fused_pep.substring(domain.start_pos, domain.end_pos) + '</span>';
			}
			current_pos = domain.end_pos;
		}
		if (current_pos <= fused_pos) {
			html += '<span style=background-color:' + left_color + '0.4)>' + fused_pep.substring(current_pos, fused_pos) + '</span>';
			current_pos = fused_pos;
		}
		html += '<span style=background-color:' + right_color + '0.4)>' + fused_pep.substring(current_pos) + '</span>';
		//html += fused_pep;
		//alert(domain_coord);
		//alert(fused_pos);
		return html;		
	}

	function addDomainHint(domains) {
		if (domains.constructor === String)
			domains = JSON.parse(domains);
		new_domains = [];
		domains.forEach(function (d) {
			accession_arr = d[3].split(".");
			accession = accession_arr[0];
			var domain = {"start_pos": d[0], "end_pos": d[1], "name": d[2], "hint": {"Name": d[2], "Coordinate": d[0] + " - " + d[1], "Length": d[1] - d[0] + 1, "Description": d[4], "Accession": "<a target=_blank href='https://pfam.xfam.org/family/" + accession + "''>" + accession + "</a>"}};
			new_domains.push(domain);			
		});
		return new_domains;
	}

	function addExonHint(exon_infos) {
		exon_infos.forEach(function (d) {
			d.hint = {"Type": d.type, "Coordinate": d.start_pos + " - " + d.end_pos, "Exon number": d.exon_number};
		});
		return exon_infos;
	}

	function plotGeneFusion(plot_id, aa_id, cdna_id, download_id, type_id, left_status_id, right_status_id, opacity_id, left_gene, right_gene,  left_trans, right_trans, left_junction, right_junction, fusion_protein, left_info, right_info) {
		if (left_info.exon_info.length == 0) {
			$('#' + type_id).html('No exon information');
			return;
		}
		if (right_info.exon_info.length == 0) {
			$('#' + type_id).html('No exon information');
			return;
		}
		
		var fusion_info = {};
		fusion_protein.transcripts.forEach(function (d) {
			if (d.left_trans == left_trans && d.right_trans == right_trans) {
				
				fusion_info = d;
				return;
			}

		});
		
		var left_color = "lightgreen";
		var right_color = "pink";
		domains = {};
		domains[left_trans] = addDomainHint(left_info.domains);
		domains[right_trans] = addDomainHint(right_info.domains);
		domains["fused"] = addDomainHint(fusion_protein.domains);
		left_info.exon_info = addExonHint(left_info.exon_info);
		right_info.exon_info = addExonHint(right_info.exon_info);
		console.log("fusion_info.fuse_pep_position");
		console.log(fusion_info.fuse_pep_position);
		var right_pep_junction = right_info.protein_length - Math.floor(right_info.cdna.length / 3) + 1;
		var has_protein = (fusion_protein.sequence.length > 0);
		var geneInfo = {"gene1":{"name":left_gene, "trans":left_trans, "chr":left_info.chr,"strand":left_info.strand,"color":left_color,"junction":left_junction,"exons":left_info.exon_info, "pep_length":left_info.protein_length, "pep_junction" : fusion_info.fuse_pep_position},
			"gene2":{"name":right_gene, "trans":right_trans, "chr":right_info.chr,"strand":right_info.strand,"color":right_color,"junction":right_junction,"exons":right_info.exon_info, "pep_length":right_info.protein_length, "pep_junction" : right_pep_junction}, "has_protein": has_protein, "fused_pep_position":fusion_info.fuse_pep_position, "fused_pep" : fusion_protein.sequence, "domains":domains};
		$('#' + type_id).html(fusion_protein.type);
		left_status = fusion_info.left_location + ", " + fusion_info.left_exon_number;
		right_status = fusion_info.right_location + ", " + fusion_info.right_exon_number;
		if (left_info.seq_to_ss != "")
			left_status = left_status + ", sequence to the splice site: " + left_info.seq_to_ss;
		if (right_info.seq_to_ss != "")
			right_status = right_status + ", sequence to the splice site: " + right_info.seq_to_ss;
		$('#' + left_status_id).html(left_status);
		$('#' + right_status_id).html(right_status);
		plot_height = 500;
		if (has_protein) {					
			var html = getAAprop(fusion_protein.sequence, domains["fused"], fusion_info.fuse_pep_position);
			$('#' + aa_id).html(html);
			plot_height = 700;			
			var left_html = "";
			var opacity = 1;
			var triplet = "";
			if (fusion_protein.type != "right gene intact")
				for (var i=0;i<left_info.cdna.length;i+=3) {
					var end = Math.min(i+3,left_info.cdna.length);
					opacity = (opacity == 1)? 0.4 : 1;
					triplet = left_info.cdna.substring(i, end);
					var aa = getAAHint(triplet);
					var hint = aa + ' , ' + getAADesc(aa);
					left_html += '<span title="' + hint + '" style="background-color:rgba(0,204,102,' + opacity + ')">' + triplet + '</span>';
			}
			var right_html = "";
			for (var i=(left_info.cdna.length % 3)*-1;i<right_info.cdna.length;i+=3) {
				var end = Math.min(i+3,right_info.cdna.length);
				triplet = (i < 0)? triplet + right_info.cdna.substring(i, end): right_info.cdna.substring(i, end);
				var aa = getAAHint(triplet);
				var hint = aa + ' , ' + getAADesc(aa);					
				left_html += '<span title="' + hint + '"style="background-color:rgba(255,153,153,' + opacity + ')">' + right_info.cdna.substring(i, end) + '</span>';
				opacity = (opacity == 1)? 0.4 : 1;
			}
			$('#' + cdna_id).html(left_html);
			console.log(opacity_id);
			console.log($('#' + opacity_id).val());			
			var fusionPlot = new GeneFusionPlot({"height": plot_height, "targetElement" : plot_id, "downloadID" : download_id, "cytobandFile" : '{{url('/packages/gene_fusion/data/hg19_cytoBand.txt')}}', "genes": geneInfo, "opacity" : $('#' + opacity_id).val()});
		}		
	}
	

	function format( d, idx ) {
		var left_gene = d[left_gene_idx];
		if (left_gene.indexOf('<img') > -1)
			left_gene = left_gene.substring(0, left_gene.indexOf('<img'));		
		var right_gene = d[left_gene_idx+1];
		if (right_gene.indexOf('<img') > -1)
			right_gene = right_gene.substring(0, right_gene.indexOf('<img'));
		var left_chr = d[left_gene_idx+2];
		var left_junction = parseInt(d[left_gene_idx+3]);
		var right_chr = d[left_gene_idx+4];
		var right_junction = parseInt(d[left_gene_idx+5]);
		var sample_id = d[left_gene_idx+6];
		var tool = d[left_gene_idx+7];
		var type = d[left_gene_idx+8];
		var tier = d[left_gene_idx+9];
		var left_region = d[left_gene_idx+10];
		var right_region = d[left_gene_idx+11];
		var left_trans = d[left_gene_idx+12];
		var right_trans = d[left_gene_idx+13];

		var id = Date.now();
		sample_id = sample_id.replace(/^Sample_/, '');
		var loading_id = 'loading_' + id;
		var content_id = "content_" + id;
		var opacity_id = "opacity_" + id;
		var tbl_id = 'tbl' + id;
		//var url = '{{url('/getTranscriptExpressionData')}}' + '/' + left_gene + ',' + right_gene + '/' + sample_id;
		var fusion_type = ($('#ckInFrame').is(":checked"))? 'In-frame' : 'all';
		var url = '{{url('/getFusionData')}}' + '/' +  left_gene + '/' + right_gene + '/' + left_chr + '/' + right_chr + '/' + left_junction + '/' + right_junction + '/' + sample_id + '/' + fusion_type;
		var fusion_data = {};
		console.log(url);

		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				fusion_data = JSON.parse(data);	
				//push data to select options
				if (fusion_data.fusion_proteins.length == 0) {
					alert("No transcript data!");
					$('#' + loading_id).css("display","none");
					$('#' + content_id).css("display","block");
					$('#' + type_id).html("No transcript data");
					//return;
				}
				var target_type = "";
				if ($('#ckInFrame').is(":checked"))
					target_type = "In-frame";	
				//console.log("fusion data");
				//console.log(JSON.stringify(fusion_data.fusion_proteins));
				var plot_col = {
                	"class":"details-control",
                	"title": "Plot",
                	"orderable":      false,                
                	"defaultContent": ""
        		}
				var fusion_details_cols = [plot_col,{title:"Fusion protein"},{title:"Protein length"},{title:"Left transcript"},{title:"Right transcript"},{title:"Left canonical"},{title:"Right canonical"},{title:"Left TPM"},{title:"Right TPM"},{title:"Type"},{title:"Tier"},{title:"Left region"},{title:"Right region"},{title:"LeftJSON", visible:false, "searchable": false}, {title:"RightJSON", visible:false, "searchable": false}, {title:"FusionJSON", visible:false, "searchable": false}];
				var fusion_details_data = [];
				Object.keys(fusion_data.fusion_proteins).forEach(function(fid) {					
					d = fusion_data.fusion_proteins[fid];
					fusion_json = JSON.stringify(d);
					d.transcripts.forEach(function(t) {
						left_info = fusion_data.left_info[t.left_trans];
						right_info = fusion_data.right_info[t.right_trans];
						left_info_json = JSON.stringify(left_info);
						right_info_json = JSON.stringify(right_info);
						left_canonical = [];
						if (left_info.is_canonical)
							left_canonical.push("Canonical");
						if (left_info.is_mane)
							left_canonical.push("MANE");
						left_canonical = left_canonical.join(",");
						right_canonical = [];
						if (right_info.is_canonical)
							right_canonical.push("Canonical");
						if (right_info.is_mane)
							right_canonical.push("MANE");
						right_canonical = right_canonical.join(",");
						row_data = ["<img width=20 height=20 src='{{url('images/details_open.png')}}'></img>",fid, d.length, t.left_trans,t.right_trans,left_canonical, right_canonical,left_info.expression, right_info.expression, t.type, t.tier, t.left_location + ':' + t.left_exon_number, t.right_location + ':' + t.right_exon_number, left_info_json, right_info_json, fusion_json];
						fusion_details_data.push(row_data);
					});																
				});

				var tbl = $('#' + tbl_id).DataTable({
						"data": fusion_details_data,
						"columns": fusion_details_cols,
						"ordering":    true,
						"lengthMenu": [[15, 25, 50], [15, 25, 50]],
						"pageLength":  25,
						"pagingType":  "simple_numbers",
						"dom": 'l<"toolbar">f'
						});
				var detailRows = [];
				$('#' + tbl_id + ' tbody').on( 'click', 'tr td.details-control', function () {
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
			            tbl.cell( this ).data("<img width=20 height=20 src='{{url('images/details_close.png')}}'></img>");
			            row.child( formatPlot( id + idx ) ).show();			 
			            doPlot( row.data(),left_gene, right_gene, left_chr, right_chr, left_junction, right_junction, id + idx, opacity_id);
			            // Add to the 'open' array
			            if ( idx === -1 ) {
			                detailRows.push( tr.attr('id') );
			            }
			        }
			    });			    
			}
		});

		return '<div style="background-color: white;border: 1px solid #cccccc;padding: 10px;margin: 0px 0px 0px 0px;font-size: 13px;line-height:1;"><br><span style="float:left"><H4 style="float:left">Canonical Transcripts:</H4><table cellpadding="0" cellspacing="0" border="1" word-wrap="break-word" style="width:100%;"><thead><th>Type</th><th>Tier</th><th>Left region</th><th>Right region</th><th>Left trans</th><th>Right trans</th></thead><tr><td>' + type + '</td><td>' + tier + '</td><td>' + left_region + '</td><td>' + right_region + '</td><td>' + left_trans + '</td><td>' + right_trans + '</td></tr></table><br><br><br><H4 style="float:left">All transcripts that produce predicted proteins:</H4>Shadow opacity (0-1) : <input id="' + opacity_id + '" class="easyui-numberbox" data-options="min:0,max:1,precision:1" style="width:60px;height:26px;border:1px solid" value="0.1"></input></span><table cellpadding="0" cellspacing="0" border="1" word-wrap="break-word" id="' + tbl_id + '" style="width:100%;"></table></div>';
	}
	function formatPlot ( id ) {
		var area_id = "area" + id;
		var plot_id = "fus_plot" + id;
		var type_id = "type" + id;
		var aa_id = 'aa_' + id;
		var cdna_id = 'cdna_' + id;
		var download_id = 'download_' + id;
		var left_status_id = 'left_status_' + id;
		var right_status_id = 'right_status_' + id;
		return '<div id="' + area_id + '" style="background-color: #f5f5f5;border: 1px solid #cccccc;padding: 3px;margin: 0px 0px 0px;font-size: 13px;line-height:1;">' + 										
					'<div id="' + plot_id + '" style="border: 1px solid #ccc;"></div>' + 
					'<table class="table table-bordered" width=100% style="background-color: #f5f5f5;table-layout: fixed;">' + 
					'<tr><td style="width:150px">Type</td><td id="' + type_id + '"></td></tr>' +
					'<tr><td style="width:150px">Download</td><td><button id="' + download_id + '" class="btn btn-warning"><img width=15 height=15 src={{url("images/download.svg")}}></img>&nbsp;SVG</button></td></tr>' +					
					'<tr><td style="width:150px">Left junction status</td><td id="' + left_status_id + '"></td></tr>' +
					'<tr><td style="width:150px">Right junction status</td><td id="' + right_status_id + '"></td></tr>' +
					'<tr><td style="width:150px">Fused sequence</td><td id="' + aa_id + '" style="word-wrap:break-word;font-family: monospace; font-size: 18px;"></td></tr>' + 
					'<tr><td style="width:150px">Fused cDNA sequence</td><td id="' + cdna_id + '" style="word-wrap:break-word;font-family: monospace; font-size: 18px;"></td></tr>' + 					
					'</table></div>';
	}

	function doPlot ( d, left_gene, right_gene, left_chr, right_chr, left_junction, right_junction,id, opacity_id ) {
		var left_info = d[d.length-3];
		var right_info = d[d.length-2];
		var fusion_protein = d[d.length-1];
		var left_trans = d[3];
		var right_trans = d[4];
		var type_id = "type" + id;
		var area_id = "area" + id;
		var plot_id = "fus_plot" + id;
		var aa_id = 'aa_' + id;
		var cdna_id = 'cdna_' + id;
		var download_id = 'download_' + id;
		var left_status_id = 'left_status_' + id;
		var right_status_id = 'right_status_' + id;		
		left_info = JSON.parse(left_info);
		right_info = JSON.parse(right_info);
		fusion_protein = JSON.parse(fusion_protein);
		plotGeneFusion(plot_id, aa_id, cdna_id, download_id, type_id, left_status_id, right_status_id, opacity_id, left_gene, right_gene,  left_trans, right_trans, left_junction, right_junction, fusion_protein, left_info, right_info);
	}
	
		
</script>

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

<div id='loadingFusion'><img src='{{url('/images/ajax-loader.gif')}}'></img></div>					
		<div id='tableAreaFusion' style="width:100%;padding:10px;overflow:none;display:none;text-align: left;font-size: 12px;">						
				<div>
						<table style='width:99%;'>
									<tr>
									<td colspan="2">
										<span id='filter' style='display: inline;height:200px;width:80%'>
											<button id="btnAddFilter" class="btn btn-primary">Add filter</button>&nbsp;<a id="fb_filter_definition" href="#filter_definition" title="Filter definitions" class="fancybox mytooltip"><img src={{url("images/help.png")}}></img></a>&nbsp;
											<span style="font-family: monospace; font-size: 20;float:right;">
											@if ($patient_id == 'null')
												Patients: <span id="lblCountPatients" style="text-align:left;color:red;" text=""></span>/<span id="lblCountTotalPatients" style="text-align:left;" text=""></span>
											@endif
												&nbsp;Fusion:&nbsp;<span id="lblCountDisplay" style="text-align:left;color:red;" text=""></span>/<span id="lblCountTotal" style="text-align:left;" text=""></span>
											</span>
										</span>
										<button id="btnClearFilter" type="button" class="btn btn-info" style="font-size: 12px;">Show all</button>
									</td>
									</tr>
									<tr>
									<td colspan="2">
										<div style="height:20px;"><HR></div>
									</td>
									</tr>
									<tr>
										<td></td><td>
										<img class="mytooltip" src={{url("images/help.png")}}></img>Types: 
											<select id="selTypes" class="form-control" style="width:150px;display: inline;">
												<option value="All">All</option>												
											</select>
											&nbsp;Tools:
											<select id="selTools" class="form-control" style="width:150px;display: inline;">
												<option value="All">All</option>
											</select>
										<span class="btn-group" id="interchr" data-toggle="buttons">
			  								<label class="mut btn btn-default">
												<input class="ck" id="ckInterChr" type="checkbox" autocomplete="off">Inter-chromosomal
											</label>
										</span>	
										<a target=_blank href="{{url("data/".Config::get('onco.classification_fusion'))}}" title="Tier definitions" class="mytooltip"><img src={{url("images/help.png")}}></img></a>
										<!--a id="fb_tier_definition" href="{{url("data/".Config::get('onco.classification_fusion'))}}" title="Tier definitions" class="fancybox mytooltip"><img src={{url("images/help.png")}}></img></a-->
										<span class="btn-group" id="tiers" data-toggle="buttons">
					  						<label id="btnTier1" class="btn btn-default tier_filter">
												<input id="ckTier1" class="ckTier" type="checkbox" autocomplete="off">Tier 1
											</label>
											<label id="btnTier2" class="btn btn-default tier_filter">
												<input id="ckTier2" class="ckTier" type="checkbox" autocomplete="off">Tier 2
											</label>
											<label id="btnTier3" class="btn btn-default tier_filter">
												<input id="ckTier3" class="ckTier" type="checkbox" autocomplete="off">Tier 3
											</label>
											<label id="btnTier4" class="btn btn-default tier_filter">
												<input id="ckTier4" class="ckTier" type="checkbox" autocomplete="off">Tier 4
											</label>											
										</span>
										<span class="btn-group" id="tier_all" data-toggle="buttons">
											<label id="btnTierAll" class="btn btn-default">
												<input id="ckTierAll" type="checkbox" autocomplete="off">All
											</label>
										</span>
										@if ($has_qci)
										<span id="QCIfilter" style="display:inline">QCI:&nbsp;
											<span class="btn-group" id="QCItiers" data-toggle="buttons">
  												<label id="btnQCITier1" class="btn btn-default tier_filter active">
													<input id="ckQCITier1" class="ckQCITier" type="checkbox" autocomplete="off" checked>1
												</label>
												<label id="btnQCITier2" class="btn btn-default tier_filter active">
													<input id="ckQCITier2" class="ckQCITier" type="checkbox" autocomplete="off" checked>2
												</label>
												<label id="btnQCITier3" class="btn btn-default tier_filter active">
													<input id="ckQCITier3" class="ckQCITier" type="checkbox" autocomplete="off" checked>3
												</label>
												<label id="btnQCITier4" class="btn btn-default tier_filter active">
													<input id="ckQCITier4" class="ckQCITier" type="checkbox" autocomplete="off" checked>4
												</label>
												<label id="btnQCINoTier" class="btn btn-default tier_filter active">
													<input id="ckQCINoTier" class="ckQCITier" type="checkbox" autocomplete="off" checked>No Tier
												</label>
											</span>
										</span>
										@endif
										@if ($patient_id != 'null')
											<!--button id="btnDownload" class="btn btn-info">Download Actionable</button-->
										@endif
									</span>
								</td></tr>
						</table>								
        		</div>
        		<HR>
				<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblFusion" style='width:100%;'>
				</table>
		</div>					
</div>
			
