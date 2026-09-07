<?php
// api/admin/rooms_list.php
// GET — list ALL rooms (including unavailable ones — unlike the public
// api/rooms/read.php, which only returns available rooms), for the admin Rooms tab.
// Requires Authorization: Bearer <JWT> header, role = admin.

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Authorization");

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed."]);
    exit();
}

require_once '../../config/database.php';
require_once '../auth/jwt_helper.php';

$decoded = requireRole(['admin']);

$stmt = $db->query(
    "SELECT r.id, r.title, r.location, r.price_per_month, r.is_available, r.created_at,
            u.id AS owner_id, u.name AS owner_name, u.phone AS owner_phone,
            (SELECT COUNT(*) FROM bookings b WHERE b.room_id = r.id) AS booking_count
     FROM rooms r
     INNER JOIN users u ON r.owner_id = u.id
     ORDER BY r.created_at DESC"
);
$rooms = $stmt->fetchAll();

foreach ($rooms as &$r) {
    $r['is_available'] = (int) $r['is_available'];
    $r['booking_count'] = (int) $r['booking_count'];
}

http_response_code(200);
echo json_encode(["rooms" => $rooms]);
