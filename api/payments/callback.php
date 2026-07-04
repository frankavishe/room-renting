<?php
// api/payments/callback.php
// This is a WEBHOOK endpoint — it is called by the payment gateway server, NOT the browser.
// The gateway POSTs here when the user completes (or fails) their mobile money PIN entry.
// This URL must be publicly accessible (no JWT required — the gateway calls it).

header("Content-Type: application/json; charset=UTF-8");

// Only allow POST from the gateway.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed."]);
    exit();
}

require_once '../../config/database.php';

// Read the raw JSON payload the gateway sends us.
$rawBody = file_get_contents("php://input");
$data    = json_decode($rawBody, true);

// The gateway sends us the reference we originally sent + a status.
// Typical payload: { "reference": "TZR-XXX", "status": "successful", "provider_ref": "ABC123" }
if (empty($data['reference']) || empty($data['status'])) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid callback payload."]);
    exit();
}

$reference = $data['reference'];
$status    = in_array($data['status'], ['successful', 'failed']) ? $data['status'] : 'failed';

// Find the payment record matching this reference.
$payStmt = $db->prepare("SELECT id, booking_id FROM payments WHERE reference = :ref");
$payStmt->execute([':ref' => $reference]);
$payment = $payStmt->fetch();

if (!$payment) {
    http_response_code(404);
    echo json_encode(["message" => "Payment reference not found."]);
    exit();
}

// Use a database TRANSACTION so both updates happen together or not at all.
// If updating payments succeeds but updating bookings fails (or vice versa),
// the transaction rolls back — preventing inconsistent data.
$db->beginTransaction();
try {
    // Update the payment status and store the raw callback for auditing.
    $updatePay = $db->prepare(
        "UPDATE payments SET status = :status, callback_raw = :raw WHERE reference = :ref"
    );
    $updatePay->execute([
        ':status' => $status,
        ':raw'    => $rawBody,
        ':ref'    => $reference,
    ]);

    // If payment was successful, confirm the booking and mark the room as occupied.
    if ($status === 'successful') {
        $updateBooking = $db->prepare(
            "UPDATE bookings SET status = 'confirmed' WHERE id = :id"
        );
        $updateBooking->execute([':id' => $payment['booking_id']]);

        $updateRoom = $db->prepare(
            "UPDATE rooms SET is_available = 0
             WHERE id = (SELECT room_id FROM bookings WHERE id = :id)"
        );
        $updateRoom->execute([':id' => $payment['booking_id']]);
    }

    $db->commit();  // Everything succeeded — save all changes.

    http_response_code(200);
    echo json_encode(["message" => "Callback processed."]);

} catch (Exception $e) {
    $db->rollBack();  // Something failed — undo all changes.
    error_log("Callback transaction error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["message" => "Server error processing callback."]);
}
