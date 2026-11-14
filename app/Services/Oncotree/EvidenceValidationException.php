<?php

namespace App\Services\Oncotree;

class EvidenceValidationException extends \InvalidArgumentException
{
    public function __construct(public readonly array $details, ?\Throwable $previous = null)
    {
        parent::__construct((string) $details['reason'], 0, $previous);
    }
}
