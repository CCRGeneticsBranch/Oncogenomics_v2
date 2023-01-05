    
    function capitalize(s) {
        return s[0].toUpperCase() + s.slice(1);
    }

    function getInnerText(innerHtml) {
        var elem = document.createElement("div");
        elem.innerHTML = innerHtml;
        return elem.innerText.trim();
    }

    function getTitleText(innerHtml) {
        var elem = document.createElement("div");
        elem.innerHTML = innerHtml;
        var t  = elem.childNodes[0].title;
        if (t == undefined)
            return elem.innerText;
        return elem.childNodes[0].title;
    }

    function getColor(value){
        var hue=((1-value)*120).toString(10);
        return ["hsl(",hue,",100%,50%)"].join("");
    }

    function getRandomColor() {
        var letters = '0123456789ABCDEF'.split('');
        var color = '#';
        for (var i = 0; i < 6; i++) {
            color += letters[Math.round(Math.random() * 10)];
        }
        return color;
    }

    function mergeArrays(a, b){
        var hash = {};
        var ret = [];

        for(var i=0; i < a.length; i++){
            var e = a[i];
            if (!hash[e]){
                hash[e] = true;
                ret.push(e);
            }
        }

        for(var i=0; i < b.length; i++){
            var e = b[i];
            if (!hash[e]){
                hash[e] = true;
                ret.push(e);
            }
        }
        return ret;
    }

    function generateUUID() {
        var d = new Date().getTime();
        var uuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = (d + Math.random()*16)%16 | 0;
            d = Math.floor(d/16);
            return (c=='x' ? r : (r&0x3|0x8)).toString(16);
        });
        return uuid;
    };

    function removeElement(arr, elem) {
        var idx = arr.indexOf(elem);
        if (idx > -1) {
            arr.splice(idx, 1);
        }
    }

    function getPercentile(d, percentile) {
        var data = d.slice(0);
        data.sort(numSort);
        var index = (percentile/100) * data.length;
        var result;
        if (Math.floor(index) == index) {
             result = (data[(index-1)] + data[index])/2;
        }
        else {
            result = data[Math.floor(index)];
        }
        return result;
    }
    //because .sort() doesn't sort numbers correctly
    function numSort(a,b) { 
        return a - b; 
    } 

    function getBoxValues(data, series_idx, names) {
        //if (data.length == 1)
        //   return {data:[data[0], data[0], data[0], data[0], data[0]], outliers:[]]};
        var q1     = getPercentile(data, 25);
        var median = getPercentile(data, 50);
        var q3     = getPercentile(data, 75);
        
        var low    = Math.min.apply(Math,data);    
        var high   = Math.max.apply(Math,data);        
        low    = Math.max(low, q1-(q3-q1)*1.5);
        high    = Math.min(high, q3+(q3-q1)*1.5);
        var outliers = [];
        data.forEach(function(d, idx) {
            if (d > high || d < low)
              outliers.push({name:names[idx], x:series_idx, y:d});
        });
        return {data:[low, q1, median, q3, high], outliers:outliers};
    }

    function isInt(value) {
        var x;
        if (isNaN(value)) {
            return false;
        }
        x = parseInt(value);
        return (x | 0) === x;
    }

    function isNumber(value) {
        var x;
        if (isNaN(parseFloat(value))) {
            return false;
        }
        return true;
        //x = parseFloat(value);
        //return (x | 0) === x;
    }

    function sortByArray(value_array, target_array, desc=true) {
        var all = [];
        for (var i = 0; i < target_array.length; i++) {
            all.push({ 'A': value_array[i], 'B': target_array[i] });
        }

        all.sort(function(a, b) {
            if (desc)
                return b.A - a.A;
            return a.A - b.A;
        });

        value_array = [];
        target_array = [];

        for (var i = 0; i < all.length; i++) {
            value_array.push(all[i].A);
            target_array.push(all[i].B);
        }
        return {value_array: value_array, target_array: target_array};
    }

    function getSortedScatterValues(data, names, highlight_samples) {
        /*
        names.forEach(function(d,idx) {
            console.log(d + ": " + data[idx]);
        });
        */
        var data = getNumberArray(data);
        var sorted = sortByArray(data, names);
        
        data = sorted.value_array;
        names = sorted.target_array;

        //console.log("value array length: " + data.length);
        //console.log("sample array length: " + names.length);
        //console.log("highlight_samples:" + JSON.stringify(highlight_samples));
        var values = [];
        data.forEach(function(d,idx) {
            var s = 5;
            var lc = 'rgb(119, 152, 191)';
            var fc = 'rgba(119, 152, 191, .1)';
            var name = names[idx];
            for (var i=0;i<highlight_samples.length; i++) {
                if (highlight_samples[i] == name) {
                    s = 12;
                    lc = 'rgba(223, 83, 83, 1)';
                    fc = 'rgba(223, 83, 83, .5)';
                }
            }          
            values.push({name:name, x:idx, y:d, marker: {
                radius: s, fillColor:fc, lineColor: lc, lineWidth:1, states: { hover: { radius: s+2, fillColor:lc }}
            }});
        });
        return values;
    }

    function standardDeviation(values){
        var avg = average(values);
      
        var squareDiffs = values.map(function(value){
                var diff = value - avg;
                var sqrDiff = diff * diff;
                return sqrDiff;
            });
      
        var avgSquareDiff = average(squareDiffs);

        var stdDev = Math.sqrt(avgSquareDiff);
        return stdDev;
    }

    function average(data){
        var sum = data.reduce(function(sum, value){
            return sum + value;
            }, 0);

        var avg = sum / data.length;
        return avg;
    }

    function zscore(values){
        var mean = average(values);
        var std = standardDeviation(values);
        var zscores = [];
        values.forEach(function(value) {
            zscores.push((value - mean)/std);
        })        
        return zscores;
    }

    function flatten(arr) {
        return arr.reduce(function (flat, toFlatten) {
            return flat.concat(Array.isArray(toFlatten) ? flatten(toFlatten) : toFlatten);
        }, []);
    }

    function max_arr(a) {
        a = flatten(a);
        max=a.reduce(function(max, arr) { 
            return Math.max(max, arr); 
            }, -Infinity)
        return max;
    }

    function min_arr(a) {
        a = flatten(a);
        min=a.reduce(function(min, arr) { 
            return Math.min(min, arr); 
            }, Infinity)
        return min;
    }
    
    function objAttrToArray(obj) {
        var arr = [];
        for (var i in obj) {
            if (Object.prototype.hasOwnProperty.call(obj, i)) {
                arr.push(i);                
            }
        }
        return arr;
    }

    function unique_array(arr) {
        var a = [];
        for (var i=0, l=arr.length; i<l; i++)
            if (a.indexOf(arr[i]) === -1 && arr[i] !== '')
                a.push(arr[i]);
        return a;
    }

    function newFilledArray(length, val) {
        var array = [];
        for (var i = 0; i < length; i++) {
            array[i] = val;
        }
        return array;
    }

    function countArray(arr) {
        var counts = {};
        arr.forEach(function(x) { counts[x] = (counts[x] || 0)+1; });
        return counts;
    }

    function getPieChartData(attr_value) {
        var values = countArray(attr_value);
        var data = [];
        for (var value in values)
            data.push({name: value, y: values[value]});
        return data;        
    }

    function getHistData(attr_value) {
        var binned_data = binData(attr_value);
        return binned_data;        
    }

    function isNumberArray(arr) {
        var hasN = false;
        for (var i in arr) {
            x = arr[i];
            x = x.trim();
            if (!isNumber(x)) {                
                if (x !== '' && x !== 'NA' && x !== 'N/A' && x !== '.' && x != 'NoValue' && x != 'unknown' && x != '-') {
                    return false;                
                }
            } else {
                hasN = true;
            }
        }
        return hasN;
    }

    function getNumberArray(arr) {
        var data = [];
        for (var i in arr) {
            x = arr[i];
            //console.log(x + ':' +isNaN(x));
            if (!isNaN(parseFloat(x))) {
                data.push(parseFloat(x));    
            } else {
                //console.log(x);
            }
        }
        return data;
    }

    function createColorRange(c1, c2) {
        var colorList = [], tmpColor;
        for (var i=0; i<255; i++) {
            tmpColor = new GColor();
            tmpColor.r = c1.r + ((i*(c2.r-c1.r))/255);
            tmpColor.g = c1.g + ((i*(c2.g-c1.g))/255);
            tmpColor.b = c1.b + ((i*(c2.b-c1.b))/255);
            colorList.push(tmpColor);
        }
        return colorList;
    }

    function binData(data) {
        var hData = new Array(), //the output array
            size = data.length, //how many data points
            bins = Math.round(Math.sqrt(size)); //determine how many bins we need
            bins = bins > 50 ? 50 : bins; //adjust if more than 50 cells
        var max = Math.max.apply(null, data), //lowest data value
            min = Math.min.apply(null, data), //highest data value
            range = max - min, //total range of the data
            width = range / bins, //size of the bins
            bin_bottom, //place holders for the bounds of each bin
            bin_top;
        //loop through the number of cells
        for (var i = 0; i < bins; i++) {
            //set the upper and lower limits of the current cell
            bin_bottom = min + (i * width);
            bin_top = bin_bottom + width;

            //check for and set the x value of the bin
            if (!hData[i]) {
                hData[i] = new Array();
                hData[i][0] = bin_bottom + (width / 2);
                hData[i][2] = bin_bottom;
                hData[i][3] = bin_top;
            }

            //loop through the data to see if it fits in this bin
            for (var j = 0; j < size; j++) {
                var x = data[j];
                if (!isNumber(x))
                    continue;
                //adjust if it's the first pass
                i == 0 && j == 0 ? bin_bottom -= 1 : bin_bottom = bin_bottom;

                //if it fits in the bin, add it
                if (x > bin_bottom && x <= bin_top) {
                    !hData[i][1] ? hData[i][1] = 1 : hData[i][1]++;                    
                }
            }
        }
        $.each(hData, function(i, point) {
            if (typeof point[1] == 'undefined') {
                hData[i][1] = 0;
            }
        });
        return hData;
    }

    /**
     * Code extracted from https://github.com/Tom-Alexander/regression-js/
     * Human readable formulas: 
     * 
     *              N * Σ(XY) - Σ(X) 
     * intercept = ---------------------
     *              N * Σ(X^2) - Σ(X)^2
     * 
     * correlation = N * Σ(XY) - Σ(X) * Σ (Y) / √ (  N * Σ(X^2) - Σ(X) ) * ( N * Σ(Y^2) - Σ(Y)^2 ) ) )
     * 
     */
    function linear_regression(data, decimalPlaces) {
        var sum = [0, 0, 0, 0, 0], n = 0, results = [], N = data.length;

        for (; n < data.length; n++) {
          if (data[n]['x'] != null) {
            data[n][0] = data[n]['x'];
            data[n][1] = data[n]['y'];
          }
          if (data[n][1] != null) {
            sum[0] += data[n][0]; //Σ(X) 
            sum[1] += data[n][1]; //Σ(Y)
            sum[2] += data[n][0] * data[n][0]; //Σ(X^2)
            sum[3] += data[n][0] * data[n][1]; //Σ(XY)
            sum[4] += data[n][1] * data[n][1]; //Σ(Y^2)
          } else {
            N -= 1;
          }
        }

        var gradient = (N * sum[3] - sum[0] * sum[1]) / (N * sum[2] - sum[0] * sum[0]);
        var intercept = (sum[1] / N) - (gradient * sum[0]) / N;
        // var correlation = (N * sum[3] - sum[0] * sum[1]) / Math.sqrt((N * sum[2] - sum[0] * sum[0]) * (N * sum[4] - sum[1] * sum[1]));
        
        for (var i = 0, len = data.length; i < len; i++) {
            var coorY = data[i][0] * gradient + intercept;
            if (decimalPlaces)
                coorY = parseFloat(coorY.toFixed(decimalPlaces));
            var coordinate = [data[i][0], coorY];
            results.push(coordinate);
        }

        results.sort(function(a,b){
           if(a[0] > b[0]){ return 1}
            if(a[0] < b[0]){ return -1}
              return 0;
        });

        var string = 'y = ' + Math.round(gradient*100) / 100 + 'x + ' + Math.round(intercept*100) / 100;
        return {equation: [gradient, intercept], points: results, string: string};
    }

    function parseJSON(response) {
        try{
            if (response == "AuthorizeFailed") {
                alert("Session timeout or unauthorized!");
                location.reload();
            }
            return JSON.parse(response);
        }catch(e){
            location.reload();
            //alert('Session expired!');
            console.log('cannot parse Ajax response. It should be session timeout. Error: ' + e);                  
            window.location = login_url;
        }
    }    

    function showPieChart(div_id, title, data, click_handler=null, show_legend=false, data_label=true, exporting=true, series_name='Number of patients') {
        $('#' + div_id).highcharts({
            credits: false,
            chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false,              
                type: 'pie'
            },
            title: {
                text: title
            },
            tooltip: {
                pointFormat: '{series.name}: <b>{point.y}/{point.total} ({point.percentage:.1f}%)</b>'
            },
            exporting: { enabled: exporting },            
            plotOptions: {
                pie: {
                    allowPointSelect: true,
                    cursor: 'pointer',
                    dataLabels: {
                        enabled: data_label,
                        format: '<b>{point.name}</b>: {point.y}/{point.total}',
                        style: {
                            color: (Highcharts.theme && Highcharts.theme.contrastTextColor) || 'black'
                        }
                    },
                    showInLegend: show_legend
                }
            },
            series: [{
                name: series_name,
                colorByPoint: true,
                data: data,                
                point: {
                    events: {
                        click: function(e) {
                            click_handler(this);                            
                        }
                    }
                }
            }]
        });
    }
    
    function showHistChart(div_id, title, data, click_handler=null) {       
        $('#' + div_id).highcharts({
            credits: false,
            chart: {
              type: 'column',             
            },
            title: {
              text: title,
              x: 25
            },          
            legend: {
              enabled: false
            },            
            exporting: { enabled: true },
            tooltip: {},
            plotOptions: {
              series: {
                pointPadding: 0,
                groupPadding: 0,
                borderWidth: 0.5,
                borderColor: 'rgba(255,255,255,0.5)',
                color: Highcharts.getOptions().colors[1]
              }
            },
            xAxis: {
              title: {
                text: title
              }
            },
            yAxis: {
              title: {
                text: 'Number of patients'
              }
            },
            series: [{
                name: 'Number of patients',
                data: data,
                point: {
                    events: {
                        click: function(e) {
                            if (click_handler != null)
                                click_handler(this);                            
                        }
                    }
                }
            }]
        });
    }

    

    function showColumnPlot(div_id, title, y_label, raw_data, value_format='.4f', click_handler=null) {
        var data = [];
        for (var i in raw_data) {
            x = raw_data[i];
            if (!isNaN(x[1]))
                data.push([x[0], parseFloat(x[1])]);
        }
        $('#' + div_id).highcharts({
        credits: false,
        exporting: {
              enabled: false
            },
        chart: {
            type: 'column'            
        },
        title: {
            text: title
        },        
        xAxis: {
            type: 'category',
            labels: {
                rotation: -90,
                style: {
                    fontSize: '10px',
                    fontFamily: 'Verdana, sans-serif'
                }
            }
        },
        yAxis: {
            //min: 0,
            title: {
                text: y_label
            }
        },
        legend: {
            enabled: false
        },
        tooltip: {
            pointFormat: y_label + ': <b>{point.y:' + value_format + '} </b>'
        },
        series: [{
            name: 'Population',
            data: data,
            point: {
                    events: {
                        click: function(e) {
                            if (click_handler != null)
                                click_handler(this);                            
                        }
                    }
            },
            dataLabels: {
                enabled: true,
                rotation: -90,
                color: '#FFFFFF',
                align: 'right',
                format: '{point.y:' + value_format + '}', // one decimal
                y: 10, // 10 pixels down from the top
                style: {
                    fontSize: '13px',
                    fontFamily: 'Verdana, sans-serif'
                }
            }
        }]
    });
    }

    function showLinePlot(div_id, title, x_labels, data) {
        $('#' + div_id).highcharts({
            credits: false,
            title: {
                text: title,
                x: -20 //center
            },        
            xAxis: {
                categories: x_labels
            },
            yAxis: {
                title: {
                    text: 'Variance'
                },
                plotLines: [{
                    value: 0,
                    width: 1,
                    color: '#808080'
                }]
            },
            tooltip: {
                valueSuffix: ''
            },
            legend: {
                enabled: false
            },
            series: [{
                name: 'Variance',
                data: data
            }]
        });
    }

    function getMax(arr) {
        let len = arr.length;
        let max = -Infinity;

        while (len--) {
            max = arr[len] > max ? arr[len] : max;
        }
        return max;
    }

    function getMin(arr) {
        let len = arr.length;
        let min = Infinity;

        while (len--) {
            min = arr[len] < min ? arr[len] : min;
        }
        return min;
    }

    function minMax2DArray(arr, idx) {
        var max = -Number.MAX_VALUE,
            min = Number.MAX_VALUE;
        arr.forEach(function(e) {
            if (max < e[idx]) {
                max = e[idx];
            }
            if (min > e[idx]) {
               min = e[idx];
           }
        });
        return {max: max, min: min};
    }

    function show3DScatter(div_id, title, x_title, y_title, z_title, data, show_label=false, legend_height=50) {
        max = 0;
        data.forEach(function(d){            
            d.data.forEach(function(v){                
                var m = Math.max(Math.abs(v.x), Math.abs(v.y), Math.abs(v.z));
                if (m > max)
                    max = m;
            });            
        })
        console.log(max);
        //max = max + 10;
        
        if (pca_plot != null) {
            //pca_plot.destroy();
            //$('#' + div_id).empty();
            //console.log(JSON.stringify(pca_plot.series));

            //pca_plot.series.setData([]);
            //pca_plot.redraw();
            //return;
        }
        /*
         Highcharts.getOptions().colors = $.map(Highcharts.getOptions().colors, function (color) {
            return {
                radialGradient: {
                    cx: 0.4,
                    cy: 0.3,
                    r: 0.5
                },
                stops: [
                    [0, color],
                    [1, Highcharts.Color(color).brighten(-0.2).get('rgb')]
                ]
            };
        });
*/

        // Set up the chart
        pca_plot = new Highcharts.Chart({
            credits: false,
            chart: {                
                renderTo: div_id,
                margin: [100,100,100 + legend_height,100],
                type: 'scatter',
                options3d: {
                    enabled: true,
                    alpha: 10,
                    beta: 30,
                    depth: 500,
                    width:500,
                    height:500,
                    viewDistance: 50,
                    fitToPlot: false,
                    frame: {
                        bottom: { size: 1, color: 'rgba(0,0,0,0.02)' },
                        back: { size: 1, color: 'rgba(0,0,0,0.04)' },
                        side: { size: 1, color: 'rgba(0,0,0,0.06)' }
                    }
                }
            },
            title: {
                text: title
            },
            subtitle: {
                text: 'Click and drag the plot area to rotate in space'
            },
            plotOptions: {
                series: {
                    turboThreshold: 10000,
                    marker: {
                            radius: 6
                    },
                    dataLabels: {
                        format: "{point.name}",
                        enabled: show_label,
                    },
                }                
            },
            tooltip: {
                formatter: function(chart) {
                    var p = this.point;
                    return '<font color=red>Series:</font>' + this.series.name + '<br>' + 
                        '<font color=red>Sample:</font>' + p.name + '<br>' + 
                       '<font color=red>PC1:</font>' + Math.round(p.x, 2) + '<br>' + 
                       '<font color=red>PC2:</font>' + Math.round(p.y, 2) + '<br>' + 
                       '<font color=red>PC3:</font>' + Math.round(p.z, 2) + '<br>'                       
                }
            },
            yAxis: {
                max: max,
                title: {text: y_title}
            },
            xAxis: {
                max: max,
                title: {text: x_title}
            },
            zAxis: {
                max: max,
                title: {text: z_title}
            },
            legend: {
                align: 'bottom',
                verticalAlign: 'bottom',
                layout: 'horizontal'
            },            
            series: data
        });        


        // Add mouse events for rotation
        $(pca_plot.container).bind('mousedown.hc touchstart.hc', function (eStart) {
            eStart = pca_plot.pointer.normalize(eStart);

            var posX = eStart.pageX,
                posY = eStart.pageY,
                alpha = pca_plot.options.chart.options3d.alpha,
                beta = pca_plot.options.chart.options3d.beta,
                newAlpha,
                newBeta,
                sensitivity = 5; // lower is more sensitive

            $(document).bind({
                'mousemove.hc touchdrag.hc': function (e) {
                    // Run beta
                    newBeta = beta + (posX - e.pageX) / sensitivity;
                    pca_plot.options.chart.options3d.beta = newBeta;

                    // Run alpha
                    newAlpha = alpha + (e.pageY - posY) / sensitivity;
                    pca_plot.options.chart.options3d.alpha = newAlpha;

                    pca_plot.redraw(false);
                },
                'mouseup touchend': function () {
                    $(document).unbind('.hc');
                }
            });
        });        
        

    }

    function drawStackPlot(div_id, title, categories, series, legend=false, x_title="Genes", y_title="# of patients", click_handler=null) {
        //console.log(JSON.stringify(series));
        Highcharts.chart(div_id, {
                credits: false,
                chart: {
                    type: 'column',
                    zoomType: 'x',
                },
                title: {
                    text: title
                },
                xAxis: {
                    categories: categories,
                    title: { x_title}
                },
                yAxis: {
                    min: 0,
                    title: {
                        text: y_title
                    },
                    stackLabels: {
                        enabled: true,
                        style: {
                            fontWeight: 'bold',
                            color: (Highcharts.theme && Highcharts.theme.textColor) || 'gray'
                        }
                    }
                },
                legend: {
                    enabled: legend
                },
                tooltip: {
                    headerFormat: '<b>{point.x}</b><br/>',
                    pointFormat: '{series.name}: {point.y}{point.raw} <br/>Total: {point.stackTotal}'
                },
                plotOptions: {
                    series: {
                        cursor: 'pointer',
                        point: {
                            events: {
                                click: function () {
                                    if (click_handler != null) {
                                        click_handler(this, title);
                                    }
                                    //alert('Category: ' + this.category + ', value: ' + this.y);
                                }
                            }
                        }
                    },
                    column: {
                        stacking: 'normal',
                        groupPadding: 0,
                        pointPadding: 0,
                        dataLabels: {
                            enabled: false,
                            //color: (Highcharts.theme && Highcharts.theme.dataLabelsColor) || 'white'
                        }
                    }
                },
                series: series
            });
    }

    function redrawGroup(chart) {
            y_lines.forEach(function(d){
                y = chart.yAxis[0].toPixels(d[0]);
                x1 = chart.xAxis[0].toPixels(d[1]);
                x2 = chart.xAxis[0].toPixels(d[2]);                
                //console.log("x1:" + x1 + " x2:" + x2 + " y:" + y);
                chart.renderer.path(['M',x1, y, 'L', x2, y])
                    .attr({
                        'stroke-width': 1,
                        stroke: 'pink',
                        style: {dashStyle: 'Dash'}
                        })
                    .add();
            })
            
    };

    function drawGroupScatterPlot(div_id, title, data, x_title="Samples", y_title="Expression", click_handler=null, show_value=false, search_text='') {
        var scatter_data = [];
        var breaks = [];
        var x_lines = [];
        var y_lines = [];
        var labels = [];
        var idx = 0;
        var pre_break = 0;
        var lc = 'rgb(119, 152, 191)';
        var fc = 'rgba(119, 152, 191, .1)';
        var dot_size = 3;
        data.target_array.forEach(function(d, cat_idx){
            //console.log(JSON.stringify(d));                     
            var values = d.data.value_array;
            values.forEach(function(v, i){                
                //scatter_data.push([idx++, v]);
                v = Math.round(v*100) / 100
                scatter_data.push({sample_name:d.data.target_array[i].sample_name, patient_id: d.data.target_array[i].patient_id, cat:d.category, x:idx++, y:v, marker: {
                    radius: dot_size, fillColor:fc, lineColor: lc, lineWidth:1, states: { hover: { radius: dot_size + 2, fillColor:'pink' }}
                }});
            })
            var pos = parseInt((pre_break + idx)/2);
            breaks.push(pos);
            y_lines.push([data.value_array[cat_idx], (pre_break == 0) ? 0 : pre_break+5, idx + 5]);
            pre_break = idx;
            key = "p" + pos;
            labels[key] = d.category;
            x_lines.push( {color: 'lightgray',dashStyle: 'solid', width: 1, value: idx + 5} );            
            idx = idx + 10;
        })

        //console.log(JSON.stringify(breaks));

        var draw_median = function(chart) {
            var chart = this;
            $('.median_line').remove() ;
            y_lines.forEach(function(d){
                y = chart.yAxis[0].toPixels(d[0]);
                x1 = chart.xAxis[0].toPixels(d[1]);
                x2 = chart.xAxis[0].toPixels(d[2]);
                //if (x1 < 0)
                //    return;
                chart.renderer.path(['M',x1, y, 'L', x2, y])
                                .attr({
                                    'stroke-width': 2,
                                    stroke: 'pink',
                                    style: {dashStyle: 'Dash'},
                                    class: 'median_line'
                                    })
                                .add();
                })
        }
        var y_offset = -4;
        dataLabels = {
                        enabled: true,
                        useHTML: true,
                        rotation : -50,
                        overflow: 'none',
                        align: 'left',
                        crop: false,
                        y : y_offset,
                        //format: label_format,
                        formatter: function() {
                            var label_format = '';
                            if (search_text.trim() != '')
                                if (this.point.sample_name.toUpperCase().indexOf(search_text.toUpperCase()) != -1) {
                                    y_offset = 0;
                                    return this.point.y + ' - <font color="red">' + this.point.sample_name + '</font>';
                                }
                            if (show_value) {
                                label_format = this.point.y;
                                y_offset = 0;
                            }
                            return label_format;
                        },
                        style: {
                            color: 'red',
                            fontSize: '10px',
                            //fontFamily: 'Verdana, sans-serif'
                        }
                    }
        $('#' + div_id).highcharts({
            credits: false,
            chart: {
                type: 'scatter',
                zoomType: 'x',
                events: {
                    load: draw_median,
                    redraw : draw_median                    
                }
            },
            title: {
                text: title,
                style: { "color": "#333333", "fontSize": "14px" }
            },       
            xAxis: {
                title: {
                    enabled: true,
                    style: { "color": "#333333", "fontSize": "16px" },
                    text: x_title
                },
                labels: {
                    formatter:function() {
                        return labels['p' + this.value];
                    },
                    rotation : 300,
                    style: { fontSize:'13px' }
                },
                tickPositioner: function() {
                    var ticks = breaks.slice();
                    //console.log(JSON.stringify(breaks));
                    return ticks;
                },                
                plotLines : x_lines,
                tickLength : 0
            },
            yAxis: {
                title: {
                    text: y_title,
                    style: { "color": "#333333", "fontSize": "14px" }
                },
                plotLines : y_lines,
            },
            
            legend: {
                enabled: false
            },            
            plotOptions: {                
                scatter: {
                    marker: {
                        //radius: 8,
                        states: {
                            hover: {
                                enabled: true,
                                lineColor: 'rgb(100,100,100)'
                            }
                        }
                    },
                    states: {
                        hover: {
                            marker: {
                                enabled: false
                            }
                        }
                    },
                    tooltip: {
                        useHTML: true,
                        headerFormat: '',
                        pointFormat: "<b>Sample Name: </b>{point.sample_name}:<br><br><b>Value: </b>{point.y}",
                    },
                    dataLabels: dataLabels
                }
            },
            series: [{
                turboThreshold : 0,
                name: '',
                //color: 'rgba(223, 83, 83, .5)',
                data: scatter_data,
                cursor: 'pointer',
                point: {
                    events: {
                        click: function() {
                            if (click_handler != null) {
                                click_handler(this);
                            }
                            //console.log(this.patient_id);
                        }
                    }
                }

            }],            

        }, 
        function(chart) {
            
            
        });
        
    }

    function drawScatterPlot(div_id, title, values, x_title="Samples", y_title="Expression", click_handler=null) {
        $('#' + div_id).highcharts({
            credits: false,
            chart: {
                type: 'scatter',
                zoomType: 'xy'
            },
            title: {
                text: title,
                style: { "color": "#333333", "fontSize": "14px" }
            },       
            xAxis: {
                title: {
                    enabled: true,
                    text: x_title
                },
                startOnTick: false,
                endOnTick: false
            },
            yAxis: {
                title: {
                    text: y_title
                }
            },
            
            legend: {
                enabled: false
            },
            
            plotOptions: {                
                series: {
                    turboThreshold: 10000,
                },
                scatter: {                    
                    marker: {
                        //radius: 8,
                        states: {
                            hover: {
                                enabled: true,
                                lineColor: 'rgb(100,100,100)'
                            }
                        }
                    },
                    states: {
                        hover: {
                            marker: {
                                enabled: false
                            }
                        }
                    },
                    tooltip: {
                        headerFormat: '',
                        pointFormat: '<B>{point.name}:</B><BR>{point.y}'
                    }
                }
            },
            series: [{
                name: '',
                //color: 'rgba(223, 83, 83, .5)',
                data: values,
                cursor: 'pointer',
                point: {
                    events: {
                        click: function() {
                            if (click_handler != null) {
                                click_handler(this);
                            }
                            //console.log(this.patient_id);
                        }
                    }
                }

            }]
        });
    }

    