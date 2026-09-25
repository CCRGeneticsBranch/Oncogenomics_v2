<?php

return [
    'onco' => [
        // Automatically discover tools from discovery_path / discovery_namespace.
        'autodiscover' => true,

        'discovery_path' => app_path('Mcp/Tools'),
        'discovery_namespace' => 'App\\Mcp\\Tools',

        // Optional explicit tools (e.g. special cases outside discovery path).
        'tools' => [],
    ],
];
