<?php

namespace App\Packages\Services\Sites\Explorer\Exceptions;

use RuntimeException;

class ServerFileExplorerException extends RuntimeException
{
    public function __construct(string $message, protected int $status = 422)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
