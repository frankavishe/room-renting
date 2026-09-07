<?php
// api/admin/users_update_role.php
// POST — change a user's role. Accepts JSON: { user_id, role }
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

if (empty($data->user_id) || empty($data->role)) {
    http_response_code(400);
    echo json_encode(["message" => "user_id and role are required."]);
    exit();
}

if (!in_array($data->role, ['tenant', 'landlord', 'admin'], true)) {
    http_response_code(400);
    echo json_encode(["message" => "Role must be one of: tenant, landlord, admin."]);
    exit();
}

$userId = (int) $data->user_id;

// Prevent an admin from demoting themselves — with no session/token revocation
// in this app, that could lock them out of the panel with no way back in.
if ($userId === (int) $decoded['user_id'] && $data->role !== 'admin') {
    http_response_code(409);
    echo json_encode(["message" => "You cannot change your own role."]);
    exit();
}

$check = $db->prepare("SELECT id FROM users WHERE id = :id");
$check->execute([':id' => $userId]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(["message" => "User not found."]);
    exit();
}

$stmt = $db->prepare("UPDATE users SET role = :role WHERE id = :id");
$stmt->execute([':role' => $data->role, ':id' => $userId]);

http_response_code(200);
echo json_encode(["message" => "Role updated."]);
