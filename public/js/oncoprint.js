(function () {
    'use strict';

    window.initializeOncoprintPlot = function (table, data) {
        var chart = document.getElementById('oncoprint-chart');
        var status = document.getElementById('oncoprint-plot-status');
        var loading = document.getElementById('oncoprint-plot-loading');
        var plotted = false;
        var renderVersion = 0;
        var columns = data.columns;
        var columnTypes = Object.create(null);
        columns.forEach(function (column) { columnTypes[column.key] = column.type; });
        var patientPage = 0;
        var alterationPage = 0;
        var patientsPerPage = 250;
        var alterationsPerPage = 50;
        var patients = Object.keys(data.patients).map(function (id) {
            var alterations = data.patients[id];
            var mutationCount = 0;
            var fusionCount = 0;
            Object.keys(alterations).forEach(function (key) {
                if (columnTypes[key] === 'mutation') mutationCount++;
                else if (columnTypes[key] === 'fusion') fusionCount++;
            });
            return { id: id, alterations: alterations, mutationCount: mutationCount, fusionCount: fusionCount };
        });

        function escapeText(value) {
            return String(value).replace(/[&<>"']/g, function (character) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
            });
        }

        function renderPlot() {
            var version = ++renderVersion;
            loading.hidden = false;
            chart.setAttribute('aria-busy', 'true');
            // Allow the loading indicator to paint before preparing a large matrix.
            requestAnimationFrame(function () {
                setTimeout(function () {
                    if (version !== renderVersion) return;
                    Promise.resolve().then(function () {
                        return drawPlot();
                    }).catch(function () {
                        if (version === renderVersion) {
                            plotted = false;
                            status.textContent = 'Unable to draw the plot. Try changing a filter or reopening the Plot tab.';
                        }
                    }).finally(function () {
                        if (version === renderVersion) {
                            loading.hidden = true;
                            chart.setAttribute('aria-busy', 'false');
                        }
                    });
                }, 0);
            });
        }

        function drawPlot() {
            var query = document.getElementById('oncoprint-patient-search').value.trim().toLowerCase();
            var alteredOnly = document.getElementById('oncoprint-altered-only').checked;
            var byBurden = document.getElementById('oncoprint-patient-order').value === 'burden';
            var visible = patients.filter(function (patient) {
                return patient.id.toLowerCase().indexOf(query) !== -1 &&
                    (!alteredOnly || patient.mutationCount + patient.fusionCount > 0);
            });
            visible.sort(function (a, b) {
                return (byBurden ? (b.mutationCount + b.fusionCount) - (a.mutationCount + a.fusionCount) : 0) ||
                    a.id.localeCompare(b.id, undefined, { numeric: true });
            });
            if (!columns.length || !visible.length) {
                Plotly.purge(chart);
                chart.hidden = true;
                updatePaging('patients', 0, 0, patientsPerPage);
                updatePaging('alterations', 0, 0, alterationsPerPage);
                status.textContent = !columns.length ? 'No Tier 1 alterations to plot.' : 'No patients match the selected filters.';
                return;
            }
            chart.hidden = false;
            var matchingCount = visible.length;
            var counts = Object.create(null);
            visible.forEach(function (patient) {
                Object.keys(patient.alterations).forEach(function (key) { counts[key] = (counts[key] || 0) + 1; });
            });
            var orderedColumns = columns.map(function (column) {
                return {
                    column: column,
                    count: counts[column.key] || 0
                };
            }).sort(function (a, b) {
                return b.count - a.count || a.column.alteration.localeCompare(b.column.alteration) ||
                    a.column.type.localeCompare(b.column.type);
            });
            patientPage = Math.min(patientPage, Math.max(0, Math.ceil(matchingCount / patientsPerPage) - 1));
            alterationPage = Math.min(alterationPage, Math.max(0, Math.ceil(columns.length / alterationsPerPage) - 1));
            updatePaging('patients', patientPage, matchingCount, patientsPerPage);
            updatePaging('alterations', alterationPage, columns.length, alterationsPerPage);
            visible = visible.slice(patientPage * patientsPerPage, (patientPage + 1) * patientsPerPage);
            orderedColumns = orderedColumns.slice(alterationPage * alterationsPerPage, (alterationPage + 1) * alterationsPerPage);
            status.textContent = matchingCount + ' of ' + patients.length + ' patients match · ' + columns.length +
                ' alterations. Showing ' + visible.length + ' patients × ' + orderedColumns.length + ' alterations on this page.';
            var x = visible.map(function (_, index) { return index; });
            var y = orderedColumns.map(function (_, index) { return index; });
            var labels = [];
            var hover = [];
            var z = orderedColumns.map(function (item) {
                var column = item.column;
                var hoverRow = [];
                var values = visible.map(function (patient) {
                    var present = !!patient.alterations[column.key];
                    hoverRow.push('Patient: ' + escapeText(patient.id) + '<br>Alteration: ' + escapeText(column.alteration) +
                        '<br>Type: ' + escapeText(column.type) + '<br>' + (present ? 'Present' : 'No matching alteration recorded'));
                    return present ? (column.type === 'mutation' ? 1 : 2) : 0;
                });
                labels.push(escapeText(column.alteration) + ' (' + Math.round(100 * item.count / matchingCount) + '%)');
                hover.push(hoverRow);
                return values;
            });
            var traces = [{
                type: 'heatmap', x: x, y: y, z: z, text: hover,
                hovertemplate: '%{text}<extra></extra>',
                zmin: 0, zmax: 2, showscale: false, xgap: 1, ygap: 1,
                colorscale: [[0, '#ebedf0'], [0.249, '#ebedf0'], [0.25, '#26965c'], [0.749, '#26965c'], [0.75, '#8756b3'], [1, '#8756b3']]
            }];
            ['mutation', 'fusion'].forEach(function (type) {
                traces.push({
                    type: 'bar', x: x, yaxis: 'y2', name: type === 'mutation' ? 'Mutation' : 'Fusion',
                    y: visible.map(function (patient) { return patient[type + 'Count']; }),
                    text: visible.map(function (patient) { return escapeText(patient.id); }),
                    hovertemplate: 'Patient: %{text}<br>' + (type === 'mutation' ? 'Mutations' : 'Fusions') + ': %{y}<extra></extra>',
                    marker: { color: type === 'mutation' ? '#26965c' : '#8756b3' }
                });
            });
            var plotHeight = Math.max(480, orderedColumns.length * 34 + 300);
            var innerHeight = plotHeight - 145;
            var maxAlterationCount = visible.reduce(function (maximum, patient) {
                return Math.max(maximum, patient.mutationCount + patient.fusionCount);
            }, 0);
            return Plotly.react(chart, traces, {
                height: plotHeight,
                margin: { l: 180, r: 30, t: 35, b: 110 },
                barmode: 'stack', dragmode: 'zoom', hovermode: 'closest',
                legend: { orientation: 'h', x: 0, y: 1, yanchor: 'bottom', itemclick: false, itemdoubleclick: false },
                xaxis: {
                    title: 'Patient', tickmode: 'array',
                    tickvals: x.filter(function (_, index) { return index % Math.ceil(visible.length / 50) === 0; }),
                    ticktext: visible.filter(function (_, index) { return index % Math.ceil(visible.length / 50) === 0; })
                        .map(function (patient) { return escapeText(patient.id); }),
                    tickangle: -90, range: [-0.5, visible.length - 0.5], automargin: true
                },
                yaxis: {
                    domain: [0, (innerHeight - 140) / innerHeight], tickmode: 'array', tickvals: y, ticktext: labels,
                    range: [orderedColumns.length - 0.5, -0.5], automargin: true, zeroline: false
                },
                yaxis2: {
                    domain: [1 - 90 / innerHeight, 1], title: 'Alterations',
                    range: [0, Math.max(3, maxAlterationCount * 1.1)], tickmode: 'auto', nticks: 4,
                    tickformat: ',d', fixedrange: true
                },
                showlegend: true,
                paper_bgcolor: '#fff', plot_bgcolor: '#fff'
            }, {
                responsive: true, displaylogo: false,
                toImageButtonOptions: {
                    filename: document.getElementById('btnDownloadOncoprint').getAttribute('data-filename').replace(/\.tsv$/i, ''),
                    format: 'png', scale: 2
                }
            });
        }

        function updatePaging(group, page, total, size) {
            document.getElementById('oncoprint-' + group + '-page').textContent = total
                ? (page * size + 1) + '–' + Math.min((page + 1) * size, total) + ' of ' + total
                : '0 of 0';
            document.getElementById('oncoprint-' + group + '-prev').disabled = page === 0 || total === 0;
            document.getElementById('oncoprint-' + group + '-next').disabled = (page + 1) * size >= total;
        }

        function selectTab(tab) {
            document.querySelectorAll('.oncoprint-tabs [role="tab"]').forEach(function (button) {
                var selected = button === tab;
                button.setAttribute('aria-selected', String(selected));
                button.tabIndex = selected ? 0 : -1;
                document.getElementById(button.getAttribute('aria-controls')).hidden = !selected;
            });
            if (tab.id === 'plot-tab') {
                if (!plotted) {
                    renderPlot();
                    plotted = true;
                } else if (!chart.hidden) {
                    Plotly.Plots.resize(chart);
                }
            } else {
                table.columns.adjust();
            }
        }
        document.querySelectorAll('.oncoprint-tabs [role="tab"]').forEach(function (tab) {
            tab.addEventListener('click', function () { selectTab(tab); });
            tab.addEventListener('keydown', function (event) {
                if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(event.key) === -1) return;
                event.preventDefault();
                var target = document.getElementById(event.key === 'Home' ? 'matrix-tab' : event.key === 'End' ? 'plot-tab' :
                    (tab.id === 'matrix-tab' ? 'plot-tab' : 'matrix-tab'));
                selectTab(target);
                target.focus();
            });
        });
        ['patients', 'alterations'].forEach(function (group) {
            ['prev', 'next'].forEach(function (direction) {
                document.getElementById('oncoprint-' + group + '-' + direction).addEventListener('click', function () {
                    var step = direction === 'next' ? 1 : -1;
                    if (group === 'patients') patientPage = Math.max(0, patientPage + step);
                    else alterationPage = Math.max(0, alterationPage + step);
                    renderPlot();
                });
            });
        });
        var searchTimer;
        document.getElementById('oncoprint-patient-search').addEventListener('input', function () {
            clearTimeout(searchTimer);
            patientPage = 0;
            alterationPage = 0;
            searchTimer = setTimeout(renderPlot, 200);
        });
        ['oncoprint-patient-order', 'oncoprint-altered-only'].forEach(function (id) {
            document.getElementById(id).addEventListener('change', function () {
                patientPage = 0;
                alterationPage = 0;
                renderPlot();
            });
        });
    };
}());
