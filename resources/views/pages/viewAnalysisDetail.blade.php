{{ HTML::style('css/analysis.css') }}
{{ HTML::style('packages/canvasXpress/css/canvasXpress.css') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('packages/jquery-easyui/themes/icon.css') }}
{{ HTML::script('packages/canvasXpress/js/canvasXpress.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/jquery/jquery-1.11.1.min.js') }}

<script type="text/javascript">
      var plot = null;
      $(document).ready(function() {
          
          plot_width = {{$plot_width}};
          plot_height = {{$plot_height}};          
          $('#analysis_canvas').attr("width" , Math.max($('#plot_area').width() - 50, plot_width));
          $('#analysis_canvas').attr("height", Math.max($('#plot_area').height() - 50, plot_height));
          $('#selAnalysis').on('change', function() {
              refreshPage(this.value, 'Boxplot',0,0,0);
          });
          $('#selX').on('change', function() {
              refreshPage({{$aid}},$("#selPlotType").val(),$("#selX").val(),$("#selY").val(),$("#selValue").val());              
          });
          $('#selY').on('change', function() {
              refreshPage({{$aid}},$("#selPlotType").val(),$("#selX").val(),$("#selY").val(),$("#selValue").val());
          });
          $('#selValue').on('change', function() {
              refreshPage({{$aid}},$("#selPlotType").val(),$("#selX").val(),$("#selY").val(),$("#selValue").val());
          });
          $('#selPlotType').on('change', function() {
              refreshPage({{$aid}},$("#selPlotType").val(),$("#selX").val(),$("#selY").val(),$("#selValue").val());
          });
          $('#showDendrogram').change(function() {
             if ($(this).is(":checked"))
                 plot.dendrogramSpace = 6;
             else
                 plot.dendrogramSpace = 0;
             plot.draw();
          });
          showPlot();
          $("#selAnalysis").val({{$aid}});          
          $("#selPlotType").val('{{$plot_type}}');
          $("#selX").val({{$x_idx}});
          $("#selY").val({{$y_idx}});
          $("#selValue").val({{$value_idx}});
          $('#container-analysis_canvas').css("width", $('#analysis_canvas').width() + 30);
          $('#container-analysis_canvas').css("height", $('#analysis_canvas').height() + 30);
      }); 

      function refreshPage(aid, plot_type, x_idx, y_idx, value_idx) {          
          if (y_idx == undefined)
              y_idx = 0;  
          window.location.replace("{{url("/analysisDetail/")}}" + "/" + aid + "/" + "{{$detailed_value}}" + "/" + plot_type + "/" + x_idx + "/" + y_idx + "/" + value_idx);
      }

      var showPlot = function () {
          plot = new CanvasXpress("analysis_canvas", {{$data}},
      @if ($plot_type == 'Boxplot' || $plot_type == 'Dotplot' || $plot_type == 'Line')                    
          {
		"axisTitleFontStyle": "italic",
		"axisTitleFontSize": 18,
		"smpTitleFontSize": 18,
		"axisTickFontSize": 18,
		"smpLabelFontSize": 18,        
		"autoScaleFont": false, 
		"showBoxplotOriginalData": true,
		"graphType": "{{$plot_type}}",
		"showLegend": false,
		"showShadow": true,
		"graphOrientation": "vertical",
		"smpLabelScaleFontFactor": 0.8,
		"axisAlgorithm" : "heckbert",
		"title": "{{$title}}.",
		"smpTitle": "{{$x_field}}",
		"xAxisTitle": "{{$axis_title}}",
          }
        );
        plot.groupSamples(["{{$x_field}}"]);
        
      @elseif ($plot_type == 'Heatmap')
          {"axisTitleFontStyle": "italic",
           "axisTitleFontSize": 18,
           "smpTitleFontSize": 18,
           "varTitleFontSize": 18,
           "axisTickFontSize": 18,
           "smpLabelFontSize": 18, 
           "varLabelFontSize": 18,        
           "autoScaleFont": false, 
           "dendrogramHang": true,
           "graphType": "{{$plot_type}}",
           "heatmapType": "green-red",
           "indicatorCenter": "rainbow",
           "showDataValues": false,
           "varDendrogramPosition": "top",
           "adjustAspectRatio " : true,
           "title": "{{$title}}.",
           "varTitle": "Group",
           "smpTitle": "Sample",
           @if (isset($x_field_type))
                "varOverlays": ["{{$x_field_type}}"]
           @endif
          }
        );
        plot.clusterSamples();
        plot.clusterVariables();           
      @endif
      }

    </script>

<div id="out_container" class="easyui-panel" style="width:100%;height:95%;padding:10px;">
  <div class="easyui-layout" data-options="fit:true">
    <div data-options="region:'west',split:true" style="width:300px;padding:10px;overflow:auto;" title="Setting">
<table>
   <tr>
       <td> Analysis: </td>
       <td>
            <select id='selAnalysis' style="width: 150px">
                 @foreach ($analyses as $analysis)
                    <option value="{{$analysis->id}}">{{$analysis->analysis_name}}</option>
                 @endforeach
             </select>
       </td>
   </tr>
   <tr>
       <td> Plot type: </td>
       <td>
            <select id='selPlotType' style="width: 150px">
                  <option value="Boxplot">Box plot</option>
                  <option value="Dotplot">Dot plot</option>
                  <option value="Line">Line</option>
@if (isset($y_field) && $y_field != '')
                  <option value="Heatmap">Heatmap</option>
@endif
             </select>
       </td>
   </tr>
   <?$idx=0;?>
   <tr>
       <td> X axis: </td>
       <td>
             <select id='selX' style="width: 150px">
                 @foreach ($x_fields as $x_field)
                    <option value="{{$idx++}}">{{$x_field}}</option>
                 @endforeach
             </select>
       </td>
   </tr>

@if ($plot_type == 'Heatmap')
   <?$idx=0;?>
   <tr>
       <td> Y axis: </td>
       <td>
             <select id='selY' style="width: 150px">
                 @foreach ($y_fields as $y_field)
                    <option value="{{$idx++}}">{{$y_field}}</option>
                 @endforeach
             </select>
       </td>
   </tr>
@endif
   <?$idx=0;?>
   <tr>
       <td>Value: </td>
       <td>
             <select id='selValue' style="width: 150px">
                 @foreach ($value_fields as $value_field)
                    <option value="{{$idx++}}">{{$value_field}}</option>
                 @endforeach
             </select>
       </td>
   </tr>
@if ($plot_type == 'Heatmap')
   <tr>
       <td>Show Dendrogram: </td>
       <td>
           <input id='showDendrogram' type="checkbox" checked/>  
       </td>
   </tr>
@endif
</table>

    </div>
    <div id="plot_area" data-options="region:'center'" style="padding:0px; border:1px" title="Plot" >
       <canvas id='analysis_canvas'></canvas>
    </div>
</div>

