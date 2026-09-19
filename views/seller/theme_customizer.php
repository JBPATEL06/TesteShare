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

// Resolve store
$storeId = 0;
if ($sellerId) {
    $stmtStore = $db->prepare("SELECT id FROM stores WHERE owner_id = ?");
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

// Gate feature — Premium or Ultra Premium required
if (!hasFeature($storeId, 'theme_customizer')) {
    $pageTitle = 'Theme Customizer — Premium Feature';
    $activeNav = 'theme_customizer';
    view('partials/seller_header', get_defined_vars());
    view('partials/seller_sidebar', get_defined_vars());
    ?>
    <div class="flex-grow p-8 overflow-y-auto">
        <div class="max-w-4xl mx-auto py-12 text-center bg-surface-container border border-outline-variant rounded-2xl shadow-xl p-8 space-y-6">
            <span class="material-symbols-outlined text-[64px] text-primary">palette</span>
            <h2 class="font-headline-lg text-on-surface font-bold">Seller Panel Theme Customizer</h2>
            <p class="text-body-lg text-on-surface-variant max-w-lg mx-auto">
                Customizing your merchant panel accent colors and user-facing storefront branding is exclusively available to <strong class="text-primary">Premium</strong> and <strong class="text-amber-400">Ultra Premium</strong> subscribers.
            </p>
            <a href="<?php echo url('seller/subscription'); ?>" class="inline-block px-8 py-3 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-xl hover:brightness-110 transition-all text-decoration-none shadow-lg">
                Upgrade to Premium ($49/mo)
            </a>
        </div>
    </div>
    <?php
    view('partials/seller_footer');
    exit;
}

$msgSuccess = '';
$msgError = '';

// Handle theme update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_theme') {
    $accentColor = trim($_POST['accent_color'] ?? '#ff9f0d');
    $secondaryColor = trim($_POST['secondary_color'] ?? '#2d2d2d');
    $bannerStyle = trim($_POST['banner_style'] ?? 'default');
    $customCss = trim($_POST['custom_css'] ?? '');
    
    // Upsert theme config
    $upStmt = $db->prepare("
        INSERT INTO store_theme_config (store_id, accent_color, secondary_color, banner_style, custom_css)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            accent_color = VALUES(accent_color),
            secondary_color = VALUES(secondary_color),
            banner_style = VALUES(banner_style),
            custom_css = VALUES(custom_css)
    ");
    $upStmt->execute([$storeId, $accentColor, $secondaryColor, $bannerStyle, $customCss]);
    $msgSuccess = "Theme branding settings updated successfully!";
}

// Fetch current config
$themeConfig = getStoreThemeConfig($storeId);

$pageTitle = 'Theme & Brand Customizer';
$activeNav = 'theme_customizer';
view('partials/seller_header', get_defined_vars());
view('partials/seller_sidebar', get_defined_vars());
?>

<div class="flex-grow p-8 overflow-y-auto">
    <div class="max-w-4xl mx-auto space-y-8">
        <!-- Header -->
        <div class="border-b border-outline-variant pb-4">
            <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold mb-1">Theme &amp; Brand Customizer</h2>
            <p class="text-body-md text-on-surface-variant mb-0">Customize your merchant panel color palette and storefront presentation for customers.</p>
        </div>

        <?php if ($msgSuccess): ?>
            <div class="bg-green-950/20 border border-green-500 text-green-400 rounded-xl p-4 text-sm font-bold">
                <?php echo htmlspecialchars($msgSuccess); ?>
            </div>
        <?php endif; ?>

        <!-- Form & Live Preview Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Left: Settings Form -->
            <div class="bg-surface-container border border-outline-variant p-8 rounded-2xl shadow-xl space-y-6">
                <h3 class="font-headline-sm text-on-surface font-bold mb-4">Color Palette &amp; Branding</h3>
                <form action="" method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="save_theme">
                    
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-on-surface-variant uppercase">Primary Accent Color</label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="accent_color" id="accent_color_input" value="<?php echo htmlspecialchars($themeConfig['accent_color'] ?? '#ff9f0d'); ?>" class="w-12 h-12 rounded-lg border border-outline-variant cursor-pointer bg-transparent" onchange="updatePreview()">
                            <input type="text" id="accent_color_hex" value="<?php echo htmlspecialchars($themeConfig['accent_color'] ?? '#ff9f0d'); ?>" class="bg-surface border border-outline-variant text-on-surface px-3 py-2 rounded-lg text-sm font-mono w-32 uppercase" oninput="document.getElementById('accent_color_input').value=this.value; updatePreview();">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-on-surface-variant uppercase">Banner Style Preset</label>
                        <select name="banner_style" class="bg-surface border border-outline-variant text-on-surface p-3 rounded-lg text-sm w-full outline-none">
                            <option value="default" <?php echo ($themeConfig['banner_style'] ?? '') === 'default' ? 'selected' : ''; ?>>Standard Full Width</option>
                            <option value="overlay" <?php echo ($themeConfig['banner_style'] ?? '') === 'overlay' ? 'selected' : ''; ?>>Dark Overlay Gradient</option>
                            <option value="minimal" <?php echo ($themeConfig['banner_style'] ?? '') === 'minimal' ? 'selected' : ''; ?>>Minimal Card View</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-on-surface-variant uppercase">Preset Accent Colors</label>
                        <div class="flex gap-3">
                            <button type="button" onclick="setPreset('#ff9f0d')" class="w-8 h-8 rounded-full border-2 border-white shadow-md cursor-pointer" style="background:#ff9f0d;"></button>
                            <button type="button" onclick="setPreset('#e63946')" class="w-8 h-8 rounded-full border-2 border-white shadow-md cursor-pointer" style="background:#e63946;"></button>
                            <button type="button" onclick="setPreset('#2a9d8f')" class="w-8 h-8 rounded-full border-2 border-white shadow-md cursor-pointer" style="background:#2a9d8f;"></button>
                            <button type="button" onclick="setPreset('#7209b7')" class="w-8 h-8 rounded-full border-2 border-white shadow-md cursor-pointer" style="background:#7209b7;"></button>
                            <button type="button" onclick="setPreset('#4361ee')" class="w-8 h-8 rounded-full border-2 border-white shadow-md cursor-pointer" style="background:#4361ee;"></button>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3.5 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-xl hover:brightness-110 active:scale-95 transition-all border-0 shadow-lg cursor-pointer">
                        Save Theme Customizations
                    </button>
                </form>
            </div>

            <!-- Right: Live Storefront Card Preview -->
            <div class="bg-surface-container border border-outline-variant p-8 rounded-2xl shadow-xl space-y-6 flex flex-col justify-between">
                <div>
                    <h3 class="font-headline-sm text-on-surface font-bold mb-4">User-Side Card Live Preview</h3>
                    <div id="preview-card" class="bg-surface border rounded-xl p-6 shadow-lg transition-all space-y-4" style="border-color: <?php echo htmlspecialchars($themeConfig['accent_color'] ?? '#ff9f0d'); ?>;">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white font-bold" id="preview-icon" style="background-color: <?php echo htmlspecialchars($themeConfig['accent_color'] ?? '#ff9f0d'); ?>;">
                                🍔
                            </div>
                            <div>
                                <h4 class="font-bold text-on-surface text-base mb-0">Your Restaurant Name</h4>
                                <span class="text-xs text-on-surface-variant">Cuisine • Location</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-xs font-bold" id="preview-badge-wrapper">
                            <span class="px-2 py-0.5 rounded font-bold text-black" id="preview-badge" style="background-color: <?php echo htmlspecialchars($themeConfig['accent_color'] ?? '#ff9f0d'); ?>;">
                                ⭐ Premium Verified
                            </span>
                        </div>
                        <button type="button" class="w-full py-2 text-white font-bold text-xs rounded-lg border-0 shadow-md" id="preview-btn" style="background-color: <?php echo htmlspecialchars($themeConfig['accent_color'] ?? '#ff9f0d'); ?>;">
                            View Menu &amp; Order
                        </button>
                    </div>
                </div>
                <p class="text-xs text-on-surface-variant italic">This custom color scheme will be automatically applied to your store listing on the Explore page and your public restaurant storefront.</p>
            </div>
        </div>
    </div>
</div>

<script>
function setPreset(color) {
    document.getElementById('accent_color_input').value = color;
    document.getElementById('accent_color_hex').value = color;
    updatePreview();
}

function updatePreview() {
    const color = document.getElementById('accent_color_input').value;
    document.getElementById('accent_color_hex').value = color;
    document.getElementById('preview-card').style.borderColor = color;
    document.getElementById('preview-icon').style.backgroundColor = color;
    document.getElementById('preview-badge').style.backgroundColor = color;
    document.getElementById('preview-btn').style.backgroundColor = color;
}
</script>

<?php view('partials/seller_footer'); ?>
