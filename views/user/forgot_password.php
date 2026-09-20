<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../helpers/mailer.php';

$db = getDB();
$errorMsg = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = "Please enter a valid email address.";
    } else {
        // Check if email exists in users table
        $stmt = $db->prepare("SELECT id, fullname, role FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate OTP & Secure Token
            $otp = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            // Invalidate existing resets for this email
            $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

            // Save new reset request
            $ins = $db->prepare("INSERT INTO password_resets (email, token, otp, role, expires_at) VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$email, $token, $otp, $user['role'] ?? 'user', $expiresAt]);

            $resetUrl = url('user/reset_password&token=' . $token);

            // Construct HTML Email Content
            $subject = "🔑 Reset Your Password - TestShare";
            $htmlBody = "
            <div style='background-color:#121214; padding:30px; font-family:Arial, sans-serif; color:#e5e2e1;'>
                <div style='max-width:500px; margin:0 auto; background:#1c1c1e; border:1px solid #2d2d2d; border-radius:16px; padding:30px; box-shadow:0 10px 30px rgba(0,0,0,0.5);'>
                    <div style='text-align:center; margin-bottom:20px;'>
                        <h2 style='color:#ff9f0d; margin:0; font-size:24px; font-weight:800;'>TestShare</h2>
                        <span style='color:#888; font-size:12px; text-transform:uppercase; letter-spacing:1px;'>SaaS Product Account Security</span>
                    </div>
                    <hr style='border:0; border-top:1px solid #2d2d2d; margin:20px 0;'>
                    <h3 style='color:#ffffff; margin-top:0;'>Hello " . htmlspecialchars($user['fullname']) . ",</h3>
                    <p style='color:#aaa; font-size:14px; line-height:1.6;'>
                        We received a request to reset your password for your TestShare account. Use your 6-digit OTP code below or click the reset button:
                    </p>
                    
                    <div style='background:#ff9f0d1a; border:1px border-style:dashed; border-color:#ff9f0d; border-radius:12px; padding:15px; text-align:center; margin:25px 0;'>
                        <span style='color:#aaa; font-size:11px; text-transform:uppercase; display:block; margin-bottom:4px;'>Your 6-Digit OTP Code</span>
                        <span style='color:#ff9f0d; font-size:32px; font-weight:900; letter-spacing:6px; font-family:monospace;'>" . $otp . "</span>
                    </div>

                    <div style='text-align:center; margin:30px 0;'>
                        <a href='" . $resetUrl . "' style='background:#ff9f0d; color:#000000; padding:14px 28px; text-decoration:none; border-radius:10px; font-weight:800; font-size:14px; display:inline-block;'>Reset Password Now</a>
                    </div>

                    <p style='color:#777; font-size:12px; margin-bottom:0; text-align:center;'>
                        This code &amp; link will expire in <strong>15 minutes</strong>. If you did not request this reset, you can safely ignore this email.
                    </p>
                </div>
            </div>
            ";

            // Send via Gmail SMTP Mailer
            $mailResult = sendMail($email, $user['fullname'], $subject, $htmlBody);

            if ($mailResult['success']) {
                $_SESSION['reset_email'] = $email;
                header("Location: " . url('user/reset_password&sent=1'));
                exit;
            } else {
                $errorMsg = "Failed to send email: " . $mailResult['message'];
            }
        } else {
            // Friendly message even if email not found to prevent enumeration
            $_SESSION['reset_email'] = $email;
            header("Location: " . url('user/reset_password&sent=1'));
            exit;
        }
    }
}

$pageTitle = 'Forgot Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — TestShare</title>
    <link href="<?php echo asset('css/bootstrap.min.css'); ?>" rel="stylesheet">
    <link href="<?php echo asset('css/google-fonts.css'); ?>" rel="stylesheet">
<style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #0f0f11;
            color: #e5e2e1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .auth-card {
            background: #1c1c1e;
            border: 1px solid #2d2d2d;
            border-radius: 24px;
            width: 100%;
            max-width: 440px;
            padding: 40px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
        }
        .brand-icon {
            width: 56px;
            height: 56px;
            background: rgba(255, 159, 13, 0.15);
            border: 1px solid rgba(255, 159, 13, 0.3);
            color: #ff9f0d;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        .form-control {
            background-color: #121214;
            border: 1px solid #2d2d2d;
            color: #e5e2e1;
            padding: 12px 16px 12px 42px;
            border-radius: 12px;
            font-size: 14px;
        }
        .form-control:focus {
            background-color: #121214;
            border-color: #ff9f0d;
            color: #ffffff;
            box-shadow: 0 0 0 3px rgba(255, 159, 13, 0.15);
        }
        .btn-amber {
            background: #ff9f0d;
            color: #000000;
            font-weight: 800;
            border-radius: 12px;
            padding: 14px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 0;
            width: 100%;
            transition: all 0.2s ease;
        }
        .btn-amber:hover {
            background: #ffb03a;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="brand-icon">
        <span class="material-symbols-outlined fs-2">lock_reset</span>
    </div>
    
    <h3 class="fw-extrabold text-white mb-1">Forgot Password?</h3>
    <p class="text-secondary small mb-4">Enter your registered email address and we'll send a 6-digit OTP code &amp; password reset link via Gmail SMTP.</p>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger border-0 bg-danger-subtle text-danger small font-bold rounded-3 mb-4">
            <?php echo htmlspecialchars($errorMsg); ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo url('user/forgot_password'); ?>" method="POST">
        <div class="mb-4">
            <label class="form-label small fw-bold text-secondary">Email Address</label>
            <div class="position-relative">
                <span class="material-symbols-outlined position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary fs-5">mail</span>
                <input type="email" name="email" class="form-control" placeholder="name@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autofocus>
            </div>
        </div>

        <button type="submit" class="btn btn-amber shadow-lg">Send OTP &amp; Reset Link</button>
    </form>

    <div class="text-center mt-4 pt-2 border-top border-secondary-subtle">
        <a href="<?php echo url('user/login'); ?>" class="text-secondary small text-decoration-none hover-primary font-bold d-inline-flex align-items-center gap-1">
            <span class="material-symbols-outlined fs-6">arrow_back</span>
            Back to Sign In
        </a>
    </div>
</div>

</body>
</html>
