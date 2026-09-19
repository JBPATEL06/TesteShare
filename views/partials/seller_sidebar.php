    <style>
        #sellerSidebar {
            transition: all 0.2s ease-in-out;
        }
        @media (max-width: 767px) {
            #sellerSidebar:not(.show-mobile) {
                display: none !important;
            }
            #sellerSidebar.show-mobile {
                display: flex !important;
                position: fixed;
                top: 0;
                left: 0;
                z-index: 50;
                height: 100vh;
            }
        }
        @media (min-width: 768px) {
            #sellerSidebar.hide-desktop {
                display: none !important;
            }
        }
    </style>

<?php
// Resolve merchant store logo & name for sidebar
if (!isset($sidebarStore) || !$sidebarStore) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $sUserId = $_SESSION['user_id'] ?? null;
    if ($sUserId) {
        if (!function_exists('getDB')) require_once __DIR__ . '/../../db.php';
        $sDb = getDB();
        $sStmt = $sDb->prepare("SELECT store_name, store_logo, subscription_tier FROM stores WHERE owner_id = ?");
        $sStmt->execute([$sUserId]);
        $sidebarStore = $sStmt->fetch();
    }
}
$sidebarLogo = !empty($sidebarStore['store_logo']) ? $sidebarStore['store_logo'] : asset('images/default_restaurant_logo.png');
$sidebarName = !empty($sidebarStore['store_name']) ? $sidebarStore['store_name'] : 'Merchant Outlet';
$sidebarTier = !empty($sidebarStore['subscription_tier']) ? $sidebarStore['subscription_tier'] : 'Starter';
?>
    <!-- Sidebar Navigation -->
    <aside id="sellerSidebar" class="hidden md:flex flex-col h-full w-64 border-r border-outline-variant bg-surface-container-lowest transition-all duration-200 ease-in-out shrink-0">
        <div class="px-6 py-8 flex flex-col gap-2">
            <a href="<?php echo url('seller/dashboard1'); ?>" class="text-decoration-none"><div class="font-headline-sm text-headline-sm text-primary-container font-bold">TestShare</div></a>
            <div class="flex items-center gap-3 mt-4">
                <div class="w-10 h-10 rounded-xl bg-surface-container-highest border border-outline-variant flex items-center justify-center overflow-hidden shrink-0 shadow-md">
                    <img class="w-full h-full object-cover" data-alt="<?php echo htmlspecialchars($sidebarName); ?>" src="<?php echo htmlspecialchars($sidebarLogo); ?>">
                </div>
                <div class="overflow-hidden">
                    <p class="font-label-md text-label-md text-on-surface mb-0 font-bold truncate" title="<?php echo htmlspecialchars($sidebarName); ?>"><?php echo htmlspecialchars($sidebarName); ?></p>
                    <p class="font-label-sm text-label-sm text-primary mb-0 font-bold"><?php echo htmlspecialchars($sidebarTier); ?> Tier</p>
                </div>
            </div>
        </div>
        <nav class="flex-1 px-4 py-4 flex flex-col gap-2 overflow-y-auto custom-scrollbar">
            <!-- Dashboard Preview -->
            <a class="flex items-center gap-3 px-4 py-2.5 <?php echo (isset($activeNav) && $activeNav === 'dashboard1') ? 'bg-primary-container text-on-primary-container font-bold rounded-lg translate-x-1 transition-transform' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface rounded-lg transition-all'; ?> text-decoration-none font-label-md text-label-md" href="<?php echo url('seller/dashboard1'); ?>">
                <span class="material-symbols-outlined">dashboard</span>
                <span>Dashboard</span>
            </a>
            <!-- Custom Orders Panel -->
            <a class="flex items-center gap-3 px-4 py-2.5 <?php echo (isset($activeNav) && $activeNav === 'custom_orders') ? 'bg-primary-container text-on-primary-container font-bold rounded-lg translate-x-1 transition-transform' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface rounded-lg transition-all'; ?> text-decoration-none font-label-md text-label-md" href="<?php echo url('seller/custom_orders'); ?>">
                <span class="material-symbols-outlined">contract</span>
                <span>Custom Orders</span>
            </a>
            <!-- Raw Material Sale -->
            <a class="flex items-center gap-3 px-4 py-2.5 <?php echo (isset($activeNav) && $activeNav === 'raw_material_sale') ? 'bg-primary-container text-on-primary-container font-bold rounded-lg translate-x-1 transition-transform' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface rounded-lg transition-all'; ?> text-decoration-none font-label-md text-label-md" href="<?php echo url('seller/raw_material_sale'); ?>">
                <span class="material-symbols-outlined">inventory</span>
                <span>Raw Material Sale</span>
            </a>
            <!-- Explore Market -->
            <a class="flex items-center gap-3 px-4 py-2.5 <?php echo (isset($activeNav) && $activeNav === 'explore_market') ? 'bg-primary-container text-on-primary-container font-bold rounded-lg translate-x-1 transition-transform' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface rounded-lg transition-all'; ?> text-decoration-none font-label-md text-label-md" href="<?php echo url('seller/explore_market'); ?>">
                <span class="material-symbols-outlined">storefront</span>
                <span>Explore Market</span>
            </a>
            <!-- Dish Studio -->
            <a class="flex items-center gap-3 px-4 py-2.5 <?php echo (isset($activeNav) && $activeNav === 'order_detail') ? 'bg-primary-container text-on-primary-container font-bold rounded-lg translate-x-1 transition-transform' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface rounded-lg transition-all'; ?> text-decoration-none font-label-md text-label-md" href="<?php echo url('seller/order_detail'); ?>">
                <span class="material-symbols-outlined">palette</span>
                <span>Dish Studio</span>
            </a>
            <!-- Orders Panel -->
            <a class="flex items-center gap-3 px-4 py-2.5 <?php echo (isset($activeNav) && $activeNav === 'orders_redesigned') ? 'bg-primary-container text-on-primary-container font-bold rounded-lg translate-x-1 transition-transform' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface rounded-lg transition-all'; ?> text-decoration-none font-label-md text-label-md" href="<?php echo url('seller/orders_redesigned'); ?>">
                <span class="material-symbols-outlined">list_alt</span>
                <span>Orders Panel</span>
            </a>
            <!-- Promotional Offers -->
            <a class="flex items-center gap-3 px-4 py-2.5 <?php echo (isset($activeNav) && ($activeNav === 'menu_offers' || $activeNav === 'create_offer')) ? 'bg-primary-container text-on-primary-container font-bold rounded-lg translate-x-1 transition-transform' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface rounded-lg transition-all'; ?> text-decoration-none font-label-md text-label-md" href="<?php echo url('seller/menu_offers'); ?>">
                <span class="material-symbols-outlined">local_offer</span>
                <span>Promotional Offers</span>
            </a>
            <!-- Reviews & Ratings -->
            <a class="flex items-center gap-3 px-4 py-2.5 <?php echo (isset($activeNav) && $activeNav === 'reviews') ? 'bg-primary-container text-on-primary-container font-bold rounded-lg translate-x-1 transition-transform' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface rounded-lg transition-all'; ?> text-decoration-none font-label-md text-label-md" href="<?php echo url('seller/reviews'); ?>">
                <span class="material-symbols-outlined">star_rate</span>
                <span>Reviews &amp; Ratings</span>
            </a>
            <!-- Subscription -->
            <a class="flex items-center gap-3 px-4 py-2.5 <?php echo (isset($activeNav) && $activeNav === 'subscription') ? 'bg-primary-container text-on-primary-container font-bold rounded-lg translate-x-1 transition-transform' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface rounded-lg transition-all'; ?> text-decoration-none font-label-md text-label-md" href="<?php echo url('seller/subscription'); ?>">
                <span class="material-symbols-outlined">loyalty</span>
                <span>Subscription</span>
            </a>
            <!-- Seller Location -->
            <a class="flex items-center gap-3 px-4 py-2.5 <?php echo (isset($activeNav) && $activeNav === 'location') ? 'bg-primary-container text-on-primary-container font-bold rounded-lg translate-x-1 transition-transform' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface rounded-lg transition-all'; ?> text-decoration-none font-label-md text-label-md" href="<?php echo url('seller/location'); ?>">
                <span class="material-symbols-outlined">location_on</span>
                <span>Store Location</span>
            </a>
            <!-- Store Settings -->
            <a class="flex items-center gap-3 px-4 py-2.5 <?php echo (isset($activeNav) && $activeNav === 'settings') ? 'bg-primary-container text-on-primary-container font-bold rounded-lg translate-x-1 transition-transform' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface rounded-lg transition-all'; ?> text-decoration-none font-label-md text-label-md" href="<?php echo url('seller/settings'); ?>">
                <span class="material-symbols-outlined">settings</span>
                <span>Store Settings</span>
            </a>
            <!-- Theme Customizer -->
            <a class="flex items-center gap-3 px-4 py-2.5 <?php echo (isset($activeNav) && $activeNav === 'theme_customizer') ? 'bg-primary-container text-on-primary-container font-bold rounded-lg translate-x-1 transition-transform' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface rounded-lg transition-all'; ?> text-decoration-none font-label-md text-label-md" href="<?php echo url('seller/theme_customizer'); ?>">
                <span class="material-symbols-outlined">brush</span>
                <span>Theme Customizer</span>
            </a>
        </nav>
        <div class="p-4 border-t border-outline-variant">
            <a class="flex items-center justify-center gap-3 px-4 py-3 text-on-surface-variant hover:text-primary transition-all font-label-md text-label-md text-decoration-none font-bold" href="#">
                <span class="material-symbols-outlined">help</span>
                Help Center
            </a>
        </div>
    </aside>
    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col h-full overflow-hidden bg-background relative">
        <!-- Top App Bar -->
        <header class="flex justify-between items-center px-8 py-4 border-b border-outline-variant bg-background z-20 shrink-0">
            <div class="flex items-center gap-4">
                <button id="sidebarToggle" class="p-2 text-on-surface-variant hover:text-primary transition-colors bg-transparent border-0 select-none">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <h1 class="font-headline-md text-headline-md font-bold text-primary mb-0"><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Overview'; ?></h1>
            </div>
            <div class="flex items-center gap-4">
                <button onclick="window.location.href='<?php echo url('seller/notifications'); ?>'" class="p-2 <?php echo (isset($activeNav) && $activeNav === 'notifications') ? 'text-primary' : 'text-on-surface-variant'; ?> hover:text-primary transition-colors bg-transparent border-0">
                    <span class="material-symbols-outlined">notifications</span>
                </button>
                <button onclick="window.location.href='<?php echo url('seller/profile'); ?>'" class="p-2 <?php echo (isset($activeNav) && $activeNav === 'profile') ? 'text-primary' : 'text-on-surface-variant'; ?> hover:text-primary transition-colors bg-transparent border-0">
                    <span class="material-symbols-outlined">account_circle</span>
                </button>
                <button onclick="window.location.href='<?php echo url('user/logout'); ?>'" class="p-2 text-on-surface-variant hover:text-red-400 transition-colors bg-transparent border-0 cursor-pointer" title="Logout">
                    <span class="material-symbols-outlined">logout</span>
                </button>
            </div>
        </header>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const toggleBtn = document.getElementById('sidebarToggle');
                if (toggleBtn) {
                    toggleBtn.addEventListener('click', () => {
                        const sidebar = document.getElementById('sellerSidebar');
                        if (sidebar) {
                            if (window.innerWidth < 768) {
                                sidebar.classList.toggle('show-mobile');
                            } else {
                                sidebar.classList.toggle('hide-desktop');
                            }
                        }
                    });
                }
            });
        </script>
