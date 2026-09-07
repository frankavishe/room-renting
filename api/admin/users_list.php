<?php
// api/admin/users_list.php
// GET — list all users, for the admin Users tab.
// Optional query param: ?role=tenant|landlord|admin
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

// room_count / booking_count via correlated subqueries — never returns the password column.
$sql = "SELECT u.id, u.name, u.email, u.phone, u.role, u.created_at,
               (SELECT COUNT(*) FROM rooms r WHERE r.owner_id = u.id)      AS room_count,
               (SELECT COUNT(*) FROM bookings b WHERE b.tenant_id = u.id)  AS booking_count
        FROM users u";

$params = [];
if (!empty($_GET['role']) && in_array($_GET['role'], ['tenant', 'landlord', 'admin'], true)) {
    $sql .= " WHERE u.role = :role";
    $params[':role'] = $_GET['role'];
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

foreach ($users as &$u) {
    $u['room_count']    = (int) $u['room_count'];
    $u['booking_count'] = (int) $u['booking_count'];
}

http_response_code(200);
echo json_encode(["users" => $users]);
