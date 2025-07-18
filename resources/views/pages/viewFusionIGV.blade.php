@extends('layouts.default')
@section('title', "FusionIGV--$patient_id--$case_name")
@section('content')

{{-- HTML::style('packages/igv.js/igv.css') --}}
{{ HTML::style('https://maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css') }}

<script type="module">

    import igv from '{!!url("packages/igv.js/igv.esm.min.js")!!}'

    const div = document.getElementById("igvDiv")

    const config = {
                    showNavigation: true,
                    showKaryo : false,
                    showRuler : true,
                    showCenterGuide : true,
                    showCursorTrackingGuide : true,
                    genome: "hg19",
                    //reference: {id: "hg19", fastaURL: "{{url('/ref/hg19.fasta')}}", cytobandURL: "{{url('/ref/cytoBand.txt')}}"},
                    locus: ['{{"$left_chr:".($left_position-25)."-".($left_position+25)}}', '{{"$right_chr:".($right_position-25)."-".($right_position+25)}}'],
                    tracks: [ 
                        {
                            url: '{{url('/getBAM/')."/".$bam}}',
                            indexURL: '{{url('/getBAM/')."/".$bam}}' + '.bai',
                            //url: 'https://data.broadinstitute.org/igvdata/BodyMap/hg19/IlluminaHiSeq2000_BodySites/brain_merged/accepted_hits.bam',
                            //locus: "chr8:128,747,267-128,754,546",
                            name: '{{$sample_name}}',
                            removable : true,
                            height : track_hight,
                            colorBy : 'strand',
                            showSoftClips: true,
                            showCenterGuide: true,
                            samplingDepth : Number.MAX_VALUE
                        },
                        {
                            //url: "{{url('/ref/06302016_refseq.gtf.gz')}}",
                            //indexURL: "{{url('/ref/06302016_refseq.gtf.gz.tbi')}}",                            
                            url: "{{url('/ref/gencode.v38lift37.annotation.sorted.genename_changed.canonical.gtf.gz')}}",
                            indexURL: "{{url('/ref/gencode.v38lift37.annotation.sorted.genename_changed.canonical.gtf.gz.tbi')}}",
                            name: 'Ensembl Canonical',
                            height : 50,
                            format: 'gtf',
                            //displayMode: "COLLAPSED",
                            displayMode: "EXPANDED",
                            displayName: "transcript_id",
                            visibilityWindow: 10000000
                        },                        
                        {
                            //url: "{{url('/ref/06302016_refseq.gtf.gz')}}",
                            //indexURL: "{{url('/ref/06302016_refseq.gtf.gz.tbi')}}",                            
                            url: "{{url('/ref/gencode.v38lift37.annotation.sorted.genename_changed.gtf.gz')}}",
                            indexURL: "{{url('/ref/gencode.v38lift37.annotation.sorted.genename_changed.gtf.gz.tbi')}}",
                            name: 'Gencode',
                            height : 150,
                            format: 'gtf',
                            //displayMode: "COLLAPSED",
                            displayMode: "EXPANDED",
                            visibilityWindow: 10000000
                        }
                    ]
                };
    browser = await igv.createBrowser(div, config)
</script>




<script type="text/javascript">

    var track_hight = 800;
    var browser; 
	
    $(document).ready(function() {   

    });     

</script>

<span id="igv_header">                
	<h3>The IGV view of patient: <font color="red">{{$patient_id}}</font> case: <font color="red">{{$case_name}}</font>	
</span>
<hr>
<div class="container-fluid" id="igvDiv" style="padding:5px; border:1px solid lightgray"></div>



@stop