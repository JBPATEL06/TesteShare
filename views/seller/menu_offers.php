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
$db = getDB();

$sellerId = $_SESSION['user_id'] ?? null;
$storeId = 0;
$myStore = null;

if ($sellerId) {
    $stmtStore = $db->prepare("SELECT * FROM stores WHERE owner_id = ?");
    $stmtStore->execute([$sellerId]);
    $myStore = $stmtStore->fetch();
    if ($myStore) {
        $storeId = $myStore['id'];
    }
}

if (!$sellerId || !$storeId) {
    header("Location: " . url('seller/login'));
    exit;
}

$successMsg = '';
$errorMsg = '';

// Handle status toggling or deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_status') {
        $offerId = intval($_POST['offer_id'] ?? 0);
        $newStatus = trim($_POST['new_status'] ?? 'Active');
        if ($offerId && $storeId) {
            $upd = $db->prepare("UPDATE promotional_offers SET status = ? WHERE id = ? AND store_id = ?");
            $upd->execute([$newStatus, $offerId, $storeId]);
            $successMsg = "Offer status updated to '$newStatus' successfully!";
        }
    } elseif ($_POST['action'] === 'delete_offer') {
        $offerId = intval($_POST['offer_id'] ?? 0);
        if ($offerId && $storeId) {
            $del = $db->prepare("DELETE FROM promotional_offers WHERE id = ? AND store_id = ?");
            $del->execute([$offerId, $storeId]);
            $successMsg = "Offer removed successfully!";
        }
    }
}

// Fetch ALL Offers (Both Active & Unactive / Expired / Draft)
$offers = [];
if ($storeId) {
    $offStmt = $db->prepare("
        SELECT *, 
               (status = 'Active' AND end_date >= NOW()) as is_currently_active,
               (end_date < NOW()) as is_expired
        FROM promotional_offers 
        WHERE store_id = ? 
        ORDER BY is_currently_active DESC, created_at DESC
    ");
    $offStmt->execute([$storeId]);
    $offers = $offStmt->fetchAll();
}

$activeCount = 0;
$unactiveCount = 0;
foreach ($offers as $off) {
    if ($off['is_currently_active']) {
        $activeCount++;
    } else {
        $unactiveCount++;
    }
}

$pageTitle = 'Promotional Offers';
$activeNav = 'menu_offers';
view('partials/seller_header', get_defined_vars());
view('partials/seller_sidebar', get_defined_vars());
?>

<div class="flex-grow p-8 overflow-y-auto">
    <div class="max-w-6xl mx-auto space-y-8">
        
        <!-- Header Section -->
        <div class="border-b border-outline-variant pb-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold mb-1">Promotional Offers &amp; Coupons</h2>
                <p class="text-body-md text-on-surface-variant mb-0">View, manage, activate, or deactivate all active and inactive promotional coupons for your outlet.</p>
            </div>
            <a href="<?php echo url('seller/create_offer'); ?>" class="px-6 py-2.5 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-lg border-0 shadow-md hover:brightness-110 active:scale-95 transition-all text-decoration-none flex items-center gap-2 shrink-0">
                <span class="material-symbols-outlined text-base">add</span>
                <span>Create New Offer</span>
            </a>
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

        <!-- Filter Tabs -->
        <div class="flex gap-3 border-b border-outline-variant pb-4">
            <button type="button" onclick="filterOffers('all')" id="tab-all" class="offer-tab px-4 py-2 rounded-xl text-xs font-bold transition-all bg-primary-container text-on-primary-container border-0 cursor-pointer">
                All Offers (<?php echo count($offers); ?>)
            </button>
            <button type="button" onclick="filterOffers('active')" id="tab-active" class="offer-tab px-4 py-2 rounded-xl text-xs font-bold transition-all bg-surface-container-high text-on-surface-variant hover:text-on-surface border border-outline-variant/30 cursor-pointer">
                Active (<?php echo $activeCount; ?>)
            </button>
            <button type="button" onclick="filterOffers('unactive')" id="tab-unactive" class="offer-tab px-4 py-2 rounded-xl text-xs font-bold transition-all bg-surface-container-high text-on-surface-variant hover:text-on-surface border border-outline-variant/30 cursor-pointer">
                Inactive / Expired (<?php echo $unactiveCount; ?>)
            </button>
        </div>

        <!-- Offers Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="offersGrid">
            <?php if (empty($offers)): ?>
                <div class="col-span-full p-12 text-center text-on-surface-variant bg-surface-container rounded-2xl border border-outline-variant shadow-xl">
                    <span class="material-symbols-outlined text-5xl text-on-surface-variant/40 mb-3">local_offer</span>
                    <h3 class="font-bold text-lg text-on-surface mb-1">No Offers Created Yet</h3>
                    <p class="text-xs text-on-surface-variant mb-6">Create promotional discount coupons to boost customer orders and storefront visibility.</p>
                    <a href="<?php echo url('seller/create_offer'); ?>" class="px-6 py-2.5 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-lg border-0 shadow-md hover:brightness-110 transition-all text-decoration-none inline-flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">add</span>
                        <span>Create First Offer</span>
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($offers as $offer):
                    $isActive = (bool)$offer['is_currently_active'];
                    $isExpired = (bool)$offer['is_expired'];
                    $statusClass = $isActive ? 'active-offer' : 'unactive-offer';
                ?>
                    <div class="offer-card <?php echo $statusClass; ?> bg-surface-container border border-outline-variant rounded-2xl p-6 relative overflow-hidden flex flex-col justify-between shadow-xl transition-all hover:border-primary/50">
                        <div>
                            <!-- Header & Status Badge -->
                            <div class="flex justify-between items-start mb-3 gap-2">
                                <div>
                                    <h3 class="font-bold text-xl text-on-surface mb-1"><?php echo htmlspecialchars($offer['offer_title']); ?></h3>
                                    <span class="text-xs text-primary font-mono font-bold"><?php echo number_format($offer['discount_percentage'], 0); ?>% OFF</span>
                                </div>
                                <?php if ($isActive): ?>
                                    <span class="bg-green-500/10 text-green-400 border border-green-500/20 text-[10px] uppercase font-mono px-3 py-1 rounded-full font-bold">Active</span>
                                <?php elseif ($isExpired): ?>
                                    <span class="bg-amber-500/10 text-amber-400 border border-amber-500/20 text-[10px] uppercase font-mono px-3 py-1 rounded-full font-bold">Expired</span>
                                <?php else: ?>
                                    <span class="bg-red-500/10 text-red-400 border border-red-500/20 text-[10px] uppercase font-mono px-3 py-1 rounded-full font-bold">Inactive</span>
                                <?php endif; ?>
                            </div>

                            <!-- Description -->
                            <p class="text-xs text-on-surface-variant mb-4">
                                <?php echo htmlspecialchars($offer['offer_description'] ?: 'Special promotional coupon offer.'); ?>
                            </p>

                            <!-- Code Box -->
                            <div class="flex items-center justify-between bg-surface-container-high p-3 rounded-xl border border-outline-variant/40 mb-4">
                                <div class="flex flex-col">
                                    <span class="text-[10px] text-on-surface-variant font-bold uppercase">Coupon Code</span>
                                    <span class="font-mono font-bold text-primary text-base" id="code-<?php echo $offer['id']; ?>"><?php echo htmlspecialchars($offer['coupon_code']); ?></span>
                                </div>
                                <button type="button" onclick="copyCode('code-<?php echo $offer['id']; ?>', this)" class="bg-surface-container-highest hover:bg-primary-container hover:text-on-primary-container text-on-surface font-bold px-3 py-1.5 rounded-lg text-xs transition-all border border-outline-variant cursor-pointer">
                                    Copy Code
                                </button>
                            </div>

                            <!-- Offer Meta -->
                            <div class="grid grid-cols-2 gap-2 text-xs text-on-surface-variant border-t border-outline-variant/30 pt-3 mb-4">
                                <div>
                                    <span class="block text-[10px] font-bold uppercase">Min Order</span>
                                    <span class="font-mono font-bold text-on-surface">₹<?php echo number_format($offer['min_order_value'], 2); ?></span>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-bold uppercase">Valid Until</span>
                                    <span class="font-mono font-bold text-on-surface"><?php echo date('M d, Y', strtotime($offer['end_date'])); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Actions Bar -->
                        <div class="flex items-center justify-between pt-3 border-t border-outline-variant/30 gap-3">
                            <!-- Toggle Status Form -->
                            <form method="POST" action="<?php echo url('seller/menu_offers'); ?>" class="inline-block">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="offer_id" value="<?php echo $offer['id']; ?>">
                                <?php if ($isActive): ?>
                                    <input type="hidden" name="new_status" value="Draft">
                                    <button type="submit" class="px-3 py-1.5 bg-amber-500/10 text-amber-400 border border-amber-500/30 hover:bg-amber-500/20 text-xs font-bold rounded-lg cursor-pointer transition-all">
                                        Deactivate Offer
                                    </button>
                                <?php else: ?>
                                    <input type="hidden" name="new_status" value="Active">
                                    <button type="submit" class="px-3 py-1.5 bg-green-500/10 text-green-400 border border-green-500/30 hover:bg-green-500/20 text-xs font-bold rounded-lg cursor-pointer transition-all">
                                        Activate Offer
                                    </button>
                                <?php endif; ?>
                            </form>

                            <!-- Delete Form -->
                            <form method="POST" action="<?php echo url('seller/menu_offers'); ?>" onsubmit="return confirm('Are you sure you want to delete this offer?');" class="inline-block">
                                <input type="hidden" name="action" value="delete_offer">
                                <input type="hidden" name="offer_id" value="<?php echo $offer['id']; ?>">
                                <button type="submit" class="p-1.5 text-on-surface-variant hover:text-red-400 bg-transparent border-0 cursor-pointer transition-colors" title="Delete Offer">
                                    <span class="material-symbols-outlined text-sm">delete</span>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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

<script>
    function filterOffers(type) {
        const tabs = document.querySelectorAll('.offer-tab');
        tabs.forEach(tab => {
            tab.className = 'offer-tab px-4 py-2 rounded-xl text-xs font-bold transition-all bg-surface-container-high text-on-surface-variant hover:text-on-surface border border-outline-variant/30 cursor-pointer';
        });

        const activeTab = document.getElementById('tab-' + type);
        if (activeTab) {
            activeTab.className = 'offer-tab px-4 py-2 rounded-xl text-xs font-bold transition-all bg-primary-container text-on-primary-container border-0 cursor-pointer';
        }

        const cards = document.querySelectorAll('.offer-card');
        cards.forEach(card => {
            if (type === 'all') {
                card.style.display = 'flex';
            } else if (type === 'active') {
                card.style.display = card.classList.contains('active-offer') ? 'flex' : 'none';
            } else if (type === 'unactive') {
                card.style.display = card.classList.contains('unactive-offer') ? 'flex' : 'none';
            }
        });
    }

    function copyCode(elementId, btn) {
        const codeEl = document.getElementById(elementId);
        if (!codeEl) return;
        const code = codeEl.innerText;
        navigator.clipboard.writeText(code).then(() => {
            const orig = btn.innerText;
            btn.innerText = 'Copied!';
            btn.className = 'bg-green-600 text-white font-bold px-3 py-1.5 rounded-lg text-xs transition-all border border-green-600 cursor-pointer';
            setTimeout(() => {
                btn.innerText = orig;
                btn.className = 'bg-surface-container-highest hover:bg-primary-container hover:text-on-primary-container text-on-surface font-bold px-3 py-1.5 rounded-lg text-xs transition-all border border-outline-variant cursor-pointer';
            }, 2000);
        }).catch(err => {
            alert('Failed to copy code: ' + err);
        });
    }
</script>

<?php view('partials/seller_footer'); ?>
