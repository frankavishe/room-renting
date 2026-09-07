<?php
// api/admin/payments_list.php
// GET — list all payments, for the admin Payments tab. Read-only: there is
// intentionally no mutation endpoint here — api/payments/callback.php (the
// gateway webhook) is the sole source of truth for payment status, so an
// admin can't force-flip it and desync from the real gateway.
// Optional query param: ?status=pending|successful|failed
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

$sql = "SELECT p.id, p.reference, p.provider, p.amount, p.status, p.updated_at,
               b.id AS booking_id, t.name AS tenant_name, r.title AS room_title
        FROM payments p
        INNER JOIN bookings b ON p.booking_id = b.id
        INNER JOIN users t    ON b.tenant_id = t.id
        INNER JOIN rooms r    ON b.room_id = r.id";

$params = [];
if (!empty($_GET['status']) && in_array($_GET['status'], ['pending', 'successful', 'failed'], true)) {
    $sql .= " WHERE p.status = :status";
    $params[':status'] = $_GET['status'];
}

$sql .= " ORDER BY p.updated_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);

http_response_code(200);
echo json_encode(["payments" => $stmt->fetchAll()]);
