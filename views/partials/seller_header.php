<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller_manager') {
    if (!function_exists('getDB')) {
        require_once __DIR__ . '/../../db.php';
    }
    $db = getDB();
    $defSeller = $db->query("SELECT * FROM users WHERE email = 'seller@testshare.com'")->fetch();
    if ($defSeller) {
        $_SESSION['user_id'] = $defSeller['id'];
        $_SESSION['fullname'] = $defSeller['fullname'];
        $_SESSION['email'] = $defSeller['email'];
        $_SESSION['role'] = $defSeller['role'];
    } else {
        header("Location: " . url('seller/login'));
        exit;
    }
}

if (!function_exists('getDB')) {
    require_once __DIR__ . '/../../db.php';
}
$db = getDB();
$stmtStore = $db->prepare("SELECT * FROM stores WHERE owner_id = ?");
$stmtStore->execute([$_SESSION['user_id']]);
$myStore = $stmtStore->fetch();

if (!$myStore) {
    $myStore = $db->query("SELECT * FROM stores LIMIT 1")->fetch();
}
?>
<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | TestShare Seller Panel' : 'Seller Dashboard | TestShare'; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="<?php echo asset('css/bootstrap.min.css'); ?>" rel="stylesheet">
    <!-- Tailwind CDN -->
    <script src="<?php echo asset('js/tailwind.min.js'); ?>"></script>
    <link href="<?php echo asset('css/google-fonts.css'); ?>" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="<?php echo asset('css/custom.css'); ?>?v=<?php echo time(); ?>" rel="stylesheet">
    <script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            "colors": {
                    "outline": "#a28d7a",
                    "on-secondary": "#233143",
                    "on-primary-container": "#673d00",
                    "background": "#1a120a",
                    "on-secondary-fixed-variant": "#39485a",
                    "primary-container": "#ff9f0d",
                    "on-error": "#690005",
                    "outline-variant": "#544434",
                    "error": "#ffb4ab",
                    "on-secondary-fixed": "#0d1c2d",
                    "surface-container": "#271e15",
                    "surface-container-lowest": "#140d06",
                    "surface-container-highest": "#3d3329",
                    "secondary-fixed-dim": "#b9c8de",
                    "on-primary-fixed-variant": "#673d00",
                    "inverse-on-surface": "#382f25",
                    "on-primary": "#482900",
                    "secondary-fixed": "#d4e4fa",
                    "primary-fixed-dim": "#ffb868",
                    "on-tertiary-fixed": "#001e2b",
                    "primary": "#ffc688",
                    "on-tertiary-container": "#004d66",
                    "surface-bright": "#42372d",
                    "secondary-container": "#39485a",
                    "on-surface": "#f1e0d1",
                    "inverse-primary": "#885200",
                    "error-container": "#93000a",
                    "tertiary": "#90daff",
                    "on-primary-fixed": "#2b1700",
                    "on-tertiary-fixed-variant": "#004d66",
                    "tertiary-fixed-dim": "#70d2ff",
                    "inverse-surface": "#f1e0d1",
                    "on-tertiary": "#003547",
                    "tertiary-container": "#00c3fd",
                    "tertiary-fixed": "#c0e8ff",
                    "surface-container-low": "#221a11",
                    "surface-tint": "#ffb868",
                    "on-error-container": "#ffdad6",
                    "surface-variant": "#3d3329",
                    "on-secondary-container": "#a7b6cc",
                    "surface": "#1a120a",
                    "surface-dim": "#1a120a",
                    "on-surface-variant": "#dac3ad",
                    "primary-fixed": "#ffddbb",
                    "secondary": "#b9c8de",
                    "surface-container-high": "#32281f",
                    "on-background": "#f1e0d1"
            },
            "borderRadius": {
                    "DEFAULT": "0.125rem",
                    "lg": "0.25rem",
                    "xl": "0.5rem",
                    "full": "0.75rem"
            },
            "spacing": {
                    "unit": "4px",
                    "stack-md": "16px",
                    "margin-mobile": "16px",
                    "margin-desktop": "64px",
                    "stack-sm": "8px",
                    "gutter": "24px",
                    "container-max-width": "1440px",
                    "stack-lg": "32px"
            },
            "fontFamily": {
                    "label-sm": ["JetBrains Mono"],
                    "body-lg": ["Inter"],
                    "label-md": ["JetBrains Mono"],
                    "headline-md": ["Inter"],
                    "headline-lg": ["Inter"],
                    "body-sm": ["Inter"],
                    "body-md": ["Inter"],
                    "display-lg": ["Inter"],
                    "headline-lg-mobile": ["Inter"]
            },
            "fontSize": {
                    "label-sm": ["12px", {"lineHeight": "16px", "letterSpacing": "0.05em", "fontWeight": "500"}],
                    "body-lg": ["18px", {"lineHeight": "28px", "fontWeight": "400"}],
                    "label-md": ["14px", {"lineHeight": "20px", "letterSpacing": "0.05em", "fontWeight": "500"}],
                    "headline-md": ["24px", {"lineHeight": "32px", "fontWeight": "600"}],
                    "headline-lg": ["32px", {"lineHeight": "40px", "letterSpacing": "-0.01em", "fontWeight": "700"}],
                    "body-sm": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
                    "body-md": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                    "display-lg": ["48px", {"lineHeight": "56px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                    "headline-lg-mobile": ["24px", {"lineHeight": "32px", "fontWeight": "700"}]
            }
          },
        },
      }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #1a120a; }
        ::-webkit-scrollbar-thumb { background: #3d3329; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #ff9f0d; }
        
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 24px;
        }

        @keyframes pulse-amber {
            0%, 100% { opacity: 1; border-color: rgba(255, 159, 13, 0.5); }
            50% { opacity: 0.7; border-color: rgba(255, 159, 13, 1); }
        }
        .order-flash {
            animation: pulse-amber 2s infinite;
        }
    </style>
</head>
<body class="bg-background text-on-background font-body-md selection:bg-primary-container selection:text-on-primary-container overflow-hidden">
<div class="flex h-screen w-full">
