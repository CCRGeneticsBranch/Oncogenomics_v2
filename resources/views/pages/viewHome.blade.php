@extends('layouts.default')
@section('title', 'Oncogenomics-Home')
@section('content')

{!! HTML::style('css/style_datatable.css') !!}
{!! HTML::style('css/font-awesome.min.css') !!}

{!! HTML::script('packages/highchart/js/highcharts.js')!!}
{!! HTML::script('packages/highchart/js/modules/exporting.js')!!}
{!! HTML::style('packages/jquery-ui-1.14.1/jquery-ui.min.css')!!}
{!! HTML::script('packages/jquery-ui-1.14.1/jquery-ui.min.js')!!}
{!! HTML::script('packages/DataTables/datatables.min.js') !!}
{!! HTML::script('js/onco.js') !!}

<style>
.ui-dialog { z-index: 9999 !important ;}
</style>
<script type="text/javascript">
	
	(function($){
	    $.widget( "ui.combobox", $.ui.autocomplete, 
	        {
	        options: { 
	            /* override default values here */
	            minLength: 2,
	            /* the argument to pass to ajax to get the complete list */
	            ajaxGetAll: {get: "all"}
	        },

	        _create: function(){
	            if (this.element.is("SELECT")){
	                this._selectInit();
	                return;
	            }

	            $.ui.autocomplete.prototype._create.call(this);
	            var input = this.element;
	            input.addClass( "ui-widget ui-widget-content ui-corner-left" );

	            this.button = $( "<button type='button'>&nbsp;</button>" )
	            .attr( "tabIndex", -1 )
	            .attr( "title", "Show All Items" )
	            .insertAfter( input )
	            .button({
	                icons: { primary: "ui-icon-triangle-1-s" },
	                text: false
	            })
	            .removeClass( "ui-corner-all" )
	            .addClass( "ui-corner-right ui-button-icon" )
	            .click(function(event) {
	                // close if already visible
	                if ( input.combobox( "widget" ).is( ":visible" ) ) {
	                    input.combobox( "close" );
	                    return;
	                }
	                // when user clicks the show all button, we display the cached full menu
	                var data = input.data("combobox");
	                clearTimeout( data.closing );
	                if (!input.isFullMenu){
	                    data._swapMenu();
	                    input.isFullMenu = true;
	                }
	                /* input/select that are initially hidden (display=none, i.e. second level menus), 
	                   will not have position cordinates until they are visible. */
	                input.combobox( "widget" ).css( "display", "block" )
	                .position($.extend({ of: input },
	                    data.options.position
	                    ));
	                input.focus();
	                data._trigger( "open" );
	            });

	            /* to better handle large lists, put in a queue and process sequentially */
	            $(document).queue(function(){
	                var data = input.data("combobox");
	                if ($.isArray(data.options.source)){ 
	                    $.ui.combobox.prototype._renderFullMenu.call(data, data.options.source);
	                }else if (typeof data.options.source === "string") {
	                    $.getJSON(data.options.source, data.options.ajaxGetAll , function(source){
	                        $.ui.combobox.prototype._renderFullMenu.call(data, source);
	                    });
	                }else {
	                    $.ui.combobox.prototype._renderFullMenu.call(data, data.source());
	                }
	            });
	        },

	        /* initialize the full list of items, this menu will be reused whenever the user clicks the show all button */
	        _renderFullMenu: function(source){
	            var self = this,
	                input = this.element,
	                ul = input.data( "combobox" ).menu.element,
	                lis = [];
	            source = this._normalize(source); 
	            input.data( "combobox" ).menuAll = input.data( "combobox" ).menu.element.clone(true).appendTo("body");
	            for(var i=0; i<source.length; i++){
	                lis[i] = "<li class=\"ui-menu-item\" role=\"menuitem\"><a class=\"ui-corner-all\" tabindex=\"-1\">"+source[i].label+"</a></li>";
	            }
	            ul.append(lis.join(""));
	            this._resizeMenu();
	            // setup the rest of the data, and event stuff
	            setTimeout(function(){
	                self._setupMenuItem.call(self, ul.children("li"), source );
	            }, 0);
	            input.isFullMenu = true;
	        },

	        /* incrementally setup the menu items, so the browser can remains responsive when processing thousands of items */
	        _setupMenuItem: function( items, source ){
	            var self = this,
	                itemsChunk = items.splice(0, 500),
	                sourceChunk = source.splice(0, 500);
	            for(var i=0; i<itemsChunk.length; i++){
	                $(itemsChunk[i])
	                .data( "item.autocomplete", sourceChunk[i])
	                .mouseenter(function( event ) {
	                    self.menu.activate( event, $(this));
	                })
	                .mouseleave(function() {
	                    self.menu.deactivate();
	                });
	            }
	            if (items.length > 0){
	                setTimeout(function(){
	                    self._setupMenuItem.call(self, items, source );
	                }, 0);
	            }else { // renderFullMenu for the next combobox.
	                $(document).dequeue();
	            }
	        },

	        /* overwrite. make the matching string bold */
	        _renderItem: function( ul, item ) {
	            var label = item.label.replace( new RegExp(
	                "(?![^&;]+;)(?!<[^<>]*)(" + $.ui.autocomplete.escapeRegex(this.term) + 
	                ")(?![^<>]*>)(?![^&;]+;)", "gi"), "<strong>$1</strong>" );
	            return $( "<li></li>" )
	                .data( "item.autocomplete", item )
	                .append( "<a>" + label + "</a>" )
	                .appendTo( ul );
	        },

	        /* overwrite. to cleanup additional stuff that was added */
	        destroy: function() {
	            if (this.element.is("SELECT")){
	                this.input.remove();
	                this.element.removeData().show();
	                return;
	            }
	            // super()
	            $.ui.autocomplete.prototype.destroy.call(this);
	            // clean up new stuff
	            this.element.removeClass( "ui-widget ui-widget-content ui-corner-left" );
	            this.button.remove();
	        },

	        /* overwrite. to swap out and preserve the full menu */ 
	        search: function( value, event){
	            var input = this.element;
	            if (input.isFullMenu){
	                this._swapMenu();
	                input.isFullMenu = false;
	            }
	            // super()
	            $.ui.autocomplete.prototype.search.call(this, value, event);
	        },

	        _change: function( event ){
	            abc = this;
	            if ( !this.selectedItem ) {
	                var matcher = new RegExp( "^" + $.ui.autocomplete.escapeRegex( this.element.val() ) + "$", "i" ),
	                    match = $.grep( this.options.source, function(value) {
	                        return matcher.test( value.label );
	                    });
	                if (match.length){
	                    match[0].option.selected = true;
	                }else {
	                    // remove invalid value, as it didn't match anything
	                    this.element.val( "" );
	                    if (this.options.selectElement) {
	                        this.options.selectElement.val( "" );
	                    }
	                }
	            }                
	            // super()
	            $.ui.autocomplete.prototype._change.call(this, event);
	        },

	        _swapMenu: function(){
	            var input = this.element, 
	                data = input.data("combobox"),
	                tmp = data.menuAll;
	            data.menuAll = data.menu.element.hide();
	            data.menu.element = tmp;
	        },

	        /* build the source array from the options of the select element */
	        _selectInit: function(){
	            var select = this.element.hide(),
	            selected = select.children( ":selected" ),
	            value = selected.val() ? selected.text() : "";
	            this.options.source = select.children( "option[value!='']" ).map(function() {
	                return { label: $.trim(this.text), option: this };
	            }).toArray();
	            var userSelectCallback = this.options.select;
	            var userSelectedCallback = this.options.selected;
	            this.options.select = function(event, ui){
	                ui.item.option.selected = true;
	                if (userSelectCallback) userSelectCallback(event, ui);
	                // compatibility with jQuery UI's combobox.
	                if (userSelectedCallback) userSelectedCallback(event, ui);
	            };
	            this.options.selectElement = select;
	            this.input = $( "<input>" ).insertAfter( select )
	                .val( value ).combobox(this.options);
	        }
	    }
	);
	})(jQuery);



	$(document).ready(function() {

		var url = '{!!url('/getTopVarGenes')!!}';
		console.log(url);		
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				//console.log(data);
				data = JSON.parse(data);
				keys = Object.keys(data);
				//console.log(keys.length);
				//show one plot for now...
				$('#one_col').css("display","none");
				$('#two_cols').css("display","block");
				var type = "germline";
				drawStackPlot("col2_v1", capitalize(type), data[type].category, data[type].series);
				type = "somatic";
				drawStackPlot("col2_v2", capitalize(type), data[type].category, data[type].series);					
				//$('#one_col').css("display","block");
				//$('#two_cols').css("display","none");
				//	var type = keys[0];
				//	drawStackPlot("col1_v1", capitalize(type), data[type].category, data[type].series);
			}			
		});
		console.log('{!!json_encode($exp_types)!!}');
		console.log('{!!json_encode($tissue_cats)!!}');
		var pie_data = {!!json_encode($exp_types)!!};
		showPieChart("exp_type", "Library Type", pie_data, null, false, true, false, 'Number of samples');
		var pie_data = {!!json_encode($tissue_cats)!!};
		showPieChart("tissue_cats", "Normal/Tumor", pie_data, null, false, true, false, 'Number of samples');

		patient_data = {!!$patient_data!!};
		gene_data = {!!$gene_data!!};

		$( "#search_patient" ).autocomplete({
      	source: function( request, response ) {
              var matcher = new RegExp( "^" + $.ui.autocomplete.escapeRegex( request.term ), "i" );
              response( $.grep( patient_data, function( item ){
                  return matcher.test( item );
              }) );
          },
        select: function( event, ui ) {
            var v=ui.item.value; //ui.item is your object from the array            
            $( "#search_patient" ).val( ui.item.label);
            //window.open("{!!url("/viewProjectDetails/")!!}" + "/" + v);
            var e = jQuery.Event("keyup");
			e.which = 13;
			e.keyCode = 13;
			$("#search_patient").trigger(e);
            return false;
        },delay: 500
    });		


    $( "#search_sample" ).autocomplete({
      	source: {!!$sample_data!!},
      	focus: function( event, ui ) {

      	},
        select: function( event, ui ) {
            $( "#search_sample" ).val( ui.item.label);
            //window.open("{!!url("/viewProjectDetails/")!!}" + "/" + v);
            window.open("{!!url('/viewSearchSample')!!}" + "/" + ui.item.label);
            return false;
        }
    });

    $( "#search_gene" ).autocomplete({
      	source: function( request, response ) {
              var matcher = new RegExp( "^" + $.ui.autocomplete.escapeRegex( request.term ), "i" );
              response( $.grep( gene_data, function( item ){
                  return matcher.test( item );
              }) );
          },
      	focus: function( event, ui ) {

      	},
        select: function( event, ui ) {
        		var gene_id = ui.item.label;
            $( "#search_gene" ).val( gene_id);
            @if ($project_count > 1)
        			window.open("{!!url('/viewGeneDetail')!!}" + "/" + gene_id.toUpperCase());
        		@else
        			window.open("{!!url("/viewProjectGeneDetail/$project_id")!!}" + "/" + gene_id.toUpperCase());
        		@endif
            return false;
        }
    });


    $( "#search_project" ).autocomplete({
      	source: {!!$project_data!!},
      	focus: function( event, ui ) {

      	},
        select: function( event, ui ) {
            //var v=$( "#search_project" ).val( ui.item.label); //ui.item is your object from the array            
            var v=ui.item.v; //ui.item is your object from the array            
            console.log(v);
            console.log(ui.item.label);
            $( "#search_project" ).val( ui.item.label);
            window.open("{!!url("/viewProjectDetails/")!!}" + "/" + v);
            return false;
        }
    });

    $( "#search_cancer_type" ).autocomplete({
      	source: {!!$cancer_type_data!!},
      	focus: function( event, ui ) {

      	},
        select: function( event, ui ) {
            //var v=$( "#search_project" ).val( ui.item.label); //ui.item is your object from the array            
            var v=ui.item.label; //ui.item is your object from the array            
            console.log(v);
            console.log(ui.item.label);
            $( "#search_cancer_type" ).val( ui.item.label);
            window.open("{!!url("/viewCancerTypeDetails/")!!}" + "/" + v);
            return false;
        }
    });


	$('#search_gene').keyup(function(e){
			if(e.keyCode == 13) {
				var gene_id = $('#search_gene').val();
				@if ($project_count > 1)
        			window.open("{!!url('/viewGeneDetail')!!}" + "/" + gene_id.toUpperCase());
        		@else
        			window.open("{!!url("/viewProjectGeneDetail/$project_id")!!}" + "/" + gene_id.toUpperCase());
        		@endif
    		}
		});

	$('#search_patient').keyup(function(e){
				if(e.keyCode == 13) {
						var patient_id = $('#search_patient').val();
						var url = '{!!url('/getProejctListForPatient')!!}' + '/' + patient_id;
						console.log(url);		
						$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
								data = JSON.parse(data);
								if (data.data.length == 1) {
									window.open("{!!url("/viewPatient")!!}" + "/" + data.data[0][1] + "/" + patient_id + "/any");
									return;
								}
								new_data = [];
								data.data.forEach(function(d){
									var patient_url = "{!!url('/viewPatient')!!}" + "/" + d[1] + "/" + patient_id + "/any";
									new_data.push(["<a target='_blank' href='" + patient_url + "'>" + d[0] + '</a>']);
								})
								if ( $.fn.DataTable.isDataTable('#tblProjectList') ) {
  									$('#tblProjectList').DataTable().destroy();
								}
								//$('#tblProjectList tbody').empty();
								tblDetail = $('#tblProjectList').DataTable({ 
									"paging":   false,
									"info":   false,
									"searching": false,
									"data": new_data,
									"columns": [{"title":"Project"}]
								});
								//var x = jQuery(this).position().left;
    						//var y = jQuery(this).position().top;
    						$( "#projectDiag" ).dialog({position: {my: 'left-100 top', 
                                    at: 'right top', 
                                    of: '#search_patient'}});
							}			
						});

        		//window.open("{!!url('/viewPatient')!!}" + "/" + '{!!$project_id!!}' + "/" + patient_id.toUpperCase() + "/any");
    		}
	});
	
	$('#search_sample').keyup(function(e){
			if(e.keyCode == 13) {				
				var sample_id = $('#search_sample').val();
        		window.open("{!!url('/viewSearchSample')!!}" + "/" + sample_id.toUpperCase());
    		}
		});

		$("#btnAnnoSearch").on('click', function(){
			var chr=$("#search_variant_chr").val(); 
			var start=$("#search_variant_start").val(); 
			var end=$("#search_variant_end").val();
			var ref=$("#search_variant_ref").val();
			var alt=$("#search_variant_alt").val();
			url="{!!url('/viewVariant')!!}"+"/"+chr+"/"+start+"/"+end+"/"+ref+"/"+alt
			console.log(url);
			window.open(url);
    		
		});

	});
</script>

<!--div class="main-panel" -->
<div id="projectDiag" title="Select project" style="display:none;z-index: 99999;">
  <table cellpadding="5" cellspacing="5" class="prettyDetail" word-wrap="break-word" id="tblProjectList" style="width:100%;border:2px solid"></table>
</div>

    <div class="sr-only">
      <a href="#main" data-skip-link>Skip to content</a>
    </div>
	<div class="pane-content" style="text-align: center; padding: 10px 0 0 20px">		
		<div  class="container-fluid" style="padding:10px" >									
			<div class="row">
				<div class="col-md-9">
					<!--div class="row">
	                	<div class="card">
	                		<div class="row">
								<div class="col-md-12">
									<div style='text-align:center'>
									    <H4>Search Gene: <input class="form-control"></input></H4>
									</div>
								</div>
							</div>
						</div>
					</div-->
					<div class="row">
						<!--div class="col-md-6">
	                		<div class="card" style="padding:5px">
	                			<div id="main" style='text-align: left; height:220px' role="main" >
									    <H1 style="font-size:18px; margin:5px 0 5px">Mission of the Oncogenomics Section</H1><hr>
									    The mission of the Oncogenomics Section is to harness the power of high throughput genomic and proteomic methods to improve the outcome of children with high-risk metastatic, refractory and recurrent cancers. The research goals are to integrate the data, decipher the biology of these cancers and to identify and validate biomarkers and novel therapeutic targets and to rapidly translate our findings to the clinic.<br><br>
								</div>
							</div>
						</div-->
						<div class="col-md-6">
	                		<div class="card">
	                			<div id="exp_type" style="height:230px">
								</div>
							</div>
						</div>
						<div class="col-md-6">
	                		<div class="card">
	                			<div id="tissue_cats" style="height:230px">
								</div>
							</div>
						</div>
					</div>					
					<br>
					<div class="row">
						<div class="col-md-12">
						<div class="card">
	                    	<div class="row">
								<div class="col-md-4">
									<div class="card mx-2 my-2">
										<div class="card-header bg-info text-white">
											<div class="row">
												<div class="col-md-3">
													<i class="fa fa-institution fa-4x"></i>
												</div>
												<div class="col-md-9 text-right">
													<div class="h3">{!!$project_count!!}<br>Projects</div>                                        
	                                    		</div>
	                                    	</div>
										</div>
										<a href="{!!url('/viewProjects')!!}">
											<div class="card-footer bg-light text-info">
												<span class="pull-left">View Details</span>
												<span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
												<div class="clearfix"></div>
											</div>
										</a>
									</div>
								</div>
								<div class="col-md-4">									
									<div class="card mx-2 my-2">
										<div class="card-header bg-success text-white">
											<div class="row">
												<div class="col-md-3">
													<i class="fa fa-address-card fa-4x"></i>
												</div>
												<div class="col-md-9 text-right">
													<div class="h3">{!!$patient_count!!}<br>Patients</div>                                        
												</div>
	                    					</div>
										</div>
										<a href="{!!url('/viewPatients/null/any/1/normal')!!}">
											<div class="card-footer bg-light text-success">
												<span class="pull-left">View Details</span>
												<span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
												<div class="clearfix"></div>
											</div>
										</a>
									</div>
								</div>
								<div class="col-md-4">
									<div class="card mx-2 my-2">
										<div class="card-header bg-warning text-white">
											<div class="row">
												<div class="col-md-3">
													<i class="fa fa-briefcase fa-4x"></i>
												</div>
												<div class="col-md-9 text-right">
													<div class="h3">{!!$case_count!!}<br>Cases</div>                                        
												</div>
	                                    	</div>
										</div>
										@if (Config::get('site.isPublicSite'))
										<a href="{!!url('/viewPatients/null/any/1/normal')!!}">
										@else
										<a href="{!!url('/viewCases/any')!!}">
										@endif
											<div class="card-footer bg-light text-warning">
												<span class="pull-left">View Details</span>
												<span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
												<div class="clearfix"></div>
											</div>
										</a>										
									</div>
								</div>
							</div>
	                    </div>
	                </div>
	            	</div>
	                <br>
	                @if ($project_count > 0)
	                <div class="row">
	                	<div class="col-md-12">
							<div class="card">
								<div id="two_cols" sytle="display:none">
			                		<div class="row">
										<div class="col-md-6">
											<div id="col2_v1" style="min-width: 310px; height: 350px; margin: 0 auto"></div>
										</div>
										<div class="col-md-6">
											<div id="col2_v2" style="min-width: 310px; height: 350px; margin: 0 auto"></div>
										</div>
									</div>
								</div>
								<div id="one_col" sytle="display:none">
									<div class="row">
										<div class="col-md-12">
											<div id="col1_v1" style="min-width: 310px; height: 350px; margin: 0 auto"></div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					@endif
					<br>                			
				</div>
				@if ($project_count > 0)
				<div class="col-md-3 small">
					<div class="row" style="padding: 0px 20px 0px 20px">
						<div class="col-md-12">
							<div class="card">
								<div class="card-body mx-1 my-1">
									@if (App\Models\User::accessAll() || 1)
									<i class="fa fa-search"></i>Gene:
									<input id="search_gene" class="form-control" type="text" placeholder="Gene Symbol" aria-label="Search Gene Symbol"></input>
									@endif
									<i class="fa fa-search"></i>Patient:
									<input id="search_patient" class="form-control" type="text" placeholder="Patient ID" aria-label="Search Patient"></input>
									<i class="fa fa-search"></i>Sample:
									<input id="search_sample" class="form-control" type="text" placeholder="Sample ID" aria-label="Search Sample"></input>
									<i class="fa fa-search"></i>Project:
									<input id="search_project" class="form-control" type="text" placeholder="Project ID" aria-label="Search Project"></input>
									<i class="fa fa-search"></i>Cancer Type:
									<input id="search_cancer_type" class="form-control" type="text" placeholder="Cancer Type" aria-label="Search Cancer Type"></input>

									@if (!Config::get('site.isPublicSite') && App\Models\User::hasAccessMRN())
										<br />
										<form method="post" action="{!!$lbm!!}" target="customwindow" style="padding-bottom:0px; margin-bottom:0px">
											<input type="hidden" name="tier" value="clinomics_dev" />
      								<input type="hidden" name="authorized" value="{!!$user['id']!!}" /><i class="fa fa-search"></i>Search MRNs by clicking <a href='#' onclick="window.open('{!!$lbm!!}', 'customwindow','left=20,top=20,width=500,height=500,toolbar=0,location=0,status=0,resizable=0');$(this).closest('form').submit();")>here </a>
   									</form>
									@endif
								</div>						
							</div>						
						</div>

					</div>					
					@if (!Config::get('site.isPublicSite'))
					<!--div class="row" style="padding: 0px 20px 0px 20px">
						<div class="col-md-12">
							<div class="panel panel-default">
								
								<div class="panel-body">
									<div class="form-group form-inline" style="margin-bottom: 0px">
										<i class="fa fa-search"></i><span style="font-size:20">Variant:</span></br>
										<input id="search_variant_chr" class="form-control" type="text" placeholder="Chr" aria-label="Search Gene Symbol"   ></input></br>
										<input id="search_variant_start" class="form-control" type="text" placeholder="Start" aria-label="Search Gene Symbol" ></input></br>
										<input id="search_variant_end" class="form-control" type="text" placeholder="End" aria-label="Search Gene Symbol"  ></input></br>
										<input id="search_variant_ref" class="form-control" type="text" placeholder="Ref" aria-label="Search Gene Symbol"  ></input></br>
										<input id="search_variant_alt" class="form-control" type="text" placeholder="Alt" aria-label="Search Gene Symbol" ></input></br></br>
										<button id="btnAnnoSearch" type="button" class="btn btn-infcommit ./ap	o">Search Variant</button>
									</div>
								</div>						
							</div>						
						</div>
					</div-->
					@endif
					@if (count($user_log) > 0)
					<br>
					<div class="row" style="padding: 0px 20px 0px 20px">
						<div class="col-md-12">
							<div class="card">
								<div class="card-header bg-success text-white">
									Recently Visited Patients
								</div>
								<div class="card-body">
								@foreach ($user_log as $patient_id)
									<h2 style="margin:5px 0 2px;font-size:16px"><a class="link-underline-light" target="_blank" href="{!!url("/viewPatient/null/$patient_id")!!}">{!!$patient_id!!}</a></h2>
								@endforeach
								</div>						
							</div>						
						</div>
					</div>
					@endif
					@if (count($project_list) > 0)
					<br>
					<div class="row" style="padding: 0px 20px 0px 20px">
						<div class="col-md-12">
							<div class="card">
								<div class="card-header bg-info text-white">
									Popular Projects
								</div>
								<div class="card-body">
								@foreach ($project_list as $project_id => $name)
									<h2 style="margin:5px 0 2px;font-size:16px"><a class="link-underline-light" target="_blank" href="{!!url("/viewProjectDetails/$project_id")!!}">{!!$name!!}</a></h2>
								@endforeach
								</div>						
							</div>						
						</div>
					</div>
					@endif
					@if (count($gene_list) > 0 && App\Models\User::accessAll())
					<br>
					<div class="row" style="padding: 0px 20px 0px 20px">
						<div class="col-md-12">
							<div class="card">
								<div class="card-header bg-warning text-white">
									Popular Genes
								</div>
								<div class="card-body">
								@foreach ($gene_list as $gene)
									@if ($project_count > 1)
									<h2 style="margin:5px 0 2px;font-size:16px"><a target="_blank" href="{!!url("/viewGeneDetail/$gene")!!}">{!!$gene!!}</a></h5>
									@else
									<h2 style="margin:5px 0 2px;font-size:16px"><a target="_blank" href="{!!url("/viewProjectGeneDetail/$project_id/$gene")!!}">{!!$gene!!}</a></h5>
									@endif

								@endforeach
								</div>						
							</div>						
						</div>
					</div>
					@endif
				</div>
				@endif
			</div>
		</div>
	</div>
<!--/div-->
@stop
