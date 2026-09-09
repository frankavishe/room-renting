<?php
// api/auth/reset_password.php
// Accepts POST JSON: { "token": "", "password": "" }
// Returns JSON: { "message": "..." } — 200 on success, 400 on invalid/expired token.

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed."]);
    exit();
}

require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if (empty($data->token) || empty($data->password)) {
    http_response_code(400);
    echo json_encode(["message" => "Token and new password are required."]);
    exit();
}

$tokenHash = hash('sha256', $data->token);

$stmt = $db->prepare(
    "SELECT id, user_id FROM password_reset_tokens
     WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > NOW()"
);
$stmt->execute([':token_hash' => $tokenHash]);
$resetRow = $stmt->fetch();

if (!$resetRow) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid or expired reset link. Please request a new one."]);
    exit();
}

$hashedPassword = password_hash($data->password, PASSWORD_BCRYPT);

$updateStmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
$updateStmt->execute([
    ':password' => $hashedPassword,
    ':id'       => $resetRow['user_id'],
]);

$markUsedStmt = $db->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id");
$markUsedStmt->execute([':id' => $resetRow['id']]);

http_response_code(200);
echo json_encode(["message" => "Password reset successful. You can now log in with your new password."]);
