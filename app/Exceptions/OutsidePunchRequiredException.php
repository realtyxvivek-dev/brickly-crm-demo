<?php

namespace App\Exceptions;

use Exception;

class OutsidePunchRequiredException extends Exception
{
    public function __construct(
        string $message,
        protected array $context = []
    ) {
        parent::__construct($message);
    }

    public function context(): array
    {
        return $this->context;
    }
}
