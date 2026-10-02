<?php
/**
 * OrderGo - Native SMTP Mailer Service
 * Communicates directly with SMTP server (e.g., Hostinger SSL/TLS) without external dependencies.
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Send an email via SMTP
 *
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $htmlContent HTML body content
 * @param string $toName Recipient display name
 * @param string $plainContent Plain text fallback
 * @return array ['success' => bool, 'error' => string|null, 'detail' => string]
 */
function send_mail(string $to, string $subject, string $htmlContent, string $toName = '', string $plainContent = ''): array {
    $host   = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.hostinger.com';
    $port   = defined('SMTP_PORT') ? SMTP_PORT : 465;
    $secure = defined('SMTP_SECURE') ? SMTP_SECURE : 'ssl';
    $user   = defined('SMTP_USER') ? SMTP_USER : 'admin@specanciens.com';
    $pass   = defined('SMTP_PASS') ? SMTP_PASS : 'QU6Sob0doq@1';
    $from   = defined('EMAIL_FROM') ? EMAIL_FROM : 'admin@specanciens.com';
    $fromName = defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : 'OrderGo Canteen';

    // Build socket remote address
    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    $socket = @stream_socket_client($remote, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        $error = "Failed to connect to mail server {$host}:{$port} - {$errstr} ({$errno})";
        error_log("[OrderGo Mailer] " . $error);
        return ['success' => false, 'error' => $error];
    }

    stream_set_timeout($socket, 15);

    // Helper to read multiline SMTP response
    $readResponse = function() use ($socket): string {
        $data = '';
        while (!feof($socket)) {
            $line = fgets($socket, 512);
            if ($line === false) break;
            $data .= $line;
            // SMTP lines end with <code><space> on final line, or <code><dash> on continuation
            if (strlen($line) >= 4 && substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return $data;
    };

    // Helper to send command and verify expected response code
    $sendCommand = function(string $cmd, array $expectedCodes) use ($socket, $readResponse): array {
        fputs($socket, $cmd . "\r\n");
        $resp = $readResponse();
        $code = (int)substr($resp, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            return ['ok' => false, 'code' => $code, 'resp' => trim($resp)];
        }
        return ['ok' => true, 'code' => $code, 'resp' => trim($resp)];
    };

    // Read initial greeting
    $greeting = $readResponse();
    $greetCode = (int)substr($greeting, 0, 3);
    if ($greetCode !== 220) {
        fclose($socket);
        return ['success' => false, 'error' => "Invalid greeting: " . trim($greeting)];
    }

    // EHLO
    $clientDomain = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $ehlo = $sendCommand("EHLO {$clientDomain}", [250]);
    if (!$ehlo['ok']) {
        fclose($socket);
        return ['success' => false, 'error' => "EHLO rejected: " . $ehlo['resp']];
    }

    // If port 587 and STARTTLS is required
    if ($port === 587 || $secure === 'tls') {
        $tls = $sendCommand("STARTTLS", [220]);
        if ($tls['ok']) {
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $sendCommand("EHLO {$clientDomain}", [250]);
        }
    }

    // AUTH LOGIN
    $auth = $sendCommand("AUTH LOGIN", [334]);
    if (!$auth['ok']) {
        fclose($socket);
        return ['success' => false, 'error' => "AUTH LOGIN rejected: " . $auth['resp']];
    }

    // Send Base64 Username
    $uResp = $sendCommand(base64_encode($user), [334]);
    if (!$uResp['ok']) {
        fclose($socket);
        return ['success' => false, 'error' => "SMTP Username rejected: " . $uResp['resp']];
    }

    // Send Base64 Password
    $pResp = $sendCommand(base64_encode($pass), [235]);
    if (!$pResp['ok']) {
        fclose($socket);
        return ['success' => false, 'error' => "SMTP Authentication failed: " . $pResp['resp']];
    }

    // MAIL FROM
    $mfResp = $sendCommand("MAIL FROM:<{$from}>", [250]);
    if (!$mfResp['ok']) {
        fclose($socket);
        return ['success' => false, 'error' => "MAIL FROM rejected: " . $mfResp['resp']];
    }

    // RCPT TO
    $rcptResp = $sendCommand("RCPT TO:<{$to}>", [250, 251]);
    if (!$rcptResp['ok']) {
        fclose($socket);
        return ['success' => false, 'error' => "Recipient {$to} rejected: " . $rcptResp['resp']];
    }

    // DATA
    $dataResp = $sendCommand("DATA", [354]);
    if (!$dataResp['ok']) {
        fclose($socket);
        return ['success' => false, 'error' => "DATA rejected: " . $dataResp['resp']];
    }

    // Headers
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $encodedToName = !empty($toName) ? '=?UTF-8?B?' . base64_encode($toName) . '?= ' : '';
    $msgId = '<' . md5(uniqid((string)time(), true)) . '@specanciens.com>';

    $headers = [
        "From: {$encodedFromName} <{$from}>",
        "Reply-To: <{$from}>",
        "To: {$encodedToName}<{$to}>",
        "Subject: {$encodedSubject}",
        "Date: " . date('r'),
        "Message-ID: {$msgId}",
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "Content-Transfer-Encoding: 8bit",
        "X-Mailer: OrderGo-Mailer/1.0"
    ];

    // Build complete raw email body
    $emailBody = implode("\r\n", $headers) . "\r\n\r\n" . $htmlContent . "\r\n.\r\n";
    fputs($socket, $emailBody);

    $sendResult = $readResponse();
    $sendCode = (int)substr($sendResult, 0, 3);

    // QUIT
    $sendCommand("QUIT", [221]);
    fclose($socket);

    if ($sendCode === 250) {
        return ['success' => true, 'error' => null, 'detail' => trim($sendResult)];
    }

    return ['success' => false, 'error' => "Send failed: " . trim($sendResult)];
}

/**
 * Send password reset 6-digit verification code with high-end branded HTML email
 *
 * @param string $email Recipient email
 * @param string $name User name or empty
 * @param string $code 6-digit reset code
 * @return array ['success' => bool, 'error' => string|null]
 */
function send_password_reset_email(string $email, string $name, string $code): array {
    $displayName = !empty($name) ? htmlspecialchars($name) : 'Student';
    $subject = "OrderGo Password Recovery Code: {$code}";

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OrderGo Password Recovery</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: Arial, Helvetica, sans-serif;
      background-color: #f8f9fa;
      color: #333333;
    }
    .email-wrapper {
      width: 100%;
      background-color: #f8f9fa;
      padding: 40px 15px;
      box-sizing: border-box;
    }
    .email-container {
      max-width: 500px;
      margin: 0 auto;
      background: #ffffff;
      border: 1px solid #e9ecef;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }
    .email-header {
      background-color: #f8f9fa;
      padding: 24px;
      text-align: center;
      border-bottom: 1px solid #e9ecef;
    }
    .header-title {
      margin: 0;
      color: #ea580c;
      font-size: 22px;
      font-weight: bold;
    }
    .email-body {
      padding: 30px;
      line-height: 1.6;
      font-size: 15px;
    }
    .greeting {
      font-size: 16px;
      font-weight: bold;
      color: #1a1a1a;
      margin-top: 0;
      margin-bottom: 15px;
    }
    .code-card {
      background: #f8f9fa;
      border: 1px solid #e9ecef;
      border-radius: 6px;
      padding: 20px;
      text-align: center;
      margin: 25px 0;
    }
    .code-label {
      font-size: 13px;
      color: #666666;
      margin-bottom: 8px;
    }
    .code-digits {
      font-size: 32px;
      font-weight: bold;
      letter-spacing: 4px;
      color: #333333;
      margin: 0;
    }
    .notice-box {
      font-size: 14px;
      color: #555555;
      margin: 20px 0;
    }
    .security-note {
      font-size: 13px;
      color: #777777;
      margin-top: 20px;
      border-top: 1px solid #e9ecef;
      padding-top: 15px;
    }
    .email-footer {
      background: #f8f9fa;
      padding: 20px;
      text-align: center;
      font-size: 12px;
      color: #888888;
      border-top: 1px solid #e9ecef;
    }
  </style>
</head>
<body>
  <div class="email-wrapper">
    <div class="email-container">
      <div class="email-header">
        <h1 class="header-title">OrderGo</h1>
      </div>
      
      <div class="email-body">
        <p class="greeting">Hello, {\$displayName},</p>
        <p>We received a request to reset the password for your OrderGo account.</p>
        <p>Please use the verification code below to complete your password reset process:</p>
        
        <div class="code-card">
          <div class="code-label">Verification Code</div>
          <div class="code-digits">{\$code}</div>
        </div>
        
        <div class="notice-box">
          This code is valid for <strong>10 minutes</strong>. Please do not share this code with anyone.
        </div>
        
        <p class="security-note">
          If you did not request a password reset, you can safely ignore this email. Your account remains secure.
        </p>
      </div>
      
      <div class="email-footer">
        <div>OrderGo Campus Dining System</div>
        <div style="margin-top: 5px;">&copy; 2026 OrderGo. All rights reserved.</div>
      </div>
    </div>
  </div>
</body>
</html>
HTML;

    return send_mail($email, $subject, $html, $name);
}
