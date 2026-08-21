<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Promptable;
use Stringable;

class ClinomicsResultSummarizer implements Agent, CanActAsTool
{
    use Promptable;

    public function __construct(
        private readonly array|string $providerName,
        private readonly ?string $modelName,
        private readonly int $requestTimeout,
    ) {}

    public function name(): string
    {
        return 'clinomics_result_synthesizer';
    }

    public function description(): Stringable|string
    {
        return 'Summarize one or more Clinomics tool results into a precise answer. Pass a self-contained task containing the user question and the relevant tool evidence.';
    }

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are the result-synthesis subagent for a biomedical data application.
            Use only the evidence included in the delegated task. Do not invent genes,
            samples, counts, statistics, cohort IDs, or conclusions. Reconcile results
            from multiple tools, state important filters and units, distinguish no data
            from an execution error, and mention material limitations. Lead with the
            direct answer and use a compact table or list only when it improves clarity.
        INSTRUCTIONS;
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
        return 2;
    }

    public function temperature(): float
    {
        return 0.0;
    }
}
