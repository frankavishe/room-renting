<?php
// api/bookings/create.php
// POST — tenant creates a booking for a room.
// Accepts JSON: { room_id, start_date, end_date }
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

// Verify the user is logged in.
$headers = getallheaders();
$token   = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
$decoded = verifyJWT($token);

if (!$decoded) {
    http_response_code(401);
    echo json_encode(["message" => "Please log in to make a booking."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->room_id) || empty($data->start_date) || empty($data->end_date)) {
    http_response_code(400);
    echo json_encode(["message" => "room_id, start_date, and end_date are required."]);
    exit();
}

// Confirm the room exists and is currently available.
$roomStmt = $db->prepare("SELECT id, is_available FROM rooms WHERE id = :id");
$roomStmt->execute([':id' => (int) $data->room_id]);
$room = $roomStmt->fetch();

if (!$room) {
    http_response_code(404);
    echo json_encode(["message" => "Room not found."]);
    exit();
}

if (!$room['is_available']) {
    http_response_code(409);
    echo json_encode(["message" => "Room is no longer available."]);
    exit();
}

// Check for date overlap — prevent double-booking the same room for overlapping dates.
// This query finds any existing confirmed booking that overlaps our requested dates.
$overlapStmt = $db->prepare(
    "SELECT id FROM bookings
     WHERE room_id = :room_id
       AND status = 'confirmed'
       AND start_date < :end_date
       AND end_date > :start_date"
);
$overlapStmt->execute([
    ':room_id'    => (int) $data->room_id,
    ':start_date' => $data->start_date,
    ':end_date'   => $data->end_date,
]);
if ($overlapStmt->fetch()) {
    http_response_code(409);
    echo json_encode(["message" => "Room is already booked for those dates."]);
    exit();
}

// Create the booking — status starts as 'pending' until payment succeeds.
$stmt = $db->prepare(
    "INSERT INTO bookings (room_id, tenant_id, start_date, end_date)
     VALUES (:room_id, :tenant_id, :start_date, :end_date)"
);
$stmt->execute([
    ':room_id'    => (int) $data->room_id,
    ':tenant_id'  => $decoded['user_id'],
    ':start_date' => $data->start_date,
    ':end_date'   => $data->end_date,
]);

$bookingId = $db->lastInsertId();

http_response_code(201);
echo json_encode([
    "message"    => "Booking created. Proceed to payment.",
    "booking_id" => $bookingId
]);
