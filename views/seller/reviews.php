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

// Resolve store — same pattern used across all seller pages
$storeId = 0;
$myStore = null;
if ($sellerId) {
    $stmtStore = $db->prepare("SELECT * FROM stores WHERE owner_id = ?");
    $stmtStore->execute([$sellerId]);
    $myStore = $stmtStore->fetch();
    if ($myStore) $storeId = $myStore['id'];
}
if (!$sellerId || !$storeId) {
    header("Location: " . url('seller/login'));
    exit;
}

// Handle deactivate/reactivate review moderation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['review_id'])) {
    $reviewId = intval($_POST['review_id']);
    if ($_POST['action'] === 'deactivate') {
        $db->prepare("UPDATE reviews SET status = 'Deactivated for Store (User Active)' WHERE id = ? AND store_id = ?")
           ->execute([$reviewId, $storeId]);
    } elseif ($_POST['action'] === 'reactivate') {
        $db->prepare("UPDATE reviews SET status = 'Public storefront' WHERE id = ? AND store_id = ?")
           ->execute([$reviewId, $storeId]);
    }
    header("Location: " . url('seller/reviews'));
    exit;
}

// Fetch ALL reviews for this store so seller can see and moderate them
$stmt = $db->prepare("
    SELECT r.*, u.fullname as reviewer_name
    FROM reviews r
    JOIN users u ON r.customer_id = u.id
    WHERE r.store_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$storeId]);
$reviews = $stmt->fetchAll();

$reviewsCount = count($reviews);

// Calculate averages across all reviews
$avgRating = 5.0;
if ($reviewsCount > 0) {
    $sum = 0;
    foreach ($reviews as $rev) {
        $sum += $rev['rating_stars'];
    }
    $avgRating = number_format($sum / $reviewsCount, 1);
}

// Distribution
$starsBreakdown = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
foreach ($reviews as $rev) {
    $starsBreakdown[$rev['rating_stars']]++;
}

// Count public vs hidden for context
$publicCount = count(array_filter($reviews, fn($r) => $r['status'] === 'Public storefront'));
$hiddenCount = $reviewsCount - $publicCount;

$pageTitle = 'Reviews & Ratings';
$activeNav = 'reviews';
view('partials/seller_header', get_defined_vars());
view('partials/seller_sidebar', get_defined_vars());
?>

    <!-- Main Content Area -->
    <div class="flex-grow p-8 overflow-y-auto">
        <!-- Content Canvas -->
        <div class="max-w-7xl mx-auto w-full">
            <!-- Header Section -->
            <div class="mb-8">
                <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold mb-1">Reviews &amp; Ratings</h2>
                <p class="text-body-md text-on-surface-variant mt-2 mb-0">Monitor and manage customer feedback for your store and menu items.</p>
            </div>
            <!-- Bento Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6 mb-12">
                <!-- Overall Rating -->
                <div class="md:col-span-4 glass-card p-8 flex flex-col justify-center items-center text-center shadow-xl bg-surface-container rounded-3 border border-outline-variant p-4">
                    <span class="text-label-md text-on-surface-variant uppercase tracking-widest mb-4 font-bold">Overall Store Rating</span>
                    <div class="text-[64px] font-bold text-primary-container leading-none mb-4"><?php echo $avgRating; ?></div>
                    <div class="flex gap-1 mb-4 text-primary-container">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' <?php echo $i <= $avgRating ? '1' : '0'; ?>;">star</span>
                        <?php endfor; ?>
                    </div>
                    <p class="text-on-surface-variant text-label-md mb-0 font-bold">Based on <?php echo $reviewsCount; ?> reviews</p>
                    <div class="flex gap-3 mt-3">
                        <span class="text-[10px] bg-green-900/30 text-green-400 border border-green-800 px-2 py-0.5 rounded font-bold"><?php echo $publicCount; ?> Public</span>
                        <?php if ($hiddenCount > 0): ?>
                        <span class="text-[10px] bg-error-container/20 text-error border border-error-container/30 px-2 py-0.5 rounded font-bold"><?php echo $hiddenCount; ?> Hidden</span>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Rating Breakdown -->
                <div class="md:col-span-8 glass-card p-8 shadow-xl bg-surface-container rounded-3 border border-outline-variant p-4">
                    <div class="flex justify-between items-center mb-6">
                        <span class="text-label-md text-on-surface-variant uppercase tracking-widest font-bold mb-0">Rating Distribution</span>
                        <span class="text-label-sm text-tertiary font-bold mb-0">Top 1% in Category</span>
                    </div>
                    <div class="space-y-4">
                        <?php for ($stars = 5; $stars >= 1; $stars--): ?>
                            <?php
                            $cnt = $starsBreakdown[$stars];
                            $pct = $reviewsCount > 0 ? round(($cnt / $reviewsCount) * 100) : 0;
                            ?>
                            <!-- Star Distribution -->
                            <div class="flex items-center gap-4">
                                <span class="text-label-sm w-12 text-on-surface-variant font-bold"><?php echo $stars; ?> Star</span>
                                <div class="flex-1 h-2 bg-surface-container-highest rounded-full overflow-hidden">
                                    <div class="h-full bg-primary-container" style="width: <?php echo $pct; ?>%;"></div>
                                </div>
                                <span class="text-label-sm w-10 text-right font-bold"><?php echo $pct; ?>%</span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- Individual Reviews List -->
            <div class="bg-surface-container rounded-xl border border-outline-variant p-6 space-y-6">
                <h3 class="font-headline-md text-on-surface font-bold mb-4">Customer Reviews</h3>
                <?php if (empty($reviews)): ?>
                    <p class="text-on-surface-variant font-body-md">No reviews for your store yet.</p>
                <?php else: ?>
                    <div class="space-y-6 divide-y divide-outline-variant/30">
                        <?php foreach ($reviews as $rev): ?>
                            <?php $isPublic = $rev['status'] === 'Public storefront'; ?>
                            <div class="pt-6 first:pt-0 <?php echo !$isPublic ? 'opacity-50' : ''; ?>">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <p class="font-label-md text-on-surface font-bold mb-0"><?php echo htmlspecialchars($rev['reviewer_name']); ?></p>
                                        <p class="font-label-sm text-on-surface-variant mb-0"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="flex text-primary-container">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' <?php echo $i <= $rev['rating_stars'] ? '1' : '0'; ?>;">star</span>
                                            <?php endfor; ?>
                                        </div>
                                        <!-- Moderation Badge -->
                                        <?php if ($isPublic): ?>
                                            <span class="text-[10px] bg-green-900/30 text-green-400 border border-green-800 px-2 py-0.5 rounded font-bold uppercase">Public</span>
                                        <?php else: ?>
                                            <span class="text-[10px] bg-error-container/20 text-error border border-error-container/30 px-2 py-0.5 rounded font-bold uppercase">Hidden</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p class="text-on-surface-variant font-body-md leading-relaxed mt-2 mb-3">"<?php echo htmlspecialchars($rev['comment_text']); ?>"</p>
                                <!-- Moderation action -->
                                <form method="POST" action="" class="m-0">
                                    <input type="hidden" name="review_id" value="<?php echo $rev['id']; ?>">
                                    <?php if ($isPublic): ?>
                                        <input type="hidden" name="action" value="deactivate">
                                        <button type="submit" class="text-xs text-error border border-error/30 bg-error/10 hover:bg-error/20 px-3 py-1 rounded-lg font-bold transition-all cursor-pointer">
                                            Hide from storefront
                                        </button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="reactivate">
                                        <button type="submit" class="text-xs text-primary border border-primary/30 bg-primary/10 hover:bg-primary/20 px-3 py-1 rounded-lg font-bold transition-all cursor-pointer">
                                            Restore to storefront
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php view('partials/seller_footer'); ?>
