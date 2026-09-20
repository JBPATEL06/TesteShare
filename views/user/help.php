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

$pageTitle = 'Help & Support Center';
$activeNav = 'help';
view('partials/user_header', get_defined_vars());
?>

<style>
    .help-card {
        background-color: #1e1e1e !important;
        border: 1px solid #333333 !important;
        transition: all 0.2s ease-in-out;
    }
    .help-card:hover {
        border-color: #ff9f0d !important;
        transform: translateY(-2px);
    }
    
    .faq-container {
        background-color: #1c1b1b;
        border: 1px solid #333333;
        border-radius: 12px;
        overflow: hidden;
    }
    .faq-item {
        border-bottom: 1px solid #2d2d2d;
    }
    .faq-item:last-child {
        border-bottom: none;
    }
    .faq-trigger {
        width: 100%;
        background-color: #242424;
        color: #ffffff;
        padding: 18px 24px;
        text-align: left;
        font-weight: 600;
        font-size: 1.05rem;
        border: none;
        outline: none;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        transition: background-color 0.2s ease, color 0.2s ease;
    }
    .faq-trigger:hover {
        background-color: #2d2d2d;
        color: #ff9f0d;
    }
    .faq-trigger.active {
        background-color: #2a2a2a;
        color: #ff9f0d;
    }
    .faq-icon {
        transition: transform 0.3s ease;
        color: #ff9f0d;
    }
    .faq-trigger.active .faq-icon {
        transform: rotate(180deg);
    }
    .faq-content {
        display: none;
        background-color: #161616;
        padding: 20px 24px;
        border-top: 1px solid #2a2a2a;
    }
    .faq-content.show {
        display: block;
    }
    .faq-content p {
        color: #ffffff !important;
        font-size: 0.98rem !important;
        line-height: 1.7 !important;
        margin: 0 !important;
        opacity: 1 !important;
    }
</style>

<main class="container-xl px-4 py-5 mb-5 text-light">
    <!-- Hero Header -->
    <section class="text-center py-5 mb-5 rounded-4 p-4 p-md-5 border" style="background: linear-gradient(135deg, #1e1e1e 0%, #282828 100%); border-color: #333333 !important;">
        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill font-mono mb-3 fs-6 fw-bold">24/7 Support Center</span>
        <h1 class="display-5 fw-bold text-light mb-3">How can we help you today?</h1>
        <p class="text-light max-w-2xl mx-auto lead" style="color: #f1f5f9 !important;">Find quick answers to common questions about orders, payments, delivery radius, and catering requests on TestShare.</p>
    </section>

    <!-- FAQ Cards Grid -->
    <section class="row g-4 mb-5">
        <div class="col-md-6 col-lg-4">
            <div class="card help-card h-100 rounded-3 p-4 shadow-sm">
                <div class="w-12 h-12 rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center mb-3" style="width: 48px; height: 48px;">
                    <span class="material-symbols-outlined fs-3">local_shipping</span>
                </div>
                <h5 class="fw-bold mb-2 text-light">Delivery & Pincodes</h5>
                <p class="small mb-0" style="color: #cbd5e1 !important;">Learn how our pincode-based delivery validation works and why orders are matched directly against restaurant service pincodes.</p>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="card help-card h-100 rounded-3 p-4 shadow-sm">
                <div class="w-12 h-12 rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center mb-3" style="width: 48px; height: 48px;">
                    <span class="material-symbols-outlined fs-3">payments</span>
                </div>
                <h5 class="fw-bold mb-2 text-light">Payments & Refunds</h5>
                <p class="small mb-0" style="color: #cbd5e1 !important;">Instant updates on order cancellations, automated refund receipts, and merchant subsidy promo codes.</p>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="card help-card h-100 rounded-3 p-4 shadow-sm">
                <div class="w-12 h-12 rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center mb-3" style="width: 48px; height: 48px;">
                    <span class="material-symbols-outlined fs-3">skillet</span>
                </div>
                <h5 class="fw-bold mb-2 text-light">Custom Bulk Catering</h5>
                <p class="small mb-0" style="color: #cbd5e1 !important;">How to request custom prices for large events, counter-offer with chefs, and track negotiation status in real-time.</p>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="max-w-4xl mx-auto">
        <h3 class="fw-bold mb-4 text-center text-light">Frequently Asked Questions</h3>
        
        <div class="faq-container shadow-lg">
            <div class="faq-item">
                <button class="faq-trigger active" onclick="toggleFaq(this)">
                    <span>How does Pincode Delivery validation work?</span>
                    <span class="material-symbols-outlined faq-icon">expand_more</span>
                </button>
                <div class="faq-content show">
                    <p>
                        TestShare verifies delivery availability by matching your delivery address pincode with the restaurant's operational pincode. If your delivery pincode matches the restaurant's pincode, your order is permitted regardless of distance coordinates.
                    </p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-trigger" onclick="toggleFaq(this)">
                    <span>How do I place a Custom Catering order?</span>
                    <span class="material-symbols-outlined faq-icon">expand_more</span>
                </button>
                <div class="faq-content">
                    <p>
                        Visit any restaurant storefront on TestShare, click the "Custom Order" button, enter your guest count, budget, and dietary preferences. The restaurant manager will respond with a custom quotation or counter-offer in your dashboard.
                    </p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-trigger" onclick="toggleFaq(this)">
                    <span>How are merchant subscriptions structured?</span>
                    <span class="material-symbols-outlined faq-icon">expand_more</span>
                </button>
                <div class="faq-content">
                    <p>
                        Merchants can upgrade between Starter (5% platform fee), Premium (2% fee), and Ultra Premium (0% fee + B2B Raw Material Marketplace access) with automated prorated credit calculations and zero setup cost.
                    </p>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
function toggleFaq(button) {
    const content = button.nextElementSibling;
    const isActive = button.classList.contains('active');
    
    // Close all other items
    document.querySelectorAll('.faq-trigger').forEach(btn => {
        btn.classList.remove('active');
        btn.nextElementSibling.classList.remove('show');
    });
    
    // Toggle clicked item
    if (!isActive) {
        button.classList.add('active');
        content.classList.add('show');
    }
}
</script>

<?php view('partials/user_footer'); ?>

