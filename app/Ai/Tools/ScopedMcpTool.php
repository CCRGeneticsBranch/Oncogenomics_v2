<?php

namespace App\Ai\Tools;

use App\Ai\Support\ChatbotRunContext;
use App\Ai\Support\ChatbotScopeArguments;
use App\Ai\Support\ExplicitChatbotCohort;
use App\Ai\Support\ToolResultCompactor;
use App\Services\ExpressionPlotPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool as AiTool;
use Laravel\Ai\Tools\Request as AiRequest;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool as McpTool;
use Stringable;
use Throwable;

class ScopedMcpTool implements AiTool
{
    /** @param array<string, array<string, mixed>> $replayResults */
    public function __construct(
        private readonly McpTool $tool,
        private readonly ChatbotRunContext $context,
        private readonly ToolResultCompactor $compactor,
        private readonly array $replayResults = [],
    ) {}

    /** @param array<string, array<string, mixed>> $replayResults */
    public function withReplayResults(array $replayResults): self
    {
        return new self($this->tool, $this->context, $this->compactor, $replayResults);
    }

    /** @param array<string, mixed> $arguments */
    public static function invocationKey(array $arguments): string
    {
        $normalize = static function (mixed $value) use (&$normalize): mixed {
            if (! is_array($value)) {
                return $value;
            }
            if (array_is_list($value)) {
                return array_map($normalize, $value);
            }

            ksort($value);
            foreach ($value as $key => $item) {
                $value[$key] = $normalize($item);
            }

            return $value;
        };

        return hash('sha256', (string) json_encode(
            $normalize($arguments),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        ));
    }

    public function name(): string
    {
        return $this->tool->name();
    }

    public function description(): Stringable|string
    {
        return $this->tool->description();
    }

    public function schema(JsonSchema $schema): array
    {
        $fields = $this->tool->schema($schema);
        foreach ($this->injectedFields() as $field) {
            unset($fields[$field]);
        }

        // Gemini function declarations do not accept non-null JSON Schema
        // unions such as ["integer", "string"]. The public MCP contract can
        // keep that union, while the AI adapter uses a string for the global
        // cohort selector. Numeric project IDs are accepted as decimal strings
        // and are validated/cast by ResolvesCohortInput.
        if ($this->context->scope === 'global'
            && ChatbotScopeArguments::isCohortTool($this->name())) {
            $fields['cohort_id'] = $schema->string()
                ->description('Numeric project ID returned by getProjects, or exact Cancer Type value returned by getCancerTypes.')
                ->required();
        }

        return $fields;
    }

    public function handle(AiRequest $request): Stringable|string
    {
        $arguments = $this->applyScope($request->all());
        $invocationKey = self::invocationKey($arguments);
        if (array_key_exists($invocationKey, $this->replayResults)) {
            return $this->compactor->encode($this->compactor->compact(
                $this->replayResults[$invocationKey],
            ));
        }

        try {
            $response = app()->call([$this->tool, 'handle'], [
                'request' => new McpRequest($arguments),
            ]);
            $result = $this->normalizeResponse($response);
        } catch (Throwable $exception) {
            report($exception);
            $result = [
                'status' => 'error',
                'action' => $this->name(),
                'message' => $exception->getMessage(),
            ];
        }

        $this->context->record($this->name(), $arguments, $result);

        return $this->compactor->encode($this->compactor->compact($result));
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    public function applyScope(array $arguments): array
    {
        $arguments = ChatbotScopeArguments::apply(
            $this->name(),
            $arguments,
            $this->context->scope,
            $this->context->cohortId,
        );

        if ($this->context->scope === 'global') {
            $project = ExplicitChatbotCohort::projectFromAgentQuery($this->context->query);
            $cancerType = ExplicitChatbotCohort::cancerTypeFromAgentQuery($this->context->query);
            if ($project !== null) {
                if (ChatbotScopeArguments::isCohortTool($this->name())) {
                    $arguments['cohort_type'] = 'project';
                    $arguments['cohort_id'] = $project['id'];
                } elseif (! ChatbotScopeArguments::isResolverTool($this->name())
                    && ! ChatbotScopeArguments::isCancerTypeOnlyTool($this->name())) {
                    $arguments['project_id'] = $project['id'];
                }
            } elseif ($cancerType !== null) {
                if (ChatbotScopeArguments::isCohortTool($this->name())) {
                    $arguments['cohort_type'] = 'cancer_type';
                    $arguments['cohort_id'] = $cancerType;
                } elseif (ChatbotScopeArguments::isCancerTypeOnlyTool($this->name())) {
                    $arguments['cancer_type_id'] = $cancerType;
                }
            }
        }

        if (in_array(strtolower($this->name()), ['expression_by_gene', 'getcohortexpression'], true)) {
            $arguments = app(ExpressionPlotPresenter::class)->applyQueryIntent(
                $arguments,
                $this->context->query,
            );
        }

        return $arguments;
    }

    /** @return array<int, string> */
    private function injectedFields(): array
    {
        return ChatbotScopeArguments::injectedFields($this->name(), $this->context->scope);
    }

    /** @return array<string, mixed> */
    private function normalizeResponse(mixed $response): array
    {
        if ($response instanceof ResponseFactory) {
            if (is_array($response->getStructuredContent())) {
                return $response->getStructuredContent();
            }

            return [
                'status' => 'success',
                'content' => $response->responses()
                    ->map(static fn (Response $item): string => (string) $item->content())
                    ->values()
                    ->all(),
            ];
        }

        if ($response instanceof Response) {
            return ['status' => 'success', 'content' => (string) $response->content()];
        }

        if (is_array($response)) {
            return $response;
        }

        return ['status' => 'success', 'content' => (string) $response];
    }
}
