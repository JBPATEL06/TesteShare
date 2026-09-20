<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../config.php';

$db = getDB();
$errorMsg = '';
$successMsg = '';

$urlToken = trim($_GET['token'] ?? '');
$presetEmail = $_SESSION['reset_email'] ?? trim($_GET['email'] ?? '');
$isSent = isset($_GET['sent']) || !empty($_SESSION['reset_email']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
    $otp = trim($_POST['otp'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($newPassword) || strlen($newPassword) < 6) {
        $errorMsg = "Password must be at least 6 characters long.";
    } elseif ($newPassword !== $confirmPassword) {
        $errorMsg = "Passwords do not match. Please re-type.";
    } else {
        // Validate reset request
        $resetRecord = null;

        if ($token) {
            $stmt = $db->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at >= NOW() LIMIT 1");
            $stmt->execute([$token]);
            $resetRecord = $stmt->fetch();
        } elseif ($otp && $email) {
            $stmt = $db->prepare("SELECT * FROM password_resets WHERE email = ? AND otp = ? AND expires_at >= NOW() LIMIT 1");
            $stmt->execute([$email, $otp]);
            $resetRecord = $stmt->fetch();
        }

        if ($resetRecord) {
            $targetEmail = $resetRecord['email'];
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

            // Update user password
            $upd = $db->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
            $upd->execute([$hashedPassword, $targetEmail]);

            // Invalidate used reset request
            $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$targetEmail]);
            unset($_SESSION['reset_email']);

            $_SESSION['auth_success'] = "Password reset successfully! Please sign in with your new password.";
            header("Location: " . url('user/login'));
            exit;
        } else {
            $errorMsg = "Invalid or expired OTP / Token. Please request a new password reset.";
        }
    }
}

// Prefetch token record info if URL token is passed
if ($urlToken && !$errorMsg) {
    $chk = $db->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at >= NOW() LIMIT 1");
    $chk->execute([$urlToken]);
    $tRow = $chk->fetch();
    if ($tRow) {
        $presetEmail = $tRow['email'];
    } else {
        $errorMsg = "Invalid or expired reset link. Please request a new code.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — TestShare</title>
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
        .otp-input {
            letter-spacing: 6px;
            font-family: monospace;
            font-weight: 800;
            font-size: 18px;
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
        <span class="material-symbols-outlined fs-2">key</span>
    </div>
    
    <h3 class="fw-extrabold text-white mb-1">Set New Password</h3>
    <p class="text-secondary small mb-4">Enter the 6-digit OTP sent to your email and choose a new password.</p>

    <?php if ($isSent && !$errorMsg): ?>
        <div class="alert alert-success border-0 bg-success-subtle text-success small font-bold rounded-3 mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined fs-5">mark_email_read</span>
            <span>OTP &amp; reset link sent via Gmail SMTP! Check your inbox.</span>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger border-0 bg-danger-subtle text-danger small font-bold rounded-3 mb-4">
            <?php echo htmlspecialchars($errorMsg); ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo url('user/reset_password'); ?>" method="POST">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($urlToken); ?>">
        
        <div class="mb-3">
            <label class="form-label small fw-bold text-secondary">Email Address</label>
            <div class="position-relative">
                <span class="material-symbols-outlined position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary fs-5">mail</span>
                <input type="email" name="email" class="form-control" placeholder="name@example.com" value="<?php echo htmlspecialchars($presetEmail); ?>" required>
            </div>
        </div>

        <?php if (!$urlToken): ?>
        <div class="mb-3">
            <label class="form-label small fw-bold text-secondary">6-Digit OTP Code</label>
            <div class="position-relative">
                <span class="material-symbols-outlined position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary fs-5">pin</span>
                <input type="text" name="otp" class="form-control otp-input" placeholder="123456" maxlength="6" required>
            </div>
        </div>
        <?php endif; ?>

        <div class="mb-3">
            <label class="form-label small fw-bold text-secondary">New Password</label>
            <div class="position-relative">
                <span class="material-symbols-outlined position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary fs-5">lock</span>
                <input type="password" name="new_password" class="form-control" placeholder="••••••••" required minlength="6">
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label small fw-bold text-secondary">Confirm New Password</label>
            <div class="position-relative">
                <span class="material-symbols-outlined position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary fs-5">lock_reset</span>
                <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required minlength="6">
            </div>
        </div>

        <button type="submit" class="btn btn-amber shadow-lg">Update Password</button>
    </form>

    <div class="text-center mt-4 pt-2 border-top border-secondary-subtle">
        <a href="<?php echo url('user/forgot_password'); ?>" class="text-secondary small text-decoration-none hover-primary font-bold">
            Resend OTP Code
        </a>
    </div>
</div>

</body>
</html>
