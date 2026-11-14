<?php

namespace App\Services;

final class WikiTierFigures
{
    private const FIGURES = [
        'germline' => ['Germline mutation tier definitions', 'Germline-Classification--03022017-1.svg', 'germline'],
        'somatic' => ['Somatic mutation tier definitions', 'Somatic-Classification--01032017-1.svg', 'somatic'],
        'fusion' => ['Fusion tier definitions', 'fusion_tier.png', 'output-table'],
    ];

    public static function forQuery(string $query, string $wikiText): array
    {
        if (!preg_match('/\btier(?:s|ing|[\s-]*[1-4])?\b/i', $query)) return [];
        $types = [];
        foreach (array_keys(self::FIGURES) as $type) {
            if (preg_match('/\b'.preg_quote($type, '/').'(?:s)?\b/i', $query)) $types[] = $type;
        }
        if ($types === []) {
            $types = preg_match('/\b(?:mutations?|variants?)\b/i', $query)
                ? ['germline', 'somatic'] : array_keys(self::FIGURES);
        }
        $figures = [];
        foreach ($types as $type) {
            [$title, $file, $anchor] = self::FIGURES[$type];
            $source = self::sourceUrl($file);
            if (!str_contains($wikiText, $source) || !is_file(public_path('images/tutorial/'.$file))) continue;
            $figures[] = ['title' => $title, 'image_url' => asset('images/tutorial/'.$file),
                'source_url' => $source, 'wiki_url' => OncogenomicsWiki::URL.'/6.-Cases#'.$anchor];
        }
        return $figures;
    }

    /** Only these exact known figures may appear as Markdown images. */
    public static function localUrl(string $url): ?string
    {
        foreach (self::FIGURES as [, $file]) {
            $local = asset('images/tutorial/'.$file);
            if (($url === $local || $url === self::sourceUrl($file))
                && is_file(public_path('images/tutorial/'.$file))) return $local;
        }
        return null;
    }

    private static function sourceUrl(string $file): string
    {
        return 'https://clinomics.ccr.cancer.gov/clinomics/public/images/tutorial/'.$file;
    }
}
