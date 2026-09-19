<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - TestShare' : 'TestShare - Explore Dishes'; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="<?php echo asset('css/bootstrap.min.css'); ?>" rel="stylesheet">
    <!-- Tailwind CDN (Fallback for exact token matching) -->
    <script src="<?php echo asset('js/tailwind.min.js'); ?>"></script>
    <link href="<?php echo asset('css/google-fonts.css'); ?>" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="<?php echo asset('css/custom.css'); ?>?v=<?php echo time(); ?>" rel="stylesheet">
    <!-- Tailwind Theme Configuration -->
    <script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            "colors": {
                    "on-secondary-fixed-variant": "#474746",
                    "on-primary-fixed": "#2b1700",
                    "outline": "#a28d7a",
                    "on-surface": "#e5e2e1",
                    "on-error": "#690005",
                    "primary": "#ffc688",
                    "surface": "#131313",
                    "on-primary-fixed-variant": "#673d00",
                    "inverse-primary": "#885200",
                    "on-surface-variant": "#dac3ad",
                    "on-tertiary-fixed-variant": "#004d66",
                    "on-tertiary-fixed": "#001e2b",
                    "on-tertiary": "#003547",
                    "tertiary": "#90daff",
                    "on-secondary": "#303030",
                    "surface-variant": "#353534",
                    "surface-dim": "#131313",
                    "surface-container": "#201f1f",
                    "on-tertiary-container": "#004d66",
                    "surface-container-lowest": "#0e0e0e",
                    "secondary-container": "#474746",
                    "error-container": "#93000a",
                    "error": "#ffb4ab",
                    "tertiary-container": "#00c3fd",
                    "on-secondary-fixed": "#1b1b1c",
                    "background": "#131313",
                    "primary-container": "#ff9f0d",
                    "inverse-on-surface": "#313030",
                    "secondary-fixed-dim": "#c8c6c5",
                    "tertiary-fixed-dim": "#70d2ff",
                    "surface-container-low": "#1c1b1b",
                    "on-secondary-container": "#b7b5b4",
                    "on-background": "#e5e2e1",
                    "secondary": "#c8c6c5",
                    "outline-variant": "#544434",
                    "surface-tint": "#ffb868",
                    "surface-bright": "#393939",
                    "tertiary-fixed": "#c0e8ff",
                    "inverse-surface": "#e5e2e1",
                    "surface-container-high": "#2a2a2a",
                    "primary-fixed-dim": "#ffb868",
                    "surface-container-highest": "#353534",
                    "on-error-container": "#ffdad6",
                    "primary-fixed": "#ffddbb",
                    "secondary-fixed": "#e5e2e1",
                    "on-primary-container": "#673d00",
                    "on-primary": "#482900"
            },
            "borderRadius": {
                    "DEFAULT": "0.125rem",
                    "lg": "0.25rem",
                    "xl": "0.5rem",
                    "full": "0.75rem"
            },
            "spacing": {
                    "stack-sm": "4px",
                    "stack-md": "12px",
                    "gutter": "16px",
                    "container-margin": "20px",
                    "section-gap": "48px",
                    "base": "8px",
                    "stack-lg": "24px"
            },
            "fontFamily": {
                    "headline-lg": ["Inter"],
                    "body-md": ["Inter"],
                    "label-sm": ["Inter"],
                    "label-md": ["Inter"],
                    "body-lg": ["Inter"],
                    "headline-md": ["Inter"],
                    "headline-xl": ["Inter"],
                    "headline-lg-mobile": ["Inter"]
            }
          }
        }
      }
    </script>
</head>
<body class="bg-surface text-on-surface font-body-md">
    <!-- TopNavBar -->
    <header class="bg-surface sticky-top border-bottom border-outline-variant z-3">
        <div class="container-xl d-flex justify-content-between align-items-center py-3 px-4">
            <div class="d-flex align-items-center gap-3">
                <a href="<?php echo url('user/home'); ?>" class="text-decoration-none"><span class="fs-4 fw-bold text-primary-custom">TestShare</span></a>
            </div>
            <div class="d-none d-md-flex align-items-center gap-4">
                <a class="<?php echo (!isset($activeNav) || $activeNav === 'explore') ? 'text-primary-custom fw-bold border-bottom border-primary border-2 pb-1' : 'text-on-surface-variant hover-primary transition'; ?> text-decoration-none" href="<?php echo url('user/home'); ?>">Explore</a>
                <a class="<?php echo (isset($activeNav) && $activeNav === 'orders') ? 'text-primary-custom fw-bold border-bottom border-primary border-2 pb-1' : 'text-on-surface-variant hover-primary transition'; ?> text-decoration-none" href="<?php echo url('user/orders'); ?>">Orders</a>
                <a class="<?php echo (isset($activeNav) && $activeNav === 'help') ? 'text-primary-custom fw-bold border-bottom border-primary border-2 pb-1' : 'text-on-surface-variant hover-primary transition'; ?> text-decoration-none" href="<?php echo url('user/help'); ?>">Help</a>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button onclick="window.location.href='<?php echo url('user/location'); ?>'" class="btn btn-link text-on-surface-variant p-0 hover-primary d-none d-sm-inline-block">
                    <span class="material-symbols-outlined">location_on</span>
                </button>
                <?php if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'customer'): 
                    $headerDb = getDB();
                    $stmtHead = $headerDb->prepare("SELECT profile_image FROM users WHERE id = ?");
                    $stmtHead->execute([$_SESSION['user_id']]);
                    $headerUser = $stmtHead->fetch();
                    $headerAvatarUrl = ($headerUser && $headerUser['profile_image']) ? asset('images/' . $headerUser['profile_image']) : asset('images/user_header_avatar.png');
                    
                    $stmtNotif = $headerDb->prepare("SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND is_read = 0");
                    $stmtNotif->execute([$_SESSION['user_id']]);
                    $unreadCount = $stmtNotif->fetchColumn();
                ?>
                    <div class="dropdown">
                        <button class="btn btn-link text-on-surface-variant p-0 hover-primary position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                            <span class="material-symbols-outlined">notifications</span>
                            <?php if ($unreadCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle">
                                <span class="visually-hidden">New alerts</span>
                            </span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-outline-variant bg-surface" style="width: 320px;">
                            <li><h6 class="dropdown-header text-on-surface fw-bold border-bottom border-outline-variant pb-2">Notifications</h6></li>
                            <?php
                            $stmtNotifs = $headerDb->prepare("SELECT * FROM user_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
                            $stmtNotifs->execute([$_SESSION['user_id']]);
                            $notifs = $stmtNotifs->fetchAll();
                            if ($notifs):
                                foreach($notifs as $n):
                            ?>
                            <li class="position-relative border-bottom border-outline-variant">
                                <div class="dropdown-item py-2 pe-4 <?php echo $n['is_read'] ? 'opacity-75' : 'bg-surface-container-high fw-bold'; ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small class="text-primary fw-bold"><?php echo htmlspecialchars($n['type']); ?></small>
                                        <div class="d-flex align-items-center gap-1">
                                            <small class="text-muted" style="font-size: 10px;"><?php echo date('M d', strtotime($n['created_at'])); ?></small>
                                            <a href="<?php echo url('user/home&delete_user_notif=' . $n['id']); ?>" onclick="event.stopPropagation(); return confirm('Delete this notification?');" class="text-muted hover-danger text-decoration-none ms-1" title="Delete notification">
                                                <span class="material-symbols-outlined" style="font-size: 14px;">delete</span>
                                            </a>
                                        </div>
                                    </div>
                                    <div class="text-on-surface text-wrap" style="font-size: 12px;"><?php echo htmlspecialchars($n['message']); ?></div>
                                </div>
                            </li>
                            <?php endforeach; else: ?>
                            <li><span class="dropdown-item-text text-muted text-center py-3">No new notifications</span></li>
                            <?php endif; ?>
                            <li class="border-top border-outline-variant pt-1">
                                <div class="d-flex justify-content-between px-3 py-1 text-xs">
                                    <a class="text-primary fw-bold text-decoration-none" style="font-size: 11px;" href="<?php echo url('user/home&mark_notifs_read=1'); ?>">Mark all read</a>
                                    <a class="text-danger fw-bold text-decoration-none" style="font-size: 11px;" href="<?php echo url('user/home&clear_user_notifs=1'); ?>" onclick="return confirm('Clear all notifications?');">Clear Box</a>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <a href="<?php echo url('user/profile'); ?>" class="w-8 h-8 rounded-circle border border-outline-variant overflow-hidden d-block" style="width: 32px; height: 32px;" title="Profile">
                        <img class="w-100 h-100 object-fit-cover" alt="User Profile" src="<?php echo htmlspecialchars($headerAvatarUrl); ?>">
                    </a>
                    <button onclick="window.location.href='<?php echo url('user/logout'); ?>'" class="btn btn-link text-on-surface-variant p-0 hover-primary border-0 bg-transparent" title="Logout">
                        <span class="material-symbols-outlined">logout</span>
                    </button>
                <?php else: ?>
                    <a href="<?php echo url('user/login'); ?>" class="btn btn-sm btn-outline-light fw-semibold rounded-2 px-3 py-1 text-decoration-none" style="font-size: 11px;">
                        Login / Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>
