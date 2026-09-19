<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../config.php';

$db = getDB();
$userId = $_SESSION['user_id'] ?? null;

// Handle Mark All As Read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    if ($userId) {
        $upd = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $upd->execute([$userId]);
        $_SESSION['notif_msg'] = "All notifications marked as read.";
        header("Location: " . url('seller/notifications'));
        exit;
    }
}

// Handle Delete Single Notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_notification') {
    $notifId = intval($_POST['notification_id'] ?? 0);
    if ($userId && $notifId) {
        $del = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $del->execute([$notifId, $userId]);
        $_SESSION['notif_msg'] = "Notification deleted.";
    }
    header("Location: " . url('seller/notifications'));
    exit;
}

// Handle Clear All Notifications Box
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_all') {
    if ($userId) {
        $del = $db->prepare("DELETE FROM notifications WHERE user_id = ?");
        $del->execute([$userId]);
        $_SESSION['notif_msg'] = "Notification box cleared completely.";
    }
    header("Location: " . url('seller/notifications'));
    exit;
}

// Fetch Notifications
$notifications = [];
if ($userId) {
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();
}

$pageTitle = 'Notifications & Alerts';
$activeNav = 'notifications';
view('partials/seller_header', get_defined_vars());
view('partials/seller_sidebar', get_defined_vars());

$flashMsg = $_SESSION['notif_msg'] ?? '';
unset($_SESSION['notif_msg']);
?>

<div class="flex-grow p-8 overflow-y-auto">
    <div class="max-w-4xl mx-auto w-full flex flex-col justify-between" style="min-height: 80vh;">
        <div class="space-y-6">
            <?php if ($flashMsg): ?>
                <div class="p-3 bg-primary-container/20 border border-primary/40 text-primary rounded-xl text-xs font-bold flex items-center justify-between">
                    <span><?php echo htmlspecialchars($flashMsg); ?></span>
                    <button type="button" onclick="this.parentElement.remove()" class="bg-transparent border-0 text-primary cursor-pointer text-base font-bold">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Header Section -->
            <div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-outline-variant pb-4">
                <div>
                    <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold mb-1">Notifications &amp; Alerts</h2>
                    <p class="text-body-md text-on-surface-variant mb-0">Manage system events, custom orders, and client requests.</p>
                </div>
                <div class="flex items-center gap-3">
                    <form action="<?php echo url('seller/notifications'); ?>" method="POST" class="m-0">
                        <input type="hidden" name="action" value="mark_read">
                        <button type="submit" class="px-3.5 py-2 border border-outline-variant hover:bg-surface-container bg-transparent text-on-surface font-bold text-xs rounded-xl transition-all cursor-pointer flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sm">mark_email_read</span>
                            <span>Mark all read</span>
                        </button>
                    </form>
                    <form action="<?php echo url('seller/notifications'); ?>" method="POST" onsubmit="return confirm('Are you sure you want to clear all notifications from your box?');" class="m-0">
                        <input type="hidden" name="action" value="clear_all">
                        <button type="submit" class="px-3.5 py-2 bg-error/10 border border-error/30 text-error hover:bg-error/20 font-bold text-xs rounded-xl transition-all cursor-pointer flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sm">delete_sweep</span>
                            <span>Clear Box</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Notifications List -->
            <div class="space-y-4">
                <?php if (empty($notifications)): ?>
                    <div class="p-8 bg-surface-container border border-outline-variant rounded-2xl text-center">
                        <span class="material-symbols-outlined text-on-surface-variant text-4xl mb-2">notifications_off</span>
                        <p class="text-on-surface-variant font-bold text-sm mb-0">No notifications at the moment.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <?php
                            $bgClass = $notif['is_read'] ? 'bg-surface border-outline-variant' : 'bg-surface-container-low border-primary shadow-lg';
                            $iconColor = $notif['is_read'] ? 'bg-surface-container text-on-surface-variant' : 'bg-primary-container text-on-primary-container';
                        ?>
                        <div class="<?php echo $bgClass; ?> p-5 rounded-2xl flex items-start gap-4 border group relative">
                            <div class="<?php echo $iconColor; ?> p-2.5 rounded-xl flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">notifications</span>
                            </div>
                            <div class="flex-grow pr-8">
                                <div class="flex justify-between items-start mb-1">
                                    <h4 class="font-bold text-on-surface text-base mb-0"><?php echo htmlspecialchars($notif['title']); ?></h4>
                                    <span class="text-xs <?php echo $notif['is_read'] ? 'text-on-surface-variant' : 'text-primary font-bold'; ?>"><?php echo date('M j, g:i a', strtotime($notif['created_at'])); ?></span>
                                </div>
                                <p class="text-on-surface-variant text-sm mb-0"><?php echo htmlspecialchars($notif['message']); ?></p>
                            </div>
                            <!-- Delete Button -->
                            <form action="<?php echo url('seller/notifications'); ?>" method="POST" onsubmit="return confirm('Delete this notification?');" class="m-0">
                                <input type="hidden" name="action" value="delete_notification">
                                <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                <button type="submit" class="p-1.5 text-on-surface-variant hover:text-error bg-transparent border-0 cursor-pointer rounded-lg hover:bg-surface-container-highest transition-colors flex items-center justify-center" title="Delete Notification">
                                    <span class="material-symbols-outlined text-base">delete</span>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer inside scrollable wrapper -->
        <footer class="w-full border-t border-outline-variant bg-background shrink-0 mt-12 pt-6">
            <div class="flex flex-col md:flex-row justify-between items-center w-full max-w-container-max-width mx-auto">
                <p class="font-body-sm text-body-sm text-on-surface-variant mb-0">© 2024 TestShare Enterprise. All rights reserved.</p>
                <div class="flex gap-6 mt-4 md:mt-0">
                    <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors text-decoration-none" href="#">Privacy Policy</a>
                    <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors text-decoration-none" href="#">Terms of Service</a>
                </div>
            </div>
        </footer>
    </div>
</div>

<?php view('partials/seller_footer'); ?>
