<?php

namespace InventoryApp\Infrastructure\Identity;

use Illuminate\Database\Capsule\Manager as DB;
use DateTimeImmutable;

/**
 * Issues and validates opaque API tokens stored in the `api_tokens` table.
 *
 * Tokens are random 64-byte hex strings stored as SHA-256 hashes (so the DB
 * never holds the plaintext value — similar to Laravel Sanctum's approach).
 *
 * Each token carries the user_id and tenant_id so the auth middleware can
 * reconstruct a security context without a round-trip to the users table.
 */
class ApiTokenService
{
    private int $tokenTtlDays;

    public function __construct(int $tokenTtlDays = 30)
    {
        $this->tokenTtlDays = $tokenTtlDays;
    }

    /**
     * Create and persist a new token. Returns the plaintext token to hand
     * back to the client — this is the only time the plaintext is available.
     */
    public function issue(string $userId, string $tenantId): string
    {
        $plaintext = bin2hex(random_bytes(64)); // 128-char hex string
        $hash      = hash('sha256', $plaintext);
        $expiresAt = (new DateTimeImmutable())->modify("+{$this->tokenTtlDays} days");

        DB::table('api_tokens')->insert([
            'id'         => uniqid('tok_', true),
            'user_id'    => $userId,
            'tenant_id'  => $tenantId,
            'token_hash' => $hash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $plaintext;
    }

    /**
     * Validate a plaintext token from the Authorization header.
     * Returns the token row (with user_id, tenant_id) or null if invalid/expired.
     */
    public function validate(string $plaintext): ?object
    {
        $hash = hash('sha256', $plaintext);

        $token = DB::table('api_tokens')
            ->where('token_hash', $hash)
            ->first(['user_id', 'tenant_id', 'expires_at']);

        if (!$token) {
            error_log("Token hash not found in DB: " . $hash . " for plaintext prefix " . substr($plaintext, 0, 10));
            return null;
        }

        $now = date('Y-m-d H:i:s');
        if ($token->expires_at <= $now) {
            error_log("Token expired: expires_at=" . $token->expires_at . ", now=" . $now);
            return null;
        }

        return $token;
    }

    /**
     * Revoke all tokens for a user (e.g., on password change or deactivation).
     */
    public function revokeAll(string $userId): void
    {
        DB::table('api_tokens')->where('user_id', $userId)->delete();
    }
}
