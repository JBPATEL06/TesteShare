<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../config.php';

$db = getDB();
$sellerId = $_SESSION['user_id'] ?? null;

if (!$sellerId) {
    header("Location: " . url('user/login'));
    exit;
}

$successMsg = '';
$errorMsg = '';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_profile') {
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($fullname) || empty($email)) {
            $errorMsg = "Full name and email address are required.";
        } else {
            // Check if email taken by another user
            $stmtCheckEmail = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmtCheckEmail->execute([$email, $sellerId]);
            if ($stmtCheckEmail->fetch()) {
                $errorMsg = "The email address is already in use by another account.";
            } else {
                // Update User
                $stmtUpdateUser = $db->prepare("UPDATE users SET fullname = ?, email = ?, phone = ? WHERE id = ?");
                $stmtUpdateUser->execute([$fullname, $email, $phone, $sellerId]);

                // Update Store Config if store exists
                $stmtCheckStore = $db->prepare("SELECT id FROM stores WHERE owner_id = ?");
                $stmtCheckStore->execute([$sellerId]);
                if ($stmtCheckStore->fetch()) {
                    $gstin = trim($_POST['gstin'] ?? '');
                    $pan = trim($_POST['pan'] ?? '');
                    $fssai = trim($_POST['fssai'] ?? '');
                    $autoAccept = isset($_POST['auto_accept']) ? 'Auto Accept' : 'Manual Accept';
                    
                    $stmtUpdateStore = $db->prepare("UPDATE stores SET gstin = ?, pan = ?, fssai = ?, accept_system_status = ? WHERE owner_id = ?");
                    $stmtUpdateStore->execute([$gstin, $pan, $fssai, $autoAccept, $sellerId]);
                }
                $successMsg = "Profile information updated successfully!";
            }
        }
    } elseif ($_POST['action'] === 'add_payout') {
        $bank_name = trim($_POST['bank_name'] ?? '');
        $bank_holder_name = trim($_POST['bank_holder_name'] ?? '');
        $bank_account_number = trim($_POST['bank_account_number'] ?? '');
        $bank_ifsc = trim($_POST['bank_ifsc'] ?? '');
        
        if (empty($bank_name) || empty($bank_holder_name) || empty($bank_account_number) || empty($bank_ifsc)) {
            $errorMsg = "All bank payout fields are required.";
        } else {
            $stmtCheckStore = $db->prepare("SELECT id FROM stores WHERE owner_id = ?");
            $stmtCheckStore->execute([$sellerId]);
            if ($stmtCheckStore->fetch()) {
                $stmtUpdateBank = $db->prepare("UPDATE stores SET bank_name = ?, bank_holder_name = ?, bank_account_number = ?, bank_ifsc = ? WHERE owner_id = ?");
                $stmtUpdateBank->execute([$bank_name, $bank_holder_name, $bank_account_number, $bank_ifsc, $sellerId]);
                $successMsg = "Bank payout settlement account linked successfully!";
            } else {
                $errorMsg = "No merchant store found to link payout details.";
            }
        }
    } elseif ($_POST['action'] === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $errorMsg = "All password fields are required.";
        } elseif ($newPassword !== $confirmPassword) {
            $errorMsg = "New passwords do not match.";
        } elseif (strlen($newPassword) < 6) {
            $errorMsg = "New password must be at least 6 characters long.";
        } else {
            $stmtUserPwd = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmtUserPwd->execute([$sellerId]);
            $usrData = $stmtUserPwd->fetch();
            if ($usrData && password_verify($currentPassword, $usrData['password_hash'])) {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmtUpdPwd = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmtUpdPwd->execute([$newHash, $sellerId]);
                $successMsg = "Account password updated successfully!";
            } else {
                $errorMsg = "Current password is incorrect.";
            }
        }
    } elseif ($_POST['action'] === 'update_avatar') {
        $avatarUrl = trim($_POST['avatar_url'] ?? '');
        if (!empty($avatarUrl)) {
            $stmtAvatar = $db->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
            $stmtAvatar->execute([$avatarUrl, $sellerId]);
            $successMsg = "Profile image updated successfully!";
        } else {
            $errorMsg = "Please provide a valid image URL.";
        }
    }
}

// Fetch User Data
$stmtUser = $db->prepare("SELECT fullname, email, phone, role, profile_image, created_at FROM users WHERE id = ?");
$stmtUser->execute([$sellerId]);
$user = $stmtUser->fetch();

// Fetch Store Data
$stmtStore = $db->prepare("SELECT * FROM stores WHERE owner_id = ?");
$stmtStore->execute([$sellerId]);
$store = $stmtStore->fetch();

$fullname = $user['fullname'] ?? 'Unknown User';
$email = $user['email'] ?? '';
$phone = $user['phone'] ?? '';
$profileImage = $user['profile_image'] ?? '';
$roleLabel = ($user['role'] ?? '') === 'seller_manager' ? 'Store Owner' : 'Merchant Admin';
$memberSince = $user['created_at'] ? date('F Y', strtotime($user['created_at'])) : 'Unknown';
$initials = strtoupper(substr(trim($fullname), 0, 2));

$storeName = $store ? $store['store_name'] : 'No Assigned Outlet';
$storeIdDisplay = $store ? 'STORE-' . str_pad($store['id'], 5, '0', STR_PAD_LEFT) : 'N/A';
$gstin = $store ? $store['gstin'] : '';
$pan = $store ? $store['pan'] : '';
$fssai = $store ? $store['fssai'] : '';
$bankHolder = $store ? $store['bank_holder_name'] : '';
$bankName = $store ? $store['bank_name'] : '';
$bankAccount = $store ? $store['bank_account_number'] : '';
$bankIfsc = $store ? $store['bank_ifsc'] : '';
$acceptStatus = $store ? $store['accept_system_status'] : 'Manual Accept';
$isAutoAccept = ($acceptStatus === 'Auto Accept');

// Fetch Tier
$tierDisplay = 'Standard';
if ($store) {
    $stmtTier = $db->prepare("SELECT tier FROM billing_subscriptions WHERE store_id = ? AND status = 'Active' AND end_date > NOW() ORDER BY id DESC LIMIT 1");
    $stmtTier->execute([$store['id']]);
    $sub = $stmtTier->fetch();
    if ($sub) {
        $tierDisplay = 'Enterprise ' . $sub['tier'];
    }
}

$pageTitle = 'Seller Profile';
$activeNav = 'profile';
view('partials/seller_header', get_defined_vars());
view('partials/seller_sidebar', get_defined_vars());
?>

<div class="flex-grow p-8 overflow-y-auto">
    <!-- Content Canvas -->
    <div class="max-w-6xl mx-auto space-y-8">
        
        <!-- Header Section -->
        <div class="border-b border-outline-variant pb-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold mb-1">Administrative Profile</h2>
                <p class="text-body-md text-on-surface-variant mb-0">Manage merchant credentials, business verification, payout settlement, and account security.</p>
            </div>
            <button type="button" onclick="document.getElementById('profileForm').submit()" class="px-6 py-2.5 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-lg border-0 shadow-md cursor-pointer hover:brightness-110 active:scale-95 transition-all shrink-0">
                Save Profile
            </button>
        </div>

        <!-- Flash Messages -->
        <?php if ($successMsg): ?>
            <div class="p-4 bg-green-500/10 border border-green-500/30 rounded-xl text-green-400 text-sm font-semibold flex items-center gap-2">
                <span class="material-symbols-outlined">check_circle</span>
                <span><?php echo htmlspecialchars($successMsg); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="p-4 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm font-semibold flex items-center gap-2">
                <span class="material-symbols-outlined">error</span>
                <span><?php echo htmlspecialchars($errorMsg); ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column: Personal Card & Portal Access -->
            <div class="lg:col-span-1 space-y-8">
                <!-- Administrative Card -->
                <div class="bg-surface-container rounded-2xl border border-outline-variant p-6 shadow-xl text-center relative group">
                    <div class="relative w-32 h-32 mx-auto mb-4">
                        <div class="w-full h-full rounded-full border-4 border-outline-variant bg-surface-container-high overflow-hidden shadow-md flex items-center justify-center">
                            <?php if (!empty($profileImage)): ?>
                                <img id="profileImage" class="w-full h-full object-cover" data-alt="Merchant Profile Image" src="<?php echo htmlspecialchars($profileImage); ?>">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-primary/20 text-primary font-bold text-3xl font-mono">
                                    <?php echo htmlspecialchars($initials); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="button" onclick="openAvatarModal()" class="absolute inset-0 bg-black/60 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity rounded-full border-0 cursor-pointer" title="Update Profile Picture">
                            <span class="material-symbols-outlined text-white text-2xl">photo_camera</span>
                        </button>
                    </div>
                    
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-1"><?php echo htmlspecialchars($fullname); ?></h3>
                    <span class="text-xs font-mono px-3 py-1 bg-primary/10 text-primary border border-primary/20 rounded-full font-bold"><?php echo htmlspecialchars($roleLabel); ?></span>
                    
                    <div class="mt-6 pt-6 border-t border-outline-variant/30 space-y-4 text-left">
                        <div>
                            <span class="text-xs text-on-surface-variant font-bold block mb-1">Assigned Outlet</span>
                            <span class="text-sm text-on-surface font-semibold"><?php echo htmlspecialchars($storeName); ?></span>
                        </div>
                        <div>
                            <span class="text-xs text-on-surface-variant font-bold block mb-1">Account Tier</span>
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-base">verified</span>
                                <span class="text-sm text-on-surface font-semibold"><?php echo htmlspecialchars($tierDisplay); ?></span>
                            </div>
                        </div>
                        <div>
                            <span class="text-xs text-on-surface-variant font-bold block mb-1">Member Since</span>
                            <span class="text-sm text-on-surface font-semibold"><?php echo htmlspecialchars($memberSince); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Portal Access & Security Settings -->
                <div class="bg-surface-container rounded-2xl border border-outline-variant p-6 shadow-xl">
                    <div class="flex items-center gap-3 mb-6">
                        <span class="material-symbols-outlined text-primary">security</span>
                        <h4 class="font-bold text-on-surface text-base mb-0">Portal Access &amp; Security</h4>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="text-xs text-on-surface-variant font-bold block mb-1">Merchant Store ID</label>
                            <div class="flex gap-2">
                                <input type="text" id="merchantStoreId" readonly class="flex-grow bg-surface-container-high border border-outline-variant rounded-xl px-3 py-2 font-mono text-xs text-on-surface select-all" value="<?php echo htmlspecialchars($storeIdDisplay); ?>">
                                <button type="button" onclick="copyStoreId()" class="px-3 border border-outline-variant bg-surface-container-high hover:bg-surface-container-highest text-on-surface rounded-xl cursor-pointer" title="Copy Store ID">
                                    <span class="material-symbols-outlined text-sm">content_copy</span>
                                </button>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-outline-variant/30">
                            <button type="button" onclick="openChangePasswordModal()" class="w-full py-2.5 border border-primary text-primary font-bold text-xs uppercase tracking-wider rounded-xl bg-transparent hover:bg-primary/5 transition-all cursor-pointer flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-sm">key</span>
                                <span>Change Account Password</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Columns: Profile Form, Business Verification, Payouts, Order Acceptance -->
            <div class="lg:col-span-2 space-y-8">
                <form id="profileForm" method="POST" action="<?php echo url('seller/profile'); ?>">
                    <input type="hidden" name="action" value="save_profile">
                    
                    <!-- Personal Credentials -->
                    <div class="bg-surface-container rounded-2xl border border-outline-variant p-6 shadow-xl mb-8">
                        <h3 class="font-headline-md text-headline-md text-on-surface font-bold mb-6 border-b border-outline-variant pb-4">Personal Credentials</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs text-on-surface-variant font-bold block mb-1">Full Name</label>
                                <input name="fullname" type="text" required class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-2.5 text-sm text-on-surface focus:outline-none focus:border-primary" value="<?php echo htmlspecialchars($fullname); ?>">
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs text-on-surface-variant font-bold block mb-1">Contact Email</label>
                                    <input name="email" type="email" required class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-2.5 text-sm text-on-surface focus:outline-none focus:border-primary" value="<?php echo htmlspecialchars($email); ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-on-surface-variant font-bold block mb-1">Contact Phone</label>
                                    <input name="phone" type="tel" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-2.5 text-sm text-on-surface focus:outline-none focus:border-primary" value="<?php echo htmlspecialchars($phone); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
    
                    <!-- Business Verification Details -->
                    <div class="bg-surface-container rounded-2xl border border-outline-variant p-6 shadow-xl mb-8">
                        <div class="flex justify-between items-center mb-6 border-b border-outline-variant pb-4">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-primary">verified</span>
                                <h3 class="font-headline-md text-headline-md text-on-surface font-bold mb-0">Business Verification Details</h3>
                            </div>
                            <span class="text-[10px] font-mono px-3 py-1 bg-primary/10 text-primary border border-primary/20 rounded-full font-bold">Verified</span>
                        </div>
    
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs text-on-surface-variant font-bold block mb-1">GSTIN / Tax ID</label>
                                    <input name="gstin" type="text" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-2.5 font-mono text-sm text-on-surface focus:outline-none focus:border-primary" value="<?php echo htmlspecialchars($gstin); ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-on-surface-variant font-bold block mb-1">PAN Number</label>
                                    <input name="pan" type="text" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-2.5 font-mono text-sm text-on-surface focus:outline-none focus:border-primary" value="<?php echo htmlspecialchars($pan); ?>">
                                </div>
                            </div>
                            <div>
                                <label class="text-xs text-on-surface-variant font-bold block mb-1">FSSAI License Number (Food Businesses)</label>
                                <input name="fssai" type="text" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-2.5 font-mono text-sm text-on-surface focus:outline-none focus:border-primary" value="<?php echo htmlspecialchars($fssai); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Linked Settlement & Payout Account -->
                    <div class="bg-surface-container rounded-2xl border border-outline-variant p-6 shadow-xl mb-8">
                        <div class="flex justify-between items-center mb-6 border-b border-outline-variant pb-4">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-primary">account_balance</span>
                                <h3 class="font-headline-md text-headline-md text-on-surface font-bold mb-0">Payout Settlement Account</h3>
                            </div>
                            <button type="button" onclick="openAddPayoutModal()" class="text-primary font-bold text-xs uppercase tracking-wider hover:underline bg-transparent border-0 cursor-pointer">
                                <?php echo $bankName ? 'Edit Account' : '+ Link Account'; ?>
                            </button>
                        </div>

                        <!-- Settlement Account Details -->
                        <div id="payoutContainer">
                            <?php if ($bankName): ?>
                            <div class="border border-outline-variant bg-surface-container-high p-5 rounded-xl relative">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-primary">account_balance</span>
                                        <span class="font-mono text-sm text-on-surface font-bold"><?php echo htmlspecialchars($bankName); ?></span>
                                    </div>
                                    <span class="text-[10px] uppercase px-2.5 py-0.5 bg-green-500/10 text-green-400 border border-green-500/20 rounded-full font-bold">Active Primary Account</span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-3 pt-3 border-t border-outline-variant/30">
                                    <div>
                                        <span class="text-xs text-on-surface-variant font-bold block mb-0.5">Account Holder</span>
                                        <span class="text-sm font-semibold text-on-surface"><?php echo htmlspecialchars($bankHolder); ?></span>
                                    </div>
                                    <div>
                                        <span class="text-xs text-on-surface-variant font-bold block mb-0.5">Account Number</span>
                                        <span class="text-sm font-mono font-bold text-on-surface">••••••••<?php echo substr($bankAccount, -4); ?></span>
                                    </div>
                                    <div>
                                        <span class="text-xs text-on-surface-variant font-bold block mb-0.5">IFSC Code</span>
                                        <span class="text-sm font-mono font-bold text-on-surface"><?php echo htmlspecialchars($bankIfsc); ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="border border-outline-variant bg-surface-container-high p-6 rounded-xl text-center">
                                <span class="material-symbols-outlined text-on-surface-variant text-3xl mb-2">account_balance_wallet</span>
                                <p class="text-sm text-on-surface-variant font-bold mb-1">No Bank Account Linked</p>
                                <p class="text-xs text-on-surface-variant mb-4">Link a bank account to receive automated payout settlements for completed merchant orders.</p>
                                <button type="button" onclick="openAddPayoutModal()" class="px-4 py-2 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-lg border-0 shadow-md cursor-pointer hover:brightness-110 transition-all">
                                    Link Settlement Account
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Order Acceptance System Settings -->
                    <div class="bg-surface-container rounded-2xl border border-outline-variant p-6 shadow-xl">
                        <div class="flex justify-between items-center mb-6 border-b border-outline-variant pb-4">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-primary">settings_input_component</span>
                                <h3 class="font-headline-md text-headline-md text-on-surface font-bold mb-0">Order Acceptance System</h3>
                            </div>
                            <span class="text-[10px] font-mono px-3 py-1 bg-green-500/10 text-green-400 border border-green-500/20 rounded-full font-bold">System Online</span>
                        </div>

                        <div class="space-y-4">
                            <!-- Auto Accept Toggle -->
                            <div class="flex items-center justify-between p-4 bg-surface-container-high rounded-xl border border-outline-variant/30">
                                <div class="pr-4">
                                    <span class="text-sm font-bold text-on-surface block">Auto-Accept Incoming Orders</span>
                                    <span class="text-xs text-on-surface-variant">Automatically accept incoming customer orders and dispatch them immediately to your active store queue without manual confirmation.</span>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer select-none shrink-0">
                                    <input type="checkbox" name="auto_accept" id="autoAcceptToggle" class="sr-only peer" <?php if ($isAutoAccept) echo 'checked'; ?>>
                                    <div class="w-11 h-6 bg-outline-variant rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-on-surface after:border-outline-variant after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary peer-checked:after:bg-on-primary"></div>
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
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

<!-- ================= MODALS ================= -->

<!-- 1. Change Account Password Modal -->
<div id="credentialsModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300">
    <div class="bg-surface-container-high border border-outline-variant rounded-2xl w-full max-w-md p-6 shadow-2xl scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center border-b border-outline-variant pb-3 mb-4">
            <h3 class="font-bold text-on-surface text-base mb-0">Change Account Password</h3>
            <button type="button" onclick="closeModal('credentialsModal')" class="bg-transparent border-0 text-on-surface-variant hover:text-on-surface cursor-pointer p-0">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="<?php echo url('seller/profile'); ?>">
            <input type="hidden" name="action" value="change_password">
            <div class="space-y-4">
                <div>
                    <label class="text-xs text-on-surface-variant font-bold block mb-1">Current Password</label>
                    <input type="password" name="current_password" required class="w-full bg-surface border border-outline-variant rounded-xl px-3 py-2 text-sm text-on-surface focus:outline-none focus:border-primary">
                </div>
                <div>
                    <label class="text-xs text-on-surface-variant font-bold block mb-1">New Password</label>
                    <input type="password" name="new_password" required minlength="6" class="w-full bg-surface border border-outline-variant rounded-xl px-3 py-2 text-sm text-on-surface focus:outline-none focus:border-primary">
                </div>
                <div>
                    <label class="text-xs text-on-surface-variant font-bold block mb-1">Confirm New Password</label>
                    <input type="password" name="confirm_password" required minlength="6" class="w-full bg-surface border border-outline-variant rounded-xl px-3 py-2 text-sm text-on-surface focus:outline-none focus:border-primary">
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeModal('credentialsModal')" class="px-4 py-2 border border-outline-variant text-on-surface bg-transparent font-bold text-xs uppercase tracking-wider rounded-lg hover:bg-surface-container-highest cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-lg border-0 shadow-md cursor-pointer hover:brightness-110 active:scale-95 transition-all">
                    Update Password
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 2. Add/Edit Settlement Account Modal -->
<div id="payoutModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300">
    <div class="bg-surface-container-high border border-outline-variant rounded-2xl w-full max-w-md p-6 shadow-2xl scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center border-b border-outline-variant pb-3 mb-4">
            <h3 class="font-bold text-on-surface text-base mb-0">Link Settlement Bank Account</h3>
            <button type="button" onclick="closeModal('payoutModal')" class="bg-transparent border-0 text-on-surface-variant hover:text-on-surface cursor-pointer p-0">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="<?php echo url('seller/profile'); ?>">
            <input type="hidden" name="action" value="add_payout">
            <div class="space-y-4">
                <div>
                    <label class="text-xs text-on-surface-variant font-bold block mb-1">Bank Name</label>
                    <input type="text" name="bank_name" value="<?php echo htmlspecialchars($bankName); ?>" class="w-full bg-surface border border-outline-variant rounded-xl px-3 py-2 text-sm text-on-surface focus:outline-none focus:border-primary" placeholder="e.g. HDFC Bank, ICICI Bank" required>
                </div>
                <div>
                    <label class="text-xs text-on-surface-variant font-bold block mb-1">Account Holder Name</label>
                    <input type="text" name="bank_holder_name" value="<?php echo htmlspecialchars($bankHolder); ?>" class="w-full bg-surface border border-outline-variant rounded-xl px-3 py-2 text-sm text-on-surface focus:outline-none focus:border-primary" placeholder="e.g. Test Share Store" required>
                </div>
                <div>
                    <label class="text-xs text-on-surface-variant font-bold block mb-1">Account Number</label>
                    <input type="text" name="bank_account_number" value="<?php echo htmlspecialchars($bankAccount); ?>" class="w-full bg-surface border border-outline-variant rounded-xl px-3 py-2 text-sm text-on-surface font-mono focus:outline-none focus:border-primary" placeholder="e.g. 50100248839211" required>
                </div>
                <div>
                    <label class="text-xs text-on-surface-variant font-bold block mb-1">IFSC Code</label>
                    <input type="text" name="bank_ifsc" value="<?php echo htmlspecialchars($bankIfsc); ?>" class="w-full bg-surface border border-outline-variant rounded-xl px-3 py-2 text-sm text-on-surface font-mono focus:outline-none focus:border-primary" placeholder="e.g. HDFC0001234" required>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeModal('payoutModal')" class="px-4 py-2 border border-outline-variant text-on-surface bg-transparent font-bold text-xs uppercase tracking-wider rounded-lg hover:bg-surface-container-highest cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-lg border-0 shadow-md cursor-pointer hover:brightness-110 active:scale-95 transition-all">
                    Link Settlement Account
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 3. Update Avatar Modal -->
<div id="avatarModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300">
    <div class="bg-surface-container-high border border-outline-variant rounded-2xl w-full max-w-md p-6 shadow-2xl scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center border-b border-outline-variant pb-3 mb-4">
            <h3 class="font-bold text-on-surface text-base mb-0">Update Profile Picture</h3>
            <button type="button" onclick="closeModal('avatarModal')" class="bg-transparent border-0 text-on-surface-variant hover:text-on-surface cursor-pointer p-0">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="<?php echo url('seller/profile'); ?>">
            <input type="hidden" name="action" value="update_avatar">
            <div class="space-y-4">
                <div>
                    <label class="text-xs text-on-surface-variant font-bold block mb-1">Image URL</label>
                    <input type="url" name="avatar_url" value="<?php echo htmlspecialchars($profileImage); ?>" class="w-full bg-surface border border-outline-variant rounded-xl px-3 py-2 text-sm text-on-surface focus:outline-none focus:border-primary" placeholder="https://example.com/profile.jpg" required>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeModal('avatarModal')" class="px-4 py-2 border border-outline-variant text-on-surface bg-transparent font-bold text-xs uppercase tracking-wider rounded-lg hover:bg-surface-container-highest cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-lg border-0 shadow-md cursor-pointer hover:brightness-110 active:scale-95 transition-all">
                    Save Image
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Copy Store ID
    function copyStoreId() {
        const val = document.getElementById('merchantStoreId').value;
        navigator.clipboard.writeText(val).then(() => {
            alert('Store ID copied to clipboard!');
        }).catch(err => {
            alert('Failed to copy Store ID: ' + err);
        });
    }

    // Modal helpers
    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modal.querySelector('.scale-95')?.classList.remove('scale-95');
            }, 10);
        }
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('opacity-0');
            modal.querySelector('.max-w-md')?.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }
    }

    function openChangePasswordModal() { openModal('credentialsModal'); }
    function openAddPayoutModal() { openModal('payoutModal'); }
    function openAvatarModal() { openModal('avatarModal'); }
</script>

<?php view('partials/seller_footer'); ?>
