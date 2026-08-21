<?php

namespace App\Ai\Support;

final class ChatbotScopeArguments
{
    private const RESOLVER_TOOLS = [
        'getprojects',
        'getcancertypes',
    ];

    private const COHORT_TOOLS = [
        'getcohortsamples',
        'getcohortexpression',
        'getcohortchipseq',
        'getcohortmutationgenes',
    ];

    public static function isCohortTool(string $toolName): bool
    {
        return in_array(strtolower(trim($toolName)), self::COHORT_TOOLS, true);
    }

    public static function isResolverTool(string $toolName): bool
    {
        return in_array(strtolower(trim($toolName)), self::RESOLVER_TOOLS, true);
    }

    public static function isCancerTypeOnlyTool(string $toolName): bool
    {
        return strtolower(trim($toolName)) === 'getfusioncancertypedetail';
    }

    /** @return array<int, string> */
    public static function injectedFields(string $toolName, string $scope): array
    {
        $tool = strtolower(trim($toolName));
        if ($scope === 'global') {
            return [];
        }
        if (self::isCohortTool($tool)) {
            return ['cohort_type', 'cohort_id'];
        }
        if ($scope === 'project') {
            return ['project_id'];
        }
        if ($tool === 'getfusioncancertypedetail') {
            return ['cancer_type_id'];
        }

        return [];
    }

    /**
     * Apply the fixed page scope to an MCP invocation. This is shared by the
     * in-process Laravel AI adapter and compatibility HTTP path, so a model
     * cannot change the project or diagnosis selected by the page.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public static function apply(
        string $toolName,
        array $arguments,
        string $scope,
        string|int $cohortId,
    ): array {
        $tool = strtolower(trim($toolName));

        if ($scope === 'global') {
            if (self::isResolverTool($tool)) {
                unset(
                    $arguments['project_id'],
                    $arguments['cohort_id'],
                    $arguments['cohort_type'],
                    $arguments['cancer_type_id'],
                    $arguments['cancer_type'],
                );

                return $arguments;
            }

            if (self::isCohortTool($tool)) {
                unset($arguments['project_id'], $arguments['cancer_type_id'], $arguments['cancer_type']);

                return self::applyConfiguredCohortAlias($arguments);
            }

            // Project-only and cancer-type-only tools are also available from
            // the home chatbot. Preserve the exact resolver-derived ID they
            // require, while removing context fields their schemas do not use.
            if (self::isCancerTypeOnlyTool($tool)) {
                unset($arguments['project_id'], $arguments['cohort_id'], $arguments['cohort_type']);
                $arguments['cancer_type_id'] = self::configuredCancerTypeAlias(
                    (string) ($arguments['cancer_type_id'] ?? ''),
                );

                return $arguments;
            }

            unset($arguments['cohort_id'], $arguments['cohort_type'], $arguments['cancer_type_id'], $arguments['cancer_type']);

            return $arguments;
        }

        if (self::isCohortTool($tool)) {
            unset($arguments['project_id'], $arguments['cancer_type_id'], $arguments['cancer_type']);
            $arguments['cohort_type'] = $scope;
            $arguments['cohort_id'] = $scope === 'project' ? (int) $cohortId : (string) $cohortId;

            return $arguments;
        }

        if ($scope === 'project') {
            unset($arguments['cohort_id'], $arguments['cohort_type'], $arguments['cancer_type_id']);
            $arguments['project_id'] = (int) $cohortId;

            return $arguments;
        }

        unset($arguments['project_id'], $arguments['cohort_id'], $arguments['cohort_type']);
        if ($tool === 'getfusioncancertypedetail') {
            $arguments['cancer_type_id'] = (string) $cohortId;
        }

        return $arguments;
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    private static function applyConfiguredCohortAlias(array $arguments): array
    {
        if (strtolower(trim((string) ($arguments['cohort_type'] ?? ''))) !== 'cancer_type') {
            return $arguments;
        }

        $alias = strtolower(trim((string) ($arguments['cohort_id'] ?? '')));
        $canonical = config('chatbot.cohort_aliases.cancer_type.'.$alias);
        if (is_string($canonical) && trim($canonical) !== '') {
            $arguments['cohort_id'] = trim($canonical);
        }

        return $arguments;
    }

    private static function configuredCancerTypeAlias(string $value): string
    {
        $value = trim($value);
        $canonical = config('chatbot.cohort_aliases.cancer_type.'.strtolower($value));

        return is_string($canonical) && trim($canonical) !== ''
            ? trim($canonical)
            : $value;
    }
}
