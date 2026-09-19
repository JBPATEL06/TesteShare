<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$adminId = $_SESSION['user_id'] ?? null;
$adminRole = $_SESSION['role'] ?? null;
if (!$adminId || $adminRole !== 'super_admin') {
    header("Location: " . url('user/login'));
    exit;
}
?>
<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | TestShare System Administration' : 'TestShare Master Console'; ?></title>
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
                    "primary": "#10b981",
                    "primary-container": "#059669",
                    "on-primary": "#ffffff",
                    "on-primary-container": "#a7f3d0",
                    "background": "#0c0f0d",
                    "surface": "#0c0f0d",
                    "surface-container": "#141b18",
                    "surface-container-low": "#0f1412",
                    "surface-container-lowest": "#070a09",
                    "surface-container-high": "#1a2420",
                    "surface-container-highest": "#202c27",
                    "on-surface": "#e2ebe7",
                    "on-surface-variant": "#a3b8b0",
                    "outline": "#25332e",
                    "outline-variant": "#30423c",
                    "on-background": "#e2ebe7",
                    "error": "#f87171",
                    "error-container": "#991b1b",
                    "on-error-container": "#fca5a5"
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
                    "display-lg": ["57px", {"lineHeight": "64px", "letterSpacing": "-0.025em", "fontWeight": "700"}],
                    "headline-lg-mobile": ["28px", {"lineHeight": "36px", "fontWeight": "700"}]
            }
          },
        },
      }
    </script>
    <style>
        body {
            background-color: #0c0f0d;
            color: #e2ebe7;
            font-family: 'Inter', sans-serif;
            margin: 0;
            overflow-x: hidden;
            height: 100vh;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #070a09;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #25332e;
            border-radius: 3px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #10b981;
        }
    </style>
</head>
<body class="bg-background text-on-surface flex h-screen overflow-hidden">
