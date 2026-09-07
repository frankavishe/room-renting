<?php
// api/admin/stats.php
// GET — dashboard summary counts + revenue, for the admin Overview tab.
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

// --- Users by role ---
$users = ['total' => 0, 'tenants' => 0, 'landlords' => 0, 'admins' => 0];
$stmt = $db->query("SELECT role, COUNT(*) AS c FROM users GROUP BY role");
foreach ($stmt->fetchAll() as $row) {
    $users['total'] += (int) $row['c'];
    if ($row['role'] === 'tenant')   $users['tenants']   = (int) $row['c'];
    if ($row['role'] === 'landlord') $users['landlords']  = (int) $row['c'];
    if ($row['role'] === 'admin')    $users['admins']     = (int) $row['c'];
}

// --- Rooms by availability ---
$rooms = ['total' => 0, 'available' => 0, 'occupied' => 0];
$stmt = $db->query("SELECT is_available, COUNT(*) AS c FROM rooms GROUP BY is_available");
foreach ($stmt->fetchAll() as $row) {
    $rooms['total'] += (int) $row['c'];
    if ((int) $row['is_available'] === 1) $rooms['available'] = (int) $row['c'];
    else                                   $rooms['occupied']  = (int) $row['c'];
}

// --- Bookings by status ---
$bookings = ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0];
$stmt = $db->query("SELECT status, COUNT(*) AS c FROM bookings GROUP BY status");
foreach ($stmt->fetchAll() as $row) {
    $bookings['total'] += (int) $row['c'];
    $bookings[$row['status']] = (int) $row['c'];
}

// --- Payments by status + revenue ---
$payments = ['total' => 0, 'pending' => 0, 'successful' => 0, 'failed' => 0,
             'revenue_total' => 0.0, 'revenue_this_month' => 0.0];
$stmt = $db->query("SELECT status, COUNT(*) AS c, SUM(amount) AS total FROM payments GROUP BY status");
foreach ($stmt->fetchAll() as $row) {
    $payments['total'] += (int) $row['c'];
    $payments[$row['status']] = (int) $row['c'];
    if ($row['status'] === 'successful') {
        $payments['revenue_total'] = (float) $row['total'];
    }
}

$stmt = $db->query(
    "SELECT SUM(amount) AS total FROM payments
     WHERE status = 'successful'
       AND MONTH(updated_at) = MONTH(CURDATE())
       AND YEAR(updated_at) = YEAR(CURDATE())"
);
$row = $stmt->fetch();
$payments['revenue_this_month'] = (float) ($row['total'] ?? 0);

http_response_code(200);
echo json_encode([
    "users"    => $users,
    "rooms"    => $rooms,
    "bookings" => $bookings,
    "payments" => $payments,
]);
