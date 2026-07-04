<?php
// api/bookings/my.php
// GET — returns all bookings for the currently logged-in tenant.

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization");

require_once '../../config/database.php';
require_once '../auth/jwt_helper.php';

$headers = getallheaders();
$token   = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
$decoded = verifyJWT($token);

if (!$decoded) {
    http_response_code(401);
    echo json_encode(["message" => "Unauthorized."]);
    exit();
}

// JOIN rooms so we can show the room title and location in one query.
$stmt = $db->prepare(
    "SELECT b.id, b.start_date, b.end_date, b.status, b.created_at,
            r.title AS room_title, r.location, r.price_per_month,
            p.status AS payment_status, p.reference AS payment_reference
     FROM bookings b
     INNER JOIN rooms r ON b.room_id = r.id
     LEFT  JOIN payments p ON p.booking_id = b.id
     WHERE b.tenant_id = :tenant_id
     ORDER BY b.created_at DESC"
);
$stmt->execute([':tenant_id' => $decoded['user_id']]);

echo json_encode(["bookings" => $stmt->fetchAll()]);
