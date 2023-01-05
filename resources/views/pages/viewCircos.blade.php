<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<title></title>
		{{ HTML::style('css/bootstrap.min.css') }}
		{{ HTML::style('css/style.css') }}
		{{ HTML::style('css/circosJS.css') }}
		{{ HTML::style('css/font-awesome.min.css') }}
		<link rel="stylesheet" type="text/css" href="{{asset('//fonts.googleapis.com/css?family=Titillium+Web|Roboto+Condensed')}}">

		{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-core-css.css') }}
		{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-blue/sm-blue.css') }}
		{{ HTML::script('js/jquery-3.6.0.min.js') }}
		{{ HTML::script('packages/smartmenus-1.0.0-beta1/jquery.smartmenus.min.js') }}

		{{ HTML::script('packages/d3/d3.min.js') }}
		{{ HTML::script('packages/d3/d3.tip.js') }}

		{{ HTML::script('js/jquery-ui.min.js') }}
		{{ HTML::script('js/circosJS.js') }}
		{{ HTML::script('js/biocircos-1.1.2.mod.js') }}
		{{ HTML::script('js/colors.js') }}
		{{ HTML::script('js/jqColorPicker.js') }}

		{{ HTML::script('packages/gene_fusion/data/karyotype.human.hg19.js') }}
		{{ HTML::script('js/bootstrap.min.js') }}
	</head>
	<body>
		<div class="config_container">
			<a href="javascript: window.location.reload();" title="Reset Plot" class="reset"><i class="fa fa-refresh"></i></a>
			<a href="javascript:void(0);" title="Detach" class="detach"><i class="fa fa-arrows"></i></a>
			<a href="javascript: void(0);" title="Toggle Config" class="config-hide"><i class="fa fa-minus"></i></a>
			{{--<div class="clearfix"></div>--}}
			<h4>Circos Configuration</h4>
			<div class="clearfix"></div>
			<form id="control">
				<ol id="tracks"></ol>
			</form>
		</div>
		<br />
		<div id="biocircos" style="background-color:#FFFFFF"></div>
	</body>

<script type="text/javascript">

	var patient_id = '{{$patient_id}}';
	var case_name = '{{$case_name}}';
	var circos_data_url = '{{url('/getCircosData')}}' + '/' + patient_id + '/' + case_name;
	console.log(circos_data_url);
	//var cytoband_url = '{{$cytoband_url}}';
	var circos_data = null;
	var placement_options = [];

	var svgWidth = $(window).width();
	var svgHeight = 800;
	var margin = 150;
	var outerRadius = (svgHeight - margin) / 2;
	//var outerRadius = 405;
	var supported_tracks = 12; // do not increase this unless you increase outerRadius
	var track_width = 25;
	var innerRadius = outerRadius - track_width;
	var track_padding = 5;
	var default_radii = get_radius_defaults();


	$(document).ready(function() {

		$(".config_container").draggable({cancel:'#control'});
		$(".config_container").draggable('disable');


		$(".config-hide").on("click", function(){
			$("#download_svg, .setting_container, #fusion_container, #fusion_legend, .point-data-link-container").slideToggle("fast");
			$(this).children("i").toggleClass("fa-minus").toggleClass("fa-window-maximize");
		});

		var currentDate = new Date();
		var day = currentDate.getDate();
		var month = currentDate.getMonth() + 1;
		var year = currentDate.getFullYear();
		var filename = "NCI_circos_" + year + month + day;

		$('title').html(filename);

		$.ajax({
			url: circos_data_url,
			async: true,
			dataType: 'text',
			success: function(d) {

				$("#loading").css("display","none");
				circos_data = JSON.parse(d);

				var colors = get_colors();
				var color = '';
				var used_colors = [];
				for (var property in circos_data) {
					if (circos_data.hasOwnProperty(property)) {

						var name = circos_data[property].name;
						var description = circos_data[property].description;
						var data_type = circos_data[property].data_type;

						var new_color = colors[Math.floor((Math.random() * colors.length))];
						while(used_colors.indexOf(new_color) !== -1){
							new_color = colors[Math.floor((Math.random() * colors.length))];
						}
						used_colors.push(new_color);
						color = new_color;

						if (circos_data[property].range !== undefined) {
							var default_yAxisMin = circos_data[property].range[0];
							var default_yAxisMax = circos_data[property].range[1];
						}

						if (data_type.indexOf("span") >= 0){

							$('#tracks').append('\
								<li class="setting_container">\
									<h4 class="pull-left">\
									'+name+'\
									<a type="button" class="fa fa-info-circle tip-desc" data-toggle="tooltip" data-placement="right" title="'+description+'"></a>\
									</h4>\
									<button class="btn dropdown-toggle color-select" data-toggle="dropdown">\
										<div>\
											<div class="color-well pull-left"></div>\
											<span class="caret pull-left"></span>\
										</div>\
										<input class="color-input" type="hidden" name="'+name+'_color" value="'+color+'"/>\
									</button>\
									<div class="clearfix"></div>\
									<div id="' + name + '_config">\
										<div class="container-fluid">\
											<span class="setting-title pull-left">Active: </span><input name="' + name + '_active" type="checkbox" class="active_chkbx pull-left" value="true" />\
										</div>\
										<div class="container-fluid radio-container">\
											<span class="pull-left setting-title">Type:</span>\
											<label class="radio-inline pull-left">\
												<input class="plot_histo" type="radio" name="' + name + '_plot" value="histogram"><i class="fa fa-bar-chart" aria-hidden="true"></i>\
											</label>\
											<label class="radio-inline pull-left">\
												<input class="plot_line" type="radio" name="' + name + '_plot" value="line"><i class="fa fa-line-chart" aria-hidden="true"></i>\
											</label>\
										</div>\
										<div class="container-fluid dip_contrast">\
											<span class="setting-title pull-left">Diploid Contrast: </span>\
											<input name="'+ name +'_diploid" type="checkbox" class="active_chkbx pull-left" value="true" />\
										</div>\
										<div class="container-fluid yRange-radio-container">\
											<span class="setting-title pull-left">Y-Axis Range: </span>\
											<label class="radio-inline pull-left">\
												<input class="yAxis-radio yAxis-radio-auto" type="radio" name="' + name + '_yAxis" value="automatic">Auto Fit\
											</label>\
											<label class="radio-inline pull-left">\
												<input type="radio" class="yAxis-radio yAxis-radio-custom pull-left" name="' + name + '_yAxis" value="custom">\
												<div class="yRange-val-container">\
													<span class="pull-left">yMin</span>\
													<input type="textfield" class="y-input y-input-min" name="'+name+'_yAxisMin" data-default="'+default_yAxisMin+'" value="'+default_yAxisMin+'">\
													<span class="pull-left">yMax</span>\
													<input type="textfield" class="y-input y-input-max" name="'+name+'_yAxisMax" data-default="'+default_yAxisMax+'" value="'+default_yAxisMax+'">\
												</div>\
											</label>\
										</div>\
									</div>\
								</li>\
							');

							placement_options.push('<option value="'+name+'">'+name+'</option>');

						}else if (data_type.indexOf("links") >= 0){

							var meta_data = [];
							for (var meta in circos_data[property].header){

								if (meta > 5){
									var meta_name = circos_data[property].header[meta];
									var meta_options = [];

									for (var slot in circos_data[property].data){
										meta_options.push(circos_data[property].data[slot][meta]);
									}

									meta_data.push({name: meta_name, options: unique(meta_options).sort()});
								}
							}

							$('#control').append('\
								<div id="fusion_container">\
									<span class="pull-left">' + name + ' Active: </span>\
									<!-- <input name="' + name + '_active" type="checkbox" class="active_chkbx" value="true" /> -->\
									<div id="fusion_input_container" class="pull-right">\
									</div>\
								</div>\
							');

							for (var meta in meta_data){

								if (meta_data[meta].name.localeCompare("type") === 0) {

									$('#fusion_input_container').append('\
										<select id="'+ meta_data[meta].name +'" name="' + meta_data[meta].name + '">\
										</select>\
									');

									$("#"+meta_data[meta].name).append('\
										<option value="all">All '+ toTitleCase(meta_data[meta].name) +'s</option>\
									');

									for (var meta_option in meta_data[meta].options){
										if (meta_data[meta].name.localeCompare("type") === 0) {
											$("#" + meta_data[meta].name).append('\
											<option value="' + meta_data[meta].options[meta_option] + '">' + toTitleCase(meta_data[meta].options[meta_option]) + '</option>\
										');
										}
									}

								}else if (meta_data[meta].name.localeCompare("tier") === 0) {
									$("#control").append('<div class="clearfix"></div><div id="fusion_legend" class="pull-right"></div>');
									var fusion_colors = get_colors(true);
									for (var meta_option in meta_data[meta].options){
										if (meta_data[meta].options[meta_option].indexOf("Tier") >= 0){
											$("#fusion_legend").append('\
												<div class="fusion_legend_tier" style="background-color:'+fusion_colors[meta_option]+';">\
													<input type="checkbox" name="'+"tier_"+meta_data[meta].options[meta_option].replace('Tier ', '')+'"  class="tier_chkbx pull-left" value="true"/>\
													<span style="margin: 2px 2px 0 5px;" class="pull-left">'+ toTitleCase(meta_data[meta].options[meta_option]) +'</span>\
												</div>\
											');
										}
									}

								}




								$("#control").append('<div class="clearfix"></div>');
							}

						}else if (data_type.indexOf("point") >= 0){

							$('#tracks').append('\
								<li class="setting_container">\
									<h4 class="pull-left">\
										'+name+'\
										<a type="button" class="fa fa-info-circle tip-desc" data-toggle="tooltip" data-placement="right" title="'+description+'"></a>\
									</h4>\
									<button class="btn dropdown-toggle color-select" data-toggle="dropdown">\
										<div>\
											<div class="color-well pull-left"></div>\
											<span class="caret pull-left"></span>\
										</div>\
										<input class="color-input" type="hidden" name="'+name+'_color" value="'+color+'"/>\
									</button>\
									<div class="clearfix"></div>\
									<div id="' + name + '_config">\
										<div class="container-fluid">\
											<span class="setting-title pull-left">Active: </span><input name="' + name + '_active" type="checkbox" class="active_chkbx pull-left" value="true" />\
										</div>\
										<div class="container-fluid yRange-radio-container">\
											<span class="setting-title pull-left">Y-Axis Range: </span>\
											<label class="radio-inline pull-left">\
												<input class="yAxis-radio yAxis-radio-auto" type="radio" name="' + name + '_yAxis" value="automatic">Auto Fit\
											</label>\
											<label class="radio-inline pull-left">\
												<input type="radio" class="yAxis-radio yAxis-radio-custom pull-left" name="' + name + '_yAxis" value="custom">\
												<div class="yRange-val-container">\
													<span class="pull-left">yMin</span>\
													<input type="textfield" class="y-input y-input-min" name="'+name+'_yAxisMin" data-default="'+default_yAxisMin+'" value="'+default_yAxisMin+'">\
													<span class="pull-left">yMax</span>\
													<input type="textfield" class="y-input y-input-max" name="'+name+'_yAxisMax" data-default="'+default_yAxisMax+'" value="'+default_yAxisMax+'">\
												</div>\
											</label>\
										</div>\
										<input type="hidden" name="' + name + '_plot" value="scatter">\
									</div>\
								</li>\
							');
						}

						$('<div class="point-data-link-container">\
								Germline <a class="link-point-data" href="javascript: void(0);" title="Click to group point data to single track"><i class="fa fa-unlink"></i></a> Somatic\
								<input type="hidden" name="link_point_data" value="true" />\
						   </div>\
						').insertBefore('#fusion_container');
					}
				}

//				$('#control').append('\
//							<span style="color: white;">Inner Most Plot: </span>\
//							<select name="inner_placement">\
//							' + placement_options + '\
//							</select>\
//						');
				//console.log(circos_data);

				var point_data_found = 0;
				$.each(circos_data, function( index, value ) {
					if(value.name == 'Somatic' || value.name == 'Germline') ++point_data_found;
				});

				if(point_data_found == 2){
					$('.point-data-link-container').show();
				}

				$(".plot_histo, .plot_line").on("click", function(){
					if ($(this).hasClass('plot_line')){
						$(this).closest('.container-fluid').siblings('.dip_contrast').hide();
					}else{
						$(this).closest('.container-fluid').siblings('.dip_contrast').show();
					}
				});

				$(".yAxis-radio").on("change", function(){
					if($(this).val().localeCompare("custom") === 0){
						$(this).closest(".container-fluid").find(".y-input").prop('disabled', false);
						var yAxisMin = $(this).closest(".container-fluid").find(".y-input-min").data("default");
						var yAxisMax = $(this).closest(".container-fluid").find(".y-input-max").data("default");
						$(this).closest(".container-fluid").find(".y-input-min").val(yAxisMin);
						$(this).closest(".container-fluid").find(".y-input-max").val(yAxisMax);
					}else{
						$(this).closest(".container-fluid").find(".y-input").prop('disabled', true);
						$(this).closest(".container-fluid").find(".y-input").val("");
					}
				});

				var palette = get_colors();

				function get_palette(index, palette){
					return palette[index];
				}

				$('.color-select').colorPicker({
					customBG: '#222',
					margin: '4px -2px 0',
					doRender: 'div div',
					opacity: false,

					buildCallback: function($elm) {
						var colorInstance = this.color,
								colorPicker = this,
								random = function(n) {
									return Math.round(Math.random() * (n || 255));
								};

//						var i = 0;
//						while(palette.length){
//
//						}

						$elm.append('<div class="cp-memory" title="Double click to change">' +
										'<div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><br/>' +
										'<div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><br/>' +
										'<div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div>' +
									'</div>').
						on('click', '.cp-memory div', function(e) {
							var $this = $(this);

							if (this.className) {
								$this.parent().prepend($this.prev()).children().eq(0).
								css('background-color', '#' + colorInstance.colors.HEX);
							} else {
								colorInstance.setColor($this.css('background-color'));
								colorPicker.render();
							}
						}).find('.cp-memory div').each(function(index) {
							!this.className && $(this).css({background: get_palette(index, palette)
							});
						});
					},

					cssAddon: // could also be in a css file instead
					'.cp-memory {margin-bottom:6px; clear:both;}' +
					'.cp-xy-slider:active {cursor:none;}' +
					'.cp-memory div {float:left; width:17px; height:17px; margin: 2px 0 2px 4px;}' +
					'.cp-memory div:nth-of-type(1), .cp-memory div:nth-of-type(9), .cp-memory div:nth-of-type(17){margin-left: 0;}' +
					'background:rgba(0,0,0,1); text-align:center; line-height:17px;}' +
					'.cp-memory .cp-store {width:21px; margin:0; background:none; font-weight:bold;' +
					'box-sizing:border-box; border: 1px solid; border-color: #666 #222 #222 #666;}'
				});

				$('[data-toggle="tooltip"]').tooltip();

				$('#tracks').css('max-height',($(window).height()*.50));
				var toggle = true;
				$('.detach').on('click', function() {
					var config_container = $('.config_container');
					if (toggle) {
						config_container.draggable('enable');
						config_container.css({'position': 'relative', 'border-radius': '5px 5px 5px 5px', 'pointer' : 'default'});
						config_container.addClass('grabbable');
						$(this).attr('title','Anchor to Window');
						$(this).children('i').removeClass('fa-arrows').addClass('fa-anchor');
						toggle = false;
					}else{
						config_container.draggable('disable');
						config_container.css('height', config_container.height());
						config_container.css({'position': 'fixed', 'border-radius': '0 5px 0 0', 'left' : '0', 'bottom' : '0', 'top' : '', 'height' : 'auto'});
						config_container.removeClass('grabbable');
						$(this).attr('title','Detach');
						$(this).children('i').removeClass('fa-anchor').addClass('fa-arrows');
						toggle =  true;
					}
				});
				//$('.config_container').css('max-height',($('#control > div').height()+200));

				$.ajax({
					url:  '{{url('/getCytobandData')}}',
					async: true,
					dataType: 'text',
					success: function(d) {

						var cytoband_data = JSON.parse(d);

						var cdata = circos_data;

                        $(".active_chkbx, .tier_chkbx").on('change', function (){
                            if ($(this).is(':checked')){
                                $(this).attr('value', 'true');
                            }else {
                                $(this).attr('value', 'false');
                            }
                        });

						// default selection
						$(".active_chkbx, .plot_histo, .yAxis-radio-custom").prop('checked', true);
						$("#placement option:eq(1)").prop('selected', true);
						$(".tier_chkbx:eq(0), .tier_chkbx:eq(1)").prop('checked', true);
						var settings = JSON.stringify( $('#control').serializeArray() );

						// todo: add cytoband genome data
						circos_render(cdata, settings);

						// create download link
						$(".config_container h3").after("\
							<a\
								id=\"download_svg\"\
								class=\"download\"\
								href=\"javascript:(function () {var e = document.createElement('script'); if (window.location.protocol === 'https:') { e.setAttribute('src', '/sandbox2/public/js/svg-crowbar.js'); } else { e.setAttribute('src', '/sandbox2/public/js/svg-crowbar.js'); } e.setAttribute('class', 'svg-crowbar'); document.body.appendChild(e); })();\">\
								<i class=\"fa fa-download\" aria-hidden=\"true\"></i> Download SVG\
							</a>\
                        ");

//						$(".config_container h3").after("\
//						<i class=\"fa fa-download\" aria-hidden=\"true\"></i> Download(\
//							<a\
//								id=\"download_svg\"\
//								class=\"download\"\
//								href=\"javascript:(function () {var e = document.createElement('script'); if (window.location.protocol === 'https:') { e.setAttribute('src', '/sandbox2/public/js/svg-crowbar.js'); } else { e.setAttribute('src', '/sandbox2/public/js/svg-crowbar.js'); } e.setAttribute('class', 'svg-crowbar'); document.body.appendChild(e); })();\">\
//								<i class=\"fa fa-file-code-o\" aria-hidden=\"true\"></i> SVG\
//							</a>\
//							<a id=\"download_pdf\" href=\"javascript: download_pdf();\"><i class=\"fa fa-file-pdf-o\" aria-hidden=\"true\"></i> PDF</a>)\
//                        ");

						$('#control').change(function() {
							circos_update(cdata);
						});

						$('.link-point-data').on("click", function(){
							if ($(this).children('i').hasClass('fa-unlink')){
								$(this).children('i').removeClass('fa-unlink').addClass('fa-link');
								$("input[name='link_point_data']").val('true');
								circos_update(cdata);
							}else{
								$(this).children('i').removeClass('fa-link').addClass('fa-unlink');
								$("input[name='link_point_data']").val('false');
								circos_update(cdata);
							}
						});

						$('.color-input').each(function () {
							var default_color = $(this).val();
							$(this).parent().find('.color-well').css('background-color', default_color);
						});
						$("body").click(function(event) {

							if ( !$("input[name='Somatic_active']").is(':checked') || !$("input[name='Germline_active']").is(':checked') ){
								$('.point-data-link-container').hide();
								$('.link-point-data').children('i').removeClass('fa-link').addClass('fa-unlink');
								$("input[name='link_point_data']").val('false');
							}else{
								if ($('.config-hide > i').hasClass('fa-window-maximize')){
									$('.point-data-link-container').hide();
								}else{
									$('.point-data-link-container').show();
								}
							}

							if ($(event.target).parents(".cp-color-picker").length > 0){
								$('.color-select').each(function (i,e) {
									var color = $(this).find('.color-well').css('background-color');
									$(this).children('.color-input').val(color);
								});

								circos_update(cdata);
							}
						});

						$("#tracks").sortable({
							update: function( event, ui ) {
								circos_update(cdata);
							}
						});
						$("#tracks").addClass("grabbable");



						// if we want the legend hidden until download
//						$("#download_svg").on("click", function(){
//							$('.legend').show(0).delay(50).hide(0);
//						});
					}
				});

			}
		});

	});

	function circos_update (cdata) {
		var settings = JSON.stringify( $('#control').serializeArray() );
		$('#biocircos svg').remove();
		circos_render(cdata, settings);
	}

	function circos_prepare_histogram(histogram_data, track_num, primary_color, yAxisRange, is_diploid){

		//var name = cdata.name;
		var radius = default_radii;
		//var track = [];
		//cdata = cdata.data;

//		var histogram_data = [];
//		var histogram_max_val = parseInt(yAxisRange[1]);
//		for (var key in cdata){
//			var tmp_obj = {
//				chr: cdata[key][0].replace("chr", ""),
//				start: parseInt(cdata[key][1]),
//				end: parseInt(cdata[key][2]),
//				name: name,
//				value: ((yAxisRange === false) ?
//							parseInt(cdata[key][3]) :
//							((parseInt(cdata[key][3]) <= histogram_max_val) ? parseInt(cdata[key][3]) : histogram_max_val) // anything greater than yRangeMax is made equal to yRangeMax
//						)
//			};
//
//			if (diploid_setting.localeCompare("y") === 0){
//				if (cdata[key][4].localeCompare("Y") === 0){
//					histogram_data.push(tmp_obj);
//				}
//			}else if (diploid_setting.localeCompare("n") === 0) {
//				if (cdata[key][4].localeCompare("N") === 0) {
//					histogram_data.push(tmp_obj);
//				}
//			}else{
//				histogram_data.push(tmp_obj);
//			}
//		}

		var HISTOGRAM = [
			"HISTOGRAM" + track_num,
			{
				maxRadius: radius[track_num][0],
				minRadius: radius[track_num][1],
				yAxisRange: yAxisRange, // if false auto fit range else use [yMin,yMax]
				histogramFillColor: primary_color
			},
			histogram_data
		];

		if(!is_diploid) {

			var BACKGROUND = [
				"BACKGROUND" + track_num,
				{
					BginnerRadius: radius[track_num][1],
					BgouterRadius: radius[track_num][0],
					BgFillColor: "#EEEEEE",
					BgborderColor: "#000",
					BgborderSize: 0.3
				}
			];

		}else{

			var BACKGROUND = [
				"BACKGROUND" + track_num,
				{
					BginnerRadius: radius[track_num][1],
					BgouterRadius: radius[track_num][0],
					BgFillColor: "transparent",
					BgborderColor: "transparent",
					BgborderSize: 0
				}
			];

		}

		return [HISTOGRAM, BACKGROUND];
	}

	function genericSort(index, array) {array.sort(function(a,b){return a[index] < b[index]})}

	function circos_prepare_line(cdata, track_num, primary_color, yAxisRange){

		var name = cdata.name;
		var radius = default_radii;
		var track = [];
		cdata = cdata.data;

		var line_data = [];
		var line_max_val = parseInt(yAxisRange[1]);
		for (var key in cdata) {
			var times = 0;
			while(times < 2) {
				// we want to plot both start and end so the line is complete
				var pos = parseInt(cdata[key][times + 1]);
				line_data.push({
					chr: cdata[key][0].replace("chr", ""),
					pos: pos,
					value: ((yAxisRange === false) ?
								parseInt(cdata[key][3]) :
								((parseInt(cdata[key][3]) <= line_max_val) ? parseInt(cdata[key][3]) : line_max_val) // anything greater than yRangeMax is made equal to yRangeMax
							)
				});

				times++;
			}

		}

		var LINE01 = [
			"LINE" + track_num,
			{
				maxRadius: radius[track_num][0],
				minRadius: radius[track_num][1],
				yAxisRange: yAxisRange, // if false auto fit range else use [yMin,yMax]
				LineColor: primary_color,
				LineWidth: 1
			},
			line_data
		];

		var BACKGROUND01 = [
			"BACKGROUND" + track_num,
			{
				BginnerRadius: radius[track_num][1],
				BgouterRadius: radius[track_num][0],
				BgFillColor: "#EEEEEE",
				BgborderColor : "#000",
				BgborderSize : 0.3
			}];

		return [LINE01, BACKGROUND01];
	}

	function circos_prepare_snp(cdata, track_num, primary_color, yAxisRange, exclude_background){

		var name = cdata.name;
		var radius = default_radii;
		var track = [];
		cdata = cdata.data;

		var scatter_data = [];
		for (var key  in cdata) {
			scatter_data.push({
				chr: cdata[key][0].replace("chr", ""),
				pos: Math.round((parseInt(cdata[key][1]) + parseInt(cdata[key][2]))/2),
				value: cdata[key][4],
				des: cdata[key][3]
			});
		}

		var SNP01 = [
			"SNP" + track_num,
			{
				maxRadius: radius[track_num][0] - 3, // offset circle size
				minRadius: radius[track_num][1] + 3,
				yAxisRange: yAxisRange, // if false auto fit range else use [yMin,yMax]
				SNPFillColor: primary_color,
				circleSize: 3,
				displaySNPAxis: false,
				SNPAxisColor: "#B8B8B8",
				SNPAxisWidth: 0.5
			},
			scatter_data
		];

		if (exclude_background){
			var BACKGROUND01 = [
				"BACKGROUND" + track_num,
				{
					BginnerRadius: radius[track_num][1],
					BgouterRadius: radius[track_num][0],
					BgFillColor: "transparent",
					BgborderColor : "transparent",
					BgborderSize : 0
				}];
		}else{
			var BACKGROUND01 = [
				"BACKGROUND" + track_num,
				{
					BginnerRadius: radius[track_num][1],
					BgouterRadius: radius[track_num][0],
					BgFillColor: "#EEEEEE",
					BgborderColor : "#000",
					BgborderSize : 0.3
				}];
		}


		return [SNP01,BACKGROUND01];
	}

	function circos_prepare_fusion(link_tmp_raw, type, tiers_active, tiers_idx,  track_num){

		var radius = default_radii;
		var fusion_data =[];

		var tier = (link_tmp_raw[7].replace('Tier ',''));

		if ($.inArray(String(tier), tiers_active) !== -1) {
			var colors = get_colors(true);
			var color = colors[tiers_idx.indexOf(tier)];
			if (type == "all") {
				build_fusion_link_array(fusion_data, link_tmp_raw);
			}else{
				if (type == link_tmp_raw[6]){
					build_fusion_link_array(fusion_data, link_tmp_raw);
				}
			}
		}

		var LINK01 = [
			"LINK" + track_num,
			{
				LinkRadius: radius[track_num-1][1],
				LinkFillColor: color,
				LinkWidth: 1,
				displayLinkAxis: false,
				LinkAxisColor: "grey",
				LinkAxisWidth: 0.5,
				LinkAxisPad: 0,
				displayLinkLabel: true,
				LinkLabelColor: color,
				LinkLabelSize: 13,
				LinkLabelPad: 50 + (30 * (track_num))
			},
			fusion_data
		];

		return [LINK01];
	}

	function build_fusion_link_array (fusion_data, link_tmp_raw){

		var link_tmp = {
			fusion: link_tmp_raw[2] + '\uf07e' + link_tmp_raw[5], // \uf178 is right arrow
			g1chr: link_tmp_raw[0].replace("chr", ""),
			g1start: link_tmp_raw[1],
			g1end: link_tmp_raw[1],
			g1name: link_tmp_raw[2],
			g2chr: link_tmp_raw[3].replace("chr", ""),
			g2start: link_tmp_raw[4],
			g2end: link_tmp_raw[4],
			g2name: link_tmp_raw[5]
		};

		fusion_data.push(link_tmp);

	}

	// TODO: use cytoband function from original to allow for different genomes to be used
	var BioCircosGenome = [
		["1" , 249250621],
		["2" , 243199373],
		["3" , 198022430],
		["4" , 191154276],
		["5" , 180915260],
		["6" , 171115067],
		["7" , 159138663],
		["8" , 146364022],
		["9" , 141213431],
		["10" , 135534747],
		["11" , 135006516],
		["12" , 133851895],
		["13" , 115169878],
		["14" , 107349540],
		["15" , 102531392],
		["16" , 90354753],
		["17" , 81195210],
		["18" , 78077248],
		["19" , 59128983],
		["20" , 63025520],
		["21" , 48129895],
		["22" , 51304566],
		["X" , 155270560],
		["Y" , 59373566]
	];


	function circos_configure (circos_data, settings) {

		settings = $.parseJSON(settings);
		circos_data = order_tracks(circos_data);

		var track_num = 1;
		var circos_tracks = [];
		for (var data in circos_data) {

			var name = circos_data[data].name;
			var data_type = circos_data[data].data_type;
			var cdata = circos_data[data];
			var mcdata = 0;

			if (data_type.indexOf("span") >= 0 || data_type.indexOf("points") >= 0){

				for (var setting in settings) {

					if (settings.hasOwnProperty(setting)) {

						var setting_name = settings[setting].name;

						// is chart on
						if (setting_name == name + '_active') {

							// true is not bool
							if (settings[setting].value == 'true') {

								var primary_color = settings.filter(function (item) {
									return item.name === name + '_color';
								});

								primary_color = rgb2hex(primary_color[0].value);

								var chart_type = settings.filter(function (item) {
									return item.name === name + '_plot';
								});

								var diploid_setting = settings.filter(function (item) {
									return item.name === name + '_diploid';
								});

								try{
									diploid_setting = diploid_setting[0].value;
								}catch(e){}

								var yAxis_setting = settings.filter(function (item) {
									return item.name === name + '_yAxis';
								});

								var yAxisRange = false;
								if (yAxis_setting.length > 0) {

									if (yAxis_setting[0].value.localeCompare("custom") === 0) {

										var yAxisMin = settings.filter(function (item) {
											return item.name === name + '_yAxisMin';
										});

										var yAxisMax = settings.filter(function (item) {
											return item.name === name + '_yAxisMax';
										});

										yAxisRange = [parseInt(yAxisMin[0].value), parseInt(yAxisMax[0].value)];
									}
								}

								var link_point_data = settings.filter(function (item) {
									return item.name === 'link_point_data';
								});
								try{
									link_point_data = link_point_data[0].value;
								}catch(e){}

								if (chart_type[0].value == 'histogram') {
									
									cdata = cdata.data;

									var histogram_data = [];
									var diploid_data = [];
									var histogram_max_val = parseInt(yAxisRange[1]);
									for (var key in cdata){
										var tmp_obj = {
											chr: cdata[key][0].replace("chr", ""),
											start: parseInt(cdata[key][1]),
											end: parseInt(cdata[key][2]),
											name: name,
											value: ((yAxisRange === false) ?
															parseInt(cdata[key][3]) :
															// todo: I think this needs to be done for the lower bound too
															((parseInt(cdata[key][3]) <= histogram_max_val) ? parseInt(cdata[key][3]) : histogram_max_val) // anything greater than yRangeMax is made equal to yRangeMax
											)
										};

										//html if want to re-implement
//										<select class="diploid-select" name="'+ name +'_diploid">\
//											<option value="all">Visible</option>\
//											<option value="y">Only</option>\
//											<option value="n">Hidden</option>\
//										</select>\
//										switch(diploid_setting){
//											case 'y':
//												if (cdata[key][4].localeCompare("Y") === 0){
//													diploid_data.push(tmp_obj);
//												}
//												break;
//											case 'n':
//												if (cdata[key][4].localeCompare("N") === 0) {
//													histogram_data.push(tmp_obj);
//												}
//												break;
//											default:
//												if(cdata[key][4].localeCompare("Y") === 0){
//													diploid_data.push(tmp_obj);
//												}else{
//													histogram_data.push(tmp_obj);
//												}
//												break;
//										}

										if (diploid_setting == "true"){
											if(cdata[key][4].localeCompare("Y") === 0){
												diploid_data.push(tmp_obj);
											}else{
												histogram_data.push(tmp_obj);
											}
										}else{
											histogram_data.push(tmp_obj);
										}
									}

									if(diploid_data.length > 0) {
										// found a bug here, because diploids are all the same value the MaxMin in the plugin is 2,2 which won't work to graph
										// fix: I am running the calculation to get the auto MaxMin from the plugin and applying it to the diploid_data
										// which will come out to be the same as the auto values found by the plugin
										var yAxisRangeFix = getMaxMin(histogram_data);
										circos_tracks.push(circos_prepare_histogram(diploid_data, track_num, '#CCC',yAxisRangeFix, true));
									}
									circos_tracks.push(circos_prepare_histogram(histogram_data, track_num, primary_color, yAxisRange));

								} else if (chart_type[0].value == 'line') {

									circos_tracks.push(circos_prepare_line(cdata, track_num, primary_color, yAxisRange));

								} else if (chart_type[0].value == 'scatter') {

									if (link_point_data == 'true'){
										if (typeof snp_track_num === 'undefined') {
											var snp_track_num = track_num;
											--track_num;
											circos_tracks.push(circos_prepare_snp(cdata, snp_track_num, primary_color, yAxisRange));
										}else{
											circos_tracks.push(circos_prepare_snp(cdata, snp_track_num, primary_color, yAxisRange, true));
										}
									}else{
										var snp_track_num = track_num;
										circos_tracks.push(circos_prepare_snp(cdata, snp_track_num, primary_color, yAxisRange));
									}

								}
								++track_num;
							}
						}
					}
				}

			}else{
				var type = settings.filter(function (item) {
					return item.name === 'type';
				});
				type = type[0].value;

				var tiers_active = [];
				for(var idx in settings){
					if (settings[idx].name.includes("tier_")) {
						tiers_active.push(settings[idx].name.replace('tier_',''));
					}
				}
				var tiers_idx = [];
				$('.fusion_legend_tier').each(function(i,e){
					tiers_idx.push($(e).children('span').text().replace('Tier ', ''));
				});

				if(tiers_active.length > 0){
					for (var link in cdata.data){
						var link_tmp_raw = cdata.data[link];
						var tier = link_tmp_raw[7].replace('Tier ','');
						if ($.inArray(tier,tiers_active) != -1){
							circos_tracks.push(circos_prepare_fusion(link_tmp_raw, type, tiers_active, tiers_idx, track_num));
						}
					}
				}
			}
		}
		//settings.push({name: "fusionLabelOffset", value: {track_count: track_num, track_width: track_width, track_padding: track_padding}});
		return circos_tracks;
	}

//	function diploid_alter(cdata, name, settings) {
//
//		var mcdata = cdata;
//
//		var diploid_setting = settings.filter(function (item) {
//			return item.name === name + '_diploid';
//		});
//
//		if (diploid_setting[0].value.localeCompare("all") === 0) {
//
//			return 0;
//
//		}else if (diploid_setting[0].value.localeCompare("y") === 0){
//			// remove n's
//			for(var index in mcdata.data){
//				if (mcdata.data[index][4].localeCompare("N") === 0) {
//					if (index > -1) {
//						mcdata.data.splice(index, 1);
//					}
//					console.log("hit");
//				}
//			}
//
//			return mcdata;
//
//		}else if(diploid_setting[0].value.localeCompare("n") === 0) {
//			// remove y's
//			for (var index in mcdata.data) {
//				if (mcdata.data[index][4].localeCompare("Y") === 0) {
//					if (index > -1) {
//						mcdata.data.splice(index, 1);
//					}
//				}
//			}
//
//			return mcdata;
//
//		}
//	}

	function get_radius_defaults () {

		var track_radii = [];

		var i=1;
		while (i <= supported_tracks) {			
			if (i === 1){
				track_radii.push([outerRadius,innerRadius]);
			}else{				
				track_radii.push([outerRadius - ( (track_width+track_padding) * (i-1) ), innerRadius - ( (track_width+track_padding) * (i-1) )]);
			}			
			i++;
		}
		return track_radii;
	}

	function get_colors (is_fusion) {
		// pastels
//		var colors = ['#80ffff', '#80f5ff', '#80daff', '#80b0ff',
//					  '#8080ff', '#b080ff', '#da80ff', '#f580ff',
//					  '#ff80ff', '#ff80f5', '#ff80da', '#ff80b0',
//					  '#ff8080', '#ffb080', '#ffda80', '#fff580',
//					  '#ffff80', '#f5ff80', '#daff80', '#b0ff80',
//					  '#80ff80', '#62FC82', '#80ffb0', '#80ffda']; //, '#80fff5'];

		var colors = ['#f44336', '#e91e63', '#9c27b0', '#673ab7', '#3f51b5',
					  '#2196f3', '#03a9f4', '#00bcd4', '#009688', '#4caf50',
					  '#8bc34a', '#cddc39', '#ffeb3b', '#ffc107', '#ff9800',
					  '#ff5722', '#795548', '#9e9e9e', '#607d8b', '#374046'];

		if (is_fusion){

			// pastels
			colors = ['#86d868', '#fb8536', '#e53c7a', '#3b87bd', '#da80ff', '#9ca4b2', '#4e4e56'];

//			colors = ['#4caf50', '#2196f3', '#ff9800', '#e91e63', '#9c27b0', '#9e9e9e', '#374046'];

			return colors;
		}

		return colors;
	}

	function circos_render(circos_data, settings){

		var circos_params = [];

		var circos_tracks = circos_configure(circos_data, settings);

		var circos_layout = {
			target : "biocircos",
			svgWidth : svgWidth,
			svgHeight : svgHeight,
			chrPad : .035,
			innerRadius : innerRadius,
			outerRadius :outerRadius,
			showLegend : true,
			zoom : true,
			ticks : {
				"display" : true,
				"len" : 5,
				"color" : "#666",
				"textSize" : "11px",
				"textColor" : "#666",
				"scale" : 1000000,
				"interval" : 20,
				"suffix" : ""
			},
			genomeLabel : {
				"display" : true,
				"textSize" : "15px",
				"textColor" : "#333",
				"textWeight" : "bold",
				"orientUpright" : true,
				"dx" : 0,
				"dy" : 5
			},
			HISTOGRAMMouseOutDisplay : true,
			HISTOGRAMMouseOutColor : "",
			HISTOGRAMMouseOverDisplay : true,
			HISTOGRAMMouseOverColor : "",
			HISTOGRAMMouseOverOpacity : .4,
			HISTOGRAMMouseOverStrokeColor : "none",
			HISTOGRAMMouseOverStrokeWidth : "none",
			HISTOGRAMMouseOverTooltipsHtml01 : "chr :",
			HISTOGRAMMouseOverTooltipsHtml02 : "<br>start : ",
			HISTOGRAMMouseOverTooltipsHtml03 : "<br>end : ",
			HISTOGRAMMouseOverTooltipsHtml04 : "<br>name : ",
			HISTOGRAMMouseOverTooltipsHtml05 : "<br>value : ",
			HISTOGRAMMouseOverTooltipsPosition : "absolute",
			HISTOGRAMMouseOverTooltipsBackgroundColor : "#000",
			HISTOGRAMMouseOverTooltipsBorderStyle : "solid",
			HISTOGRAMMouseOverTooltipsBorderWidth : 0,
			HISTOGRAMMouseOverTooltipsPadding : "3px",
			HISTOGRAMMouseOverTooltipsBorderRadius : "5px",
			HISTOGRAMMouseOverTooltipsOpacity : 0.75,
			SNPMouseOverDisplay : true,
			SNPMouseOverColor : "none",
			SNPMouseOverCircleSize : 5,
			SNPMouseOverCircleOpacity : .75,
			SNPMouseOverCircleStrokeColor : "#000",
			SNPMouseOverCircleStrokeWidth : 0,
			SNPMouseOverTooltipsHtml01 : "chr : ",
			SNPMouseOverTooltipsHtml02 : "<br>position : ",
			SNPMouseOverTooltipsHtml03 : "<br>value : ",
			SNPMouseOverTooltipsHtml04 : "<br>name : ",
			SNPMouseOverTooltipsBackgroundColor : "#000",
			SNPMouseOverTooltipsBorderStyle : "solid",
			SNPMouseOverTooltipsBorderWidth : 0,
			SNPMouseOverTooltipsPadding : "3px",
			SNPMouseOverTooltipsBorderRadius : "5px",
			SNPMouseOutDisplay : true,
			SNPMouseOutAnimationTime : 700,
			SNPMouseOutColor : "none",
			SNPMouseOutCircleSize : "none",
			SNPMouseOutCircleOpacity : 1.0,
			SNPMouseOutCircleStrokeWidth : 0
		};

		for (var track in circos_tracks) {
			if (circos_tracks.hasOwnProperty(track)) {
				for (var track_part in circos_tracks[track]) {
					circos_params.push(circos_tracks[track][track_part]);
				}
			}
		}

//		for (var track in histograms) {
//			if (histograms.hasOwnProperty(track)) {
//				circos_params.push(histograms[track]);
//			}
//		}

		circos_params.push(get_legend());
		circos_params.push(ARC_hg19);		// todo: this is dynamic in the future based on genome
		circos_params.push(BioCircosGenome); // genome data has to be second from last
		circos_params.push(circos_layout);   // these settings have to be last

		BioCircos01 = new BioCircos(circos_params);
		BioCircos01.draw_genome(BioCircos01.genomeLength);

		//$(".legend").css("display","none"); // if we want it hidden until download
	}

	function rgb2hex(rgb) {

		if (rgb.indexOf("rgb") >= 0){
			rgb = rgb.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
			function hex(x) {
				return ("0" + parseInt(x).toString(16)).slice(-2);
			}
			return "#" + hex(rgb[1]) + hex(rgb[2]) + hex(rgb[3]);
		}
		return rgb;
	}

	function get_legend(circos_params) {

		var LEGEND = ["LEGEND",[]];
		$(".setting_container").each(function(){
			var title = $(this).children("h4").text();
			var color = $(this).find(".color-input").val();
			var description = $(this).find(".tip-desc").data("original-title");
			var active =$(this).find("input[name="+$.trim(title)+"_active]").val();
			if(active === "true"){
				LEGEND[1].push([title,color, description]);
			}
		});

		return LEGEND;
	}


	function order_tracks(circos_data) {
		var track_order = get_track_order();
		var circos_data_tmp = [];
		for (var track in track_order){
			var track_obj = circos_data.filter(function (obj) {
				return obj.name === track_order[track];
			});
			circos_data_tmp.push(track_obj[0]);
		}
		var track_obj = circos_data.filter(function (obj) {
			return obj.data_type === "links";
		});
		if (typeof track_obj[0] !== 'undefined'){
			circos_data_tmp.push(track_obj[0]);
		}

		return circos_data_tmp;
	}

	function get_track_order() {
		var track_order = [];
		$('.setting_container').each(function(i,e){
			track_order.push($(e).children('h4').text().trim());
		});
		return track_order;
	}

	function unique(array){
		return array.filter(function(el, index, arr) {
			return index == arr.indexOf(el);
		});
	}

	function toTitleCase(str)
	{
		return str.replace(/\w\S*/g, function(txt){return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();});
	}

	function getMaxMin(histogram_data){
		var i=histogram_data.length;
		var histogramValueList = new Array();
		for(var k=0;k<i;k++){
			histogramValueList[k]=histogram_data[k].value;
		}
		Array.max=function(array){
			return Math.max.apply(Math,array);
		}
		Array.min=function(array){
			return Math.min.apply(Math,array);
		}
		var histogramValueMax = Array.max(histogramValueList);
		var histogramValueMin = Array.min(histogramValueList);

		return [histogramValueMin, histogramValueMax];
	}


//	function download_pdf(){
//		// I recommend to keep the svg visible as a preview
//		var svg = $('#biocircos > svg').get(0);
//		var pdf = new jsPDF('p', 'pt', [svgWidth, svgHeight]);
//		svgElementToPdf(svg, pdf, {
//			scale: 72/96, // this is the ratio of px to pt units
//			removeInvalid: true // this removes elements that could not be translated to pdf from the source svg
//		});
//		pdf.output('datauri'); // use output() to get the jsPDF buffer
//	}

//	function rgb2hex(rgb){
//		rgb = rgb.match(/^rgba?[\s+]?\([\s+]?(\d+)[\s+]?,[\s+]?(\d+)[\s+]?,[\s+]?(\d+)[\s+]?/i);
//		return (rgb && rgb.length === 4) ? "#" +
//		("0" + parseInt(rgb[1],10).toString(16)).slice(-2) +
//		("0" + parseInt(rgb[2],10).toString(16)).slice(-2) +
//		("0" + parseInt(rgb[3],10).toString(16)).slice(-2) : '';
//	}


//	function circos_render (cytoband_data, circos_data, config) {
//
//		var instance = circos_prepare_layout(cytoband_data);
//
//        // TODO: you are here
//        // parse config maybe
//        config = JSON.parse(config);
//
//        //console.log(config);
//
//        var offset = 0;
//		for (var property in circos_data) {
//
//			if (circos_data.hasOwnProperty(property)) { // might not need this condition
//                var cdata = null;
//				cdata = circos_data[property];
//				cdata['name'] = property;
//
//                for (var setting in config) {
//                    if (config.hasOwnProperty(setting)) {
//
//                        // is chart on
//                        if (config[setting]['name'] == property + '_active'){
//                            // true is not bool
//                            if (config[setting]['value'] == 'true'){
//
//                                var chart_type = config.filter(function(item) { return item.name === property + '_plot'; });
//
//                                if (chart_type[0].value == 'histogram'){
//
//                                    circos_prepare_histogram(instance, cdata, offset);
//
//                                } else if (chart_type[0].value == 'line'){
//
//                                    circos_prepare_line(instance, cdata, offset);
//
//                                } else if (chart_type[0].value == 'scatter'){
//
//                                    circos_prepare_scatter(instance, cdata, offset);
//
//                                }
//
//                                ++offset;
//                            }
//
//                        }
//                    }
//                }
//			}
//		}
//
//
//		instance.render();
//	}



//	function print_my_arguments(/**/){
//		var args = arguments;
//		for(var i=0; i<args.length; i++){
//			console.log(args[i]);
//		}
//	};

	//		var colors =   ['#51574a', '#8e8c6d', '#447c69',
	//						'#74c493', '#e9d78e', '#e4bf80',
	//						'#e2975d', '#f19670', '#e16552',
	//						'#c94a53', '#be5168', '#a34974',
	//						'#993767', '#65387d', '#4e2472',
	//						'#9163b6', '#e279a3', '#e0598b',
	//						'#7c9fb0', '#5698c4', '#9abf88'];

	//		var colors =   ['#07040d', '#33282c', '#5a4c3f',
	//						'#7e4949', '#a64027', '#d94c3a',
	//						'#fe5f55', '#8c9999', '#d7dddb',
	//						'#f9f0e7', '#51783d', '#60824f',
	//						'#8f9f51', '#b1b856', '#dce87f',
	//						'#ebbd50', '#e8c561', '#fbdf72',
	//						'#54a992', '#63b9a4', '#8fb59c',
	//						'#1b3b7c', '#8258ce', '#b775e4',
	//						'#fac699', '#eddbc3', '#ebe7db'];

</script>
<div id='loading' style="height:98%">
	<img src='{{url('/images/ajax-loader.gif')}}'></img>
</div>
{{--<div id='circos_plot'>--}}
	{{--<img width="700" src = '{{url('/images/circos_example.png')}}'/>--}}
{{--</div>--}}

