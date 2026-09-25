<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\Project;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

trait ResolvesCohortInput
{
    /**
     * @return array{0: int|string|null, 1: ResponseFactory|null}
     */
    protected function resolveCohortId(string $cohortType, $cohortId, string $action): array
    {
        if ($cohortType === 'project') {
            $value = filter_var($cohortId, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);

            if ($value === false) {
                return [null, Response::structured([
                    'status' => 'error',
                    'action' => $action,
                    'message' => 'cohort_id must be a positive numeric project ID returned by getProjects.',
                ])];
            }

            if (!$this->isAccessibleProject((int) $value)) {
                return [null, Response::structured([
                    'status' => 'error',
                    'action' => $action,
                    'message' => "Project {$value} was not found or is not accessible to the authorized user.",
                ])];
            }

            return [(int) $value, null];
        }

        $value = trim((string) $cohortId);
        if ($value === '') {
            return [null, Response::structured([
                'status' => 'error',
                'action' => $action,
                'message' => 'cohort_id must be an exact Cancer Type value returned by getCancerTypes.',
            ])];
        }

        return [$value, null];
    }

    protected function isAccessibleProject(int $projectId): bool
    {
        foreach (Project::getAll(false) as $project) {
            if ((int) $project->id === $projectId) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    protected function cohortProperties(): array
    {
        return [
            'cohort_type' => [
                'type' => 'string',
                'enum' => ['project', 'cancer_type'],
                'description' => 'Use project for a named data project, or cancer_type for a diagnosis spanning the authorized projects.',
            ],
            'cohort_id' => [
                'type' => ['integer', 'string'],
                'description' => 'Numeric project ID returned by getProjects, or exact Cancer Type value returned by getCancerTypes.',
            ],
        ];
    }

    protected function cohortDescription(): string
    {
        return <<<'MARKDOWN'
            First determine the cohort type from the user's wording. A named
            data project is cohort_type=project: call getProjects first and use
            its numeric project ID. A diagnosis/cancer type is
            cohort_type=cancer_type: call getCancerTypes first and use its
            exact Cancer Type value. Never guess the cohort type or ID.
        MARKDOWN;
    }

    /**
     * Replace the legacy tool identity while retaining its result fields.
     *
     * @param  array<string, mixed>  $content
     */
    protected function cohortResponse(
        array $content,
        string $cohortType,
        int|string $cohortId,
        string $action
    ): ResponseFactory {
        $content['action'] = $action;
        $content['cohort_type'] = $cohortType;
        $content['cohort_id'] = $cohortId;

        return Response::structured($content);
    }
}
