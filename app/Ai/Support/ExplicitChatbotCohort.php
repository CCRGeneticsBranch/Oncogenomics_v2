<?php

namespace App\Ai\Support;

final class ExplicitChatbotCohort
{
    private const PROJECT_MARKER = 'Server-resolved explicit project cohort:';

    private const CANCER_TYPE_MARKER = 'Server-resolved explicit cancer type cohort:';

    public static function appendProjectContext(string $query, array $project): string
    {
        return rtrim($query, " \t\n\r\0\x0B.").".\n".self::PROJECT_MARKER
            .' id='.(int) $project['id']
            .'; name='.self::safeName((string) $project['name'])
            .'. This project is authoritative for the current request; do not reinterpret diagnosis grouping as a cancer-type cohort.';
    }

    /** @return array{id: int, name: string}|null */
    public static function projectFromAgentQuery(string $query): ?array
    {
        if (preg_match(
            '/'.preg_quote(self::PROJECT_MARKER, '/').'\s*id=(\d+);\s*name=([^\r\n.]+(?:\.[^\r\n.]+)*)\.\s+This project is authoritative/i',
            $query,
            $matches,
        ) !== 1) {
            return null;
        }

        $id = filter_var($matches[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $name = trim((string) $matches[2]);

        return $id !== false && $name !== ''
            ? ['id' => (int) $id, 'name' => $name]
            : null;
    }

    public static function hasResolvedProject(string $query): bool
    {
        return self::projectFromAgentQuery($query) !== null;
    }

    public static function appendCancerTypeContext(string $query, string $cancerType): string
    {
        return rtrim($query, " \t\n\r\0\x0B.").".\n".self::CANCER_TYPE_MARKER
            .' name='.self::safeName($cancerType)
            .'. This cancer type is authoritative for the dependent follow-up unless the current request names another cohort.';
    }

    public static function cancerTypeFromAgentQuery(string $query): ?string
    {
        if (preg_match(
            '/'.preg_quote(self::CANCER_TYPE_MARKER, '/').'\s*name=([^\r\n.]+(?:\.[^\r\n.]+)*)\.\s+This cancer type is authoritative/i',
            $query,
            $matches,
        ) !== 1) {
            return null;
        }

        $name = trim((string) $matches[1]);

        return $name !== '' ? $name : null;
    }

    private static function safeName(string $name): string
    {
        $name = (string) preg_replace('/[\x00-\x1F\x7F;\r\n]+/u', ' ', $name);

        return mb_substr(trim((string) preg_replace('/\s+/', ' ', $name)), 0, 160);
    }
}
