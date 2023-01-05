{{ HTML::style('packages/w2ui/w2ui-1.4.min.css') }}
{{ HTML::style('packages/bootstrap-3.3.7/css/bootstrap.min.css') }}
{{ HTML::style('css/style.css') }}

{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-core-css.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-blue/sm-blue.css') }}    
{{ HTML::script('js/jquery-3.6.0.min.js') }}
{{ HTML::script('packages/smartmenus-1.0.0-beta1/jquery.smartmenus.min.js') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}


{{ HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') }}
{{ HTML::style('css/style_datatable.css') }} 
{{ HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') }}
{{ HTML::style('packages/jquery-easyui/themes/icon.css') }}
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}
{{ HTML::style('packages/muts-needle-plot/build/muts-needle-plot.css') }}
{{ HTML::style('packages/bootstrap-switch-master/dist/css/bootstrap3/bootstrap-switch.css') }}
{{ HTML::style('css/filter.css') }}
{{ HTML::style('packages/tooltipster-master/dist/css/tooltipster.bundle.min.css') }}
{{ HTML::style('packages/DataTables-1.10.8/extensions/Highlight/dataTables.searchHighlight.css') }}

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
{{ HTML::script('packages/tooltipster-master/dist/js/tooltipster.bundle.min.js') }}
{{ HTML::script('packages/bootstrap-switch-master/dist/js/bootstrap-switch.js') }}
{{ HTML::script('packages/w2ui/w2ui-1.4.min.js')}}
{{ HTML::script('packages/DataTables-1.10.8/extensions/Highlight/jquery.highlight.js') }}
{{ HTML::script('packages/DataTables-1.10.8/extensions/Highlight/dataTables.searchHighlight.min.js') }}
{{ HTML::script('js/filter.js') }}
{{ HTML::script('js/onco.js') }}
{{ HTML::script('packages/highchart/js/highcharts.js')}}
{{ HTML::script('packages/highchart/js/highcharts-more.js')}}
{{ HTML::style('css/GSEA.css') }}


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
.disabled {
   pointer-events: none;
   cursor: default;
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
#View_Results{
  width:100%;
  border:none;
  height:100%;
}
@media screen and (min-width: 1400px) {
  .container {
    width: 1370px;
  }
}
@media screen and (min-width: 1600px) {
  .container {
    width: 1570px;
  }
}
@media screen and (min-width: 1900px) {
  .container {
    width: 1870px;
  }
}
</style>


<header class="container" style="margin-bottom:0px">    
  <div id="header" role="banner">
    <h1 id="ribbon" class="clearfix">GSEA</h1>
  </div>
</header>
  
<main class="container" role="main">
  <p style="margin-top: 2%;text-align:left">Gene Set Enrichment Analysis (GSEA) is a computational method that determines whether an a priori defined set of genes shows statistically significant, concordant differences between two biological states (e.g. phenotypes).</p> 

  <div id="sample_container" class="easyui-panel" data-options="border:false" style="width:100%;padding:0px;border-width:0px">
    <div id="tabSamples" class="easyui-tabs" data-options="toolPosition: 'left', tabPosition:'top',plain:true, pill:true,border:false" style="width:100%;padding:0px;overflow:auto;border-width:0px;display:none">
    @foreach($sample_list as $sample => $sample_name)
      <div id="{{str_replace(" ", "_", $sample)}}" title="{{$sample_name}}"></div>
    @endforeach
    </div>

  <p style="margin-top: 2%;text-align:left" id="message">There is no expression data associated with this sample</p>
  <div class="panel panel-default">

  <div class="panel panel-default">
    <div class="panel-heading"><h2 class="panel-title">Required Fields</h2></div>
    <div class="panel-body">

      <div class="row" style="margin-bottom:10px">
        <label for="pre_gene_select" class="col-sm-4"><input  aria-label="pre selected gene sets" type="radio" id="is_gene_set_type" value="pre" class="gene_select" name="gene_set_type" />Pre Selected Gene Sets:</label>
            <div class="col-sm-4"><select class="form-control" disabled id="pre_gene_select" ></select></div><a id="gene_set_help" href="#" class="fancybox mytooltip box tooltipstered"><img src={{url('images/help.png')}}></img></a>

      </div>
       <div class="row" style="margin-bottom:10px">
            <label for="user_gene_select" class="col-sm-4"><input  aria-label="user selected gene sets" type="radio" id="is_gene_set_type" value="user"  name="gene_set_type" class="gene_select" />OR User Uploaded Gene Sets:</label>
            <div class="col-sm-4"><select class="form-control" disabled  id="user_gene_select" ></select></div> 

      </div>

      <div id="samples" class="row" style="margin-bottom:10px">
            <label for="sample_list" class="col-sm-4">Sample to run:</label>
            <div class="col-sm-4"><select class="form-control" id="sample_list" ></select></div> 
      </div>



      <div class="row" id="ranked_list_div" style="margin-bottom:10px">
        <label for="rank_by" class="col-sm-4">Rank List By:</label>
         <div class="col-sm-1">
            <select id="ranked_list" name="rank_by" aria-label="rank_by" />
              <option value="TPM">TPM</option> 
              <option value="Median_Centered">Median Centered</option>
              <option value="Zscore">Zscore</option> 
              <option value="Median_Zscore">Median Centered Zscore</option> 
          </div>
            </select>
      </div> 

        
      </div>
      <div class="row" style="margin-bottom:10px;display: none" id="normal_project">
          <label for="normalize_gene_select" class="col-sm-4">Data Set to normalize against:</label>
          <div class="col-sm-4"><select class="form-control" id="normalize_gene_select" >
          </select>
      </div> 

      </div>
      <div class="row" style="margin-bottom:10px">
        <label for="gene_type" class="col-sm-4">Gene Type:</label>
         <div class="col-sm-1">
            <select id="gene_type" name="gene_type" aria-label="gene_type" />
              <option value="refseq">refseq</option> 
              <option value="ensembl">ensembl</option> 
            </select>
          </div>   
      </div>

  </div>

  <div class="panel panel-default">
    <div class="panel-heading"><h2 class="panel-title"><a data-toggle="collapse" class="collapsed" data-target="#Basic" href="#Basic">Basic Fields</a></h2></div>
    <div id="Basic" class="panel-collapse collapse">
      <div class="panel-body">
        <div class="row" style="margin-bottom:10px">
            <label for="rpt_label" class="col-sm-4">Analysis Name</label>
            <div class="col-sm-5"><input type="text" class="form-control" id="rpt_label" required value=test_analysis></div> 
       </div>
       <div class="row" style="margin-bottom:10px">
            <label for="enrich" class="col-sm-4">Enrichment statistic:</label>
            <div class="col-sm-5">
              <select class="form-control" id="enrich" >
                <option value="classic" selected="selected" >classic</option>
                <option value="weighted" >weighted</option> 
                <option value="weighted_p2">weighted_p2</option> 
                <option value="weighted_p1.5">weighted_p1.5</option> 
              </select>
            </div> 
      </div>

      <div class="row" style="margin-bottom:10px">
            <label for="max_size_large" class="col-sm-4">Max size: exclude larger sets:</label>
            <div class="col-sm-5"><input type="number" class="form-control" id="max_size_large" required value=500></div> 
      </div>

      <div class="row" style="margin-bottom:10px">
            <label for="max_size_small" class="col-sm-4">Min size: exclude smaller sets:</label>
            <div class="col-sm-5"><input type="number" class="form-control" id="max_size_small" required value=15></div> 
      </div>
       <div class="row" style="margin-bottom:10px">
            <label for="perm" class="col-sm-4">Number of Permutations:</label>
            <div class="col-sm-2">
              <select class="form-control" id="perm" >
                <option value="0">0</option>
                <option value="1">1</option> 
                <option value="10">10</option> 
                <option value="100">100</option> 
                <option value="1000" selected="selected">1000</option>  
              </select>
            </div> 
      </div>
      

      </div>
    </div>
  </div>

  <div class="panel panel-default">
    <div class="panel-heading"><h2 class="panel-title"><a data-toggle="collapse" class="collapsed" data-target="#Adv" href="#Adv">Advanced fields</a></h2></div>
    <div id="Adv" class="panel-collapse collapse">
      <div class="panel-body">

       <div class="row" style="margin-bottom:10px">
            <label for="normal" class="col-sm-4">Normalization mode:</label>
            <div class="col-sm-5">
              <select class="form-control" id="normal" >
                <option selected="selected" value="meandiv"  >meandiv</option>
                <option value="none" ">none</option> 
              </select>
            </div> 
      </div>

      <div class="row" style="margin-bottom:10px">
            <label for="alt_del" class="col-sm-4">Alternate delimiter</label>
            <div class="col-sm-5"><input type="text" class="form-control" id="alt_del" required></div> 
      </div>

      <div class="row" style="margin-bottom:10px">
            <label for="create_svg" class="col-sm-4">Create SVG plot images:</label>
            <div class="col-sm-5">
              <select class="form-control" id="create_svg" >
                <option selected="selected" value="false">false</option>
                <option value="true" ">true</option> 
              </select>
            </div> 
      </div>

       <div class="row" style="margin-bottom:10px">
            <label for="detailed_report" class="col-sm-4">Make detailed gene set report:</label>
            <div class="col-sm-5">
              <select class="form-control" id="detailed_report" >
                <option selected="selected" value="true">true</option>
                <option value="false" ">false</option> 
              </select>
            </div> 
      </div>

       <div class="row" style="margin-bottom:10px">
            <label for="plot_graphs_pheno" class="col-sm-4">Plot Graphs for the top sets of each phenotype</label>
            <div class="col-sm-5"><input type="number" class="form-control" id="plot_graphs_pheno" required value=20></div> 
      </div>

      <div class="row" style="margin-bottom:10px">
            <label for="seed_perm" class="col-sm-4">Seed for Permutation</label>
           <div class="col-sm-5">
              <select class="form-control" id="seed_perm" >
                <option selected="selected" value="timestamp">timestamp</option>
                <option value="149" ">149</option> 
              </select>
            </div> 
      </div>

      </div>
    </div>
   </div>
<div class="panel panel-default">
    <div class="panel-heading"><h2 class="panel-title"><a data-toggle="collapse" data-target="#Results" href="#View Results">View Results</a></h2></div>
    <div id="Results" class="panel-collapse ">
      <div class="panel-body">

      <div class="row" style="margin-bottom:10px">
           <div class="col-sm-12">
            <table id="data_table" class="table table-hover"  width="100%"></table>
            </div> 
      </div>
       
      </div>
    </div>
  </div>
  </div>
   <input disabled type="button" id="calculate" style="margin-top:5px;color:#fff;background:#0272A7 " value="Run GSEA" class="btn btn-default calculate" >

</main>

<div id="ok-alert" class="modal" tabindex="-1" role="dialog" >
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h2 class="modal-title" id="modalTitle">Sent to Queue</h2>
      </div>
      <div class="modal-body" id=modalContent >
        <p>...</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-dismiss="modal" onclick="delete_row()">OK</button>

      </div>
    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->
</div><!-- /.modal -->


<script type="text/javascript">

  var tab_urls = [];
  var sub_tabs = [];
  var loaded_list = [];
  var tab_shown = true;
  var sample_id="1234"
  type='{{$patient_id}}'
  console.log("TYPE "+type)
  if(type=="gene"){
    Sample_type="Gene"
    $("#ranked_list_div").css("display","none")
  }
  else
    Sample_type="Sample"
  $(document).ready(function() {
     var headers=[
            { title: "Name" },
            { title: "Date" },
            { title: "Gene Set" },
            { title: Sample_type },
            { title: "Ranked By" },
            { title: "Normalized Project" },
            { title: "Download" },
            { title: " " },
        ]
    if('{{$patient_id}}'=='any'){
      var sample_id='any'
    }
    else{
      $("#samples").css("display","none");
      $("#tabSamples").css("display","block");
      var p = $('#tabSamples').tabs('getSelected');  // get the selected tab panel
      var sample_id = p.panel('options').id;
    }
    console.log(sample_id)
    $.ajax({ url: '{{url('/getGSEA')}}' + '/' + '{{$project_id}}' + '/' + '{{$patient_id}}' + '/' + '{{$case_id}}'+ '/' + sample_id, async: true, success: function(data) {
        $("#loading").css("display","none");  
        data = JSON.parse(data);
        console.log(data);
        help=JSON.parse(data.gene_help)
        var options = '';    
        if(data.samples!="" || '{{$patient_id}}'=='gene')
                    $("#message").css("display","none")       

        for (var i = 0; i < data.pre_gene_list.length; i++) 
        {
          options += '<option value="' + data.pre_gene_list[i] + '">' + data.pre_gene_list[i] + '</option>';
        }
        $("select#pre_gene_select").html(options);
        var options = '';        
        for (var i = 0; i < data.user_list.length; i++) 
        {
          options += '<option value="' + data.user_list[i] + '">' + data.user_list[i] + '</option>';
        }
        $("select#user_gene_select").html(options);
        options=""
        
        if('{{$patient_id}}'=='any'){
            for(var i in data.samples) 
            {
              options += '<option value="' + i + '">' + data.samples[i] + '</option>';
            }
              $("select#sample_list").html(options);
          }
        options=""
        for (var i = 0; i < data.projects.length; i++) 
        {
          options += '<option value="' + data.projects[i].id + '">' + data.projects[i].name + '</option>';
        }
        $("select#normalize_gene_select").html(options);    
        $('[id=normalize_gene_select]').val('22125');
        populate_input({{$token_id}});
        //var dataSet = [[ "Test_1234567", "gene_set.gmt", "True" ]];
        var dataSet=data.results;

        console.log(data.results)
        if (data.results.length>0){
          data_table(dataSet,headers);
        }
        if(check_results(data.results,{{$token_id}})==true)
          open_results_page({{$token_id}});
        
   
      }  


      
   });

    /*
    window.setInterval(function(){
      if('{{$patient_id}}'=='any'){
      var sample_id='any'
    }
    else{
      $("#samples").css("display","none");
      $("#tabSamples").css("display","block");
      var p = $('#tabSamples').tabs('getSelected');  // get the selected tab panel
      var sample_id = p.panel('options').id;
    }
   $.ajax({ url: '{{url('/getGSEA')}}' + '/' + '{{$project_id}}' + '/' + '{{$patient_id}}' + '/' + '{{$case_id}}'+ '/' + sample_id, async: true, success: function(data) {
        $("#loading").css("display","none");  
        data = JSON.parse(data);
        var dataSet=data.results;
        data_table(dataSet,headers);
      }
  });
},  20000);
*/
 });
$('.easyui-tabs').tabs({
  onSelect:function(title, idx) {
    var tab = null;
    var url = null;       
    var sub_tab = sub_tabs[title];
    $('#View_Results').css("display","none");
    $(this).attr('src', 'http://example.com/brown.gif');
    if (sub_tab != undefined)
      tab = $('#' + sub_tab).tabs('getSelected');
    else
      tab = $(this).tabs('getSelected');        
    var sample_id = tab.panel('options').id;
    
    $.ajax({ url: '{{url('/getGSEA')}}' + '/' + '{{$project_id}}' + '/' + '{{$patient_id}}' + '/' + '{{$case_id}}'+ '/' + sample_id, async: true, success: function(table) {
      var headers=[
            { title: "Name" },
            { title: "Date" },
            { title: "Gene Set" },
            { title: "Sample" },
            { title: "Ranked By" },
            { title: "Normalized Project" },
            { title: "Download" },
            { title: " " },
        ]
         data = JSON.parse(table);
        var dataSet=data.results;
        console.log(dataSet);
          data_table(dataSet,headers);
 //       Tablelisteners();
    }
    });

   }
});
function showFrameHtml(id) {
  if (loaded_list.indexOf(id) == -1) {
    var url = tab_urls[id];
    //url="https://www.google.com";
    console.log(id);
    console.log(JSON.stringify(tab_urls));
    console.log(url);
    if (url != undefined) {
      var html = '<iframe scrolling="auto" frameborder="0"  src="' + url + '" style="width:100%;height:100%;overflow:auto;border-width:0px;"></iframe>';
      $('#' + id).html(html);
      loaded_list.push(id);
    }
  }
}

function check_results(results,token_id){
      for (var i = 0; i < results.length; i++) {
          if (results[i][6]==token_id)
            return true
        }
        return false
}
  $("#calculate").on("click", function() {
  var sPageURL = window.location.search.substring(1);
  var value = sPageURL.substring(sPageURL.lastIndexOf('/') + 1); 
          token_id=renewTokenId(true)
          ShowModal("Your submission has been queued.  You will receive an e-mail when calculation is completed.", "Calculation in Queue","center");
          console.log($("#gene_type").val());
          var sample_id=""
          var sample_name=""
          if('{{$patient_id}}'=='any'){
            sample_id=$("#sample_list").val();
            sample_name=$("#sample_list option:selected").text();
          }
          else{
            var p = $('#tabSamples').tabs('getSelected');  // get the selected tab panel
            sample_id = p.panel('options').id;
            sample_name=p.panel('options').title;
          }
          if('{{$patient_id}}'=='gene')
            rank_by="Correlation"
          else
            rank_by=$("#ranked_list").val()
          gene_set_type=$('input[name=gene_set_type]:checked').val();
          var gene_set="";
          if($("#ranked_list").val()!="TPM"){
            normal_project_id=$("#normalize_gene_select").val()
            normal_project_name=$("#normalize_gene_select option:selected").text();
          }
          else{
            normal_project_id="NA"
            normal_project_name="NA"
          }
          if(gene_set_type=="pre"){
            gene_set=$("#pre_gene_select").val()
          }
          else if(gene_set_type=="user"){
           gene_set=$("#user_gene_select").val()
          }
          var Inputs = {
            calc:"Preranked",
            gene_set_type:gene_set_type,
            gmx:gene_set,
            norm:$("#normal").val(), 
            nperm: $("#normal").val(), 
            scoring_scheme: $("#enrich").val(), 
            create_svgs: $("#create_svg").val(), 
            make_sets:  $("#detailed_report").val(), 
            plot_top_x:  $("#plot_graphs_pheno").val(), 
            rnd_seed:  $("#seed_perm").val(), 
            set_max:  $("#max_size_large").val(), 
            set_min:  $("#max_size_small").val(),
            rpt_label: $("#rpt_label").val(),
            zip_report: true, 
            gui: false,
            token_id:token_id,
            type:"Pre_ranked",
            rank_by: rank_by,
            sample_id: sample_id,
            sample_name:sample_name,
            target_type:$("#gene_type").val(),
            normal_project_id:normal_project_id,
            normal_project_name:normal_project_name
          };
  var calc_url= '{{url('/GSEAcalc')}}'+ '/' + '{{$project_id}}' +  '/' + '{{$patient_id}}' + '/' + '{{$case_id}}'
  $.ajax({
    type : 'POST',
    async: true,
    url : calc_url,
    data : Inputs,
    }).success(function(token){
      console.log("TOKEN "+token)

    });



});


function open_results_page(token_id){
  $("#calculate").removeAttr('disabled');
  $("#pre_gene_select").removeAttr('disabled');
  var url='{{url('/viewGSEAResults')}}' + '/' + '{{$project_id}}' +  '/' + token_id
  window.open(url, '_blank'); 
}

function populate_input(token_id){

  $.ajax({ url: '{{url('/getGSEAInput')}}' + '/' + token_id, async: true, success: function(data) {
  data=JSON.parse(data);     
  console.log(data);
  gene_set=data.gmx; 
  console.log(token_id);
   if($("#pre_gene_select option[value='"+gene_set+"']").length > 0){         
    $("#pre_gene_select").val(data.gmx);
    $("#user_gene_select").attr('disabled',true);
    $("#pre_gene_select").removeAttr('disabled');
    $(":radio[value=pre]").prop('checked',true);

  }
  else{
    $("#user_gene_select").val(gene_set);
    $("#user_gene_select").removeAttr('disabled');
    $("#pre_gene_select").attr('disabled',true);
    $(":radio[value=user]").prop('checked',true);


  }
  $("#gene_type").val(data.target_type);
  $("#sample_list").val(data.sample_id);
  if(data.rank_by=="FPKM")
    data.rank_by="TPM"
  $("#ranked_list").val(data.rank_by);

  $("#rpt_label").val(data.rpt_label);
  $("#enrich").val(data.scoring_scheme);
  $("#max_size_large").val(data.set_max);
  $("#max_size_small").val(data.set_min);

  $("#normal").val(data.norm); 
  $("#normal").val(data.nperm); 
  $("#create_svg").val(data.create_svgs); 
  $("#detailed_report").val(data.make_sets); 
  $("#plot_graphs_pheno").val(data.plot_top_x); 
  $("#seed_perm").val(data.rnd_seed); 

if(data.rank_by!="TPM"){
  $("#normalize_gene_select").val(data.normal_project_id); 
  $("#normal_project").css("display","block")
}
else{
  $("#normal_project").css("display","none")
}


}
  });

}
function ShowModal(message, title,text_align) {
  $("#ok-alert").find(".modal-title").empty().html(title);
  $("#ok-alert").find(".modal-body").empty().html(message);
  $("#modalContent").css("text-align", text_align); 
  $("#ok-alert").modal('show');
}

function renewTokenId(refresh_url) {

  var tokenId = Math.floor(Math.random() * (999999 - 100000 + 1));
  //console.warn(tokenId);
  if(refresh_url == true) {
    setUrlParameter(tokenId.toString());
    //setUrlParameter("request", "false");
  }

  return tokenId.toString();
}

function setUrlParameter(new_value) {
  var sPageURL = window.location.search.substring(1);

  var value = sPageURL.substring(sPageURL.lastIndexOf('/') + 1);
  sPageURL = sPageURL.replace(value, new_value)
  window.history.pushState({},'', sPageURL);



}
function select_row(value){

  var nameToSearch = value;
  var index=$("#data_table tr:contains("+nameToSearch+")").index();
  return index
  console.log(index)
}
function Tablelisteners(matrix,table){
  ReplaceCellContent(" Y "," <a target=_blank class='btn' id='remove_GSEA'><img width=15 height=15 src={{url('images/delete_2_xxl.png')}}></img></a>")
  ReplaceCellContent(" N ","<a target=_blank class='btn disabled'  id='remove_GSEA'></a>")
  ReplaceCellContent("FPKM","TPM")

  $( "#data_table tbody >tr>td" ).unbind("click");
 /* for (var i = 0; i < matrix.length; i++) {
        var data = $("#data_table tr:contains("+matrix[i][0]+")")
        console.log(data)
      }*/

  $("#data_table tbody >tr>td").on("click",function(event){
      col=$(this).index();
      if(col>=1)
        name=$(this).siblings("td:first").text();
      else 
        name=$(this).text();                 
      console.log(col);
      console.log(name)
      var res = name.split("_");
      var token_id=res[res.length-1];
      console.log(token_id); 
            content=$(this).html()

      if(col==6){
        $.ajax({ url: '{{url('/downloadGSEAResults')}}'+ '/' + '{{$project_id}}' +  '/' + token_id, async: true, success: function(data) {
                window.location = '{{url('/downloadGSEAResults')}}'+ '/' + '{{$project_id}}' +  '/' + token_id;
                }
        });
      
      }
      else if(col==7){
        if(content.indexOf("disabled") <= 0){
//          ShowModal("Are you sure you want to delete "+name+"?","Warning","center")
              $.ajax({ url: '{{url('/removeGSEArecords')}}/' + token_id, async: true, success: function(data) {
                }
        });
          $(this).closest("tr").remove();

        }

      }
      else{
        open_results_page(token_id);
        setUrlParameter(token_id)
        populate_input(token_id);
      }

  });
}
function ReplaceCellContent(find, replace)
{
    $("#data_table td:contains('" + find + "')").html(replace);
}

function data_table(matrix,headers){
 table= $('#data_table').DataTable({
      columns: headers,
      data:matrix,
      destroy:true,
      "fnDrawCallback":function(){Tablelisteners(matrix)},
      "pagingType": "full_numbers",
       "columnDefs": [ {
              "targets": -2,
              "data": null,
              "defaultContent": "<a target=_blank class='btn btn-info' id='download_GSEA'><img width=15 height=15 src={{url('images/download.svg')}}></img>&nbsp;Download</a>"

          }
          ]
    });

 
  ReplaceCellContent(" Y "," <a target=_blank class='btn' id='remove_GSEA'><img width=15 height=15 src={{url('images/delete-2-xxl.png')}}></img></a>")
  ReplaceCellContent(" N ","<a target=_blank class='btn disabled'  id='remove_GSEA'></a>")

}


 $('.gene_select').click(function() {
    $("#calculate").removeAttr('disabled');

    var gene_type=$(this).val();
    if(gene_type=="pre"){
      $("#user_gene_select").attr('disabled',true);
      $("#pre_gene_select").removeAttr('disabled');
    }
    else if(gene_type=="user"){
      $("#user_gene_select").removeAttr('disabled');
      $("#pre_gene_select").attr('disabled',true);
    }
});


$( "#gene_set_help" ).click(function() {
  var gene_set=$("#pre_gene_select").val()
  var type=gene_set.split(".");
  if(type[0]=='h'){
    var message=help['H'].content
    var title=help['H'].header
    ShowModal(message,title,"left")
  }

  else if(type[0]=='c1'){
    var message=help['C1'].content
    var title=help['C1'].header
    ShowModal(message,title,"left")
  }

  else if(type[0]=='c2' && type[1]=='all'){
    var message=help['C2'].content
    var title=help['C2'].header
    ShowModal(message,title,"left")
  }

  else if(type[0]=='c2' && type[1]=='cgp'){
    var message=help['C2cgp'].content
    var title=help['C2cgp'].header
    ShowModal(message,title,"left")
  }

  else if(type[0]=='c2' && type[1]=='cp'){
    var message=help['C2cp'].content
    var title=help['C2cp'].header
    ShowModal(message,title,"left")
  }
  else if(type[0]=='c3' && type[1]=='all'){
    var message=help['C3'].content
    var title=help['C3'].header
    ShowModal(message,title,"left")
  }
  else if(type[0]=='c3' && type[1]=='mir'){
    var message=help['C3mir'].content
    var title=help['C3mir'].header
    ShowModal(message,title,"left")
  }
  else if(type[0]=='c3' && type[1]=='tft'){
    var message=help['C3tft'].content
    var title=help['C3tft'].header
    ShowModal(message,title,"left")
  }
  else if(type[0]=='c4' && type[1]=='all'){
    var message=help['C4'].content
    var title=help['C4'].header
    ShowModal(message,title,"left")
  }
  else if(type[0]=='c4' && type[1]=='cgn'){
    var message=help['C4cgn'].content
    var title=help['C4cgn'].header
    ShowModal(message,title,"left")
  }
  else if(type[0]=='c4' && type[1]=='cm'){
    var message=help['C4cm'].content
    var title=help['C4cm'].header
    ShowModal(message,title,"left")
  }
  else if(type[0]=='c5'){
    var message=help['C5'].content
    var title=help['C5'].header
    ShowModal(message,title,"left")
  }
  else if(type[0]=='c6'){
    var message=help['C6'].content
    var title=help['C6'].header
    ShowModal(message,title,"left")
  }
  else if(type[0]=='c7'){
    var message=help['C7'].content
    var title=help['C7'].header
    ShowModal(message,title,"left")
  }
  else if(type[0]=='Cibersort_22_Estimate_2_geneSetsGMX'){
    var message=help['Cibersort'].content
    var title=help['Cibersort'].header
    ShowModal(message,title,"left")
  }
  else if(type[0]=='NCI_GeneSet_v16'){
    var message=help['NCI'].content
    var title=help['NCI'].header
    ShowModal(message,title,"left")
  }

});
$( "#ranked_list" ).change(function() {
  if($("#ranked_list").val()!="TPM")
    $("#normal_project").css("display","block")
  else
    $("#normal_project").css("display","none")
});

</script>