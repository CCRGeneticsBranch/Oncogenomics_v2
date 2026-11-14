<?php

namespace App\Mcp\Servers;

use Illuminate\Support\Facades\File;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tool as McpTool;

class OncoServer extends Server
{
    /**
     * Return the complete current tool catalog in the initial tools/list page.
     * Some MCP clients do not follow nextCursor when discovering tools.
     */
    public int $defaultPaginationLength = 50;

    /**
     * The MCP server's name.
     */
    protected string $name = 'Onco Server';

    /**
     * The MCP server's version.
     */
    protected string $version = '0.0.1';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        Query authorized Clinomics data. Resolve named projects with getProjects(query, match=exact),
        diagnoses with getCancerTypes; use result_role=discovery for these lookups, answer only for explicitly requested cohort listings; use the returned numeric ID or exact Cancer Type and matching
        cohort_type. Trusted fixed host context is already resolved. Never guess IDs; ask the user
        to choose when a named cohort is ambiguous. When no cohort is specified, query all authorized data using global tools or the SQL fallback; omit SQL cohort parameters. For gene queries
        without a specified cohort use getExpGeneSummary, getVarGeneSummary,
        getCNVGeneSummary, getFusionGeneSummary, getFusionGenePairSummary and
        getPatientsBy* gene/pair tools across all authorized data. These require
        no project or cancer-type resolver. Group summaries by diagnosis or project;
        patient category filters are optional. Preserve any explicit cohort with
        the cohort-specific tools instead of broadening it.
        For tutorial, navigation, or interface questions use searchOncogenomicsWiki without
        resolving a cohort. Cite its source URLs; tutorial examples are not live patient data.
        Prefer existing domain tools. Only for an unsupported filter, join or aggregation,
        inspect getSqlSchema (including database.db_type) and use runAuthorizedSql with a capability_gap explanation. Generate for that server-selected database using only the restricted grammar; SAFE_NUMBER and row limits are rendered server-side for MySQL or Oracle.
        Empty results are not capability gaps. Follow the restricted grammar and preserve
        authorization; survival units are days. For survival use patient_details class overall_survival and type number; use SAFE_NUMBER(attr_value) for numeric values and thresholds, never LIKE. Discover schema once and use documented class roles without an extra attribute-name query. Return DISTINCT patient/value rows without project columns unless requested. Mark exploratory SQL result_role=discovery and final SQL result_role=answer. Finish with one concise answer, not intermediate query tables. Executed SQL is returned in technical_details.
        Prefer output={mode:compact} where supported; for counts use mode:summary. Follow next_offset
        for more rows; total_rows counts table rows, not unique patients. Omit output for UI plots/tables.
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        // Populated in boot() via auto-discovery + optional configured tools.
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [
        //
    ];

    protected function boot(): void
    {
        $configuredTools = config('mcp_tools.onco.tools', []);
        $autoDiscover = (bool) config('mcp_tools.onco.autodiscover', true);

        $discoveredTools = $autoDiscover
            ? $this->discoverToolClasses(
                (string) config('mcp_tools.onco.discovery_path', app_path('Mcp/Tools')),
                (string) config('mcp_tools.onco.discovery_namespace', 'App\\Mcp\\Tools')
            )
            : [];

        $this->tools = array_values(array_unique(array_merge(
            $discoveredTools,
            is_array($configuredTools) ? $configuredTools : []
        )));
    }

    /**
     * @return array<int, class-string<McpTool>>
     */
    private function discoverToolClasses(string $path, string $namespace): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $classes = [];
        $path = rtrim($path, DIRECTORY_SEPARATOR);

        foreach (File::allFiles($path) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = ltrim(substr($file->getPathname(), strlen($path)), DIRECTORY_SEPARATOR);
            $relativeClass = str_replace(['/', '\\'], '\\', $relativePath);
            $relativeClass = preg_replace('/\.php$/', '', $relativeClass);

            if (! is_string($relativeClass) || $relativeClass === '') {
                continue;
            }

            $class = $namespace.'\\'.$relativeClass;

            if (! class_exists($class)) {
                continue;
            }

            if (! is_subclass_of($class, McpTool::class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            if ($reflection->isAbstract()) {
                continue;
            }

            $classes[] = $class;
        }

        sort($classes);

        return $classes;
    }
}
