<?php

namespace App\Exceptions;

use RuntimeException;

class FirebaseAuthException extends RuntimeException
{
    public function __construct(public readonly string $reason = 'UNAVAILABLE')
    {
        // Never attach HTTP exceptions: their URLs/bodies can contain keys and passwords.
        parent::__construct('Layanan autentikasi belum dapat dihubungi. Silakan coba lagi.');
    }
}
