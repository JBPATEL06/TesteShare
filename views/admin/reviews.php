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

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle_review') {
        $reviewId = intval($_POST['review_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'Public storefront');
        if ($reviewId) {
            $stmt = $db->prepare("UPDATE reviews SET status = ? WHERE id = ?");
            $stmt->execute([$status, $reviewId]);
        }
        exit;
    } elseif ($action === 'award_chef') {
        $reviewId = intval($_POST['review_id'] ?? 0);
        if ($reviewId) {
            $stmt = $db->prepare("UPDATE reviews SET chef_month_nominated = 1 WHERE id = ?");
            $stmt->execute([$reviewId]);
        }
        exit;
    } elseif ($action === 'batch_deactivate') {
        $ids = $_POST['review_ids'] ?? '';
        if ($ids) {
            $idArr = array_map('intval', explode(',', $ids));
            if (!empty($idArr)) {
                $placeholders = implode(',', array_fill(0, count($idArr), '?'));
                $stmt = $db->prepare("UPDATE reviews SET status = 'Deactivated for Store (User Active)' WHERE id IN ($placeholders)");
                $stmt->execute($idArr);
            }
        }
        exit;
    }
}

// Seed if needed
$reviewCount = $db->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
if ($reviewCount < 4) {
    $julianId = $db->query("SELECT id FROM users WHERE email = 'julian.rivera@testshare.io'")->fetchColumn();
    $sarahId = $db->query("SELECT id FROM users WHERE email = 'sarah.j@outlook.com'")->fetchColumn();
    $smithId = $db->query("SELECT id FROM users WHERE email = 'smith.j@gmail.com'")->fetchColumn();
    
    $obsidianId = $db->query("SELECT id FROM stores WHERE store_name = 'The Obsidian Grill'")->fetchColumn();
    $bistroId = $db->query("SELECT id FROM stores WHERE store_name = 'Bistro 88'")->fetchColumn();
    $sakuraId = $db->query("SELECT id FROM stores WHERE store_name = 'Sakura Fusion'")->fetchColumn();
    
    if ($julianId && $sarahId && $smithId && $obsidianId && $bistroId && $sakuraId) {
        $db->prepare("INSERT INTO reviews (customer_id, store_id, rating_stars, comment_text, helpful_upvotes, is_popular, status) VALUES (?, ?, 1, 'Food arrived cold and the packaging was damaged. Highly disappointing. Tried calling the restaurant but no one picked up the order confirmation line.', 0, 0, 'Public storefront')")
           ->execute([$sarahId, $bistroId]);
          
        $db->prepare("INSERT INTO reviews (customer_id, store_id, rating_stars, comment_text, helpful_upvotes, is_popular, status) VALUES (?, ?, 1, 'Terrible experience. Found hair in my sushi rolls. Never ordering again! Called customer support for immediate platform intervention.', 25, 1, 'Public storefront')")
           ->execute([$smithId, $sakuraId]);
          
        $db->prepare("INSERT INTO reviews (customer_id, store_id, rating_stars, comment_text, helpful_upvotes, is_popular, status) VALUES (?, ?, 4, 'Solid dining experience. Great ambiance and the preparation was spot-on. Minor delivery wait but warm food compensates.', 3, 0, 'Public storefront')")
           ->execute([$julianId, $obsidianId]);
    }
}

// Load stores overview (ratings card)
$storesList = $db->query("
    SELECT s.store_name, IFNULL(AVG(r.rating_stars), 5.0) as avg_rating, COUNT(r.id) as reviews_count
    FROM stores s
    LEFT JOIN reviews r ON s.id = r.store_id
    GROUP BY s.id
")->fetchAll();

// Load reviews
$reviews = $db->query("
    SELECT r.*, u.fullname as reviewer_name, s.store_name
    FROM reviews r
    JOIN users u ON r.customer_id = u.id
    JOIN stores s ON r.store_id = s.id
    ORDER BY r.created_at DESC
")->fetchAll();

$pageTitle = 'Master Reviews Moderation';
$activeNav = 'reviews';
view('partials/admin_header', get_defined_vars());
view('partials/admin_sidebar', get_defined_vars());
?>

<div class="flex-grow p-8 overflow-y-auto">
    <!-- Content Canvas -->
    <div class="max-w-6xl mx-auto space-y-8">
        
        <!-- Page Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-outline pb-6">
            <div>
                <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold mb-1">Store Ratings &amp; Reviews Moderation</h2>
                <p class="text-body-md text-on-surface-variant mb-0 font-semibold">Deactivate problematic 1-star reviews for the store profile (reviewer user account remains active) or award 'Chef of the Month' honors to stellar 5-star merchants.</p>
            </div>
        </div>

        <!-- Store Average Ratings Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php foreach ($storesList as $storeObj): ?>
                <?php
                $stars = str_repeat('★', round($storeObj['avg_rating'])) . str_repeat('☆', 5 - round($storeObj['avg_rating']));
                ?>
                <div class="bg-surface-container border border-outline p-5 rounded-2xl shadow-lg relative overflow-hidden group hover:border-primary transition-all">
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="text-xs uppercase tracking-widest text-on-surface-variant font-bold"><?php echo htmlspecialchars($storeObj['store_name']); ?></span>
                            <div class="flex items-baseline gap-2 mt-2">
                                <span class="text-3xl font-bold font-mono text-on-surface"><?php echo number_format($storeObj['avg_rating'], 1); ?></span>
                                <span class="text-xs font-semibold text-yellow-400"><?php echo $stars; ?></span>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-primary text-3xl">star_rate</span>
                    </div>
                    <div class="text-xs text-on-surface-variant mt-2 font-semibold">
                        Based on <?php echo number_format($storeObj['reviews_count']); ?> customer reviews
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Moderation Filter Control Bar -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-surface-container border border-outline p-4 rounded-2xl shadow-md">
            <!-- Filter Tabs -->
            <div class="flex flex-wrap gap-2" id="filterTabContainer">
                <button onclick="changeReviewFilter('all')" class="filter-tab px-4 py-2 rounded-xl text-xs font-bold transition-all bg-primary/20 text-primary border border-primary/20">All Reviews</button>
                <button onclick="changeReviewFilter('popular')" class="filter-tab px-4 py-2 rounded-xl text-xs font-bold transition-all text-on-surface-variant hover:bg-surface-container-high border border-outline/30">Popular (Most Liked)</button>
                <button onclick="changeReviewFilter('5')" class="filter-tab px-4 py-2 rounded-xl text-xs font-bold transition-all text-on-surface-variant hover:bg-surface-container-high border border-outline/30 flex items-center gap-1">5 Stars <span class="material-symbols-outlined text-xs text-primary fill-[1]">star</span></button>
                <button onclick="changeReviewFilter('4')" class="filter-tab px-4 py-2 rounded-xl text-xs font-bold transition-all text-on-surface-variant hover:bg-surface-container-high border border-outline/30 flex items-center gap-1">4 Stars <span class="material-symbols-outlined text-xs text-primary fill-[1]">star</span></button>
                <button onclick="changeReviewFilter('3')" class="filter-tab px-4 py-2 rounded-xl text-xs font-bold transition-all text-on-surface-variant hover:bg-surface-container-high border border-outline/30 flex items-center gap-1">3 Stars <span class="material-symbols-outlined text-xs text-primary fill-[1]">star</span></button>
                <button onclick="changeReviewFilter('2')" class="filter-tab px-4 py-2 rounded-xl text-xs font-bold transition-all text-on-surface-variant hover:bg-surface-container-high border border-outline/30 flex items-center gap-1">2 Stars <span class="material-symbols-outlined text-xs text-primary fill-[1]">star</span></button>
                <button onclick="changeReviewFilter('1')" class="filter-tab px-4 py-2 rounded-xl text-xs font-bold transition-all text-on-surface-variant hover:bg-surface-container-high border border-outline/30 flex items-center gap-1">1 Star <span class="material-symbols-outlined text-xs text-primary fill-[1]">star</span></button>
            </div>

            <!-- Bulk Operations Block (Specific to 1-star filtering) -->
            <div id="bulkOperationsContainer" class="hidden flex items-center gap-3">
                <label class="flex items-center gap-2 text-xs font-bold text-on-surface-variant select-none">
                    <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAllReviews(this)" class="w-4 h-4 rounded border-outline accent-primary">
                    <span>Select All 1-Star</span>
                </label>
                <button onclick="batchDeactivateReviews()" class="px-4 py-2 bg-error text-on-primary font-bold text-xs uppercase tracking-wider rounded-xl border-0 shadow-md hover:brightness-110 active:scale-95 transition-all">
                    Deactivate for Store
                </button>
            </div>
        </div>

        <!-- Master Reviews Stream Feed -->
        <div class="space-y-4" id="reviewsFeed">
            <?php foreach ($reviews as $rRow): ?>
                <?php
                $rating = $rRow['rating_stars'];
                $isPopular = $rRow['is_popular'] ? 'true' : 'false';
                $nameParts = explode(' ', $rRow['store_name']);
                $initials = strtoupper(substr($nameParts[0] ?? '', 0, 1) . substr($nameParts[1] ?? 'ST', 0, 1));
                if (empty($initials)) $initials = 'ST';
                
                $createdTime = date('Y-m-d H:i', strtotime($rRow['created_at']));
                $status = $rRow['status'];
                ?>
                <div class="review-card bg-surface-container border border-outline rounded-2xl p-6 shadow-lg transition-all space-y-4" id="review-card-<?php echo $rRow['id']; ?>" data-rating="<?php echo $rating; ?>" data-popular="<?php echo $isPopular; ?>">
                    <div class="flex justify-between items-start">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center font-bold text-primary font-mono text-sm"><?php echo $initials; ?></div>
                            <div>
                                <div class="text-sm font-bold text-on-surface"><?php echo htmlspecialchars($rRow['store_name']); ?></div>
                                <div class="text-[10px] text-on-surface-variant font-semibold">Reviewed by <span class="text-primary font-bold"><?php echo htmlspecialchars($rRow['reviewer_name']); ?></span> · <?php echo $createdTime; ?></div>
                            </div>
                        </div>
                        
                        <div class="flex flex-col items-end gap-1">
                            <div class="flex gap-0.5 text-primary">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="material-symbols-outlined text-sm font-bold <?php echo $i <= $rating ? 'fill-[1]' : 'text-outline-variant'; ?>">star</span>
                                <?php endfor; ?>
                            </div>
                            <span class="text-[10px] font-mono font-bold text-on-surface-variant"><?php echo number_format($rRow['helpful_upvotes']); ?> helpful upvotes</span>
                        </div>
                    </div>

                    <p class="text-sm text-on-surface leading-relaxed"><?php echo htmlspecialchars($rRow['comment_text']); ?></p>

                    <!-- Actions row -->
                    <div class="flex justify-between items-center pt-4 border-t border-outline/30">
                        <div class="flex items-center gap-3" id="badges-container-<?php echo $rRow['id']; ?>">
                            <?php if ($rating == 1): ?>
                                <label class="flex items-center gap-2 text-xs font-bold text-on-surface-variant select-none select-label">
                                    <input type="checkbox" class="review-checkbox w-4 h-4 rounded border-outline accent-primary" data-id="<?php echo $rRow['id']; ?>">
                                    <span>Select for moderation</span>
                                </label>
                            <?php endif; ?>
                            
                            <?php if ($status === 'Public storefront'): ?>
                                <span class="status-badge text-[10px] font-bold px-2.5 py-1 bg-green-500/10 text-green-400 border border-green-500/20 rounded-full uppercase tracking-wider">Public storefront</span>
                            <?php else: ?>
                                <span class="status-badge text-[10px] font-bold px-2.5 py-1 bg-red-500/10 text-red-400 border border-red-500/20 rounded-full uppercase tracking-wider">Deactivated for Store (User Active)</span>
                            <?php endif; ?>
                            
                            <?php if ($rRow['chef_month_nominated']): ?>
                                <span class="chef-month-badge text-[10px] font-bold px-2.5 py-1 bg-primary/20 text-primary border border-primary/30 rounded-full uppercase tracking-wider flex items-center gap-1 animate-pulse">
                                    <span class="material-symbols-outlined text-xs">workspace_premium</span>
                                    <span>Chef of the Month</span>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="space-x-2">
                            <?php if ($rating == 5): ?>
                                <?php if ($rRow['chef_month_nominated']): ?>
                                    <button class="award-btn px-4 py-2 bg-surface-container-high border border-outline text-on-surface-variant font-bold text-xs uppercase tracking-wider rounded-xl pointer-events-none cursor-default" disabled>Awarded Nominee</button>
                                <?php else: ?>
                                    <button onclick="awardChefOfTheMonth('<?php echo $rRow['id']; ?>', '<?php echo addslashes($rRow['store_name']); ?>')" class="award-btn px-4 py-2 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-xl border-0 shadow-md cursor-pointer hover:brightness-110 active:scale-95 transition-all">
                                        Award "Chef of the Month"
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <button onclick="toggleSingleReview('<?php echo $rRow['id']; ?>')" class="toggle-btn px-4 py-2 border <?php echo $status === 'Public storefront' ? 'border-red-500/30 text-red-400 bg-red-500/5 hover:bg-red-500/10' : 'border-green-500/30 text-green-400 bg-green-500/5 hover:bg-green-500/10'; ?> font-bold text-xs uppercase tracking-wider rounded-xl cursor-pointer transition-all">
                                    <?php echo $status === 'Public storefront' ? 'Deactivate for Store' : 'Activate'; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
    </div>
</div>

<script>
    // Tab filters
    function changeReviewFilter(filterValue) {
        // Toggle tab highlights
        const tabs = document.querySelectorAll('.filter-tab');
        tabs.forEach(tab => {
            tab.className = "filter-tab px-4 py-2 rounded-xl text-xs font-bold transition-all text-on-surface-variant hover:bg-surface-container-high border border-outline/30";
        });

        // Set active style for selected tab
        event.currentTarget.className = "filter-tab px-4 py-2 rounded-xl text-xs font-bold transition-all bg-primary/20 text-primary border border-primary/20";

        // Show/hide reviews
        const cards = document.querySelectorAll('.review-card');
        cards.forEach(card => {
            const rating = card.getAttribute('data-rating');
            const isPopular = card.getAttribute('data-popular') === 'true';

            let show = false;
            if (filterValue === 'all') {
                show = true;
            } else if (filterValue === 'popular') {
                show = isPopular;
            } else if (filterValue === rating) {
                show = true;
            }

            card.style.display = show ? '' : 'none';
        });

        // Show bulk actions only when looking at 1 star reviews
        const bulkOps = document.getElementById('bulkOperationsContainer');
        if (filterValue === '1') {
            bulkOps.classList.remove('hidden');
        } else {
            bulkOps.classList.add('hidden');
        }

        // Uncheck selects
        document.getElementById('selectAllCheckbox').checked = false;
        toggleSelectAllReviews({checked: false});
    }

    // Toggle select all visible 1-star reviews
    function toggleSelectAllReviews(masterBox) {
        const checkableVisibleBoxes = document.querySelectorAll('.review-card[data-rating="1"] .review-checkbox');
        checkableVisibleBoxes.forEach(box => {
            const card = box.closest('.review-card');
            if (card && card.style.display !== 'none') {
                box.checked = masterBox.checked;
            }
        });
    }

    // Batch deactivate selected 1-star reviews
    function batchDeactivateReviews() {
        const checkedBoxes = document.querySelectorAll('.review-checkbox:checked');
        if (checkedBoxes.length === 0) {
            alert('No reviews selected.');
            return;
        }

        if (confirm(`Are you sure you want to deactivate ${checkedBoxes.length} selected 1-star review(s)? They will be hidden from the store's public menu (reviewer user accounts remain active).`)) {
            const ids = Array.from(checkedBoxes).map(box => box.getAttribute('data-id')).join(',');
            const formData = new FormData();
            formData.append('action', 'batch_deactivate');
            formData.append('review_ids', ids);

            fetch('', { method: 'POST', body: formData })
                .then(() => window.location.reload());
        }
    }

    // Toggle single review status (deactivate/activate)
    function toggleSingleReview(id) {
        const card = document.getElementById(`review-card-${id}`);
        if (!card) return;

        const badge = card.querySelector('.status-badge');
        const isPublic = badge.textContent.toLowerCase().includes('public');
        const newStatus = isPublic ? 'Deactivated for Store (User Active)' : 'Public storefront';

        const formData = new FormData();
        formData.append('action', 'toggle_review');
        formData.append('review_id', id);
        formData.append('status', newStatus);

        fetch('', { method: 'POST', body: formData })
            .then(() => window.location.reload());
    }

    // Nominate/Award Chef of the Month title to 5-star merchants
    function awardChefOfTheMonth(id, storeName) {
        if (confirm(`Would you like to award the title 'Chef of the Month' to the culinary team at ${storeName} for this outstanding review?`)) {
            const formData = new FormData();
            formData.append('action', 'award_chef');
            formData.append('review_id', id);

            fetch('', { method: 'POST', body: formData })
                .then(() => {
                    alert(`Title released! '${storeName}' nominated for Chef of the Month. Systems badges updated across TestShare menus.`);
                    window.location.reload();
                });
        }
    }
</script>

<?php view('partials/admin_footer'); ?>
