<?php
// api/admin/bookings_update_status.php
// POST — change a booking's status. Accepts JSON: { booking_id, status }
// Requires Authorization: Bearer <JWT> header, role = admin.
//
// When a booking is cancelled, the room is also marked available again
// (mirrors the symmetric logic in api/payments/callback.php, which marks
// a room occupied when a booking is confirmed via a successful payment).

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

if (empty($data->booking_id) || empty($data->status)) {
    http_response_code(400);
    echo json_encode(["message" => "booking_id and status are required."]);
    exit();
}

if (!in_array($data->status, ['pending', 'confirmed', 'cancelled'], true)) {
    http_response_code(400);
    echo json_encode(["message" => "Status must be one of: pending, confirmed, cancelled."]);
    exit();
}

$bookingId = (int) $data->booking_id;
$newStatus = $data->status;

$check = $db->prepare("SELECT id, room_id FROM bookings WHERE id = :id");
$check->execute([':id' => $bookingId]);
$booking = $check->fetch();

if (!$booking) {
    http_response_code(404);
    echo json_encode(["message" => "Booking not found."]);
    exit();
}

$db->beginTransaction();
try {
    $stmt = $db->prepare("UPDATE bookings SET status = :status WHERE id = :id");
    $stmt->execute([':status' => $newStatus, ':id' => $bookingId]);

    // Cancelling a booking frees up the room again.
    if ($newStatus === 'cancelled') {
        $roomStmt = $db->prepare("UPDATE rooms SET is_available = 1 WHERE id = :room_id");
        $roomStmt->execute([':room_id' => $booking['room_id']]);
    }

    $db->commit();

    http_response_code(200);
    echo json_encode(["message" => "Booking status updated."]);

} catch (Exception $e) {
    $db->rollBack();
    error_log("bookings_update_status error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["message" => "Server error updating booking."]);
}
