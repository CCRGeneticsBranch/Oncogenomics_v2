@extends('layouts.default')
@section('content')

{{-- HTML::style('packages/igv.js/igv.css') --}}
{{ HTML::style('https://maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css') }}
{{ HTML::script('packages/jquery-ui-1.11.4/jquery-ui.min.js') }}
{{HTML::script('packages/igv.js/igv.min.js')}}
<!--script src="https://igv.org/web/release/2.10.1/dist/igv.min.js"></script-->





<script type="text/javascript">

    var track_infos = {};
    
	var track_hight = 500;	
    
    $(document).ready(function() {
        
        var div = $("#igvDiv")[0],
                options = {
                    showNavigation: true,
                    showKaryo : false,
                    showRuler : true,
                    showCenterGuide : true,
                    showCursorTrackingGuide : true,
                    locus: "{{$symbol}}",                    
                    //genome: "hg19",
                    reference: {fastaURL: "{{url('/ref/hg19.fasta')}}", cytobandURL: "{{url('/ref/cytoBand.txt')}}"},
                    tracks: [
                        @foreach ($junctions as $sample_id => $filenames)
                        {
                            type: 'merged',
                            name: '{{$sample_id}}',
                            height: 70,
                            autoscale: true, 
                            tracks:    
                            	[
                                    {
                                        type: 'wig',
                                        name: 'Coverage',
                                        format: 'tdf', 
                                        autoscaleGroup: "group1",                                       
                                        url: '{{url("/getBigWig/$path/$patient_id/$case_id/$sample_id/".$filenames["tdf"])}}'
                                    },
                                    {
                                        type: 'junction',
                                        name: 'Junctions',
                                        format: 'bed',
                                        url: '{{url("/getBigWig/$path/$patient_id/$case_id/$sample_id/".$filenames["bed"])}}',
                                        indexURL: '{{url("/getBigWig/$path/$patient_id/$case_id/$sample_id/".$filenames["bed"].".tbi")}}',
                                        displayMode: 'COLLAPSED',
                                        minUniquelyMappedReads: 1,
                                        minTotalReads: 1,
                                        maxFractionMultiMappedReads: 1,
                                        minSplicedAlignmentOverhang: 0,
                                        thicknessBasedOn: 'numUniqueReads', //options: numUniqueReads (default), numReads, isAnnotatedJunction
                                        bounceHeightBasedOn: 'random', //options: random (default), distance, thickness
                                        colorBy: 'isAnnotatedJunction', //options: numUniqueReads (default), numReads, isAnnotatedJunction, strand, motif
                                        labelUniqueReadCount: true,
                                        labelMultiMappedReadCount: true,
                                        labelTotalReadCount: false,
                                        labelMotif: true,
                                        labelIsAnnotatedJunction: " [A]",
                                        hideAnnotatedJunctions: false,
                                        hideUnannotatedJunctions: false,
                                        //hideMotifs: ['CT/AC', 'non-canonical'], //options: 'GT/AG', 'CT/AC', 'GC/AG', 'CT/GC', 'AT/AC', 'GT/AT', 'non-canonical'
                                    }
                                ],
                        },
                        @endforeach                         
                        {
                            url: "{{url('/ref/gencode.v38lift37.annotation.sorted.genename_changed.canonical.gtf.gz')}}",
                            indexURL: "{{url('/ref/gencode.v38lift37.annotation.sorted.genename_changed.canonical.gtf.gz.tbi')}}",
                            name: 'Ensembl Canonical',
                            height : 50,
                            format: 'gtf',
                            displayMode: "EXPANDED",
                            displayName: "transcript_id",
                            visibilityWindow: 10000000
                        },                        
                        {
                            url: "{{url('/ref/gencode.v38lift37.annotation.sorted.genename_changed.gtf.gz')}}",
                            indexURL: "{{url('/ref/gencode.v38lift37.annotation.sorted.genename_changed.gtf.gz.tbi')}}",
                            name: 'Gencode',
                            height : 150,
                            format: 'gtf',
                            displayMode: "EXPANDED",
                            visibilityWindow: 10000000
                        }
                    ]
                };

        igv.createBrowser(div, options).then(function (browser) {
                    igv.browser = browser;
                    console.log("Created IGV browser");                    
                });
        
        
        $('.ckSample').on('change', function() {
        	//var location = 45873433;
        	//console.log(JSON.stringify(igv.browser.trackViews[0].track));
        	//var bam_track = igv.browser.trackViews[2].track;
        	//bam_track.altClick(center, null);
        	//bam_track.alignmentTrack.sortAlignmentRows(location, {sort: "NUCLEOTIDE"});
        	//bam_track.trackView.redrawTile(bam_track.featureSource.alignmentContainer);
        	

        	var sample_name = $(this).val();
        	if ($(this).is(':checked')) {            	
            	track_info = track_infos[sample_name];
            	var url = '{{url('/getBAM/')}}' + '/' + track_info.sample_file;
            	var track = igv.browser.loadTrack({url: url, name: sample_name, height: track_hight, colorBy : 'strand', samplingDepth : Number.MAX_VALUE}).then(function (newTrack) {
                    sort_center(); 
                });;
            }
            else {
            	var tracks = igv.browser.trackViews;
        		for (var i = 0; i < tracks.length; i++) {
            		var track = tracks[i].track;
            		if (track.name == sample_name)
            			igv.browser.removeTrack(track);
        		}
        	}
            sort_center(); 
        });

		$('#btnSort').on('click', function() {
			sort_center();
		});        

    });    

</script>


<hr>
<div class="container-fluid" id="igvDiv" style="padding:5px; border:1px solid lightgray"></div>



@stop