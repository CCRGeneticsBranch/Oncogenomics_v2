<!DOCTYPE html>
<meta charset="utf-8">

{{ HTML::style('packages/muts-needle-plot/build/muts-needle-plot.css') }}
{{ HTML::script('js/jquery-3.6.0.min.js') }}
<style>

text {
  font: 12px sans-serif;
}

</style>
<script>
	$(document).ready(function() {
		//$("#loadingImage").css("display","block");
		$("#loadingImage").css("display","none");
		data={"domain":[{"name":"P53_TAD","coord":"5-29"},{"name":"P53","coord":"95-289"},{"name":"P53_tetramer","coord":"318-359"}],
		"sample_data":[{"coord":"200","category":"nonsynonymous SNV","value":2},{"coord":"141","category":"nonsynonymous SNV","value":4},{"coord":"126","category":"stopgain","value":4},{"coord":"130","category":"nonsynonymous SNV","value":4}],
		"ref_data":[{"coord":"200","category":"nonsynonymous SNV","value":"11"},{"coord":"141","category":"nonsynonymous SNV","value":"162"},{"coord":"126","category":"stopgain","value":"9"},{"coord":"130","category":"nonsynonymous SNV","value":"12"}],"min_coord":0,"max_coord":409};
		doPlot(data);
		/*$.ajax({ url: '{{url("/getMutationPlotData/$sid/$patient_id/$gene_id")}}', async: true, dataType: 'text', success: function(data) {
				data = JSON.parse(data);
				$("#loadingImage").css("display","none");
				doPlot(data);
			}
		});

		$('.needle-head').on('click', function(e) {

			alert(e.target.data);
			console.log($(e.target.data));

		});
*/
	});

	function nodeSelected(coord) {
		alert("gg" + coord);
	}
	
	function doPlot(data) {
	colorMap = {
		// mutation categories
		"nonsynonymous SNV": "yellow",
		"synonymous SNV": "green",
		"stopgained": "red",
		"frameshift deletion": "blue",
		"frameshift insertion": "orange",
		"nonframeshift deletion": "pink",
		"nonframeshift insertion": "purple",
		"stoplost": "lightblue",
		// regions
		"X-binding": "olive",
		"region1": "olive"
	};

	legends = {
		x: "Protein positions",
		y: "Number of mutation"
	};

	//Crate config Object
	plotConfig = {
		maxCoord :      data.max_coord,
		minCoord :      data.min_coord,
		height:         400,
		targetElement : "plot",
		mutationData:   data.sample_data,
		mutationRefData:   data.ref_data,
		regionData:     data.domain,
		colorMap:       colorMap,
		legends:        legends,
		responsive: 'resize'
	};

	// Instantiate a plot
	MutsNeedlePlot = require("muts-needle-plot");
	p = new MutsNeedlePlot(plotConfig);

	}
</script>
<body>
<div id='loadingImage'>
	<img src='{{url('/images/ajax-loader.gif')}}'></img>
</div>

{{$title}}
<div id="plot"> </div>
</body>

{{ HTML::script('packages/muts-needle-plot/build/muts-needle-plot.js') }}
{{ HTML::script('packages/muts-needle-plot/src/js/dependencies/d3.js') }}
{{ HTML::script('packages/muts-needle-plot/src/js/d3-svg-legend.js') }}
{{ HTML::script('packages/muts-needle-plot/src/js/dependencies/underscore.js') }}

<script>

	
	

</script>
