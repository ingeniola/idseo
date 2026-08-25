<?php

declare(strict_types=1);

namespace App\DataForSeo\Exceptions;

use RuntimeException;

abstract class DataForSeoException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $apiStatusCode = null,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message);
    }
}
