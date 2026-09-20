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

// Resolve store associated with logged-in owner
$storeId = 0;
$myStore = null;
if ($sellerId) {
    $stmtStore = $db->prepare("SELECT * FROM stores WHERE owner_id = ?");
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

$successMsg = '';
$errorMsg = '';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name           = trim($_POST['name'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $email          = trim($_POST['contact_email'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $city           = trim($_POST['city'] ?? '');
    $pincode        = trim($_POST['pincode'] ?? '');
    $country        = trim($_POST['country'] ?? 'India');
    $category       = trim($_POST['category'] ?? '');
    $openTime       = trim($_POST['opening_time'] ?? '09:00');
    $closeTime      = trim($_POST['closing_time'] ?? '22:00');
    $deliveryRadius = floatval($_POST['delivery_radius'] ?? $myStore['delivery_radius'] ?? 5.0);
    $lat            = isset($_POST['lat']) && $_POST['lat'] !== '' ? floatval($_POST['lat']) : floatval($myStore['lat'] ?? 40.7128);
    $lng            = isset($_POST['lng']) && $_POST['lng'] !== '' ? floatval($_POST['lng']) : floatval($myStore['lng'] ?? -74.0060);

    if ($name && $phone && $address && $city) {
        try {
            // Resolve logo value — prefer base64 upload, fallback to URL
            $logoSourceType = $_POST['logo_source_type'] ?? 'upload';
            $newLogo = null;
            if ($logoSourceType === 'upload') {
                $b64 = trim($_POST['logo_base64'] ?? '');
                if ($b64) $newLogo = $b64;
            } else {
                $u = trim($_POST['logo_url'] ?? '');
                if ($u) $newLogo = $u;
            }

            // Resolve banner value
            $bannerSourceType = $_POST['banner_source_type'] ?? 'upload';
            $newBanner = null;
            if ($bannerSourceType === 'upload') {
                $b64b = trim($_POST['banner_base64'] ?? '');
                if ($b64b) $newBanner = $b64b;
            } else {
                $ub = trim($_POST['banner_url'] ?? '');
                if ($ub) $newBanner = $ub;
            }

            // Build UPDATE — including lat, lng, and delivery_radius
            if ($newLogo !== null && $newBanner !== null) {
                $upd = $db->prepare("UPDATE stores SET store_name=?, contact_phone=?, contact_email=?, address=?, city=?, pincode=?, country=?, category=?, opening_time=?, closing_time=?, delivery_radius=?, lat=?, lng=?, store_logo=?, store_banner=? WHERE id=?");
                $upd->execute([$name, $phone, $email, $address, $city, $pincode, $country, $category, $openTime, $closeTime, $deliveryRadius, $lat, $lng, $newLogo, $newBanner, $storeId]);
            } elseif ($newLogo !== null) {
                $upd = $db->prepare("UPDATE stores SET store_name=?, contact_phone=?, contact_email=?, address=?, city=?, pincode=?, country=?, category=?, opening_time=?, closing_time=?, delivery_radius=?, lat=?, lng=?, store_logo=? WHERE id=?");
                $upd->execute([$name, $phone, $email, $address, $city, $pincode, $country, $category, $openTime, $closeTime, $deliveryRadius, $lat, $lng, $newLogo, $storeId]);
            } elseif ($newBanner !== null) {
                $upd = $db->prepare("UPDATE stores SET store_name=?, contact_phone=?, contact_email=?, address=?, city=?, pincode=?, country=?, category=?, opening_time=?, closing_time=?, delivery_radius=?, lat=?, lng=?, store_banner=? WHERE id=?");
                $upd->execute([$name, $phone, $email, $address, $city, $pincode, $country, $category, $openTime, $closeTime, $deliveryRadius, $lat, $lng, $newBanner, $storeId]);
            } else {
                $upd = $db->prepare("UPDATE stores SET store_name=?, contact_phone=?, contact_email=?, address=?, city=?, pincode=?, country=?, category=?, opening_time=?, closing_time=?, delivery_radius=?, lat=?, lng=? WHERE id=?");
                $upd->execute([$name, $phone, $email, $address, $city, $pincode, $country, $category, $openTime, $closeTime, $deliveryRadius, $lat, $lng, $storeId]);
            }

            $successMsg = "Store settings and map location updated successfully!";
            // Refresh store data after save
            $stmt2 = $db->prepare("SELECT * FROM stores WHERE id = ?");
            $stmt2->execute([$storeId]);
            $myStore = $stmt2->fetch();
        } catch (PDOException $e) {
            $errorMsg = "Error updating store: " . $e->getMessage();
        }
    } else {
        $errorMsg = "Please fill in Restaurant Name, Contact Number, Address, and City.";
    }
}

// Fetch current store details
$store = $myStore;
if (!$store) {
    echo "Store not found.";
    exit;
}

$storeLat = floatval($store['lat'] ?? 40.7128);
$storeLng = floatval($store['lng'] ?? -74.0060);
$storeRadius = floatval($store['delivery_radius'] ?? 5.0);

$pageTitle = 'Store Settings';
$activeNav = 'settings';
view('partials/seller_header', get_defined_vars());
view('partials/seller_sidebar', get_defined_vars());
?>

<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="<?php echo asset('css/leaflet.css'); ?>" />
<script src="<?php echo asset('js/leaflet.js'); ?>"></script>

<!-- Main Content Area -->
<div class="flex-grow p-8 overflow-y-auto">
    <!-- Content Canvas -->
    <div class="max-w-6xl mx-auto space-y-8">
        <!-- Hero / Banner Section -->
        <section class="relative group mb-16">
            <div class="h-64 w-full rounded-2xl border border-outline-variant overflow-hidden bg-surface-container relative shadow-2xl">
                <div class="absolute inset-0 bg-black/40 z-10"></div>
                <div class="absolute inset-0 flex flex-col items-center justify-center z-20 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button type="button" onclick="alert('Uploading new cover banner...')" class="bg-surface/90 backdrop-blur-md px-6 py-3 rounded-full flex items-center gap-2 border border-outline-variant hover:bg-surface transition-all text-on-surface font-bold cursor-pointer">
                        <span class="material-symbols-outlined">edit</span>
                        <span class="font-label-md text-label-md mb-0 font-bold">Change Cover Banner</span>
                    </button>
                    <p class="text-on-surface-variant font-label-sm mt-2 mb-0 font-bold">Recommended size: 1920x480</p>
                </div>
                <div class="w-full h-full bg-cover bg-center" data-alt="<?php echo htmlspecialchars($store['store_name']); ?>" style="background-image: url('<?php echo !empty($store['store_banner']) ? htmlspecialchars($store['store_banner']) : asset('images/obsidian_grill_kitchen.png'); ?>')"></div>
            </div>
            <!-- Brand Logo Overlay -->
            <div class="absolute -bottom-12 left-8 z-30 group/logo">
                <div class="relative">
                    <div class="w-32 h-32 rounded-2xl border-4 border-surface bg-surface-container-high overflow-hidden shadow-2xl animate-fade-in" style="width: 128px; height: 128px;">
                        <img class="w-full h-full object-cover" data-alt="Restaurant Logo" src="<?php echo !empty($store['store_logo']) ? htmlspecialchars($store['store_logo']) : asset('images/default_restaurant_logo.png'); ?>">
                    </div>
                    <button type="button" onclick="alert('Uploading new store logo...')" class="absolute inset-0 bg-black/60 flex items-center justify-center opacity-0 group-hover/logo:opacity-100 transition-opacity rounded-2xl border-0 cursor-pointer">
                        <span class="material-symbols-outlined text-white text-3xl">photo_camera</span>
                    </button>
                </div>
            </div>
        </section>

        <form class="grid grid-cols-1 lg:grid-cols-3 gap-8 pt-4" method="POST" action="">
            <!-- Left Column: Form Settings -->
            <div class="lg:col-span-2 space-y-8">
                <div class="bg-surface-container rounded-2xl border border-outline-variant p-8 shadow-xl">
                    <h3 class="font-headline-md text-headline-md mb-6 border-b border-outline-variant pb-4 font-bold text-on-surface">General Information</h3>
                    <?php if ($successMsg): ?>
                        <div class="bg-green-950/20 border border-green-500 text-green-400 rounded-xl p-3 text-xs text-center font-bold mb-4 flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-base">check_circle</span>
                            <span><?php echo htmlspecialchars($successMsg); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($errorMsg): ?>
                        <div class="bg-error-container/20 border border-error-container text-error rounded-xl p-3 text-xs text-center font-bold mb-4 flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-base">error</span>
                            <span><?php echo htmlspecialchars($errorMsg); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="font-label-md text-label-md text-on-surface-variant font-bold">Restaurant Name</label>
                                <input id="storeName" name="name" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-3 font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant/30 focus:border-primary focus:outline-none" type="text" value="<?php echo htmlspecialchars($store['store_name']); ?>" required>
                            </div>
                            <div class="space-y-2">
                                <label class="font-label-md text-label-md text-on-surface-variant font-bold">Contact Number</label>
                                <input id="storePhone" name="phone" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-3 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none" type="tel" value="<?php echo htmlspecialchars($store['contact_phone']); ?>" required>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="font-label-md text-label-md text-on-surface-variant font-bold">Contact Email</label>
                            <input id="storeEmail" name="contact_email" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-3 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none" type="email" value="<?php echo htmlspecialchars($store['contact_email'] ?? ''); ?>">
                        </div>
                        <div class="space-y-2">
                            <label class="font-label-md text-label-md text-on-surface-variant font-bold">Store Address <span class="text-error">*</span></label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant">location_on</span>
                                <input id="storeAddr" name="address" class="w-full bg-surface border border-outline-variant rounded-xl pl-12 pr-4 py-3 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none" type="text" value="<?php echo htmlspecialchars($store['address']); ?>" required>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="space-y-2">
                                <label class="font-label-md text-label-md text-on-surface-variant font-bold">City <span class="text-error">*</span></label>
                                <input name="city" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-3 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none" type="text" placeholder="e.g. Mumbai" value="<?php echo htmlspecialchars($store['city'] ?? ''); ?>" required>
                            </div>
                            <div class="space-y-2">
                                <label class="font-label-md text-label-md text-on-surface-variant font-bold">Pincode / ZIP</label>
                                <input name="pincode" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-3 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none" type="text" placeholder="e.g. 400050" value="<?php echo htmlspecialchars($store['pincode'] ?? ''); ?>">
                            </div>
                            <div class="space-y-2">
                                <label class="font-label-md text-label-md text-on-surface-variant font-bold">Country</label>
                                <input name="country" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-3 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none" type="text" placeholder="e.g. India" value="<?php echo htmlspecialchars(!empty($store['country']) && $store['country'] !== 'United States' ? $store['country'] : 'India'); ?>">
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="font-label-md text-label-md text-on-surface-variant font-bold">Business Category</label>
                            <select id="storeCuisine" name="category" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-3 font-body-md text-body-md text-on-surface focus:border-primary focus:outline-none">
                                <option value="<?php echo htmlspecialchars($store['category']); ?>"><?php echo htmlspecialchars($store['category']); ?></option>
                                <option value="North Indian & Tandoori">North Indian & Tandoori</option>
                                <option value="South Indian & Tiffin">South Indian & Tiffin</option>
                                <option value="Hyderabadi Haleem & Biryani">Hyderabadi Haleem & Biryani</option>
                                <option value="Bakery & Cafe">Bakery & Cafe</option>
                                <option value="Fast Food">Fast Food</option>
                            </select>
                        </div>

                        <!-- Store Branding Inputs -->
                        <div class="border-t border-outline-variant/30 pt-6 space-y-6">
                            <h4 class="font-bold text-on-surface text-base">Store Branding</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="block font-label-md text-label-md text-on-surface-variant font-bold">Store Logo</label>
                                    <div class="flex gap-2 mb-3 bg-surface-container-low p-1 rounded-lg border border-outline-variant">
                                        <button type="button" id="toggle-logo-upload" onclick="switchLogoSource('upload')" class="flex-grow py-1.5 text-xs rounded-md font-bold transition-all border-0 cursor-pointer bg-primary-container text-on-primary-container">
                                            Upload File
                                        </button>
                                        <button type="button" id="toggle-logo-url" onclick="switchLogoSource('url')" class="flex-grow py-1.5 text-xs rounded-md font-bold transition-all border-0 cursor-pointer bg-transparent text-on-surface-variant">
                                            Enter URL
                                        </button>
                                    </div>
                                    <input type="hidden" name="logo_source_type" id="logo_source_type" value="upload">
                                    <input type="hidden" name="logo_base64" id="logo_base64" value="">
                                    <div id="logo-upload-group" class="transition-all">
                                        <label for="logo_file" class="flex flex-col items-center justify-center border-2 border-dashed border-outline-variant hover:border-primary rounded-xl p-4 cursor-pointer hover:bg-surface-container-high transition-colors text-center">
                                            <span class="material-symbols-outlined text-2xl text-primary mb-1">cloud_upload</span>
                                            <span class="text-xs font-bold text-on-surface block truncate max-w-full" id="logo-file-label">Choose Logo File</span>
                                            <input type="file" id="logo_file" accept="image/*" class="hidden" onchange="handleLogoUpload(this)">
                                        </label>
                                    </div>
                                    <div id="logo-url-group" class="hidden transition-all">
                                        <input name="logo_url" id="logo_url" type="url" placeholder="https://images.unsplash.com/...logo.png" class="w-full bg-surface border border-outline-variant focus:border-primary focus:ring-0 text-on-surface p-3 rounded-lg outline-none transition-all">
                                    </div>
                                </div>
                                
                                <div class="space-y-2">
                                    <label class="block font-label-md text-label-md text-on-surface-variant font-bold">Store Banner</label>
                                    <div class="flex gap-2 mb-3 bg-surface-container-low p-1 rounded-lg border border-outline-variant">
                                        <button type="button" id="toggle-banner-upload" onclick="switchBannerSource('upload')" class="flex-grow py-1.5 text-xs rounded-md font-bold transition-all border-0 cursor-pointer bg-primary-container text-on-primary-container">
                                            Upload File
                                        </button>
                                        <button type="button" id="toggle-banner-url" onclick="switchBannerSource('url')" class="flex-grow py-1.5 text-xs rounded-md font-bold transition-all border-0 cursor-pointer bg-transparent text-on-surface-variant">
                                            Enter URL
                                        </button>
                                    </div>
                                    <input type="hidden" name="banner_source_type" id="banner_source_type" value="upload">
                                    <input type="hidden" name="banner_base64" id="banner_base64" value="">
                                    <div id="banner-upload-group" class="transition-all">
                                        <label for="banner_file" class="flex flex-col items-center justify-center border-2 border-dashed border-outline-variant hover:border-primary rounded-xl p-4 cursor-pointer hover:bg-surface-container-high transition-colors text-center">
                                            <span class="material-symbols-outlined text-2xl text-primary mb-1">cloud_upload</span>
                                            <span class="text-xs font-bold text-on-surface block truncate max-w-full" id="banner-file-label">Choose Banner File</span>
                                            <input type="file" id="banner_file" accept="image/*" class="hidden" onchange="handleBannerUpload(this)">
                                        </label>
                                    </div>
                                    <div id="banner-url-group" class="hidden transition-all">
                                        <input name="banner_url" id="banner_url" type="url" placeholder="https://images.unsplash.com/...banner.png" class="w-full bg-surface border border-outline-variant focus:border-primary focus:ring-0 text-on-surface p-3 rounded-lg outline-none transition-all">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-surface-container rounded-2xl border border-outline-variant p-8 shadow-xl">
                    <h3 class="font-headline-md text-headline-md mb-6 border-b border-outline-variant pb-4 font-bold text-on-surface">Operational Hours</h3>
                    <div class="space-y-6">
                        <div class="flex items-center justify-between py-3 border-b border-outline-variant/30">
                            <span class="font-body-md text-body-md w-36 font-bold text-on-surface">Daily Operational Hours</span>
                            <div class="flex items-center gap-4">
                                <input name="opening_time" class="bg-surface border border-outline-variant rounded-xl px-4 py-2 text-on-surface font-mono font-bold" type="time" value="<?php echo substr($store['opening_time'] ?? '09:00:00', 0, 5); ?>" required>
                                <span class="text-on-surface-variant font-bold">to</span>
                                <input name="closing_time" class="bg-surface border border-outline-variant rounded-xl px-4 py-2 text-on-surface font-mono font-bold" type="time" value="<?php echo substr($store['closing_time'] ?? '22:00:00', 0, 5); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Interactive Map & Delivery Radius -->
            <div class="space-y-8">
                <!-- Hidden Map Coordinates Inputs -->
                <input type="hidden" name="lat" id="settingsLat" value="<?php echo htmlspecialchars($storeLat); ?>">
                <input type="hidden" name="lng" id="settingsLng" value="<?php echo htmlspecialchars($storeLng); ?>">

                <div class="bg-surface-container rounded-2xl border border-outline-variant p-6 shadow-xl space-y-4">
                    <div class="flex justify-between items-center border-b border-outline-variant pb-3">
                        <h3 class="font-label-md text-label-md text-on-surface-variant uppercase tracking-widest font-bold mb-0">Delivery Radius &amp; Map Pin</h3>
                        <span class="text-primary font-bold font-mono text-base mb-0" id="radiusDisplay"><?php echo number_format($storeRadius, 1); ?> km</span>
                    </div>

                    <!-- Interactive Leaflet Map Container -->
                    <div class="relative bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-inner">
                        <div id="settingsMap" style="height: 220px; width: 100%;" class="z-0"></div>
                    </div>

                    <!-- Radius Range Slider -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center text-xs font-bold text-on-surface-variant">
                            <span>Adjust Radius</span>
                            <span class="text-primary font-mono" id="sliderRadiusVal"><?php echo number_format($storeRadius, 1); ?> km</span>
                        </div>
                        <input id="radiusRange" name="delivery_radius" class="w-full h-2 bg-outline-variant rounded-lg appearance-none cursor-pointer accent-primary" max="25" min="1" step="0.5" type="range" value="<?php echo htmlspecialchars($storeRadius); ?>" oninput="updateSettingsRadius(this.value)">
                        <div class="flex justify-between text-[10px] text-on-surface-variant font-bold">
                            <span>1 KM</span>
                            <span>10 KM</span>
                            <span>25 KM</span>
                        </div>
                    </div>

                    <!-- Sync & GPS Buttons -->
                    <div class="pt-2 flex flex-col gap-2">
                        <button type="button" onclick="detectStoreGPSLocation()" class="w-full py-2.5 bg-primary text-on-primary font-bold rounded-xl text-xs flex items-center justify-center gap-2 hover:brightness-110 active:scale-95 cursor-pointer transition-all border-0 shadow-md">
                            <span class="material-symbols-outlined text-sm">my_location</span>
                            <span>Use Current Location</span>
                        </button>
                        <button type="button" onclick="geocodeStoreAddress()" class="w-full py-2 bg-primary/10 text-primary border border-primary/30 rounded-xl text-xs font-bold flex items-center justify-center gap-2 hover:bg-primary/20 cursor-pointer transition-all">
                            <span class="material-symbols-outlined text-sm">sync</span>
                            <span>Sync Map Pin with Address</span>
                        </button>
                        <a href="<?php echo url('seller/location'); ?>" class="text-[11px] text-center text-on-surface-variant hover:text-primary font-bold text-decoration-none py-1">
                            Open Advanced Geofencing Studio &rarr;
                        </a>
                    </div>
                </div>

                <!-- Action Sidebar -->
                <div class="sticky top-24 space-y-4">
                    <button type="submit" class="w-full py-4 bg-primary text-on-primary font-bold rounded-xl shadow-xl hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-2 border-0 cursor-pointer font-bold">
                        <span class="material-symbols-outlined">save</span>
                        <span>Save Changes</span>
                    </button>
                    <button type="button" onclick="discardChanges()" class="w-full py-4 bg-surface border border-outline-variant text-on-surface font-bold rounded-xl hover:bg-surface-container-high active:scale-95 transition-all cursor-pointer font-bold">
                        Discard Draft
                    </button>
                </div>
            </div>
        </form>
        
        <!-- Footer -->
        <footer class="bg-surface-container border-t border-outline-variant shrink-0">
            <div class="w-full py-12 px-8 flex flex-col md:flex-row justify-between items-center gap-6 max-w-7xl mx-auto">
                <div class="flex flex-col items-center md:items-start gap-2">
                    <span class="font-headline-sm text-headline-sm font-bold text-on-surface mb-0">TestShare</span>
                    <p class="font-label-sm text-on-surface-variant mb-0">© 2024 TestShare Merchant Portal. All rights reserved.</p>
                </div>
                <nav class="flex flex-wrap justify-center gap-8 mb-0">
                    <a class="font-label-sm text-on-surface-variant hover:text-primary transition-colors text-decoration-none font-bold" href="#">Privacy Policy</a>
                    <a class="font-label-sm text-on-surface-variant hover:text-primary transition-colors text-decoration-none font-bold" href="#">Terms of Service</a>
                    <a class="font-label-sm text-on-surface-variant hover:text-primary transition-colors text-decoration-none font-bold" href="#">Merchant Support</a>
                </nav>
            </div>
        </footer>
    </div>
</div>

<script>
    let sMap, sMarker, sCircle;
    const initialLat = <?php echo json_encode($storeLat); ?>;
    const initialLng = <?php echo json_encode($storeLng); ?>;
    const initialRadius = <?php echo json_encode($storeRadius); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Leaflet Map
        sMap = L.map('settingsMap').setView([initialLat, initialLng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(sMap);

        sMarker = L.marker([initialLat, initialLng], { draggable: true }).addTo(sMap);
        sCircle = L.circle([initialLat, initialLng], {
            radius: initialRadius * 1000,
            color: '#ff9f0d',
            fillColor: '#ff9f0d',
            fillOpacity: 0.15,
            weight: 2
        }).addTo(sMap);

        sMarker.on('dragend', function(e) {
            const pos = sMarker.getLatLng();
            document.getElementById('settingsLat').value = pos.lat.toFixed(6);
            document.getElementById('settingsLng').value = pos.lng.toFixed(6);
            sCircle.setLatLng(pos);
        });

        sMap.on('click', function(e) {
            sMarker.setLatLng(e.latlng);
            sCircle.setLatLng(e.latlng);
            document.getElementById('settingsLat').value = e.latlng.lat.toFixed(6);
            document.getElementById('settingsLng').value = e.latlng.lng.toFixed(6);
        });
    });

    function updateSettingsRadius(val) {
        const fVal = parseFloat(val);
        document.getElementById('radiusDisplay').innerText = fVal.toFixed(1) + ' km';
        document.getElementById('sliderRadiusVal').innerText = fVal.toFixed(1) + ' km';
        if (sCircle) {
            sCircle.setRadius(fVal * 1000);
        }
    }

    function detectStoreGPSLocation() {
        if ('geolocation' in navigator) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                sMap.setView([lat, lng], 15);
                sMarker.setLatLng([lat, lng]);
                sCircle.setLatLng([lat, lng]);

                document.getElementById('settingsLat').value = lat.toFixed(6);
                document.getElementById('settingsLng').value = lng.toFixed(6);

                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.address) {
                            const addr = data.address;
                            const street = addr.road || addr.suburb || addr.neighbourhood || addr.amenity || '';
                            const city = addr.city || addr.town || addr.village || addr.county || '';
                            const zip = addr.postcode || '';
                            const country = addr.country || 'India';

                            if (street) document.getElementById('storeAddr').value = street;
                            if (city && document.querySelector('input[name="city"]')) {
                                document.querySelector('input[name="city"]').value = city;
                            }
                            if (zip && document.querySelector('input[name="pincode"]')) {
                                document.querySelector('input[name="pincode"]').value = zip;
                            }
                            if (country && document.querySelector('input[name="country"]')) {
                                document.querySelector('input[name="country"]').value = country;
                            }
                        }
                        alert('Map location set to your current GPS coordinates!');
                    })
                    .catch(err => {
                        alert('Current GPS location set to coordinates: ' + lat.toFixed(4) + ', ' + lng.toFixed(4));
                    });
            }, function(err) {
                alert('GPS Detection Failed: ' + err.message);
            }, { enableHighAccuracy: true });
        } else {
            alert('Geolocation is not supported by your browser.');
        }
    }

    function geocodeStoreAddress() {
        const addr = document.getElementById('storeAddr').value;
        const city = document.querySelector('input[name="city"]').value;
        const zip = document.querySelector('input[name="pincode"]').value;
        const country = document.querySelector('input[name="country"]').value;

        const fullQuery = [addr, city, zip, country].filter(Boolean).join(', ');
        if (!fullQuery) {
            alert('Please enter a store address to sync.');
            return;
        }

        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(fullQuery)}`)
            .then(res => res.json())
            .then(data => {
                if (data && data.length > 0) {
                    const lat = parseFloat(data[0].lat);
                    const lng = parseFloat(data[0].lon);
                    sMap.setView([lat, lng], 14);
                    sMarker.setLatLng([lat, lng]);
                    sCircle.setLatLng([lat, lng]);
                    document.getElementById('settingsLat').value = lat.toFixed(6);
                    document.getElementById('settingsLng').value = lng.toFixed(6);
                    alert(`Map location successfully synced to:\n${data[0].display_name}`);
                } else {
                    alert('Could not auto-find map coordinates for this address. Please drag the pin on the map to set location manually.');
                }
            })
            .catch(err => {
                alert('Geocoding error: ' + err);
            });
    }

    function setPriceRange(btn) {
        document.querySelectorAll('#priceRangeContainer .price-btn').forEach(b => {
            b.className = 'flex-1 py-3 border border-outline-variant text-on-surface-variant font-bold rounded-xl hover:bg-surface-container-high bg-transparent price-btn cursor-pointer';
        });
        btn.className = 'flex-1 py-3 border border-primary text-primary font-bold rounded-xl bg-primary/10 price-btn cursor-pointer';
    }

    function switchLogoSource(type) {
        const btnUpload = document.getElementById('toggle-logo-upload');
        const btnUrl = document.getElementById('toggle-logo-url');
        const grpUpload = document.getElementById('logo-upload-group');
        const grpUrl = document.getElementById('logo-url-group');
        const inputType = document.getElementById('logo_source_type');
        inputType.value = type;
        if (type === 'upload') {
            btnUpload.className = 'flex-grow py-1.5 text-xs rounded-md font-bold transition-all border-0 cursor-pointer bg-primary-container text-on-primary-container';
            btnUrl.className = 'flex-grow py-1.5 text-xs rounded-md font-bold transition-all border-0 cursor-pointer bg-transparent text-on-surface-variant';
            grpUpload.classList.remove('hidden');
            grpUrl.classList.add('hidden');
        } else {
            btnUpload.className = 'flex-grow py-1.5 text-xs rounded-md font-bold transition-all border-0 cursor-pointer bg-transparent text-on-surface-variant';
            btnUrl.className = 'flex-grow py-1.5 text-xs rounded-md font-bold transition-all border-0 cursor-pointer bg-primary-container text-on-primary-container';
            grpUpload.classList.add('hidden');
            grpUrl.classList.remove('hidden');
        }
    }

    function handleLogoUpload(input) {
        const file = input.files[0];
        if (file) {
            document.getElementById('logo-file-label').textContent = file.name;
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('logo_base64').value = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    }

    function switchBannerSource(type) {
        const btnUpload = document.getElementById('toggle-banner-upload');
        const btnUrl = document.getElementById('toggle-banner-url');
        const grpUpload = document.getElementById('banner-upload-group');
        const grpUrl = document.getElementById('banner-url-group');
        const inputType = document.getElementById('banner_source_type');
        inputType.value = type;
        if (type === 'upload') {
            btnUpload.className = 'flex-grow py-1.5 text-xs rounded-md font-bold transition-all border-0 cursor-pointer bg-primary-container text-on-primary-container';
            btnUrl.className = 'flex-grow py-1.5 text-xs rounded-md font-bold transition-all border-0 cursor-pointer bg-transparent text-on-surface-variant';
            grpUpload.classList.remove('hidden');
            grpUrl.classList.add('hidden');
        } else {
            btnUpload.className = 'flex-grow py-1.5 text-xs rounded-md font-bold transition-all border-0 cursor-pointer bg-transparent text-on-surface-variant';
            btnUrl.className = 'flex-grow py-1.5 text-xs rounded-md font-bold transition-all border-0 cursor-pointer bg-primary-container text-on-primary-container';
            grpUpload.classList.add('hidden');
            grpUrl.classList.remove('hidden');
        }
    }

    function handleBannerUpload(input) {
        const file = input.files[0];
        if (file) {
            document.getElementById('banner-file-label').textContent = file.name;
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('banner_base64').value = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    }

    function discardChanges() {
        if (confirm('Are you sure you want to discard all pending changes to store settings?')) {
            window.location.reload();
        }
    }
</script>

<?php view('partials/seller_footer'); ?>
