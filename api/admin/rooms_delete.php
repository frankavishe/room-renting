<?php
// api/admin/rooms_delete.php
// POST — delete a room listing. Accepts JSON: { room_id }
// Requires Authorization: Bearer <JWT> header, role = admin.
// rooms -> bookings is ON DELETE RESTRICT, so rooms with existing bookings
// cannot be deleted — the UI should steer admins toward rooms_toggle.php
// instead and only offer delete when a room has zero bookings.

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

$check = $db->prepare("SELECT id FROM rooms WHERE id = :id");
$check->execute([':id' => $roomId]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(["message" => "Room not found."]);
    exit();
}

try {
    $stmt = $db->prepare("DELETE FROM rooms WHERE id = :id");
    $stmt->execute([':id' => $roomId]);

    http_response_code(200);
    echo json_encode(["message" => "Room deleted."]);

} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(["message" => "Cannot delete this room: it has existing bookings."]);
        exit();
    }
    error_log("rooms_delete error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["message" => "Server error deleting room."]);
}
