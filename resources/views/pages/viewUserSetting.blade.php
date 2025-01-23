@extends('layouts.default')
@section('title', "User Setting")
@section('content')

{!! HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') !!}
{!! HTML::style('packages/jquery-easyui/themes/bootstrap/easyui.css') !!}

{!! HTML::script('js/bootstrap.min.js') !!}
{!! HTML::script('packages/jquery-easyui/jquery.easyui.min.js') !!}
{!! HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') !!}

<meta name="csrf-token" content="{{ csrf_token() }}">

<script type="text/javascript">
	var gene_list = {!!$gene_list!!};
	var old_list_name = "";
	var user_id = '{!!$user_id!!}';
	var user_name = '{!!$user_name!!}';
	users=["1361","1602","81"];

	$(document).ready(function() {
		if (Object.keys(gene_list).length == 0) {
			//gene_list["untitled"] = ['', '' , 'Y', 'germline'];
			$('#btnEdit').addClass('disabled');
			$('#btnRemove').addClass('disabled');

			
		}
		console.log(user_id);
		for (list_name in gene_list)
			$('#gene_list').append(new Option(list_name));
		$("#gene_list")[0].selectedIndex = 0;
		changeList();		

		$( "#gene_list").change(function() {			
			changeList();
		});

		$( "#gene_list").dblclick(function() {			
			list_name = $("#gene_list option:selected").val();
			showPopup(list_name, gene_list[list_name][0], gene_list[list_name][1], gene_list[list_name][2], gene_list[list_name][3], gene_list[list_name][5]);			
		});

		$( "#list_content").on("change keyup paste", function() {			
			//changeContent();
		});

		$("#btnNew").click(function() {
			$('#txtDesc').val();
			showPopup("", "", "", "N", "germline", user_name);
		});

		$("#btnRemove").click(function() {
			var val = $("#gene_list option:selected").val();
			delete gene_list[val];
			$("#gene_list option:selected").remove();			
			if (Object.keys(gene_list).length == 0) {
				$('#btnEdit').addClass('disabled');
				$('#btnRemove').addClass('disabled');
				//$('#list_content').val("");
				$('#lblListName').text("");
			} else {
				$("#gene_list")[0].selectedIndex = 0;
				changeList();
			}
		});

		$("#btnEdit").click(function() {
			list_name = $("#gene_list option:selected").val();
			showPopup(list_name, gene_list[list_name][0], gene_list[list_name][1], gene_list[list_name][2], gene_list[list_name][3],gene_list[list_name][5]);
		});

		$("#btnSaveProject").click(function() {	
			saveSetting('default_project', $('#selProject').val());
		});

		$("#btnSaveAnnotation").click(function() {	
			saveSetting('default_annotation', $('#selAnnotation').val());
		});

		$(".bthHighConf").click(function() {			
			var name = $(this).attr("id");
			name = name.replace(".btnSaveSystem", "");
			var config_data = {  maf : $("#" + name + "_maf").val(), 
						germline_total_cov : $("#" + name + "_germline_total_cov").val(), 
						germline_fisher : $("#" + name + "_germline_fisher").val(), 
						germline_vaf : $("#" + name + "_germline_vaf").val(), 
						somatic_panel_total_cov : $("#" + name + "_somatic_panel_total_cov").val(), 
						somatic_panel_normal_total_cov : $("#" + name + "_somatic_panel_normal_total_cov").val(), 
						somatic_panel_vaf : $("#" + name + "_somatic_panel_vaf").val(), 
						somatic_exome_total_cov : $("#" + name + "_somatic_exome_total_cov").val(), 
						somatic_exome_normal_total_cov : $("#" + name + "_somatic_exome_normal_total_cov").val(), 
						somatic_exome_vaf : $("#" + name + "_somatic_exome_vaf").val() 
					};
			var attr_id = "high_conf";
			if (name != "Khanlab")
				attr_id = "high_conf_" + name.toLowerCase();
			var url = '{!!url("/saveSystemSetting/")!!}' + '/' + attr_id;
			console.log(url);
			console.log(JSON.stringify(config_data));
			//return;

			$.ajaxSetup({
			  headers: {
				    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			  }
			});

			$.ajax({ url: url, async: true, type: 'POST', dataType: 'text', data: config_data, success: function(data) {
					if (data == "NoUserID")
						alert("Please login first!");
					else if (data == "Not admin")
						alert("Not admin!");
					else if (data == "Success")
						alert("Save successful!");
					else
						alert("Save failed: reason:" + data);
				}, error: function(xhr, textStatus, errorThrown){
					alert('save failed! Reason:' + JSON.stringify(xhr) + ' ' + errorThrown);
				}
			});			
		});

		$("#btnSave").click(function() {	
			var num_list = 0;		
			for (var list_name in gene_list) {
				num_list++;
    			if (gene_list.hasOwnProperty(list_name)) {
        			if (gene_list[list_name][0] == null || gene_list[list_name][0].trim() == "") {
        				alert("The list " + list_name + " has no genes");
        				//$("#gene_list").find("option[text='" + list_name + "']").prop("selected", true);
        				$('#gene_list').val(list_name);
        				changeList();
        				return;
        			}
    			}
			}

			//if (num_list == 0) {
			//	alert('no list found!');
			//	return;
			//}
			//var url = '{!!url("/saveSetting")!!}' + '/' + encodeURI(JSON.stringify(gene_list));
			var url = '{!!url("/saveGeneList")!!}';
			$.ajaxSetup({
			  headers: {
				    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			  }
			});
			$.ajax({ url: url, async: true, type: 'POST', dataType: 'text', data: gene_list, success: function(data) {
					if (data == "NoUserID")
						alert("Please login first!");
					else if (data == "Success")
						alert("Save successful!");
					else
						alert("Save failed: reason:" + data);
				}, error: function(xhr, textStatus, errorThrown){
					alert('save failed! Reason:' + JSON.stringify(xhr) + ' ' + errorThrown);
				}
			});			
		});

		$("#btnOK").click(function() {
			var list_name = $('#txtListName').val().trim();
			if (list_name == "")
				alert("Please input gene list name!");	
			else {								
				if (old_list_name == "") {
					if (gene_list.hasOwnProperty(list_name)) {
						alert("List " + list_name + " already exists");
						return;
					}
					$('#gene_list').append(new Option(list_name));
					gene_list[list_name] = ['','','','','',''];	
					$('#gene_list option').last().prop('selected',true);
					changeList();
					$('#btnEdit').removeClass('disabled');
					$('#btnRemove').removeClass('disabled');
					gene_list[list_name][4] = user_id;
					gene_list[list_name][5] = user_name;
				} else {
					if (old_list_name != list_name) {
						if (gene_list.hasOwnProperty(list_name)) {
							alert("List " + list_name + " already exists");
							return;
						}
						gene_list[list_name] = gene_list[old_list_name];						
						delete gene_list[old_list_name];
						$("#gene_list option:selected").text(list_name);
						changeList();
					}

				}
				gene_list[list_name][0] = $('#list_content').val();
				gene_list[list_name][1] = $('#txtDesc').val();
				gene_list[list_name][2] = ($('#ckPublic').is(':checked'))? 'Y':'N';
				gene_list[list_name][3] = $('#selType').val();
				$('#popwindow').window('close');
			}
		});

		$('#txtListName').keyup(function(e){
    		if(e.keyCode == 13) {
    			$('#btnOK').trigger("click");        		
    		}
		});

		$('#btnCancel').click(function(e) {    
			$('#popwindow').window('close');
		});

		$('#localFile').on('change', function () {
    		fileChosen(this, document.getElementById('list_content'));
		});
		if(users.indexOf(user_id)!=-1){
			var html= '<a href="#" id="sync" class="btn btn-success" >Sync Clinomics</a>'
			$( "#sync_span" ).html(html);
			$("#sync").click(function() {	
				var url = '{!!url("/syncClinomics")!!}';
				console.log(url);
				alert("Clinomics is syncing");
				$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
					alert("Clinomics is finished syncing");
					}, error: function(xhr, textStatus, errorThrown){
						alert('sync failed! Reason:' + JSON.stringify(xhr) + ' ' + errorThrown);
					}
				});	
			});
		}

	});

	function saveSetting(attr_name, attr_value) {
		var url = '{!!url("/saveSettingGet")!!}' + '/' + attr_name + '/' + attr_value;
			$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
					if (data == "NoUserID")
						alert("Please login first!");
					else if (data == "Success")
						alert("Save successful!");
					else
						alert("Save failed: reason:" + data);
				}, error: function(xhr, textStatus, errorThrown){
					alert('save failed! Reason:' + JSON.stringify(xhr) + ' ' + errorThrown);
				}
		});	
	}
	function showPopup(listName, content, desc, ispublic, type, userName) {
		old_list_name = listName;		
		$('#popwindow').window({top:200});
		$('#popwindow').window('open');
		$('#txtListName').val(old_list_name);
		$('#txtDesc').val(desc);
		$("#ckPublic").prop( "checked", (ispublic == 'Y'));
		$("#selType").val(type);
		$('#selType option[value=' + type).prop('selected',true);
		//$('#txtListName').val(old_list_name);
		$('#lblUserName').text(userName);
		$('#list_content').val(content);
		$('#txtListName').focus();		
	}

	function changeContent() {
		var val = $("#gene_list option:selected").val();
		gene_list[val][0] = $('#list_content').val();
	}

	function changeList() {
		if (Object.keys(gene_list).length == 0) 
			return;
		var val = $("#gene_list option:selected").val();
		//$('#list_content').val(gene_list[val][0]);
		$('#lblListName').text(val);
	}

	function readTextFile(file, callback, encoding) {
		var reader = new FileReader();
		reader.addEventListener('load', function (e) {
			callback(this.result);
		});
		if (encoding) 
			reader.readAsText(file, encoding);
		else 
			reader.readAsText(file);
	}

	function fileChosen(input, output) {
		if (input.files && input.files[0]) {
			readTextFile(input.files[0],function (str) {
				output.value = str;
				changeContent();
			});
		}
	}	
</script>
<div id="popwindow" class="easyui-window" title="Edit gene list" data-options="top:20,inline:true,closed:true,iconCls:'icon-save',border:'thick',zIndex:99999" style="width:900px;height:700px;top:100px;padding:10px;z-index:99999">
       <div class="container-fluid">
		<div class="row">
			<div class="col-md-2">
				<BR>
           			Gene list name: 
           		</div>
           		<div class="col-md-4">
           			<BR>
           			<input class="form-control"  id='txtListName' type="text" size="35">
           		</div>
           		<div class="col-md-6">
           			Gene lists:<input name="file" type="file" id="localFile" class="form-control" value="">
           		</div>
           	</div>
           	<div class="row">
			<div class="col-md-2">
           			Description: 
           		</div>
           		<div class="col-md-4">           		
           			<textarea class="form-control" id="txtDesc" rows="18" cols="30" style="font-size:13pt;"> </textarea>
           		</div>
           		<div class="col-md-6">
           			<textarea class="form-control" id="list_content" rows="18" cols="30" style="font-size:13pt;"> </textarea>
           		</div>
           	</div>
           	<div class="row">
			<div class="col-md-2">
           			Created by:
           		</div>
           		<div class="col-md-4">
           			<div id="lblUserName" style="color:red;text-align:left;display:inline"></div>
           		</div>           		
           	</div>
           	<div class="row">
			<div class="col-md-2">
           			Public:
           		</div>
           		<div class="col-md-4">
           			<input id='ckPublic' type="checkbox">
           		</div>           		
           	</div>
           	<div class="row">
			<div class="col-md-2">
           			Type:
           		</div>
           		<div class="col-md-4">
           			<select id="selType" class="form-control">
			           					<option value="germline">Germline</option>
			           					<option value="somatic">Somatic</option>
			           					<option value="rnaseq">RNAseq</option>
			           					<option value="variants">Variants</option>
			           					<option value="fusion">Fusion</option>
			           					<option value="all">all</option>
			        </select>
           		</div>           		
           	</div>
           	<HR>
           	<div class="row">
			<div class="col-md-2">
           		</div>
           		<div class="col-md-4">
           			<a href="#" class="btn btn-primary form-control"  id="btnOK" name="btnOK">Ok</a>
           		</div>
           		<div class="col-md-6">
           			<a href="#" class="btn btn-warning"  id="btnCancel">Cancel</a>
           		</div>
           	</div>
        </div>    
</div>

<div id="out_container" class="easyui-panel" data-options="border:false" style="width:100%;height:100%;padding:5px;border-width:1px">	
	<div id="tabVar" class="easyui-tabs" data-options="tabPosition:'top',fit:true,plain:true,pill:false,border:false,headerWidth:100" style="width:100%;height:100%;padding:5px;overflow:visible;border-width:1px">
		<div title="Gene List">
			<div class="container-fluid" style="padding:10px">
				<div class="row">
					<div class="col-md-9">
						<a href="#" id="btnNew" class="btn btn-info" >New</a>
						<a href="#" id="btnRemove" class="btn btn-danger" >Remove</a>
						<a href="#" id="btnEdit" class="btn btn-warning" >Edit</a>
					</div>
					<div class="col-md-2">
						<a href="#" id="btnSave" class="btn btn-success form-control" >Save to database</a>
					</div>
				</div>
				<div class="row">
					<div class="col-md-11">
						Gene list: <div id="lblListName" style="color:red;text-align:left;display:inline"></div>
					</div>
				</div>
				<div class="row">
					<div class="col-md-11">
						<div class="styled-select">				
							<select class="form-control" id="gene_list" size="25" style="font-size:14pt;color:red;"> </select>
						</div>
					</div>
				</div>
			</div>
			<br>
			<br>
			<span id="sync_span"></span>
		</div>
		<div title="Project">
			<div class="container-fluid" style="padding:10px">
				<div class="row">
					<div class="col-md-5">
						<label for="selProject">Default Project:</label>
						<select class="form-control" id="selProject">
							<option value='any'>(Any)</option>
						@foreach ($projects as $project)
							<option value='{!!$project->id!!}' {!!($default_project == $project->id)? 'selected' : ''!!}>{!!$project->name!!}</option>
						@endforeach
						</select>
						</br>
						<a href="#" id="btnSaveProject" class="btn btn-success" >Save</a>
					</div>
				</div>
			</div>
		</div>
		<div title="Variants Annotation">
			<div class="container-fluid" style="padding:10px">
				<div class="row">
					<div class="col-md-5">
						<label for="selAnnotation">Annotation Source:</label>
						<select class="form-control" id="selAnnotation">
							<!--option value='khanlab' {!!($default_annotation == 'khanlab')? 'selected' : ''!!}>Khan Lab</option-->
							<option value='avia' {!!($default_annotation == 'avia')? 'selected' : ''!!}>AVIA</option>
						</select>
						</br>
						<a href="#" id="btnSaveAnnotation" class="btn btn-success" >Save</a>
					</div>
				</div>
			</div>
		</div>
		@if (App\Models\User::isSuperAdmin()) 
		<div title="System">
			<div id="tabVar" class="easyui-tabs" data-options="tabPosition:'top',fit:true,plain:true,false:true,border:false,headerWidth:100" style="width:100%;height:100%;padding:5px;overflow:visible;border-width:1px">				
				<div title="High confidence setting">
					<div id="tabHighConf" class="easyui-tabs" data-options="tabPosition:'top',fit:true,plain:true,false:true,border:false,headerWidth:100" style="width:100%;height:100%;padding:5px;overflow:visible;border-width:1px">
						@foreach ($high_confs as $high_conf_name => $high_conf)
							<div title="{!!$high_conf_name!!}">
								<div class="container-fluid" style="padding:10px">
									<div class="row">
										<div class="col-md-1"></div>
										<div class="col-md-2">
											<label>MAF:</label>
										</div>	
										<div class="col-md-2">
											<input type='text' id="{!!$high_conf_name!!}_maf" value="{!!$high_conf->maf!!}" ></input>
										</div>
									</div>
									<div class="row">
										<div class="col-md-1"></div>
										<div class="col-md-2">
											<label>Germline Total Coverage:</label>
										</div>	
										<div class="col-md-2">
											<input type='text' id="{!!$high_conf_name!!}_germline_total_cov" value="{!!$high_conf->germline_total_cov!!}" ></input>
										</div>
									</div>
									<div class="row">
										<div class="col-md-1"></div>
										<div class="col-md-2">
											<label>Germline Fisher Score:</label>
										</div>	
										<div class="col-md-2">
											<input type='text' id="{!!$high_conf_name!!}_germline_fisher" value="{!!$high_conf->germline_fisher!!}" ></input>
										</div>				
									</div>
									<div class="row">
										<div class="col-md-1"></div>
										<div class="col-md-2">
											<label>Germline VAF:</label>
										</div>	
										<div class="col-md-2">
											<input type='text' id="{!!$high_conf_name!!}_germline_vaf" value="{!!$high_conf->germline_vaf!!}" ></input>
										</div>				
									</div>
									<hr>
									<div class="row">
										<div class="col-md-1"></div>
										<div class="col-md-2">
											<label>Somatic - Panel Total Coverage:</label>
										</div>	
										<div class="col-md-2">
											<input type='text' id="{!!$high_conf_name!!}_somatic_panel_total_cov" value="{!!$high_conf->somatic_panel_total_cov!!}" ></input>
										</div>
									</div>
									<div class="row">
										<div class="col-md-1"></div>
										<div class="col-md-2">
											<label>Somatic - Panel Normal Coverage:</label>
										</div>	
										<div class="col-md-2">
											<input type='text' id="{!!$high_conf_name!!}_somatic_panel_normal_total_cov" value="{!!$high_conf->somatic_panel_normal_total_cov!!}" ></input>
										</div>				
									</div>
									<div class="row">
										<div class="col-md-1"></div>
										<div class="col-md-2">
											<label>Somatic - Panel VAF:</label>
										</div>	
										<div class="col-md-2">
											<input type='text' id="{!!$high_conf_name!!}_somatic_panel_vaf" value="{!!$high_conf->somatic_panel_vaf!!}" ></input>
										</div>				
									</div>
									<div class="row">
										<div class="col-md-1"></div>
										<div class="col-md-2">
											<label>Somatic - Exome Total Coverage:</label>
										</div>	
										<div class="col-md-2">
											<input type='text' id="{!!$high_conf_name!!}_somatic_exome_total_cov" value="{!!$high_conf->somatic_exome_total_cov!!}" ></input>
										</div>
									</div>
									<div class="row">
										<div class="col-md-1"></div>
										<div class="col-md-2">
											<label>Somatic - Exome Normal Coverage:</label>
										</div>	
										<div class="col-md-2">
											<input type='text' id="{!!$high_conf_name!!}_somatic_exome_normal_total_cov" value="{!!$high_conf->somatic_exome_normal_total_cov!!}" ></input>
										</div>				
									</div>
									<div class="row">
										<div class="col-md-1"></div>
										<div class="col-md-2">
											<label for="{!!$high_conf_name!!}_somatic_exome_vaf">Somatic - Exome VAF:</label>
										</div>	
										<div class="col-md-2">
											<input class=form-control" type='text' id="{!!$high_conf_name!!}_somatic_exome_vaf" value="{!!$high_conf->somatic_exome_vaf!!}" ></input>
										</div>				
									</div>
									<hr>
									<a href="#" id="{!!$high_conf_name!!}.btnSaveSystem" class="btn btn-success bthHighConf" >Save</a>
									<br>
						
								</div>
							</div>
							@endforeach
						</div>
						<br>
					</div>
					<div title="Broadcast message">
					<form action="broadcast" method="post">
						<div class="container-fluid" style="padding:10px">
							<label for="selAnnotation">Message:</label>
							<textarea rows="8" cols="70" class="form-control" name="txtMessage" data-validation="required"></textarea>
							<br>
							<input type="hidden" name="_token" value="{{ csrf_token() }}" />
							<input type="submit" class="btn btn-success" value="Submit" style="width:100px">
						</div>						
					</form>
				</div>
				</div>
			</div>
		</div>
		@endif
	</div>
</div>

@stop
