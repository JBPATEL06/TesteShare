<?php
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Personal Info
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // 2. Store Info
    $store_name = trim($_POST['store_name'] ?? '');
    $business_category = trim($_POST['business_category'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');

    if ($fullname && $email && $phone && $password && $store_name && $address) {
        $db = getDB();
        try {
            $check = $db->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $error = "Email address is already in use.";
            } else {
                $db->beginTransaction();

                // Create user as seller_manager
                $passHash = password_hash($password, PASSWORD_DEFAULT);
                $insUser = $db->prepare("INSERT INTO users (fullname, email, password_hash, phone, role, status) VALUES (?, ?, ?, ?, 'seller_manager', 'Active')");
                $insUser->execute([$fullname, $email, $passHash, $phone]);
                $ownerId = $db->lastInsertId();

                // Create store
                $insStore = $db->prepare("
                    INSERT INTO stores (
                        owner_id, store_name, category, contact_email, contact_phone, 
                        address, city, pincode, bank_holder_name, bank_name, 
                        bank_account_number, bank_ifsc, onboarding_status, store_status, subscription_tier
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Under Review', 'Active', 'Starter')
                ");
                $insStore->execute([
                    $ownerId, 
                    $store_name, 
                    $business_category ?: 'General Restaurant', 
                    $email, 
                    $phone, 
                    $address, 
                    $city ?: 'New York', 
                    $pincode ?: '10001', 
                    $fullname, 
                    'Default Settlement Bank', 
                    '000000000000', 
                    'BANK0000000'
                ]);
                $storeId = $db->lastInsertId();

                $db->commit();

                // Log user in
                $_SESSION['user_id'] = $ownerId;
                $_SESSION['fullname'] = $fullname;
                $_SESSION['role'] = 'seller_manager';
                $_SESSION['email'] = $email;
                $_SESSION['store_id'] = $storeId;

                header("Location: " . url('seller/pending_approval'));
                exit;
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = "Registration failed: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Merchant Onboarding | TestShare</title>
    <!-- Bootstrap 5 CSS -->
    <link href="<?php echo asset('css/bootstrap.min.css'); ?>" rel="stylesheet">
    <!-- Tailwind CDN -->
    <script src="<?php echo asset('js/tailwind.min.js'); ?>"></script>
    <link href="<?php echo asset('css/google-fonts.css'); ?>" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="css/custom.css" rel="stylesheet">
    <!-- Tailwind Configuration -->
    <script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            "colors": {
                    "primary": "#ffc688",
                    "outline": "#a28d7a",
                    "on-tertiary": "#003547",
                    "secondary-fixed-dim": "#c8c6c5",
                    "tertiary-fixed": "#c0e8ff",
                    "surface-container-low": "#1c1b1b",
                    "surface-container": "#201f1f",
                    "primary-fixed-dim": "#ffb868",
                    "surface-tint": "#ffb868",
                    "on-tertiary-fixed-variant": "#004d66",
                    "on-secondary-container": "#b7b5b4",
                    "tertiary": "#90daff",
                    "secondary-container": "#474746",
                    "on-secondary-fixed": "#1b1b1c",
                    "inverse-on-surface": "#313030",
                    "secondary-fixed": "#e5e2e1",
                    "on-primary-fixed": "#2b1700",
                    "secondary": "#c8c6c5",
                    "surface-dim": "#131313",
                    "inverse-surface": "#e5e2e1",
                    "on-surface": "#e5e2e1",
                    "on-surface-variant": "#dac3ad",
                    "on-secondary-fixed-variant": "#474746",
                    "error-container": "#93000a",
                    "on-error-container": "#ffdad6",
                    "surface-container-lowest": "#0e0e0e",
                    "outline-variant": "#544434",
                    "background": "#131313",
                    "tertiary-fixed-dim": "#70d2ff",
                    "primary-fixed": "#ffddbb",
                    "primary-container": "#ff9f0d",
                    "inverse-primary": "#885200",
                    "tertiary-container": "#00c3fd",
                    "surface": "#131313",
                    "on-error": "#690005",
                    "error": "#ffb4ab",
                    "on-background": "#e5e2e1",
                    "on-primary-container": "#673d00",
                    "on-primary": "#482900",
                    "surface-container-highest": "#353534",
                    "on-primary-fixed-variant": "#673d00",
                    "on-tertiary-container": "#004d66",
                    "on-secondary": "#3030-30",
                    "surface-variant": "#353534",
                    "surface-container-high": "#2a2a2a",
                    "on-tertiary-fixed": "#001e2b",
                    "surface-bright": "#393939"
            },
            "borderRadius": {
                    "DEFAULT": "0.125rem",
                    "lg": "0.25rem",
                    "xl": "0.5rem",
                    "full": "0.75rem"
            },
            "spacing": {
                    "stack-md": "12px",
                    "gutter": "16px",
                    "base": "8px",
                    "stack-sm": "4px",
                    "stack-lg": "24px",
                    "container-margin": "20px",
                    "section-gap": "48px"
            },
            "fontFamily": {
                    "body-lg": ["Inter"],
                    "body-md": ["Inter"],
                    "label-md": ["Inter"],
                    "headline-md": ["Inter"],
                    "label-sm": ["Inter"],
                    "headline-xl": ["Inter"],
                    "headline-lg": ["Inter"]
            },
            "fontSize": {
                    "body-lg": ["18px", {"lineHeight": "28px", "fontWeight": "400"}],
                    "body-md": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                    "label-md": ["14px", {"lineHeight": "20px", "letterSpacing": "0.05em", "fontWeight": "600"}],
                    "headline-md": ["24px", {"lineHeight": "32px", "fontWeight": "600"}],
                    "label-sm": ["12px", {"lineHeight": "16px", "fontWeight": "500"}],
                    "headline-xl": ["40px", {"lineHeight": "48px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                    "headline-lg": ["32px", {"lineHeight": "40px", "letterSpacing": "-0.01em", "fontWeight": "700"}]
            }
          },
        },
      }
    </script>
    <style>
        body {
            background-color: #131313;
            color: #e5e2e1;
            font-family: 'Inter', sans-serif;
            margin: 0;
            overflow-x: hidden;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #ff9f0d !important;
            box-shadow: 0 0 0 2px rgba(255, 159, 13, 0.2);
        }

        /* Custom scrollbar for dark theme */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #131313;
        }
        ::-webkit-scrollbar-thumb {
            background: #2d2d2d;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #353534;
        }

        .step-active {
            color: #ff9f0d;
            border-color: #ff9f0d;
        }
        .step-complete {
            color: #ff9f0d;
            background-color: rgba(255, 159, 13, 0.1);
        }
    </style>
</head>
<body class="bg-background text-on-surface min-h-screen flex flex-col">
    <!-- TopNavBar (Simplified for Onboarding) -->
    <header class="w-full h-16 sticky top-0 z-40 flex justify-between items-center px-container-margin bg-surface border-b border-outline-variant">
        <div class="flex items-center gap-base">
            <a href="<?php echo url('user/home'); ?>" class="text-decoration-none"><span class="font-headline-md text-headline-md font-bold text-primary-container">TestShare</span></a>
            <div class="h-4 w-px bg-outline-variant mx-2"></div>
            <span class="font-label-md text-label-md text-on-surface-variant">Merchant Onboarding</span>
        </div>
        <div class="flex items-center gap-stack-lg">
            <button class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors bg-transparent border-0">Save Draft</button>
            <button class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors flex items-center gap-1 bg-transparent border-0">
                <span class="material-symbols-outlined text-[18px]">help</span>
                Support
            </button>
        </div>
    </header>
    <main class="flex min-h-[calc(100vh-64px)] flex-grow">
        <!-- Left Sidebar: Progress & Benefits -->
        <aside class="hidden lg:flex flex-col w-80 bg-surface-container border-r border-outline-variant py-stack-lg px-container-margin sticky top-16 h-[calc(100vh-64px)] overflow-y-auto">
            <div class="space-y-stack-lg flex-grow">
                <h2 class="font-label-md text-label-md text-primary uppercase tracking-widest mb-4">Application Progress</h2>
                <nav class="space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full border-2 border-primary-container flex items-center justify-center text-on-primary-container bg-primary-container font-bold text-label-sm">1</div>
                        <span class="font-label-md text-label-md text-on-surface">Personal Information</span>
                    </div>
                    <div class="ml-4 h-8 border-l-2 border-outline-variant"></div>
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full border-2 border-outline-variant flex items-center justify-center text-on-surface-variant font-bold text-label-sm">2</div>
                        <span class="font-label-md text-label-md text-on-surface-variant">Store Details</span>
                    </div>
                    <div class="ml-4 h-8 border-l-2 border-outline-variant"></div>
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full border-2 border-outline-variant flex items-center justify-center text-on-surface-variant font-bold text-label-sm">3</div>
                        <span class="font-label-md text-label-md text-on-surface-variant">Business Verification</span>
                    </div>
                    <div class="ml-4 h-8 border-l-2 border-outline-variant"></div>
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full border-2 border-outline-variant flex items-center justify-center text-on-surface-variant font-bold text-label-sm">4</div>
                        <span class="font-label-md text-label-md text-on-surface-variant">Bank Details &amp; Uploads</span>
                    </div>
                </nav>
                <div class="mt-section-gap pt-stack-lg border-t border-outline-variant">
                    <h3 class="font-label-md text-label-md text-primary uppercase tracking-widest mb-4">Why Partner with us?</h3>
                    <div class="space-y-6">
                        <div class="flex gap-3">
                            <span class="material-symbols-outlined text-primary">public</span>
                            <div>
                                <p class="font-label-md text-label-md text-on-surface mb-0">Global Reach</p>
                                <p class="text-label-sm font-body-md text-on-surface-variant mt-1 mb-0">Access millions of customers in your city instantly.</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <span class="material-symbols-outlined text-primary">payments</span>
                            <div>
                                <p class="font-label-md text-label-md text-on-surface mb-0">Secure Payments</p>
                                <p class="text-label-sm font-body-md text-on-surface-variant mt-1 mb-0">Reliable weekly settlements directly to your bank account.</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <span class="material-symbols-outlined text-primary">insights</span>
                            <div>
                                <p class="font-label-md text-label-md text-on-surface mb-0">Marketing Tools</p>
                                <p class="text-label-sm font-body-md text-on-surface-variant mt-1 mb-0">Advanced analytics to grow your orders and brand.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-auto p-4 bg-surface-container-high border border-outline-variant rounded-lg mt-4">
                <p class="font-label-sm text-label-sm text-on-surface-variant italic mb-0">"TestShare helped us double our daily volume in just 3 months."</p>
                <div class="flex items-center gap-2 mt-3">
                    <div class="w-6 h-6 rounded-full overflow-hidden">
                        <img class="w-full h-full object-cover" data-alt="Close-up headshot of a smiling chef in a clean professional black chef's jacket against a dark, minimalist kitchen background with soft bokeh lighting." src="<?php echo asset('images/chef_headshot.png'); ?>">
                    </div>
                    <span class="text-label-sm font-label-sm text-primary">— Mario, Bistro 88</span>
                </div>
            </div>
        </aside>
        <!-- Right Content Area: Form -->
        <section class="flex-grow bg-background py-stack-lg px-container-margin lg:px-section-gap overflow-y-auto py-8">
            <div class="max-w-4xl mx-auto">
                <div class="mb-stack-lg mb-8">
                    <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Let's set up your store</h1>
                    <p class="font-body-md text-body-md text-on-surface-variant mt-2 mb-0">Fill in your details to begin your merchant journey. This process takes about 10 minutes.</p>
                </div>
                <form class="space-y-section-gap space-y-12" method="POST" action="">
                    <?php if ($error): ?>
                        <div class="bg-error-container/20 border border-error-container text-error rounded-xl p-3 text-xs text-center font-bold">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    <!-- Section 1: Personal Information -->
                    <div class="p-gutter bg-surface-container border border-outline-variant rounded-lg p-6">
                        <div class="flex items-center gap-2 mb-gutter mb-6">
                            <span class="material-symbols-outlined text-primary">person</span>
                            <h3 class="font-headline-md text-headline-md font-bold mb-0">Personal Information</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-gutter gap-6">
                            <div class="flex flex-col gap-base gap-2">
                                <label class="font-label-md text-label-md text-on-surface font-bold">Full Name</label>
                                <input class="bg-background border border-outline-variant text-on-surface px-4 py-3 rounded-lg font-body-md" placeholder="John Doe" name="fullname" type="text" required>
                            </div>
                            <div class="flex flex-col gap-base gap-2">
                                <label class="font-label-md text-label-md text-on-surface font-bold">Email Address</label>
                                <input class="bg-background border border-outline-variant text-on-surface px-4 py-3 rounded-lg font-body-md" placeholder="john@example.com" name="email" type="email" required>
                            </div>
                            <div class="flex flex-col gap-base gap-2">
                                <label class="font-label-md text-label-md text-on-surface font-bold">Contact Number</label>
                                <div class="flex">
                                    <span class="bg-surface-container-high border border-outline-variant border-r-0 text-on-surface-variant px-4 py-3 rounded-l-lg font-body-md flex items-center">+91</span>
                                    <input class="flex-grow bg-background border border-outline-variant text-on-surface px-4 py-3 rounded-r-lg font-body-md" placeholder="9876543210" name="phone" type="tel" required>
                                </div>
                            </div>
                            <div class="flex flex-col gap-base gap-2">
                                <label class="font-label-md text-label-md text-on-surface font-bold">Password</label>
                                <input class="bg-background border border-outline-variant text-on-surface px-4 py-3 rounded-lg font-body-md" placeholder="••••••••" name="password" type="password" required>
                            </div>
                        </div>
                    </div>
                    <!-- Section 2: Store Details & Location -->
                    <div class="p-gutter bg-surface-container border border-outline-variant rounded-lg p-6">
                        <div class="flex items-center gap-2 mb-gutter mb-6">
                            <span class="material-symbols-outlined text-primary">storefront</span>
                            <h3 class="font-headline-md text-headline-md font-bold mb-0">Store &amp; Location Details</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-gutter gap-6">
                            <div class="flex flex-col gap-base gap-2">
                                <label class="font-label-md text-label-md text-on-surface font-bold">Store Name</label>
                                <input class="bg-background border border-outline-variant text-on-surface px-4 py-3 rounded-lg font-body-md" placeholder="The Urban Grill" name="store_name" type="text" required>
                            </div>
                            <div class="flex flex-col gap-base gap-2">
                                <label class="font-label-md text-label-md text-on-surface font-bold">Business Category</label>
                                <select class="bg-background border border-outline-variant text-on-surface px-4 py-3 rounded-lg font-body-md text-on-surface" name="business_category" required>
                                    <option value="">Select Category</option>
                                    <option value="restaurant">Restaurant</option>
                                    <option value="grocery">Grocery</option>
                                    <option value="bakery">Bakery</option>
                                    <option value="pharmacy">Pharmacy</option>
                                </select>
                            </div>
                            <div class="flex flex-col gap-base gap-2 md:col-span-2">
                                <label class="font-label-md text-label-md text-on-surface font-bold">Full Address</label>
                                <textarea class="bg-background border border-outline-variant text-on-surface px-4 py-3 rounded-lg font-body-md" placeholder="123 High Street, Downtown" name="address" rows="3" required></textarea>
                            </div>
                            <div class="flex flex-col gap-base gap-2">
                                <label class="font-label-md text-label-md text-on-surface font-bold">City</label>
                                <input class="bg-background border border-outline-variant text-on-surface px-4 py-3 rounded-lg font-body-md" placeholder="Bangalore" name="city" type="text" required>
                            </div>
                            <div class="flex flex-col gap-base gap-2">
                                <label class="font-label-md text-label-md text-on-surface font-bold">Pincode / Zip Code</label>
                                <input class="bg-background border border-outline-variant text-on-surface px-4 py-3 rounded-lg font-body-md" placeholder="560001" name="pincode" type="text" required>
                            </div>
                        </div>
                    </div>
                    <!-- Section 3: Business Verification -->
                    <div class="p-gutter bg-surface-container border border-outline-variant rounded-lg p-6">
                        <div class="flex items-center gap-2 mb-gutter mb-6">
                            <span class="material-symbols-outlined text-primary">verified</span>
                            <h3 class="font-headline-md text-headline-md font-bold mb-0">Business Verification</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-gutter gap-6">
                            <div class="flex flex-col gap-base gap-2">
                                <label class="font-label-md text-label-md text-on-surface font-bold">GSTIN / Tax ID</label>
                                <input class="bg-background border border-outline-variant text-on-surface px-4 py-3 rounded-lg font-body-md" placeholder="22AAAAA0000A1Z5" name="gstin" type="text">
                            </div>
                            <div class="flex flex-col gap-base gap-2">
                                <label class="font-label-md text-label-md text-on-surface font-bold">PAN Number</label>
                                <input class="bg-background border border-outline-variant text-on-surface px-4 py-3 rounded-lg font-body-md" placeholder="ABCDE1234F" name="pan_number" type="text">
                            </div>
                            <div class="flex flex-col gap-base gap-2 md:col-span-2">
                                <label class="font-label-md text-label-md text-on-surface font-bold">FSSAI License Number (For Food Businesses)</label>
                                <input class="bg-background border border-outline-variant text-on-surface px-4 py-3 rounded-lg font-body-md" placeholder="100XXXXXXXXXXX" name="fssai_number" type="text">
                            </div>
                        </div>
                    </div>
                    <!-- Section 4: Bank Details -->
                    <div class="p-gutter bg-surface-container border border-outline-variant rounded-lg p-6">
                        <div class="flex items-center gap-2 mb-gutter mb-6">
                            <span class="material-symbols-outlined text-primary">account_balance</span>
                            <h3 class="font-headline-md text-headline-md font-bold mb-0">Bank Details</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-gutter gap-6">
                            <div class="flex flex-col gap-base gap-2 md:col-span-2">
                                <label class="font-label-md text-label-md text-on-surface font-bold">Account Holder Name</label>
                                <input class="bg-background border border-outline-variant text-on-surface px-4 py-3 rounded-lg font-body-md" placeholder="The Urban Grill PVT LTD" name="bank_account_name" type="text">
                            </div>
                            <div class="flex flex-col gap-base gap-2">
                                <label class="font-label-md text-label-md text-on-surface font-bold">Account Number</label>
                                <input class="bg-background border border-outline-variant text-on-surface px-4 py-3 rounded-lg font-body-md" placeholder="••••••••••••" name="bank_account_number" type="text">
                            </div>
                            <div class="flex flex-col gap-base gap-2">
                                <label class="font-label-md text-label-md text-on-surface font-bold">IFSC Code</label>
                                <input class="bg-background border border-outline-variant text-on-surface px-4 py-3 rounded-lg font-body-md" placeholder="HDFC0001234" name="bank_ifsc" type="text">
                            </div>
                        </div>
                    </div>
                    <!-- Section 5: Uploads -->
                    <div class="p-gutter bg-surface-container border border-outline-variant rounded-lg p-6">
                        <div class="flex items-center gap-2 mb-gutter mb-6">
                            <span class="material-symbols-outlined text-primary">cloud_upload</span>
                            <h3 class="font-headline-md text-headline-md font-bold mb-0">Upload Documents</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-gutter gap-6">
                            <!-- Store Logo -->
                            <div class="flex flex-col gap-base gap-2 items-center justify-center border-2 border-dashed border-outline-variant p-gutter p-6 rounded-lg hover:border-primary transition-colors cursor-pointer group">
                                <span class="material-symbols-outlined text-4xl text-on-surface-variant group-hover:text-primary text-[36px]">image</span>
                                <span class="font-label-md text-label-md text-center font-bold">Store Logo</span>
                                <span class="text-label-sm text-on-surface-variant text-center">JPG, PNG (Max 2MB)</span>
                            </div>
                            <!-- Identity Proof -->
                            <div class="flex flex-col gap-base gap-2 items-center justify-center border-2 border-dashed border-outline-variant p-gutter p-6 rounded-lg hover:border-primary transition-colors cursor-pointer group">
                                <span class="material-symbols-outlined text-4xl text-on-surface-variant group-hover:text-primary text-[36px]">badge</span>
                                <span class="font-label-md text-label-md text-center font-bold">Identity Proof</span>
                                <span class="text-label-sm text-on-surface-variant text-center">PDF, JPG (Aadhar/PAN)</span>
                            </div>
                            <!-- Business License -->
                            <div class="flex flex-col gap-base gap-2 items-center justify-center border-2 border-dashed border-outline-variant p-gutter p-6 rounded-lg hover:border-primary transition-colors cursor-pointer group">
                                <span class="material-symbols-outlined text-4xl text-on-surface-variant group-hover:text-primary text-[36px]">description</span>
                                <span class="font-label-md text-label-md text-center font-bold">Business License</span>
                                <span class="text-label-sm text-on-surface-variant text-center">PDF, JPG (FSSAI/GST)</span>
                            </div>
                        </div>
                    </div>
                    <!-- Final Submission -->
                    <div class="flex flex-col md:flex-row items-center justify-between gap-gutter pt-gutter pt-6 border-t border-outline-variant gap-6">
                        <div class="flex items-center gap-3">
                            <input class="w-5 h-5 rounded border-outline-variant bg-background text-primary focus:ring-primary-container" id="terms" type="checkbox" required>
                            <label class="text-label-sm font-body-md text-on-surface-variant mb-0" for="terms">I agree to the <a class="text-primary hover:underline" href="#">Terms &amp; Conditions</a> and <a class="text-primary hover:underline" href="#">Merchant Policy</a>.</label>
                        </div>
                        <div class="flex gap-4 w-full md:w-auto">
                            <button onclick="window.location.href='<?php echo url('user/home'); ?>'" class="flex-grow md:flex-none px-gutter px-6 py-3 rounded-lg border border-white text-on-surface font-label-md hover:bg-surface-container-highest transition-all font-bold bg-transparent" type="button">Cancel</button>
                            <button class="flex-grow md:flex-none px-stack-lg px-8 py-3 rounded-lg bg-primary-container text-on-primary-fixed font-label-md font-bold hover:opacity-90 active:scale-95 transition-all border-0 font-bold" type="submit">Submit Application</button>
                        </div>
                    </div>
                </form>
                <!-- Footer -->
                <footer class="mt-section-gap mt-16 py-8 border-t border-outline-variant flex flex-col md:flex-row justify-between items-center gap-4 text-on-surface-variant">
                    <p class="font-label-sm text-label-sm mb-0">© 2024 TestShare Merchant Portal. All rights reserved.</p>
                    <div class="flex gap-stack-lg gap-6 font-label-sm text-label-sm">
                        <a class="hover:text-primary text-decoration-none text-on-surface-variant" href="#">Privacy Policy</a>
                        <a class="hover:text-primary text-decoration-none text-on-surface-variant" href="#">Terms of Service</a>
                        <a class="hover:text-primary text-decoration-none text-on-surface-variant" href="#">Merchant Support</a>
                    </div>
                </footer>
            </div>
        </section>
    </main>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="<?php echo asset('js/bootstrap.bundle.min.js'); ?>"></script>
    <!-- Custom Main JS -->
    <script src="js/main.js"></script>
    <script>
        // Micro-interactions for form focus
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('focus', () => {
                input.parentElement.querySelector('label')?.classList.add('text-primary');
            });
            input.addEventListener('blur', () => {
                input.parentElement.querySelector('label')?.classList.remove('text-primary');
            });
        });
    </script>
</body>
</html>
