<?php
// api/auth/jwt_helper.php
// Shared helper for verifying JWT tokens on protected endpoints.
// Any API file that needs authentication requires_once this file.

define('JWT_SECRET', "TZ_ROOMS_SECRET_CHANGE_THIS_IN_PRODUCTION");

/**
 * Verifies a JWT token and returns the decoded payload array.
 * Returns false if the token is invalid, expired, or tampered with.
 *
 * How it works:
 *  1. Split the token into 3 parts: header, payload, signature
 *  2. Re-compute the expected signature from header+payload using our secret
 *  3. If our computed signature matches the token's signature → valid
 *  4. Check the expiry time (exp) in the payload
 */
function verifyJWT(string $token): array|false
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;

    [$header, $payload, $signature] = $parts;

    // Re-compute the signature the same way login.php created it.
    $expectedSignature = base64_encode(
        hash_hmac('sha256', "$header.$payload", JWT_SECRET, true)
    );

    // Compare signatures using hash_equals to prevent timing attacks.
    // (Regular === comparison leaks timing info that attackers can exploit.)
    if (!hash_equals($expectedSignature, $signature)) return false;

    $decoded = json_decode(base64_decode($payload), true);
    if (!$decoded) return false;

    // Check token has not expired.
    if (isset($decoded['exp']) && $decoded['exp'] < time()) return false;

    return $decoded;
}

/**
 * Reads the "Authorization: Bearer <token>" header, if present.
 * Returns just the token string, or null if the header is missing/malformed.
 */
function getBearerToken(): ?string
{
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (stripos($auth, 'Bearer ') !== 0) return null;
    return substr($auth, 7);
}

/**
 * Verifies the request's bearer token AND that its role is in $allowedRoles.
 * On failure, sends the appropriate 401/403 JSON response and exits —
 * so callers can just do: $decoded = requireRole(['admin']);
 * and use $decoded afterwards knowing it's valid.
 */
function requireRole(array $allowedRoles): array
{
    $token   = getBearerToken();
    $decoded = $token ? verifyJWT($token) : false;

    if (!$decoded) {
        http_response_code(401);
        echo json_encode(["message" => "Unauthorized. Please log in."]);
        exit();
    }

    if (!in_array($decoded['role'], $allowedRoles, true)) {
        http_response_code(403);
        echo json_encode(["message" => "Forbidden. You do not have permission to perform this action."]);
        exit();
    }

    return $decoded;
}
