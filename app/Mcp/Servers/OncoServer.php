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
        Use these tools to answer Clinomics data questions.

        Before calling a cohort-scoped tool, determine whether the user named a
        data project or a diagnosis/cancer type. For a project, call
        getProjects first and pass the returned numeric project ID with
        cohort_type=project. For a diagnosis or cancer type, call
        getCancerTypes first and pass its exact Cancer Type value with
        cohort_type=cancer_type. Never infer or invent a cohort ID. If the name
        is missing or ambiguous, ask the user specifically which project or
        cancer type they mean, offer a short list of matching resolver results,
        and do not call a cohort data tool until they clarify.
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
