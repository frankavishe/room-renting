<?php
// api/admin/bookings_list.php
// GET — list all bookings, for the admin Bookings tab.
// Optional query param: ?status=pending|confirmed|cancelled
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

$sql = "SELECT b.id, b.start_date, b.end_date, b.status, b.created_at,
               r.id AS room_id, r.title AS room_title, r.location AS room_location,
               t.id AS tenant_id, t.name AS tenant_name, t.phone AS tenant_phone,
               p.status AS payment_status, p.reference AS payment_reference
        FROM bookings b
        INNER JOIN rooms r ON b.room_id = r.id
        INNER JOIN users t ON b.tenant_id = t.id
        LEFT  JOIN payments p ON p.booking_id = b.id";

$params = [];
if (!empty($_GET['status']) && in_array($_GET['status'], ['pending', 'confirmed', 'cancelled'], true)) {
    $sql .= " WHERE b.status = :status";
    $params[':status'] = $_GET['status'];
}

$sql .= " ORDER BY b.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);

http_response_code(200);
echo json_encode(["bookings" => $stmt->fetchAll()]);
