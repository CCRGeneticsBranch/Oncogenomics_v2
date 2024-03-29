
function oncoHeatmap(config) {
  // INITIALIZATION
  var data = config.data;
  var metadata = config.metadata;
  var margin = config.margin;
  var cellSize = config.cellSize;
  var rowLabel = config.rowLabel;
  var colLabel = config.colLabel;
  var hcrow = config.hcrow;
  var hccol = config.hccol;
  var colorMin = config.colorMin;
  var colorMax = config.colorMax;
  var targetElementID = config.targetElementID;
  var col_number = colLabel.length;
  var row_number = rowLabel.length;
  var delay_sec = 2000;
  this.padding = 5;
  this.cellSize = cellSize;
  this.margin = margin;
  this.rowLabel = rowLabel;
  this.colLabel = colLabel;
  this.svgClasses = "oncoHeatmap" + targetElementID;

  $(".d3-tip").remove();
  $(".d3-tip\\:after").remove();
  $(".d3-tip\\.n\\:after").remove();

  tooltip = d3.tip()
        .attr('class', 'd3-tip')
        .offset([-10, 0])
        .html(function(d) {                
                return config.getHint(rowLabel[d.row-1], colLabel[d.col-1], d.value, false);                
                
  });

  width = cellSize*col_number; // - margin.left - margin.right,
  height = cellSize*(row_number+metadata.length); // - margin.top - margin.bottom,
  margin.bottom = margin.bottom + (cellSize + this.padding) * metadata.length;
  this.height = height;
  //gridSize = Math.floor(width / 24),
  legendElementWidth = cellSize*0.15;
  colorBuckets = 100;
  colorText = [];
  for (var i=0; i<colorBuckets; i++) {
    num = colorMin + i * (colorMax-colorMin)/(colorBuckets-1);
    colorText.push(num.toFixed(1));
  }
  
  //colors = createColorRange()
  colors = [];
  var rainbow = new Rainbow();
  if (config.colorScheme == 0)
    rainbow.setSpectrum('green', 'red');
  if (config.colorScheme == 1)
    rainbow.setSpectrum('green', 'black', 'red');  
  if (config.colorScheme == 2)
    rainbow.setSpectrum('black', 'red');
  
  for (var i = 0; i <= 100; i++) {
      var hex = '#' + rainbow.colourAt(i);
      colors.push(hex);
  }
  //colors = ['#005824','#1A693B','#347B53','#4F8D6B','#699F83','#83B09B','#9EC2B3','#B8D4CB','#D2E6E3','#EDF8FB','#FFFFFF','#F1EEF6','#E6D3E1','#DBB9CD','#D19EB9','#C684A4','#BB6990','#B14F7C','#A63467','#9B1A53','#91003F'];
  //hcrow = [49,11,30,4,18,6,12,20,19,33,32,26,44,35,38,3,23,41,22,10,2,15,16,36,8,25,29,7,27,34,48,31,45,43,14,9,39,1,37,47,42,21,40,5,28,46,50,17,24,13]; // change to gene name or probe id
  //hccol = [6,5,41,12,42,21,58,56,14,16,43,15,17,46,47,48,54,49,37,38,25,22,7,8,2,45,9,20,24,44,23,19,13,40,11,1,39,53,10,52,3,26,27,60,50,51,59,18,31,32,30,4,55,28,29,57,36,34,33,35]; // change to gene name or probe id
  //rowLabel = ['1759080_s_at','1759302_s_at','1759502_s_at','1759540_s_at','1759781_s_at','1759828_s_at','1759829_s_at','1759906_s_at','1760088_s_at','1760164_s_at','1760453_s_at','1760516_s_at','1760594_s_at','1760894_s_at','1760951_s_at','1761030_s_at','1761128_at','1761145_s_at','1761160_s_at','1761189_s_at','1761222_s_at','1761245_s_at','1761277_s_at','1761434_s_at','1761553_s_at','1761620_s_at','1761873_s_at','1761884_s_at','1761944_s_at','1762105_s_at','1762118_s_at','1762151_s_at','1762388_s_at','1762401_s_at','1762633_s_at','1762701_s_at','1762787_s_at','1762819_s_at','1762880_s_at','1762945_s_at','1762983_s_at','1763132_s_at','1763138_s_at','1763146_s_at','1763198_s_at','1763383_at','1763410_s_at','1763426_s_at','1763490_s_at','1763491_s_at'], // change to gene name or probe id
  //colLabel = ['con1027','con1028','con1029','con103','con1030','con1031','con1032','con1033','con1034','con1035','con1036','con1037','con1038','con1039','con1040','con1041','con108','con109','con110','con111','con112','con125','con126','con127','con128','con129','con130','con131','con132','con133','con134','con135','con136','con137','con138','con139','con14','con15','con150','con151','con152','con153','con16','con17','con174','con184','con185','con186','con187','con188','con189','con191','con192','con193','con194','con199','con2','con200','con201','con21']; // change to contrast name  

  var colorScale = d3.scale.quantile()
      .domain([ colorMin , (colorMin + colorMax)/2, colorMax])
      .range(colors);
  
  d3.select("svg").remove();
  var svg = d3.select("#" + targetElementID).append("svg")
      .attr("width", width + margin.left + margin.right)
      .attr("height", height + margin.top + margin.bottom)
      //.on("mouseout", function(d) {d3.select("#celltooltip").classed("hidden", true);})
      .append("g")
      .attr("class", this.svgClasses)
      .attr("transform", "translate(" + margin.left + "," + margin.top + ")")      
      ;
  this.svg = svg;
  svg.call(tooltip);
  svg.on('click', tooltip.hide);
  this.metaSortOrder=true;
  var rowSortOrder=true;
  var colSortOrder=true;
  var rowLabels = svg.append("g")
      .selectAll(".rowLabelg")
      .data(rowLabel)
      .enter()
      .append("text")
      .text(function (d) { return d; })
      .attr("x", 0)
      .attr("y", function (d, i) { return (hcrow.indexOf(i+1) + metadata.length) * cellSize; })
      .style("text-anchor", "end")
      .attr("transform", "translate(-6," + cellSize / 1.5 + ")")
      .attr("class", function (d,i) { return "rowLabel mono r"+i;} ) 
	    .style("cursor", "hand")
      .on("mouseover", function(d) {d3.select(this).classed("text-hover",true);d3.select("#celltooltip").classed("hidden", true);})
      .on("mouseout" , function(d) {d3.select(this).classed("text-hover",false);})
      .on("click", function(d,i) {rowSortOrder=!rowSortOrder; sortbylabel("r",i,rowSortOrder);d3.select("#order").property("selectedIndex", 4).node().focus();;})
      ;

  var colLabels = svg.append("g")
      .selectAll(".colLabelg")
      .data(colLabel)
      .enter()
      .append("text")      
      .text(function (d) { return d;})      
      .attr("id", function (d){return d;})
      .attr("x", 0)
      .attr("y", function (d, i) { return hccol.indexOf(i+1) * cellSize; })
      .style("text-anchor", "left")
	  .style("cursor", "hand")
      .attr("transform", "translate("+cellSize/2 + ",-6) rotate (-90)")
      .attr("class",  function (d,i) { return "colLabel mono c"+i;} )
      .on("mouseover", function(d) {d3.select(this).classed("text-hover",true);d3.select("#celltooltip").classed("hidden", true);})
      .on("mouseout" , function(d) {d3.select(this).classed("text-hover",false);})
      .on("click", function(d,i) {colSortOrder=!colSortOrder;  sortbylabel("c",i,colSortOrder);d3.select("#order").property("selectedIndex", 4).node().focus();;})
      ;

  var heatMap = svg.append("g")
        //.attr("class","g3")
        .selectAll(".cellg")
        .data(data,function(d){return d.row+":"+d.col;})
        .enter()
        .append("rect")
        .attr("x", function(d) { return hccol.indexOf(d.col) * cellSize; })
        .attr("y", function(d) { return (hcrow.indexOf(d.row) + metadata.length) * cellSize; })
        .attr("class", function(d){return "cell cell-border cr"+(d.row-1)+" cc"+(d.col-1);})
        .attr("width", cellSize)
        .attr("height", cellSize)
        .style("fill", function(d) { return colorScale(d.value); })
        .style("cursor", "hand")
        .on("mouseover", function(d){
               //highlight text
               d3.select(this).classed("cell-hover",true);
               d3.selectAll(".rowLabel").classed("text-highlight",function(r,ri){ return ri==(d.row-1);});
               d3.selectAll(".colLabel").classed("text-highlight",function(c,ci){ return ci==(d.col-1);});
        
              var hint_text = config.getHint(rowLabel[d.row-1], colLabel[d.col-1], d.value, false);
              //console.log(hint_text);
              //hint_text = "Gene: " + rowLabel[d.row-1] + "\nSample: " + colLabel[d.col-1] + "\nValue: " + d.value;
               //Update the tooltip position and value
              /*
              tooltip.show(d); 
              if(tooltip.handle != undefined) 
                clearTimeout( tooltip.handle ); 
              */
              var x_pos = d3.event.pageX+2;
              if (d.col > colLabel.length - 17) {
                x_pos = (x_pos - 350) + "px";
              } else {
                 x_pos = x_pos + "px";
              }
              d3.select("#celltooltip")
                 .style("left", x_pos)
                 .style("top", (d3.event.pageY+2) + "px")
                 .select("#value")
                 .html(hint_text);  
               //Show the tooltip
               d3.select("#celltooltip").classed("hidden", false);
              
        })
        .on("mouseout", function(){

               d3.select(this).classed("cell-hover",false);
               d3.selectAll(".rowLabel").classed("text-highlight",false);
               d3.selectAll(".colLabel").classed("text-highlight",false);
               //tooltip.handle = setTimeout( tooltip.hide, delay_sec );
              //return;               
               d3.select("#celltooltip").classed("hidden", true);
        })
        .on("click", function(d, i) {
            
            var meta_type = "";
            var show_dropdown = false;
            if (metadata.length > 0) {
              meta_type = metadata[0].title;
              show_dropdown = true          
            }
            var hint_text = config.getHint(rowLabel[d.row-1], colLabel[d.col-1], d.value, show_dropdown);
            $('#cellDetails').w2popup();
            d3.select("#w2ui-popup #cellDetailsTitle").html(hint_text);         
            drawDetailPlot(rowLabel[d.row-1], colLabel[d.col-1], meta_type);
            $('#w2ui-popup #selMeta').on('change', function() {
              console.log($('#w2ui-popup #selMeta').val());
              drawDetailPlot(rowLabel[d.row-1], colLabel[d.col-1], $('#w2ui-popup #selMeta').val());
      
            }); 

            
        })
        ;

  var legend = svg.selectAll(".legend")
      .data(colorText)
      .enter().append("g")
      .attr("class", "legend");
 
  legend.append("rect")
    .attr("x", function(d, i) { return legendElementWidth * i; })
    .attr("y", height+(cellSize*1.5))
    .attr("width", legendElementWidth)
    .attr("height", cellSize)
    .style("fill", function(d, i) { return colors[i]; })
    .on("mouseover", function(d) {d3.select("#celltooltip").classed("hidden", true);})
    ;
 
  this.lengendHeight = height + (cellSize*3.5)
  legend.append("text")
    .attr("class", "mono")
    .text(function(d, i) { if (i == 0 || i == 99 || i == 50) return d; })
    .attr("width", legendElementWidth)
    .attr("x", function(d, i) { return legendElementWidth * i; })
    .attr("y", this.lengendHeight);


  // Change ordering of cells
  function sortbylabel(rORc,i,sortOrder){
       var t = svg.transition().duration(2000);
       var log2r=[];
       var sorted; // sorted is zero-based index
       d3.selectAll(".c"+rORc+i) 
         .filter(function(ce){
            log2r.push(ce.value);
          })
       ;
       if(rORc=="r"){ // sort gene
         sorted=d3.range(col_number).sort(function(a,b){ if(sortOrder){ return log2r[b]-log2r[a];}else{ return log2r[a]-log2r[b];}});
         t.selectAll(".cell")
           .attr("x", function(d) { return sorted.indexOf(d.col-1) * cellSize; })
           ;
         t.selectAll(".colLabel")
          .attr("y", function (d, i) { return sorted.indexOf(i) * cellSize; })
         ;
         for (var meta=0; meta<metadata.length;meta++)
           t.selectAll(".sample_meta" + meta)
            .attr("x", function (d, i) { return sorted.indexOf(i) * cellSize; })
           ;
       }else{ // sort log2ratio of a contrast
         sorted=d3.range(row_number).sort(function(a,b){if(sortOrder){ return log2r[b]-log2r[a];}else{ return log2r[a]-log2r[b];}});
         t.selectAll(".cell")
           .attr("y", function(d) { return (sorted.indexOf(d.row-1)+metadata.length) * cellSize; })
           ;        
         t.selectAll(".rowLabel")
          .attr("y", function (d, i) { return (sorted.indexOf(i) + metadata.length) * cellSize; })
         ;
       }
  }

  d3.select("#order").on("change",function(){
    order(this.value);
  });
  
  function order(value){
   if(value=="hclust"){
    var t = svg.transition().duration(2000);
    t.selectAll(".cell")
      .attr("x", function(d) { return hccol.indexOf(d.col) * cellSize; })
      .attr("y", function(d) { return hcrow.indexOf(d.row) * cellSize; })
      ;

    t.selectAll(".rowLabel")
      .attr("y", function (d, i) { return hcrow.indexOf(i+1) * cellSize; })
      ;

    t.selectAll(".colLabel")
      .attr("y", function (d, i) { return hccol.indexOf(i+1) * cellSize; })
      ;

   }else if (value=="probecontrast"){
    var t = svg.transition().duration(3000);
    t.selectAll(".cell")
      .attr("x", function(d) { return (d.col - 1) * cellSize; })
      .attr("y", function(d) { return (d.row - 1) * cellSize; })
      ;

    t.selectAll(".rowLabel")
      .attr("y", function (d, i) { return i * cellSize; })
      ;

    t.selectAll(".colLabel")
      .attr("y", function (d, i) { return i * cellSize; })
      ;

   }else if (value=="probe"){
    var t = svg.transition().duration(3000);
    t.selectAll(".cell")
      .attr("y", function(d) { return (d.row - 1) * cellSize; })
      ;

    t.selectAll(".rowLabel")
      .attr("y", function (d, i) { return i * cellSize; })
      ;
   }else if (value=="contrast"){
    var t = svg.transition().duration(3000);
    t.selectAll(".cell")
      .attr("x", function(d) { return (d.col - 1) * cellSize; })
      ;
    t.selectAll(".colLabel")
      .attr("y", function (d, i) { return i * cellSize; })
      ;
   }
  }
  // 
  var sa=d3.select(".g3")
      .on("mousedown", function() {
          if( !d3.event.altKey) {
             d3.selectAll(".cell-selected").classed("cell-selected",false);
             d3.selectAll(".rowLabel").classed("text-selected",false);
             d3.selectAll(".colLabel").classed("text-selected",false);
          }
         var p = d3.mouse(this);
         sa.append("rect")
         .attr({
             rx      : 0,
             ry      : 0,
             class   : "selection",
             x       : p[0],
             y       : p[1],
             width   : 1,
             height  : 1
         })
      })
      .on("mousemove", function() {
         var s = sa.select("rect.selection");
      
         if(!s.empty()) {
             var p = d3.mouse(this),
                 d = {
                     x       : parseInt(s.attr("x"), 10),
                     y       : parseInt(s.attr("y"), 10),
                     width   : parseInt(s.attr("width"), 10),
                     height  : parseInt(s.attr("height"), 10)
                 },
                 move = {
                     x : p[0] - d.x,
                     y : p[1] - d.y
                 }
             ;
      
             if(move.x < 1 || (move.x*2<d.width)) {
                 d.x = p[0];
                 d.width -= move.x;
             } else {
                 d.width = move.x;       
             }
      
             if(move.y < 1 || (move.y*2<d.height)) {
                 d.y = p[1];
                 d.height -= move.y;
             } else {
                 d.height = move.y;       
             }
             s.attr(d);
      
                 // deselect all temporary selected state objects
             d3.selectAll('.cell-selection.cell-selected').classed("cell-selected", false);
             d3.selectAll(".text-selection.text-selected").classed("text-selected",false);

             d3.selectAll('.cell').filter(function(cell_d, i) {
                 if(
                     !d3.select(this).classed("cell-selected") && 
                         // inner circle inside selection frame
                     (this.x.baseVal.value)+cellSize >= d.x && (this.x.baseVal.value)<=d.x+d.width && 
                     (this.y.baseVal.value)+cellSize >= d.y && (this.y.baseVal.value)<=d.y+d.height
                 ) {
      
                     d3.select(this)
                     .classed("cell-selection", true)
                     .classed("cell-selected", true);

                     d3.select(".r"+(cell_d.row-1))
                     .classed("text-selection",true)
                     .classed("text-selected",true);

                     d3.select(".c"+(cell_d.col-1))
                     .classed("text-selection",true)
                     .classed("text-selected",true);
                 }
             });
         }
      })
      .on("mouseup", function() {
            // remove selection frame
         sa.selectAll("rect.selection").remove();
      
             // remove temporary selection marker class
         d3.selectAll('.cell-selection').classed("cell-selection", false);
         d3.selectAll(".text-selection").classed("text-selection",false);
      })
      .on("mouseout", function() {
         if(d3.event.relatedTarget.tagName=='html') {
                 // remove selection frame
             sa.selectAll("rect.selection").remove();
                 // remove temporary selection marker class
             d3.selectAll('.cell-selection').classed("cell-selection", false);
             d3.selectAll(".rowLabel").classed("text-selected",false);
             d3.selectAll(".colLabel").classed("text-selected",false);
         }
      });
      //draw title
      for (var i=0;i<metadata.length;i++) {
          console.log(metadata[i].title);
          this.drawMeta(i, metadata[i].title, metadata[i].values, metadata.length)
      }
}

oncoHeatmap.prototype.drawMeta = function(pos, title, sample_meta, nmeta) {
  //d3.select("#legend_title").remove();
  //d3.select("#legend_title2").remove();
  //d3.selectAll(".sample_meta_cell").remove();
  //d3.selectAll(".sample_meta_text").remove();
  
  var uni_data = unique_array(sample_meta).sort();

  var colors = [];

  uni_data.forEach(function(){
    colors.push(getRandomColor());
  });

  console.log(JSON.stringify(colors));
  var svg = this.svg;
  var smp_legend = svg.selectAll(".smp_legend")
      .data(uni_data)
      .enter().append("g")
      ;
 
  legendElementWidth = 100;
  cellSize = this.cellSize;
  padding = this.padding;
  var y_pos = 5;  
  var text_widths = [];

  var meta_tooltip = d3.select("body")
    .append("div")
    .style("position", "absolute")
    .style("z-index", "10000")
    .style("visibility", "hidden")
    .style("color", "black")
    .style("background-color", "white")
    .style("border-radius", "6px")
    .style("padding", "2px")
    .style("border-style", "solid")

  svg.append("text")
    .attr("text-anchor", "left")
    .attr("x", 0)
    .attr("y", cellSize * 1.5)
    .attr("id", "click_help")
    .attr("transform", "translate(-" + this.margin.left + ",-" + this.margin.top + ")")
    .text("Click gene or sample to sort")
    .style('fill', 'blue')
    .attr('font-size', 14)
    .attr('font-weight', "bold")    

  //meta data label
  var rowSortOrder=true;
  svg.append("text")
    .attr("x", 0)
    .attr("y", cellSize * (pos))
    //.attr("y", function (d, i) { return (hcrow.indexOf(i+1) + metadata.length) * cellSize; })
    .attr("transform", "translate(-6," + cellSize / 1.5 + ")")
    .attr("id", "legend_title")
    .text(title.toUpperCase())
    .attr('font-size', 12)
    .attr('font-weight', "bold")
    .style('fill', 'red')
    .style("text-anchor", "end")
    .style("cursor", "hand")
    .on("mouseover", function(d) {d3.select(this).classed("text-hover",true);d3.select("#celltooltip").classed("hidden", true);})
    .on("mouseout" , function(d) {d3.select(this).classed("text-hover",false);})
    .on("click", function(d,i) {oncoHeatmap.metaSortOrder=!oncoHeatmap.metaSortOrder; sortMeta(pos,oncoHeatmap.metaSortOrder,nmeta);;})

  svg.append("text")
    .attr("text-anchor", "left")
    .attr("x", 0)
    .attr("y", this.lengendHeight + cellSize * (pos+1) * 1.2)
    .attr("id", "legend_title2")
    .attr("transform", "translate(-6,0)")
    .text(title.toUpperCase() + ":")
    .attr('font-weight', "bold")
    .style('fill', 'red') 
    .style("text-anchor", "end")
    .attr('font-size', 12);

  var title_width = d3.select("#legend_title").node().getComputedTextLength() + padding;

  smp_legend.append("text")
    .attr("class", "sample_meta_text" + pos + " mono")
    .text(function(d) { return d; })    
    //.attr("transform", "translate(-" + this.margin.left + ",+0)")
    .attr("x", function(d, i) { return legendElementWidth * i + cellSize + padding; })
    .attr("y", this.lengendHeight + cellSize * (pos+1) * 1.2)
    .each( function (d) {text_widths.push(this.getComputedTextLength());})
    ;

  
  d3.selectAll(".sample_meta_text" + pos)
      .attr("x", function (d, i) { return getTotalTextWidth(i) + (cellSize + padding * 0.5) * (i + 1); })
      ;
  

  var sample_meta_cells = svg.append("g")
      .selectAll(".sample_metag")
      .data(sample_meta)
      .enter()
      .append("rect")      
      .attr("x", function(d, i) {return i * cellSize;})
      .attr("y", pos * cellSize)
      .style("text-anchor", "end")
      .attr("class", function(d, i){return "cell-border cr"+(pos-1)+" cc"+(i-1) + " sample_meta" + pos + " c" + i;})
      .attr("width", cellSize)
      .attr("height", cellSize)
      .style("fill", function(d, i) { return colors[uni_data.indexOf(d)]; })
      .on("mouseover", function(d){meta_tooltip.text(d); return meta_tooltip.style("visibility", "visible");})
      .on("mousemove", function(){return meta_tooltip.style("top", (d3.event.pageY-10)+"px").style("left",(d3.event.pageX+10)+"px");})
      .on("mouseout", function(){return meta_tooltip.style("visibility", "hidden");});
      ;    

  
  smp_legend.append("rect")
    .attr("x", function(d, i) { return  getTotalTextWidth(i) + (cellSize + padding * 0.5) * i; })
	//.attr("x", function(d, i) { return  (cellSize + padding * 2 * 0) * i; })
    .attr("y", this.lengendHeight + cellSize*(pos+1)*1.2 - padding*2)
    .attr("class", "sample_meta_cell")
    .attr("width", cellSize-2)
    .attr("height", cellSize-2)
    //.attr("transform", "translate(-" + this.margin.left + ",+0)")
    .style("fill", function(d, i) { return colors[i]; })    
    ;
  
  d3.selectAll(".colLabel").each(function (d, i) {
      var color_idx = uni_data.indexOf(sample_meta[i]);
      if (color_idx != -1)
        d3.select(this).style("fill", colors[color_idx]);
    });
  /*
  d3.selectAll(".sample_meta").each(function (d, i) {
      var color_idx = uni_data.indexOf(sample_meta[i]);
      if (color_idx != -1)
        d3.select(this).style("fill", colors[color_idx]);
    });*/

  function getTotalTextWidth(up_to) {
    var total = 0;
    text_widths.forEach(function(d, i) {
      if (i < up_to)
        total += d + 5;
    })
	return total;
  }

  function sortMeta(i,sortOrder, nmeta){
       var t = svg.transition().duration(2000);
       var values=[];
       var sorted; // sorted is zero-based index
       console.log("i:" + i);
       d3.selectAll(".sample_meta"+i) 
         .filter(function(ce){
            values.push(ce);
          })
       ;
       // sort by meta
       sorted=d3.range(values.length).sort(function(a,b){ if(sortOrder){ return values[b].localeCompare(values[a]);}else{ return values[a].localeCompare(values[b]);}});
       //sorted=d3.range(values.length).sort(function (a,b) { return values[a] < values[b] ? -1 : values[a] > values[b] ? 1 : 0; });
       //console.log(sorted);
       t.selectAll(".cell")
           .attr("x", function(d) { return sorted.indexOf(d.col-1) * cellSize; })
           ;
       t.selectAll(".colLabel")
          .attr("y", function (d, i) { return sorted.indexOf(i) * cellSize; })
         ;
       for (var meta=0; meta<nmeta;meta++)
           t.selectAll(".sample_meta" + meta)
            .attr("x", function (d, i) { return sorted.indexOf(i) * cellSize; })
           ;
       
  }
}