<?php

namespace App\Ai\Support;

final class ChatbotToolPolicy
{
    /** @var array<int, string> */
    private const EXPRESSION_TOOLS = [
        'expression_by_gene',
        'getcohortexpression',
        'getexpgeneexpression',
        'getexpgenesummary',
    ];

    public static function allows(string $toolName, string $query): bool
    {
        $tool = strtolower(trim($toolName));

        // An explicit project resolved by the server must not be reclassified
        // as a diagnosis merely because the requested grouping is Diagnosis.
        if ($tool === 'getcancertypes' && ExplicitChatbotCohort::hasResolvedProject($query)) {
            return false;
        }

        // A new, explicit genomic-alteration request must not inherit an RNA
        // expression modality from conversation history. Expression remains
        // available when the current request explicitly asks for both.
        if (in_array($tool, self::EXPRESSION_TOOLS, true)
            && self::asksForGenomicAlterations($query)
            && ! self::asksForExpression($query)) {
            return false;
        }

        // When the user clearly asks for RNA expression and does not also ask
        // for ChIP-seq, remove the ChIP-seq tool from the model's choices. This
        // is a deterministic modality guardrail, not an LLM routing hint.
        if ($tool === 'getcohortchipseq'
            && self::asksForExpression($query)
            && ! self::asksForChipSeq($query)) {
            return false;
        }
        if (in_array($tool, self::EXPRESSION_TOOLS, true)
            && self::asksForChipSeq($query)
            && ! self::asksForExpression($query)) {
            return false;
        }

        return true;
    }

    public static function asksForExpression(string $query): bool
    {
        return preg_match(
            '/(?:\b(expression|expressed|expressing|rpkm|fpkm|rna[ -]?seq)\b|\b(?:log2)?tpm\b)/i',
            $query,
        ) === 1;
    }

    public static function asksForChipSeq(string $query): bool
    {
        return preg_match('/\b(chip[ -]?seq|chipseq|chromatin immunoprecipitation|peaks?|super[ -]?enhancers?)\b/i', $query) === 1;
    }

    public static function asksForGenomicAlterations(string $query): bool
    {
        return preg_match(
            '/\b(alterations?|mutations?|variants?|fusions?|cnv|copy[ -]?number|amplifications?|deletions?)\b/i',
            $query,
        ) === 1;
    }

    /**
     * The compatibility selector is stateless, so only give it conversation
     * history when the current message clearly depends on an earlier turn.
     * Complete new requests must be interpreted on their own.
     */
    public static function needsConversationContext(string $query): bool
    {
        $query = trim($query);
        if ($query === '') {
            return false;
        }

        return preg_match(
            '/(?:'
                .'^(?:please\s+)?(?:group|order|sort|filter|colour|color|split)\b'
                .'|^(?:please\s+)?(?:show|plot|render|make)(?:\s+me)?\s+(?:the\s+|a\s+)?(?:heatmap|violin(?:\s+plot)?|box\s*plot|bar\s*plot|column\s+plot)\b'
                .'|^(?:please\s+)?(?:use|switch|change)\b'
                .'|^(?:and|also|now|then|how about|what about)\b'
                .'|\b(?:it|them|those|same|previous|above|instead|again)\b'
            .')/i',
            $query,
        ) === 1;
    }
}
