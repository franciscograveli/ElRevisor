<?php

declare(strict_types=1);

namespace App\Service;

final class SignatureValidator
{
    public function __construct(private readonly string $secret)
    {
    }

    public function isValid(string $rawBody, ?string $signatureHeader): bool
    {
        if ($signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $this->secret);

        return hash_equals($expected, $signatureHeader);
    }
}
