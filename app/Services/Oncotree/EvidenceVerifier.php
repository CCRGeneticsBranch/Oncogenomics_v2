<?php

namespace App\Services\Oncotree;

use Illuminate\Support\Facades\Http;

class EvidenceVerifier
{
    public function verify(array $sources): array
    {
        if (count($sources) < 1 || count($sources) > 5) {
            throw new EvidenceValidationException(['reason' => 'Matched proposals require 1–5 evidence sources.']);
        }
        $verified = [];
        foreach ($sources as $index => $source) {
            try {
                $url = $source['url'] ?? '';
                $parts = is_string($url) ? parse_url($url) : false;
                $hosts = ['pubmed.ncbi.nlm.nih.gov', 'pmc.ncbi.nlm.nih.gov', 'oncotree.mskcc.org',
                    'cancer.gov', 'www.cancer.gov', 'ncit.nci.nih.gov', 'evs.nci.nih.gov'];
                if (!$parts || ($parts['scheme'] ?? '') !== 'https'
                    || !in_array(strtolower($parts['host'] ?? ''), $hosts, true)
                    || isset($parts['user']) || isset($parts['pass'])
                    || (isset($parts['port']) && $parts['port'] !== 443)) {
                    throw new \InvalidArgumentException('Evidence URL is not an approved HTTPS source.');
                }
                $quote = trim((string) ($source['quote'] ?? ''));
                if (mb_strlen($quote) < 20 || count(preg_split('/\s+/u', $quote)) > 25
                    || trim((string) ($source['title'] ?? '')) === '') {
                    throw new \InvalidArgumentException('Evidence requires a title and a 20-character minimum quote of at most 25 words.');
                }
                // No redirects: prevent a source from redirecting to internal services.
                $response = Http::connectTimeout(10)->timeout(30)->withOptions([
                    'allow_redirects' => false,
                    'on_headers' => static function ($response) {
                        if ((int) $response->getHeaderLine('Content-Length') > 5000000) {
                            throw new \RuntimeException('Evidence page too large.');
                        }
                    },
                ])->get($url);
                if (!$response->successful() || strlen($response->body()) > 5000000) {
                    throw new \RuntimeException('Evidence page could not be verified; no mapping applied.');
                }
                $plain = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $response->body());
                $normalizedQuote = $this->normalize($quote);
                if ($normalizedQuote === '' || !str_contains($this->normalize($plain), $normalizedQuote)) {
                    throw new \InvalidArgumentException('Supporting quote was not found on the cited page.');
                }
                $verified[] = ['url' => $url, 'title' => $source['title'], 'quote' => $quote,
                    'checked_at' => gmdate('c'), 'page_sha256' => hash('sha256', $response->body())];
            } catch (\Throwable $e) {
                throw new EvidenceValidationException([
                    'source_index' => $index + 1,
                    'url' => is_array($source) ? (string) ($source['url'] ?? '') : '',
                    'quote' => is_array($source) ? (string) ($source['quote'] ?? '') : '',
                    'reason' => $e instanceof \InvalidArgumentException || $e instanceof \RuntimeException
                        ? $e->getMessage() : 'Evidence source could not be retrieved.',
                ], $e);
            }
        }
        return $verified;
    }

    private function normalize(string $value): string
    {
        // Block boundaries must not join words; inline markup preserves them.
        $value = preg_replace('/<\/?(?:p|div|br|li|h[1-6]|section|tr|td)\b[^>]*>/i', ' ', $value);
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(["\u{00AD}", "\u{200B}"], '', $value);
        if (class_exists(\Normalizer::class)) $value = \Normalizer::normalize($value, \Normalizer::FORM_KC);
        // Match the same words/numbers despite curly quotes, dashes, punctuation,
        // and whitespace. Do not use fuzzy matching or drop negation words.
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($value)));
    }

}
