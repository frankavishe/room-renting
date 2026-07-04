<?php
// api/auth/login.php
// Accepts POST JSON: { "phone": "", "password": "" }
// Returns JSON: { "token": "JWT...", "user": { id, name, role } }

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed."]);
    exit();
}

require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if (empty($data->phone) || empty($data->password)) {
    http_response_code(400);
    echo json_encode(["message" => "Phone and password are required."]);
    exit();
}

// Normalize phone just like in register.php
$phone = trim($data->phone);
if (substr($phone, 0, 1) === '0') {
    $phone = '+255' . substr($phone, 1);
}

// Fetch the user row by phone number.
$stmt = $db->prepare("SELECT id, name, email, phone, password, role FROM users WHERE phone = :phone");
$stmt->execute([':phone' => $phone]);
$user = $stmt->fetch();

if (!$user || !password_verify($data->password, $user['password'])) {
    http_response_code(401);
    echo json_encode(["message" => "Invalid phone number or password."]);
    exit();
}

// ----------------------------------------------------------------
// JWT Generation (manual, no library needed for simple use cases)
// A JWT has 3 parts separated by dots:  HEADER.PAYLOAD.SIGNATURE
// Header  → algorithm used
// Payload → the data we store (user id, role, expiry)
// Signature → proves the token hasn't been tampered with
// ----------------------------------------------------------------
$secretKey = "TZ_ROOMS_SECRET_CHANGE_THIS_IN_PRODUCTION";  // Change this!

$header = base64_encode(json_encode(["alg" => "HS256", "typ" => "JWT"]));

$payload = base64_encode(json_encode([
    "user_id" => $user['id'],
    "role"    => $user['role'],
    "phone"   => $user['phone'],
    "exp"     => time() + (60 * 60 * 24)  // Token expires in 24 hours
]));

// The signature is an HMAC-SHA256 hash of header+payload using the secret key.
// Anyone can decode the header/payload, but only the server can CREATE a valid signature.
$signature = base64_encode(hash_hmac('sha256', "$header.$payload", $secretKey, true));

$token = "$header.$payload.$signature";

http_response_code(200);
echo json_encode([
    "message" => "Login successful.",
    "token"   => $token,
    "user"    => [
        "id"    => $user['id'],
        "name"  => $user['name'],
        "email" => $user['email'],
        "phone" => $user['phone'],
        "role"  => $user['role'],
    ]
]);
