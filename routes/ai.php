<?php

use Laravel\Mcp\Facades\Mcp;
use App\Http\Middleware\McpTokenAuth;

Mcp::web('/mcp/onco', \App\Mcp\Servers\OncoServer::class)
    ->middleware([McpTokenAuth::class]);

Mcp::local('onco', \App\Mcp\Servers\OncoServer::class);
