<?php
// api/auth/forgot_password.php
// Accepts POST JSON: { "email": "" }
// Always returns a generic 200 message so this endpoint can't be used to
// find out which emails are registered. If (and only if) the email matches
// an account, a password-reset link is emailed to it.

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
require_once '../../config/mail.php';
require_once '../../lib/PHPMailer/Exception.php';
require_once '../../lib/PHPMailer/PHPMailer.php';
require_once '../../lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

$data = json_decode(file_get_contents("php://input"));

if (empty($data->email)) {
    http_response_code(400);
    echo json_encode(["message" => "Email is required."]);
    exit();
}

$email = trim($data->email);

// The message we send back no matter what — never reveal whether the email exists.
$genericResponse = ["message" => "If an account exists for that email, a password reset link has been sent."];

$stmt = $db->prepare("SELECT id, name, email FROM users WHERE email = :email");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(200);
    echo json_encode($genericResponse);
    exit();
}

// Generate a random token. The raw token goes in the emailed link only;
// we store just its sha256 hash so a leaked DB can't be used to reset accounts.
$rawToken   = bin2hex(random_bytes(32));
$tokenHash  = hash('sha256', $rawToken);

// Compute the expiry with MySQL's own NOW(), not PHP's date(): the app server's
// PHP timezone and the DB server's timezone don't necessarily agree, and
// expires_at is later compared against NOW() by MySQL in reset_password.php —
// so both sides of that comparison must come from the same clock.
$insertStmt = $db->prepare(
    "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
     VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
);
$insertStmt->execute([
    ':user_id'    => $user['id'],
    ':token_hash' => $tokenHash,
]);

$resetLink = APP_BASE_URL . '/reset-password.html?token=' . $rawToken;

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;

    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress($user['email'], $user['name']);

    $mail->isHTML(true);
    $mail->Subject = 'Reset your TZ-Rooms password';
    $mail->Body    = "Hi {$user['name']},<br><br>"
        . "We received a request to reset your TZ-Rooms password. Click the link below to choose a new one:<br><br>"
        . "<a href=\"{$resetLink}\">{$resetLink}</a><br><br>"
        . "This link expires in 1 hour. If you didn't request this, you can safely ignore this email.";
    $mail->AltBody = "Reset your TZ-Rooms password: {$resetLink} (expires in 1 hour)";

    $mail->send();
} catch (PHPMailerException $e) {
    // Don't leak mail errors to the client — log server-side and keep the generic response.
    error_log("Failed to send password reset email: " . $mail->ErrorInfo);
}

http_response_code(200);
echo json_encode($genericResponse);
