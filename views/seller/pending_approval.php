<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../config.php';

$db = getDB();
$sellerId = $_SESSION['user_id'] ?? null;

// Fetch store for this seller
$storeName = 'Unknown Store';
$storeIdDisplay = '#00000';
$dateSubmitted = 'Unknown Date';

if ($sellerId) {
    $stmtStore = $db->prepare("SELECT id, store_name, onboarding_status, created_at FROM stores WHERE owner_id = ?");
    $stmtStore->execute([$sellerId]);
    $myStore = $stmtStore->fetch();
    
    if ($myStore) {
        if ($myStore['onboarding_status'] === 'Approved') {
            header("Location: " . url('seller/dashboard1'));
            exit;
        }
        
        $storeName = $myStore['store_name'];
        $storeIdDisplay = '#' . str_pad($myStore['id'], 5, '0', STR_PAD_LEFT);
        $dateSubmitted = date('F j, Y', strtotime($myStore['created_at']));
        $onboardingStatus = $myStore['onboarding_status'];
    }
}

$isRejected = isset($onboardingStatus) && $onboardingStatus === 'Rejected';
$pageTitle = 'Review Status';
$activeNav = 'pending_approval';
view('partials/seller_header', get_defined_vars());
view('partials/seller_sidebar', get_defined_vars());
?>

    <!-- Main Canvas -->
    <div class="flex-grow p-8 overflow-y-auto">
        <!-- Header Shell -->
        <header class="flex justify-between items-center h-16 sticky top-0 z-40 bg-background/80 backdrop-blur-md px-container-margin border-b border-outline-variant px-6 rounded-lg mb-8">
            <h2 class="font-headline-md text-headline-md font-bold text-on-background mb-0">TestShare Seller Portal</h2>
            <div class="flex items-center gap-stack-lg gap-4">
                <button onclick="toggleSupport()" class="bg-primary text-on-primary font-label-md px-4 py-2 rounded font-bold border-0 hover:brightness-110 transition-all active:scale-95 cursor-pointer">
                    Contact Support
                </button>
            </div>
        </header>

        <!-- Content Area -->
        <div class="max-w-5xl mx-auto w-full space-y-stack-lg space-y-8">
            <?php if ($isRejected): ?>
                <div class="bg-error-container/20 border border-error text-error rounded-2xl p-8 shadow-xl space-y-4">
                    <div class="flex items-center gap-4">
                        <span class="material-symbols-outlined text-[48px]">cancel</span>
                        <div>
                            <h3 class="font-headline-lg font-bold mb-1">Registration Application Rejected</h3>
                            <p class="text-body-md text-on-surface-variant mb-0">Your store application (<?php echo htmlspecialchars($storeName); ?>) was not approved during automated compliance verification. Please contact merchant support to submit updated business verification documents.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <!-- Hero Status Card (Bento Style) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter gap-6">
                <div class="lg:col-span-2 glass-panel p-stack-lg flex flex-col justify-center relative overflow-hidden p-8">
                    <div class="absolute -right-12 -top-12 w-48 h-48 bg-primary/10 rounded-full blur-3xl"></div>
                    <div class="flex items-start gap-stack-lg gap-6">
                        <div class="bg-primary-container/20 p-stack-md rounded p-3">
                            <span class="material-symbols-outlined text-[48px] text-primary status-pulse">verified_user</span>
                        </div>
                        <div>
                            <h3 class="font-headline-lg text-headline-lg text-primary mb-stack-sm font-bold mb-2">Verification in Progress</h3>
                            <p class="font-body-lg text-body-lg text-on-surface-variant max-w-lg mb-0">
                                Your application is currently being reviewed by our verification team. We are cross-referencing your documents to ensure the highest quality standards for our platform.
                            </p>
                        </div>
                    </div>
                    <div class="mt-stack-lg pt-stack-md border-t border-primary/20 flex items-center gap-stack-sm mt-8 pt-4 gap-3">
                        <span class="material-symbols-outlined text-[20px] text-primary/70">mail</span>
                        <p class="font-label-sm text-label-sm text-on-surface-variant opacity-80 mb-0">
                            Once verified, your store login credentials will be sent to your registered email address.
                        </p>
                    </div>
                </div>
                <div class="glass-panel p-stack-lg flex flex-col justify-between border-primary/30 p-8">
                    <div>
                        <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-widest mb-stack-sm font-bold mb-2">Estimated Time</p>
                        <h4 class="font-headline-xl text-headline-xl text-on-surface font-bold mb-0">24-48 <span class="text-headline-md text-on-surface-variant font-normal">Hours</span></h4>
                    </div>
                    <div class="flex items-center gap-stack-sm text-primary gap-2 mt-6">
                        <span class="material-symbols-outlined text-[20px]">info</span>
                        <span class="font-label-md text-label-md font-bold">Priority Review Active</span>
                    </div>
                </div>
            </div>
            <!-- Application Details & Progress Tracker -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter gap-6">
                <!-- Summary -->
                <div class="glass-panel p-stack-lg space-y-stack-lg p-8 space-y-6">
                    <h5 class="font-label-md text-label-md text-primary uppercase font-bold mb-6">Registration Summary</h5>
                    <div class="space-y-stack-md space-y-4">
                        <div class="flex flex-col mb-4">
                            <span class="font-label-sm text-label-sm text-on-surface-variant">Store Name</span>
                            <span class="font-body-md text-body-md font-semibold text-on-surface"><?php echo htmlspecialchars($storeName); ?></span>
                        </div>
                        <div class="flex flex-col mb-4">
                            <span class="font-label-sm text-label-sm text-on-surface-variant">Application ID</span>
                            <span class="font-body-md text-body-md font-semibold text-on-surface"><?php echo $storeIdDisplay; ?></span>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-label-sm text-label-sm text-on-surface-variant">Date Submitted</span>
                            <span class="font-body-md text-body-md font-semibold text-on-surface"><?php echo $dateSubmitted; ?></span>
                        </div>
                    </div>
                </div>
                <!-- Timeline -->
                <div class="lg:col-span-2 glass-panel p-stack-lg flex flex-col p-8">
                    <h5 class="font-label-md text-label-md text-primary uppercase mb-stack-lg font-bold mb-8">Verification Timeline</h5>
                    <div class="relative flex-1 flex flex-col justify-between pb-stack-md space-y-8">
                        <!-- Connecting Line -->
                        <div class="absolute left-[11px] top-4 bottom-4 w-[2px] bg-outline-variant"></div>
                        <div class="absolute left-[11px] top-4 h-1/2 w-[2px] bg-primary"></div>
                        <!-- Step 1 -->
                        <div class="relative flex items-start gap-stack-lg z-10 gap-6">
                            <div class="w-6 h-6 rounded-full bg-primary flex items-center justify-center shrink-0 mt-1">
                                <span class="material-symbols-outlined text-[16px] text-on-primary-fixed" style="font-variation-settings: 'FILL' 1;">check</span>
                            </div>
                            <div>
                                <p class="font-label-md text-label-md font-bold text-on-surface mb-1">Application Submitted</p>
                                <p class="font-label-sm text-label-sm text-on-surface-variant mb-0">Successfully received on Oct 24, 10:42 AM</p>
                            </div>
                        </div>
                        <!-- Step 2 -->
                        <div class="relative flex items-start gap-stack-lg z-10 gap-6">
                            <div class="w-6 h-6 rounded-full bg-primary-container flex items-center justify-center border-2 border-primary shrink-0 mt-1">
                                <div class="w-2 h-2 rounded-full bg-primary animate-pulse"></div>
                            </div>
                            <div>
                                <p class="font-label-md text-label-md font-bold text-primary mb-1">Document Verification</p>
                                <p class="font-label-sm text-label-sm text-on-surface-variant mb-0">Under review by compliance team</p>
                            </div>
                        </div>
                        <!-- Step 3 -->
                        <div class="relative flex items-start gap-stack-lg z-10 opacity-40 gap-6">
                            <div class="w-6 h-6 rounded-full bg-surface-container flex items-center justify-center border border-outline-variant shrink-0 mt-1">
                            </div>
                            <div>
                                <p class="font-label-md text-label-md font-bold text-on-surface mb-1">Final Approval</p>
                                <p class="font-label-sm text-label-sm text-on-surface-variant mb-0">Awaiting completion of review</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- What's Next? (Informational Section) -->
            <div class="glass-panel p-stack-lg p-8">
                <h5 class="font-label-md text-label-md text-primary uppercase mb-stack-lg font-bold mb-6">What's Next?</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-stack-lg gap-6">
                    <div class="flex gap-stack-md items-start p-stack-md bg-surface-container-lowest border border-outline-variant rounded p-6 gap-4">
                        <span class="material-symbols-outlined text-primary text-[32px]">restaurant_menu</span>
                        <div>
                            <p class="font-label-md text-label-md font-bold text-on-surface mb-1">Menu Configuration</p>
                            <p class="font-label-sm text-label-sm text-on-surface-variant mt-1 mb-0">Prepare your high-quality dish images and descriptions for the digital storefront.</p>
                        </div>
                    </div>
                    <div class="flex gap-stack-md items-start p-stack-md bg-surface-container-lowest border border-outline-variant rounded p-6 gap-4">
                        <span class="material-symbols-outlined text-primary text-[32px]">campaign</span>
                        <div>
                            <p class="font-label-md text-label-md font-bold text-on-surface mb-1">Store Launch</p>
                            <p class="font-label-sm text-label-sm text-on-surface-variant mt-1 mb-0">Once approved, you can set your operating hours and go live to thousands of customers.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Support Interaction Layer -->
<div class="fixed inset-0 z-50 flex items-center justify-center bg-background/90 backdrop-blur-sm hidden" id="support-modal">
    <div class="glass-panel w-full max-w-md p-stack-lg border-primary p-6 m-4">
        <div class="flex justify-between items-center mb-stack-lg mb-6">
            <h3 class="font-headline-md text-headline-md text-primary font-bold mb-0">Contact Support</h3>
            <button class="material-symbols-outlined text-on-surface-variant hover:text-white bg-transparent border-0 text-[24px]" onclick="toggleSupport()">close</button>
        </div>
        <div class="space-y-stack-md space-y-4">
            <p class="font-body-md text-body-md text-on-surface-variant mb-6">Need help with your application? Our merchant support team is available 24/7.</p>
            <div class="p-stack-md bg-surface-container-low border border-outline-variant flex items-center gap-stack-md p-4 rounded-lg gap-4">
                <span class="material-symbols-outlined text-primary text-[28px]">chat_bubble</span>
                <div>
                    <p class="font-label-md text-label-md font-bold mb-1 text-on-surface">Live Chat</p>
                    <p class="font-label-sm text-label-sm text-on-surface-variant mb-0">Average response time: 2 mins</p>
                </div>
            </div>
            <div class="p-stack-md bg-surface-container-low border border-outline-variant flex items-center gap-stack-md p-4 rounded-lg gap-4">
                <span class="material-symbols-outlined text-primary text-[28px]">mail</span>
                <div>
                    <p class="font-label-md text-label-md font-bold mb-1 text-on-surface">Email Support</p>
                    <p class="font-label-sm text-label-sm text-on-surface-variant mb-0">merchants@testshare.com</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php view('partials/seller_footer'); ?>
<script>
    function toggleSupport() {
        const modal = document.getElementById('support-modal');
        modal.classList.toggle('hidden');
    }
</script>
</body>
</html>
