<?php

namespace App\Ai\Exceptions;

use Laravel\Ai\Exceptions\FailoverableException;
use RuntimeException;

class ProviderStreamTimeout extends RuntimeException implements FailoverableException
{
}
