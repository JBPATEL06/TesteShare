<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!function_exists('getDB')) {
    require_once __DIR__ . '/../../db.php';
}
if (!function_exists('view')) {
    require_once __DIR__ . '/../../config.php';
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['status'] === 'Active') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['email'] = $user['email'];

                if ($user['role'] === 'customer') {
                    header("Location: " . url('user/home'));
                    exit;
                } elseif ($user['role'] === 'seller_manager') {
                    $storeStmt = $db->prepare("SELECT id FROM stores WHERE owner_id = ?");
                    $storeStmt->execute([$user['id']]);
                    $store = $storeStmt->fetch();
                    if ($store) {
                        $_SESSION['store_id'] = $store['id'];
                    }
                    header("Location: " . url('seller/dashboard1'));
                    exit;
                } elseif ($user['role'] === 'super_admin') {
                    header("Location: " . url('admin/dashboard'));
                    exit;
                }
            } else {
                $error = "Your account is " . htmlspecialchars($user['status']) . ".";
            }
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>TestShare | Premium Login</title>
    <!-- Bootstrap 5 CSS -->
    <link href="<?php echo asset('css/bootstrap.min.css'); ?>" rel="stylesheet">
    <!-- Tailwind CDN (Fallback for exact token matching) -->
    <script src="<?php echo asset('js/tailwind.min.js'); ?>"></script>
    <link href="<?php echo asset('css/google-fonts.css'); ?>" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="<?php echo asset('css/custom.css'); ?>" rel="stylesheet">
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "tertiary-fixed": "#c0e8ff",
                        "primary-container": "#ff9f0d",
                        "outline-variant": "#544434",
                        "error": "#ffb4ab",
                        "surface": "#131313",
                        "tertiary-fixed-dim": "#70d2ff",
                        "secondary-fixed-dim": "#c8c6c5",
                        "tertiary": "#90daff",
                        "inverse-surface": "#e5e2e1",
                        "surface-container-lowest": "#0e0e0e",
                        "tertiary-container": "#00c3fd",
                        "surface-container-high": "#2a2a2a",
                        "background": "#131313",
                        "primary-fixed-dim": "#ffb868",
                        "on-error": "#690005",
                        "inverse-primary": "#885200",
                        "surface-container-highest": "#353534",
                        "on-surface": "#e5e2e1",
                        "primary": "#ffc688",
                        "surface-tint": "#ffb868",
                        "on-tertiary-container": "#004d66",
                        "surface-variant": "#353534",
                        "surface-bright": "#393939",
                        "on-tertiary-fixed": "#001e2b",
                        "on-error-container": "#ffdad6",
                        "on-secondary": "#303030",
                        "outline": "#a28d7a",
                        "on-primary-container": "#673d00",
                        "on-secondary-fixed-variant": "#474746",
                        "secondary-fixed": "#e5e2e1",
                        "on-primary-fixed-variant": "#673d00",
                        "on-tertiary": "#003547",
                        "inverse-on-surface": "#313030",
                        "surface-dim": "#131313",
                        "secondary": "#c8c6c5",
                        "on-surface-variant": "#dac3ad",
                        "on-secondary-fixed": "#1b1b1c",
                        "surface-container-low": "#1c1b1b",
                        "on-tertiary-fixed-variant": "#004d66",
                        "on-primary-fixed": "#2b1700",
                        "error-container": "#93000a",
                        "primary-fixed": "#ffddbb",
                        "on-primary": "#482900",
                        "on-secondary-container": "#b7b5b4",
                        "surface-container": "#201f1f",
                        "on-background": "#e5e2e1",
                        "secondary-container": "#474746"
                    }
                }
            }
        }
    </script>
</head>
<body class="min-vh-100 d-flex flex-column align-items-center justify-content-center position-relative bg-surface text-on-surface font-body-md">
    <!-- Background Layer -->
    <div class="position-fixed inset-0 w-100 h-100 z-0">
        <div class="w-100 h-100 bg-cover bg-center parallax-bg" style="background-image: url('<?php echo asset('images/login_bg.png'); ?>'); background-size: cover; background-position: center; transform: scale(1.05);"></div>
        <div class="position-absolute inset-0 glass-overlay"></div>
    </div>

    <!-- Main Content Shell -->
    <main class="position-relative z-3 w-100 px-4 py-5 d-flex flex-column align-items-center" style="max-width: 450px;">
        <!-- Brand Header -->
        <header class="mb-5 text-center">
            <h1 class="display-4 fw-bold text-primary-container tracking-tight mb-1">TestShare</h1>
            <p class="fs-7 text-on-surface-variant text-uppercase tracking-widest mt-1">Obsidian Harvest</p>
        </header>

        <!-- Login Card -->
        <div class="w-100 bg-surface-container border border-outline-variant p-4 p-md-5 rounded-3 shadow-lg">
            <h2 class="fs-3 fw-bold text-on-surface mb-2">Portal Sign In</h2>
            <p class="fs-7 text-on-surface-variant mb-4">Single login for Customers, Merchant Partners, and System Admins.</p>
            <form class="d-flex flex-column gap-4" method="POST" action="">
                <?php if ($error): ?>
                    <div class="bg-error-container/20 border border-error-container text-error rounded-xl p-3 text-xs text-center font-bold">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <!-- Email Field -->
                <div class="d-flex flex-column gap-2">
                    <label class="fs-7 fw-semibold text-secondary-custom" for="email">Email Address</label>
                    <div class="position-relative">
                        <span class="material-symbols-outlined position-absolute top-50 start-0 translate-middle-y ms-3 text-on-surface-variant z-2">mail</span>
                        <input class="form-control bg-surface-container-lowest border-outline-variant text-on-surface py-3 ps-5 pe-3 input-focus-amber rounded-1" id="email" name="email" placeholder="name@company.com" type="email" required>
                    </div>
                </div>

                <!-- Password Field -->
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <label class="fs-7 fw-semibold text-secondary-custom" for="password">Password</label>
                        <a class="fs-8 text-primary-custom text-decoration-none hover-underline" href="<?php echo url('user/forgot_password'); ?>">Forgot Password?</a>
                    </div>
                    <div class="position-relative">
                        <span class="material-symbols-outlined position-absolute top-50 start-0 translate-middle-y ms-3 text-on-surface-variant z-2">lock</span>
                        <input class="form-control bg-surface-container-lowest border-outline-variant text-on-surface py-3 ps-5 pe-3 input-focus-amber rounded-1" id="password" name="password" placeholder="••••••••" type="password" required>
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="form-check d-flex align-items-center gap-2 mt-2">
                    <input class="form-check-input bg-surface-container-lowest border-outline-variant text-primary-container mt-0" id="remember" type="checkbox">
                    <label class="form-check-label fs-8 text-on-surface-variant" for="remember">Remember this device</label>
                </div>

                <!-- Login CTA -->
                <button class="btn btn-primary-custom w-100 py-3 mt-3 d-flex align-items-center justify-content-center gap-2 fs-6 rounded-1 shadow" type="submit">
                    <span>Login to Account</span>
                    <span class="material-symbols-outlined fs-5">arrow_forward</span>
                </button>
            </form>

            <!-- Apply as Seller Section -->
            <div class="mt-4 pt-4 border-top border-outline-variant/40 text-center">
                <p class="fs-7 text-on-surface-variant mb-2 font-bold">Want to grow your food business on TestShare?</p>
                <a href="<?php echo url('seller/register'); ?>" class="btn btn-outline-warning w-100 py-2.5 fs-7 fw-bold text-decoration-none d-flex align-items-center justify-content-center gap-2 rounded-1 border-primary-container text-primary-container hover:bg-primary-container hover:text-black transition-all">
                    <span class="material-symbols-outlined fs-5">storefront</span>
                    <span>Apply as Merchant Partner</span>
                </a>
            </div>
        </div>

        <!-- Footer Links -->
        <footer class="mt-4 text-center">
            <p class="fs-7 text-on-surface-variant mb-0">
                Don't have a customer account? 
                <a class="text-primary-custom fw-bold text-decoration-none hover-underline ms-1" href="<?php echo url('user/register'); ?>">Sign up now</a>
            </p>
        </footer>
    </main>

    <!-- Language & Support Bottom Row -->
    <div class="position-absolute bottom-0 start-0 w-100 px-4 py-4 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 z-1 opacity-75">
        <div class="d-flex gap-3 fs-8 text-secondary-custom">
            <a class="text-decoration-none text-secondary-custom hover-primary" href="#">Privacy Policy</a>
            <span class="text-outline-variant">/</span>
            <a class="text-decoration-none text-secondary-custom hover-primary" href="#">Terms of Service</a>
        </div>
        <p class="fs-8 text-on-surface-variant mb-0">© 2024 TestShare Technologies Inc.</p>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="<?php echo asset('js/bootstrap.bundle.min.js'); ?>"></script>
    <!-- Custom Main JS -->
    <script src="<?php echo asset('js/main.js'); ?>"></script>
</body>
</html>
