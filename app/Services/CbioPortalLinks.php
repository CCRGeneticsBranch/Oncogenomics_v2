<?php

namespace App\Services;

class CbioPortalLinks
{
    public static function forProject(string $name): ?array
    {
        $studies = config('cbioportal.studies', []);
        $study = $studies[trim($name)] ?? null;
        if (!is_string($study) || $study === '') {
            return null;
        }

        return [
            'base_url' => rtrim(config('cbioportal.base_url'), '/'),
            'study_id' => $study,
        ];
    }

    public static function studyUrl(array $portal): string
    {
        return $portal['base_url'].'/study/summary?'.http_build_query(
            ['id' => $portal['study_id']], '', '&', PHP_QUERY_RFC3986
        );
    }

    public static function patientUrl(array $portal, string $patientId): string
    {
        return $portal['base_url'].'/patient?'.http_build_query(
            ['studyId' => $portal['study_id'], 'caseId' => $patientId], '', '&', PHP_QUERY_RFC3986
        );
    }
}
