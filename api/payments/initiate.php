<?php
// api/payments/initiate.php
// POST — initiates a mobile money STK Push payment.
// Accepts JSON: { booking_id, phone, amount }
// Requires Authorization: Bearer <JWT>

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed."]);
    exit();
}

require_once '../../config/database.php';
require_once '../auth/jwt_helper.php';

// Verify user is logged in.
$headers = getallheaders();
$token   = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
$decoded = verifyJWT($token);

if (!$decoded) {
    http_response_code(401);
    echo json_encode(["message" => "Unauthorized."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->booking_id) || empty($data->phone) || empty($data->amount)) {
    http_response_code(400);
    echo json_encode(["message" => "booking_id, phone, and amount are required."]);
    exit();
}

// Normalize phone: 0712345678 → +255712345678
$phone = trim($data->phone);
if (substr($phone, 0, 1) === '0') {
    $phone = '+255' . substr($phone, 1);
}

// Verify the booking belongs to this user and is still pending.
$bookingStmt = $db->prepare(
    "SELECT id FROM bookings WHERE id = :id AND tenant_id = :tenant_id AND status = 'pending'"
);
$bookingStmt->execute([
    ':id'        => (int) $data->booking_id,
    ':tenant_id' => $decoded['user_id'],
]);
if (!$bookingStmt->fetch()) {
    http_response_code(404);
    echo json_encode(["message" => "Booking not found or already processed."]);
    exit();
}

// Generate a unique temporary reference for this transaction.
// This reference ties the gateway callback back to this specific payment row.
$tempRef  = "TZR-" . strtoupper(uniqid());
$provider = "MOBILE_MONEY";  // Will be determined by the gateway based on phone prefix

// Step 1: Insert a 'pending' payment record BEFORE calling the gateway.
// This ensures we always have a record even if the gateway call fails.
$payStmt = $db->prepare(
    "INSERT INTO payments (booking_id, amount, provider, reference, status)
     VALUES (:booking_id, :amount, :provider, :reference, 'pending')"
);
$payStmt->execute([
    ':booking_id' => (int) $data->booking_id,
    ':amount'     => (float) $data->amount,
    ':provider'   => $provider,
    ':reference'  => $tempRef,
]);

// Step 2: Fire the STK Push request to the payment gateway using cURL.
// cURL is PHP's tool for making HTTP requests to external servers.
$gatewayUrl = "https://api.tz-gateway.co.tz/v1/stk/push";  // Replace with real gateway URL

$callbackUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') .
               "://" . $_SERVER['HTTP_HOST'] . "/api/payments/callback.php";

$payload = json_encode([
    "amount"       => (float) $data->amount,
    "phone"        => $phone,
    "reference"    => $tempRef,
    "callback_url" => $callbackUrl
]);

$ch = curl_init($gatewayUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   // Return response as string, don't print
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);             // Wait max 30 seconds for gateway
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer GATEWAY_SECRET_KEY"     // Replace with your real gateway API key
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// If the gateway is unreachable during development, we still respond with the reference
// so the frontend can start polling. In production, handle gateway errors more strictly.
if ($curlError) {
    // Log the error but don't block the user — gateway may still push asynchronously.
    error_log("Gateway cURL error: " . $curlError);
}

http_response_code(200);
echo json_encode([
    "status"         => "initiated",
    "message"        => "Check your handset for a PIN prompt.",
    "temp_reference" => $tempRef
]);
