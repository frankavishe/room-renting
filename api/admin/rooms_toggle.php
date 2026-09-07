<?php
// api/admin/rooms_toggle.php
// POST — flips a room's is_available flag. Accepts JSON: { room_id }
// The primary admin action on a room: works even when the room has bookings
// (unlike delete, which is blocked by the FK in that case).
// Requires Authorization: Bearer <JWT> header, role = admin.

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

$decoded = requireRole(['admin']);

$data = json_decode(file_get_contents("php://input"));

if (empty($data->room_id)) {
    http_response_code(400);
    echo json_encode(["message" => "room_id is required."]);
    exit();
}

$roomId = (int) $data->room_id;

$check = $db->prepare("SELECT is_available FROM rooms WHERE id = :id");
$check->execute([':id' => $roomId]);
$room = $check->fetch();

if (!$room) {
    http_response_code(404);
    echo json_encode(["message" => "Room not found."]);
    exit();
}

$newValue = $room['is_available'] ? 0 : 1;

$stmt = $db->prepare("UPDATE rooms SET is_available = :val WHERE id = :id");
$stmt->execute([':val' => $newValue, ':id' => $roomId]);

http_response_code(200);
echo json_encode(["message" => "Room availability updated.", "is_available" => $newValue]);
