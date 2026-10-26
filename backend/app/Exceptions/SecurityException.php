<?php

namespace App\Exceptions;

class SecurityException extends \RuntimeException
{
}

if (!class_exists('SecurityException')) {
    class_alias(SecurityException::class, 'SecurityException');
}
