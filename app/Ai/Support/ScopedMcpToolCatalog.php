<?php

namespace App\Ai\Support;

use App\Ai\Tools\ScopedMcpTool;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Server\Tool as McpTool;
use ReflectionClass;

class ScopedMcpToolCatalog
{
    /** @return array<int, ScopedMcpTool> */
    public function forContext(ChatbotRunContext $context): array
    {
        $allowed = array_fill_keys(array_map(
            static fn (mixed $name): string => strtolower(trim((string) $name)),
            (array) config('chatbot.scope_tools.'.$context->scope, [])
        ), true);
        $compactor = new ToolResultCompactor(
            max(1, (int) config('chatbot.agent.tool_preview_rows', 75)),
            max(1000, (int) config('chatbot.agent.tool_preview_chars', 30000)),
        );
        $tools = [];

        foreach ($this->discoverClasses() as $class) {
            /** @var McpTool $tool */
            $tool = app($class);
            if (! isset($allowed[strtolower($tool->name())]) || ! $tool->eligibleForRegistration()) {
                continue;
            }
            if (! ChatbotToolPolicy::allows($tool->name(), $context->query)) {
                continue;
            }
            $tools[] = new ScopedMcpTool($tool, $context, $compactor);
        }

        usort($tools, static fn (ScopedMcpTool $a, ScopedMcpTool $b): int => strcasecmp($a->name(), $b->name()));

        return $tools;
    }

    /** @return array<int, class-string<McpTool>> */
    private function discoverClasses(): array
    {
        $path = (string) config('mcp_tools.onco.discovery_path', app_path('Mcp/Tools'));
        $namespace = (string) config('mcp_tools.onco.discovery_namespace', 'App\\Mcp\\Tools');
        if (! is_dir($path)) {
            return [];
        }

        $classes = [];
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        foreach (File::allFiles($path) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = ltrim(substr($file->getPathname(), strlen($path)), DIRECTORY_SEPARATOR);
            $relative = preg_replace('/\.php$/', '', str_replace(['/', '\\'], '\\', $relative));
            $class = $namespace.'\\'.$relative;
            if (! class_exists($class) || ! is_subclass_of($class, McpTool::class)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            if (! $reflection->isAbstract()) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }
}
