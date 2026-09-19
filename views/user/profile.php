<?php
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header("Location: " . url('user/login'));
    exit;
}

$db = getDB();
$successMsg = '';
$errorMsg = '';

// Handle Profile Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';

    if ($action === 'update_profile') {
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($fullname && $email && $phone) {
            try {
                $check = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $check->execute([$email, $userId]);
                if ($check->fetch()) {
                    $errorMsg = "Email address is already in use by another user.";
                } else {
                    $upd = $db->prepare("UPDATE users SET fullname = ?, email = ?, phone = ? WHERE id = ?");
                    $upd->execute([$fullname, $email, $phone, $userId]);
                    $_SESSION['fullname'] = $fullname;
                    $_SESSION['email'] = $email;
                    $successMsg = "Profile updated successfully!";
                }
            } catch (PDOException $e) {
                error_log("Profile Update Error: " . $e->getMessage());
                $errorMsg = "Unable to update profile. Please verify your details and try again.";
            }
        } else {
            $errorMsg = "Please fill in all fields.";
        }
    } elseif ($action === 'add_address') {
        $label = trim($_POST['label'] ?? '');
        $address_line1 = trim($_POST['address_line1'] ?? '');
        $address_line2 = trim($_POST['address_line2'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $zip_code = trim($_POST['zip_code'] ?? '');
        if ($label && $address_line1 && $city) {
            $ins = $db->prepare("INSERT INTO user_addresses (user_id, label, address_line1, address_line2, city, state, zip_code) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$userId, $label, $address_line1, $address_line2, $city, $state, $zip_code]);
            $successMsg = "Address added successfully!";
        } else {
            $errorMsg = "Please fill in all required address fields.";
        }
    } elseif ($action === 'delete_address') {
        $id = intval($_POST['address_id'] ?? 0);
        $del = $db->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
        $del->execute([$id, $userId]);
        $successMsg = "Address removed.";
    } elseif ($action === 'add_payment') {
        $network = trim($_POST['card_network'] ?? '');
        $last_four = trim($_POST['last_four'] ?? '');
        $exp_month = intval($_POST['expiry_month'] ?? 0);
        $exp_year = intval($_POST['expiry_year'] ?? 0);
        if ($network && strlen($last_four) === 4 && $exp_month > 0 && $exp_year > 0) {
            $ins = $db->prepare("INSERT INTO user_payment_methods (user_id, card_network, last_four, expiry_month, expiry_year) VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$userId, $network, $last_four, $exp_month, $exp_year]);
            $successMsg = "Payment method added!";
        } else {
            $errorMsg = "Invalid payment method details.";
        }
    } elseif ($action === 'delete_payment') {
        $id = intval($_POST['payment_id'] ?? 0);
        $del = $db->prepare("DELETE FROM user_payment_methods WHERE id = ? AND user_id = ?");
        $del->execute([$id, $userId]);
        $successMsg = "Payment method removed.";
    } elseif ($action === 'update_preferences') {
        $dietary = implode(',', $_POST['dietary_restrictions'] ?? []);
        $cuisines = implode(',', $_POST['favorite_cuisines'] ?? []);
        $contactless = isset($_POST['contactless_delivery']) ? 1 : 0;
        
        $chk = $db->prepare("SELECT user_id FROM user_preferences WHERE user_id = ?");
        $chk->execute([$userId]);
        if ($chk->fetch()) {
            $upd = $db->prepare("UPDATE user_preferences SET dietary_restrictions = ?, favorite_cuisines = ?, contactless_delivery = ? WHERE user_id = ?");
            $upd->execute([$dietary, $cuisines, $contactless, $userId]);
        } else {
            $ins = $db->prepare("INSERT INTO user_preferences (user_id, dietary_restrictions, favorite_cuisines, contactless_delivery) VALUES (?, ?, ?, ?)");
            $ins->execute([$userId, $dietary, $cuisines, $contactless]);
        }
        $successMsg = "Preferences saved!";
    } elseif ($action === 'upload_avatar' && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $filename = $_FILES['avatar']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $uploadDir = __DIR__ . '/../../images/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $newFileName = md5(time() . $userId) . '.' . $ext;
            $dest = $uploadDir . $newFileName;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
                $upd = $db->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                $upd->execute(['uploads/' . $newFileName, $userId]);
                $successMsg = "Profile picture updated!";
            } else {
                $errorMsg = "Failed to move uploaded file.";
            }
        } else {
            $errorMsg = "Invalid file type. Only JPG, PNG, WEBP allowed.";
        }
    }
}

// Fetch current user details
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: " . url('user/login'));
    exit;
}

$stmtAddr = $db->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id ASC");
$stmtAddr->execute([$userId]);
$addresses = $stmtAddr->fetchAll();

$stmtPay = $db->prepare("SELECT * FROM user_payment_methods WHERE user_id = ? ORDER BY is_primary DESC, id ASC");
$stmtPay->execute([$userId]);
$paymentMethods = $stmtPay->fetchAll();

$stmtPref = $db->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
$stmtPref->execute([$userId]);
$preferences = $stmtPref->fetch();

$userDietary = $preferences && $preferences['dietary_restrictions'] ? explode(',', $preferences['dietary_restrictions']) : [];
$userCuisines = $preferences && $preferences['favorite_cuisines'] ? explode(',', $preferences['favorite_cuisines']) : [];
$userContactless = $preferences && $preferences['contactless_delivery'] ? true : false;

view('partials/user_header', ['pageTitle' => 'My Profile', 'activeNav' => 'profile']);
?>

<style>
    /* Custom Scrollbar */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: #131313; }
    ::-webkit-scrollbar-thumb { background: #2D2D2D; border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: #ff9f0d; }
</style>

<!-- Main Content Canvas -->
<main class="flex-grow max-w-7xl mx-auto px-container-margin py-section-gap w-full my-4">
    <div class="mb-stack-lg my-4">
        <h1 class="font-headline-xl text-headline-xl text-on-surface mb-2 font-bold">My Profile</h1>
        <p class="font-body-md text-body-md text-on-surface-variant mb-0">Manage your account settings, addresses, and delivery preferences.</p>
    </div>
    <!-- Bento Grid Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
        <!-- Left Column: Personal Info & Security -->
        <div class="lg:col-span-4 flex flex-col gap-gutter">
            <!-- Personal Info Card -->
            <section class="bg-surface-container border border-outline-variant p-stack-lg rounded-3 mb-4 p-4">
                <div class="flex flex-col items-center mb-stack-lg relative">
                    <form action="<?php echo url('user/profile'); ?>" method="POST" enctype="multipart/form-data" id="avatarForm" class="relative group cursor-pointer w-32 h-32 rounded-3 border-2 border-primary-container overflow-hidden mb-stack-md shadow-2xl">
                        <input type="hidden" name="action" value="upload_avatar">
                        <?php 
                        $avatarUrl = $user['profile_image'] ? asset('images/' . $user['profile_image']) : asset('images/julian_rivera_avatar.png'); 
                        ?>
                        <img class="w-full h-full object-cover" alt="User profile picture" src="<?php echo htmlspecialchars($avatarUrl); ?>">
                        <div class="absolute inset-0 bg-black/50 hidden group-hover:flex items-center justify-center transition-all">
                            <span class="material-symbols-outlined text-white">photo_camera</span>
                        </div>
                        <input type="file" name="avatar" class="absolute inset-0 opacity-0 cursor-pointer" accept="image/png, image/jpeg, image/webp" onchange="document.getElementById('avatarForm').submit()">
                    </form>
                    <h2 class="font-headline-md text-headline-md font-bold mb-1"><?php echo htmlspecialchars($user['fullname']); ?></h2>
                    <span class="font-label-md text-label-md text-primary uppercase tracking-widest font-bold">Gold Member</span>
                </div>
                <form class="space-y-stack-md mt-4" method="POST" action="">
                    <?php if ($successMsg): ?>
                        <div class="bg-green-950/20 border border-green-500 text-green-400 rounded-xl p-3 text-xs text-center font-bold mb-3">
                            <?php echo htmlspecialchars($successMsg); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($errorMsg): ?>
                        <div class="bg-error-container/20 border border-error-container text-error rounded-xl p-3 text-xs text-center font-bold mb-3">
                            <?php echo htmlspecialchars($errorMsg); ?>
                        </div>
                    <?php endif; ?>
                    <div class="flex flex-col gap-1 mb-3">
                        <label class="font-label-sm text-label-sm text-on-surface-variant font-bold">Full Name</label>
                        <input class="w-full bg-background border border-outline-variant text-on-surface px-4 py-2 rounded-2 focus:border-primary-container focus:ring-1 focus:ring-primary-container outline-none transition-all font-body-md text-body-md" type="text" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                    </div>
                    <div class="flex flex-col gap-1 mb-3">
                        <label class="font-label-sm text-label-sm text-on-surface-variant font-bold">Email Address</label>
                        <input class="w-full bg-background border border-outline-variant text-on-surface px-4 py-2 rounded-2 focus:border-primary-container focus:ring-1 focus:ring-primary-container outline-none transition-all font-body-md text-body-md" type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="flex flex-col gap-1 mb-4">
                        <label class="font-label-sm text-label-sm text-on-surface-variant font-bold">Phone Number</label>
                        <input class="w-full bg-background border border-outline-variant text-on-surface px-4 py-2 rounded-2 focus:border-primary-container focus:ring-1 focus:ring-primary-container outline-none transition-all font-body-md text-body-md" type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                    </div>
                    <button class="w-full bg-primary-container text-on-primary-container font-bold py-3 rounded-2 hover:brightness-110 active:scale-[0.98] transition-all font-label-md text-label-md border-0 uppercase tracking-wider" type="submit">SAVE CHANGES</button>
                </form>
            </section>
        </div>
        <!-- Middle/Right Column: Addresses, Payments, Preferences -->
        <div class="lg:col-span-8 flex flex-col gap-gutter">
            <!-- Saved Addresses -->
            <section class="bg-surface-container border border-outline-variant p-stack-lg rounded-3 mb-4 p-4">
                <div class="flex justify-between items-center mb-stack-lg mb-4">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">location_on</span>
                        <h3 class="font-headline-md text-headline-md mb-0 font-bold">Saved Addresses</h3>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-stack-lg mb-4">
                    <?php if (empty($addresses)): ?>
                        <p class="text-on-surface-variant col-span-2">No saved addresses yet.</p>
                    <?php else: ?>
                        <?php foreach ($addresses as $addr): ?>
                            <div class="border <?php echo $addr['is_default'] ? 'border-primary' : 'border-outline-variant'; ?> bg-surface-container-high p-stack-md rounded-3 relative group p-4">
                                <div class="flex items-start justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-primary-container"><?php echo strtolower($addr['label']) === 'home' ? 'home' : (strtolower($addr['label']) === 'work' ? 'work' : 'location_on'); ?></span>
                                        <span class="font-label-md text-label-md uppercase tracking-wider font-bold"><?php echo htmlspecialchars($addr['label']); ?></span>
                                        <?php if ($addr['is_default']): ?><span class="badge bg-primary text-dark ms-2" style="font-size: 0.6rem;">Default</span><?php endif; ?>
                                    </div>
                                    <form method="POST" class="m-0" onsubmit="return confirm('Delete this address?');">
                                        <input type="hidden" name="action" value="delete_address">
                                        <input type="hidden" name="address_id" value="<?php echo $addr['id']; ?>">
                                        <button type="submit" class="material-symbols-outlined text-on-surface-variant cursor-pointer hover:text-error transition-colors bg-transparent border-0 p-0">delete</button>
                                    </form>
                                </div>
                                <p class="font-body-md text-body-md mb-1"><?php echo htmlspecialchars($addr['address_line1']); ?></p>
                                <?php if ($addr['address_line2']): ?>
                                    <p class="font-body-md text-body-md mb-1"><?php echo htmlspecialchars($addr['address_line2']); ?></p>
                                <?php endif; ?>
                                <p class="font-body-md text-body-md text-on-surface-variant mb-0"><?php echo htmlspecialchars($addr['city']); ?><?php echo $addr['state'] ? ', ' . htmlspecialchars($addr['state']) : ''; ?> <?php echo htmlspecialchars($addr['zip_code']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <h4 class="font-label-md text-label-md mt-4 mb-2">Add New Address</h4>
                <form method="POST" class="border border-outline-variant rounded-2 p-3 bg-surface-container">
                    <input type="hidden" name="action" value="add_address">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="font-label-sm text-on-surface-variant font-bold block mb-1">Label (e.g., Home, Work)</label>
                            <input class="w-full bg-background border border-outline-variant text-on-surface px-3 py-1.5 rounded-2" type="text" name="label" required>
                        </div>
                        <div>
                            <label class="font-label-sm text-on-surface-variant font-bold block mb-1">Address Line 1</label>
                            <input class="w-full bg-background border border-outline-variant text-on-surface px-3 py-1.5 rounded-2" type="text" name="address_line1" required>
                        </div>
                        <div>
                            <label class="font-label-sm text-on-surface-variant font-bold block mb-1">Address Line 2 (Optional)</label>
                            <input class="w-full bg-background border border-outline-variant text-on-surface px-3 py-1.5 rounded-2" type="text" name="address_line2">
                        </div>
                        <div>
                            <label class="font-label-sm text-on-surface-variant font-bold block mb-1">City</label>
                            <input class="w-full bg-background border border-outline-variant text-on-surface px-3 py-1.5 rounded-2" type="text" name="city" required>
                        </div>
                        <div>
                            <label class="font-label-sm text-on-surface-variant font-bold block mb-1">State / Province</label>
                            <input class="w-full bg-background border border-outline-variant text-on-surface px-3 py-1.5 rounded-2" type="text" name="state">
                        </div>
                        <div>
                            <label class="font-label-sm text-on-surface-variant font-bold block mb-1">ZIP / Postal Code</label>
                            <input class="w-full bg-background border border-outline-variant text-on-surface px-3 py-1.5 rounded-2" type="text" name="zip_code">
                        </div>
                    </div>
                    <button class="bg-primary text-dark font-bold py-1.5 px-4 rounded-2 text-sm border-0 uppercase" type="submit">Save Address</button>
                </form>
            </section>
            <!-- Payment Methods -->
            <section class="bg-surface-container border border-outline-variant p-stack-lg rounded-3 mb-4 p-4">
                <div class="flex justify-between items-center mb-stack-lg mb-4">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">credit_card</span>
                        <h3 class="font-headline-md text-headline-md mb-0 font-bold">Payment Methods</h3>
                    </div>
                </div>
                <div class="space-y-stack-md mb-4">
                    <?php if (empty($paymentMethods)): ?>
                        <p class="text-on-surface-variant">No saved payment methods.</p>
                    <?php else: ?>
                        <?php foreach ($paymentMethods as $pm): ?>
                            <div class="flex items-center justify-between p-stack-md border <?php echo $pm['is_primary'] ? 'border-primary' : 'border-outline-variant'; ?> rounded-2 bg-surface-container-high p-3 mb-3">
                                <div class="flex items-center gap-stack-md">
                                    <div class="w-12 h-8 bg-black/40 border border-outline-variant rounded-1 flex items-center justify-center mr-3" style="width: 48px; height: 32px;">
                                        <span class="font-bold text-[10px] text-white"><?php echo htmlspecialchars(strtoupper($pm['card_network'])); ?></span>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-body-md text-body-md font-bold"><?php echo htmlspecialchars($pm['card_network']); ?> ending in <?php echo htmlspecialchars($pm['last_four']); ?></span>
                                        <span class="font-label-sm text-label-sm text-on-surface-variant mb-0">Expires <?php echo str_pad($pm['expiry_month'], 2, '0', STR_PAD_LEFT) . '/' . substr($pm['expiry_year'], -2); ?></span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <?php if ($pm['is_primary']): ?>
                                        <span class="bg-primary-container text-on-primary-container px-2 py-0.5 rounded-1 font-label-sm text-label-sm font-bold">PRIMARY</span>
                                    <?php endif; ?>
                                    <form method="POST" class="m-0" onsubmit="return confirm('Delete this payment method?');">
                                        <input type="hidden" name="action" value="delete_payment">
                                        <input type="hidden" name="payment_id" value="<?php echo $pm['id']; ?>">
                                        <button type="submit" class="material-symbols-outlined text-on-surface-variant cursor-pointer hover:text-error transition-colors bg-transparent border-0 p-0">delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <h4 class="font-label-md text-label-md mt-4 mb-2">Add New Card (Mock)</h4>
                <form method="POST" class="border border-outline-variant rounded-2 p-3 bg-surface-container">
                    <input type="hidden" name="action" value="add_payment">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3">
                        <div class="md:col-span-2">
                            <label class="font-label-sm text-on-surface-variant font-bold block mb-1">Card Network</label>
                            <select class="w-full bg-background border border-outline-variant text-on-surface px-3 py-1.5 rounded-2" name="card_network" required>
                                <option value="Visa">Visa</option>
                                <option value="Mastercard">Mastercard</option>
                                <option value="Amex">American Express</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="font-label-sm text-on-surface-variant font-bold block mb-1">Last 4 Digits</label>
                            <input class="w-full bg-background border border-outline-variant text-on-surface px-3 py-1.5 rounded-2" type="text" name="last_four" maxlength="4" pattern="\d{4}" title="Four digits" required>
                        </div>
                        <div class="md:col-span-2">
                            <label class="font-label-sm text-on-surface-variant font-bold block mb-1">Expiry Month (1-12)</label>
                            <input class="w-full bg-background border border-outline-variant text-on-surface px-3 py-1.5 rounded-2" type="number" min="1" max="12" name="expiry_month" required>
                        </div>
                        <div class="md:col-span-2">
                            <label class="font-label-sm text-on-surface-variant font-bold block mb-1">Expiry Year (YYYY)</label>
                            <input class="w-full bg-background border border-outline-variant text-on-surface px-3 py-1.5 rounded-2" type="number" min="2024" max="2040" name="expiry_year" required>
                        </div>
                    </div>
                    <button class="bg-primary text-dark font-bold py-1.5 px-4 rounded-2 text-sm border-0 uppercase" type="submit">Save Card</button>
                </form>
            </section>
            <!-- Order Preferences -->
            <section class="bg-surface-container border border-outline-variant p-stack-lg rounded-3 p-4">
                <div class="flex items-center gap-2 mb-stack-lg mb-4">
                    <span class="material-symbols-outlined text-primary">restaurant</span>
                    <h3 class="font-headline-md text-headline-md mb-0 font-bold">Order Preferences</h3>
                </div>
                <form method="POST" class="space-y-stack-lg">
                    <input type="hidden" name="action" value="update_preferences">
                    <div class="mb-4">
                        <h4 class="font-label-md text-label-md text-on-surface-variant mb-stack-md uppercase tracking-wider font-bold mb-2">Dietary Restrictions</h4>
                        <div class="flex flex-wrap gap-2">
                            <?php 
                            $diets = ['Vegan', 'Gluten-Free', 'Nut-Free', 'Dairy-Free', 'Vegetarian', 'Halal'];
                            foreach($diets as $diet): 
                                $checked = in_array($diet, $userDietary);
                            ?>
                            <label class="px-4 py-2 rounded-full border <?php echo $checked ? 'border-primary-container bg-primary-container/10 text-primary-container' : 'border-outline-variant bg-surface-container-high text-on-surface-variant'; ?> font-label-sm text-label-sm cursor-pointer hover:border-primary transition-colors font-bold flex items-center gap-2">
                                <input type="checkbox" name="dietary_restrictions[]" value="<?php echo htmlspecialchars($diet); ?>" class="hidden" <?php echo $checked ? 'checked' : ''; ?> onchange="this.form.submit()">
                                <?php echo htmlspecialchars($diet); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-4">
                        <h4 class="font-label-md text-label-md text-on-surface-variant mb-stack-md uppercase tracking-wider font-bold mb-2">Favorite Cuisines</h4>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <?php 
                            $cuisines_list = ['Japanese', 'Italian', 'Mexican', 'Thai', 'Indian', 'American', 'Chinese', 'Mediterranean'];
                            foreach($cuisines_list as $cuisine): 
                                $checked = in_array($cuisine, $userCuisines);
                            ?>
                            <label class="bg-surface-container-high border <?php echo $checked ? 'border-primary text-primary' : 'border-outline-variant text-on-surface-variant'; ?> p-2 rounded-2 text-center cursor-pointer hover:border-primary transition-all">
                                <input type="checkbox" name="favorite_cuisines[]" value="<?php echo htmlspecialchars($cuisine); ?>" class="hidden" <?php echo $checked ? 'checked' : ''; ?> onchange="this.form.submit()">
                                <span class="font-label-md text-label-md font-bold"><?php echo htmlspecialchars($cuisine); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="flex items-center gap-stack-md pt-4 border-t border-outline-variant">
                        <input class="w-5 h-5 rounded bg-background border-outline-variant text-primary-container focus:ring-primary-container mr-2" id="contactless" name="contactless_delivery" type="checkbox" <?php echo $userContactless ? 'checked' : ''; ?> onchange="this.form.submit()">
                        <label class="font-body-md text-body-md mb-0" for="contactless">Always prefer contactless delivery</label>
                    </div>
                    <noscript><button type="submit" class="mt-3 bg-primary text-dark font-bold py-1.5 px-4 rounded-2 text-sm border-0 uppercase">Save Preferences</button></noscript>
                </form>
            </section>
        </div>
    </div>
</main>

<script>
    // Micro-interactions for input focus
    document.querySelectorAll('input').forEach(input => {
        input.addEventListener('focus', () => {
            const lbl = input.parentElement.querySelector('label');
            if(lbl) lbl.classList.add('text-primary');
        });
        input.addEventListener('blur', () => {
            const lbl = input.parentElement.querySelector('label');
            if(lbl) lbl.classList.remove('text-primary');
        });
    });
</script>

<?php view('partials/user_footer'); ?>
