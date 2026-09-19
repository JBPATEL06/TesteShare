<?php
require_once __DIR__ . '/db.php';

function checkAndSeedData($pdo) {
    // Clean old seed data if present
    $hasJulian = $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'julian.rivera@testshare.io'")->fetchColumn();
    if ($hasJulian > 0) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $pdo->exec("TRUNCATE TABLE users;");
        $pdo->exec("TRUNCATE TABLE stores;");
        $pdo->exec("TRUNCATE TABLE billing_subscriptions;");
        $pdo->exec("TRUNCATE TABLE menu_items;");
        $pdo->exec("TRUNCATE TABLE orders;");
        $pdo->exec("TRUNCATE TABLE order_items;");
        $pdo->exec("TRUNCATE TABLE promotional_offers;");
        $pdo->exec("TRUNCATE TABLE reviews;");
        $pdo->exec("TRUNCATE TABLE custom_orders;");
        $pdo->exec("TRUNCATE TABLE payouts_ledger;");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    }

    // 1. Seed Indian Users
    $defaultUsers = [
        [
            'email' => 'customer@testshare.com',
            'fullname' => 'Rohan Malhotra',
            'phone' => '9876543210',
            'password' => 'password123',
            'role' => 'customer',
            'status' => 'Active'
        ],
        [
            'email' => 'seller@testshare.com',
            'fullname' => 'Chef Harpal Singh',
            'phone' => '8765432109',
            'password' => 'password123',
            'role' => 'seller_manager',
            'status' => 'Active'
        ],
        [
            'email' => 'admin@testshare.com',
            'fullname' => 'System Admin',
            'phone' => '7654321098',
            'password' => 'password123',
            'role' => 'super_admin',
            'status' => 'Active'
        ],
        [
            'email' => 'aarav.sharma@testshare.in',
            'fullname' => 'Aarav Sharma',
            'phone' => '9812345670',
            'password' => 'password123',
            'role' => 'customer',
            'status' => 'Active'
        ],
        [
            'email' => 'ananya.patel@gmail.com',
            'fullname' => 'Ananya Patel',
            'phone' => '9812345671',
            'password' => 'password123',
            'role' => 'customer',
            'status' => 'Active'
        ],
        [
            'email' => 'kabir.singh@yahoo.com',
            'fullname' => 'Kabir Singh',
            'phone' => '9812345672',
            'password' => 'password123',
            'role' => 'customer',
            'status' => 'Active'
        ],
        [
            'email' => 'diya.iyer@gmail.com',
            'fullname' => 'Diya Iyer',
            'phone' => '9812345673',
            'password' => 'password123',
            'role' => 'customer',
            'status' => 'Active'
        ],
        [
            'email' => 'rahul.verma@outlook.com',
            'fullname' => 'Rahul Verma',
            'phone' => '9812345674',
            'password' => 'password123',
            'role' => 'customer',
            'status' => 'Suspended'
        ],
        [
            'email' => 'sanjeev@bakery88.in',
            'fullname' => 'Chef Sanjeev Kapoor',
            'phone' => '9924883921',
            'password' => 'password123',
            'role' => 'seller_manager',
            'status' => 'Active'
        ],
        [
            'email' => 'kunal@curryleaves.in',
            'fullname' => 'Chef Kunal Kapur',
            'phone' => '9924883922',
            'password' => 'password123',
            'role' => 'seller_manager',
            'status' => 'Active'
        ],
        [
            'email' => 'owner@pistahouse.com',
            'fullname' => 'Zeeshan Ali',
            'phone' => '9924883923',
            'password' => 'password123',
            'role' => 'seller_manager',
            'status' => 'Active'
        ]
    ];

    foreach ($defaultUsers as $user) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$user['email']]);
        if (!$stmt->fetch()) {
            $hash = password_hash($user['password'], PASSWORD_DEFAULT);
            $ins = $pdo->prepare("INSERT INTO users (fullname, email, phone, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?)");
            $ins->execute([$user['fullname'], $user['email'], $user['phone'], $hash, $user['role'], $user['status']]);
        }
    }

    // 2. Seed Indian Stores
    $stores = [
        [
            'email' => 'seller@testshare.com',
            'store_name' => 'Royal Punjab Grill',
            'category' => 'North Indian & Tandoori',
            'contact_email' => 'contact@royalpunjab.in',
            'contact_phone' => '8765432109',
            'address' => 'Shop 12, Connaught Place, Block E',
            'city' => 'New Delhi',
            'pincode' => '110001',
            'bank_holder_name' => 'Royal Punjab Grill LLP',
            'bank_name' => 'State Bank of India',
            'bank_account_number' => '3009876543210',
            'bank_ifsc' => 'SBIN0001234',
            'onboarding_status' => 'Approved',
            'store_status' => 'Active',
            'gstin' => '07AAAAA1234A1Z5',
            'pan' => 'ABCDE1234F',
            'fssai' => '10023011000456'
        ],
        [
            'email' => 'sanjeev@bakery88.in',
            'store_name' => 'Central Bakery',
            'category' => 'Bakery & Cafe',
            'contact_email' => 'contact@centralbakery.in',
            'contact_phone' => '9924883921',
            'address' => '88 Hill Road, Bandra West',
            'city' => 'Mumbai',
            'pincode' => '400050',
            'bank_holder_name' => 'Central Bakery Corp',
            'bank_name' => 'HDFC Bank',
            'bank_account_number' => '50100987654321',
            'bank_ifsc' => 'HDFC0000060',
            'onboarding_status' => 'Under Review',
            'store_status' => 'Active',
            'gstin' => '27BBBBB1234B1Z5',
            'pan' => 'ABCDE5678G',
            'fssai' => '10024022000819'
        ],
        [
            'email' => 'kunal@curryleaves.in',
            'store_name' => 'Curry Leaves',
            'category' => 'South Indian & Tiffin',
            'contact_email' => 'contact@curryleaves.in',
            'contact_phone' => '9924883922',
            'address' => '99 Nungambakkam High Road',
            'city' => 'Chennai',
            'pincode' => '600034',
            'bank_holder_name' => 'Kunal Kapur Hospitality',
            'bank_name' => 'ICICI Bank',
            'bank_account_number' => '000401987654',
            'bank_ifsc' => 'ICIC0000004',
            'onboarding_status' => 'Under Review',
            'store_status' => 'Active',
            'gstin' => '33CCCCC1234C1Z5',
            'pan' => 'ABCDE9012H',
            'fssai' => '10024099000902'
        ],
        [
            'email' => 'owner@pistahouse.com',
            'store_name' => 'Pista House Biryani',
            'category' => 'Hyderabadi Haleem & Biryani',
            'contact_email' => 'owner@pistahouse.com',
            'contact_phone' => '9924883923',
            'address' => 'Charminar Road, Shalibanda',
            'city' => 'Hyderabad',
            'pincode' => '500002',
            'bank_holder_name' => 'Pista House Corp',
            'bank_name' => 'State Bank of India',
            'bank_account_number' => '200123456789',
            'bank_ifsc' => 'SBIN0004567',
            'onboarding_status' => 'Approved',
            'store_status' => 'Active',
            'gstin' => '36DDDDD1234D1Z5',
            'pan' => 'ABCDE3456I',
            'fssai' => '10024033000123'
        ]
    ];

    foreach ($stores as $s) {
        $stmtOwner = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmtOwner->execute([$s['email']]);
        $owner = $stmtOwner->fetch();
        if ($owner) {
            $stmtStore = $pdo->prepare("SELECT id FROM stores WHERE owner_id = ?");
            $stmtStore->execute([$owner['id']]);
            if (!$stmtStore->fetch()) {
                $insStore = $pdo->prepare("INSERT INTO stores (
                    owner_id, store_name, category, contact_email, contact_phone, 
                    address, city, pincode, bank_holder_name, bank_name, 
                    bank_account_number, bank_ifsc, onboarding_status, store_status,
                    gstin, pan, fssai
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insStore->execute([
                    $owner['id'], $s['store_name'], $s['category'], $s['contact_email'], $s['contact_phone'],
                    $s['address'], $s['city'], $s['pincode'], $s['bank_holder_name'], $s['bank_name'],
                    $s['bank_account_number'], $s['bank_ifsc'], $s['onboarding_status'], $s['store_status'],
                    $s['gstin'], $s['pan'], $s['fssai']
                ]);
            }
        }
    }

    // 3. Seed Promotional Offers
    $offersCount = $pdo->query("SELECT COUNT(*) FROM promotional_offers")->fetchColumn();
    if ($offersCount == 0) {
        $insOffer = $pdo->prepare("INSERT INTO promotional_offers (
            coupon_code, offer_title, offer_description, discount_percentage, 
            min_order_value, admin_subsidy_percentage, merchant_absorb_percentage,
            start_date, end_date, status
        ) VALUES ('SUPER50', 'Super 50% Off Promo', '50% off all orders above $20', 50.00, 20.00, 80.00, 20.00, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 'Active')");
        $insOffer->execute();
    }

    // 4. Seed Orders
    $ordersCount = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    if ($ordersCount == 0) {
        $custStmt = $pdo->prepare("SELECT id FROM users WHERE email = 'aarav.sharma@testshare.in'");
        $custStmt->execute();
        $aarav = $custStmt->fetch();
        $storeStmt = $pdo->prepare("SELECT id FROM stores WHERE store_name = 'Royal Punjab Grill'");
        $storeStmt->execute();
        $rpg = $storeStmt->fetch();

        if ($aarav && $rpg) {
            $insOrder = $pdo->prepare("INSERT INTO orders (customer_id, store_id, total_amount, platform_commission, store_net_amount, payment_status, order_status) VALUES (?, ?, ?, ?, ?, 'Paid', 'Completed')");
            $insOrder->execute([$aarav['id'], $rpg['id'], 100.00, 5.00, 95.00]);
            $insOrder->execute([$aarav['id'], $rpg['id'], 40.00, 2.00, 38.00]);
        }
    }

    // 5. Seed Subscriptions
    $subsCount = $pdo->query("SELECT COUNT(*) FROM billing_subscriptions")->fetchColumn();
    if ($subsCount == 0) {
        $storeStmt = $pdo->query("SELECT id, store_name FROM stores");
        $allStores = $storeStmt->fetchAll();
        $insSub = $pdo->prepare("INSERT INTO billing_subscriptions (store_id, tier, price_per_month, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, 'Active')");
        foreach ($allStores as $st) {
            if ($st['store_name'] === 'Royal Punjab Grill') {
                $insSub->execute([$st['id'], 'Enterprise', 149.00, '2024-01-01', '2026-12-31']);
            } elseif ($st['store_name'] === 'Central Bakery') {
                $insSub->execute([$st['id'], 'Pro', 79.00, '2025-05-15', '2026-08-15']);
            } elseif ($st['store_name'] === 'Curry Leaves') {
                $insSub->execute([$st['id'], 'Basic', 29.00, '2025-07-20', '2026-07-20']);
            } elseif ($st['store_name'] === 'Pista House Biryani') {
                $insSub->execute([$st['id'], 'Basic', 29.00, '2023-06-30', '2026-06-30']);
            }
        }
    }

    // 6. Seed Reviews
    $reviewsCount = $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
    if ($reviewsCount == 0) {
        $custStmt = $pdo->prepare("SELECT id FROM users WHERE email = 'aarav.sharma@testshare.in'");
        $custStmt->execute();
        $aarav = $custStmt->fetch();
        $storeStmt = $pdo->prepare("SELECT id FROM stores WHERE store_name = 'Royal Punjab Grill'");
        $storeStmt->execute();
        $rpg = $storeStmt->fetch();

        if ($aarav && $rpg) {
            $insReview = $pdo->prepare("INSERT INTO reviews (customer_id, store_id, rating_stars, comment_text, helpful_upvotes, is_popular, status) VALUES (?, ?, 5, 'The Butter Chicken and Butter Naan were absolutely delicious! Perfectly cooked and spiced.', 14, 1, 'Public storefront')");
            $insReview->execute([$aarav['id'], $rpg['id']]);
        }
    }

    // 7. Seed Menu Items (Indian Cuisines)
    $menuItemsCount = $pdo->query("SELECT COUNT(*) FROM menu_items")->fetchColumn();
    if ($menuItemsCount == 0) {
        $rpg = $pdo->query("SELECT id FROM stores WHERE store_name = 'Royal Punjab Grill'")->fetch();
        $central = $pdo->query("SELECT id FROM stores WHERE store_name = 'Central Bakery'")->fetch();
        $curry = $pdo->query("SELECT id FROM stores WHERE store_name = 'Curry Leaves'")->fetch();
        $pista = $pdo->query("SELECT id FROM stores WHERE store_name = 'Pista House Biryani'")->fetch();

        $insMenu = $pdo->prepare("INSERT INTO menu_items (store_id, name, description, price, category, image_url, prep_time, ingredients, is_available) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
        
        if ($rpg) {
            $insMenu->execute([$rpg['id'], 'Butter Chicken', 'Tender tandoori chicken cooked in creamy, buttery sweet tomato gravy.', 12.50, 'North Indian', 'images/butter_chicken.png', 25, 'Chicken, Butter, Cream, Tomato Puree, Onion, Garlic, Ginger, Garam Masala, Kashmiri Chilli']);
            $insMenu->execute([$rpg['id'], 'Paneer Tikka Masala', 'Grilled paneer cubes cooked in spicy spiced tomato onion masala gravel.', 10.00, 'North Indian', 'images/paneer_tikka.png', 20, 'Paneer, Tomatoes, Onion, Bell Peppers, Cream, Cumin, Coriander, Fenugreek Leaves']);
        }
        if ($central) {
            $insMenu->execute([$central['id'], 'Mango Lassi Shake', 'Creamy blend of yogurt and sweet Alphonso mangoes, served chilled.', 4.00, 'Drinks', 'images/mango_lassi.png', 5, 'Alphonso Mango Pulp, Full-Fat Yogurt, Sugar, Cardamom, Saffron, Ice']);
            $insMenu->execute([$central['id'], 'Veg Hakka Noodles', 'Indo-Chinese street style stir fried noodles with crisp fresh vegetables.', 6.00, 'Appetizers', 'images/hakka_noodles.png', 15, 'Noodles, Cabbage, Carrot, Capsicum, Spring Onion, Soy Sauce, Vinegar, Garlic']);
        }
        if ($curry) {
            $insMenu->execute([$curry['id'], 'Masala Dosa', 'Thin crispy golden rice crepe filled with spiced potato masala.', 5.00, 'South Indian', 'images/masala_dosa.png', 20, 'Rice Batter, Urad Dal, Potato, Onion, Mustard Seeds, Curry Leaves, Green Chilli, Turmeric']);
            $insMenu->execute([$curry['id'], 'Filter Coffee', 'Traditional South Indian filter coffee whipped with hot frothy milk.', 2.50, 'Drinks', 'images/filter_coffee.png', 5, 'Coffee Powder, Chicory, Full-Fat Milk, Sugar']);
        }
        if ($pista) {
            $insMenu->execute([$pista['id'], 'Hyderabadi Chicken Biryani', 'Layered basmati rice and marinated chicken cooked dum-style with spices.', 9.50, 'Biryani', 'images/chicken_biryani.png', 40, 'Basmati Rice, Chicken, Yogurt, Fried Onions, Saffron, Whole Spices, Mint, Ghee']);
            $insMenu->execute([$pista['id'], 'Double Ka Meetha', 'Golden bread pieces soaked in sweet cardamom saffron syrup and rich rabri.', 4.00, 'Dessert', 'images/double_ka_meetha.png', 30, 'Bread, Milk, Khoya, Sugar, Cardamom, Saffron, Cashews, Almonds, Raisins']);
        }
    }
}

$pdo = getDB();
checkAndSeedData($pdo);
echo "Database seeded.\n";
