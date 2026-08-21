# Plotly browser runtime

`plotly.min.js` is the vendored Plotly.js 2.35.2 browser bundle used by both
the legacy expression result page and the streaming chatbot's interactive
expression plots. Plotly.js is distributed under the MIT license; its bundle
contains the upstream license header.

Keep `plotly.min.js` in deployments. The chatbot loads it lazily only when a
tool result contains a Plotly chart.
