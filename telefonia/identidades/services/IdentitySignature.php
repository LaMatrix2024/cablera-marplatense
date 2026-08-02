<?php

final class IdentitySignature
{
    public static function bodyHash(string $body): string
    {
        return hash('sha256', $body);
    }

    public static function payload(
        string $method,
        string $path,
        string $timestamp,
        string $clientId,
        string $actorEmail,
        string $body
    ): string {
        return implode("\n", [
            strtoupper($method),
            $path,
            $timestamp,
            $clientId,
            strtolower(trim($actorEmail)),
            self::bodyHash($body),
        ]);
    }

    public static function sign(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }
}
