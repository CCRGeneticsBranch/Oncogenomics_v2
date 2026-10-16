<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Oncoprint</title>
    {!! HTML::style('packages/jquery/jquery.dataTables.css') !!}
    {!! HTML::script('js/jquery-3.6.0.min.js') !!}
    {!! HTML::script('packages/DataTables/datatables.min.js') !!}
    {!! HTML::script('packages/plotly/plotly.min.js') !!}
    {!! HTML::script('js/oncoprint.js') !!}
    <style>
        body { font: 14px Arial, sans-serif; margin: 12px; }
        .oncoprint-description { display: flex; align-items: center; gap: 8px; font-size: 20px; }
        #btnDownloadOncoprint { margin-left: auto; padding: 6px 12px; border: 1px solid #aaa; border-radius: 4px; background: #f7f7f7; color: #333; font: 14px Arial, sans-serif; cursor: pointer; }
        .oncoprint-help { position: relative; display: inline-flex; }
        .oncoprint-help button { width: 22px; height: 22px; padding: 0; border: 1px solid #666; border-radius: 50%; background: #fff; color: #444; font: bold 15px Arial, sans-serif; cursor: help; }
        .oncoprint-tooltip { display: none; position: absolute; top: calc(100% + 6px); left: 0; z-index: 10; width: 320px; max-width: calc(100vw - 48px); padding: 10px; border-radius: 4px; background: #333; color: #fff; font-size: 14px; line-height: 1.5; box-shadow: 0 2px 6px #0003; }
        .oncoprint-help:hover .oncoprint-tooltip, .oncoprint-help:focus-within .oncoprint-tooltip { display: block; }
        @media (max-width: 700px) { .oncoprint-tooltip { left: auto; right: 0; } }
        .oncoprint-scroll { width: 100%; max-width: 100%; }
        .oncoprint-scroll table { border-collapse: collapse; white-space: nowrap; }
        .oncoprint-scroll th, .oncoprint-scroll td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        .oncoprint-scroll th:first-child, .oncoprint-scroll td:first-child { position: sticky; left: 0; background: #fff; text-align: left; }
        .oncoprint-check { color: #187442; font-size: 18px; }
        .oncoprint-tabs { display: flex; gap: 4px; border-bottom: 1px solid #ccc; margin-bottom: 14px; }
        .oncoprint-tabs button { padding: 9px 18px; border: 1px solid #ccc; border-bottom: 0; background: #f7f7f7; cursor: pointer; font: 15px Arial, sans-serif; }
        .oncoprint-tabs button[aria-selected="true"] { background: #e8f2fa; color: #175a8d; font-weight: bold; }
        .oncoprint-controls { display: flex; flex-wrap: wrap; gap: 16px; align-items: center; margin-bottom: 10px; }
        .oncoprint-controls input[type="search"], .oncoprint-controls select { padding: 5px; }
        #oncoprint-chart { width: 100%; min-height: 420px; }
        [hidden] { display: none !important; }
    </style>
</head>
<body>
    <p class="oncoprint-description">
        Tier 1 mutations and fusions
        <span class="oncoprint-help">
            <button type="button" aria-label="About the Oncoprint matrix" aria-describedby="oncoprint-help-text">?</button>
            <span id="oncoprint-help-text" class="oncoprint-tooltip" role="tooltip">Matrix columns show mutations first, then fusions, with the highest patient count first within each group. &#10003; = alteration present; blank = no matching alteration recorded. The plot shows patients in columns and alterations in rows, sorted by patient count, highest first: green = mutation, purple = fusion, light gray = no matching alteration recorded. Row order and percentages reflect all patients matching the plot filters. Large plots show up to 250 patients and 50 alterations per page; use Previous and Next to see more.</span>
        </span>
        <button id="btnDownloadOncoprint" type="button" data-filename="{{ $download_filename ?? 'oncoprint.tsv' }}" title="Download all matching patients as TSV">Download TSV</button>
    </p>
    @if (count($columns) === 0)
        <p>No Tier 1 alterations found for this project.</p>
    @endif
    <div class="oncoprint-tabs" role="tablist" aria-label="Oncoprint views">
        <button id="matrix-tab" type="button" role="tab" aria-selected="true" aria-controls="matrix-panel">Matrix</button>
        <button id="plot-tab" type="button" role="tab" aria-selected="false" aria-controls="plot-panel" tabindex="-1">Plot</button>
    </div>
    <div id="matrix-panel" role="tabpanel" aria-labelledby="matrix-tab">
    <div class="oncoprint-scroll" tabindex="0" aria-label="Patient alteration matrix">
        <table id="tblOncoprint" style="width:100%">
            <thead>
                <tr>
                    <th scope="col">patient_id</th>
                    @foreach ($columns as $column)
                        <th scope="col">{{ $column['alteration'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    </div>
    <div id="plot-panel" role="tabpanel" aria-labelledby="plot-tab" hidden>
        <div class="oncoprint-controls">
            <label>Patient: <input id="oncoprint-patient-search" type="search" placeholder="Search patient ID"></label>
            <label>Patient order:
                <select id="oncoprint-patient-order">
                    <option value="burden">Most alterations first</option>
                    <option value="id">Patient ID</option>
                </select>
            </label>
            <label><input id="oncoprint-altered-only" type="checkbox"> Only patients with alterations</label>
        </div>
        <div class="oncoprint-controls" aria-label="Plot pages">
            <span>Patients: <button id="oncoprint-patients-prev" type="button">Previous</button> <span id="oncoprint-patients-page"></span> <button id="oncoprint-patients-next" type="button">Next</button></span>
            <span>Alterations: <button id="oncoprint-alterations-prev" type="button">Previous</button> <span id="oncoprint-alterations-page"></span> <button id="oncoprint-alterations-next" type="button">Next</button></span>
        </div>
        <p>Hover for details. Drag to zoom; double-click to reset. Use the toolbar to pan or save an image.</p>
        <div id="oncoprint-plot-loading" role="status" style="padding:20px 0;" hidden>
            <img src="{{ url('/images/ajax-loader.gif') }}" alt=""> Loading plot…
        </div>
        <p id="oncoprint-plot-status" role="status"></p>
        <div id="oncoprint-chart" role="img" aria-label="Interactive Oncoprint: patients in columns, alterations in rows"></div>
    </div>
    <script>
        $(function () {
            var matrix = {{ \Illuminate\Support\Js::from(['columns' => $columns, 'patients' => $patients]) }};
            var table = $('#tblOncoprint').DataTable({
                data: Object.keys(matrix.patients).map(function (id) { return { patient_id: id }; }),
                columns: [{ data: 'patient_id', type: 'string', render: $.fn.dataTable.render.text() }].concat(matrix.columns.map(function (column) {
                    return {
                        data: null,
                        type: 'num',
                        searchable: false,
                        render: function (data, type, row) {
                            var present = !!matrix.patients[row.patient_id][column.key];
                            return type === 'display'
                                ? (present ? '<span class="oncoprint-check" role="img" aria-label="Alteration present">&#10003;</span>' : '')
                                : (present ? 1 : 0);
                        }
                    };
                })),
                deferRender: true,
                pageLength: 25,
                order: [],
                autoWidth: false,
                scrollX: true,
                scrollY: 'calc(100vh - 230px)',
                scrollCollapse: true
            });
            initializeOncoprintPlot(table, matrix);
            $('#btnDownloadOncoprint').on('click', function () {
                function tsvCell(value) {
                    value = String(value);
                    if (/^[=+@\-\t\r\n]/.test(value)) {
                        value = "'" + value;
                    }
                    return value.replace(/[\t\r\n]/g, ' ');
                }
                var rows = [table.columns().header().toArray().map(function (header) {
                    return header.textContent.trim();
                })];
                table.rows({ search: 'applied', order: 'applied' }).data().each(function (row) {
                    rows.push([row.patient_id].concat(matrix.columns.map(function (column) {
                        return matrix.patients[row.patient_id][column.key] ? '1' : '';
                    })));
                });
                var tsv = '\uFEFF' + rows.map(function (row) {
                    return row.map(tsvCell).join('\t');
                }).join('\r\n');
                var downloadUrl = URL.createObjectURL(new Blob([tsv], { type: 'text/tab-separated-values;charset=utf-8;' }));
                var link = document.createElement('a');
                link.href = downloadUrl;
                link.download = this.getAttribute('data-filename');
                document.body.appendChild(link);
                link.click();
                link.remove();
                setTimeout(function () { URL.revokeObjectURL(downloadUrl); }, 1000);
            });
        });
    </script>
</body>
</html>
