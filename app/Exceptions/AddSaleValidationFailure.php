<?php

namespace App\Exceptions;

use RuntimeException;

class AddSaleValidationFailure extends RuntimeException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $status,
        public array $errors
    ) {
        parent::__construct('Add sale validation failed.');
    }
}
