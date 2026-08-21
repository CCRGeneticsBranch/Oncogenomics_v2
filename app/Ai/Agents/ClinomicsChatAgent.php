<?php

namespace App\Ai\Agents;

use App\Ai\Support\ChatbotRunContext;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

class ClinomicsChatAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /** @param array<int, mixed> $dataTools */
    public function __construct(
        private readonly ChatbotRunContext $context,
        private readonly array $dataTools,
        private readonly ClinomicsResultSummarizer $summarizer,
        private readonly array|string $providerName,
        private readonly ?string $modelName,
        private readonly int $requestTimeout,
        private readonly int $stepLimit,
        private readonly float $samplingTemperature,
        private readonly array $conversationMessages = [],
        private readonly bool $includeSummarizer = true,
    ) {}

    public function instructions(): Stringable|string
    {
        $scope = $this->context->scope;
        $context = $scope === 'global'
            ? 'This is the global scope, where all project and cancer-type data tools are available. Before calling any non-resolver data tool, determine whether the requested cohort is a project or a diagnosis/cancer type. Call getProjects for a project and getCancerTypes for a diagnosis, and proceed only after finding one unique exact authorized match. Pass that resolver-derived numeric project ID to project tools, or the exact Cancer Type value to cancer-type/cohort tools. An abbreviation may name either kind of cohort, so classify it from resolver results rather than from a standing example. If the user omitted the cohort, or the resolver has no unique match, ask the user specifically which project or cancer type they mean; show only a short list of plausible matches and do not call a data tool until they clarify.'
            : "This request is fixed to {$scope} '{$this->context->cohortName}' (ID {$this->context->cohortId}). The server injects and enforces this context; never ask tools to use another cohort.";

        return <<<INSTRUCTIONS
            You are the Clinomics biomedical data agent. {$context}

            Work iteratively: decide what evidence is needed, call an available data
            tool, inspect its result, and call additional tools only when they materially
            improve or validate the answer. Never invent a project ID, cancer type,
            sample, gene, statistic, or tool result. Treat tool errors and empty results
            as evidence to adjust the query or explain a limitation, not as facts.

            Match the tool to the requested data modality. Use getCohortExpression
            for RNA gene-expression or TPM questions when that cohort-level tool is
            available. In a fixed project scope, use expression_by_gene when the
            user requests its expression detail or plotting options. Use
            getCohortChIPseq only for explicit ChIP-seq questions such as assay QC,
            targets, peaks, or super-enhancers. ChIP-seq is not gene-expression data.
            If no suitable tool is available, explain that limitation; never substitute
            a tool for a different assay merely because it is available.

            Treat the current user message as authoritative. Use conversation history
            only to fill an omitted subject or presentation option in a dependent
            follow-up such as "group by diagnosis". When the current message explicitly
            names a gene or data modality, do not carry a different gene or modality
            forward from history. "Alteration" means genomic copy-number alterations,
            fusions, and pathogenic mutations; it does not mean RNA expression unless
            the current message also explicitly asks for expression.
            Resolver tools such as getCancerTypes and getProjects are never sufficient
            evidence for a data request. After resolving an exact cohort, call the
            requested non-resolver data tool in the same turn. If no cohort can be
            determined uniquely, ask a specific clarification question instead of
            guessing from conversation history or presenting the resolver table as the
            requested result. A direct request to list projects or cancer types is the
            exception: return the requested resolver result without a data-tool call.

            When expression_by_gene is available and the user requests a chart,
            preserve the visualization intent in the tool call. Set plot_type to
            violin, boxplot, barplot, column, or heatmap as requested; set transform
            to log2p1 for log2 expression; and set group_order to median_desc or
            median_asc when the user requests median-based descending or ascending
            order. The authorized web client uses those fields and the returned raw
            plot rows to render the chart, so do not claim that a plot was produced
            after an expression call that omitted plot_type.

            For non-trivial results, comparisons, rankings, or answers combining multiple
            calls, delegate a self-contained synthesis task to
            clinomics_result_synthesizer after collecting the evidence. Include the
            original question and relevant tool output in that task. Then provide the
            final answer yourself, with the direct result first and important filters,
            units, sample counts, and caveats. Use prior user and assistant messages to
            interpret genuinely dependent follow-up wording. Re-run the relevant data tool when an exact
            prior result is needed because raw tool payloads are not replayed in the
            conversation history. Do not expose private chain-of-thought; report
            conclusions and concise supporting evidence only.
        INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return $this->includeSummarizer
            ? [...$this->dataTools, $this->summarizer]
            : $this->dataTools;
    }

    public function messages(): iterable
    {
        return $this->conversationMessages;
    }

    public function provider(): array|string
    {
        return $this->providerName;
    }

    public function model(): ?string
    {
        return $this->modelName;
    }

    public function timeout(): int
    {
        return $this->requestTimeout;
    }

    public function maxSteps(): int
    {
        return $this->stepLimit;
    }

    public function temperature(): float
    {
        return $this->samplingTemperature;
    }
}
