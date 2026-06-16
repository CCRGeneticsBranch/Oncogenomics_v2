@extends('layouts.default')
@section('title', "IGV--Pacbio-$project_id")
@section('content')

{!! HTML::style('https://maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css') !!}


<script type="module">

    import igv from '{!!url("packages/igv.js/igv.esm.min.js")!!}'

    const div = document.getElementById("igvDiv")

    const config = {
                    showNavigation: true,
                    showKaryo : false,
                    showRuler : true,
                    showCenterGuide : true,
                    showCursorTrackingGuide : true,
                    genome: "hg38",
                    locus: '{!!$gene!!}',
                    tracks: [
                        @foreach ($tumors as $tumor) 
                        {
                            type: 'annotation',
                            //url: "{!!url("/getPacBioGTF/$project_id/$tumor/gtf")!!}",
                            //indexURL: "{!!url("/getPacBioGTF/$project_id/$tumor/tbi")!!}",
                            url: "{!!url("/ref/pacbio/per_sample/${tumor}.filtered.sorted.gtf.gz")!!}",
                            indexURL: "{!!url("/ref/pacbio/per_sample/${tumor}.filtered.sorted.gtf.gz.tbi")!!}",
                            name: 'Tumor: {!!$tumor!!}',
                            format: 'gtf',
                            searchable: true,
                            displayMode: "EXPANDED",
                            visibilityWindow: 10000000
                        },
                        @endforeach
                        @foreach ($normals as $normal) 
                        {
                            type: 'annotation',
                            //url: "{!!url("/getPacBioGTF/$project_id/$normal/gtf")!!}",
                            //indexURL: "{!!url("/getPacBioGTF/$project_id/$normal/tbi")!!}",
                            url: "{!!url("/ref/pacbio/per_sample/${normal}.filtered.sorted.gtf.gz")!!}",
                            indexURL: "{!!url("/ref/pacbio/per_sample/${normal}.filtered.sorted.gtf.gz.tbi")!!}",
                            name: 'Normal: {!!$normal!!}',
                            format: 'gtf',
                            searchable: true,
                            displayMode: "EXPANDED",
                            visibilityWindow: 10000000
                        },
                        @endforeach
                    ]
                };

    (async () => {
        const browser = await igv.createBrowser(div, config)
    })().catch(error => {
        console.error('Error loading IGV browser:', error)
    })
</script>


<span id="igv_header">                
<div class="container-fluid" id="igvDiv" style="padding:5px; border:1px solid lightgray"></div>



@stop