<?php

declare(strict_types=1);

namespace Xray\AzureStorageLaravel\Exceptions;

use Exception;

class InvalidArgumentException extends Exception
{
    public static function make(string $message): self
    {
        return new self($message);
    }
}
