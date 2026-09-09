<?php
// config/mail.php
// SMTP settings used by api/auth/forgot_password.php to send password-reset emails.
//
// >>> FILL THESE IN before the forgot-password feature will actually send mail. <<<
// For Gmail: use an "App Password" (myaccount.google.com/apppasswords), not your
// normal account password — Gmail rejects plain passwords for SMTP login.

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);                          // 587 = STARTTLS, 465 = SMTPS
define('SMTP_USER', 'your-email@gmail.com');        // the Gmail address that will send the emails
define('SMTP_PASS', 'your-16-char-app-password');   // Gmail App Password, not your login password
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');  // usually same as SMTP_USER
define('SMTP_FROM_NAME', 'TZ-Rooms');

// Base URL the reset link points back to (no trailing slash).
// Change if the app is hosted somewhere other than local XAMPP.
define('APP_BASE_URL', 'http://localhost/room-renting');
