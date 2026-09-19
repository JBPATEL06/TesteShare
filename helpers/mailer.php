<?php

if (!function_exists('getEnvVar')) {
    function getEnvVar($key, $default = '') {
        static $envVars = null;
        if ($envVars === null) {
            $envVars = [];
            $envFile = __DIR__ . '/../.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || strpos($line, '#') === 0) continue;
                    if (strpos($line, '=') !== false) {
                        list($k, $v) = explode('=', $line, 2);
                        $envVars[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
                    }
                }
            }
        }
        return $envVars[$key] ?? getenv($key) ?: $default;
    }
}

/**
 * Native Socket-based SMTP Mailer for Gmail (Port 465 SSL or 587 TLS)
 * Zero external library dependencies required.
 */
function sendMail($toEmail, $toName, $subject, $htmlContent) {
    $smtpHost = getEnvVar('SMTP_HOST', 'smtp.gmail.com');
    $smtpPort = intval(getEnvVar('SMTP_PORT', 465));
    $smtpUser = getEnvVar('SMTP_USER', '');
    $smtpPass = getEnvVar('SMTP_PASS', '');
    $fromEmail = getEnvVar('SMTP_FROM_EMAIL', $smtpUser);
    $fromName = getEnvVar('SMTP_FROM_NAME', 'TestShare');

    // Clean app password of spaces if present
    $smtpPass = str_replace(' ', '', $smtpPass);

    if (empty($smtpUser) || empty($smtpPass)) {
        return ['success' => false, 'message' => 'SMTP credentials not configured in .env file.'];
    }

    $socketHost = ($smtpPort === 465) ? 'ssl://' . $smtpHost : $smtpHost;
    
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    $socket = @stream_socket_client($socketHost . ':' . $smtpPort, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        return ['success' => false, 'message' => "SMTP Socket Connection Failed: $errstr ($errno)"];
    }

    $readResponse = function($expectedCode) use ($socket) {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        $code = intval(substr($response, 0, 3));
        return ['code' => $code, 'raw' => $response, 'ok' => ($code === $expectedCode)];
    };

    // Read initial 220 banner
    $res = $readResponse(220);
    if (!$res['ok']) { fclose($socket); return ['success' => false, 'message' => 'SMTP Banner Error: ' . $res['raw']]; }

    // EHLO
    fputs($socket, "EHLO " . gethostname() . "\r\n");
    $readResponse(250);

    // AUTH LOGIN
    fputs($socket, "AUTH LOGIN\r\n");
    $res = $readResponse(334);
    if (!$res['ok']) { fclose($socket); return ['success' => false, 'message' => 'AUTH LOGIN error: ' . $res['raw']]; }

    // Send Base64 Username
    fputs($socket, base64_encode($smtpUser) . "\r\n");
    $res = $readResponse(334);
    if (!$res['ok']) { fclose($socket); return ['success' => false, 'message' => 'SMTP Username rejected: ' . $res['raw']]; }

    // Send Base64 Password
    fputs($socket, base64_encode($smtpPass) . "\r\n");
    $res = $readResponse(235);
    if (!$res['ok']) { fclose($socket); return ['success' => false, 'message' => 'SMTP Authentication failed. Check App Password. ' . $res['raw']]; }

    // MAIL FROM
    fputs($socket, "MAIL FROM: <{$fromEmail}>\r\n");
    $res = $readResponse(250);
    if (!$res['ok']) { fclose($socket); return ['success' => false, 'message' => 'MAIL FROM Error: ' . $res['raw']]; }

    // RCPT TO
    fputs($socket, "RCPT TO: <{$toEmail}>\r\n");
    $res = $readResponse(250);
    if (!$res['ok']) { fclose($socket); return ['success' => false, 'message' => 'RCPT TO Error: ' . $res['raw']]; }

    // DATA
    fputs($socket, "DATA\r\n");
    $res = $readResponse(354);
    if (!$res['ok']) { fclose($socket); return ['success' => false, 'message' => 'DATA Error: ' . $res['raw']]; }

    // Headers & Body
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "To: {$toName} <{$toEmail}>\r\n";
    $headers .= "Subject: {$subject}\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "Message-ID: <" . time() . '.' . md5($toEmail) . "@{$smtpHost}>\r\n";

    $message = $headers . "\r\n" . $htmlContent . "\r\n.\r\n";

    fputs($socket, $message);
    $res = $readResponse(250);
    if (!$res['ok']) { fclose($socket); return ['success' => false, 'message' => 'Message Delivery Error: ' . $res['raw']]; }

    // QUIT
    fputs($socket, "QUIT\r\n");
    fclose($socket);

    return ['success' => true, 'message' => 'Email sent successfully!'];
}
