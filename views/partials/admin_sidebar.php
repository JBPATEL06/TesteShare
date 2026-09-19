    <style>
        #adminSidebar {
            transition: all 0.2s ease-in-out;
        }
        @media (max-width: 767px) {
            #adminSidebar:not(.show-mobile) {
                display: none !important;
            }
            #adminSidebar.show-mobile {
                display: flex !important;
                position: fixed;
                top: 0;
                left: 0;
                z-index: 50;
                height: 100vh;
            }
        }
        @media (min-width: 768px) {
            #adminSidebar.hide-desktop {
                display: none !important;
            }
        }
    </style>

    <!-- Sidebar Navigation -->
    <aside id="adminSidebar" class="hidden md:flex flex-col h-full w-64 border-r border-outline bg-surface-container-lowest transition-all duration-200 ease-in-out shrink-0">
        <div class="px-6 py-8 flex flex-col gap-2">
            <a href="<?php echo url('user/home'); ?>" class="text-decoration-none">
                <div class="font-headline-sm text-headline-sm text-primary font-bold">TestShare Admin</div>
            </a>
            <div class="flex items-center gap-3 mt-4">
                <div class="w-10 h-10 rounded-full bg-surface-container-highest border border-outline flex items-center justify-center overflow-hidden">
                    <span class="material-symbols-outlined text-primary text-2xl">admin_panel_settings</span>
                </div>
                <div>
                    <p class="font-label-md text-label-md text-on-surface mb-0 font-bold">Super Admin</p>
                    <p class="font-label-sm text-label-sm text-on-surface-variant mb-0">Full System Root</p>
                </div>
            </div>
        </div>
        <nav class="flex-1 px-4 py-4 flex flex-col gap-2 overflow-y-auto custom-scrollbar">
            <!-- Dashboard Preview -->
            <a class="flex items-center gap-3 px-4 py-2.5 <?php echo (isset($activeNav) && $activeNav === 'dashboard') ? 'bg-primary/20 text-primary font-bold rounded-lg translate-x-1 border-l-4 border-primary transition-all' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface rounded-lg transition-all'; ?> text-decoration-none font-label-md text-label-md" href="<?php echo url('admin/dashboard'); ?>">
                <span class="material-symbols-outlined">analytics</span>
                <span>System Dashboard</span>
            </a>
            <!-- Sales Overview Analytics -->
            <a class="flex items-center gap-3 px-4 py-2.5 <?php echo (isset($activeNav) && $activeNav === 'sales_analytics') ? 'bg-primary/20 text-primary font-bold rounded-lg translate-x-1 border-l-4 border-primary transition-all' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface rounded-lg transition-all'; ?> text-decoration-none font-label-md text-label-md" href="<?php echo url('admin/sales_analytics'); ?>">
                <span class="material-symbols-outlined">finance_chip</span>
                <span>Sales &amp; Commission</span>
            </a>
            <!-- Users Control -->
            <a class="flex items-center gap-3 px-4 py-2.5 <?php echo (isset($activeNav) && $activeNav === 'users') ? 'bg-primary/20 text-primary font-bold rounded-lg translate-x-1 border-l-4 border-primary transition-all' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface rounded-lg transition-all'; ?> text-decoration-none font-label-md text-label-md" href="<?php echo url('admin/users'); ?>">
                <span class="material-symbols-outlined">group</span>
                <span>User Management</span>
            </a>
            <!-- Sellers Control -->
            <a class="flex items-center gap-3 px-4 py-2.5 <?php echo (isset($activeNav) && $activeNav === 'sellers') ? 'bg-primary/20 text-primary font-bold rounded-lg translate-x-1 border-l-4 border-primary transition-all' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface rounded-lg transition-all'; ?> text-decoration-none font-label-md text-label-md" href="<?php echo url('admin/sellers'); ?>">
                <span class="material-symbols-outlined">storefront</span>
                <span>Seller Management</span>
            </a>
            <!-- Subscriptions -->
            <a class="flex items-center gap-3 px-4 py-2.5 <?php echo (isset($activeNav) && $activeNav === 'subscriptions') ? 'bg-primary/20 text-primary font-bold rounded-lg translate-x-1 border-l-4 border-primary transition-all' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface rounded-lg transition-all'; ?> text-decoration-none font-label-md text-label-md" href="<?php echo url('admin/subscriptions'); ?>">
                <span class="material-symbols-outlined">loyalty</span>
                <span>Subscriptions</span>
            </a>
            <!-- Reviews -->
            <a class="flex items-center gap-3 px-4 py-2.5 <?php echo (isset($activeNav) && $activeNav === 'reviews') ? 'bg-primary/20 text-primary font-bold rounded-lg translate-x-1 border-l-4 border-primary transition-all' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface rounded-lg transition-all'; ?> text-decoration-none font-label-md text-label-md" href="<?php echo url('admin/reviews'); ?>">
                <span class="material-symbols-outlined font-bold">star_rate</span>
                <span>Seller Reviews</span>
            </a>
            <!-- Global Coupons & Subsidy System -->
            <a class="flex items-center gap-3 px-4 py-2.5 <?php echo (isset($activeNav) && $activeNav === 'offers') ? 'bg-primary/20 text-primary font-bold rounded-lg translate-x-1 border-l-4 border-primary transition-all' : 'text-on-surface-variant hover:bg-surface-container-highest hover:text-on-surface rounded-lg transition-all'; ?> text-decoration-none font-label-md text-label-md" href="<?php echo url('admin/offers'); ?>">
                <span class="material-symbols-outlined">local_activity</span>
                <span>Global Offers &amp; Subsidy</span>
            </a>
        </nav>
        <div class="p-4 border-t border-outline">
            <a class="flex items-center justify-center gap-3 px-4 py-3 text-on-surface-variant hover:text-primary transition-all font-label-md text-label-md text-decoration-none font-bold" href="#">
                <span class="material-symbols-outlined">help</span>
                System Logs
            </a>
        </div>
    </aside>
    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col h-full overflow-hidden bg-background relative">
        <!-- Top App Bar -->
        <header class="flex justify-between items-center px-8 py-4 border-b border-outline bg-background z-20 shrink-0">
            <div class="flex items-center gap-4">
                <button id="adminSidebarToggle" class="p-2 text-on-surface-variant hover:text-primary transition-colors bg-transparent border-0 select-none">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <h1 class="font-headline-md text-headline-md font-bold text-primary mb-0"><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Admin Overview'; ?></h1>
            </div>
            <div class="flex items-center gap-4">
                <button onclick="window.location.href='<?php echo url('user/logout'); ?>'" class="p-2 text-on-surface-variant hover:text-red-400 transition-colors bg-transparent border-0 cursor-pointer" title="Logout">
                    <span class="material-symbols-outlined">logout</span>
                </button>
            </div>
        </header>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const toggleBtn = document.getElementById('adminSidebarToggle');
                if (toggleBtn) {
                    toggleBtn.addEventListener('click', () => {
                        const sidebar = document.getElementById('adminSidebar');
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
