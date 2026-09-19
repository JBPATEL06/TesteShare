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
$userId = $_SESSION['user_id'] ?? 0;

// Haversine formula distance calculation in kilometers
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return null;
    $earthRadius = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return round($earthRadius * $c, 1);
}

// Handle set default location/address
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_default_location') {
    $addressId = intval($_POST['address_id'] ?? 0);
    if ($userId && $addressId) {
        $db->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
        $updDef = $db->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?");
        $updDef->execute([$addressId, $userId]);
        $_SESSION['location_msg'] = "Default delivery location updated successfully!";
    }
    header("Location: " . url('user/location'));
    exit;
}

// Handle location/address delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_location') {
    $addressId = intval($_POST['address_id'] ?? 0);
    if ($userId && $addressId) {
        $del = $db->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
        $del->execute([$addressId, $userId]);
        
        // Promote newest remaining address as default if default address was deleted
        $checkDef = $db->prepare("SELECT id FROM user_addresses WHERE user_id = ? AND is_default = 1");
        $checkDef->execute([$userId]);
        if (!$checkDef->fetchColumn()) {
            $db->prepare("UPDATE user_addresses SET is_default = 1 WHERE user_id = ? ORDER BY id DESC LIMIT 1")->execute([$userId]);
        }
        
        $_SESSION['location_msg'] = "Location deleted successfully!";
    }
    header("Location: " . url('user/location'));
    exit;
}

// Handle location/address save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_location') {
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $city = trim($_POST['city'] ?? 'New York');
    $pincode = trim($_POST['pincode'] ?? '10001');
    $label = trim($_POST['address_label'] ?? 'Home');
    $lat = floatval($_POST['lat'] ?? 40.7128);
    $lng = floatval($_POST['lng'] ?? -74.0060);

    if ($userId && $address_line1) {
        $db->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
        $ins = $db->prepare("INSERT INTO user_addresses (user_id, label, address_line1, city, state, zip_code, lat, lng, is_default) VALUES (?, ?, ?, ?, 'NY', ?, ?, ?, 1)");
        $ins->execute([$userId, $label, $address_line1, $city, $pincode, $lat, $lng]);
        $_SESSION['user_location'] = "$address_line1, $city";
    }
    header("Location: " . url('user/location'));
    exit;
}

// Load saved addresses
$stmtAddrs = $db->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$stmtAddrs->execute([$userId]);
$addresses = $stmtAddrs->fetchAll();

// Determine active customer coordinates
$userLat = 40.7128;
$userLng = -74.0060;
$locationCity = null;
if (!empty($addresses)) {
    $userLat = floatval($addresses[0]['lat'] ?? 40.7128);
    $userLng = floatval($addresses[0]['lng'] ?? -74.0060);
    $locationCity = $addresses[0]['city'];
}

// Fetch all active approved stores and compute exact distance
$stmtStores = $db->query("
    SELECT s.id, s.store_name, s.category, s.address, s.city, s.lat, s.lng, s.delivery_radius, s.store_logo,
           IFNULL(AVG(r.rating_stars), 5.0) as avg_rating,
           COUNT(DISTINCT r.id) as reviews_count,
           (SELECT COUNT(*) FROM promotional_offers po WHERE po.store_id = s.id AND po.status = 'Active' AND po.end_date >= NOW()) as active_offers
    FROM stores s
    LEFT JOIN reviews r ON s.id = r.store_id
    WHERE s.store_status = 'Active' AND s.onboarding_status = 'Approved'
    GROUP BY s.id
");
$allStores = $stmtStores->fetchAll();

foreach ($allStores as &$st) {
    $stLat = floatval($st['lat'] ?? 40.7128);
    $stLng = floatval($st['lng'] ?? -74.0060);
    $stRadius = floatval($st['delivery_radius'] ?? 5.0);
    
    $dist = calculateDistance($userLat, $userLng, $stLat, $stLng);
    $st['distance_km'] = $dist;
    $st['in_radius'] = ($dist !== null) ? ($dist <= $stRadius) : true;
}
unset($st);

// Sort stores by geographic proximity
usort($allStores, function($a, $b) {
    return ($a['distance_km'] ?? 999) <=> ($b['distance_km'] ?? 999);
});
$nearestStores = array_slice($allStores, 0, 6);

$pageTitle = 'Select Delivery Location';
$activeNav = 'explore';
view('partials/user_header', get_defined_vars());
?>

<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<main class="container-xl py-5 px-4" style="min-height: 80vh; background-color: #131313;">
    <div class="max-w-5xl mx-auto space-y-8">

        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-outline-variant pb-4">
            <div>
                <h2 class="font-headline-md text-headline-md text-on-surface font-bold mb-1">Delivery Geolocation &amp; Address Pin</h2>
                <p class="text-body-sm text-on-surface-variant mb-0">Set your precise delivery pin to discover nearby restaurants operating within your delivery range.</p>
            </div>
            <button type="button" onclick="detectUserGPS()" class="px-4 py-2 bg-primary/10 text-primary border border-primary/30 rounded-xl text-xs font-bold flex items-center gap-2 hover:bg-primary/20 cursor-pointer transition-all shrink-0">
                <span class="material-symbols-outlined text-base">my_location</span>
                <span>Detect My Location</span>
            </button>
        </div>

        <?php if (!empty($_SESSION['location_msg'])): ?>
            <div class="p-3 bg-green-500/10 border border-green-500/30 text-green-400 text-xs font-bold rounded-xl flex items-center gap-2">
                <span class="material-symbols-outlined text-base">check_circle</span>
                <span><?php echo htmlspecialchars($_SESSION['location_msg']); unset($_SESSION['location_msg']); ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch">
            <!-- Left Column: Map & Address Input -->
            <div class="lg:col-span-7 bg-surface-container-low border border-outline-variant p-6 rounded-2xl flex flex-col gap-6 shadow-xl">
                <div class="border-b border-outline-variant pb-3 flex justify-between items-center">
                    <h3 class="font-headline-sm text-headline-sm text-primary font-bold mb-0">Select Delivery Location Pin</h3>
                    <span class="material-symbols-outlined text-primary-container">explore</span>
                </div>

                <!-- Leaflet Interactive Map Container -->
                <div class="relative bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-inner">
                    <div id="userMap" style="height: 280px; width: 100%;" class="z-0"></div>
                </div>

                <!-- Address Form -->
                <form action="" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="save_location">
                    <input type="hidden" id="userLat" name="lat" value="<?php echo htmlspecialchars($userLat); ?>">
                    <input type="hidden" id="userLng" name="lng" value="<?php echo htmlspecialchars($userLng); ?>">

                    <div class="space-y-2">
                        <label class="block font-label-sm text-on-surface font-bold">Street Address</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant material-symbols-outlined text-sm">location_on</span>
                            <input type="text" id="userAddress" name="address_line1" required placeholder="Enter street address..." class="bg-surface-container border border-outline-variant focus:border-primary text-on-surface pl-10 pr-4 py-2.5 rounded-lg text-sm w-full outline-none">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-4">
                        <div class="space-y-2 col-span-1">
                            <label class="block font-label-sm text-on-surface font-bold">City</label>
                            <input type="text" id="userCity" name="city" required value="<?php echo htmlspecialchars($locationCity ?: 'New York'); ?>" class="bg-surface-container border border-outline-variant focus:border-primary text-on-surface p-2.5 rounded-lg text-sm w-full outline-none">
                        </div>
                        <div class="space-y-2 col-span-1">
                            <label class="block font-label-sm text-on-surface font-bold">Postal Code</label>
                            <input type="text" id="userPincode" name="pincode" value="10001" placeholder="10001" class="bg-surface-container border border-outline-variant focus:border-primary text-on-surface p-2.5 rounded-lg text-sm w-full outline-none">
                        </div>
                        <div class="space-y-2 col-span-1">
                            <label class="block font-label-sm text-on-surface font-bold">Label</label>
                            <input type="text" name="address_label" value="Home" placeholder="Home, Work..." class="bg-surface-container border border-outline-variant focus:border-primary text-on-surface p-2.5 rounded-lg text-sm w-full outline-none">
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3 bg-primary text-on-primary font-bold rounded-xl hover:brightness-110 active:scale-95 transition-all border-0 shadow-md cursor-pointer">
                        Confirm &amp; Set Delivery Location
                    </button>
                </form>
            </div>

            <!-- Right Column: Saved Addresses -->
            <div class="lg:col-span-5 bg-surface-container-low border border-outline-variant p-6 rounded-2xl flex flex-col gap-4 shadow-xl">
                <div class="border-b border-outline-variant pb-3 flex justify-between items-center">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-0">Saved Addresses</h3>
                    <span class="text-xs text-on-surface-variant font-mono"><?php echo count($addresses); ?> Saved</span>
                </div>

                <?php if (!empty($addresses)): ?>
                    <div class="space-y-3 max-h-[380px] overflow-y-auto pr-1">
                        <?php foreach ($addresses as $addr):
                            $icon = 'location_on';
                            $lbl = strtolower($addr['label']);
                            if (strpos($lbl, 'home') !== false) $icon = 'home';
                            elseif (strpos($lbl, 'work') !== false) $icon = 'work';
                            elseif (strpos($lbl, 'gym') !== false) $icon = 'fitness_center';
                            $addrLat = floatval($addr['lat'] ?? 40.7128);
                            $addrLng = floatval($addr['lng'] ?? -74.0060);
                        ?>
                        <div onclick="selectSavedAddress(<?php echo $addrLat; ?>, <?php echo $addrLng; ?>, '<?php echo addslashes(htmlspecialchars($addr['address_line1'])); ?>', '<?php echo addslashes(htmlspecialchars($addr['city'])); ?>', '<?php echo addslashes(htmlspecialchars($addr['zip_code'] ?? '')); ?>')"
                             class="bg-surface-container border border-outline-variant hover:border-primary cursor-pointer p-4 rounded-xl flex items-start gap-4 transition-all group relative">
                            <span class="material-symbols-outlined text-primary-container bg-surface-container-high p-2 rounded-lg shrink-0 group-hover:bg-primary/20"><?php echo $icon; ?></span>
                            <div class="flex-grow">
                                <div class="flex items-center justify-between mb-0.5">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-on-surface text-sm"><?php echo htmlspecialchars($addr['label']); ?></span>
                                        <?php if ($addr['is_default']): ?>
                                            <span class="text-[10px] bg-primary-container/20 text-primary-container border border-primary-container/30 px-1.5 py-0.5 rounded font-bold flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[12px]">star</span> Default Pin
                                            </span>
                                        <?php else: ?>
                                            <form method="POST" action="" onsubmit="event.stopPropagation();" class="m-0 p-0 inline-block">
                                                <input type="hidden" name="action" value="set_default_location">
                                                <input type="hidden" name="address_id" value="<?php echo $addr['id']; ?>">
                                                <button type="submit" onclick="event.stopPropagation();" class="text-[10px] bg-surface-container-high text-on-surface-variant hover:text-primary hover:border-primary/40 border border-outline-variant/40 px-2 py-0.5 rounded-full font-bold transition-all cursor-pointer">
                                                    Set as Default
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                    <form method="POST" action="" onsubmit="event.stopPropagation(); return confirm('Are you sure you want to delete this delivery location?');" class="m-0 p-0 inline-block">
                                        <input type="hidden" name="action" value="delete_location">
                                        <input type="hidden" name="address_id" value="<?php echo $addr['id']; ?>">
                                        <button type="submit" onclick="event.stopPropagation();" class="p-1 text-on-surface-variant hover:text-red-400 bg-transparent border-0 cursor-pointer transition-colors rounded-md hover:bg-surface-container-highest flex items-center justify-center" title="Delete Location">
                                            <span class="material-symbols-outlined text-base">delete</span>
                                        </button>
                                    </form>
                                </div>
                                <p class="text-xs text-on-surface-variant mb-0"><?php echo htmlspecialchars($addr['address_line1']); ?></p>
                                <p class="text-xs text-on-surface-variant mb-0 font-mono text-[11px]"><?php echo htmlspecialchars($addr['city'] . ($addr['zip_code'] ? ', ' . $addr['zip_code'] : '')); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-6 text-center bg-surface-container rounded-xl border border-outline-variant/30">
                        <span class="material-symbols-outlined text-on-surface-variant text-3xl mb-2">location_off</span>
                        <p class="text-on-surface-variant text-sm font-bold mb-1">No saved addresses yet.</p>
                        <p class="text-xs text-on-surface-variant mb-0">Drag the map marker or click "Detect My Location" to set your primary delivery pin.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Nearest Stores Section -->
        <div class="bg-surface-container-low border border-outline-variant rounded-2xl p-6 shadow-xl">
            <div class="flex justify-between items-center border-b border-outline-variant pb-3 mb-5">
                <div>
                    <h3 class="font-headline-sm text-on-surface font-bold mb-0">
                        <?php if ($locationCity): ?>
                            Restaurants near <span class="text-primary"><?php echo htmlspecialchars($locationCity); ?></span>
                        <?php else: ?>
                            Nearby Restaurants
                        <?php endif; ?>
                    </h3>
                    <p class="text-xs text-on-surface-variant mt-1 mb-0">
                        <?php echo count($nearestStores); ?> restaurant<?php echo count($nearestStores) !== 1 ? 's' : ''; ?> sorted by real-time distance
                    </p>
                </div>
                <a href="<?php echo url('user/home'); ?>" class="text-xs text-primary font-bold text-decoration-none hover:underline flex items-center gap-1">
                    Explore all <span class="material-symbols-outlined text-sm">chevron_right</span>
                </a>
            </div>

            <?php if (empty($nearestStores)): ?>
                <p class="text-on-surface-variant text-sm text-center py-6">No active restaurants found in this area.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($nearestStores as $store): ?>
                        <a href="<?php echo url('user/restaurant_storefront&id=' . $store['id']); ?>"
                           class="bg-surface-container border border-outline-variant hover:border-primary rounded-xl p-4 flex flex-col gap-2 transition-all text-decoration-none group">
                            <div class="flex justify-between items-start">
                                <div class="w-10 h-10 bg-surface-container-high border border-outline-variant rounded-lg flex items-center justify-center shrink-0 overflow-hidden">
                                    <?php if (!empty($store['store_logo'])): ?>
                                        <img src="<?php echo htmlspecialchars($store['store_logo']); ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;">restaurant</span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-center gap-2">
                                    <?php if ($store['in_radius']): ?>
                                        <span class="text-[10px] bg-green-500/10 text-green-400 border border-green-500/20 px-2 py-0.5 rounded-full font-bold">In Delivery Zone</span>
                                    <?php else: ?>
                                        <span class="text-[10px] bg-amber-500/10 text-amber-400 border border-amber-500/20 px-2 py-0.5 rounded-full font-bold">Out of Range</span>
                                    <?php endif; ?>
                                    <div class="flex items-center gap-1 text-xs font-bold text-primary-container">
                                        <span class="material-symbols-outlined text-[14px]" style="font-variation-settings: 'FILL' 1;">star</span>
                                        <?php echo number_format($store['avg_rating'], 1); ?>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-on-surface mb-0 group-hover:text-primary transition-colors"><?php echo htmlspecialchars($store['store_name']); ?></h4>
                                <p class="text-xs text-on-surface-variant mb-0"><?php echo htmlspecialchars($store['category']); ?></p>
                                <div class="flex items-center justify-between mt-2 pt-2 border-t border-outline-variant/40 text-xs">
                                    <span class="text-on-surface-variant flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px] text-primary">near_me</span>
                                        <span class="font-bold text-on-surface font-mono"><?php echo $store['distance_km'] !== null ? $store['distance_km'] . ' km away' : 'Distance N/A'; ?></span>
                                    </span>
                                    <span class="text-on-surface-variant text-[11px]">
                                        Radius: <?php echo number_format($store['delivery_radius'], 1); ?> km
                                    </span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<script>
    let uMap, uMarker;
    const initialLat = <?php echo json_encode($userLat); ?>;
    const initialLng = <?php echo json_encode($userLng); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        uMap = L.map('userMap').setView([initialLat, initialLng], 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(uMap);

        uMarker = L.marker([initialLat, initialLng], { draggable: true }).addTo(uMap);

        uMarker.on('dragend', function(e) {
            const pos = uMarker.getLatLng();
            setUserCoords(pos.lat, pos.lng);
            reverseGeocodeUser(pos.lat, pos.lng);
        });

        uMap.on('click', function(e) {
            uMarker.setLatLng(e.latlng);
            setUserCoords(e.latlng.lat, e.latlng.lng);
            reverseGeocodeUser(e.latlng.lat, e.latlng.lng);
        });
    });

    function setUserCoords(lat, lng) {
        document.getElementById('userLat').value = parseFloat(lat).toFixed(6);
        document.getElementById('userLng').value = parseFloat(lng).toFixed(6);
    }

    function selectSavedAddress(lat, lng, address, city, pincode) {
        if (uMap && uMarker) {
            uMap.setView([lat, lng], 15);
            uMarker.setLatLng([lat, lng]);
            setUserCoords(lat, lng);
            if (address) document.getElementById('userAddress').value = address;
            if (city) document.getElementById('userCity').value = city;
            if (pincode) document.getElementById('userPincode').value = pincode;
        }
    }

    function detectUserGPS() {
        if ('geolocation' in navigator) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                uMap.setView([lat, lng], 15);
                uMarker.setLatLng([lat, lng]);
                setUserCoords(lat, lng);
                reverseGeocodeUser(lat, lng);
            }, function(err) {
                alert('GPS detection failed: ' + err.message);
            }, { enableHighAccuracy: true });
        } else {
            alert('Geolocation is not supported by your browser.');
        }
    }

    function reverseGeocodeUser(lat, lng) {
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
            .then(res => res.json())
            .then(data => {
                if (data && data.address) {
                    const addr = data.address;
                    const street = addr.road || addr.suburb || addr.neighbourhood || addr.amenity || '';
                    const city = addr.city || addr.town || addr.village || addr.county || '';
                    const zip = addr.postcode || '';

                    if (street) document.getElementById('userAddress').value = street;
                    if (city) document.getElementById('userCity').value = city;
                    if (zip) document.getElementById('userPincode').value = zip;
                }
            })
            .catch(err => console.log('Reverse geocode error:', err));
    }
</script>

<?php view('partials/user_footer', get_defined_vars()); ?>
