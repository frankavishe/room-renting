<?php
// api/payments/status.php
// GET ?reference=TZR-XXX
// Called repeatedly by the frontend to check if payment completed.
// Returns the current status of a payment by its reference code.

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || empty($_GET['reference'])) {
    http_response_code(400);
    echo json_encode(["message" => "GET request with ?reference= required."]);
    exit();
}

require_once '../../config/database.php';

$stmt = $db->prepare("SELECT status FROM payments WHERE reference = :ref");
$stmt->execute([':ref' => $_GET['reference']]);
$payment = $stmt->fetch();

if (!$payment) {
    http_response_code(404);
    echo json_encode(["message" => "Reference not found."]);
    exit();
}

echo json_encode(["status" => $payment['status']]);
