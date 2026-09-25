<?php

namespace App\Ai\Support;

class ChatbotRunContext
{
    /** @var array<int, array<string, mixed>> */
    private array $executions = [];

    public function __construct(
        public readonly string $scope,
        public readonly string|int $cohortId,
        public readonly string $cohortName,
        public readonly string $query,
    ) {
    }

    /** @param array<string, mixed> $arguments @param array<string, mixed> $result */
    public function record(string $tool, array $arguments, array $result): void
    {
        $this->executions[] = [
            'tool' => $tool,
            'arguments' => $arguments,
            'result' => $result,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function executions(): array
    {
        return $this->executions;
    }
}
