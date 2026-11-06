<?php

namespace App\Exceptions;

use RuntimeException;

class OfferUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Questo modulo non è più disponibile.');
    }
}
