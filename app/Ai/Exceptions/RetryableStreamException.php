<?php

namespace App\Ai\Exceptions;

use Laravel\Ai\Exceptions\FailoverableException;
use RuntimeException;

/**
 * An error delivered inside an otherwise successful provider stream that is
 * safe for the chatbot runner to resume on the next configured provider.
 */
class RetryableStreamException extends RuntimeException implements FailoverableException {}
