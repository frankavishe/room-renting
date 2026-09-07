<?php
// api/admin/users_delete.php
// POST — delete a user. Accepts JSON: { user_id }
// Requires Authorization: Bearer <JWT> header, role = admin.
//
// Respects the schema's existing FK semantics rather than working around them:
//   users -> rooms     ON DELETE CASCADE   (landlord's rooms are auto-deleted...)
//   rooms -> bookings  ON DELETE RESTRICT  (...unless one of those rooms has bookings)
//   users -> bookings  ON DELETE CASCADE   (tenant's bookings are auto-deleted...)
//   bookings -> payments ON DELETE RESTRICT (...unless one of those bookings has a payment)
// So deleting a user with dependent rooms/bookings/payments fails at the DB level;
// we catch that and report it clearly instead of a raw 500.

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

if (empty($data->user_id)) {
    http_response_code(400);
    echo json_encode(["message" => "user_id is required."]);
    exit();
}

$userId = (int) $data->user_id;

if ($userId === (int) $decoded['user_id']) {
    http_response_code(409);
    echo json_encode(["message" => "You cannot delete your own account."]);
    exit();
}

$check = $db->prepare("SELECT id FROM users WHERE id = :id");
$check->execute([':id' => $userId]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(["message" => "User not found."]);
    exit();
}

try {
    $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);

    http_response_code(200);
    echo json_encode(["message" => "User deleted."]);

} catch (PDOException $e) {
    // SQLSTATE 23000 = integrity constraint violation (a FK RESTRICT blocked the delete).
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode([
            "message" => "Cannot delete this user: related rooms, bookings, or payments are protected. Resolve those first."
        ]);
        exit();
    }
    error_log("users_delete error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["message" => "Server error deleting user."]);
}
