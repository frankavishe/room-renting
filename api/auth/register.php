<?php
// api/auth/register.php
// Accepts POST JSON: { "name": "", "phone": "", "email": "", "password": "", "role": "" }
// Returns JSON: { "message": "..." }  with HTTP status 201 on success, 4xx on failure.

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Only allow POST requests — reject everything else.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed."]);
    exit();
}

// Load the PDO connection — $db is now available.
require_once '../../config/database.php';

// Read the raw JSON body sent by the frontend fetch() call.
// php://input is a read-only stream that gives us the raw POST body.
$data = json_decode(file_get_contents("php://input"));

// Validate required fields exist and are not empty.
if (empty($data->name) || empty($data->phone) || empty($data->password)) {
    http_response_code(400);
    echo json_encode(["message" => "Name, phone, and password are required."]);
    exit();
}

// Normalize phone: if user types 0712345678, convert to +255712345678
$phone = trim($data->phone);
if (substr($phone, 0, 1) === '0') {
    $phone = '+255' . substr($phone, 1);
}

// Validate phone format: must be +255 followed by 9 digits
if (!preg_match('/^\+255\d{9}$/', $phone)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid phone number. Use format: 07XXXXXXXX or +255XXXXXXXXX"]);
    exit();
}

// Validate role if provided — must be tenant or landlord (admin is set manually).
$role = isset($data->role) && in_array($data->role, ['tenant', 'landlord']) ? $data->role : 'tenant';

// Hash the password using bcrypt (PHP's default cost of 10 is fine).
// This is a one-way hash — you can never reverse it to get the original password.
$hashedPassword = password_hash($data->password, PASSWORD_BCRYPT);

$email = !empty($data->email) ? trim($data->email) : null;

// Check if phone already exists — we don't want duplicates.
// This uses a PREPARED STATEMENT: :phone is a placeholder, not raw user input.
// This prevents SQL Injection attacks.
$checkStmt = $db->prepare("SELECT id FROM users WHERE phone = :phone");
$checkStmt->execute([':phone' => $phone]);
if ($checkStmt->fetch()) {
    http_response_code(409);  // 409 Conflict
    echo json_encode(["message" => "Phone number already registered."]);
    exit();
}

// Insert the new user into the database.
$stmt = $db->prepare(
    "INSERT INTO users (name, email, phone, password, role)
     VALUES (:name, :email, :phone, :password, :role)"
);

$stmt->execute([
    ':name'     => trim($data->name),
    ':email'    => $email,
    ':phone'    => $phone,
    ':password' => $hashedPassword,
    ':role'     => $role,
]);

// 201 Created — standard HTTP status for a successful resource creation.
http_response_code(201);
echo json_encode([
    "message" => "Registration successful.",
    "user_id" => $db->lastInsertId()
]);
