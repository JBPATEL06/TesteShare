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

$successMsg = '';
$errorMsg = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $storeId) {
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $zip = trim($_POST['zip'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $lat = floatval($_POST['lat'] ?? 40.7128);
    $lng = floatval($_POST['lng'] ?? -74.0060);
    $radius = floatval($_POST['delivery_radius'] ?? 5.0);

    if ($address && $city && $zip && $country) {
        try {
            $upStmt = $db->prepare("
                UPDATE stores 
                SET address = ?, city = ?, pincode = ?, country = ?, lat = ?, lng = ?, delivery_radius = ?
                WHERE id = ?
            ");
            $upStmt->execute([$address, $city, $zip, $country, $lat, $lng, $radius, $storeId]);
            $successMsg = "Restaurant location coordinates and geofence saved successfully!";
            
            // refresh store data
            $stmtStore->execute([$sellerId]);
            $myStore = $stmtStore->fetch();
        } catch (Exception $e) {
            $errorMsg = "Error updating location: " . $e->getMessage();
        }
    } else {
        $errorMsg = "Please fill in all address fields.";
    }
}

$storeLat = floatval($myStore['lat'] ?? 40.7128);
$storeLng = floatval($myStore['lng'] ?? -74.0060);
$storeRadius = floatval($myStore['delivery_radius'] ?? 5.0);

$pageTitle = 'Store Location';
$activeNav = 'location';
view('partials/seller_header', get_defined_vars());
view('partials/seller_sidebar', get_defined_vars());
?>

<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="<?php echo asset('css/leaflet.css'); ?>" />
<script src="<?php echo asset('js/leaflet.js'); ?>"></script>

<div class="flex-grow p-8 overflow-y-auto">
    <div class="max-w-6xl mx-auto w-full flex flex-col justify-between" style="min-height: 80vh;">
        <div class="space-y-8">
            <!-- Header Section -->
            <div class="border-b border-outline-variant pb-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold mb-1">Store Location &amp; Geofencing</h2>
                    <p class="text-body-md text-on-surface-variant mb-0">Configure your restaurant's geolocation pin, managing delivery service radiuses and drop-off coordinates.</p>
                </div>
                <button type="button" onclick="detectGPSLocation()" class="px-4 py-2.5 bg-primary/10 text-primary border border-primary/30 rounded-xl text-xs font-bold flex items-center gap-2 hover:bg-primary/20 cursor-pointer shadow-sm transition-all shrink-0">
                    <span class="material-symbols-outlined text-base">my_location</span>
                    <span>Detect My Location</span>
                </button>
            </div>
            
            <?php if ($successMsg): ?>
                <div class="bg-green-500/20 text-green-400 border border-green-500/30 p-4 rounded-xl font-bold flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">check_circle</span>
                    <span><?php echo htmlspecialchars($successMsg); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($errorMsg): ?>
                <div class="bg-error-container/20 text-error border border-error-container/30 p-4 rounded-xl font-bold flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">error</span>
                    <span><?php echo htmlspecialchars($errorMsg); ?></span>
                </div>
            <?php endif; ?>

            <!-- Main Layout Map & Form -->
            <form action="" method="POST">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch">
                    <!-- Map Configuration (7 Cols) -->
                    <div class="lg:col-span-7 bg-surface-container border border-outline-variant p-6 rounded-2xl flex flex-col gap-6 shadow-xl">
                        <div class="flex justify-between items-center">
                            <h3 class="font-bold text-lg text-on-surface mb-0">Interactive Store Map Pin</h3>
                            <span class="text-xs text-on-surface-variant font-mono bg-surface-container-high px-2.5 py-1 rounded-lg border border-outline-variant/30">
                                Drag pin or click map to move
                            </span>
                        </div>
                        
                        <!-- Interactive Leaflet Map Container -->
                        <div class="relative bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-inner">
                            <div id="sellerMap" style="height: 340px; width: 100%;" class="z-0"></div>
                        </div>

                        <!-- Geofencing Settings -->
                        <div class="space-y-4 pt-2">
                            <div class="flex justify-between items-center">
                                <h4 class="font-bold text-sm text-on-surface uppercase mb-0">Delivery Radius Geofence</h4>
                                <span class="text-primary font-bold text-lg font-mono" id="rangeValue"><?php echo number_format($storeRadius, 1); ?> km</span>
                            </div>
                            <div class="flex gap-4 items-center">
                                <input type="range" name="delivery_radius" min="1" max="25" step="0.5" value="<?php echo htmlspecialchars($storeRadius); ?>" class="flex-grow accent-primary h-2 bg-outline-variant rounded-lg appearance-none cursor-pointer" id="rangeSlider" oninput="updateRangeText(this.value)">
                            </div>
                            <p class="text-xs text-on-surface-variant mb-0">Customer delivery orders are limited to addresses within this specified kilometer radius geofence.</p>
                        </div>
                    </div>

                    <!-- Geolocation Details Form (5 Cols) -->
                    <div class="lg:col-span-5 bg-surface-container border border-outline-variant p-6 rounded-2xl flex flex-col justify-between shadow-xl">
                        <div class="space-y-4">
                            <h3 class="font-bold text-lg text-on-surface mb-2 border-b border-outline-variant pb-3">Address &amp; Coordinates</h3>
                            
                            <div class="space-y-2">
                                <label for="address" class="block text-xs font-bold text-on-surface-variant uppercase">Street Address</label>
                                <input type="text" id="address" name="address" required value="<?php echo htmlspecialchars($myStore['address'] ?? ''); ?>" class="bg-surface-container-low border border-outline-variant focus:border-primary text-on-surface p-2.5 rounded-lg text-sm w-full outline-none">
                            </div>
                            
                            <div class="space-y-2">
                                <label for="city" class="block text-xs font-bold text-on-surface-variant uppercase">City &amp; Region</label>
                                <input type="text" id="city" name="city" required value="<?php echo htmlspecialchars($myStore['city'] ?? ''); ?>" class="bg-surface-container-low border border-outline-variant focus:border-primary text-on-surface p-2.5 rounded-lg text-sm w-full outline-none">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <label for="zip" class="block text-xs font-bold text-on-surface-variant uppercase">Postal Code</label>
                                    <input type="text" id="zip" name="zip" required value="<?php echo htmlspecialchars($myStore['pincode'] ?? ''); ?>" class="bg-surface-container-low border border-outline-variant focus:border-primary text-on-surface p-2.5 rounded-lg text-sm w-full outline-none">
                                </div>
                                <div class="space-y-2">
                                    <label for="country" class="block text-xs font-bold text-on-surface-variant uppercase">Country</label>
                                    <input type="text" id="country" name="country" required value="<?php echo htmlspecialchars($myStore['country'] ?? 'United States'); ?>" class="bg-surface-container-low border border-outline-variant focus:border-primary text-on-surface p-2.5 rounded-lg text-sm w-full outline-none">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <label for="lat" class="block text-xs font-bold text-on-surface-variant uppercase">Latitude</label>
                                    <input type="text" id="lat" name="lat" required readonly value="<?php echo htmlspecialchars($storeLat); ?>" class="bg-surface-container-high border border-outline-variant text-on-surface p-2.5 rounded-lg text-sm font-mono w-full outline-none select-all">
                                </div>
                                <div class="space-y-2">
                                    <label for="lng" class="block text-xs font-bold text-on-surface-variant uppercase">Longitude</label>
                                    <input type="text" id="lng" name="lng" required readonly value="<?php echo htmlspecialchars($storeLng); ?>" class="bg-surface-container-high border border-outline-variant text-on-surface p-2.5 rounded-lg text-sm font-mono w-full outline-none select-all">
                                </div>
                            </div>
                        </div>

                        <div class="pt-6">
                            <button type="submit" class="w-full py-3 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-xl hover:brightness-110 active:scale-95 transition-all border-0 shadow-md cursor-pointer">
                                Save Geolocation &amp; Radius
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Footer inside scrollable wrapper -->
        <footer class="w-full border-t border-outline-variant bg-background shrink-0 mt-12 pt-6">
            <div class="flex flex-col md:flex-row justify-between items-center w-full max-w-container-max-width mx-auto">
                <p class="font-body-sm text-body-sm text-on-surface-variant mb-0">© 2024 TestShare Enterprise. All rights reserved.</p>
                <div class="flex gap-6 mt-4 md:mt-0">
                    <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors text-decoration-none" href="#">Privacy Policy</a>
                    <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors text-decoration-none" href="#">Terms of Service</a>
                </div>
            </div>
        </footer>
    </div>
</div>

<script>
    let map, marker, radiusCircle;
    const initialLat = <?php echo json_encode($storeLat); ?>;
    const initialLng = <?php echo json_encode($storeLng); ?>;
    const initialRadius = <?php echo json_encode($storeRadius); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Leaflet Map
        map = L.map('sellerMap').setView([initialLat, initialLng], 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(map);

        // Add Draggable Marker
        marker = L.marker([initialLat, initialLng], { draggable: true }).addTo(map);

        // Add Geofence Radius Circle
        radiusCircle = L.circle([initialLat, initialLng], {
            radius: initialRadius * 1000,
            color: '#ff9f0d',
            fillColor: '#ff9f0d',
            fillOpacity: 0.15,
            weight: 2
        }).addTo(map);

        // Marker Drag Event
        marker.on('dragend', function(e) {
            const pos = marker.getLatLng();
            updateCoords(pos.lat, pos.lng);
            radiusCircle.setLatLng(pos);
            reverseGeocode(pos.lat, pos.lng);
        });

        // Map Click Event
        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            radiusCircle.setLatLng(e.latlng);
            updateCoords(e.latlng.lat, e.latlng.lng);
            reverseGeocode(e.latlng.lat, e.latlng.lng);
        });
    });

    function updateCoords(lat, lng) {
        document.getElementById('lat').value = parseFloat(lat).toFixed(6);
        document.getElementById('lng').value = parseFloat(lng).toFixed(6);
    }

    function updateRangeText(value) {
        const valFloat = parseFloat(value);
        document.getElementById('rangeValue').innerText = valFloat.toFixed(1) + ' km';
        if (radiusCircle) {
            radiusCircle.setRadius(valFloat * 1000);
        }
    }

    function detectGPSLocation() {
        if ('geolocation' in navigator) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                map.setView([lat, lng], 15);
                marker.setLatLng([lat, lng]);
                radiusCircle.setLatLng([lat, lng]);
                updateCoords(lat, lng);
                reverseGeocode(lat, lng);
            }, function(err) {
                alert('GPS location detection failed: ' + err.message);
            }, { enableHighAccuracy: true });
        } else {
            alert('Geolocation is not supported by your browser.');
        }
    }

    function reverseGeocode(lat, lng) {
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
            .then(res => res.json())
            .then(data => {
                if (data && data.address) {
                    const addr = data.address;
                    const street = addr.road || addr.suburb || addr.neighbourhood || addr.amenity || '';
                    const city = addr.city || addr.town || addr.village || addr.county || '';
                    const zip = addr.postcode || '';
                    const country = addr.country || 'United States';

                    if (street) document.getElementById('address').value = street;
                    if (city) document.getElementById('city').value = city;
                    if (zip) document.getElementById('zip').value = zip;
                    if (country) document.getElementById('country').value = country;
                }
            })
            .catch(err => console.log('Reverse geocode error:', err));
    }
</script>

<?php view('partials/seller_footer'); ?>
